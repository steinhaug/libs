<?php
// Load environment
require 'func.inc.php';

$GLOBALS['Content-Encoding'] = 'gzip';

if(_GET('mode')=='a'){
    $swlib = new steinhaug_libs;
    $swlib->start();
} else {
    $swlib = new steinhaug_libs;
    $swlib->start_ob(false, true, $GLOBALS['Content-Encoding']);
}

?>
<!DOCTYPE html>
<html lang="no">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="x-ua-compatible" content="ie=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>steinhaug_libs_v2 class</title>

    <link rel="icon" href="images/favicon.png" />
  </head>
  <body>
    <h1>Hello steinhaug_libs <?=time()?>!</h1>
    <p>Run the /public_html for tests and usage..</p>

    <p>
        <a href="index.php">steinhaug_libs_v1</a> - 
        <a href="libs2.php?mode=a">steinhaug_libs a (v1)</a> - 
        <a href="libs2.php?mode=b">steinhaug_libs b (v2)</a> - 
        <a href="css.php">css bundle</a> - 
        <a href="css.php">js bundle</a>
    </p>

  </body>
</html>
<?php
if(_GET('mode')=='a'){
    $swlib->end();
} else {
    $swlib->end_ob('text/html');
}
