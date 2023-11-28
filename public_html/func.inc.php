<?php


if(!function_exists('ob_flush')){ function ob_flush() { return true; }} // Patch for DG 80.64.202.13 server
if(!function_exists('same_length')){ function same_length($a,$b,$s=' '){ if(strlen((string) $a) == strlen((string) $b)) return array($a,$b); if(strlen((string) $a) > strlen((string) $b)){ while(strlen((string) $a) > strlen((string) $b)){ $b .= $s; } return array($a,$b); } else { while(strlen((string) $a) < strlen((string) $b)){ $a .= $s; } return array($a,$b);} return array($a,$b);}}
if(!function_exists('getallheaders')){
    function getallheaders() {
        $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (str_starts_with($name, 'HTTP_')){
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        return $headers;
    }
}

/**
 * Seperate a block of code by sub blocks. Example, removing all <script>...<script> tags from HTML kode
 * Original sollution: https://stackoverflow.com/questions/27078259/get-string-between-find-all-occurrences-php
 * 
 * Lives kundeweb
 * 
 * @param string $str, text block
 * @param string $startDelimiter, string to match for start of block to be extracted
 * @param string $endDelimiter, string to match for ending the block to be extracted
 * @param string $removeDelimiters, remove delimiters from returned code
 * 
 * @return array [all inner blocks, all outer blocks, all blocks]
 */
function getDelimitedStrings($str, $startDelimiter, $endDelimiter, $removeDelimiters=true) {
    $contents = [];
    $startDelimiterLength = strlen($startDelimiter);
    $endDelimiterLength = strlen($endDelimiter);
    $startFrom = $contentStart = $contentEnd = $outStart = $outEnd = 0;
    while (false !== ($contentStart = strpos($str, $startDelimiter, $startFrom))) {
        $contentStart += $startDelimiterLength;
        $contentEnd = strpos($str, $endDelimiter, $contentStart);
        $outEnd = $contentStart - 1;
        if (false === $contentEnd) {
            break;
        }

        if($removeDelimiters)
            $contents['inner'][] = substr($str, $contentStart, $contentEnd - $contentStart);
            else
            $contents['inner'][] = substr($str, ($contentStart-$startDelimiterLength), ($contentEnd + ($startDelimiterLength*2) +1) - $contentStart);

        if( $outStart ){
            $contents['outer'][] = substr($str, ($outStart+$startDelimiterLength+1), $outEnd - $outStart - ($startDelimiterLength*2));
            $contents['items'][] = ['type'=>'outer','string'=>substr($str, ($outStart+$startDelimiterLength+1), $outEnd - $outStart - ($startDelimiterLength*2))];
        } else if( ($outEnd - $outStart - ($startDelimiterLength-1)) > 0 ){
            $contents['outer'][] = substr($str, $outStart, $outEnd - $outStart - ($startDelimiterLength-1));
            $contents['items'][] = ['type'=>'outer','string'=>substr($str, $outStart, $outEnd - $outStart - ($startDelimiterLength-1))];
        }

        if($removeDelimiters)
            $contents['items'][] = ['type'=>'inner','string'=>substr($str, ($contentStart), ($contentEnd) - $contentStart)];
            else
            $contents['items'][] = ['type'=>'inner','string'=>substr($str, ($contentStart-$startDelimiterLength), ($contentEnd + ($startDelimiterLength*2) +1) - $contentStart)];

        $startFrom = $contentEnd + $endDelimiterLength;
        $startFrom = $contentEnd;
        $outStart = $startFrom;
    }

    // No full block detected by delimiters, so full $str is returned
    if( !isset($contents['inner']) ){
        $contents['outer'][] = $str;
        $contents['items'][] = ['type'=>'outer','string'=>$str];
        return $contents;
    }

    $total_length = strlen($str);
    $current_position = $outStart + $startDelimiterLength + 1;
    if( $current_position < $total_length ){
        $contents['outer'][] = substr($str, $current_position);
        $contents['items'][] = ['type'=>'outer','string'=>substr($str, $current_position)];
    }

    return $contents;
}

/**
 * Helper function for getDelimitedStrings(). Returns two blocks.
 */
function getDelimitedStrings_flattened($string, $startDelimiter, $endDelimiter, $removeDelimiters=true, $glue="\n"){
    $parsed = getDelimitedStrings($string, $startDelimiter, $endDelimiter);
    return $parsed['items'];
    return [ implode($glue, $parsed['in']) , implode($glue, $parsed['out']) ];
}

