<?php

use MatthiasMullie\Minify;

/**
 * Steinhaug library for handling the caching and delivery of page
 * 
 */
class steinhaug_libs {

    var $env = array(
        'version' => 2
    );

    var $do_gzip_compress = true;
    var $gzip_compression_level = 9;
    var $headers_use_expires = false;
    var $ignore_session_cache_headers = false;
    var $optimize_output = false; // Will run some optimization schemes

    var $content_charset = 'charset=UTF-8';
    var $content_type = '';

    var $content_buffer = null;

    var $headers_to_write = [];

    var $bench_time_start = null;
    var $bench_time_end = null;
    var $bench_time_total = 0;

    var $cache_prefix  = 'ews_';
    var $cache_type = 'dat';

    var $cache_time;

    var $_id;
    var $_group;
    var $_fileName;
    var $_file;

    var $_cacheDir = null;
    var $_hashedDirectoryLevel = 0;
    var $_caching = true;
    var $_lifeTime = 2592000; //2592000;
    var $_refreshTime;
    var $_automaticSerialization = false;
    var $_fileLocking = true;
    var $_readControlType = 'md5';
    var $_writeControl = true;
    var $_readControl = true;
    var $_hashedDirectoryUmask = 0700;
    var $_pearErrorMode = 1;

    function __construct(){
        $this->env['version'] = $this->get_php_version();
        $this->cache_time = time();
    }

    /**
     * Get PHP version and cast to int for matching
     */
    function get_php_version(){
        $version = phpversion();
        $v = explode('.',$version);
        if($v>1){
            $temp = $v[0];
            unset($v[0]);
            $v = $temp . '.' . implode('',$v);
        } else {
            $v = implode('.',$v);
        }
        return $v;
    }

    function smart_sql_quote($value, $len=null){
        global $mysqli;

        if( $len !== null ){
            if(strlen((string) $value) > $len){
                $value = substr((string) $value, 0, $len);
            }
        }

        if (!is_numeric($value)) {
            $value = "'" . mysqli_real_escape_string($mysqli, (string) $value) . "'";
        } else {
            $value = mysqli_real_escape_string($mysqli, $value);
        }

        return $value;
    }



    /**
     * Set content type and charset, defaults to text/plain
     * Also prefixes cache files with name for grouping
     * 
     */
    function set_type($content_type='text/plain', $content_charset='charset=UTF-8'){

        if($content_type=='text/javascript'){
            $this->cache_prefix = 'ews_';
            $this->cache_type   = 'js';
        } else if($content_type=='text/css'){
            $this->cache_prefix = 'ews_';
            $this->cache_type   = 'css';
        } else {
            $this->cache_prefix = 'ews_';
            $this->cache_type   = 'dat2';
        }
        $this->content_type = $content_type;
        $this->content_charset = $content_charset;

        /* Reset filenames in case of wrong configuration order */
        if( $this->_cacheDir !== null )
            $this->set_cachedir($this->_cacheDir);

        /*
        echo 'content_type: ' . $content_type . "<br>\n";
        echo 'File: ' . $this->_file . "<br>\n";
        echo 'cache_prefix: ' . $this->cache_prefix . "<br>\n";
        echo 'cache_type: ' . $this->cache_type . "<br>\n"; 
        exit;
        */
    }

    /**
     * Set cache directory and generic filename
     * 
     */
    function set_cachedir($dir){

        if( file_exists($dir) and is_dir($dir) ){
            $this->_cacheDir = $dir;
        } else {
            echo '<h1>ERROR: Dir doesnt exist</h1>';
            echo $dir;
            exit;
        }

        // Calculate $this->_fileName, $this->_file
        $this->_createFileName($_SERVER['SCRIPT_NAME'], $_SERVER['QUERY_STRING']);

        /*
        echo '_SERVER[\'SCRIPT_NAME\']: ' . $_SERVER['SCRIPT_NAME'] . "<br>\n"; 
        echo '_SERVER[\'QUERY_STRING\']: ' . $_SERVER['QUERY_STRING'] . "<br>\n"; 
        echo '_cacheDir: ' . $this->_cacheDir . "<br>\n"; 
        exit;
        */
    }


    // Reference for getting into the moode:
    // http://norskwebforum.no/viewtopic.php?t=18654
    // http://norskwebforum.no/viewtopic.php?t=18632

    // $headers_use_expires
    // - adds Expires: header for 24 hours
    // $ignore_session_cache_headers
    // - purges the Pragma: and Cache-control: headers.
    // - Since we cant remove headers, we add empty ones. hotfix
    //   Cache-Control
    //   Pragma
    //
    // Usage, when using together with session_start()
    //   start_ob();
    // Usage, when NOT with session_start(), eg. css og js file
    //   start_ob(false,false);
    //
    function start_ob($headers_use_expires=false,$ignore_session_cache_headers=true,$gzip='auto'){
        global $_SERVER;
        if(((string) $gzip=='auto') AND isset($GLOBALS['Content-Encoding']))
            $gzip = $GLOBALS['Content-Encoding']; // none with IE, use e-tag instead is better.

        if( isset($_SERVER['HTTP_ACCEPT_ENCODING']) ){
            if (strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') && extension_loaded('zlib')){
                $this->do_gzip_compress = true;
                if(($gzip === false) OR ((string) $gzip == 'none'))
                    $this->do_gzip_compress = false;
            }
        }

        $this->ignore_session_cache_headers = $ignore_session_cache_headers;
        $this->headers_use_expires = $headers_use_expires;

        if( $this->_cacheDir !== null ){

            if (!function_exists('getallheaders')) {
                $headers = $this->getallheaders_polyfill();
            } else {
                $headers = getallheaders();
            }
            if(isset($headers['Pragma']) AND ($headers['Pragma']=='no-cache')){
                $this->remove();
                $this->_cacheDir = null;
            } else if(isset($headers['Cache-Control']) AND ($headers['Cache-Control']=='no-cache')){
                $this->remove();
                $this->_cacheDir = null;
            }

            $this->headers_to_write['X-Cache-Lookup'] = 'X-Cache-Lookup: HIT';
            $this->check_for_cached_result();
        } else {
            $this->headers_to_write['X-Cache-Lookup'] = 'X-Cache-Lookup: MISS';
        }

        ob_start();
    }


    /**
     * Checks for cached file in cache directory and outputs it if found
     * 
     */
    function check_for_cached_result(){

        if (($cached_data = $this->get($this->_id, $this->_group)) != false){
            $this->headers_to_write['X-Cache'] = 'X-Cache: HIT';
            $this->output_data($cached_data);

        } else {
            $this->headers_to_write['X-Cache'] = 'X-Cache: MISS';
        }

    }



    /**
    * Remove a cache file
    *
    * @param string $id cache id
    * @param string $group name of the cache group
    * @return boolean true if no problem
    * @access public
    */
    function remove($id=null, $group = 'default'){

        if($id!==null){
            $this->_setFileName($id, $group);
        }

        if( file_exists($this->_file) ){
            return $this->_unlink($this->_file);
        } else {
            return true;
        }
    }

    /**
    * Remove a file
    *
    * @param string $file complete file path and name
    * @return boolean true if no problem
    * @access private
    */
    function _unlink($file){
        if (!@unlink($file)) {
            return $this->raiseError('Cache_Lite : Unable to remove cache ! ( ' . $file . ' )', -3);
        }
        return true;
    }


    /**
    * Make a file name (with path)
    *
    * @param string $id cache id
    * @param string $group name of the group
    * @access private
    */
    function _setFileName($id, $group){

        $this->_id = $id;
        $this->_group = $group;

        $suffix = $this->cache_prefix . md5($group) . '_' . md5($id) . '.' . $this->cache_type;
        //$suffix = $this->cache_prefix . substr(md5($group),0,12) . substr(md5($id),6,12) . substr(md5($group . $id),12,12) . '.' . $this->cache_type;

        $root = $this->_cacheDir;
        if ($this->_hashedDirectoryLevel>0) {
            $hash = md5($suffix);
            for ($i=0 ; $i<$this->_hashedDirectoryLevel ; $i++) {

                if(!$i){
                    $root = $root . $this->cache_type . '/';
                } else {
                    $root = $root . substr($hash, 0, $i + 1) . '/';
                }
            }
        }
        $this->_fileName = $suffix;
        $this->_file = $root . $suffix;

        /*
        echo 'cache_type: ' . $this->cache_type . "\n";
        echo 'cache_prefix: ' . $this->cache_prefix . "\n";
        echo '_cacheDir: ' . $this->_cacheDir . "\n";
        echo '_id: ' . $this->_id . "\n";
        echo '_group: ' . $this->_group . "\n";
        echo '_fileName: ' . $this->_fileName . "\n";
        echo '_file:     ' . $this->_file . "\n";
        exit;
        */
    }

    function _createFileName($id, $group){
        return $this->_setFileName($id, $group);
    }







    /**
    * Save some data in a cache file
    *
    * @param string $data data to put in cache (can be another type than strings if automaticSerialization is on)
    * @param string $id cache id
    * @param string $group name of the cache group
    * @return boolean true if no problem (else : false or a PEAR_Error object)
    * @access public
    */
    function save($data, $id = NULL, $group = 'default'){
        if ($this->_caching) {
            if ($this->_automaticSerialization) {
                $data = serialize($data);
            }
            if ($id !== null) {
                $this->_setFileName($id, $group);
            }
            if ($this->_writeControl) {
                $res = $this->_writeAndControl($data);
                if (is_bool($res)) {
                    if ($res) {
                        return true;
                    }
                    // if $res if false, we need to invalidate the cache
                    @touch($this->_file, time() - 2*abs($this->_lifeTime));
                    return false;
                }
            } else {
                $res = $this->_write($data);
            }
            if (is_object($res)) {
	        	// $res is a PEAR_Error object
                if (!($this->_errorHandlingAPIBreak)) {
	                return false; // we return false (old API)
	            }
	        }
            return $res;
        }
        return false;
    }


    /**
    * Write the given data in the cache file
    *
    * @param string $data data to put in cache
    * @return boolean true if ok (a PEAR_Error object else)
    * @access private
    */
    function _write($data){
        if ($this->_hashedDirectoryLevel > 0) {
            $hash = md5($this->_fileName);
            $root = $this->_cacheDir;
            for ($i=0 ; $i<$this->_hashedDirectoryLevel ; $i++) {

                if(!$i){
                    $root = $root . $this->cache_type . '/';
                } else {
                    $root = $root . substr($hash, 0, $i + 1) . '/';
                }

                if (!(@is_dir($root))) {
                    @mkdir($root, $this->_hashedDirectoryUmask);
                }
            }
        }
        $fp = @fopen($this->_file, "wb");

        if ($fp) {
            if ($this->_fileLocking) @flock($fp, LOCK_EX);
            if ($this->_readControl) {
                @fwrite($fp, $this->_hash($data, $this->_readControlType), 32);
            }

            $len = strlen($data);
            @fwrite($fp, $data, $len);
            if ($this->_fileLocking) @flock($fp, LOCK_UN);
            @fclose($fp);
            return true;
        }
        return $this->raiseError('Cache_Lite : Unable to write cache file : '.$this->_file, -1);
    }

    /**
    * Write the given data in the cache file and control it just after to avoir corrupted cache entries
    *
    * @param string $data data to put in cache
    * @return boolean true if the test is ok (else : false or a PEAR_Error object)
    * @access private
    */
    function _writeAndControl($data){
        $result = $this->_write($data);
        if (is_object($result)) {
            return $result;
        }
        $dataRead = $this->_read();
        if (is_object($dataRead)) {
            return $result;
        }
        if ((is_bool($dataRead)) && (!$dataRead)) {
            return false;
        }
        return ($dataRead==$data);
    }

    /**
    * Test if a cache is available and (if yes) return it
    *
    * @param string $id cache id
    * @param string $group name of the cache group
    * @param boolean $doNotTestCacheValidity if set to true, the cache validity won't be tested
    * @return string data of the cache (else : false)
    * @access public
    */
    function get($id, $group = 'default', $doNotTestCacheValidity = false){
        $this->_id = $id;
        $this->_group = $group;
        $data = false;
        if ($this->_caching) {
            $this->_setRefreshTime();
            $this->_setFileName($id, $group);
            clearstatcache();

            if ((is_null($this->_refreshTime))) {
                if (file_exists($this->_file)) {
                    $data = $this->_read();
                }
            } else {

                if ((file_exists($this->_file)) && (@filemtime($this->_file) > $this->_refreshTime)) {
                    $data = $this->_read();
                }
            }
            if (($this->_automaticSerialization) and (is_string($data))) {
                $data = unserialize($data);
            }
            return $data;
        }
        return false;
    }
    /**
    * Compute & set the refresh time
    *
    * @access private
    */
    function _setRefreshTime(){
        if (is_null($this->_lifeTime)) {
            $this->_refreshTime = null;
        } else {
            $this->_refreshTime = time() - $this->_lifeTime;
        }
    }


    /**
    * Read the cache file and return the content
    *
    * @return string content of the cache file (else : false or a PEAR_Error object)
    * @access private
    */
    function _read(){

        $this->cache_time = filemtime($this->_file);

        $fp = @fopen($this->_file, "rb");
        if ($this->_fileLocking) @flock($fp, LOCK_SH);
        if ($fp) {
            clearstatcache();
            $length = @filesize($this->_file);
            //$mqr = get_magic_quotes_runtime();
            //set_magic_quotes_runtime(0);
            if ($this->_readControl) {
                $hashControl = @fread($fp, 32);
                $length = $length - 32;
            }
            if ($length) {
                $data = @fread($fp, $length);
            } else {
                $data = '';
            }

            //set_magic_quotes_runtime($mqr);
            if ($this->_fileLocking) @flock($fp, LOCK_UN);
            @fclose($fp);
            if ($this->_readControl) {
                $hashData = $this->_hash($data, $this->_readControlType);
                if ($hashData != $hashControl) {
                    if (!(is_null($this->_lifeTime))) {
                        @touch($this->_file, time() - 2*abs($this->_lifeTime));
                    } else {
                        @unlink($this->_file);
                    }
                    return false;
                }
            }
            return $data;
        }
        return $this->raiseError('Cache_Lite : Unable to read cache !', -2);
    }

    /**
    * Make a control key with the string containing datas
    *
    * @param string $data data
    * @param string $controlType type of control 'md5', 'crc32' or 'strlen'
    * @return string control key
    * @access private
    */
    function _hash($data, $controlType){
        switch ($controlType) {
            case 'md5':
                return md5($data);
            case 'crc32':
                return sprintf('% 32d', crc32($data));
            case 'strlen':
                return sprintf('% 32d', strlen($data));
            default:
                return $this->raiseError('Unknown controlType ! (available values are only \'md5\', \'crc32\', \'strlen\')', -5);
        }
    }

    /**
     * End content creation and output page to browser
     * 
     * @param string $content_type
     * @param string $content_charset
     */
    function end_ob($content_type=null, $content_charset='charset=UTF-8'){

        if( $content_type !== null ){
            $this->content_type = $content_type;
            $this->content_charset = $content_charset;
        }

        $contents = $this->_get_contents($this->content_type, $this->content_charset);

        if( $this->_cacheDir !== null ){
            $this->save($contents, $this->_id, $this->_group);
        }

        $this->output_data($contents);
        if( $this->content_buffer !== null )
            echo $this->content_buffer;

    }

    /**
     * Finnish buffering and collect buffered contents
     */
    function _get_contents($content_type, $content_charset='charset=UTF-8'){
            if($this->optimize_output){
                $contents = $this->optimize_code(ob_get_contents(),$this->content_type);
            } else {
                $contents = ob_get_contents();
            }
            ob_end_clean();
            return $contents;
    }

    /**
     * We want Last-Modified: and NOT Expires: on files!
     */
    function output_data($contents){

        if (!function_exists('getallheaders')) {
            $headers = $this->getallheaders_polyfill();
        } else {
            $headers = getallheaders();
        }
        $headers_to_write = $this->headers_to_write;

        $last_modified  = $this->cache_time;
        $etag = sprintf( '"%s-%s"', $last_modified, crc32( $contents ) );

        if($this->do_gzip_compress){

            $gzip_size = strlen($contents);
            $gzip_crc = crc32($contents);
            $contents = gzcompress($contents, $this->gzip_compression_level);
            $contents = substr($contents, 0, strlen($contents) - 4);
            $contents = "\x1f\x8b\x08\x00\x00\x00\x00\x00" . $contents . pack('V', $gzip_crc) . pack('V', $gzip_size);
            $length = strlen($contents);

            $headers_to_write['Content-type'] = "Content-type: " . $this->content_type . ( strlen($this->content_charset) ? '; ' . $this->content_charset : '' );
            $headers_to_write['Accept-Ranges'] = 'Accept-Ranges: bytes';
            $headers_to_write['Content-Length'] = 'Content-Length: ' . $length;

            if(isset($headers['If-None-Match']) && preg_match("/" . $etag . "/", $headers['If-None-Match'])){
                $headers_to_write['http'] = 'HTTP/1.1 304 Not Modified';
                $headers_to_write['ETag'] = 'ETag: ' . $etag;

                foreach($headers_to_write AS $data_line){
                    header($data_line);
                }

            } else {
                if(($this->content_type == 'text/css') OR ($this->content_type == 'text/javascript')){
                    $this->headers_use_expires = false;
                    $headers_to_write['Last-Modified'] = "Last-Modified: " . gmdate( "D, d M Y H:i:s", $last_modified ) . " GMT";
                }
                $headers_to_write['ETag'] = 'ETag: ' . $etag;
                $headers_to_write['Content-Encoding'] = 'Content-Encoding: gzip';
                if($this->ignore_session_cache_headers){
                    $headers_to_write['Pragma'] = 'Pragma: '; // Voids the header, drops it.
                    $headers_to_write['Cache-control'] = 'Cache-control: '; // Voids the header, drops it.
                }
                $headers_to_write['Accept-Ranges'] = 'Accept-Ranges: bytes';
                $headers_to_write['Content-Length'] = 'Content-Length: ' . $length;
                $headers_to_write['Content-type'] = "Content-type: " . $this->content_type . ( strlen($this->content_charset) ? '; ' . $this->content_charset : '' );
                if($this->headers_use_expires){
                    $expiresOffset = 3600 * 24 * 1;  // Expire in 1 days";
                    $headers_to_write['Expires'] = "Expires: " . gmdate("D, d M Y H:i:s", $last_modified + $expiresOffset) . " GMT";
                    $headers_to_write['Cache-control'] = 'cache-control: max-age=' . $expiresOffset;
                }

                /* More headers to look into
                $headers_to_write['access-control-allow-origin']    = 'access-control-allow-origin: *';
                $headers_to_write['content-security-policy']        = 'content-security-policy: default-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https: data:';
                $headers_to_write['referrer-policy']                = 'referrer-policy: origin';
                $headers_to_write['vary']                           = 'vary: Accept-Encoding';
                $headers_to_write['status']                         = 'status: 200';
                $headers_to_write['X-Cache']                        = 'X-Cache: HIT/MISS';          //  Did we use cached data
                $headers_to_write['X-Cache-Lookup']                 = 'X-Cache-Lookup: HIT/MISS';   //  Are cached data an option at all
                */

                foreach($headers_to_write AS $data_line){
                    header($data_line);
                }

                $this->content_buffer = $contents;

            }
        } else {

            $length = strlen($contents);

            if(isset($headers['If-None-Match']) && preg_match("/" . $etag . "/", $headers['If-None-Match'])){

                $headers_to_write['http'] = 'HTTP/1.1 304 Not Modified';
                $headers_to_write['ETag'] = 'ETag: ' . $etag;

                foreach($headers_to_write AS $data_line){
                    header($data_line);
                }

            } else {
                if(($this->content_type == 'text/css') OR ($this->content_type == 'text/javascript')){
                    $this->headers_use_expires = false;
                    $headers_to_write['Last-Modified'] = "Last-Modified: " . gmdate( "D, d M Y H:i:s", $last_modified ) . " GMT";
                }
                $headers_to_write['ETag'] = 'ETag: ' . $etag;
                if($this->ignore_session_cache_headers){
                    $headers_to_write['Pragma'] = 'Pragma: '; // Voids the header, drops it.
                    $headers_to_write['Cache-control'] = 'Cache-control: '; // Voids the header, drops it.
                }

                $headers_to_write['Accept-Ranges'] = 'Accept-Ranges: bytes';
                $headers_to_write['Content-Length'] = 'Content-Length: ' . $length;
                $headers_to_write['Content-Type'] = "Content-type: " . $this->content_type . ( strlen($this->content_charset) ? '; ' . $this->content_charset : '' );

                if($this->headers_use_expires) {
                    $expiresOffset = 3600 * 24 * 1;  // Expire in 1 days";
                    $headers_to_write['Expires'] = "Expires: " . gmdate("D, d M Y H:i:s", $last_modified + $expiresOffset) . " GMT";
                    $headers_to_write['Cache-control'] = 'cache-control: max-age=' . $expiresOffset;
                }

                foreach($headers_to_write AS $data_line){
                    header($data_line);
                }

                $this->content_buffer = $contents;
            }
        }
    }

    /**
     * Optimization techniques for the different content types delivered.
     */
    function optimize_code($data,$type){
        
        if($type=='text/javascript'){

            $minifier = new Minify\JS( $data );
            $data = $minifier->minify();

        } else if($type=='text/css'){

            $minifier = new Minify\CSS( $data );
            $data = $minifier->minify();

        }

        return $data;

    }


    function html_turbo_compressor($buffer, $only_comments=false, $xtra_header=false) {

        // This needs to be first so that it can eat other comments
        // Removing: <!---EOF whatever EOF--->
        $buffer = preg_replace('#<!---EOF[^\\[<>].*?(?<!!)EOF--->#s', '', $buffer);

        if(!$xtra_header){
            //$buffer = preg_replace('@<!--[^>]*-->@', '', $buffer); // Basic easy fail
            //$buffer = preg_replace('#<!--[\s\S]*?-->#', '', $buffer); // Basic, seems to work
            //$buffer = preg_replace('#<!-- /[^\\[<>].*?(?<!!) -->#m', '', $buffer);      // Removing: <!-- /whatever -->
            //$buffer = preg_replace('@(?=<!--)([\s\S]*?-->)@', '', $buffer); // Basic, best
        } else {    
            //$buffer = preg_replace('@(?=<!--)([\s\S]*?-->)@', '', $buffer); // Basic, best
        }

        // Removing all all comments, preserving conditional statments, attempt to be smart enough to not break by coments in comments and works multiline
        // Removing: <!--.*--> 
        $buffer = preg_replace('@<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->@s', '', $buffer);

        if($only_comments)
            return $buffer;

            $buffer = preg_replace("/\s+/", ' ', $buffer);

            $buffer = str_replace("</td> </tr>", '</td></tr>', $buffer);
            $buffer = str_replace("</li> </ul>", '</li></ul>', $buffer);
            $buffer = str_replace("</li> <li",   '</li><li', $buffer);
            $buffer = str_replace("</td> <td",   '</td><td', $buffer);

            if($xtra_header){
                $buffer = str_replace("> <script", '><script', $buffer);
                $buffer = str_replace("> <link",   '><link', $buffer);
                $buffer = str_replace("> <meta",   '><meta', $buffer);
        }

        return $buffer;
    }

    function minify($code){
        if($this->content_type == 'text/javascript'){
            return $this->minify_js($code);
        } else if($this->content_type == 'text/css'){
            return $this->minify_css($code);
        } else if($this->content_type == 'text/html'){
            return $this->minify_html($code);
        }
    }
    function minify_js($js){
        return $this->optimize_code($js,'text/javascript') . "\n";
    }
    function minify_css($css){
        return $this->optimize_code($css,'text/css') . "\n";
    }
    function minify_html($html,$only_comments=false, $xtra_header=false){
        return $this->html_turbo_compressor($html, $only_comments, $xtra_header) . "\n";
    }

    function secure_id($id,$allowed_strings=NULL){
        if(!is_array($allowed_strings) AND strlen($allowed_strings))
            $as = array($allowed_strings);
            else if(is_array($allowed_strings))
            $as = $allowed_strings;
            else
            $as = array();
        if(count($as) AND in_array($id,$as)){
            return $id;
        } else if(is_numeric($id)){
            return (int) $id;
        } else {
            return 0;
        }
    }
    function is_secure_id($id,$allowed_strings=NULL){
        if(!is_array($allowed_strings) AND strlen($allowed_strings))
            $as = array($allowed_strings);
            else if(is_array($allowed_strings))
            $as = $allowed_strings;
            else
            $as = array();
        if(count($as) AND in_array($id,$as)){
            return true;
        } else if(is_numeric($id)){
            return true;
        } else {
            return false;
        }
    }
    function microtime_float(){
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
    function bench_start(){
        $this->bench_time_start = $this->microtime_float();
    }
    function bench_stopp(){
        $this->bench_time_total = $this->bench_time_total + ( $this->microtime_float() - $this->bench_time_start );
    }
    function bench_time(){
        return $this->bench_time_total;
    }


    function read_and_write_to_buffer($files, $verbose=false, $mode='auto'){

        if( $mode == 'auto' ){
            $mode = $this->cache_type;
        }

        $setName = '';
        if( isset($_GET['set']) )
            $setName = $_GET['set'];

        foreach($files AS $file){
            if(file_exists($file[0])){
                if( $mode == 'js' ){
                    echo 'console.log("Concat ' . $setName . ' loaded, ' . basename($file[0]) . '");' . "\n";
                } else if( $mode == 'css' ){
                    echo '/*! Set ' . $setName . ' loaded, ' . basename($file[0]) . ' */' . "\n";
                }
                if( isset($file[1]) AND $file[1] ){
                    $data = file_get_contents($file[0]);
                    echo $this->minify($data) . "\n";
                } else {
                    readfile ($file[0]);
                    echo "\n";
                }
            
                //if($verbose)
                //    echo 'console.log("Concat ' . $setName . ' load, ' . basename($file[0]) . ' loaded!");' . "\n";

            } else {
                if( $mode == 'js' ){
                    echo 'console.warn("Concat ' . $setName . ' load, ' . basename($file[0]) . ' not found!");' . "\n";
                } else if( $mode == 'css' ){
                    echo '/*! Set ' . $setName . ' loaded, ' . basename($file[0]) . ' not found! */' . "\n";
                }
            }
        }
        if( $mode == 'js' ){
            if($verbose) echo "\nconsole.info(\"Optimization reduced " . (count($files) - 1) . " HTTP requests.\");\n";
        } else if( $mode == 'css' ){
            if($verbose) echo "\n/*! Optimization reduced " . (count($files) - 1) . " HTTP requests. */\n";
        }
    }

    /**
     * Polyfill of getallheaders
     * Maintained: https://github.com/ralouphie/getallheaders/blob/master/src/getallheaders.php
     */
    function getallheaders_polyfill(){
        $headers = array();
        $copy_server = array(
            'CONTENT_TYPE'   => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5'    => 'Content-Md5',
        );
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $key = substr($key, 5);
                if (!isset($copy_server[$key]) || !isset($_SERVER[$key])) {
                    $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                    $headers[$key] = $value;
                }
            } elseif (isset($copy_server[$key])) {
                $headers[$copy_server[$key]] = $value;
            }
        }
        if (!isset($headers['Authorization'])) {
            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
                $basic_pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
                $headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basic_pass);
            } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
            }
        }
        return $headers;
    }

    /**
    * Trigger a PEAR error
    *
    * To improve performances, the PEAR.php file is included dynamically.
    * The file is so included only when an error is triggered. So, in most
    * cases, the file isn't included and perfs are much better.
    *
    * @param string $msg error message
    * @param int $code error code
    * @access public
    */
    function raiseError($msg, $code){

        echo '<h1>error ' . $code . '</h1>' . "\n";
        echo '<p>' . $msg . '</p>';
        exit;

    }


    function start($headers_use_expires=false,$ignore_session_cache_headers=true,$gzip='auto'){
        global $_SERVER;

        if(empty($gzip))
            die('Missing $gzip parameter, empty.');

        if(((string) $gzip=='auto') AND isset($GLOBALS['Content-Encoding']))
            $gzip = $GLOBALS['Content-Encoding']; // none with IE, use e-tag instead is better.

        if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') && extension_loaded('zlib')){
            $this->do_gzip_compress = true;
            if(($gzip === false) OR ((string) $gzip == 'none'))
                $this->do_gzip_compress = false;
        }

        //$this->do_gzip_compress = false;

        $this->ignore_session_cache_headers = $ignore_session_cache_headers;
        $this->headers_use_expires = $headers_use_expires;
        ob_start();
    }

    // We want Last-Modified: and NOT Expires: on files!
    function end($content_type='text/html', $content_charset='charset=UTF-8'){

        if(empty($content_type))
            $content_type = 'text/html';

        // Don't send extraneous entity-headers on a 304 as per RFC 2616 section 10.3.5
        // Fixed in v4.4.0, http://bugs.php.net/33057
        if((floor($this->get_php_version())==4) && ($this->get_php_version()>=4.4)){
            $Etag = true;
            $headers = getallheaders();
        } else {
            $Etag = false;
        }

        $Etag = true;
        $headers = getallheaders();

        if($this->do_gzip_compress){
            if($this->optimize_output)
                $gzip_contents = $this->optimize_code(ob_get_contents(),$content_type);
                else
                $gzip_contents = ob_get_contents();

            if($content_charset == 'charset=utf-8')
                $gzip_contents = utf8_encode($gzip_contents);
            ob_end_clean();

            $gzip_size = strlen($gzip_contents);
            $gzip_crc = crc32($gzip_contents);
            $gzip_contents = gzcompress($gzip_contents, $this->gzip_compression_level);
            $gzip_contents = substr($gzip_contents, 0, strlen($gzip_contents) - 4);
            $contents = "\x1f\x8b\x08\x00\x00\x00\x00\x00" . $gzip_contents . pack('V', $gzip_crc) . pack('V', $gzip_size);
            $length = strlen($contents);
            $hash = md5($contents);
            if($Etag && isset($headers['If-None-Match']) && preg_match("/" . $hash . "/", $headers['If-None-Match'])){
                header('HTTP/1.1 304 Not Modified');
                /* :TODO:
                    Problem is PHP inserts this header, so we need to remove it - which this doesnt do, however we nullify it */
                header('Cache-control: ');
            } else {
                if(($content_type == 'text/css') OR ($content_type == 'text/javascript')) {
                    $this->headers_use_expires = false;
                    header('Last-Modified: ' . date('D') . ', ' . date('j') . ' ' . date('M') . ' ' . date('Y') . ' ' . date('G') . ':' . date('i') . ':' . date('s') . ' GMT');
                }
                if($Etag){
                    header("ETag: \"$hash\"");
                }
                header('Content-Encoding: gzip');
                if($this->ignore_session_cache_headers){
                    // :TODO:
                    // We really want to remove this headers completely, this way we atleast gets them nullified
                    header('Pragma: ');
                    header('Cache-control: '); // private, set to nothing seems to override the header correctly
                }
                header('Accept-Ranges: bytes');
                header('Content-Length: ' . $length);
                header("Content-type: " . $content_type . ( strlen($content_charset) ? '; ' . $content_charset : '' ) );
                if($this->headers_use_expires) {
                    $expiresOffset = 3600 * 24 * 1;  // Expire in 1 days";
                    header("Expires: " . gmdate("D, d M Y H:i:s", time() + $expiresOffset) . " GMT");
                }
                echo $contents;
            }
        } else {
            if($this->optimize_output){
                $contents = $this->optimize_code(ob_get_contents(),$content_type);
                $length = strlen($contents);
            } else {
                $length = ob_get_length();
                $contents = ob_get_contents();
            }
            if($content_charset == 'charset=utf-8')
                $contents = utf8_encode($contents);
            $length = strlen($contents);
            ob_end_clean();
            $hash = md5($contents);
            if($Etag && isset($headers['If-None-Match']) && preg_match("/" . $hash . "/", $headers['If-None-Match'])){
                header('HTTP/1.1 304 Not Modified');
            } else {
                if(($content_type == 'text/css') OR ($content_type == 'text/javascript')) {
                    $this->headers_use_expires = false;
                    header('Last-Modified: ' . date('D') . ', ' . date('j') . ' ' . date('M') . ' ' . date('Y') . ' ' . date('G') . ':' . date('i') . ':' . date('s') . ' GMT');
                }
                if($Etag){
                    header("ETag: \"$hash\"");
                }
                if($this->ignore_session_cache_headers){
                    // :TODO:
                    // We really want to remove this headers completely, this way we atleast gets them nullified
                    header('Pragma: ');
                    header('Cache-control: '); // private, set to nothing seems to override the header correctly
                }
                header('Accept-Ranges: bytes');
                header('Content-Length: ' . $length);
                header("Content-type: " . $content_type . ( strlen($content_charset) ? '; ' . $content_charset : '' ) );
                if($this->headers_use_expires) {
                    $expiresOffset = 3600 * 24 * 1;  // Expire in 1 days";
                    header("Expires: " . gmdate("D, d M Y H:i:s", time() + $expiresOffset) . " GMT");
                }
                echo $contents;
            }
        }
    }

}