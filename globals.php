<?php
date_default_timezone_set("America/Los_Angeles");

$debug[0] = true;
$debug[1] = true;  // Updating file.
$debug[2] = true;  // Backup files.

// How often lock file is touched.
$lckfile_update_interval = 60;

$demo = false;
$demo_username = 'demo';
// -----------------------------------------------------------------------------
function my_error_log($message) {
   error_log($message);
}


?>
