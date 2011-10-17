<?php
// Admin tasks - add/delete users, set passwords, admin privileges. 

error_reporting(E_ALL);

include "globals.php";

// Security check.
session_start();
if (! isset($_SESSION['wysiwygit_admin'])) {
   $errmsg = "You are not logged in as an administrative user.";
} else {
   $errmsg = '';

   // If updating, create new user file.  Check that not a duplicate user
   // (in case page reloaded).
   if (isset($_REQUEST['username'])) {
      $username = $_REQUEST['username'];

      $userfile = "users/$username.txt";
      if (! file_exists($userfile)) {
         $admin = isset($_REQUEST['admin']);
         $password = $_REQUEST['password'];
         $encrypted_password = crypt($password);
         $info = array(
            "username" => $username,
            "password" => $encrypted_password,
            "admin" => $admin
         );
         $line = json_encode($info) . "\n";
         if ($debug[2]) {
            my_error_log("[admin.php] userfile: $userfile");
            my_error_log("[admin.php] line: $line");
         }
         file_put_contents($userfile, $line);
      }
   }

   // Read users' files.
   $userfiles = glob('users/*.txt');
   foreach ($userfiles as $userfile) {

      $lines = file($userfile);

      // Read info line into arrays over users.
      foreach ($lines as $line) {

         // Skip comments and blank lines.
         $line = trim($line);
         if ($line{0} == '#' || $line == '') {
            continue;
         }
         $info = json_decode($line);
         $usernames[] = $info->username;
         if (isset($info->admin)) {
            $admins[] = $info->admin;
         } else {
            $admins[] = '';
         }
         break;
      }
   }

   // Sort.
   array_multisort($usernames, $admins);
}


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
   <title>
      Admin
   </title>
   <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.5/jquery.min.js">
   </script>
   <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js">
   </script>

   <link rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/themes/base/jquery-ui.css" type="text/css" media="all" />

   <link rel="stylesheet" type="text/css" href="wysiwygit.css" />

   <script type="text/javascript">
      //<![CDATA[

      $(document).ready(function() {
         $('#adduser_dialog').dialog({
            autoOpen:   false,
            height:     400,
            width:      400,
            modal:      true,
            buttons:    {
               "Add user": addUser,
               "Cancel":   function() { $(this).dialog('close'); }
            }
         });
         $('#reset_password_dialog').dialog({
            autoOpen:   false,
            height:     400,
            width:      400,
            modal:      true,
            buttons:    {
               "Reset password": resetPassword,
               "Cancel":   function() { $(this).dialog('close'); }
            }
         });
      });

      // Transfer user names to javascript.
      var usernames = new Array();
      <?php 
      foreach ($usernames as $username) {
         $username = preg_replace("/'/", "\\'", $username);
         print "usernames.push('$username');\n";
      }
      ?>


      var addUser = function() {
         var errmsg = '';

         // Validate form, submit (this page).
         // Check user not already there.
         var username = $('#username').val();
         username = username.replace(/\s/, '');
         if ($.inArray(username, usernames) != -1) {
            errmsg += 'User name ' + username + ' is already being used.\n';
         }

         var password = $('#password').val();
         password = password.replace(/\s/, '');
         if (password.length < 4) {
            errmsg += 'Password must have at least four characters.\n';
         }
         var confirm_password = $('#confirm_password').val();
         if (password != confirm_password) {
            errmsg += 'Password and confirm password do not match';
         }
         if (errmsg) {
            alert(errmsg);
         } else {
            $('#adduser_form').submit();
         }
      }


      function deleteUser(aElm, i) {
         var tr = $(aElm).parent();

         // Closure to pass tr to callback.
         var onDeleteUser = function(returnData) {
            if (returnData) {
               alert(returnData);
            } else {
               tr.remove();

               // Also delete from local array.
               usernames[i] = '';
            }
         }

         var data = 'username=' + usernames[i];
         $.ajax({
               type:       'POST',
               url:        'delete_user.php',
               data:       data,
               success:    onDeleteUser,
               dataType:   'json'
         });
      }


      function changeAdmin(checkboxElm, i) {
         var admin = checkboxElm.checked ? 1 : 0;
         var data = 'username=' + usernames[i] + '&admin=' + admin;
         $.ajax({
               type:       'POST',
               url:        'change_admin.php',
               data:       data,
               success:    onChangeAdmin,
               dataType:   'json'
         });
      }


      function onChangeAdmin(returnData) {
         $('#reset_password_dialog').dialog('close');
         feedback(returnData);
      }


      var resetPasswordUsername;

      function resetPasswordDialog(i) {
         resetPasswordUsername = usernames[i];
         $('#reset_password_dialog').dialog('option', 'title', 'Reset password for ' + resetPasswordUsername);
         $('#reset_password_dialog').dialog('open');
      }


      function resetPassword() {
         var errmsg = '';

         var password = $('#reset_password').val();
         password = password.replace(/\s/, '');
         if (password.length < 4) {
            errmsg += 'Password must have at least four characters.\n';
         }
         var confirm_password = $('#reset_confirm_password').val();
         if (password != confirm_password) {
            errmsg += 'Password and confirm password do not match';
         }
         if (errmsg) {
            alert(errmsg);
         } else {
            var data = 'username=' + resetPasswordUsername 
                       + '&password=' + password;
            $.ajax({
                  type:       'POST',
                  url:        'reset_password.php',
                  data:       data,
                  success:    onResetPassword,
                  dataType:   'json'
            });
         }
      }


      function onResetPassword(returnData) {
         $('#reset_password_dialog').dialog('close');
         feedback(returnData);
      }


      function feedback(msg) {
         $('#feedback').html(msg).css({background: 'yellow'});
         $('#feedback').animate({backgroundColor: '#ffffff'}, 7000);
      }


      //]]>
   </script>

   <style type="text/css">
      body {
         font-size:           10pt;
         font-family:         Arial, Verdana, sans-serif;
      }

      #userlist td {
         font-size:           10pt;
         font-family:         Arial, Verdana, sans-serif;
         padding-left:        10px;
         padding-right:       10px;
         padding-top:         3px;
         padding-bottom:      3px;
      }

      label, input {
         display:             block;
      }
   </style>

</head>
<body>
   <div id="wysiwygit_control_header">
      <p style="margin-top: 4px;">
         &emsp;
         <b><span style="font-size: 140%; color: white;">Admin&nbsp;</span> 
         </b>
      </p>
      <div class="wysiwygit_button"
           style="position: absolute; top: 0px; right: 100px; 
                  background: none;">
         <a href="index.php" style="text-decoration: none;">
            <!-- IE8 needs the window.location method -->
            <button onclick="window.location.href = 'index.php'">
               Return to editor
            </button></a>
      </div>
   </div>
   <div id="main" style="margin-left: 20px;">
      <br />
      <?php
      if ($errmsg) {
         ?>
         <p style="text-align: center; font-weight: bold;">
            <?php print $errmsg ?>
         <p>
         <?php
      } else {
         ?>
         <div style="text-align: right;">
            <span id="feedback"></span>&nbsp;
         </div>
         <h2>
            Users
         </h2>
         <table id="userlist" cellspacing="0" border="1" style="border: 1px solid black;">
            <thead>
               <tr style="font-weight: bold;">
                  <td>
                     User name
                  </td>
                  <td>
                     Administrator?
                  </td>
                  <td>
                     Reset password
                  </td>
                  <td>
                     Delete user
                  </td>
               </tr>
            </thead>
            <tbody>
            <?php

            // Users.
            $n_users = count($usernames);
            for ($i=0; $i<$n_users; $i++) {
               $username = $usernames[$i];
               if ($admins[$i]) {
                  $admin = 'checked';
               } else {
                  $admin = '';
               }

               ?>
               <tr>
                  <td>
                     <?php print $username ?>
                  </td>
                  <td align="center">
                     <input type="checkbox" onchange="changeAdmin(this, <?php print $i ?>)" <?php print $admin ?> />
                  </td>
                  <td style="text-align: center;">
                     <a href="javascript: resetPasswordDialog(<?php print $i ?>)">
                        Reset</a>
                  </td>
                  <td style="text-align: center;" onclick="deleteUser(this, <?php print $i ?>)">
                     <a href="javascript: void(0)">
                        Delete</a>
                  </td>
               </tr>

               <?php
            }
            ?>
            </tbody>
         </table>
         <br />
         <br />
         <button onclick="$('#adduser_dialog').dialog('open');">
            Add new user
         </button>
         <div id="adduser_dialog" title="Add new user">
            <form id="adduser_form" action="<?php $_SERVER['PHP_SELF'] ?>" 
                  method="POST">
               <fieldset>
                  <table border="0">
                     <tr>
                        <td>
                           <label for="username">User name</label>
                        </td>
                        <td>
                           <input type="text" name="username" id="username" class="text ui-widget-content ui-corner-all" />
                        </td>
                     </tr>
                     <tr>
                        <td>
                           <label for="admin">Administrator</label>
                        </td>
                        <td>
                           <input type="checkbox" name="admin" id="admin" />
                        </td>
                     </tr>
                     <tr>
                        <td>
                           <label for="password">Password</label>
                        </td>
                        <td>
                           <input type="password" name="password" id="password" class="text ui-widget-content ui-corner-all" />
                        </td>
                     </tr>
                     <tr>
                        <td>
                           <label for="confirm_password">Confirm password</label>
                        </td>
                        <td>
                           <input type="password" name="confirm_password" id="confirm_password" class="text ui-widget-content ui-corner-all" />
                        </td>
                     </tr>
                  </table>
               </fieldset>
            </form>
         </div>
         <div id="reset_password_dialog" title="Reset password">
            <table border="0">
               <tr>
                  <td>
                     <label for="reset_password">New password</label>
                  </td>
                  <td>
                     <input type="password" name="reset_password" id="reset_password" class="text ui-widget-content ui-corner-all" />
                  </td>
               </tr>
               <tr>
                  <td>
                     <label for="reset_confirm_password">Confirm new password</label>
                  </td>
                  <td>
                     <input type="password" name="reset_confirm_password" id="reset_confirm_password" class="text ui-widget-content ui-corner-all" />
                  </td>
               </tr>
            </table>
         </div>
         <?php
      } // else of if ($errmsg)
      ?>
   </div> <!-- main -->

</body>
</html>
