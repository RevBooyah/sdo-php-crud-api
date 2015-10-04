#!/usr/bin/php -q
<?php

# Generate PDO DB Object with CRUD abilities used with class.sdo.php
# Copyright 2015 - Stephen Cook & BooyahMedia.com LLC
# Author: Steve Cook  <booyahmedia@gmail.com>

set_include_path(".:/www/vhosts/domain.com/lib");

require_once("globals.php");

$dsn="mysql:host=".DB_HOST.";dbname=".DB_NAME;
$pdo=new PDO($dsn,DB_USER,DB_PASS);

CreateDBClass(DB_NAME);

function CreateDBClass($db_name=DB_NAME) {
    global $pdo;

    print("<"."?\n");
    print("\n/*\n ****************************************************************\n");
    print(" * *****   DO NOT EDIT THIS FILE!!!! ******************************\n");
    print(" * ***  This file is generated automatically to create a DATABASE class ONLY.\n");
    print(" * *** Generated on: ".date("r")."\n");
    print(" *****************************************************************\n");
    print(" */\n");
    print("\nrequire_once('globals.php');"); // defines the db details.
    print("\nrequire_once('class.sdo.php');"); // defines the db details.

    $qry=$pdo->prepare("SHOW TABLES FROM $db_name");
    $result = $qry->execute();
    $tables = $qry->fetchAll(); 

    foreach($tables as $table) {
        //echo "Table: $table[0]\n";
        ListFields($table[0]);
    }

    print("\n?".">");

}


function ListFields($table) {
    global $pdo;

    $db_host=DB_HOST;
    $db_name=DB_NAME;
    $db_user=DB_USER;
    $db_pass=DB_PASS;

    print("\n\n\nclass DB_$table extends SDO {\n");

    $fields = array();
   
    $qry = $pdo->query("SELECT * FROM $table LIMIT 0");
    for($x=0;$x < $qry->columnCount(); $x++) {
        $fields[$x] = $qry->getColumnMeta($x);
        //print_r($fields[$x]);
    }

    $fieldnames=array();

    foreach($fields as $field) {
        echo "\tpublic \$".$field['name'].";\n";
    }

    print("\n\tpublic function __construct(\$id=0) {\n");
    print("\t\t\$this->className='".$table."';\n");
    print("\t\treturn(\$this->getInstance(\$id));\n");
    print("\t}\n\n");

    print("\tpublic function initObject(){\n");

    foreach($fields as $field) {
        print("\t\t\$this->".$field['name']."=");
        if(!isset($field['native_type'])) $field['native_type']='LONG';
        if($field['name']==$table."ID") {
            echo "'NULL';\n";
        } else {
            switch($field['native_type']) {
            case "LONG":
            case "LONGLONG":
            case "SHORT":
                print("0;\n");
                break;
            case "real":
            case "REAL":
            case "FLOAT":
            case "DOUBLE":
                print("0.00;\n");
                break;
            case "string":
            case "STRING":
            case "VAR_STRING":
            case "BLOB":
                print("'';\n");
                break;
            case "DATETIME":
            case "DATE":
                print("'';\n");
                break;
            case "TIMESTAMP":
                print(time()."\n");
                break;
            default:
                print("UNKNOWN COLUMN TYPE******* .".$field['native_type'].";\n");
                exit();
                break;
            }
        }
    }
    print("\t}\n\n");
    print("} //End of class $table");

}
