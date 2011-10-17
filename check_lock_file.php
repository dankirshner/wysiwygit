<?php
// Check for editing lock file.  Also return modification time of tmpfile.

error_reporting(E_ALL);

include "globals.php";

$lckfile_fullpath = $_REQUEST['lckfile'];
$tmpfile_fullpath = $_REQUEST['tmpfile'];

if ($debug[1]) {
   my_error_log("[check_lock_file] lckfile_fullpath: $lckfile_fullpath");
}

// Two checks: lock file exists, and is current.  Give a few seconds leeway.
$editing_locked = false;
if (file_exists($lckfile_fullpath)) {
   if ($debug[1]) {
      my_error_log("[check_lock_file.php] filemtime(lckfile_fullpath): " . filemtime($lckfile_fullpath) . ", time(): " . time());
   }
   if(filemtime($lckfile_fullpath) >= time() - $lckfile_update_interval - 5) {
      $editing_locked = true;
   }
}
if (! $editing_locked) {

   // Create new lock file.  Eventually will contain user information.
   touch($lckfile_fullpath);
}

// Return flag and tmpfile time to onCheckEditingLock() function.
$tmpfile_mtime = filemtime($tmpfile_fullpath);

$return_data = array($editing_locked, $tmpfile_mtime);
$json = json_encode($return_data);
print $json;
if ($debug[1]) {
   my_error_log("[check_lock_file.php] return_data: $json");
}

?>
