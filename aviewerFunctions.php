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


/**
 * Checks if a file is a ZIP file.
 * @param string $file - The file.
 * @return boolean - True if the file is a ZIP, false otherwise.
 */
function aviewer_isZip($file) {
  $file = (string) $file; // God, I wish this could be done in the function line.

  if (strpos($file, '.zip') === strlen($file) - 4) return true;
  else return false;
}


/**
 * Removes zip extension from file string.
 * @param string $file - The file.
 * @return string - File without zip extension.
 */
function aviewer_stripZip($file) {
  $file = (string) $file; // God, I wish this could be done in the function line.

  if (aviewer_isZip($file)) return substr($file, 0, strlen($file) - 4); // Get all but last four characters (.zip) of the string. 
  else return $file;
}


/**
 * @global type $config - Domain configuration for cacheStore
 * @param type $domain - The domain to check.
 * @return boolean - True if in cache, false otherwise.
 */
function aviewer_inCache($domain) {
  global $config;

  if (in_array($domain, (array) scandir($config['cacheStore']))) return true; // TODO?
  else return false;
}


/**
 * Formats a file that is included in an archive to be easily processed by the MirrorReader.
 * @global string $me - Current executing file.
 * @global string $urlParts - The dissected URL we are currently visiting.
 * @global string $config - The configuration for the domain.
 * @param type $file - The file string in the original archive.
 * @return string - New file that can be queried by aviewer.php.
 */
function aviewer_format($file) { // Attempts to format URLs -- absolute or relative -- so that they can be loaded with the viewer.
  global $me, $urlParts, $config; // Oh, sue me. I'll make it a class or something later.

  $urlDirectoryLocal = $urlParts['dir'];

  if ($config['getHack']) {
    // Encodes URLs that include GET arguments. (PHP5.3 REQUIRED)
    // TODO: Optimise.
    $getRegex = '/\.(' . implode('|', $config['recognisedExtensions']) . ')\?([a-zA-Z0-9\-\_\*]+)(=([a-zA-Z0-9\-\_\*\%]+)|)(((\&amp\;|\&)([a-zA-Z0-9\-\_\*]+)(=([a-zA-Z0-9\-\_\*\%]+)|))+|)/';
//return $file;
    if (preg_match($getRegex, $file)) { // Note: We do this check since the /e replacement takes quite a while longer. I don't really know why.
      $file = preg_replace_callback($getRegex, function($m) {
        // $m = [
        // 1 - file extension
        // 2 - first GET argument (e.g. "hi" in "lol.txt?hi=mom&bye=dad)
        // 3 - first GET value, including equals, and possibly empty (e.g. "=mom")
        // 4 - first GET value, without equals (e.g. "mom")
        // 5 - remaining string of GET arguments & values, and possibly empty (e.g. "&bye=dad")
        // 6+ - irrelevant in current usage
        return "{$m[2]}" . ($m[3] ?: '') . "{$m[5]}" . ".{$m[1]}";
      }, $file);
    }
  }

  if (stripos($file, 'data:') === 0) { // Do not process data: URIs
    return $file;
  }
  elseif (stripos($file, 'http:') === 0 || stripos($file, 'https:') === 0 || stripos($file, 'mailto:') === 0 || stripos($file, 'ftp:') === 0) { // Domain Included
  }
  elseif (strpos($file, '/') === 0) { // Absolute Path
    $file = "{$urlParts[host]}/{$file}";
  }
  else {
    while (strpos($file, './') === 0) {
      $file = preg_replace('/^\.\/(.*)/', '$1', $file);
    }
    while (strpos($file, '../') === 0) {
      $file = preg_replace('/^\.\.\/(.*)/', '$1', $file);
      
      if ($urlDirectoryLocal) $urlDirectoryLocal = aviewer_filePart($urlDirectoryLocal, 'dir'); // One case has been found where "../" is used at the root level. The browser, as a result, treats it as a "./" instead. ...I had no fricken clue this was even possible.
    }
    
    $file = "{$urlParts[host]}/{$urlDirectoryLocal}/{$file}";
  }

  return "{$me}?url=" . urlencode($file) . ($config['passthru'] ? '&passthru=1' : '');
}


/**
 * Obtains the directory part of a file string.
 * @param string $file - The file.?
 * @param string $part - The part, either "file", "directory", "root", or "nonroot".
 * @return string - The file, with only the directory in the string.
 */
function aviewer_filePart($file, $filePart) { // Obtain the parent directory of a file or directory by analysing its string value. This will not operate on the directory or file itself.
  $fileParts = explode('/', $file);

  foreach ($fileParts AS $id => $part) { // Remove all empty elements.
    if (!$part) unset($fileParts[$id]);
  }
   // Note: Because of the previous foreach loop, the array index may be corrupted (e.g. the array will be {0 = ele, 2 = ele}), thus making array_pop and array_push the only possible means of removing the last/first element of the array (as opposed to the count method that may be faster).
  switch($filePart) {
    case 'file' :
    return array_pop($fileParts);
    break;
  
    case 'dir':
    array_pop($fileParts);
    return implode('/', $fileParts);
    break;
  
    case 'root':
    return array_shift($fileParts);
    break;
  
    case 'nonroot':
    array_shift($fileParts);
    return implode('/', $fileParts);
    break;
  
    default:
    throw new Exception('Missing second parameter in aviewer_filePart call.');
    break;
  }
}


/**
 * Checks if a file is a special file.
 * @param string $file - The file.
 * @return boolean - If the file is special, returns true. Otherwise, returns false.
 */
function aviewer_isSpecial($file) {
  if ($file === '.' || $file === '..' || $file === '~') return true; // Yes, the last one isn't normally used; I have my pointless reasons.
  else return false;
}


/**
 * Formats a text string in a template. Consistent UI, yay! (Seeing as this template is very rarely shown, it is very minimal.)
 * @param string $data - The data to be returned as part of a template.
 * @param string $title - The title of the page.
 * @param int $special - If 0, standard template is used. If 1, the end body/html is not included. If 2, only text is returned.
 * @return string - Returns data, title formatted in template.
 */
function aviewer_basicTemplate($data, $title = '', $special = 0) {
  $return = '';
  
  if ($special === 0 || $special === 1) $return .= "<html>
  <head>
    <title>{$title}</title>
    <style>
    body { font-family: Ubuntu, sans; }
    h1 { margin: 0px; padding: 5px; display: block; border-color: gray; border-spacing: 4px; background-color: #9f9f9f; }
    .error { color: #ff0000; }
    </style>
  </head>

  <body>
    <h1>MirrorReader" . ($title ? ': ' . $title : '') . "</h1><hr />
    {$data}
";
  
  if ($special === 1) $return .= "  </body>
</html>";
  
  if ($special === 2) $return .= $data;
  
  return $return;
}


/**
 * Attempts to flush the output buffer. This will also output 4K of whitespace, since some browsers will require roughly this much to show the sent output (in fact, I don't know a single browser that doesn't.)
 */
function aviewer_flush() {
  // Browsers are bitches, and like to make it hard to send buffers. This helps.
  echo '                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    ';

  flush();
  ob_flush();
}


/**
 * Replaces "<" and ">" (using entitiesHackInner) if within a string.
 * @param string $scriptContent
 * @return string
 */
function entitiesHackOuter($scriptContent) {
  $scriptContent = preg_replace("/\"(.+)\"/e", '"\"" . entitiesHackInner("$1") . "\""', $scriptContent);
  $scriptContent = preg_replace("/'(.+)'/e", '"\'" . entitiesHackInner("$1") . "\'"', $scriptContent);

  return $scriptContent;
}


/**
 * Replaces "<" and ">".with "&lt;" and "&gt;"
 * @param string $stringContent
 * @return string
 */
function entitiesHackInner($stringContent) {
  return str_replace(array('<', '>'), array('&lt;', '&gt'), $stringContent);
}


/**
 * Rewrites HTML. This uses DOMDocument, and can handle most bad HTML. (No guarentees, but no cases have yet been found where it screws up.)
 * @global string $config
 * @param string $contents
 * @return string
 */
function aviewer_processHtml($contents) {
  global $config, $urlParts, $me; // Yes, I will make this a class so this is less annoying.

  if (isset($config['htmlReplacePre'])) {
    foreach ($config['htmlReplacePre'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
  }

  $contents = str_replace('--!>', '-->', $contents); // Alter Improper Comment Form. This is known to break things.
  $contents = str_replace('//-->', '-->', $contents); // Alter Improper Comment Form. This may break things (it is less clear.)
  
  if ($config['removeExtra']) {
    $contents = preg_replace('/\<\?xml(.+)\?\>/', '', $contents);
    $contents = preg_replace('/\<\!--(.*?)--\>/ism', '', $contents); // Get rid of comments (cleans up the DOM at times, making things faster). We do not remove commnets if they are a part of JavaScript.
  }

  if ($config['badEntitiesHack']) { // This is a strange hack that prevents Javascript from being interpretted as part of the HTML DOM. Many sites will only use external scripts or not use entities in their scripts, so we do not want this to be enabled by default.
    $contents = preg_replace('/\<script(.*?)\>(.*?)\<\/script\>/es','"<script$1>" . entitiesHackOuter("$2") . "</script>"', $contents);
  }

//  $contents = preg_replace('/<head(.*?)>(.*?)<\/head>(.*?)<body(.*?)>(.*?)<style(.*?)>(.*?)<\/style>(.*?)<\/body>/is', '<head$1>$2<style$6>$7</style></head>$3<body$4>$5$8</body>', $contents); // Hack to move <style> tags into <head> if they are in <body>, since it will cause problems with loadHtml.
//  $contents = preg_replace('/\<style(.*?)\>(.*?)\<\!\-\-(.*?)\-\-\\>(.*?)\<\/style\>/is', '<style$1>$2$3$4</style>', $contents); // Hack to remove HTML comments from <style> tags, for the same reason

  libxml_use_internal_errors(true); // Stop the loadHtml call from spitting out a million errors.
  $doc = new DOMDocument(); // Initiate the PHP DomDocument.
  $doc->preserveWhiteSpace = false; // Don't worry about annoying whitespace.
  $doc->substituteEntities = false;
  $doc->formatOutput = false;
  $doc->loadHTML($contents); // Load the HTML.
  
  if ($config['baseMethod']) {
    $xpath = new DOMXPath($doc);
    $results = $xpath->query('head');
    $head = $results->item(0);

    $base = $doc->createElement('base');
    $base->setAttribute('href', "http://localhost/{$me}?url={$urlParts['host']}/");

    $head->appendChild($base);

    return $doc->saveHTML();
  }
  else {
    // Process LINK tags
    $linkList = $doc->getElementsByTagName('link');
    for ($i = 0; $i < $linkList->length; $i++) {
      if ($linkList->item($i)->hasAttribute('href')) {
        if ($linkList->item($i)->getAttribute('type') == 'text/css' || $linkList->item($i)->getAttribute('rel') == 'stylesheet') {
          $linkList->item($i)->setAttribute('href', aviewer_format($linkList->item($i)->getAttribute('href')) . '&type=css');
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
        if ($config['scriptDispose']) $scriptDrop[] = $scriptList->item($i);
        else $scriptList->item($i)->nodeValue = htmlentities(aviewer_processJavascript($scriptList->item($i)->nodeValue));
      }
    }
    foreach ($scriptDrop AS $drop) {
      $drop->parentNode->removeChild($drop);
    }

    // Process STYLE tags.
    $styleList = $doc->getElementsByTagName('style');
    for ($i = 0; $i < $styleList->length; $i++) {
      $styleList->item($i)->nodeValue = htmlentities(aviewer_processCSS($styleList->item($i)->nodeValue));
    }

    // Process BASE tags. (EXPERIMENTAL)
    // These are processed really strangely from what I've seen. I've only found one case in the wild so far, and the replacment works with it.
    $baseList = $doc->getElementsByTagName('base');
    for ($i = 0; $i < $baseList->length; $i++) {    
      if ($baseList->item($i)->hasAttribute('href')) {
        $baseList->item($i)->setAttribute('href', aviewer_format($baseList->item($i)->getAttribute('src')));
      }
    }

    // Process IMG, VIDEO, AUDIO, IFRAME tags
    foreach (array('img', 'video', 'audio', 'frame', 'iframe', 'applet') AS $ele) {
      $imgList = $doc->getElementsByTagName($ele);
      for ($i = 0; $i < $imgList->length; $i++) {
        if ($imgList->item($i)->hasAttribute('src')) {
          $imgList->item($i)->setAttribute('src', aviewer_format($imgList->item($i)->getAttribute('src')));
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

    if ($config['dirtyAttributes']) { // This is an incredibly horrible config in which we will parse the CSS of every single element in the document. It takes way too long, but the alternative, regex, is too unreliable.
      $docAll = new RecursiveIteratorIterator(
        new RecursiveDOMIterator($doc),
        RecursiveIteratorIterator::SELF_FIRST
      );

      foreach($docAll as $node) {
        if ($node->nodeType === XML_ELEMENT_NODE) {
          if ($node->hasAttribute('style')) {
            $node->setAttribute('style', str_replace("\n", '', aviewer_processCSS($node->getAttribute('style'))));
          }
          foreach (array('onclick', 'onmouseover', 'onmouseout', 'onfocus', 'onblur', 'onchange', 'onsubmit') as $att) {
            if ($node->hasAttribute($att)) {
              $node->setAttribute($att, str_replace("\n", '', aviewer_processJavascript($node->getAttribute($att))));
            }
          }
        }
      }
    }

    if (isset($config['htmlReplacePost'])) {
      foreach ($config['htmlReplacePost'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
    }

    return $doc->saveHTML(); // Return the updated data.
  }
}


/**
 * Rewrites Javascript
 * @global string $config
 * @global string $urlParts
 * @param string $contents&&
 * @return string
 */
function aviewer_processJavascript($contents) {
  global $config, $urlParts;

  if ($config['baseMethod']) {
    return $contents;
  }
  else {
    if (isset($config['jsReplacePre'])) {
      foreach ($config['jsReplacePre'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
    }

    $contents = preg_replace('/\/\*(.*?)\*\//is', '', $contents); // Removes comments.

    if (in_array('suspectFileAnywhere', $config['scriptHacks'])) { // Convert anything that appears to be a suspect file. Because of the nature of this, there is a high chance stuff will break if enabled. But, it allows some sites to work properly that otherwise wouldn't.
      $contents = preg_replace_callback('/(([a-zA-Z0-9\_\-\/]+)(\.(' . implode('|', $config['recognisedExtensions']) . ')))([^a-zA-Z0-9])/i', function($m) {
        return aviewer_format($m[1]) . $m[5];
      }, $contents); // Note that if the extension is followed by a letter or integer, it is possibly a part of a JavaScript property, which we don't want to convert
    }
    elseif (in_array('suspectFileString', $config['scriptHacks'])) { // Convert strings that contain files ending with suspect extensions.
      $contents = preg_replace_callback('/("|\')(([a-zA-Z0-9\_\-\/]+)\.(' . implode('|', $config['recognisedExtensions']) . '))\1/i', function($m) {
        return $m[1] . aviewer_format($m[2]) . $m[1] . 2;
      }, $contents);
    }

    if (in_array('suspectDomainAnywhere', $config['scriptHacks'])) {
      $contents = str_replace('http://' . $urlParts['host'], $_SERVER['PHP_SELF'] . '?url=' . $urlParts['host'], $contents); // In some cases, the URL may be dropped directly in. This is an unreliable method of trying to replace it with the equvilent aviewer.php script, and is only used with the eccentric method, since this is rarely used when string-dropped.
    }

    if (in_array('suspectDirString', $config['scriptHacks'])) {
      $contents = preg_replace_callback('/("|\')((\/|)((([a-zA-Z0-9\_\-]+)\/)+))\1/i', function($m) {
        return $m[1] . aviewer_format($m[2]) . $m[1];
      }, $contents);
    }

    if (isset($config['jsReplacePost'])) {
      foreach ($config['jsReplacePost'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
    }

    return $contents; // Return the updated data.
  }
}


/**
 * Process CSS.
 * @param string $contents
 * @return string
 */
function aviewer_processCSS($contents) {
  if ($config['baseMethod']) {
    return $contents;
  }
  else {
    if (isset($config['cssReplacePre'])) {
      foreach ($config['cssReplacePre'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
    }

    $contents = preg_replace('/\/\*(.*?)\*\//is', '', $contents); // Removes comments.
  //  $contents = str_replace(';',";\n", $contents); // Fixes an annoying REGEX quirk below; I won't go into it.
    $contents = preg_replace_callback('/url\((\'|"|)(.+?)\\1\)/i', function($m) {
      return 'url(' . $m[1] . aviewer_format($m[2]) . $m[1] . ')';
    }, $contents); // CSS images are handled with this.

    if (isset($config['cssReplacePost'])) {
      foreach ($config['cssReplacePost'] AS $find => $replace) $contents = str_replace($find, $replace, $contents);
    }

    return $contents; // Return the updated data.
  }
}


/*
 * Courtesy of https://github.com/salathe/spl-examples/wiki/RecursiveDOMIterator
 */
class RecursiveDOMIterator implements RecursiveIterator
{
    protected $_position;
    protected $_nodeList;
    public function __construct(DOMNode $domNode)
    {
        $this->_position = 0;
        $this->_nodeList = $domNode->childNodes;
    }
    public function getChildren() { return new self($this->current()); }
    public function key()         { return $this->_position; }
    public function next()        { $this->_position++; }
    public function rewind()      { $this->_position = 0; }
    public function valid()
    {
        return $this->_position < $this->_nodeList->length;
    }
    public function hasChildren()
    {
        return $this->current()->hasChildNodes();
    }
    public function current()
    {
        return $this->_nodeList->item($this->_position);
    }
}
?>