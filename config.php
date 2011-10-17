<?php

/* ----------------------------------------------------------------------------
 * Option to allow all of a web page's <body> to be edited in all cases.  
 *
 * Default: only divs within each page may be edited ($edit_all_body = false;).
 */
$edit_all_body = false;

/* ---------------------------------------------------------------------------- * Div ids or classes that will be excluded from editing.
 * Name of directory containing CKEditor.  This should be the modified version
 * that came with Wygiwygit.
 */
$ckeditor_dir = 'ckeditor';

/* ---------------------------------------------------------------------------- * Div ids or classes that will be excluded from editing.
 * Ids indicated with "#"; classes indicated with ".".
 *
 * Note: class "noedit" is excluded by default.
 *
 * Example: uncomment (remove the double slashes in) the following line to 
 * exclude divs like * <div id="header">  and <div class="keep">
 */
// $noedit_div_selectors = array("#header", ".keep");

/* ----------------------------------------------------------------------------
 * Directories that will be excluded from browsing/file deleting/uploading.
 *
 * Note: directories backup, ckeditor, edit, and wysiwygit are excluded by
 * default.
 *
 * Example: uncomment (remove the double slashes in) the following line to 
 * exclude directories test_pages and webalizer
 */

// $excluded_dirs = array('test_pages', 'webalizer');
?>
