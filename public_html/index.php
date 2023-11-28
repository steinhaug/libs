<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'func.inc.php';

// Autoload files using the Composer autoloader.
require_once '../vendor/autoload.php';

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

$GLOBALS['Content-Encoding'] = 'gzip';
$swlib->start_ob(false, true, $GLOBALS['Content-Encoding']);
?>
<!DOCTYPE html>
<html lang="no">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="x-ua-compatible" content="ie=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>steinhaug_libs class</title>

    <link rel="icon" href="images/favicon.png" />
  </head>
  <body>
    <h1>Hello steinhaug_libs <?=time()?>!</h1>
    <p>Run the /public_html for tests and usage..</p>
  </body>
</html>
<?php
$swlib->end_ob('text/html');
?>