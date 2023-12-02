<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'func.inc.php';


$uploadpath = './';
$uploadCACHE = 'cache/';


// Autoload files using the Composer autoloader.
require_once '../vendor/autoload.php';

// Initialize the DB connection
require '../mysqli_credentials.inc';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = mysqli_connect($host, $us, $password, $store);
if (mysqli_connect_errno()) {
    echo 'Problemer med databasen, sjekk konfigurasjon!';
    exit();
}
if ($mysqli->character_set_name() != 'utf8') {
    if (!$mysqli->set_charset("utf8")) {
        printf("Error loading character set utf8: %s\n", $mysqli->error);
        exit();
    }
}


$swlib = new steinhaug_libs;
$swlib->set_type('text/css');
$swlib->set_cachedir($uploadpath . $uploadCACHE);
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

$swlib->end_ob('text/css');
