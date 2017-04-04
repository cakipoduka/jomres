<?php
/**
 * Core file.
 *
 * @author Vince Wooll <sales@jomres.net>
 *
 * @version Jomres 9.8.29
 *
 * @copyright	2005-2017 Vince Wooll
 * Jomres is currently available for use in all personal or commercial projects under both MIT and GPL2 licenses. This means that you can choose the license that best suits your project, and use it accordingly
 **/

// ################################################################
defined('_JOMRES_INITCHECK') or die('');
// ################################################################
$siteConfig = jomres_singleton_abstract::getInstance('jomres_config_site_singleton');
$jrConfig = $siteConfig->get();
$scriptname = str_replace('/', '', $_SERVER[ 'PHP_SELF' ]);
if (strstr($scriptname, 'install_jomres.php')) {
    set_showtime('live_site', str_replace('/jomres', '', get_showtime('live_site')));
}

$ssllink = str_replace('https://', 'http://', get_showtime('live_site'));
define('JOMRES_ADMINISTRATORDIRECTORY', 'administrator');

//detect jomres itemId
$jomresItemid = 0;

if (!strstr($scriptname, 'install_jomres.php')) {
	$app = JFactory::getApplication(); 
	$menu = $app->getMenu();
	$menuItem = $menu->getItems( 'link', 'index.php?option=com_jomres&view=default', $firstonly = true );
	if ($menuItem) {
		$jomresItemid = (int)$menuItem->id;
	}
}

set_showtime('jomresItemid', $jomresItemid);

$index = 'index.php';
$tmpl = '';
if (!isset($_GET[ 'tmpl' ])) {
    $_GET[ 'tmpl' ] = false;
}

if (!isset($jrConfig[ 'isInIframe' ])) {
    $jrConfig[ 'isInIframe' ] = '0';
}

if (($jrConfig[ 'isInIframe' ] == '1' || $_GET[ 'tmpl' ] == get_showtime('tmplcomponent')) && !isset($_REQUEST[ 'nofollowtmpl' ]) && !jomres_cmsspecific_areweinadminarea()) {
    $tmpl = '&tmpl='.get_showtime('tmplcomponent');
    define('JOMRES_WRAPPED', 1);
    if (!isset($_REQUEST['tmpl'])) {
        $url = (isset($_SERVER['HTTPS']) ? 'https' : 'http')."://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]".'&tmpl=jomres';
        jomresRedirect($url);
    }
} else {
    define('JOMRES_WRAPPED', 0);
}

if (isset($_REQUEST[ 'is_wrapped' ])) {
    if ($_REQUEST[ 'is_wrapped' ] == '1') {
        $tmpl .= '&is_wrapped=1';
    }
}

if (isset($_REQUEST[ 'menuoff' ])) {
    if ($_REQUEST[ 'menuoff' ] == '1') {
        $tmpl .= '&menuoff=1';
        set_showtime('menuoff', true);
    } else {
        $tmpl .= '&menuoff=0';
        set_showtime('menuoff', false);
    }
}

if (isset($_REQUEST[ 'topoff' ])) {
    if ($_REQUEST[ 'topoff' ] == '1') {
        $tmpl .= '&topoff=1';
        set_showtime('topoff', true);
    } else {
        $tmpl .= '&topoff=0';
        set_showtime('topoff', false);
    }
}

$lang = substr(get_showtime('lang'), 0, 2);
//Jomres specific lang switching
$lang_param = '';
if (isset($_REQUEST[ 'jomreslang' ])) {
	$jomreslang = jomresGetParam($_REQUEST, 'jomreslang', '');
    $jomres_language = jomres_singleton_abstract::getInstance('jomres_language');
    if ($jomreslang != '' && array_key_exists($jomreslang, $jomres_language->datepicker_crossref)) {
        $lang_param = '&jomreslang='.$jomreslang;
    }
}

define('JOMRES_SITEPAGE_URL_NOSEF', get_showtime('live_site').'/index.php?option=com_jomres&Itemid='.$jomresItemid.'&lang='.$lang.$tmpl.$lang_param);
define('JOMRES_SITEPAGE_URL_AJAX', get_showtime('live_site').'/'.'index.php?option=com_jomres&no_html=1&jrajax=1&Itemid='.$jomresItemid.'&lang='.$lang.$tmpl.$lang_param);
define('JOMRES_SITEPAGE_URL_ADMIN', get_showtime('live_site').'/'.JOMRES_ADMINISTRATORDIRECTORY.'/index.php?option=com_jomres'.$tmpl.$lang_param);
define('JOMRES_SITEPAGE_URL_ADMIN_AJAX', get_showtime('live_site').'/'.JOMRES_ADMINISTRATORDIRECTORY.'/index.php?option=com_jomres&no_html=1&jrajax=1'.$lang_param.$tmpl);

if (class_exists('JFactory')) {
    $config = JFactory::getConfig();
    if ($config->get('sef') == '1') {
        define('JOMRES_SITEPAGE_URL', $index.'?option=com_jomres&Itemid='.$jomresItemid.$tmpl.'&lang='.$lang.$lang_param);
    } else {
        define('JOMRES_SITEPAGE_URL', get_showtime('live_site').'/'.$index.'?option=com_jomres&Itemid='.$jomresItemid.$tmpl.'&lang='.$lang.$lang_param);
    }
} else {
    define('JOMRES_SITEPAGE_URL', get_showtime('live_site').'/'.$index.'?option=com_jomres&Itemid='.$jomresItemid.$tmpl.'&lang='.$lang.$lang_param);
}
