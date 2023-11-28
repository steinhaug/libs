<?php

/*             START              */
/* Some Steinhaug libraries to go */
/*                                */
class steinhaug_libs_v1
{
  var $env = array(
    'version' => 1
  );
  var $do_gzip_compress = false;
  var $gzip_compression_level = 9;
  var $headers_use_expires = false;
  var $ignore_session_cache_headers = false;
  var $optimize_output = false; // Will run some optimization schemes

  function __construct(){
    $this->env['version'] = $this->get_php_version();
  }

  /* Get the PHP version, convert it to a INT so we can match */
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
  function end_ob($content_type, $content_charset='charset=UTF-8'){
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
  function optimize_code($data,$type){
    if($type=='text/javascript'){
      $lines = explode("\n",$data);
      $data = '';
      foreach ($lines AS $l){
        $l = trim($l);
        if(strlen($l))
          $data .= $l . "\n";
      }
    } else if($type=='text/css'){
        $data = $this->css_turbo_compressor(explode("\n",$data));
    } else if($type=='text/html'){
        $data = $this->minify_page($data);
    }
    return $data;
  }
  function css_turbo_compressor($data){
      $count = count($data);
      for($i=0;$i<$count;$i++){
        // remove whitespace
        $data[$i] = preg_replace("/\s+/", ' ', $data[$i]);
        $data[$i] = trim($data[$i]);

        // minor tweaks
        $data[$i] = str_replace(' 0px',' 0',$data[$i]);

        $data[$i] = preg_replace("/\s*\{\s*/", '{', $data[$i]);
        $data[$i] = preg_replace("/\s*\}\s*/", '}', $data[$i]);
        $data[$i] = preg_replace("/\s*\;\s*/", ';', $data[$i]);
        $data[$i] = preg_replace("/\s*\:\s*/", ':', $data[$i]);

        $data[$i] = preg_replace("/#ffffff/i", '#FFF', $data[$i]);
        $data[$i] = preg_replace("/#000000/i", '#000', $data[$i]);
        $data[$i] = preg_replace("/#888888/i", '#888', $data[$i]);

        if(!strlen(trim($data[$i])))
          unset($data[$i]);
      }

    // Finnish up replaces
    $css = implode("\n",$data);
    $css = str_replace("\n}\n","}\n",$css);
    $css = str_replace("{\n","{",$css);
    $css = str_replace(";\n",";",$css);
    $css = str_replace(";}","}",$css);

    // Final 0px
    $css = str_replace(":0px",":0",$css);

    return $css;
  }

  function prepareHTMLoutput($html,$options = array(NULL),$swECMS_font_sice_css_fix=false){
    // Options should be fed when used
    require_once EWS_ROOT_PATH . '/ecms.lib/index.HTMLSax3.cleanup.php';
    $CleanWordAndHTML = new CleanWordAndHTML();
    if(isset($options['post-removespan']) AND !$options['post-removespan'])      $options['post-removespan']      = true;  // Removes empty SPAN
    if(isset($options['post-removefont']) AND !$options['post-removefont'])      $options['post-removefont']      = true;  // Removes empty FONT
    if(isset($options['post-removefont-face']) AND !$options['post-removefont-face']) $options['post-removefont-face'] = false; // Removes FONT tags with face
    if(isset($options['post-removefont-size']) AND !$options['post-removefont-size']) $options['post-removefont-size'] = false; // Removes FONT tags with size
    if(isset($options['post-remove-orphans']) AND !$options['post-remove-orphans'])  $options['post-remove-orphans']  = true;  // Removes empty orphans
    if(isset($options['post-fixentities']) AND !$options['post-fixentities'])     $options['post-fixentities']     = true;  // Correct some typical MAC characters
    unset($CleanWordAndHTML->deleteTagAttributes['font']);
    $CleanWordAndHTML->deleteTagsIfNoAttribs = array('font','span');
    $CleanWordAndHTML->swECMS_font_sice_css_fix = $swECMS_font_sice_css_fix; // Correct font sizes
    $html = $CleanWordAndHTML->parseCMS($html,$options);
    if(isset($options['run-twice']) AND $options['run-twice']){
      $CleanWordAndHTML = new CleanWordAndHTML();
      unset($CleanWordAndHTML->deleteTagAttributes['font']);
      $CleanWordAndHTML->deleteTagsIfNoAttribs = array('font','span');
      $html = $CleanWordAndHTML->parseCMS($html,$options);
    }
    unset($CleanWordAndHTML);
    return $html;
  }
  function strip_empty_param($param){
    $params = explode('&amp;',$param);
    $count = count($params);
    for($i=0;$i<$count;$i++){
      $temp = explode('=',$params[$i]);
      if(!isset($temp[1])){
        unset($params[$i]);
      } else if(!strlen($temp[1])){
        unset($params[$i]);
      } else {
      }
    }
    return implode('&amp;',$params);
  }
  // Check if string qualifies to be used inside a mysql regex, note bug in MySQL
  // http://bugs.mysql.com/bug.php?id=399
  function _regex_qualified($str){
    $str = $this->_regex_qualified_bug($str);
    $str = $this->_regex_qualified_pipe($str);
    $str = $this->_regex_qualified_parathes($str);
    return $str;
  }
  function _regex_qualified_bug($str){
    if(strpos($str,'|') !== false){
      $a = explode('|',$str);
      for($i=0;$i<count($a);$i++){
        if(preg_match("/^\*/",$a[$i])){
          $a[$i] = '\\' . $a[$i];
        }
        if(preg_match("/^\+/",$a[$i])){
          $a[$i] = '\\' . $a[$i];
        }
      }
      $str = implode('|',$a);
      return $str;
    } else {
      if(preg_match("/^\*/",$str)){
        $str = '\\' . $str;
      }
      if(preg_match("/^\+/",$str)){
        $str = '\\' . $str;
      }
    }
    return $str;
  }
  function _regex_qualified_pipe($str){
    if(preg_match("/^\|/",$str))
      $str = preg_replace("/^\|/",'',$str);
    if(preg_match("/\|$/",$str))
      $str = preg_replace("/\|$/",'',$str);
    return $str;
  }
  function _regex_qualified_parathes($str){
    $str = str_replace("(","\\(",$str);
    $str = str_replace(")","\\)",$str);
    return $str;
    // TODO! Enable () usage in query!
    // What does the () infact do in the query at all?
    if(strpos($str,'(') !== false){
      $r = preg_split('/(\()|(\))/', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
      for ($i = 0; $i < count($r); $i++){
        echo '<hr>' . $r[$i];
      }
      echo '<hr>';
      //return implode('', $r);
    }
    return $str;
  }
  function _regex_qualified_brackets($str){
    $str = str_replace(array('[',']'),array('\\[','\\]'),$str);
    $str = str_replace(array('(',')'),array('\\(','\\)'),$str);
    return $str;
    // TODO! Enable () usage in query!
    // What does the () infact do in the query at all?
    if(strpos($str,'(') !== false){
      $r = preg_split('/(\()|(\))/', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
      for ($i = 0; $i < count($r); $i++){
        echo '<hr>' . $r[$i];
      }
      echo '<hr>';
      //return implode('', $r);
    }
    return $str;
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

  /*                                                                                                        
     Quick compress for final output. This is a nice compressor with certain rules not to break anything!   
     We should add rules for things that break so that this will not become a function that dont work!      
                                                                                                          */
  function html_turbo_compressor($buffer,$section='all') {

      // regex for removing comments and NOT conditional comments
      // <!--(?!\[).*?(?!<\])-->

      //$buffer = preg_replace('#<!--/[^\\[<>].*?(?<!!)-->#s', '', $buffer);        // Removing: <!-- - - End of infoblocks - - -->
      //$buffer = preg_replace('#<!-- -[^\\[<>].*?(?<!!)- -->#s', '', $buffer);     // Removing: <!--/ .infoblocks_container -->

      //$buffer = preg_replace('/<!--\*.*\*-->/Us', '', $buffer);     // Removing: <!--* whatever *-->
      $buffer = preg_replace('/<!--\*.*?\*-->/s', '', $buffer);     // Removing: <!--* whatever *-->

      $search = array(
      //    '/\>[^\S ]+/s',  // strip whitespaces after tags, except space      
      //    '/[^\S ]+\</s',  // strip whitespaces before tags, except space     
      //    '/(\s)+/s'       // shorten multiple whitespace sequences           
            "/\s+/"          // 4: Remove whitespace                            
      );                                                                        
      $replace = array(                                                         
      //    '>',                                                                
      //    '<',                                                                
      //    '\\1'                                                               
            " "             // 4: replace with space                            
      );
      $buffer = preg_replace($search, $replace, $buffer);

    return $buffer;
  }


    function minify_page($html){
        $string = '';
        $parsed = getDelimitedStrings($html, '<script>', '</script>');
        foreach( $parsed['items'] AS $item ){
            if( $item['type'] == 'outer' ){
                $string .= $this->html_turbo_compressor_remove(trim($item['string']),false,true);
            } else {
                $string .= '<script>' . minify_js($item['string']) . '</script>';
                //$string .= '<script>' . $item['string'] . '</script>';
            }
        }
        return $string;
    }


    function html_turbo_compressor_remove($buffer,$only_comments=false, $xtra_header=false) {
        if(!$xtra_header){
            $buffer = preg_replace('#<!---EOF[^\\[<>].*?(?<!!)EOF--->#s', '', $buffer); // Removing: <!--EOF whatever EOF--->
            $buffer = preg_replace('#<!-- /[^\\[<>].*?(?<!!) -->#s', '', $buffer);      // Removing: <!-- /whatever -->
            $buffer = preg_replace('#<!--\*[^\\[<>].*?(?<!!)\*-->#s', '', $buffer);     // Removing: <!--*whatever*-->

            $buffer = preg_replace('#<!--/ [^\\[<>].*?(?<!!) -->#s', '', $buffer);      // Removing: <!--/ (.*) -->

        } else {
            $buffer = preg_replace('#<!--\*[^\\[<>].*?(?<!!)\*-->#s', '', $buffer);     // Removing: <!--*whatever*-->
        }
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
}