<?php
// Remove editing lock file.

error_reporting(E_ALL);

include "globals.php";

$lckfile_fullpath = $_REQUEST['lckFile'];

if ($debug[1]) {
   my_error_log("[remove_lock_file] lckfile_fullpath: $lckfile_fullpath");
}

unlink($lckfile_fullpath);
?>
