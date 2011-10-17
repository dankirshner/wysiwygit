<?php
// Delete user file from users directory.

error_reporting(E_ALL);

include "globals.php";

$username = $_REQUEST['username'];

if ($debug[1]) {
   my_error_log("[delete_user.php] username: $username");
}

$errmsg = "";

$userfile = "users/$username.txt";
unlink($userfile);

$return_data = $errmsg;

$json = json_encode($return_data);
print $json;
if ($debug[1]) {
   my_error_log("[delete_user.php] return_data: $json");
}

?>
