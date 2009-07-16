<?php
/* SVN FILE: $Id: bootstrap.php,v 1.4 2006/08/26 03:29:13 wclouser%mozilla.com Exp $ */
/**
 * Short description for file.
 *
 * Long description for file
 *
 * PHP versions 4 and 5
 *
 * CakePHP :  Rapid Development Framework <http://www.cakephp.org/>
 * Copyright (c)	2006, Cake Software Foundation, Inc.
 *								1785 E. Sahara Avenue, Suite 490-204
 *								Las Vegas, Nevada 89104
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright		Copyright (c) 2006, Cake Software Foundation, Inc.
 * @link				http://www.cakefoundation.org/projects/info/cakephp CakePHP Project
 * @package			cake
 * @subpackage		cake.app.config
 * @since			CakePHP v 0.10.8.2117
 * @version			$Revision: 1.4 $
 * @modifiedby		$LastChangedBy: phpnut $
 * @lastmodified	$Date: 2006/08/26 03:29:13 $
 * @license			http://www.opensource.org/licenses/mit-license.php The MIT License
 */
/**
 *
 * This file is loaded automatically by the app/webroot/index.php file after the core bootstrap.php is loaded
 * This is an application wide file to load any function that is not used within a class define.
 * You can also use this to include or require any files in your application.
 *
 */
/**
 * The settings below can be used to set additional paths to models, views and controllers.
 * This is related to Ticket #470 (https://trac.cakephp.org/ticket/470)
 *
 * $modelPaths = array('full path to models', 'second full path to models', 'etc...');
 * $viewPaths = array('this path to views', 'second full path to views', 'etc...');
 * $controllerPaths = array('this path to controllers', 'second full path to controllers', 'etc...');
 *
 */

// Make the app and l10n play nice with Windows.
if (substr(PHP_OS, 0, 3) == 'WIN')
    define('WINDOWS', 1);

// Load database and URL configuration.
require_once ROOT.DS.APP_DIR.DS.'config'.DS.'config.php';

// Require global constants.
require_once ROOT.DS.APP_DIR.DS.'config'.DS.'constants.php';

// Required for translating the templates (using gettext)
require_once ROOT.DS.APP_DIR.DS.'config'.DS.'language.php';

// Require language config file, containing arrays for valid, right-to-left,
// and native language strings.
require_once ROOT.DS.APP_DIR.DS.'config'.DS.'language.inc.php';

// Indicate which node is serving the request, for debugging assistance in a
// load-balanced environment.
header('X-AMO-ServedBy: ' . php_uname('n'));

if (DISABLE_AMO) {
    if ($_GET['url'] != 'en-US/instantbird/disabled.php') {
        global $webpath;
        if (is_null($webpath)) {
            require_once(CAKE.DS.'dispatcher.php');
            $_dispatcher = new Dispatcher();
            $_dispatcher->parseParams('');
            $_appDirName = str_replace('/', '\/', preg_quote(APP_DIR));
            $webpath = preg_replace('/'.$_appDirName.'.*/','',$_dispatcher->baseUrl());
        }

        $fullbaseurl = FULL_BASE_URL;
        if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']))
            preg_replace('/^http:/i', 'https:', $fullbaseurl);
        if ($webpath{0} != '/' && substr($fullbaseurl, -1) != '/')
            $fullbaseurl .= '/';
            
        $target = $fullbaseurl.$webpath.'/disabled.php';

        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0, private');
        header('Pragma: no-cache');
        header('Location: '.$target);
    }

    exit;
}

if (defined('FB_ENABLED') && FB_ENABLED == 'true') {
    if (defined('FB_BOUNCE_PERCENTAGE') && FB_BOUNCE_PERCENTAGE > 0) {
        if (rand(1, 100) <= FB_BOUNCE_PERCENTAGE && strpos($_SERVER['REQUEST_URI'], 'outage') === false) {
            die('<fb:ref url="'.SITE_URL.'/facebook/outage" />');
        }
    }
}

global $webpath;
$webpath = null;  // Relative webpath.
$buf = array();  // Temp array.

// Build our language_config object.  This won't go through the steps to actually
// set the language, since we might just be redirecting shortly
$language_config = new LANGUAGE_CONFIG($valid_languages, $supported_languages, false);

// Grab a language (will attempt to detect, otherwise, fallback)
$lang = $language_config->detectCurrentLanguage();
    
// XSS check - if there are weird symbols in this, they lose their chance, and
// they get a "bad request" response
// We don't run this test on the API: preg only considers \w that
// applies to the currently running PHP locale and hence thinks non en 
// characters are non word characters.  This doesn't affect regular AMO
// urls because the url format is different (?foo=bar as opposed to
// /bar/.  The GET params are not included in the $_GET['url'] as
// generated by Cake.
// The API has its own parameter santization which is UTF-8 aware.
//  This comment is now longer than the code change. 
if (array_key_exists('url',$_GET) && 
    !preg_match('/\/api\//', $_GET['url']) && 
    preg_match('/[^\w\d\/\.\-_!: ]/u',$_GET['url'])) {
    header("HTTP/1.1 400 Bad Request");
    exit;
}
if (isset($_SERVER['HTTP_MOZ_REQ_METHOD']) && $_SERVER['HTTP_MOZ_REQ_METHOD'] == 'HTTPS') {
    $_SERVER['HTTPS'] = 'on';
}

function redirectWithNewLocaleAndExit($pathParts) {
    // we need this pile of code right before we
    // redirect, so we can get our "base web path".
    global $webpath, $valid_languages;
    if (is_null($webpath)) {
        require_once(CAKE.DS.'dispatcher.php');
        $_dispatcher = new Dispatcher();
        $_dispatcher->parseParams('');
        $_appDirName = str_replace('/', '\/', preg_quote(APP_DIR));
        $webpath = preg_replace('/'.$_appDirName.'.*/','',$_dispatcher->baseUrl());
    }
    
    if (empty($pathParts))
        $location = '/';
    else
        $location = implode('/', $pathParts);
        
    if ((substr($webpath,-1) != '/') && ($location{0} != '/'))
        $webpath .= '/';
    
    // make sure the redirection base url is https if https is applicable
    $fullbaseurl = FULL_BASE_URL;
    if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']))
        preg_replace('/^http:/i', 'https:', $fullbaseurl);
    if ($webpath{0} != '/' && substr($fullbaseurl, -1) != '/')
        $fullbaseurl .= '/';

    // preserve GET variables if present
    $ignore_get_variables = array('url', 'origurl', 'lang', 'addons-author-addons-select'); // GET variable names not to be preserved
    $myget = array();
    foreach ($_GET as $key => $value) {
        if (in_array($key, $ignore_get_variables)) continue;
        $myget[] = urlencode($key) .'='. urlencode($value);
    }

    // build redirection target
    $target = $fullbaseurl.$webpath.$location;
    if (!empty($myget)) $target .= '?'.implode('&', $myget);

    // If they are requesting a language, don't send the no-cache headers
    if (!(array_key_exists('lang', $_GET) && array_key_exists($_GET['lang'], $valid_languages))) {
        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0, private');
        header('Pragma: no-cache');
    }
    header('Location: '.$target);
    exit;
}

// If it is not set, we are at the root
if (!array_key_exists('url', $_GET) || empty($_GET['url'])) {
    // Refresh the language, detecting it from the browser
    $lang = $language_config->detectCurrentLanguage(true);
    redirectWithNewLocaleAndExit(array($lang, ''));
}

// If someone goes to a URL without a language, we need to redirect them to one with
// a language, so caching works correctly.  If url is set, replace current locale 
// with new locale and send the user along.

$buf = explode('/', $_GET['url']);

global $app_shortnames, $valid_layouts, $other_layouts;

// If we already have a set locale, overwrite it.
if (array_key_exists($buf[0],$valid_languages)) {
    // Only redirect if we're going to a different language
    if ($buf[0] != $lang) {
        $buf[0] = $lang;
        redirectWithNewLocaleAndExit($buf);
    }
} elseif (!empty($buf[1]) && array_key_exists($buf[1], $valid_layouts)) {
    // The first URL param isn't a language but the second one is an application/layout.
    // Best guess is they went to an unsupported language, like /xx/instantbird/
    array_shift($buf);
    $lang = $language_config->detectCurrentLanguage(true);
    array_unshift($buf, $lang);
    redirectWithNewLocaleAndExit($buf);

} else {
    // The first URL param isn't a language and the second one isn't an application.
    // We'll give it a language for now and it'll get an app with the next redirect
    // (would be better to do this all in one go)
    $lang = $language_config->detectCurrentLanguage(true);
    array_unshift($buf, $lang);
    redirectWithNewLocaleAndExit($buf);
}

// Now make sure that there's a known app/layout in the second position.
if (count($buf) < 2 || !array_key_exists($buf[1], $valid_layouts)) {
    // No app or unknown app, so we see if this is seamonkey, otherwise stick
    // instantbird in the place of honour and redirect.
    if (array_key_exists('HTTP_USER_AGENT', $_SERVER) && 
        strpos($_SERVER['HTTP_USER_AGENT'], 'SeaMonkey') !== false) {
        array_splice($buf, 1, 0, "seamonkey");
    } else {
        array_splice($buf, 1, 0, "instantbird");
    }
    redirectWithNewLocaleAndExit($buf);
}

// For other functions/classes
define('SITE_LAYOUT', $buf[1]);

if (array_key_exists(SITE_LAYOUT, $other_layouts)) {
    // If this is a non-app layout, see if the previous app layout was set
    
    if (!empty($_COOKIE['AMOappName']) && array_key_exists($_COOKIE['AMOappName'], $app_shortnames)) {
        define('APP_SHORTNAME', $_COOKIE['AMOappName']);
    }
    else {
        // App wasn't set, so default to Instantbird
        define('APP_SHORTNAME', 'instantbird');
    }
    define('LAYOUT_NAME', $other_layouts[SITE_LAYOUT]);
}
else {
    define('APP_SHORTNAME', $buf[1]);
    define('LAYOUT_NAME', $buf[1]);
    
    // If app cookie is set and it's different from the current app, update the
    // cookie
    if (!empty($_COOKIE['AMOappName']) && $_COOKIE['AMOappName'] != APP_SHORTNAME) {
        setcookie('AMOappName', APP_SHORTNAME, 0, '/');
    }
}

define('APP_ID', $app_shortnames[APP_SHORTNAME]);
// Sets up all the gettext functions for our language
$language_config->setCurrentLanguage(array($lang));

// For other functions/classes
define('LANG', $lang);

if (in_array(LANG, $rtl_languages)) {
    define('TEXTDIR','rtl');
} else {
    define('TEXTDIR','ltr');
}

global $app_prettynames;
$app_prettynames = array(
    'instantbird' => ___('main_prettyname_instantbird')
    );
define('APP_PRETTYNAME', $app_prettynames[APP_SHORTNAME]);

// Get rid of the temp vars
unset($webpath, $buf, $lang, $language_config);

/**
 * @var flush_lists list of cache list ids to flush (Not their cache keys!)
 * See AppController::afterFilter()
 */
global $flush_lists;
$flush_lists = array();

/**
 * We depend on the order of these licenses, so this list is append-only.
 */
global $licenses;
$licenses = array(
    ___('licenses_mpl_1.1'),
    ___('licenses_gpl_2.0'),
    ___('licenses_gpl_3.0'),
    ___('licenses_lgpl_2.1'),
    ___('licenses_lgpl_3.0'),
    ___('licenses_mit'),
    ___('licenses_bsd')
);
//EOF
?>