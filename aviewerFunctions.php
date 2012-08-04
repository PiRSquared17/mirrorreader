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

function aviewer_isZip($file) {
  $file = (string) $file; // God, I wish this could be done in the function line.

  if (preg_match('/\.zip$/', $file)) return true; // This is prolly the worst way of doing this (like, duh); TODO
  else return false;
}

function aviewer_stripZip($file) {
  $file = (string) $file; // God, I wish this could be done in the function line.

  if (aviewer_isZip($file)) return preg_replace('/^(.+)\.zip$/', '\\1', $file); // This is prolly the worst way of doing this (like, duh); TODO
  else return $file;
}

function aviewer_inCache($domain) {
  global $cacheStore;

  $fileScan = (array) scandir($cacheStore); // Read the directory and return each file in the form of an array.

  if (in_array($domain, $fileScan)) return true; // TODO?
  else return false;
}

function aviewer_unArchive($text, $maxDepth = 1) { // Unarchive ZIP files, and optionally recursive into the directories and unzip all zipped files in them. Note that zip files follow these rules: (1) if they contain a singular parent directory, the directory will be igored in the created tree; (2) the directory will be named based on the zip name (e.g. fun.zip = /fun); (3) if a directory exists to match the zip name directory, the file will not be unzipped; (4) the zip name directory must NOT contain non-alphanumeric characters; (5) the original zip will not be deleted; it can still be safely referrenced by files if needed (a zip file is assumed to be downloaded when referrenced in $_GET[url]]

}

function aviewer_format($file) { // Attempts to format URLs -- absolute or relative -- so that they can be loaded with the viewer.
  global $me, $urlDomain, $urlDirectory; // Oh, sue me. I'll make it a class or something later.

  $urlDirectoryLocal = $urlDirectory;

  if (preg_match('/^(http|https|ftp|mailto)\:/i', $file)) { // Domain Included; TODO Optimise

  }
  elseif (preg_match('/^\//', $file) || !$urlDirectory) { // Absolute Path
    $file = "{$urlDomain}/{$file}";
  }
  else { // Relative Path
    while (preg_match('/^\.\//', $file)) {
      $file = preg_replace('/^\.\/(.*)/', '$1', $file);
    }
    while (preg_match('/^\.\.\//', $file)) {
      $file = preg_replace('/^\.\.\/(.*)/', '$1', $file);
      $urlDirectoryLocal = aviewer_dirPart($urlDirectoryLocal);
    }

    // Encodes URLs that include GET arguments. (PHP5.3 REQUIRED)
    // TODO: Optimise.
    if (preg_match('/\.([a-zA-Z]{0,3})\?([a-zA-Z0-9]+)=([a-zA-Z0-9]+)((\&([a-zA-Z0-9]+)=([a-zA-Z0-9]+))+|)/', $file)) { // Note: We do this check since the /e replacement takes quite a while longer. I don't really know why.
      $file = preg_replace_callback('/\.([a-zA-Z]{0,3})\?([a-zA-Z0-9]+)=([a-zA-Z0-9]+)((\&([a-zA-Z0-9]+)=([a-zA-Z0-9]+))+|)/', function($m) {
        return urlencode("{$m[2]}={$m[3]}{$m[4]}") . ".{$m[1]}";
      }, $file);
    }

    $file = "{$urlDomain}/{$urlDirectoryLocal}/{$file}";
  }

  return "{$me}?url={$file}";
}

function aviewer_dirPart($file) { // Obtain the parent directory of a file or directory by analysing its string value. This will not operate on the directory or file itself.
  $fileParts = explode('/', $file);
  foreach ($fileParts AS $id => $part) { // Remove all empty elements.
    if (!$part) {
      unset($fileParts[$id]);
    }
  }

  array_pop($fileParts); // Note: Because of the previous foreach loop, the array index may be corrupted (e.g. the array will be {0 = ele, 2 = ele}), thus making array_pop the only possible means of removing the last element of the array (as opposed to the count method that may be faster).

  return implode('/', $fileParts);
}

function aviewer_filePart($file) { // Obtain the file or directory without its parent directory by analysing its string value. This will not operate on the directory or file itself.
  $fileParts = explode('/', $file);

  foreach ($fileParts AS $id => $part) { // Remove all empty elements.
    if (!$part) {
      unset($fileParts[$id]);
    }
  }

  return array_pop($fileParts); // Note: Because of the previous foreach loop, the array index may be corrupted (e.g. the array will be {0 = ele, 2 = ele}), thus making array_pop the only possible means of removing the last element of the array (as opposed to the count method that may be faster).
}

function aviewer_isSpecial($file) {
  if ($file === '.' || $file === '..' || $file === '~') return true; // Yes, the last one isn't normally used; I have my pointless reasons.
  else return false;
}

function aviewer_basicTemplate($data, $title = '') {
  echo "<html>
  <head>
    <title>{$title}</title>
    <style>
    body { font-family: Ubuntu, sans; }
    h1 { margin: 0px; padding: 0px; }
    </style>
  </head>

  <body>
    {$data}
  </body>
</html>";
}

// Replaces "<" and ">" (using entitiesHackInner) if within a string.
function entitiesHackOuter($scriptContent) {
  $scriptContent = preg_replace("/\"(.+)\"/e", '"\"" . entitiesHackInner("$1") . "\""', $scriptContent);
  $scriptContent = preg_replace("/'(.+)'/e", '"\'" . entitiesHackInner("$1") . "\'"', $scriptContent);

  return $scriptContent;
}

// Replaces "<" and ">".
function entitiesHackInner($stringContent) {
  $stringContent = str_replace('<', '&lt;', $stringContent);
  $stringContent = str_replace('>', '&gt;', $stringContent);

  return $stringContent;
}

function aviewer_processHtml($contents) {
  global $config; // Yes, I will make this a class so this is less annoying.

  if ($config['removeExtra']) {
    $contents = preg_replace('/\<\?xml(.+)\?\>/', '', $contents);
    $contents = preg_replace('/\<\!--(.*?)--\>/ism', '', $contents); // Get rid of comments (cleans up the DOM at times, making things faster). We do not remove commnets if they are a part of JavaScript.
  }

  if ($config['badEntitiesHack']) {
//    preg_match_all('/\<script(.*?)\>(.*?)<\/script\>/s',$contents,$return);
//    print_r($return);
    $contents = preg_replace('/\<script(.*?)\>(.*?)\<\/script\>/es','"<script$1>" . entitiesHackOuter("$2") . "</script>"',$contents);
  }

  libxml_use_internal_errors(true); // Stop the loadHtml call from spitting out a million errors.
  $doc = new DOMDocument(); // Initiate the PHP DomDocument.
  $doc->preserveWhiteSpace = false; // Don't worry about annoying whitespace.
  $doc->loadHTML($contents); // Load the HTML.

  // Process LINK tags
  $linkList = $doc->getElementsByTagName('link');
  for ($i = 0; $i < $linkList->length; $i++) {
    if ($linkList->item($i)->hasAttribute('href')) {
      if ($linkList->item($i)->getAttribute('type') == 'text/css' || $linkList->item($i)->getAttribute('rel') == 'stylesheet') {
        $linkList->item($i)->setAttribute('href', aviewer_format($linkList->item($i)->getAttribute('href') . '&type=css'));
      }
      else {
        $linkList->item($i)->setAttribute('href', aviewer_format($linkList->item($i)->getAttribute('href')));
      }
    }
  }

  // Process SCRIPT tags.
  $scriptList = $doc->getElementsByTagName('script');
  $scriptDrop = array();
  for ($i = 0; $i < $scriptList->length; $i++) {
    if ($scriptList->item($i)->hasAttribute('src')) {
      $scriptList->item($i)->setAttribute('src', aviewer_format($scriptList->item($i)->getAttribute('src')) . '&type=js');
    }
    else {
      if ($config['scriptDispose']) {
        $scriptDrop[] = $scriptList->item($i);
      }
      else {
        $scriptList->item($i)->nodeValue = aviewer_processJavascript($scriptList->item($i)->nodeValue);
      }
    }
  }
  foreach ($scriptDrop AS $drop) {
    $drop->parentNode->removeChild($drop);
  }

  // Process STYLE tags.
  $styleList = $doc->getElementsByTagName('style');
  for ($i = 0; $i < $styleList->length; $i++) {
    $styleList->item($i)->nodeValue = aviewer_processCSS($styleList->item($i)->nodeValue);
  }

  // Process BASE tags.
  $baseList = $doc->getElementsByTagName('base');
  for ($i = 0; $i < $scriptList->length; $i++) {
    // TODO: Change Base (e.g. $urlDirectory)
  }

  // Process IMG, VIDEO, AUDIO, IFRAME tags
  foreach (array('img', 'video', 'audio', 'iframe', 'applet') AS $ele) {
    $imgList = $doc->getElementsByTagName($ele);
    for ($i = 0; $i < $imgList->length; $i++) {
      if ($imgList->item($i)->hasAttribute('src')) {
        $imgList->item($i)->setAttribute('src', aviewer_format($imgList->item($i)->getAttribute('src')) . '&type=other');
      }
    }
  }

  // Process A, AREA (image map) tags
  foreach (array('a', 'area') AS $ele) {
    $aList = $doc->getElementsByTagName($ele);
    for ($i = 0; $i < $aList->length; $i++) {
      if ($aList->item($i)->hasAttribute('href')) {
        $aList->item($i)->setAttribute('href', aviewer_format($aList->item($i)->getAttribute('href')));
      }
    }
  }

  // Process BODY, TABLE, TD, and TH tags w/ backgrounds. TABLE, TD & TH do support the background tag, but it was an extension of both Netscape and IE way back, and today most browsers still recognise it and will add a background image as appropriate, so... we have to support it.
  foreach (array('body', 'table', 'td', 'th') AS $ele) {
    $aList = $doc->getElementsByTagName($ele);
    for ($i = 0; $i < $aList->length; $i++) {
      if ($aList->item($i)->hasAttribute('background')) {
        $aList->item($i)->setAttribute('background', aviewer_format($aList->item($i)->getAttribute('background')));
      }
    }
  }

  // Process Option Links; some sites will store links in OPTION tags and then sue Javascript to link to them. Thus, if the hack is enabled, we will try to cope.
  if ($config['selectHack']) {
    $optionList = $doc->getElementsByTagName('option');
    for ($i = 0; $i < $optionList->length; $i++) {
      if ($optionList->item($i)->hasAttribute('value')) {
        $optionValue = $optionList->item($i)->getAttribute('value');
        if (preg_match('/\.(htm|html|php|shtml|\/)$/', $optionValue)) { // TODO Optimise
          $optionList->item($i)->setAttribute('value', aviewer_format($optionValue));
        }
      }
    }
  }

  // This is the meta-refresh hack, which tries to fix meta-refresh headers that may in some cases automatically redirect a page, similar to <a href>. This is hard to work with, and in general sites wishing to achieve this will often implement it instead using headers (which, due to the nature of an archive, will not be transmitted and thus we don't have to worry about modifying them) or using JavaScript (which is never easy to implement, though in some cases it still works). An example: <meta http-equiv="Refresh" content="5; URL=http://www.google.com/index">
  if ($config['metaHack']) {
    $metaList = $doc->getElementsByTagName('meta');
    for ($i = 0; $i < $metaList->length; $i++) {
      if ($metaList->item($i)->hasAttribute('http-equiv') && $metaList->item($i)->hasAttribute('content')) {
        if (strtolower($metaList->item($i)->getAttribute('http-equiv')) == 'refresh') {
          $metaList->item($i)->setAttribute('content', preg_replace('/^(.*)url=([^ ]+)(.*)$/ies', '"$1" . aviewer_format("$2") . "$3"', $metaList->item($i)->getAttribute('content')));
        }
      }
    }
  }

  return $doc->saveHTML(); // Return the updated data.
}

function aviewer_processJavascript($contents) {
  global $config;

  $contents = preg_replace('/\/\*(.*?)\*\//is', '', $contents); // Removes comments.

  if ($config['scriptEccentric']) { // Convert anything that appears to be a suspect file. Because of the nature of this, there is a high chance stuff will break if $scriptEccentric is enabled. But, it allows some sites to work properly that otherwise wouldn't.
    $contents = preg_replace('/(([a-zA-Z0-9\_\-\/]+)\.(php|htm|html|css|js)[^a-zA-Z0-9])/ie', 'aviewer_format("$1")', $contents); // Note that if the extension is followed by a letter or integer, it is possibly a part of a JavaScript property, which we don't want to convert.
  }
  else { // Convert strings that contain files ending with suspect extensions.
    $contents = preg_replace('/("|\')(([a-zA-Z0-9\_\-\/]+)\.(php|htm|html|css|js))\1/ie', 'stripslashes("$1") . aviewer_format("$2") . stripslashes("$1")', $contents);
  }

  if (isset($config['jsReplace'])) {
    $contents = str_replace($config['jsReplace'], '', $contents);
  }

  return $contents; // Return the updated data.
}

function aviewer_processCSS($contents) {
  $contents = preg_replace('/\/\*(.*?)\*\//is', '', $contents); // Removes comments.
  $contents = str_replace(';',";\n", $contents); // Fixes an annoying REGEX quirk below; I won't go into it.
  $contents = preg_replace('/url\((\'|"|)(.+)\\1\)/ei', '\'url($1\' . aviewer_format("$2") . \'$1)\'', $contents); // CSS images are handled with this.

  if (isset($config['cssReplace'])) {
    $contents = str_replace($config['cssReplace'], '', $contents);
  }

  return $contents; // Return the updated data.
}
?>