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
$swlib->set_type('text/javascript');
$swlib->set_cachedir($uploadpath . $uploadCACHE);
$swlib->optimize_output = false;
$swlib->start_ob(false,false);

    $files = array(
        ['assets/1.js',false]
    );
    $swlib->read_and_write_to_buffer($files);
    $js_snippet = <<<'EOD'
// Commom Partials
(function($) {

	'use strict';

	// Sticky Header
	if (typeof theme.StickyHeader !== 'undefined') {
		theme.StickyHeader.initialize();
	}

	// Nav Menu
	if (typeof theme.Nav !== 'undefined') {
		theme.Nav.initialize();
	}

	// Search
	if (typeof theme.Search !== 'undefined') {
		theme.Search.initialize();
	}

	// Newsletter
	if (typeof theme.Newsletter !== 'undefined') {
		theme.Newsletter.initialize();
	}

	// Account
	if (typeof theme.Account !== 'undefined') {
		theme.Account.initialize();
	}

}).apply(this, [jQuery]);
EOD;
    echo $swlib->minify_js($js_snippet);

$swlib->end_ob();
