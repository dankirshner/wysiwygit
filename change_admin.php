<?php
// Change administrative flag.

error_reporting(E_ALL);

include "globals.php";

$username = $_REQUEST['username'];
$admin    = $_REQUEST['admin'] == 1;   // As boolean.

if ($debug[1]) {
   my_error_log("[reset_password.php] username: $username, admin: $admin");
}

// Security check.
session_start();
if (! isset($_SESSION['wysiwygit_admin'])) {
   $errmsg = "You are not logged in as an administrative user.";
} else {

   $errmsg = "";

   // Read user's file.
   $userfile = "users/$username.txt";
   $lines = @file($userfile);
   if (! $lines) {
      $errmsg = "Could not find $userfile file";
   } else {

      // Find info line.
      $ok_f = false;
      $n_lines = count($lines);
      for ($i=0; $i<$n_lines; $i++) {

         // Skip comments and blank lines.
         $line = $lines[$i];
         $line = trim($line);
         if ($line{0} == '#' || $line == '') {
            continue;
         }
         $info = json_decode($line);
         if ($info->username == $username) {

            // Found line for this user.  Delete from array of lines.
            unset($lines[$i]);
            $ok_f = true;
            break;
         }
      }
      if ($ok_f) {

         // Add new line with new data.  Reset info.
         $info->admin = $admin;
         $line = json_encode($info) . "\n";

         // Add line.
         $lines[] = $line;
         $file_string = implode('', $lines);
         file_put_contents($userfile, $file_string);
         if ($admin) {
            $errmsg = "OK.  User $username now has administrative privileges";
         } else {
            $errmsg = "OK.  User $username now is not an administrator";
         }
         $errmsg .= " (" . date("g:i") . ")";
      } else {
         $errmsg = "Could not find info in $userfile file";
      }
   }
}

$return_data = $errmsg;

$json = json_encode($return_data);
print $json;
if ($debug[1]) {
   my_error_log("[reset_password.php] return_data: $json");
}

?>
