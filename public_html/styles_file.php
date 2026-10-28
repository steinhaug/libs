<?php
// Load environment
require 'func.inc.php';

$cachedir = './cache/';


// --- INIT START
$swlib = new steinhaug_libs;
$swlib->set_type('text/css');
$swlib->set_cachedir($cachedir);
$swlib->optimize_output = false;    // compress the CSS, whole file
$swlib->start_ob(false,false);
// --- INIT END
?>

#id-<?php echo time();?>{}


<?php

$verbose = false;

$files = [
    ['js.libs/misc/gdpr-1.css', false],
    ['js.libs/misc/gdpr-2.css', true],  // compress
];
$swlib->read_and_write_to_buffer($files,$verbose);



// Load CSS file
$filea = file_get_contents('./js.libs/trans_banner/style.css');
// Correct paths
$filea = str_replace('(preloader.gif)','(./js.libs/trans_banner/preloader.gif)',$filea);
$filea = str_replace('(timer-sprite.png)','(./js.libs/trans_banner/timer-sprite.png)',$filea);
$filea = str_replace('(icons.png)','(./js.libs/trans_banner/icons.png)',$filea);
// Output the CSS
echo $filea;


// -- END START
$swlib->end_ob('text/css');
// -- END END
