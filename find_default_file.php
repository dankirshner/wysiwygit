<?php
function find_default_file($directory) {
   global $errmsg;

   $basename = '';

   $names = array('index', 'default'); 
   $suffixes = array('php', 'asp', 'jsp', 'html', 'htm');
   foreach ($names as $name) {
      foreach($suffixes as $suffix) {
         if (file_exists("$directory/$name.$suffix")) {
            $basename = "$name.$suffix";
            break;
         }
      }
   }
   return $basename;
}
?>
