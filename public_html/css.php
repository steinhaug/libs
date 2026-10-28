<?php
// Load environment
require 'func.inc.php';

$cachedir = './cache/';


// Lib start
$swlib = new steinhaug_libs;
$swlib->set_type('text/css');
$swlib->set_cachedir($cachedir);
$swlib->optimize_output = true;
$swlib->start_ob(false,false);


    $files = array(
        ['assets/1.css',false],
        ['assets/2.css',false],
    );
    $swlib->read_and_write_to_buffer($files);
    $css_snippet = <<<'EOD'
        .css-snippet {
            color: red;
            border: 1px solid red;
            padding: 1em;
        }
EOD;
    echo $swlib->minify_css($css_snippet);


// End and output
$swlib->end_ob('text/css');
