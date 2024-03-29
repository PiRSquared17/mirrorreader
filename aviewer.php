<?php
/*
   Copyright 2011 Joseph T. Parsons

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

   http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/

/* Check http://code.google.com/p/mirrorreader/w/list for help. */

require('aviewerConfiguration.php');
require('aviewerFunctions.php');
ini_set('pcre.backtrack_limit' , 1000000000);
error_reporting(E_ALL);
$data = '';

$url = isset($_GET['url']) ? (string) urldecode($_GET['url']) : false; // Get the URL to display from GET. Note: URL must be functional in a web browser for it to be parsed. (In other words, broken URLs, like "http:google.com" are not fixed in the script. This is an archive viewer, not a Frankenstein machine.)
$passthru = isset($_GET['passthru']) ? $_GET['passthru'] : false;
$fileType = isset($_GET['type']) ? (string) $_GET['type'] : false; // Get the URL to display from GET.
$me = $_SERVER['PHP_SELF']; // This file.

if ($url === false) { // No URL specified.
  $fileScan = scandir($store); // Read the directory and return each file in the form of an array.
  
  $data = '';

  foreach ($fileScan AS $domain) { // List each of the stored domains.
    if (aviewer_isSpecial($domain)) continue; // Don't show ".", "..", etc.
    
    if (is_dir("{$store}/{$domain}") || substr($domain, -3, 3) == 'zip') { // Only show ZIPed files and directories.
      $domainNoZip = aviewer_stripZip($domain); // Domains can be zipped initially, so remove them if needed.
      $data .= "<a href=\"{$me}?url={$domainNoZip}/{$homeFile}\">{$domainNoZip}</a><br />";
    }
  }

  echo aviewer_basicTemplate($data, 'Choose a Domain');
}

else { // URL specified
  if (stripos($url, 'http:') !== 0 && stripos($url, 'https:') !== 0 && stripos($url, 'mailto:') !== 0 && stripos($url, 'ftp:') !== 0) { // Domain Not Included, Add It
    $url = 'http://' . $url;
  }

  $urlParts = parse_url($url);
  while(strpos($urlParts['path'], '//') !== false) $urlParts['path'] = str_replace('//', '/', $urlParts['path']); // Get rid of excess slashes.

  $urlParts['dir'] = aviewer_filePart($urlParts['path'], 'dir') ?: '';
  $urlParts['file'] = aviewer_filePart($urlParts['path'], 'file') ?: '';

  // Get proper configuration.
  if (isset($domainConfiguration[$urlParts['host']])) $config = array_merge($domainConfiguration['default'], $domainConfiguration[$urlParts['host']]);
  else $config = $domainConfiguration['default'];

  /* Handle $config Redirects */
  if (isset($config['redirect'])) {
    foreach ($config['redirect'] AS $find => $replace) {
      if (strpos($urlParts['host'] . $urlParts['path'], $find) === 0) {
        $newLocation = str_replace($find, $replace, $urlParts['host'] . $urlParts['path']);
        header("Location: {$me}?url={$newLocation}");
        die(aviewer_basicTemplate("<a href=\"{$me}?url={$newLocation}\">Redirecting.</a>"));
      }
    }
  }

  if (!aviewer_inCache($urlParts['host'])) {
    $storeScan = scandir($store); // Scan the directory that stores offline domains.
    if (in_array($urlParts['host'], $storeScan)) { // Check to see if the domain is in the store.
      symlink("{$store}/{$urlParts['host']}", "{$config['cacheStore']}/{$urlParts['host']}") or die(aviewer_basicTemplate("Could not create symlink. Are directory permissions set correctly?<br /><br />Source: {$store}/{$urlParts['host']}/<br />Link Destination: {$config['cacheStore']}/{$urlParts['host']}/", '<span class="error">Error</span>')); // Note, because I couldn't figure it out: symlink params can not contain end slashes
    }
    elseif (in_array($urlParts['host'] . '.zip', $storeScan)) {
      $zip = new ZipArchive;

      echo aviewer_basicTemplate('Loading archive. This may take a moment...<br />', 'Processing...', 1);
      aviewer_flush();

      if ($zip->open("{$store}/$urlParts[host].zip") === TRUE) {
        echo aviewer_basicTemplate('Unzipping. This may take a few moments...<br />', '', 2);
        aviewer_flush();
        $zip->extractTo($config['cacheStore']);
        $zip->close();

        die(aviewer_basicTemplate("Archive Loaded. <a href=\"{$me}?url={$url}\">Redirecting.</a><script type=\"text/javascript\">window.location.reload();</script>", '', 2));
      }
      else {
        die('Zip Extraction Failed.');
      }
    }
    else { // The domain isn't in the store.
      if ($config['passthru'] || $passthru) {
        header('Location: ' . $url); // Note: This redirects to the originally embedded URL (thus, we aren't touching it at all).
        die(aviewer_basicTemplate("<a href=\"$url\">Redirecting.</a>"));
      }
      else {
        echo aviewer_basicTemplate('Domain not found: "' . $urlParts['host'] . '"');
        die();
      }
    }

    /* TODO: Uncompress */
  }
  
  $absPath = $config['cacheStore'] . $urlParts['host'] . $urlParts['path'];
  $path301 = $urlParts['host'] . '/' . $urlParts['dir'] . '/' . $urlParts['file'] . '1/';

  if ($config['301mode'] == 'dir' && !$_GET['301']) { // Oh God, is this going to be weird...
    $dirParts = explode('/', $urlParts['path']); // Start by breaking up the directory into individual folders.
    $dirPartsNew = [];
    $is301 = false; // We need to set this to true once a substitution has occured, otherwise we'll never stop redirecting.

    foreach ($dirParts AS $index => &$part) { // After doing that, we'll build an array containing only unique directories.
      if ($part) $dirPartsNew[] = $part;
    }

    $dirPartsRe = $dirPartsNew; // Here, we'll copy dirPartsNew to a new array. (We could technically skip this in exchange for any semblance of sanity I have left after writing this bit of nonsense.
    
    foreach ($dirPartsNew AS $index => $part) { // Next, we run through the array we just created, both reading and making modifications to the mirror array we just created in which the directories will be changed to the "1" version if it exists.
      $path = implode('/', array_slice($dirPartsRe, 0, $index + 1)); // First, we create the normal path.
      $array301 = array_slice($dirPartsRe, 0, $index); // Then, we create the modified path.
      array_push($array301, $dirPartsRe[$index] . 1); // "
      $path301 = implode('/', $array301);  // "

      if (is_dir($config['cacheStore'] . $urlParts['host'] . '/' . $path301)) {
        $is301 = true;
        $dirPartsRe[$index] = $dirPartsRe[$index] . 1;
      }
      else {
        $dirPartsRe[$index] = $dirPartsRe[$index];
      }
    }
    
    $path301 = implode('/', $dirPartsRe); // And, finally, we implode the modified path and will use it as the 301 path.
    
    if (is_dir("{$config['cacheStore']}{$urlParts['host']}/{$path301}") && $is301) {
      header("Location: {$me}?url={$urlParts['host']}/{$path301}&301");
      die(aviewer_basicTemplate("<a href=\"{$me}?url={$urlParts['host']}/{$path301}&301\">Redirecting</a>"));
    }
  }
  
  if (is_dir($absPath)) { // Allow (minimal) directory viewing.
    if (is_file("{$absPath}/{$config['homeFile']}")) { // Automatically redirect to the home/index file if it exists in the directory.
      header("Location: {$me}?url={$urlParts['host']}/{$urlParts['path']}/{$config['homeFile']}");
      die(aviewer_basicTemplate("<a href=\"{$me}?url={$urlParts['host']}/{$urlParts['path']}/{$config['homeFile']}\">Redirecting</a>"));
    }
    else {
      $dirFiles = scandir($absPath); // Get all files.
      
      $data = '';

      foreach ($dirFiles AS $file) { // List each one.
        if (aviewer_isSpecial($file)) continue; // Don't show ".", "..", etc.
        $data .= "<a href=\"{$me}?url={$url}/{$file}\">$file</a><br />";
      }

      echo aviewer_basicTemplate($data, "Directory \"{$url}\"");
    }
  }
  else {
    if (file_exists($absPath)) {
      $contents = file_get_contents($absPath); // Get the file contents.

      $urlFileParts = explode('.', $urlParts['file']);
      $urlFileExt = $urlFileParts[count($urlFileParts) - 1];

      if (!$fileType) {
        switch ($urlFileExt) { // Attempt to detect file type by extension.
          case 'html': case 'htm': case 'shtml': case 'php': $fileType = 'html';  break;
          case 'css':                                        $fileType = 'css';   break;
          case 'js':                                         $fileType = 'js';    break;
          default:                                           $fileType = 'other'; break;
        }
      }
      
      if ($fileType == 'other' && preg_match('/^([\ \n]*)(\<\!DOCTYPE|\<html)/i', $contents)) $fileType = 'html';

      switch ($fileType) {
        case 'html': header('Content-type: text/html');       echo aviewer_processHtml($contents);       break;
        case 'css':  header('Content-type: text/css');        echo aviewer_processCSS($contents);        break;
        case 'js':   header('Content-type: text/javascript'); echo aviewer_processJavascript($contents); break;
        case 'other':
        $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
        $mimeType = finfo_file($finfo, $absPath);
        finfo_close($finfo);

        header('Content-type: ' . $mimeType);
        if (in_array($urlFileExt, array('zip', 'tar', 'gz', 'bz2', '7z', 'lzma'))) header('Content-Disposition: *; filename="' . $urlParts['file'] . '"');

        echo $contents;
        break;
      }
    }
    else {
      if ($config['passthru']) {
        header('Location: ' . $url); // Redirect to the URL as originally passed. (Though, if no prefix was available, "http:" will have been added.)
        die(aviewer_basicTemplate("<a href=\"$url\">Redirecting.</a>"));
      }
      else {
        die(aviewer_basicTemplate('File not found: "' . $absPath . '"'));
      }
    }
  }
}
?>