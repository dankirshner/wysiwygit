<?php
// Reset password -- rewrite info line.

error_reporting(E_ALL);

include "globals.php";

$username = $_REQUEST['username'];
$password = $_REQUEST['password'];

if ($debug[1]) {
   my_error_log("[reset_password.php] username: $username, password: $password");
}

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
      $info->password = crypt($password);
      $line = json_encode($info) . "\n";

      // Add line.
      $lines[] = $line;
      $file_string = implode('', $lines);
      file_put_contents($userfile, $file_string);
      $errmsg = "OK.  Password reset for user $username (" . date("g:i a") . ")";
   } else {
      $errmsg = "Could not find info in $userfile file";
   }
}

$return_data = $errmsg;

$json = json_encode($return_data);
print $json;
if ($debug[1]) {
   my_error_log("[reset_password.php] return_data: $json");
}

?>
