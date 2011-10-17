<?php
// Update editing lock file -- change file modification time.

error_reporting(E_ALL);

include "globals.php";

$lckfile_fullpath = $_REQUEST['lckFile'];

if ($debug[1]) {
   my_error_log("[update_lock_file] lckfile_fullpath: $lckfile_fullpath");
}

touch($lckfile_fullpath);
?>
