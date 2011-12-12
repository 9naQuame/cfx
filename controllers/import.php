<?php
/**
 * A PHP script which allows you to upload data into models. This script can
 * work both as a CLI script and it can also be called through the
 * ModelController class. When working as a CLI script, the command takes the 
 * following format
 * @code
 * php import.php <file_to_import> <model_to_import_into> [<user_id>]
 * @endcode
 * 
 * For example to import data from a file <tt>data.csv</tt> into the
 * <tt>system.users</tt> model as a user with id <tt>1</tt> you can enter the
 * following command.
 * 
 * @code
 * php import.php data.csv system.users 1
 * @endcode
 * 
 * @todo This should be implemented as a method in the model controller class to help it take advantage of Global initialisations
 * @file
 * @ingroup Controllers
 */

session_start();
date_default_timezone_set("Africa/Accra");
define("MODEL_IMPORTER",true);
error_reporting(E_ALL ^ E_NOTICE);

set_include_path(get_include_path() . PATH_SEPARATOR . "../../");

include "coreutils.php";
require "../../app/config.php";

add_include_path("lib");
add_include_path("lib/models");
add_include_path("lib/models/datastores");
add_include_path("lib/cache");

require_once "../../lib/models/datastores/databases/$db_driver/$db_driver.php";


Cache::init($cache_method);
define('CACHE_MODELS', $cache_models);

global $cli;

if(defined('STDIN'))
{
    $cli = true;
    $uploadfile = $argv[1];
    $modelName = $argv[2];
    $_SESSION["user_id"]=$argv[3];
    if($uploadfile=="")
    {
        die("Please add the file name as an argument\n\n");
    }
    $cleared = true;
}
else
{
    session_start();

    $uploaddir = '../../app/uploads/';

    //include "connection.php";
    $modelName = $_GET["model"];
    $uploadfile = $uploaddir . basename($_FILES['file']['name']);
    $cleared = move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile);
}

if ($cleared)
{
    $file = fopen($uploadfile,"r");
    $headers = fgetcsv($file);
    $model = Model::load($modelName);//,"../../");
    $fieldInfo = $model->getFields(); array_shift($fieldInfo);
    $fields = array_keys($fieldInfo);
    $primary_key = $model->getKeyField("primary");
    $secondary_key = $model->getKeyField("secondary");
    $tertiary_key = $model->getKeyField("tertiary");
    
    foreach($model->getLabels() as $i => $label)
    {
        if(strtolower($label)!=strtolower($headers[$i]))
        {
            message("Invalid file format ($label and {$headers[$i]} do not match)", $cli, "error", true);
        }
    }
    
    if($secondary_key == null)
    {
        //print "<div id='information'><h4>Warning</h4>  This model has no secondary keys so imported data may overlap</div>";
        message("<h4>Warning</h4> This model has no secondary keys. Imported data may overlap", $cli, "info", false);
    }
    
    
    $out = "<table class='data-table'>";
    $out .= "<thead><tr><td>".implode("</td><td>",$headers)."</td></tr></thead>";
    $out .= "<tbody>";
    $line = 1;
    $status = "<h3>Successfully Imported</h3>";
    
    while(!feof($file))
    {
        $data = fgetcsv($file);
        $model_data = array();
        $errors = array();
        if(count($data)<count($headers)) break;
        
        foreach($data as $i => $value)
        {
            $model_data[$fields[$i]] = $value;
        }                
        $display_data = $model_data;
        
        if($secondary_key!=null && count($errors==false))
        {
            $temp_data = $model->getWithField($secondary_key,$model_data[$secondary_key]);
            if(count($temp_data)>0) 
            {
                if($tertiary_key != "")
                {
                    $model_data[$primary_key] = $temp_data[0][$primary_key];
                    $model_data[$tertiary_key] = $temp_data[0][$tertiary_key];
                }
                $validated = $model->setResolvableData($model_data,$secondary_key,$model_data[$secondary_key]);
                if($validated===true) $model->update($secondary_key,$model_data[$secondary_key]);
            }
            else
            {
                $validated = $model->setResolvableData($model_data);
                if($validated===true) $model->save();
            }
        }
        else
        {
            $validated = $model->setResolvableData($model_data);
            if($validated===true) $model->save();
        }
        
        if($validated===true)
        {
            $out .= "<tr><td>".implode("</td><td>",$display_data)."</td></tr>";
        }
        else
        {
            $out .= "<tr style='border:1px solid red'>";
            foreach($display_data as $field=>$value)
            {
                $out .= "<td>$value";
                if(count($validated["errors"][$field])>0)
                {
                    $out .= "<div class='fapi-error'><ul>";
                    foreach($validated["errors"][$field] as $error)
                    {
                        $error = str_replace("%field_name%",$fieldInfo[$field]["label"],$error);
                        $out .= "<li>$error</li>";
                        if($cli) echo "*** Error on line $line ! [$field] $error ($value)\n";
                    }
                    $out .= "</ul></div>";
                }
                $out .= "</td>";
            }
            $out .= "</tr>";
            $hasErrors = true;
            $status = "<h3>Errors Importing Data</h3><div class='error'>\n\nErrors on line $line</div><div>$errors</div>";
            if($_POST["break_on_errors"]=="1") break;
        }
        $line++;
    }
    $out .= "</tbody>";
    $out .= "</table>";

    if($cli)
    {
        message($status, $cli, null, false);
    }
    else
    {
        print $status.$out;
    }
    die();
}
else
{
    message("Cound not import data", $cli, "error", true);
}

function message($message, $cli, $type, $die)
{
    if($cli)
    {
        print(strip_tags($message)."\n\n");
    }
    else
    {
        print("<div id='$type' >$message</div>");
    }
    if($die) die();
}
