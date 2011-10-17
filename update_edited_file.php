<?php
// Update tmp file and (optionally) original file.

error_reporting(E_ALL);

include "globals.php";
include "config.php";

// Security check.
session_start();
if (! isset($_SESSION['wysiwygit'])) {
   $errmsg = "User not properly logged in";
   $return_data = array('', $errmsg, '');
   $json = json_encode($return_data);
   print $json;
   if ($debug[1]) {
      my_error_log("[update_edited_file] return_data: $json");
   }
   exit;
}

// Edited HTML, div index, full file path, flag.
$edit_data        = $_REQUEST['editData'];
$edit_div_index   = $_REQUEST['editDivIndex'];

$fullpath         = $_REQUEST['fullPath'];
$tmpfile_fullpath = $_REQUEST['tmpfileFullPath'];

if (isset($_REQUEST['lckfileFullPath'])) {
   $lckfile_fullpath = $_REQUEST['lckfileFullPath'];
} else {
   $lckfile_fullpath = "";
}

if ($debug[1]) {
   my_error_log("[update_edited_file] edit_data: $edit_data");
   my_error_log("[update_edited_file] edit_div_index: $edit_div_index");
   my_error_log("[update_edited_file] fullpath: $fullpath");
   my_error_log("[update_edited_file] tmpfile_fullpath: $tmpfile_fullpath");
}

$errmsg = '';

// -----------------------------------------------------------------------------

// We keep updating the tmp file as we go, using the dke_element_index 
// attributes as a guide, and then copying the tmp file (with additions 
// expunged) over the regular file for a full save.

// Autosave: update tmp file, don't copy over regular file.

// Read in the tmp file.
$html = file_get_contents($tmpfile_fullpath);

// Find the edited div.  
$n_matches = preg_match("/dke_element_index=\"$edit_div_index\"/", $html, $matches, PREG_OFFSET_CAPTURE);
if ($n_matches == 0) {
   $errmsg .= "Could not find edited div in tmp file. ";
} else {
   $beg_offset = $matches[0][1];

   // Process edit data -- see if backslashes have been escaped.  
   // prepare_page_for_edit.php put in dke_encoding="dke_ck1\2"
   $n_matches = preg_match('/dke_ck1([^2]+)2/', $edit_data, $matches);
   if ($n_matches > 0) {
      $encoded_backslash = $matches[1];
      if ($debug[1]) {
         my_error_log("encoded_backslash: $encoded_backslash");
      }
      if (strlen($encoded_backslash) > 1) {

         // Yes, backslashes have been escaped.  First take out single 
         // backslashes.  Use lookbehind and lookahead to match non-backslashes,
         // but not include them in the matched characters.
         // (Yes, it takes four backslashes to represent a single backslash!)
         $edit_data = preg_replace('/(?<!\\\\)\\\\(?!\\\\)/', '', $edit_data);

         // Now convert double backslashes to single.
         $edit_data = preg_replace('/\\\\\\\\/', '\\\\', $edit_data);
         if ($debug[1]) {
            my_error_log("edit_data: $edit_data");
         }
      }
   }

   // Get rid of any extra content added by document.write() between closing
   // </script> tag and <!-- /protect --> comment (which was added by 
   // prepare_page_for_edit.php).
   $edit_data = preg_replace('/<\/script\s*>.*?<!-- \/protect -->/i', '</script><!-- /protect -->', $edit_data);

   // At the end of the edited content there's going to be a clearer div,
   // with an index.  Find it as the last match.
   $n_matches = preg_match_all('/wysiwygit_clearer_\d+/', $edit_data, $matches);
   if ($n_matches == 0) {
      $errmsg .= "Could not find <div id=\"wysiwygit_clearer_NN\"... in edited data. ";
   } else {
      $last = $n_matches - 1;
      $clearer_id = $matches[0][$last];

      // Find that clearer id in the remaining html.
      preg_match("/$clearer_id/", $html, $matches, PREG_OFFSET_CAPTURE, $beg_offset);
      $end_offset = $matches[0][1];

      // Find precisely where the inner html begins.  Exclude initial
      // spaces/newlines from inner html (which is purpose of subexpression).
      preg_match('/>\s*(\S)/', $html, $matches, PREG_OFFSET_CAPTURE, $beg_offset);
      $beg_innerhtml = $matches[1][1];

      // Find where inner html ends.
      preg_match('/<\\/div>/', $html, $matches, PREG_OFFSET_CAPTURE, $end_offset);
      $end_innerhtml = $matches[0][1] + 5;

      if ($debug[1]) {
         my_error_log("old innerhtml:\n" . substr($html, $beg_innerhtml, $end_innerhtml-$beg_innerhtml+1));
      }

      // Also update the modification time of the file.
      $replace = 'tmpfileMtime = ' . time();
      $html = preg_replace('/tmpfileMtime = \d+/', $replace, $html);

      // Put the pieces together.
      $html = substr($html, 0, $beg_innerhtml) . $edit_data 
              . substr($html, $end_innerhtml+1);

      // Replace temp file.
      $result = file_put_contents($tmpfile_fullpath, $html);
      if ($result === false) {
         $errmsg .= "Could not write to tmp file $tmpfile_fullpath ";
      }

      // Save backup.  Save in backup directory with path from "home" 
      // and timestamp.

      // Backup directory default: subdirectory of this site's "home"
      // directory (assumed to be one closer to root than this script's
      // directory).
      // (E.g., /fuggle/hosts/www.berkeleyballet.org/docs/backup)

      // Whole path (e.g. /fuggle/hosts/www.berkeleyballet.org/docs/
      //                          edittest/wysiwygit/prepare_page_for_edit.php)
      $script_filename = $_SERVER['SCRIPT_FILENAME'];

      // Change backslashes followed by non-space to forward slashes.
      $script_filename = preg_replace('/\\\\(?=\S)/', '/', $script_filename);

      // Take off the last part.
      $script_dir = str_replace('/update_edited_file.php', '', $script_filename);

      // Once more for "home" dir.
      $home_dir = preg_replace('/\/[^\/]+$/', '', $script_dir);
      $BACKUP = "$home_dir/backup";

      // Get path name relative to "home."  Add timestamp to path name -- 
      // before extension.
      $relative_path = str_replace($home_dir, '', $fullpath);

      $path_parts = pathinfo($relative_path);
      $dirname = $path_parts['dirname'];
      if ($dirname == '\\' || $dirname == '/') {
         $dirname = '';
      }
      
      $unix_file_modification_sec = filemtime($fullpath);
      $timestamp = date("Y-m-d_His", $unix_file_modification_sec);
      $backup_file = $BACKUP . '/' . $dirname . '/' 
                     . $path_parts['filename'] . '_' . $timestamp . '.' 
                     . $path_parts['extension'];
      if ($debug[1]) {
         my_error_log("request_uri: " . $_SERVER['REQUEST_URI']);
         my_error_log("script_filename: $script_filename");
         my_error_log("path_parts: " . print_r($path_parts, true));
         my_error_log("home_dir: $home_dir");
         my_error_log("backup_file: $backup_file");
      }

      // Make backup directory if it doesn't exist.
      $backup_dir_ok_f = true;
      $backup_full_dir = pathinfo($backup_file, PATHINFO_DIRNAME);
      if (! file_exists($backup_full_dir)) {
         $ok_f = @mkdir($backup_full_dir, 0777, true);
         if (! $ok_f) {
            $errmsg = "Could not make backup directory $backup_full_dir. ";
            $backup_dir_ok_f = false;
         }
      } else {

         // Make sure is a directory, and is writable.
         if (! is_dir($backup_full_dir)) {
            $errmsg = "Could not make backup directory $backup_full_dir. "
                      . "It already exists as a file. ";
            $backup_dir_ok_f = false;
         }
         if (! is_writable($backup_full_dir)) {
            $errmsg = "Backup directory $backup_full_dir is not writable. ";
            $backup_dir_ok_f = false;
         }
      }
      if ($backup_dir_ok_f) {

         // Copy file.
         copy($fullpath, $backup_file);
      }

      // -------------------------------------------------------------------
      // Proceed with save.

      // Get rid of <!-- protect --> and <!-- /protect --> comments.
      $html = preg_replace('/<!-- \/?protect -->/', '', $html);

      // Get rid of additions at end of temp file.
      $html = preg_replace('/<link href=[^>]+dke_delete="true" \\/>.*/s', '', $html);

      // Get rid of added attributes -- dke_element_index.
      $html = preg_replace('/\\s*dke_element_index="\d+"/', '', $html);

      // Get rid of clearer divs.
      $html = preg_replace('/<div [^<]*id="wysiwygit_clearer[^<]+<\\/div>/im', '', $html);

      // If flag set, get rid of added whole-body div.
      if (! isset($edit_all_body)) {
         $edit_all_body = false;
      }
      if ($edit_all_body) {
         $html = preg_replace('/<div id="wysiwygit_body">/i', '', $html);

         // Last closing div tag.
         $html = preg_replace('/<\/div>(?!.*<\/div>.*)/is', '', $html, 1);
      }

      // Overwrite original file.
      $result = file_put_contents($fullpath, $html);
      if ($debug[1]) {
         my_error_log("result: $result");
      }
      if ($result === false) {
         $errmsg .= "Could not write to file $fullpath ";
      }

      // Indicate that the tmpfile is up to date.
      touch($tmpfile_fullpath);

      // If exiting, remove lock file.
      if ($lckfile_fullpath) {
         unlink($lckfile_fullpath);
      }
   }
}

// Return save time feedback, error message, and unix save time to onSave() 
// function.
$save_time = date("g:i");
$return_data = array($save_time, $errmsg, time());
$json = json_encode($return_data);
print $json;
if ($debug[1]) {
   my_error_log("return_data: $json");
}

?>
