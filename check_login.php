<?php
// Check login.

error_reporting(E_ALL);

include "globals.php";

$username = $_REQUEST['username'];
$password = $_REQUEST['password'];

if ($debug[1]) {
   my_error_log("[check_login] username: $username, password: $password");
}

$login_ok = false;
$admin = false;
$errmsg = "";

// Read user's file.
$userfile = "users/$username.txt";
$lines = @file($userfile);
if (! $lines) {
   $errmsg = "Could not find $userfile file";
} else {

   // Find info line.
   foreach ($lines as $line) {

      // Skip comments and blank lines.
      $line = trim($line);
      if ($line{0} == '#' || $line == '') {
         continue;
      }
      $info = json_decode($line);
      $login_ok = true;
      break;
   }
   if ($login_ok) {

      // Check password.
      $encrypted_password = $info->password;
      if (crypt($password, $encrypted_password) != $encrypted_password) {
         $login_ok = false;
      }
   }

   if ($login_ok) {

      // wysiwygit and KCFinder.
      session_start();
      $_SESSION['wysiwygit'] = 1;
      $_SESSION['wysiwygit_username'] = $username;
      $_SESSION['KCFINDER'] = array('disabled' => false);

      // Also set cookie.  Expire in two weeks.
      setcookie('wysiwygit_username', $username, time() + 14*24*3600);

      // Is this user an admin?
      if (isset($info->admin)) {
         if ($info->admin) {
            $admin = true;
            $_SESSION['wysiwygit_admin'] = 1;
         }
      }
   }
}

$return_data = array( $login_ok, $admin, $errmsg);

$json = json_encode($return_data);
print $json;
if ($debug[1]) {
   my_error_log("[check_login.php] return_data: $json");
}

?>
