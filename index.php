<?php
include "globals.php";
include "find_default_file.php";

// See if user already logged in.
session_start();
$logged_in = isset($_SESSION['wysiwygit']);
$admin     = isset($_SESSION['wysiwygit_admin']);
if ($logged_in) {
   $username = $_SESSION['wysiwygit_username'];
} else {

   // See if username saved in cookie.
   if (isset($_COOKIE['wysiwygit_username'])) {
      $username = $_COOKIE['wysiwygit_username'];
   } else {
      if ($demo) {
         $username = $demo_username;
      } else {
         $username = '';
      }
   } 
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
   <title>
      Edit
   </title>
   <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.5/jquery.min.js">
   </script>
   <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js">
   </script>
   <script src="jquery.infieldlabel.min.js">
   </script>
   <script type="text/javascript" src="hoverIntent.js">
   </script>
   <script type="text/javascript" src="superfish.js">
   </script>
   <!--
   <script src="../bbt/jquery.min.js"></script>
   -->
   <link rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/themes/base/jquery-ui.css" type="text/css" media="all" />

   <link rel="stylesheet" type="text/css" href="wysiwygit.css" media="screen" />
   <link rel="stylesheet" type="text/css" href="superfish.css" media="screen" />

   <script type="text/javascript">
      //<![CDATA[

      var debug = new Array();
      debug[0] = false;
      debug[1] = false;  // Load/reload.
      debug[2] = false;  // Update URL.

      var wLoggedIn = false;
      var wAdmin = false;
      var wUsername;
      var wHome = '';
      var wNewUrl;
      var wUrlTimer;
      var currentlyEditingTmpfileUrl;
      var wEditorDivIndex;
      var wExtraTime;

      $(document).ready (function () {

         // Resize iframe to match (remaining) window height.
         var headerHeight = $("#wysiwygit_control_header").height ();
         var windowHeight = $(window).height ();
         $("#wysiwygit_page_to_edit").height (windowHeight - headerHeight);

         // Controls menu.
         $('ul.sf-menu').superfish({autoArrows: false});

         // Load "home" page.  Default location: one directory closer to
         // root than this script's directory.
         <?php

         // Whole path (e.g. /fuggle/hosts/www.berkeleyballet.org/docs/edittest/wysiwygit/index.php)
         $script_filename = $_SERVER['SCRIPT_FILENAME'];

         // Change backslashes followed by non-space to forward slashes.
         $script_filename = preg_replace('/\\\\(\S)/', '/$1', $script_filename);

         // Take off the last part.
         $home_dir = preg_replace('/\/[^\/]+\/index.php/', '', $script_filename);

         // E.g, /edittest/wysiwygit/index.php
         $script_name = $_SERVER['SCRIPT_NAME'];  

         // E.g., /edittest/wysiwygit
         $script_relative_path = pathinfo($script_name, PATHINFO_DIRNAME);  // e.g., /wysiwygit

         // And again, e.g., /edittest
         $home_relative_path = pathinfo($script_relative_path, PATHINFO_DIRNAME);
         if ($home_relative_path == '\\' || $home_relative_path == '/') {
            $home_relative_path = '';
         }
         if ($debug[0]) {
            my_error_log("[wysiwygit.php] script_filename: $script_filename");
            my_error_log("[wysiwygit.php] script_relative_path: $script_relative_path");
            my_error_log("[wysiwygit.php] home_relative_path: $home_relative_path");
         }
         $basename = find_default_file($home_dir);
         if ($basename) {
            $home = "$home_relative_path/$basename";
         } else {
            $home = "";
         }
         if ($debug[0]) {
            my_error_log("[wysiwygit.php] home_dir: $home_dir");
            my_error_log("[wysiwygit.php] home: $home");
         }
         ?>
         wLoggedIn = '<?php print $logged_in ?>';
         wAdmin    = '<?php print $admin ?>';
         wUsername = '<?php print $username ?>';
         wHome     = '<?php print $home ?>';
         var wHomeBasename = '<?php print $basename ?>';
         if (wHome) {

            // Put into URL bar in header.
            $('#wysiwygit_url').val(wHome);

            // Put into title.
            document.title = 'Edit ' + wHomeBasename;

            // Load.
            loadNewPage(wHome);
         } else {
            $('#wysiwygit_url').val('Enter URL of page to edit');
            $('#wysiwygit_url').css({color: 'gray'});
         }

         if (wLoggedIn) {

            // Display user name in header.
            var html = usernameSelectHtml();
            $('#header_username').html(html);

            if (wAdmin) {

               // Administrators get option to go to admin page.
               $('.wysiwygit_admin_menu_item').css({display: 'block'});
            }
         } else {
            setupLogin();
         }

         // Start timer to keep URL bar up to date, if in same domain.
         if (! wUrlTimer) {
            wUrlTimer = setInterval("updateUrl()", 2500);
         }

         // Stop update if user is entering a URL.
         $('#wysiwygit_url').focus(function() {
            if (wUrlTimer) {
               if (debug[0]) {
                  $('#wysiwygit_dump').append('[focus] clearInterval(wUrlTimer)');
               }
               clearInterval(wUrlTimer);
               wUrlTimer = null;
            }
            if (this.value == 'Enter URL of page to edit') {
               $(this).val('').css({color: 'black'});
            }
         });

         // Restart update once user stops entering.
         $('#wysiwygit_url').blur(function() {
            if (debug[0]) {
               $('#wysiwygit_dump').append('[blur] starting setInterval("updateUrl()", 2500)');
            }
            if (this.value == '') {
               $(this).css({color: 'gray'});
               this.value = 'Enter URL of page to edit';
               $('#wysiwygit_header_message').html('');
            } else {
               if (! wUrlTimer) {
                  wUrlTimer = setInterval("updateUrl()", 2500);
               }
            }
         });
      });

      // ======================================================================

      $(window).resize (function () {
         var headerHeight = $("#wysiwygit_control_header").height ();
         var windowHeight = $(window).height ();
         $('#wysiwygit_page_to_edit').height (windowHeight - headerHeight);
      });


      function setupLogin() {

         // Handle login.
         var login = '<div id="please_log_in" style="display: inline; background: yellow; padding: 2px;">Please log in \
                      </div> \
                      <form id="login_form" style="display: inline;"> \
                      <div class="wysiwygit_login"> ';
         if (! wUsername) {
            login += ' \
               <label id="wysiwygit_username_label" for="wysiwygit_username">User name</label> ';
         }
         login += '      <input type="text" id="wysiwygit_username" /> \
                      </div> \
                      <div class="wysiwygit_login"> \
                         <label id="wysiwygit_password_label" for="wysiwygit_password">Password</label> \
                         <input type="password" id="wysiwygit_password" /> \
                      <button type="submit" id="wysiwygit_login">Login</button> \
                      </form> \
                      </div>';
         $('#wysiwygit_header_message').html(login);
         $('label').inFieldLabels();
         if (wUsername) {
            $('#wysiwygit_username').val(wUsername);
            $('#wysiwygit_password').focus();
         } else {
            $('#wysiwygit_username').focus();
         }

         // Enter key submits, but don't reload page (return false).
         $('#login_form').submit(function() {
            sendLogin();
            return false;
         });
      }


      function sendLogin() {
         $('#please_log_in').html('<img src="wysiwygit_throbber.gif" border="0" />');
         wUsername  = $('#wysiwygit_username').val();
         var password = $('#wysiwygit_password').val();
         var data = 'username=' + wUsername
                    + '&password=' + password;
         $.ajax({
               type:       'POST',
               url:        'check_login.php',
               data:       data,
               success:    onLogin,
               dataType:   'json'
         });
      }


      function onLogin(returnData) {
         if (returnData[0]) {
            wLoggedIn = true;
            wAdmin = returnData[1]
            if (wAdmin) {
               $('.wysiwygit_admin_menu_item').css({display: 'block'});
            }
            var html = usernameSelectHtml();
            $('#header_username').html(html);
            editButtonOn();
         } else {
            errmsg = returnData[2];
            if (errmsg) {
               alert(errmsg);
            } else {
               $('#please_log_in').html('Login unsuccessful').css({color: 'red', 'font-weight': 'bold'});
            }
         }
      }


      function usernameSelectHtml() {
         var html = '<select onchange="logout(this)" style="background: gray; border: 0px;"> \
                        <option>'
                   +       wUsername
                   +'   </option> \
                        <option> \
                           Logout \
                        </option> \
                     </select>';
         return html;
      }


      function logout(selectElm) {
         if (selectElm.selectedIndex == 1) {
            $.ajax({
                  type:       'POST',
                  url:        'logout.php',
                  success:    onLogout,
                  dataType:   'json'
            });
         }
      }


      function onLogout(returnData) {
         $('#header_username').html('');
         $('.wysiwygit_admin_menu_item').css({display: 'none'});
         wUsername = '';
         setupLogin();
      }


      function loadPageForEdit() {
         try {
            var url = window.frames.wysiwygit_page_to_edit.location.href;

            // Stop timer -- restarted when page loads.
            if (wUrlTimer) {
               clearInterval(wUrlTimer);
               wUrlTimer = null;
            }

            if (debug[1]) {
               $('#wysiwygit_dump').append('<br />[loadPageForEdit] url: ' +url);
            }
            $('#wysiwygit_url').val(url);

            // Basename to title.
            var basename = url.replace(/.*\//, '');
            if (debug[1]) {
               $('#wysiwygit_dump').append('<br />[loadPageForEdit] basename: ' + basename);
            }
            document.title = 'Edit ' + basename;

            $('#wysiwygit_header_message').html('<span style="background: yellow; padding: 2px;">Please wait...</span>');

            // While waiting, screen iframe with animated gif.
            $('#iframe_screen').css({display: 'block'});

            var editUrl = 'prepare_page_for_edit.php?url=' + url;
            $('#wysiwygit_page_to_edit').attr('src', editUrl);

            // (Page will call DoubleclickMessageOn() when loaded.)
         } catch (err) {
            var hostname = window.location.hostname;
            alert ('Sorry, can only edit pages on this site (' + hostname + ')');
         }
      }

      // Replace edit button with "Double-click..." text.
      function DoubleclickMessageOn() {
         $('#editButton').css({display: 'none'});
         $('#iframe_screen').css({display: 'none'});
         $('#wysiwygit_header_message').html('&nbsp;Double-click section to edit.&nbsp;');

         // Restart timer.
         currentlyEditingTmpfileUrl = window.frames.wysiwygit_page_to_edit.location.href;
         if (debug[2]) {
            $('#wysiwygit_dump').append('<br />[DoubleclickMessageOn] currentlyEditingTmpfileUrl: ' + currentlyEditingTmpfileUrl);
         }
         if (! wUrlTimer) {
            wUrlTimer = setInterval("updateUrl()", 2500);
         }
      }

      function loadButton(newUrl) {

         if (debug[2]) {
            $('#wysiwygit_dump').append('<br />[loadButton] newUrl: ' + newUrl);
         }
         if (wUrlTimer) {
            clearInterval(wUrlTimer);
            wUrlTimer = null;
         }

         // If unsaved changes...
         var pteFrame = window.frames.wysiwygit_page_to_edit;
         if (pteFrame.wysiwygit && pteFrame.wysiwygit.editor) {
            if (pteFrame.wysiwygit.editor.checkDirty()) {
               if (confirm("Changes have not been saved.  Save now?  Click Cancel to load/reload page without saving")) {
                  window.frames.wysiwygit_page_to_edit.wysiwygit.saveComplete = false;
                  window.frames.wysiwygit_page_to_edit.wysiwygit.save();
                  wNewUrl = newUrl;
                  setTimeout("waitForSaveThenLoad()", 100);
                  return 0;
               }
            }
            window.frames.wysiwygit_page_to_edit.wysiwygit.removeLockFile('parent.loadNewPage("'+newUrl+'")');
            return 0;
         }
         if (newUrl) {
            loadNewPage(newUrl);
         }
      }

      function waitForSaveThenLoad() {
         if (window.frames.wysiwygit_page_to_edit.wysiwygit.saveComplete) {
            loadNewPage(wNewUrl);
         } else {
            setTimeout("waitForSaveThenLoad()", 100);
         }
      }


      function loadNewPage(newUrl) {

         // Do not allow pages outside of "home" directory of this site.
         // wHome is like /wysiwygit/demos/wysZHwKxq/index.php.
         var homeDirUrl = wHome.replace(/\/[^\/]+$/, '');
         if (newUrl.indexOf(homeDirUrl) == -1) {
            alert('Sorry, page to edit must be in directory or subdirectory of ' + homeDirUrl);
         } else {
            $('#wysiwygit_page_to_edit').attr('src', newUrl);

            // Make edit button visible only when a page is loaded.  No way to
            // tell when actually ready, but we'll wait a second.  Get rid of
            // anything there first.
            $('#wysiwygit_header_message').html('');

            if (wLoggedIn) {
               setTimeout("editButtonOn()", 1000);
            }
         }
      }


      // Edit button and "Click to enable editing" message.
      function editButtonOn() {
         $('#editButton').css({display: 'inline'});
         $('#wysiwygit_header_message').html('<a href="javascript: loadPageForEdit()">Click to enable editing for this page</a>');
      }


      // Load current page in place of editor.
      function exitTo(currentUrl) {
         wNewUrl = '';

         // If unsaved changes...
         var pteFrame = window.frames.wysiwygit_page_to_edit;
         if (   pteFrame.wysiwygit 
             && pteFrame.wysiwygit.editor 
             && pteFrame.wysiwygit.editor.checkDirty()) {
            if (confirm("Changes have not been saved.  Save now?  Click Cancel to exit without saving")) {
               window.frames.wysiwygit_page_to_edit.wysiwygit.saveComplete = false;
               window.frames.wysiwygit_page_to_edit.wysiwygit.save(true);
               wNewUrl = currentUrl;
               setTimeout("waitForSaveThenExit()", 100);
            }
         }
         if (! wNewUrl) {
            window.location.href = currentUrl;
         }
      }


      function waitForSaveThenExit() {
         if ( window.frames.wysiwygit_page_to_edit.wysiwygit.saveComplete) {
            window.location.href = wNewUrl;
         } else {
            setTimeout("waitForSaveThenExit()", 100);
         }
      }

      /*
      function loadSiteHome() {
         $('#wysiwygit_url').val(wHome);
         $('#wysiwygit_page_to_edit').attr('src', wHome);
      }
       */


      // Called by setInterval() to keep url bar up to date.  Only update url
      // bar if not currently editing a page (don't want to show tmpfile name 
      // in url bar).
      function updateUrl() {
         try {
            var url = window.frames.wysiwygit_page_to_edit.location.href;
            if (   url != currentlyEditingTmpfileUrl
                && url != 'about:blank') {
               if (debug[2]) {
                  $('#wysiwygit_dump').append('  [updateUrl] url: ' + url);
               }
               currentlyEditingTmpfileUrl = '';
               $('#wysiwygit_url').val(url);

               // Basename to title.
               var basename = url.replace(/.*\//, '');
               document.title = 'Edit ' + basename;
            }
         } catch (err) {

            // Gray out -- we're not sure if URL is current.
         }
      }


      // Bring in new tmpfile, wait for load, invoke replaceDiv().
      function reloadTmpfile(editorDivIndex, tmpfileUrl) {
         $('#wysiwygit_page_to_edit').attr('src', tmpfileUrl);
         wEditorDivIndex = editorDivIndex;
         if (debug[1]) {
            $('#wysiwygit_dump').append('<br />[reloadTmpfile] wEditorDivIndex: ' + wEditorDivIndex);
         }
         wExtraTime = 0;
         setTimeout("waitForLoad()", 100);

         // wUrlTimer will be signal that tmpfile has loaded.
         clearInterval(wUrlTimer);
         wUrlTimer = null;
      }


      function waitForLoad() {

         // wysiwygit.js sets flag at end.  (No standard method for capturing
         // load in iframe.)
         if (debug[1]) {
            $('#wysiwygit_dump').append(' [waitForLoad] typeof: ' + typeof window.frames.wysiwygit_page_to_edit.wysiwygit.replaceDiv);
         }
         if (typeof window.frames.wysiwygit_page_to_edit.wysiwygit.replaceDiv == 'function') {

            // OK, it's there, supposedly.  Let's wait one more time.
            if (wExtraTime < 1) {
               wExtraTime++;
               setTimeout("waitForLoad()", 200);
            } else {
               window.frames.wysiwygit_page_to_edit.wysiwygit.replaceDiv(null, wEditorDivIndex);
            }
         } else {
            setTimeout("waitForLoad()", 200);
         }
      }


      function lockedDialog() {
         $('#locked').dialog({
            position: ['right', 'top'],
            title: 'Help - Cannot edit'
         }); 
      }


      // If editor is open, give chance to save before leaving.
      // Can't work so far.
      /*
      window.onunload = function() {
         if ( window.frames.wysiwygit_page_to_edit.wysiwygit.editor && window.frames.wysiwygit_page_to_edit.wysiwygit.editor.checkDirty()) {
            if (confirm("Changes have not been saved.  Save now?  Click Cancel to continue without saving")) {
               window.frames.wysiwygit_page_to_edit.wysiwygit.save();
            }
         }
      }
      */
 
      //]]>
   </script>
</head>
<body style="margin: 0px; font-size: 10pt; font-family: Arial, Verdana, sans-serif;">
   <div id="wysiwygit_control_header">
      <ul class="sf-menu" style="margin-left: 5px">
         <li>
            <a href="javascript: void(0)"><img src="wysiwygit_controls.png" class="wysiwygit_image"></a>
            <ul>
               <li>
                  <a href="javascript: exitTo('backups_list.php')">
                     View/restore backups</a>
               </li>
               <li class="wysiwygit_admin_menu_item">
                  <a href="javascript: exitTo('admin.php')">
                     Admin - users/passwords</a>
               </li>
            </ul>
         </li>
      </ul>

      <!--
      <img id="wysiwygit_controls_button" src="wysiwygit_controls.png" style="margin-top: 5px; border: 1px solid gray;" class="wysiwygit_image" />
      -->


      <button type="button" title="Load site home" onclick="loadButton(wHome);" 
              style="position: relative; top: -5px;" class="wysiwygit_button"><img src="wysiwygit_home.png" class="wysiwygit_image" alt="Load site home" /></button>

      <input type="text" id="wysiwygit_url" 
             onchange="loadButton(this.value);"
             style="position: relative; top: -7px; width: 300px;" />

      <button id="loadButton" type="button" title="Load" 
              onclick="loadButton($('#wysiwygit_url').val());" 
              style="top: -5px;" class="wysiwygit_button"><img src="wysiwygit_reload.png" class="wysiwygit_image" alt="Load" /></button>

      <button id="editButton" type="button" title="Edit" 
              onclick="loadPageForEdit();" 
              class="wysiwygit_button" 
              style="top: -5px; display: none;"><img src="wysiwygit_edit.gif" class="wysiwygit_image" alt="Edit" /></button
      ><div id="wysiwygit_header_message">
      </div>
      <div id="header_username"
           style="position: absolute; right: 35px; top: 8px;
                  font-weight: bold; background: none;">
      </div>
      <div id="wysiwygit_exitButton" class="wysiwygit_button" 
           style="position: absolute; right: 7px; top: 2px; 
                  background: none;">
         <a type="button" title="End editing" 
                href="javascript: exitTo($('#wysiwygit_url').val());" 
                ><img src="wysiwygit_exit_large.png" class="wysiwygit_image" alt="Exit" /></a>
      </div>


      <br />
      <br />
      <div id="wysiwygit_dump">
      </div>
   </div>
   <!--
   <div id="check" style="position: fixed; top: 5px; right: 120px;">
      <button type="button" 
              onclick="$('#locked').dialog()">
         test dialog
      </button>
   </div>
   <div id="clear" style="position: fixed; top: 5px; right: 30px;">
      <button type="button" onclick="$('#wysiwygit_dump').html('');">
         Clear
      </button>
   </div>
   -->
   <div id="locked" style="display: none;">
      If you or another user just closed another browser window without
      exiting the editor, please try again in one minute
   </div>

   <div id="iframe_wrapper">
      <div id="iframe_screen" class='wysiwygit_screen' style="display: none;">
         <img src="wysiwygit_working.gif" border="0"
              style="position: absolute; margin-left: -30px; left: 50%;
                     margin-top: -30px; top: 35%" />
      </div>
      <iframe id="wysiwygit_page_to_edit" name="wysiwygit_page_to_edit" src="about:blank" 
              frameborder="0">
      </iframe>
   </div>

</body>
</html>
<?php

// Clean up old tmp files.  Don't do more often than once per day.
$is_last_cleanup = file_exists("last_cleanup");
if (! ($is_last_cleanup && time() < filemtime("last_cleanup") + 24*3600)) {

   // Find tmp files from viewing previous versions (filenames look like 
   // .../subdir/.page_2011-09-15_083023.php) and tmp files from edits
   // and lock files (.wys_lck.page...)
   // Older than 24 hours.  Run in background.
   $pat1 = '".*[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]_[0-9][0-9][0-9][0-9][0-9][0-9].*"';
   $pat2 = '".tmp.*"';
   $pat3 = '".wys_lck.*"';
   $cmd = "( find $home_dir -name $pat1 -o -name $pat2 -o -name $pat3 -type f -mtime +0 -print0 | xargs -0 /bin/rm -f ) &";
   if ($debug[2]) {
      my_error_log("[cleanup] $cmd");
   }
   system($cmd);

   // Touch file to indicate have done cleanup.
   touch("last_cleanup");
}
?>
