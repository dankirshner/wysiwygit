<?php
// Logout -- clear cookies.

error_reporting(E_ALL);

include "globals.php";

if ($debug[1]) {
   my_error_log("[logout.php]");
}

// Setting time in past clears cookies.
setcookie('wysiwygit_username', '', time() - 3600);

unset($_COOKIE['wysiwygit_username']);

// wysiwygit and KCFinder.

session_start();
unset($_SESSION['wysiwygit']);
unset($_SESSION['wysiwygit_admin']);
unset($_SESSION['KCFINDER']);

$return_data = 1;

$json = json_encode($return_data);
print $json;
if ($debug[1]) {
   my_error_log("[logout.php] return_data: $json");
}

?>
