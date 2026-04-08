<?php

require_once PROJECT_ROOT_PATH . "/db/Database.php";
require_once PROJECT_ROOT_PATH . "/data/SqlValueIF.php";

/**
 * Description of ReflectModel
 *
 * @author Rune
 */
class ReflectModel extends Database {

    private static $DEBUGINFO = false;
    
    public ReflectionClass $clazz;
    public string $table;
    public string $dbid;
    public $properties;
    public $types;
    public $dbtypes;
    public $arraytypes;

    public function __construct(ReflectionClass $clazz, string $table, string $dbid) {
        $starttime = microtime(true);
        parent::__construct();
        $this->debuginfo("ReflectModel construct init start ".(microtime(true)-$starttime));
        $this->clazz = $clazz;
        $this->table = $table;
        $this->dbid = $dbid;
        $this->properties = [];
        $this->types = [];
        $this->dbtypes = [];
        $this->arraytypes = [];

        $this->initFromClass();
        $this->debuginfo("ReflectModel construct init end ".(microtime(true)-$starttime));
    }

    //*****************************************
    // public methods
    //*****************************************

    /**
     * Quick check without fetching all array properties
     * @param ID value to check $id
     * @return bool, true if existing
     */
    public function checkExisting($id) {
        if (empty($id)) {
            return false;
        }
        $starttime = microtime(true);
        $sql = 'SELECT * FROM ' . $this->table;
        $sql .= " WHERE " . $this->dbid . " = ?";
        $arr = $this->select($sql, [$this->dbtypes[$this->dbid], $id]);
        $this->debuginfo("checkExisting ".(microtime(true)-$starttime));
        return isset($arr) && count($arr) > 0;
    }

    /**
     * 
     * @param Peoperty $prop
     * @param Property value $val
     * @return bool, true if existing
     */
    public function checkExistingWithPropertyValue($prop, $val) {
        if (empty($prop) || empty($val) || empty($this->dbtypes[$prop])) {
            return false;
        }
        $starttime = microtime(true);
        $sql = 'SELECT * FROM ' . $this->table;
        $sql .= " WHERE " . $prop . " = ?";
        $arr = $this->select($sql, [$this->dbtypes[$val], $val]);
        $this->debuginfo("checkExistingWithPropertyValue ".(microtime(true)-$starttime));
        return isset($arr) && count($arr) > 0;
    }

    public function getList($limit) {
        $starttime = microtime(true);
        $sql = 'SELECT ' . implode(",", $this->properties) . " FROM " . $this->table;
        if (!empty($this->sortid)) {
            $sql .= " ORDER BY " . $this->dbid . " ASC ";
        }
        if (!empty($limit)) {
            $sql .= " LIMIT ? ";
        }
        $arr = $this->select($sql, ["i", $limit]);
        $this->addArrays($arr);
        $this->debuginfo("getList ".(microtime(true)-$starttime));
        return $arr;
//        return $this->select("SELECT cid,userid,firstname,lastname,phone,username FROM fakt_users ORDER BY userid ASC LIMIT ?", ["i", $limit]);  
    }

    public function getFromId($id) {
        $starttime = microtime(true);
        $sql = 'SELECT ' . implode(",", $this->properties) . " FROM " . $this->table;
        if (!empty($id)) {
            $sql .= " WHERE " . $this->dbid . " = ?";
        }
        $arr = $this->select($sql, [$this->dbtypes[$this->dbid], $id]);
        $this->addArrays($arr);
        $this->debuginfo("getFromId ".(microtime(true)-$starttime));
        return $arr;
//        return $this->select("SELECT cid,userid,firstname,lastname,phone,username FROM fakt_users WHERE userid = ?", ["s", $userid]);
    }

    public function getSingleValueFromId($id) {
        $res = $this->getFromId($id);
        if (!empty($res)) {
            return $res[0];
        }
        return null;
    }

    public function getFromPropertyValue($prop, $val) {
        return $this->getFromPropertyValueWithAddedWhereParam($prop, $val, null);
    }

    public function getFromPropertyValueWithAddedWhereParam($prop, $val, $sqlpar) {
        if (empty($prop) || empty($val) || !in_array($prop, $this->properties)) {
            throw new Exception("Class " . $this->clazz->getName() . ' unable to find property ' . $prop . "=" . $val);
        }
        $starttime = microtime(true);
        $sql = 'SELECT ' . implode(",", $this->properties) . " FROM " . $this->table;
        $sql .= " WHERE " . $prop . " = ?";
        if (!empty($sqlpar)) {
            $sql .= ' ' . $sqlpar;
        }
        $arr = $this->select($sql, [$this->dbtypes[$prop], $val]);
        $this->addArrays($arr);
        $this->debuginfo("getFromPropertyValueWithAddedWhereParam ".(microtime(true)-$starttime));
        return $arr;
//        return $this->select("SELECT cid,userid,firstname,lastname,phone,username FROM fakt_users WHERE username = ?", ["s", $username]);
    }

    public function getFromPropertySingleValue($prop, $val) {
        $res = $this->getFromPropertyValue($prop, $val);
        if (!empty($res)) {
            return $res[0];
        }
        return null;
    }

    public function storeObject($obj) {
        $existing = $this->checkExisting($obj->{$this->dbid});
        if (empty($existing)) {
            return $this->insertObjectWithCheck($obj, true);
        } else {
            return $this->updateObjectWithCheck($obj, true);
        }
    }

    public function insertObject($obj) {
        return $this->insertObjectWithCheck($obj, false);
    }

    private function insertObjectWithCheck($obj, $donotcheck) {
        $starttime = microtime(true);
        if (!$donotcheck) {
            $existing = $this->checkExisting($obj->{$this->dbid});
            if (!empty($existing)) {
                throw New Exception('Object already exist');
            }
        }
//        if ($this->checkUsernameExistForOtherUser($user->username, $user->userid)) {
//            throw New Exception('Username ' . $user->username . ' already exist');
//        }
        $props = [];
        $dbtypes = [];
        $valpar = [];
        $sqlpar = [];
        $sqlpar[] = '';
        foreach ($this->dbtypes as $key => $value) {
            $props[] = $key;
            $valpar [] = "?";
            $objprop = $obj->{$key};
            if ($objprop instanceof SqlValueIF) {
                $dbtypes[] = $objprop->datatype();
                $sqlpar[] = $objprop->datavalue();
            } else {
                $dbtypes[] = $value;
                $sqlpar[] = $objprop;
            }
        }
        $sqlpar[0] = implode("", $dbtypes);
        $sql = 'INSERT INTO ' . $this->table . ' (' . implode(",", $props) . ") VALUES (" . implode(",", $valpar) . ') ';
        $result = $this->insert($sql, $sqlpar);
        if (!$result) {
            throw New Exception("Unable to insert object.");
        }
        $this->storeArrays($obj);
        $this->debuginfo("insertObjectWithCheck ".(microtime(true)-$starttime));
        return $result;
    }

    public function updateObject($obj) {
        return $this->updateObjectWithCheck($obj, false);
    }

    private function updateObjectWithCheck($obj, $donotcheck) {
        $starttime = microtime(true);
        if (!$donotcheck) {
            $existing = $this->checkExisting($obj->{$this->dbid});
            if (empty($existing)) {
                throw New Exception('Object does not exist');
            }
        }
//        if ($this->checkUsernameExistForOtherUser($user->username, $user->userid)) {
//            throw New Exception('Username ' . $user->username . ' already exist');
//        }
        $props = [];
        $dbtypes = [];
        $sqlpar = [];
        $sqlpar[] = '';
        foreach ($this->dbtypes as $key => $value) {
            if ($key !== $this->dbid) {
                $props[] = $key . " = ?";
                $objprop = $obj->{$key};
                if ($objprop instanceof SqlValueIF) {
                    $dbtypes[] = $objprop->datatype();
                    $sqlpar[] = $objprop->datavalue();
                } else {
                    $dbtypes[] = $value;
                    $sqlpar[] = $objprop;
                }
            }
        }
        $dbtypes[] = $this->dbtypes[$this->dbid];
        $sqlpar[] = $obj->{$this->dbid};
        $sqlpar[0] = implode("", $dbtypes);
        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(", ", $props) . " WHERE " . $this->dbid . ' = ? ';
        $result = $this->update($sql, $sqlpar);
        if (!$result) {
            throw New Exception("Unable to update object.");
        }
        $this->storeArrays($obj);
        $this->debuginfo("updateObjectWithCheck ".(microtime(true)-$starttime));
        return $result;
    }

    public function deleteObject($obj) {
        $starttime = microtime(true);
        $sql = 'DELETE FROM ' . $this->table . " WHERE " . $this->dbid . ' = ? ';
        $result = $this->delete($sql, [$this->dbtypes[$this->dbid], $obj->{$this->dbid}]);
        $this->deleteArrays($obj);
        $this->debuginfo("deleteObject ".(microtime(true)-$starttime));
        return $result;
    }

    //********************************
    // protected methods
    //********************************
    
    protected function deleteObjectWithProperty($prop, $val) {
        if (empty($prop) || empty($val) || empty($this->dbtypes[$prop])) {
            throw New Exception('Unable to delete ' . $prop . ', missing parameters');
        }
        $sql = 'DELETE FROM ' . $this->table . " WHERE " . $prop . ' = ? ';
        // $$$ TODO delete arrays also
        return $this->delete($sql, [$this->dbtypes[$prop], $val]);
    }

        protected function debuginfo(string $str) {
        if(self::$DEBUGINFO) {
            print $str. PHP_EOL;
        }
    }
    

    
    //********************************
    // private methods
    //********************************

    private function deleteArrays($obj) {
        if (empty($this->arraytypes)) {
            return;
        }
        foreach ($this->arraytypes as $key => $value) {
            $this->deleteArray($obj, $key, $value);
        }
    }

    private function deleteArray($obj, $member, $type) {
        if (empty($obj) || empty($member) || empty($type)) {
            return;
        }
        $model = $this->getModelForDatatype($type);
        $model->deleteObjectWithProperty($this->dbid, $obj->{$this->dbid});
    }

    private function storeArrays($obj) {
        if (empty($this->arraytypes)) {
            return;
        }
        foreach ($this->arraytypes as $key => $value) {
            $this->storeArray($obj, $key, $value);
        }
    }

    private function storeArray($obj, $member, $type) {
        if (empty($obj) || empty($member) || empty($type)) {
            return;
        }
        $model = $this->getModelForDatatype($type);
//        var_dump($model);
        $model->deleteObjectWithProperty($this->dbid, $obj->{$this->dbid});
        foreach ($obj->{$member} as $val) {
            $model->insertObjectWithCheck($val, true);
        }
    }

    private function addArrays(&$arr) {
        if (empty($this->arraytypes)) {
            return;
        }
        $starttime = microtime(true);
        foreach ($this->arraytypes as $key => $value) {
            $this->readArray($arr, $key, $value);
        }
        $this->debuginfo("addArrays ".(microtime(true)-$starttime));
    }

    private function readArray(&$arr, $member, $type) {
        if (!isset($arr) || count($arr) <= 0 || empty($member) || empty($type)) {
            return;
        }
        $model = $this->getModelForDatatype($type);
//        var_dump($model);
        for ($x = 0; $x < count($arr); $x++) {
            $arr[$x][$member] = $model->getFromPropertyValue($this->dbid, $arr[$x][$this->dbid]);
        }
    }

    private function getModelForDatatype($type) {
        $className = $type . 'Model';
        require_once PROJECT_ROOT_PATH . '/model/' . $className . '.php';
        return new $className;
    }

    private function initFromClass() {
        $properties = $this->clazz->getProperties();
//      var_dump($properties);
        $this->properties = [];
        $this->types = [];
        $this->dbtypes = [];
        $this->arraytypes = [];

        foreach ($properties as $property) {
            $this->addProperty($property);
        }

        if (empty($this->properties)) {
            throw new Exception("Class " . $this->clazz->getName() . ' does not have any properties');
        }
        $this->validate();
    }

    private function addProperty($property) {
        $reflectionType = $property->getType();
        if ($reflectionType !== null) {
            $typeName = $reflectionType->getName();
            $attrs = $property->getAttributes();
            $this->validateType($typeName);
            if ($typeName === 'array') {
                if (!empty($attrs)) {
                    $instance = $property->getAttributes()[0]->newInstance();
                    if ($instance instanceof ObjectArrayAttribute) {
                        $this->arraytypes[$property->getName()] = $instance->value;
                    }
                }
                if (empty($this->arraytypes[$property->getName()])) {
                    throw new Exception("Array property " . $property->getName() . ' is missing type declaration');
                }
            } else {
                if (!empty($attrs)) {
                    $instance = $property->getAttributes()[0]->newInstance();
                    if ($instance instanceof IgnoreAttribute && $instance->value === true) {
                        return;
                    }
                }
                $this->properties[] = $property->getName();
                $this->types[$property->getName()] = $typeName;
                $this->dbtypes[$property->getName()] = $this->type2dbtype($typeName);
            }
        } else {
            throw new Exception("Property " . $property->getName() . ' is missing type declaration');
        }
    }

    private function validate() {
        if (empty($this->table)) {
            throw new Exception("Class " . $this->clazz->getName() . ' missing table');
        }
        if (empty($this->dbid)) {
            throw new Exception("Class " . $this->clazz->getName() . ' missing table id value');
        }
        if (empty($this->properties)) {
            throw new Exception("Class " . $this->clazz->getName() . ' does not have any properties');
        }
        if (empty($this->types)) {
            throw new Exception("Class " . $this->clazz->getName() . ' does not have any datatypes');
        }
        if (empty($this->dbtypes)) {
            throw new Exception("Class " . $this->clazz->getName() . ' does not have any database datatypes');
        }
    }

    private function validateType($typeName) {
        switch ($typeName) {
            case "string":
            case "float":
            case "int":
            case "array":
                break;
            // blob not supported yet
            default:
            //throw new Exception("Class " . $this->clazz->getName() . ' type ' . $typeName . ' not supported');
        }
    }

    private function type2dbtype($typeName) {
        switch ($typeName) {
            case "string":
                return "s";
            case "float":
                return "d";
            case "int":
                return "i";
            case "array":
                return "";
            // blob not supported yet
            default:
//                print $typeName . '=s' . PHP_EOL;
                return "x"; // enums etc.
            //throw new Exception("Class " . $this->clazz->getName() . ' type ' . $typeName . ' not supported');
        }
    }
}
