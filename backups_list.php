<?php
// List directories and files in current directory.

error_reporting(E_ALL);

include "globals.php";

// Security check.
session_start();
if (! isset($_SESSION['wysiwygit'])) {
   $errmsg = "User not properly logged in.  Please return to editor to log in.";
} else {
   $errmsg = '';
}


// ------------------------------------------------------------------------------
// Whole path (e.g. /fuggle/hosts/www.berkeleyballet.org/docs/edittest/wysiwygit/index.php)
$script_filename = $_SERVER['SCRIPT_FILENAME'];

// Change backslashes followed by non-space to forward slashes.
$script_filename = preg_replace( '/\\\\(?=\S)/', '/', $script_filename);

// Take off the last part.
$home_dir = preg_replace('/\/[^\/]+\/backups_list.php/', '', $script_filename);

// ------------------------------------------------------------------------------
// E.g, /edittest/wysiwygit/index.php
$script_name = $_SERVER['SCRIPT_NAME'];  

// Change backslashes followed by non-space to forward slashes.
$script_name = preg_replace( '/\\\\(?=\S)/', '/', $script_name);

// E.g., /edittest/wysiwygit
$script_relative_path = pathinfo($script_name, PATHINFO_DIRNAME);

// And again, e.g., /edittest
$home_relative_path = pathinfo($script_relative_path, PATHINFO_DIRNAME);
if ($home_relative_path == '/') {
   $home_relative_path = '';
}

// ------------------------------------------------------------------------------
if (isset($_REQUEST['directory_path'])) {
   $directory_path = $_REQUEST['directory_path'];
} else {

   // We're in wysiwygit, subdirectory of "home".
   $directory_path = "$home_dir/backup";
}
$orig_relative_path = str_replace("$home_dir/backup", '', $directory_path);

if ($debug[2]) {
   my_error_log("[backups_list.php] home_dir: $home_dir");
   my_error_log("[backups_list.php] directory_path: $directory_path");
   my_error_log("[backups_list.php] orig_relative_path: |$orig_relative_path|");
}

// Get list of files and directories in backup folder.  Get in reverse
// alphabetical order (so first file will be most recent).
$all_dirs_files = scandir($directory_path, 1);
if ($debug[2]) {
   my_error_log("all_dirs_files\n" . print_r($all_dirs_files, true));
}

// Create two arrays: standard directories and most-recent version of each
// page.

$directories = array();
$pages = array();
$pages_file = array();
$pages_time = array();
$previous_versions_page = array();
$previous_versions_file = array();
$previous_versions_time = array();
$previous_page = "";
$i = -1;
foreach ($all_dirs_files as $dir_file) {
   if (is_dir("$directory_path/$dir_file")) {
      if ($dir_file != "." && $dir_file != "..") {
         $directories[] = $dir_file;
      }
   } else {

      // File name without date-time part.
      //                   ----+----1----+---
      // Looks like:  index_2011-09-15_160724.php
      // Get name without extension, part before date-time.
      $filename = pathinfo($dir_file, PATHINFO_FILENAME);
      $page = substr($filename, 0, -18);

      // Add back extension.
      $page .= '.' . pathinfo($dir_file, PATHINFO_EXTENSION);

      $page_time_string = substr($filename, -17);
      $year = substr($page_time_string, 0, 4);
      $mo = (int) substr($page_time_string, 5, 2);
      $d = (int) substr($page_time_string, 8, 2);
      $h = (int) substr($page_time_string, 11, 2);
      $m = substr($page_time_string, 13, 2);

      $am_pm = 'AM';
      if ($h == 0) {
         $h = 12;
      } else if ($h == 12) {
         $am_pm = 'PM';
      } else if ($h > 12) {
         $h = $h - 12;
         $am_pm = 'PM';
      }

      $page_time = "$mo/$d/$year $h:$m $am_pm";

      if ($page != $previous_page) {
         $pages[] = $page;
         $pages_file[] = $dir_file;
         $pages_time[] = $page_time;

         $previous_versions_page[] = array();
         $previous_versions_file[] = array();
         $previous_versions_time[] = array();

         $previous_page = $page;
         $i++;
      } else {

         // Previous versions list.
         $previous_versions_page[$i][] = $page;
         $previous_versions_file[$i][] = $dir_file;
         $previous_versions_time[$i][] = $page_time;
      }
   }
}

sort($directories);
array_multisort($pages, $pages_file, $pages_time, 
                $previous_versions_page, $previous_versions_file, 
                $previous_versions_time);

if ($debug[2]) {
   my_error_log("directories:\n" . print_r($directories, true));
   my_error_log("pages:\n" . print_r($pages, true));
}

// Get time of current version of each page.
$current_pages_time = array();
foreach ($pages as $page) {
   $current_page = "$home_dir/$orig_relative_path/$page";
   $mtime = @filemtime($current_page);
   if ($mtime) {
      $current_pages_time[] = date("n/j/Y g:i A", $mtime);
   } else {
      $current_pages_time[] = "";
   }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
   <title>
      Backups
   </title>
   <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.5/jquery.min.js">
   </script>
   <!--
   <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js">
   </script>
   -->

   <link rel="stylesheet" type="text/css" href="wysiwygit.css" />

   <script type="text/javascript">
      //<![CDATA[

      var view_window;
      function view(page_file, page, page_time) {
         if (view_window) {
            view_window.close();
         }

         // Go to script that will copy file -- temporarily -- to regular
         // directory (so includes, links to css, and javascript src's will
         // work).
         var viewUrl = 'view_backup.php?filepath=' 
                       + '<?php print $directory_path ?>' + '/' + page_file
                       + '&orig_name=' + page
                       + '&mtime=' + page_time;
         view_window = window.open(viewUrl, "view_window", "resizable=yes, menubar=yes, scrollbars=yes");
         view_window.focus();
      }


      function showPreviousVersions(aElm) {
         $(aElm).parent().css({display: 'none'});
         $(aElm).parent().next().css({display: 'block'});
      }


      function hidePreviousVersions(aElm) {
         $(aElm).parent().css({display: 'none'});
         $(aElm).parent().prev().css({display: 'block'});
      }


      //]]>
   </script>

   <style type="text/css">
      a {
         text-decoration:     none;
      }
      #pagelist td {
         vertical-align:      top;
         font-size:           10pt;
         font-family:         Arial, Verdana, sans-serif;
      }
      body {
         font-size:           10pt;
         margin:              0px;
         
         font-family:         Arial, Verdana, sans-serif;
      }
   </style>

</head>
<body>
   <div id="wysiwygit_control_header">
      <p style="margin-top: 4px;">
         &emsp;
         <b><span style="font-size: 140%; color: white;">Backups.&nbsp;</span> 
         Previous versions of site pages </b>
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
         <table id="pagelist" border="0" style="border: 1px solid black;">
            <tr>
               <td>
                  &nbsp;
               </td>
               <td colspan="4">
                  <b>Backup directory <?php print $orig_relative_path ?></b>
                  <br />
                  &nbsp;
               </td>
            </tr>
         <?php
         // If not in backup "root", provide link to parent folder.
         if ($orig_relative_path != '') {
            $parent_dir = preg_replace('/\/[^\/]*$/', '', $directory_path);
            ?>
            <tr>
               <td>
                  <a href="<?php print $_SERVER['PHP_SELF'] . "?directory_path=$parent_dir" ?>"
                     ><img src="wysiwygit_parent_folder.gif" border="0" /></a>
                     </a>
               </td>
               <td>
                  <a href="<?php print $_SERVER['PHP_SELF'] . "?directory_path=$parent_dir" ?>">
                     Parent folder</a>
               </td>
            </tr>
            <?php
         }

         // Directories, if any.
         foreach($directories as $directory) {
            ?>
            <tr>
               <td>
                  <a href="<?php print $_SERVER['PHP_SELF'] . "?directory_path=$directory_path/$directory" ?>"
                     ><img src="wysiwygit_folder.png" border="0" /></a>
               </td>
               <td>
                  <a href="<?php print $_SERVER['PHP_SELF'] . "?directory_path=$directory_path/$directory" ?>">
                        <?php print $directory ?></a>
               </td>
            </tr>
            <?php
         }

         // Pages.
         $n_pages = count($pages);
         for ($i=0; $i<$n_pages; $i++) {
            $page = $pages[$i];
            $page_file = $pages_file[$i];
            $page_time = $pages_time[$i];
            $current_page_time = $current_pages_time[$i];
            if ($current_page_time) {
               $current_page_title = "Current version: saved $current_page_time";
            } else {
               $current_page_title = "No current version";
            }
            ?>
            <tr>
               <td>
               </td>
               <td>
                  <a href="javascript: view(<?php print "'$page_file', '$page', '$page_time'" ?>);"
                     title="View this version">
                        <?php print $page ?></a>
               </td>
               <td style="padding-left: 15px;">
                  <?php print $page_time ?>
               </td>
               <td>
                  <img src="wysiwygit_clock.png" 
                       title="Current version: saved <?php print $current_page_time ?>" 
                       border="0" />
               </td>
               <td style="padding-left: 15px;">
                  <?php 
                  $n_previous_versions = count($previous_versions_page[$i]);
                  if ($n_previous_versions) {
                     ?>
                     <div>
                        <a href="javascript: void(0)" onclick="showPreviousVersions(this)">
                           List earlier versions (<?php print $n_previous_versions ?>)</a>
                     </div>
                     <div style="line-height: 135%; display: none;">
                        Previous versions 
                        <a href="javascript: void(0)" onclick="hidePreviousVersions(this)">
                           (hide)</a>:
                        <br />
                        <?php
                        for ($j=0; $j<$n_previous_versions; $j++) {
                           $page = $previous_versions_page[$i][$j];
                           $file = $previous_versions_file[$i][$j];
                           $time = $previous_versions_time[$i][$j];
                           print <<<END
                              <a href="javascript: view('$file', '$page', '$time');"
                                 title="View this version">
                                 &emsp;&emsp;$time</a>
                              <br />
END;
                        }
                        ?>
                     <div>
                     <?php
                  }
                  ?>
               </td>
            </tr>

            <?php
         }
         ?>
         </table>
         <?php
      } // else of if ($errmsg)
      ?>
   </div> <!-- main -->
</body>
</html>
