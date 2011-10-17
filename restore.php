<?php
// Restore backup file -- move current file to backup directory, copy backup
// file to regular directory, set its file modification time.

error_reporting(E_ALL);

include "globals.php";

// Security check.
session_start();
if (! isset($_SESSION['wysiwygit'])) {
   $msg = "User not properly logged in. No restore.";
   $json = json_encode($msg);
   print $json;
   if ($debug[2]) {
      my_error_log("[restore.php] return_data: $json");
   }
   exit;
}


$filepath = $_REQUEST['filepath'];

$basename  = pathinfo($filepath, PATHINFO_FILENAME);
$directory = pathinfo($filepath, PATHINFO_DIRNAME);
$extension = pathinfo($filepath, PATHINFO_EXTENSION);

if ($debug[2]) {
   my_error_log("[restore.php] filepath: $filepath");
   my_error_log("[restore.php] basename: $basename");
   my_error_log("[restore.php] directory: $directory");
   my_error_log("[restore.php] extension: $extension");
}

// Whole path (e.g. /fuggle/hosts/www.berkeleyballet.org/docs/edittest/wysiwygit/index.php)
$script_filename = $_SERVER['SCRIPT_FILENAME'];

// Change backslashes followed by non-space to forward slashes.
$script_filename = preg_replace( '/\\\\(\S)/', '/$1', $script_filename);

// Take off the last part.
$script_dir = preg_replace('/\/restore.php/', '', $script_filename);
$home_dir = preg_replace('/\/[^\/]+$/', '', $script_dir);

$relative_backup_dir = str_replace("$home_dir/", '', $directory);
$relative_dir = str_replace("backup", '', $relative_backup_dir);

// Take time info off basename, add back extension.
$basename_wo_time = substr($basename, 0, -18);
$page = "$basename_wo_time.$extension";

$current_file = "$home_dir/$relative_dir/$page";

// Get time of current file.
$mtime = filemtime($current_file);

// Create backup file with that timestamp.  Path relative to home.
$timestamp = date("Y-m-d_His", $mtime);
$current_backup_fullpath = "$home_dir/backup/$relative_dir/${basename_wo_time}_$timestamp.$extension";

if ($debug[2]) {
   my_error_log("[restore.php] script_dir: $script_dir");
   my_error_log("[restore.php] home_dir: $home_dir");
   my_error_log("[restore.php] relative_backup_dir: $relative_backup_dir");
   my_error_log("[restore.php] relative_dir: $relative_dir");
   my_error_log("[restore.php] current_file: $current_file");
   my_error_log("[restore.php] current_backup_fullpath: $current_backup_fullpath");
}
$ok1 = @copy($current_file, $current_backup_fullpath);

// Replace current with copy of earlier backup.
$ok2 = false;
if ($ok1) {
   $ok2 = @copy("$filepath", $current_file);
}

// Give it timestamp of earlier backup.
$timestamp = substr($basename, -17);

//       0----+----1----+-
// E.g., 2011-09-27_123705
$ymd = substr($timestamp, 0, 10);
$h = substr($timestamp, 11, 2);
$m = substr($timestamp, 13, 2);
$s = substr($timestamp, 15, 2);
$timestr = "$ymd $h:$m:$s";
$previous_backup_time = strtotime($timestr);

$ok3 = @touch($current_file, $previous_backup_time);

if ($ok1 && $ok2) {
   $msg = "OK. Most recent $page saved to backup directory,\n"
          . "replaced with version saved " . date("n/j/Y g:i A", $previous_backup_time);
} else {
   if ($ok1) {
      $msg = "Sorry, error.  Was not able to replace current file with backup file";
   } else {
      $msg = "Sorry, error.  Was not able to make backup copy of current file,\n"
             . "so did not replace it with previous backup";
   }
}

$json = json_encode($msg);
print $json;
if ($debug[2]) {
   my_error_log("[restore.php] return_data: $json");
}

?>

