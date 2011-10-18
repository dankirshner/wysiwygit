<?php

/** This file is part of KCFinder project
  *
  *      @desc Base configuration file
  *   @package KCFinder
  *   @version 2.51
  *    @author Pavel Tzonkov <pavelc@users.sourceforge.net>
  * @copyright 2010, 2011 KCFinder Project
  *   @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
  *   @license http://www.opensource.org/licenses/lgpl-2.1.php LGPLv2
  *      @link http://kcfinder.sunhater.com
  */


// IMPORTANT!!! Do not remove uncommented settings in this file even if
// you are using session configuration.
// See http://kcfinder.sunhater.com/install for setting descriptions

//DKMOD
$script_filename = $_SERVER['SCRIPT_FILENAME'];

// Change backslashes followed by non-space to forward slashes.
$script_filename = preg_replace('/\\\\(\S)/', '/$1', $script_filename);

// Change multiple slashes to single slash.
$script_filename = preg_replace('/\/+/', '/', $script_filename);

// Take off last three slashes (e.g., .../docs/wysiwygit/kcfinder/config.php).
$uploadDir = preg_replace('/\/[^\/]+\/[^\/]+\/[^\/]+$/', '', $script_filename);

//DKMOD
$default_excluded_dirs = array('backup', 'ckeditor', 'wysiwygit', 'edit');
if (file_exists("../config.php")) {
   include "../config.php";
}
if (isset($excluded_dirs)) {
   array_push($excluded_dirs, $default_excluded_dirs);
} else {
   $excluded_dirs = $default_excluded_dirs;
} 
$_CONFIG = array(

    //DKMOD
    'debug' => false,
    'excludedDirs' => $excluded_dirs,

    'disabled' => true,
    'denyZipDownload' => false,
    'denyUpdateCheck' => true,
    'denyExtensionRename' => false,

    'theme' => "oxygen",

    'uploadURL' => "/",
    //DKMOD
    'uploadDir' => $uploadDir,

    'dirPerms' => 0755,
    'filePerms' => 0644,

    'access' => array(

        'files' => array(
            'upload' => true,
            'delete' => true,
            'copy' => true,
            'move' => true,
            'rename' => true
        ),

        'dirs' => array(
            'create' => true,
            'delete' => true,
            'rename' => true
        )
    ),

    'deniedExts' => "exe com msi bat php phps phtml php3 php4 cgi pl",

    'types' => array(

        // CKEditor & FCKEditor types
        ''        =>  "",
        'all'     =>  "",
        'files'   =>  "",
        'flash'   =>  "swf",
        'images'  =>  "*img",

        // TinyMCE types
        'file'    =>  "",
        'media'   =>  "swf flv avi mpg mpeg qt mov wmv asf rm",
        'image'   =>  "*img",
    ),

    'filenameChangeChars' => array(
        ' ' => "_",
        ':' => "."
    ),

    'dirnameChangeChars' => array(
        ' ' => "_",
        ':' => "."
    ),

    'mime_magic' => "",

    'maxImageWidth' => 0,
    'maxImageHeight' => 0,

    'thumbWidth' => 100,
    'thumbHeight' => 100,

    'thumbsDir' => ".thumbs",

    'jpegQuality' => 90,

    'cookieDomain' => "",
    'cookiePath' => "",
    'cookiePrefix' => 'KCFINDER_',

    // THE FOLLOWING SETTINGS CANNOT BE OVERRIDED WITH SESSION CONFIGURATION
    '_check4htaccess' => true,
    //'_tinyMCEPath' => "/tiny_mce",

    '_sessionVar' => &$_SESSION['KCFINDER']
    //'_sessionLifetime' => 30,
    //'_sessionDir' => "c:/inetpub/wwwroot/sessions",

    //'_sessionDomain' => ".rosexp",
    //'_sessionPath' => "/sessions"
);

?>
