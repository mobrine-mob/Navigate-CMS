<?php
// +------------------------------------------------------------------------+
// | NAVIGATE CMS                                                           |
// +------------------------------------------------------------------------+
// | Copyright (c) Naviwebs 2010-2013. All rights reserved.                 |
// | Last modified 21/12/2012                                               |
// | Email         info@naviwebs.com                                        |
// | Web           http://www.navigatecms.com                               |
// +------------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify   |
// | it under the terms of the GNU General Public License version 2 as      |
// | published by the Free Software Foundation.                             |
// |                                                                        |
// | This program is distributed in the hope that it will be useful,        |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of         |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the          |
// | GNU General Public License for more details.                           |
// |                                                                        |
// | You should have received a copy of the GNU General Public License      |
// | along with this program; if not, write to the                          |
// |   Free Software Foundation, Inc., 59 Temple Place, Suite 330,          |
// |   Boston, MA 02111-1307 USA                                            |
// +------------------------------------------------------------------------+
//

// security fix: force creating a secure $_REQUEST global variable giving priority to $_POST and ignoring $_COOKIE
$_REQUEST = array_merge($_GET, $_POST);

if(isset($_REQUEST['debug']) || APP_DEBUG)
{
    error_reporting(E_ALL ^ E_NOTICE);
    ini_set('display_errors', 1);
}

require_once('cfg/globals.php');
require_once('cfg/common.php');

define('NAVIGATE_URL', dirname($_SERVER['PHP_SELF']));

/* global variables */
global $DB;
global $user;
global $config;
global $layout;
global $website;
global $theme;
global $events;
global $world_languages; // filled in language.class.php

// is a simple keep alive request?
if(@$_REQUEST['fid']=='keep_alive')
{
	session_write_close();
	echo 'true';
	exit;
}

// create database connection
$DB = new database();
if(!$DB->connect())
{
	die(APP_NAME.' # ERROR<br /> '.$DB->get_last_error());
}

// session checking
if(ini_get("session.use_cookies") && !empty($_COOKIE['navigate-session-id']))
{
    if($_COOKIE['navigate-session-id']!=session_id())
    {
        unset($_SESSION);
        core_session_remove();
    }
}

if(empty($_SESSION['APP_USER']) || isset($_GET['logout']))
{	
    if(!empty($_SESSION['APP_USER']))
    {
        $user = new user();
        $user->load($_SESSION['APP_USER']);
        $user->remove_cookie();
    }

    if(isset($_GET['logout']) && @!empty($user->id))
        users_log::action(0, $user->id, 'logout', $user->username);

	core_session_remove();
	
	session_start();
	
	if($_SERVER['QUERY_STRING']!='logout')
		$_SESSION["login_request_uri"] = $_SERVER['QUERY_STRING'];
	
	session_write_close();
	
	header('location: login.php');	
	exit;
}
else
{
	$user = new user();
	$user->load($_SESSION['APP_USER']);

	if(empty($user->id))
		header('location: '.NAVIGATE_MAIN.'?logout');
}

// new updates check -> only Administrator (profile=1)
if($user->profile==1 && empty($_SESSION['latest_update']) && NAVIGATECMS_UPDATES!==false)
{
	$_SESSION['latest_update'] = update::latest_available();
    $_SESSION['extensions_updates'] = extension::latest_available();
}

$idn = new idna_convert();
$lang = new language();
$lang->load($user->language);

if(@$_COOKIE['navigate-language'] != $user->language)
	setcookie('navigate-language', $user->language, time() + 86400 * 30);

set_time_limit(0);

$menu_layout = new menu_layout();
$menu_layout->load();

// load the working website
$website = new Website();

if((@$_GET['act']=='0' || @$_GET['quickedit']=='true') && !empty($_GET['wid']))
	$website->load(intval($_GET['wid']));	// TODO: check if the current user	can edit this website
else if(!empty($_SESSION['website_active']))
	$website->load($_SESSION['website_active']);
else	
{
	$url = nvweb_self_url();		
	$website = nvweb_load_website_by_url($url, false);
	if(!$website)
	{
		$website = new Website();
		$website->load();
	}
}

// if there are no websites, auto-create the first one
if(empty($website->id))
{
    $website->create_default();
    header('location: '.NAVIGATE_MAIN);
    core_terminate();
}

// check allowed websites for this user
$wa = array_filter(explode(',', $user->permission('websites.allowed')));
if(!empty($wa))
{
    if(array_search($website->id, $wa)===false)
    {
        $website = new website();
        if(!empty($wa[0])) // load first website allowed
            $website->load(intval($wa[0]));

        if(empty($website->id) && $user->permission('websites.edit')=='false')
        {
            // NO website allowed AND can't create websites, so auto sign out
            core_session_remove();
            session_start();
            session_write_close();
            header('location: login.php');
            core_terminate();
        }
    }
}

$_SESSION['website_active'] = $website->id;

$events = new events();
$events->extension_backend_bindings();

// no valid website found; show Create first website wizard
if(empty($_SESSION['website_active']) && $_REQUEST['fid']!='websites')
{
	header('location: '.NAVIGATE_MAIN.'?fid=websites&act=wizard');
	core_terminate();
}

// load website basics
$nvweb_absolute = (empty($website->protocol)? 'http://' : $website->protocol);
if(!empty($website->subdomain))
	$nvweb_absolute .= $website->subdomain.'.';
$nvweb_absolute .= $website->domain.$website->folder;

define('NVWEB_ABSOLUTE', $nvweb_absolute);
define('NVWEB_OBJECT', $nvweb_absolute.'/object');	

// prepare layout
$layout = new layout('navigate');

$layout->add_content('<div class="navigate-top"></div>');

$layout->navigate_logo();
$layout->navigate_session();
$layout->navigate_title();

$menu_html = $menu_layout->generate_html();

// load website theme
if(!empty($website->theme))
{
	$theme = new theme();
	$theme->load($website->theme);

    if(!empty($website->theme) && empty($theme->title))
    {
        $layout->navigate_notification(t(439, 'Error loading theme').' '.$website->theme, true);
        firephp_nv::log($website->theme.': JSON ERROR '.json_last_error());
    }
}

$layout->add_content('<div id="navigate-menu">'.$menu_html.'</div>');

$layout->navigate_footer();

$content = core_run();

$layout->add_content('<div id="navigate-content" class="navigate-content ui-corner-all">'.$content.'</div>');

$layout->navigate_additional_scripts();

// print layout
if(!isset($_GET['mute']))
{
	if(!APP_DEBUG && headers_sent())
        ob_start("ob_gzhandler");

    echo $layout->generate();

    if(!APP_DEBUG)
        ob_end_flush();
}

session_write_close();
$DB->disconnect();

?>