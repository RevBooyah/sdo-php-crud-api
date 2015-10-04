<?php
/**
* Make crud calls to the database objects and outputs in json. (useful in Angular.js)
* Assumes path of /<api-dir>/<table-name>/
**/

require_once("globals.php");
require_once("class.sdo.php");

//$uri=explode("/",$_SERVER['REQUEST_URI']);
$uri=explode("/",$_SERVER['REDIRECT_URL']);
$oType='';
$id=0;

if(!isset($uri[2]) || strlen($uri[2])<2) {
    echo json_encode(array('result'=>'error','response'=>"Unable to determine object details."));
    exit();
} else {
    $oType=trim(urldecode($uri[2]));
    // First, filter the class name - only A-Za-z0-9\_\-
    if(!preg_match('/^[A-Za-z0-9\.\_\-]+$/',$oType)) {
        echo json_encode(array('result'=>'error','response'=>"Unable to determine valid object details. $oType"));
        exit();
    }
    if(!file_exists(CLASS_DIR."class.".$oType.".php")) {
        echo json_encode(array('result'=>'error','response'=>"Unknown object. $oType"));
        exit();
    }

    require_once(CLASS_DIR."class.".$oType.".php");
}
if($oType=='') { // SHOULD NEVER HAPPEN!!!!
    echo json_encode(array('result'=>'error','response'=>"Unknown object - Unavailable."));
    exit();
}


if(!isset($uri[3]) || intval($uri[3])<1) {
    if(isset($_REQUEST['id']) ) $id=intval($_REQUEST['id']);
} else {
    $id=intval($uri[3]);
}

// if the ID is negative, it's a new one.
if($id<0) {
    $ob = new $oType(0);
    $ob->{$oType."ID"}=0;
    echo json_encode($ob);
    exit();
}


if($_SERVER['REQUEST_METHOD']=="DELETE" && $id>0) {
    // Delete the item
    $ob = new $oType();
    $result = $ob->deleteObject($id);
    if(!$result) {
        echo json_encode(array('result'=>'error','response'=>"Unable to delete the $oType."));
    } else {
        echo json_encode(array('result'=>'success','response'=>"$oType $id deleted."));
    }
    exit();
} else if ($_SERVER['REQUEST_METHOD']=="DELETE") {
    echo json_encode(array('result'=>'error','response'=>"No Sender ID Included."));
    exit();
}

$pst = json_decode(file_get_contents("php://input"),true);
$newid=0;

if(is_array($pst) && count($pst)>0) {
    // It's a save or an update.

    // If the class contains a filterInput method, use it. If not - be unsafe! :)
    $useFilter = (method_exists($oType,'filterInput'))?true:false;
    $errors=array();

    $sid=isset($pst[$oType.'ID'])?intval($pst[$oType.'ID']):0;
    if($sid>0) {
        $s = new $oType($sid);
        if($s->{$oType."ID"} == $sid) {
            if($useFilter) list($pst,$errors) =  $s->filterInput($pst);
            foreach($pst as $key=>$val) {
                if($key != "$oType"."ID" && property_exists("DB_".$oType,$key)) {
                    $s->$key=trim($val);
                }
            }
            $s->updateObject();
        } else {
            echo json_encode(array('result'=>'error','response'=>"Unable to retrieve object."));
            exit();
        }
    } else {
        $s = new $oType();
        $s->{$oType.'ID'} = 'NULL';
        if($useFilter) list($pst,$errors) =  $s->filterInput($pst);
        foreach($pst as $key=>$val) {
            if($key != "$oType"."ID" && property_exists("DB_".$oType,$key)) {
                $s->$key=trim($val);
            }
        }
        $newid=$s->saveNewObject();
        if($newid<1) {
            echo json_encode(array('result'=>'error','response'=>"Unable to save new $oType."));
            exit();
        }
    }
    $out = json_encode(array('result'=>"success","response"=>print_r($s,true)));
    echo $out;

    exit();
}



// Now, we just want to send a list - we don't have a filter installed yet... we should add one.
//
$db = db::factory();

if($id>0) {
    $record = $db->getRow("SELECT * FROM $oType WHERE $oType"."ID='$id' LIMIT 1");
    $outp=json_encode($record);
} else {
    $records = $db->getArray("SELECT * FROM $oType");
    $outp=json_encode($records);
}


echo($outp);

exit();
