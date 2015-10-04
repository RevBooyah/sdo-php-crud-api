<?php

require_once("globals.php");

/**
 * Steve's Database Objects
 * CRUD Basis for all objects made from tables in the database schema
 * Contains classes for:
 *   * db   - database abstract class
 *   * mysql - the MySQL extension of the db class
 *   * SDO - the Steve's Database Object class
 *
 *   NOTE: This should be used in conjunction with the gen_sdo_classes.php script
 *   to generate the default clases for each table. They can then be extended with
 *   specific classes for additional functionality. 
 *
 *   More Info: http://github.com/revbooyah/sdo
 *
 *   Copyright 2015, Stephen Cook & BooyahMedia LLC. All Rights Reserved.
 **/


// Define the abstract version of the class - to add support for other DB's (postgres,mongodb, cassandra, etc.)
/**
 * Abstract class for all database interactions. It's a basic singleton pattern.
 *
 **/
abstract class db {
    /**
     * This is the method to call when crateing the instance.  $db=db::factory();
     * @param string|null       The type of database object.
     **/
    public static function factory($type="mysql") {
        return call_user_func(array($type, 'getInstance'));
    }
    abstract public function query($query);
    abstract public function getArray($query);
    abstract public function getRow($query);
    //abstract public function insertGetID($query);
    //abstract public function clean($string);
}


/**
 * The SDO MySQL DB Singleton Class - mysql extension of db for Steve's Database Object
 *
 * It handles the singleton for MySQL databases.
 *
 * @category            Database abtraction and CRUD
 * @package             SDO
 * @package_version     0.1
 * @author              Stephen Cook <booyahmedia@gmail.com>
 * @copyright           2015 (c) Stephen Cook and BooyahMedia LLC
 * @license             GNU General Public License v3 http://www.gnu.org/licenses/gpl-3.0.en.html
 * @version             0.1
 * @link                http://github.com/revbooyah/sdo-php-crud-api
 * @since               Class available since Release 0.1 - Sep 29, 2015
 **/
class mysql extends db {
    protected static $instance = null; // the single instance
    //protected $link;
    public $link;

    public static function getInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    // The __construct method is protected, so it can only be called from within the class (getInstance).
    protected function __construct() {
        try {
            $dsn="mysql:host=".DB_HOST.";dbname=".DB_NAME;
            $this->link=new PDO($dsn,DB_USER,DB_PASS);
        } catch (PDOException $e) {
            // If the database don't work - there ain't nothin' to do but die.
            echo "FATAL ERROR: Database connection failed: ".$e->getMessage();
            exit();
        }
    }


    public function getArray($query,$params=array()) {
        $q = $this->link->prepare($query);
        $res=$q->execute($params);
        if($res===false) { 
            print_r($this->link->errorInfo());
            return(false);
        }
        return($q->fetchAll(PDO::FETCH_ASSOC)); // Doesn't get the number index keys.
    }

    /**
     * Get a single row of results (or return just the first row)
     * 
     * @param mixed $query   The query to send to the database
     * @param mixed $params  an array to be parsed by the PDO::execute
     */
    public function getRow($query,$params=array()) {
        $q = $this->link->prepare($query);
        $res=$q->execute($params);
        if(!$res) {
            print_r($this->link->errorInfo());
            return(false);
        }
        return($q->fetch(PDO::FETCH_ASSOC));
    }


    public function query($query,$params=array()) {
        $q = $this->link->prepare($query);
        $res=$q->execute($params);
        if(!$res) {
            print_r($this->link->errorInfo());
            return(false);
        }
        if(!$res) {
            print_r($this->link->errorInfo());
            print("BAD QUERY PARAMS: ".print_r($query,true)."\n".print_r($params,true)."\n");
            return(false);
        }

        if($q->rowCount()>0) {
            $out=$q->fetch(PDO::FETCH_ASSOC);
            if($out==false) $out=true;
            return($out);
        } else {
            return(1);
        }
    }


    public function insertGetID($query,$params=array()) {
        if(count($params)<1) {
            $firstResult=$this->link->exec($query);
            $result=$this->link->lastInsertId();
        } else {
            $q = $this->link->prepare($query);
            $result=$q->execute($params);
        }
        if(!$result) {
            print_r($this->link->errorInfo());
            return(0);
        }
        return $this->link->lastInsertId();
    }


    public function clean($string,$len=0) {
        // But seriously - use the PDO::prepare(). Use this for things like column names
        $tmp = trim($this->link->quote($string),"'");
        if($len>0) $tmp = substr($tmp,0,$len);
        return($tmp);
    }


}



/**
 * The SDO Class - Steve's Database Object
 *
 * It handles turning database tables into base objects, with CRUD methods, so that you 
 * can extend them into usable objects.
 *
 * @category            Database abtraction and CRUD
 * @package             SDO
 * @package_version     0.1
 * @author              Stephen Cook <booyahmedia@gmail.com>
 * @copyright           2015 (c) Stephen Cook and BooyahMedia LLC
 * @license             GNU General Public License v3 http://www.gnu.org/licenses/gpl-3.0.en.html
 * @version             0.1
 * @link                http://github.com/revbooyah/sdo
 * @since               Class available since Release 0.1 - Sep 29, 2015
 **/
class SDO {
    public $className;

    public function __construct() {
        echo "FATAL ERROR. Illegal constructor call for this data type: ".__CLASS__."\n";
        exit();
    }
    public function initObject() {
        echo "FATAL ERROR. Illegal initObject() call for this data type: ".__CLASS__."\n";
        exit();
    }


    public function getInstance($id=0) {
        $id=intval($id);
        if($this->className=='' || strlen($this->className)<2) {
            echo "FATAL ERROR. Illegal getInstance() call for this data type: ".__CLASS__."\n Please add className.\n";
            exit();
        }

        if($id>0) {
            return($this->readObject($id));
        }
        return($this->initObject()); 
    }

    public function readObject($id) {
        $db=db::factory();
        $id=intval($id);
        if($id<1) return($this->initObject());
        $sth = $db->link->prepare("SELECT * FROM ".$this->className." WHERE ".$this->className."ID='$id' LIMIT 1");
        $sth->setFetchMode(PDO::FETCH_INTO,$this);
        $sth->execute();
        $sth->fetch();
        return($this);
    }

    public function save($fields='') { return($this->updateObject($fields)); } // alias for updateObjects

    public function updateObject($fields='') {
        $db=db::factory();
        // This is the save. If fields is an array - only updates those fields.
        if(intval($this->{$this->className."ID"})<1) return($this->saveNewObject());
        if(is_array($fields) && count($fields)>0) {
            $q="UPDATE ".DB_NAME.".".$this->className." SET ";
            $params=array();
            $COMMA='';
            foreach($fields as $key=>$val) {
                if($key!='className' && $key[0]!='_') { // Any non-db fields should start with _
                    $params[$key]=$val;
                    $q.="$COMMA$key=:$key ";
                    $COMMA=',';
                }
            }
        } else {
            $q="UPDATE ".DB_NAME.".".$this->className." SET ";
            $params=array();
            $COMMA='';
            foreach($this as $key=>$val) {
                if($key!='className' && $key[0]!='_') { // Any non-db fields should start with _
                    $params[$key]=$val;
                    $q.="$COMMA$key=:$key ";
                    $COMMA=',';
                }
            }
        }
        $q.=" WHERE ".$this->className."ID = ".$this->{$this->className."ID"}." LIMIT 1";
        //print("Q: $q");
        $sth = $db->link->prepare($q);
        if(!$sth) { print_r($sth->errorInfo()); return(false); }
        $result = $sth->execute($params);
        if(!$result) { print_r($sth->errorInfo()); return(false); }
        return(true);
    }

    public function saveNewObject() {
        $db=db::factory();
        $q="INSERT INTO ".DB_NAME.".".$this->className." (";
        $vals=" VALUES (";
        $params=array();
        $COMMA='';
        foreach($this as $key=>$val) {
            if($key!='className' && $key[0]!='_') { // Any non-db fields should start with _
                $params[$key]=$val;
                $q.="$COMMA$key ";
                $vals.="$COMMA:$key ";
                $COMMA=',';
            }
        }
        $q.=")".$vals.")";
        $sth = $db->link->prepare($q);
        if(!$sth) { print_r($sth->errorInfo()); return(false); }
        $result = $sth->execute($params);
        if(!$result) { print_r($sth->errorInfo()); return(false); }
        $this->{$this->className."ID"}=$db->link->lastInsertID();
        return(true);
    }


    public function deleteObject($id=0,$permanent=true) {
        $db = db::factory();

        if($id==0) {
            if(intval($this->{$this->className."ID"})<1) return(false);
            $id = intval($this->{$this->className."ID"});
        } else {
            $id = intval($id);
        }
        // permanent=false will just set Active=0;
        $q="DELETE FROM ".DB_NAME.".".$this->className;
        $q.=" WHERE ".$this->className."ID = ".$id." LIMIT 1";
        $sth = $db->link->query($q);
        if(!$sth) { print_r($sth->errorInfo()); return(false); }
        return(true);
    }

}
