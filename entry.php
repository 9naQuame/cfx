<?php
/*
 * WYF Framework
 * Copyright (c) 2011 James Ekow Abaka Ainooson
 * 
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

/**
 * Main entry script for the framework. 
 */

/**
 * If the request is intended for the API then setup the session handlers
 * since the API caller may not have session cookies stored.
 */
if(isset($_REQUEST["__api_session_id"]))
{
    session_id($_REQUEST["__api_session_id"]);
    unset($_REQUEST["__api_session_id"]);
    unset($_POST["__api_session_id"]);
    unset($_GET["__api_session_id"]);
}

require "cfx_bootstrap.php";


$authExcludedPaths = array();

$t = new TemplateEngine();
Application::$templateEngine = $t; 

if(defined('AUTH_PACKAGE'))
{
    $authPackage = AUTH_PACKAGE;
}
else
{
    $authPackage = 'system';
}

$authExcludedPaths[] = "{$authPackage}/login";

// Authentication ... check if someone is already logged in if not force 
// a login
if ($_SESSION["logged_in"] == false && array_search($_GET["q"], $authExcludedPaths) === false && substr($_GET["q"], 0, 10) != "system/api")
{
    $redirect = urlencode(Application::getLink("/{$_GET["q"]}"));
    foreach($_GET as $key=>$value) 
    {
        if($key == "q") continue;
        $redirect .= urlencode("$key=$value");
    }
    header("Location: ".Application::getLink("/{$authPackage}/login") . "?redirect=$redirect");
}
else if ($_SESSION["logged_in"] === true )
{        
    // Force a password reset if user is logging in for the first time
    if ($_SESSION["user_mode"] == 2 && $_GET["q"] != "{$authPackage}/login/change_password")
    {
        header("Location: " . Application::getLink("/{$authPackage}/login/change_password"));
    }

    Application::addJavaScript(Application::getLink(Application::getWyfHome("js/wyf.js")));

    $t->assign('username', $_SESSION["user_name"]);
    $t->assign('firstname', $_SESSION['user_firstname']);
    $t->assign('lastname', $_SESSION['user_lastname']);

    if (isset($_GET["notification"]))
    {
        $t->assign('notification', "<div id='notification'>" . $_GET["notification"] . "</div>");
    }

    // Load the side menus
    if(defined('MENU_FILE'))
    {
        $menuFile = MENU_FILE;
    }
    else
    {
        $menuFile = SOFTWARE_HOME . "app/cache/menus/side_menu_{$_SESSION["role_id"]}.html";
    }

    if(file_exists($menuFile))
    {
        $t->assign(
            'side_menu', 
            file_get_contents($menuFile)
        );
    }

    $top_menu_items = explode("/", $_GET["q"]);
    if($top_menu_items[0] != '')
    {
        for($i = 0; $i < count($top_menu_items); $i++)
        {
            $item = $top_menu_items[$i];
            $link .= "/" . $item;
            while(is_numeric($top_menu_items[$i + 1]))
            {
                $link .= "/" . $top_menu_items[$i + 1];
                $i++;
            }
            $item = str_replace("_", " ", $item);
            $item = ucwords($item);
            $top_menu .= "<a href='".Application::getLink($link)."'><span>$item</span></a>";
        }
        $t->assign('top_menu', $top_menu);
    }
}

// Log the route into the audit trail if it is enabled
if($_SESSION['logged_in'] == true && ($_GET['q']!='system/api/table') && ENABLE_AUDIT_TRAILS === true)
{
    $data = json_encode(
        array(
            'route' => $_GET['q'],
            'request' => $_REQUEST,
            'get' => $_GET,
            'post' => $_POST
        )
    );

    if(class_exists("SystemAuditTrailModel", false) && ENABLE_ROUTING_TRAILS === true)
    {
        SystemAuditTrailModel::log(
            array(
                'item_id' => '0',
                'item_type' =>'routing_activity',
                'description' => "Accessed [{$_GET['q']}]",
                'type' => SystemAuditTrailModel::AUDIT_TYPE_ROUTING,
                'data' => $data
            )
        );
    }
}  

if(!isset($fapiStyleSheet))
{
    Application::preAddStylesheet("css/fapi.css", "vendor/ekowabaka/cfx/fapi/");
}
else
{
    Application::preAddStylesheet($fapiStyleSheet);
}

Application::preAddStylesheet("kalendae/kalendae.css", Application::getWyfHome('js/'));
Application::preAddStylesheet("css/main.css");
Application::addJavaScript(Application::getLink(Application::getWyfHome("fapi/js/fapi.js")));
Application::addJavaScript(Application::getLink(Application::getWyfHome("js/jquery.js")));
Application::addJavaScript(Application::getLink(Application::getWyfHome("js/kalendae/kalendae.js")));

// Blast the HTML code to the browser!
Application::setSiteName(Application::$config['name']);
Application::render();
