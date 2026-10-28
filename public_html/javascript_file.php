<?php
// Load environment
require 'func.inc.php';

$cachedir = './cache/';


// --- INIT START
$swlib = new steinhaug_libs;
$swlib->set_cachedir($cachedir);
$swlib->set_type('text/javascript');
$swlib->optimize_output = false;        // compress the JS, whole file
$swlib->start_ob(false,false);
// --- INIT END
?>

document.head.insertAdjacentHTML('beforeend', `<style>#id-<?php echo time();?>{}</style>`);

<?php
$verbose = false;

$files = [
    ['js.libs/confirm-popover.js', false],
    ['js.libs/trans_banner/ios6-timers.js', true],            // compress
    ['js.libs/trans_banner/jquery.easing.1.3.min.js', true],  // compress
    ['js.libs/trans_banner/trans-banner.min.js', true],       // compress
];
$swlib->read_and_write_to_buffer($files,$verbose);



// -- END START
$swlib->end_ob('text/javascript');
// -- END END
