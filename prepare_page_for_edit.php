<?php
// Set up copy of page for editing.

error_reporting(E_ALL);

include "globals.php";
include "config.php";

// ==============================================================================
// Look for likely default files.
// function find_default_file($directory) 
include "find_default_file.php";


// ------------------------------------------------------------------------------
function error_print($errmsg) {

   print <<<END

   <script type="text/javascript">
      var iframe_screen = parent.document.getElementById("iframe_screen");
      iframe_screen.style.display = "none";
   </script>
   <body style="text-align: center;">
      <br />
      <br />
      <strong>
         $errmsg
      </strong>
   </body>

END;
   exit;
}

// ==============================================================================

// Security check.
session_start();
if (! isset($_SESSION['wysiwygit'])) {
   error_print("Sorry, not logged in correctly");
}

$errmsg = "";

// Get url.
$url = $_REQUEST['url'];

$directory = dirname(__FILE__);
if ($debug[0]) {
   my_error_log("[prepare_page_for_edit.php] url: $url\n");  // http://rosexp/whatever/subdir/script.php

   my_error_log("[prepare_page_for_edit.php] directory: $directory\n");  // C:\Inetpub\wwwroot\wysiwygit
   my_error_log("[prepare_page_for_edit.php] hostname: " . $_SERVER['SERVER_NAME']); // c:\inetpub\wwwroot
   my_error_log("[prepare_page_for_edit.php] document root: " . $_SERVER['DOCUMENT_ROOT']); // c:\inetpub\wwwroot
   my_error_log("[prepare_page_for_edit.php] php self: " . $_SERVER['PHP_SELF']); // /wysiwygit/prepare_page_for_edit.php
   my_error_log("[prepare_page_for_edit.php] script filename: " . $_SERVER['SCRIPT_FILENAME']); // c:\inetpub\wwwroot\wysiwygit\prepare_page_for_edit.php
   my_error_log("[prepare_page_for_edit.php] script name: " . $_SERVER['SCRIPT_NAME']); // /wysiwygit/prepare_page_for_edit.php
}

// Script relative path -- passed back to javascript.
$script_name = $_SERVER['SCRIPT_NAME'];  // e.g, /wysiwygit/update_edited_file.php
$script_relative_path = pathinfo($script_name, PATHINFO_DIRNAME);  // e.g., /wysiwygit

// Whole path (e.g. /fuggle/hosts/www.berkeleyballet.org/docs/edittest/wysiwygit/prepare_page_for_edit.php)
$script_filename = $_SERVER['SCRIPT_FILENAME'];

// Change backslashes followed by non-space to forward slashes.
$script_filename = preg_replace( '/\\\\(\S)/', '/$1', $script_filename);

// Take off the last part.
$home_dir = preg_replace('/\/[^\/]+\/prepare_page_for_edit.php/', '', $script_filename);

// Script might be
//         /home3/whereisq/www/dynamicridesharing/user1/wysiwygit/prep...edit.php.
// So $home_dir may be something like 
//         /home3/whereisq/www/dynamicridesharing/user1     (a demo "home").
// The url might be 
//         http:/dynamicridesharing.org/user1/subdir/index.php.
// So relative path is directories after user1.  Duplicates in path will be
// trouble.
$server_name = $_SERVER['SERVER_NAME'];
$relative_path = str_replace("http://$server_name", '', $url);
if ($debug[0]) {
   my_error_log("[prepare_page_for_edit.php] relative_path: $relative_path");
}

if (substr($relative_path, -1) == '/') {
   $relative_dir = substr($relative_path, 0, -1);
} else {
   $relative_dir = pathinfo($relative_path, PATHINFO_DIRNAME);
}
if ($relative_dir == '\\' || $relative_dir == '/') {
   $relative_dir = '';
}

// Overlap between relative dir and "home" dir needs to be eliminated.
// home dir:      /home3/whereisq/www/dynamicridesharing/user1     
// relative dir:  /user1
// relative path: /user1/index.php   --> index.php
// -- or --
// home dir:      /home3/whereisq/www/dynamicridesharing/user1     
// relative dir:  /user1/subdir
// relative path: /user1/subdir/index.php   --> subdir/index.php

// See how many pieces of relative dir are in home dir.
$trimmed = preg_replace('.^/.', '', $relative_dir);
$trimmed = preg_replace('./$.', '', $trimmed);
$pieces = preg_split('./.', $trimmed);

$piece = "";
$new_overlap = "";
$overlap = "";
while ($pieces) {
   $piece = array_shift($pieces);
   $new_overlap = "$overlap/$piece";
   if (preg_match("|$new_overlap|", $home_dir)) {
      $overlap = $new_overlap;
   } else {
      break;
   }
}
if ($overlap) {
   $relative_path = str_replace($overlap, '', $relative_path);
   $relative_dir = str_replace($overlap, '', $relative_dir);
}

$fullpath = "$home_dir/$relative_path";

if ($debug[0]) {
   my_error_log("[prepare_page_for_edit.php] overlap: $overlap");
   my_error_log("[prepare_page_for_edit.php] home_dir: $home_dir");
   my_error_log("[prepare_page_for_edit.php] relative_dir: $relative_dir");
   my_error_log("[prepare_page_for_edit.php] fullpath: $fullpath");
}

// If we've just got a directory URL (ends with a "/") then we don't know the
// file basename.  Try a few likely candidates for the default file.
if (substr($fullpath, -1) == '/') {
   $basename = find_default_file($fullpath);
   if (! $basename) {
      $errmsg .= "<p>Could not find file for this web page.</p>\n"
                ."<p>Please try entering full URL for page.</p>\n";
      error_print($errmsg);
   }
   $dirname = substr($fullpath, 0, -1);
   $fullpath .= $basename;
} else {
   $basename = basename($fullpath);
   $dirname = pathinfo($fullpath, PATHINFO_DIRNAME);
}
$tmpfile_fullpath = "$dirname/.tmp.$basename";
$lckfile_fullpath = "$dirname/.wys_lck.$basename";
//$usgfile_fullpath = "$dirname/.dke_usg.$basename";
if ($debug[0]) {
   my_error_log("[prepare_page_for_edit.php] dirname: " . $dirname);
   my_error_log("[prepare_page_for_edit.php] basename: " . $basename);
}

if ($relative_dir == '') {
   $tmpfile_url = "../.tmp.$basename";
   $iframe_url = ".tmp.$basename";
} else {
   $tmpfile_url = "../$relative_dir/.tmp.$basename";
   $iframe_url = "$relative_dir/.tmp.$basename";
}

// Stylesheet URL.  In same directory as this script.
$dke_style_main_url = "$script_relative_path/wysiwygit.css";

// Add-ons/script/jQuery to page to be edited.
$dke_script = "$script_relative_path/wysiwygit.js";
if (! isset($ckeditor_dir)) {
   $ckeditor_dir = 'ckeditor';
}
$cke_script = "$script_relative_path/../$ckeditor_dir/ckeditor.js";

if ($debug[0]) {
   my_error_log("[prepare_page_for_edit.php] tmpfile_fullpath: " . $tmpfile_fullpath);
   my_error_log("[prepare_page_for_edit.php] tmpfile_url: " . $tmpfile_url);
   my_error_log("[prepare_page_for_edit.php] dke_script: " . $dke_script);
}

// Check whether file is readable and writable.
if (! is_readable($fullpath)) {
   $errmsg = "<p>Cannot read file $fullpath.</p>\n";
}
if (! is_writable($fullpath)) {
   $errmsg .= "<p>Cannot write file $fullpath.  Will not be able to save.</p>\n";
}

// Directory needs to be writable in order to create temp file.
if (! is_writable($dirname)) {
   $errmsg .= "<p>Cannot write directory $dirname.  Cannot create temporary file.</p>\n";
}
if ($errmsg) {
   error_print($errmsg);
}

// See if tmpfile exists and is up to date (consistent with original page 
// file).  Also, it should be newer than this script (in case some portion of
// the prep has changed).
if ($debug[0]) {
   if (file_exists($tmpfile_fullpath)) {
      my_error_log("filemtime($tmpfile_fullpath): " . filemtime($tmpfile_fullpath) . ", filemtime($fullpath): " . filemtime($fullpath));
   }
}
if (file_exists($tmpfile_fullpath) 
    && (filemtime($tmpfile_fullpath) >= filemtime($fullpath))
    && (filemtime($tmpfile_fullpath) >= filemtime($script_filename))) {

   // Yes.  See if accessed recently (last 10 minutes).
   // DON'T HAVE WAY TO TRANSFER THIS INFO!  Could write to a script that's
   // src'd into tmpfile.
   /*
   $someone_using_tmpfile = 'false';
   if (file_exists($usgfile_fullpath)) {
      if (filemtime($usgfile_fullpath) > time() - 600) {
         $someone_using_tmpfile = 'true';
      }
   }
   // Use that file.  Set signal that this user is using it.
   touch($usgfile_fullpath);
   */

   if ($debug[0]) {
      my_error_log("Using existing tmpfile $tmpfile_fullpath");
   }

} else {

   // Create tmpfile.
   // If php file (etc), mark and protect php.
   // ...


   // Read in file.
   $html = file_get_contents($fullpath);

   // Find inline scripts (after </head> tag), enclose in <!-- protect -->
   // <!-- /protect --> comments (as a way to see if document.write() has added 
   // anything).
   // Split on </head> tag, if there.
   $pieces = preg_split('/<\/head\s*>/', $html, 2);
   if (count($pieces) > 1) {
      $piece = $pieces[1];
   } else {
      $piece = $html;
   }

   // Add <!-- protect --> before <script... '?=' indicates lookahead.
   $piece = preg_replace('/(?=<script[^>]*>)/i', '<!-- protect -->', $piece);

   // Add <!-- /protect --> after </script>.  
   $piece = preg_replace('/(<\/script\s*>)/i', '$1<!-- /protect -->', $piece);

   // Also, if flag set, add div for whole-page editing.
   if (! isset($edit_all_body)) {
      $edit_all_body = false;
   }
   if ($edit_all_body) {

      // See if there's a body tag.
      if (preg_match('/<body/', $piece)) {
         $piece = preg_replace('/(<body[^>]*>)/i', '$1<div id="wysiwygit_body">', $piece);
      } else {

         // No body tag.  Add div at beginning.
         $piece = '<div id="wysiwygit_body">' . $piece;
      }

      // Closing div tag.  Is there a closing body tag?
      if (preg_match('/<\/body/', $piece)) {
         $piece = preg_replace('/(?=<\/body)/i', '</div>', $piece);
      } else {

         // How about a closing html tag?
         if (preg_match('/<\/html/', $piece)) {
            $piece = preg_replace('/(?=<\/html)/i', '</div>', $piece);
         } else {

            // Add at end.
            $piece .= '</div>';
         }
      }
   }

   // Put pieces back together if necessary.
   if (count($pieces) > 1) {
      $html = $pieces[0] . '</head>' . $piece;
   } else {
      $html = $piece;
   }

   // Find divs, number with dke_element_index.  First, split on "<div".
   $pieces = preg_split('/<div/i', $html);
   $n_pieces = count($pieces);
   if ($debug[0]) {
      my_error_log("n_pieces: $n_pieces");
   }
   if ($n_pieces > 1) {

      // Add index to each piece.
      for ($i=0; $i<$n_pieces-1; $i++) {
         $pieces[$i] .= "<div dke_element_index=\"$i\"";;
      }
      $html = implode('', $pieces);
   }

   // Find div closing tags, insert clearer div (with numbering) before each.
   // Want clearer div to go _before_ any spaces at end of div, so move it before
   // spaces.
   //$html = preg_replace('/(\s*)<\\/div>/is', '</div>\1', $html);

   // Split on "</div>".
   $pieces = preg_split('/<\\/div>/i', $html);
   $n_pieces = count($pieces);
   if ($n_pieces > 1) {

      // Add indexed div to each piece.
      for ($i=0; $i<$n_pieces-1; $i++) {
         $pieces[$i] .= "<div id=\"wysiwygit_clearer_$i\" class=\"wysiwygit_clearer noedit\" dke_encoding=\"dke_ck1\\2\">&nbsp;</div></div>";;
      }
      $html = implode('', $pieces);
   }

   // Add elements at end: stylesheet, jQuery, ckeditor, and local script.
   // (What if jQuery and ckeditor already on page?  Firefox, IE8, Chrome: the last
   // one wins.  Same for styles.)  (Toolbar divs done in wysiwygit.js.)
   // Mark each with special attribute.

   // IE and Firefox accept stuff even after the </html> tag.

   $additions = array();

   // Stylesheet.
   $additions[] = '<link href="' . $dke_style_main_url . '" type="text/css" rel="stylesheet" dke_delete="true" />';

   // jQuery.
   $additions[] = '<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.5/jquery.min.js" type="text/javascript" dke_delete="true"></script>';
   $additions[] = '<script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js" type="text/javascript" dke_delete="true"></script>';
   /*
   $additions[] = '<script src="/bbt/jquery.min.js" type="text/javascript" dke_delete="true"></script>';
   $additions[] = '<script src="/wysiwygit/jquery-ui.min.js" type="text/javascript" dke_delete="true"></script>';
    */

   // CKEditor.
   $additions[] = '<script src="' . $cke_script . '" type="text/javascript" dke_delete="true"></script>';

   // wysiwygit.
   $additions[] = '<script src="' . $dke_script . '" type="text/javascript" dke_delete="true"></script>';

   // Javascript to pass file names.
   // Change backslashes followed by non-space to forward slashes.
   $fullpath = preg_replace( '/\\\\(\S)/', '/$1', $fullpath);
   $tmpfile_fullpath = preg_replace( '/\\\\(\S)/', '/$1', $tmpfile_fullpath);

   $additions[] = '<script type="text/javascript" dke_delete="true">';
   $time = time();

   // User-defined noedit divs (set in config.php).
   $noedit_div_selectors[] = ".noedit";
   $noedit_div_selectors = implode(',', $noedit_div_selectors);

      //var dke_usgfileFullPath = '$usgfile_fullpath';
   $additions[] = <<<END

      var scriptRelativePath = '$script_relative_path';
      var fullPath = '$fullpath';
      var tmpfileFullPath = '$tmpfile_fullpath';
      var tmpfileUrl = '$iframe_url';
      var lckfileFullPath = '$lckfile_fullpath';

      var tmpfileMtime = $time;
      var lckUpdateInterval = $lckfile_update_interval * 1000;
      var dke_noedit = '$noedit_div_selectors';

      // Turn on "Double-click section to edit..." message.
      parent.DoubleclickMessageOn();

END;

   $additions[] = '</script>';


   $html .= implode("\n", $additions);

   // Save modified html to temp name.
   $result = file_put_contents($tmpfile_fullpath, $html);
   if ($result === false) {
      print "<body>";
      print "<strong>";
      print "Unable to write temporary file $tmpfile_fullpath";
      print "</strong>";
      print "</body>";
      exit;
   }
}

// Load the file.
header("Location: $tmpfile_url");


?>
