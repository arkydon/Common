<?php
    if(!defined($d = 'DB_LOG_QUERY')) define($d, false);
    if(!defined($d = 'DB_CONNECT_TIMEOUT')) define($d, 600); // 10 minutes
    if(!defined($d = 'DB_NC_TABLE')) define($d, "%s");
    if(!defined($d = 'DB_NC_ID')) define($d, "%s_id");
    class DB {
        private static $dbh = array();
        private static $current = null;
        public static function init($dbh) {
            if(!is_array($dbh) or !count($dbh)) trigger_error('INVALID ARGUMENT', E_USER_ERROR);
            self::$dbh = $dbh;
        }
        public static function getConnection($current = null) {
            $current = $current ? $current : self::$current;
            if(!$current) trigger_error('INVALID CURRENT CONNECTION', E_USER_ERROR);
            if(is_object(self::$dbh[$current])) return self::$dbh[$current];
            $rc = new ReflectionClass('DBC');
            self::$dbh[$current] = $rc -> newInstanceArgs(self::$dbh[$current]);
            if(is_object(self::$dbh[$current])) return self::$dbh[$current];
            trigger_error('CONNECTION FAILED', E_USER_ERROR);
        }
        public static function setCurrent($current) { self::$current = $current; }
        public static function getCurrent() { return self::$current; }
        public static function __callStatic($name, $args) {
            return call_user_func_array(array(self::getConnection(), $name), $args);
        }
    }
    class DBC {
        private $pdo = null;
        private $sth = null;
        public $type = null;
        private $args = null;
        private $listing = null;
        private $logQuery = false;
        private $lastQuery = null;
        public function __construct() {
            $args = func_get_args();
            list($this -> type) = explode(':', $args[0], 2);
            $this -> type = strtolower($this -> type);
            $this -> logQuery = DB_LOG_QUERY;
            $this -> args = $args;
        }
        private function connect() {
            // 'mysql:host=localhost;dbname=db', 'login', 'passwd'
            // 'sqlite:sqlite.db'
            try {
                $rc = new ReflectionClass('PDO');
                $this -> pdo = $rc -> newInstanceArgs($this -> args);
                $this -> pdo -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this -> pdo -> setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                $this -> pdo -> setAttribute(PDO::ATTR_PERSISTENT, false);
            } catch(Exception $e) {
                trigger_error($e -> getMessage(), E_USER_ERROR);
            }
        }
        public function enableLogQuery() { $this -> logQuery = true; }
        public function disableLogQuery() { $this -> logQuery = false; }
        private function normalizeArgs() {
            $args = func_get_args();
            $args = $args[0];
            $checker = function($args) {
                return count($args) === count(array_filter($args, function($_) {
                    return (is_numeric($_) or is_string($_) or is_null($_));
                }));
            };
            if(count($args) === 1 and is_array($args[0]) and $checker($args[0]))
                return $args[0];
            if($checker($args))
                return $args;
            trigger_error("INVALID PLACEHOLDER PART OF ARGUMENTS", E_USER_ERROR);
        }
        public function q() {
            //
            $args = func_get_args();
            // print_r($args);
            if(!count($args)) trigger_error("NO ARGUMENTS", E_USER_ERROR);
            @ $firstArg = array_shift($args);
            @ $secondArg = array_shift($args);
            @ $nc = $this -> getNamingConvention($secondArg);
            @ $TABLE = $nc['TABLE'];
            @ $ID = $nc['ID'];
            //
            $possibleDirect = array('all', 'listing', 'row', 'col', 'val');
            $possibleConstructor = array('all', 'listing', 'row', 'col', 'val', 'update', 'delete', 'insert', 'replace');
            //
            if(in_array($firstArg, $possibleDirect) and !$this -> isTable($TABLE))
                return $this -> SQL($TABLE, $this -> normalizeArgs($args));
            if(in_array($firstArg, $possibleConstructor) and $this -> isTable($TABLE)) {
                //
                // BEGIN CONSTRUCTOR
                //
                if(in_array($firstArg, $possibleDirect)) {
                    // print_r($args);
                    if($firstArg === 'listing')
                        @ $append = array_shift($args);
                    else $append = null;
                    @ $fields = array_shift($args);
                    @ $where = array_shift($args);
                    @ $params = $this -> normalizeArgs($args);
                    list($where, $params) = $this -> constructWhereAndParams($where, $params, $ID);
                    if(is_array($fields)) $fields = implode(', ', $fields);
                    $fields = $fields ? (($firstArg != 'col' and $firstArg != 'val') ? "{$ID}, {$fields}" : "{$fields}, {$ID}") : '*';
                    // echo "fields: ", $fields, chr(10);
                    $where = $where ? "WHERE {$where}" : '';
                    if($append and stripos($append, 'limit') === 0)
                        $where = "{$where} {$append}";
                    elseif($append and $where) $where = "({$where}) AND $append";
                    elseif($append) $where = "WHERE {$append}";
                    $pattern = array(
                        'sqlite' => "SELECT %s FROM `%s` %s",
                        'mysql' => "SELECT %s FROM `%s` %s"
                    );
                    $pattern = $pattern[$this -> type];
                    return $this -> SQL(sprintf($pattern, $fields, $TABLE, $where), $params);
                }
                if($firstArg === 'delete') {
                    @ $where = array_shift($args);
                    @ $params = $this -> normalizeArgs($args);
                    list($where, $params) = $this -> constructWhereAndParams($where, $params, $ID);
                    $where = $where ? "WHERE {$where}" : '';
                    $pattern = array(
                        'sqlite' => "DELETE FROM `%s` %s",
                        'mysql' => "DELETE FROM `%s` %s"
                    );
                    $pattern = $pattern[$this -> type];
                    return $this -> SQL(sprintf($pattern, $TABLE, $where), $params);
                } 
                if($firstArg === 'update') {
                    @ $import = array_shift($args);
                    @ $where = array_shift($args);
                    @ $params = $this -> normalizeArgs($args);
                    if(isset($import[$ID]) and ($where or $params))
                        trigger_error('UPDATE: YOU SHOULD NOT USE ID IN IMPORT WITH WHERE CONDITION', E_USER_ERROR);
                    if(isset($import[$ID])) $where = array($import[$ID]);
                    unset($import[$ID]);
                    list($import, $params0) = $this -> constructImport($import, true);
                    list($where, $params1) = $this -> constructWhereAndParams($where, $params, $ID);
                    $params = array_merge(array_values($params0), array_values($params1));
                    $where = $where ? "WHERE {$where}" : '';
                    $pattern = array(
                        'sqlite' => 'UPDATE OR IGNORE `%s` SET %s %s',
                        'mysql' => 'UPDATE IGNORE `%s` SET %s %s'
                    );
                    $pattern = $pattern[$this -> type];
                    return $this -> SQL(sprintf($pattern, $TABLE, $import, $where), $params);
                } 
                if($firstArg === 'insert' or $firstArg === 'replace') {
                    @ $import = array_shift($args);
                    if(isset($import[$ID]) and $firstArg === 'insert')
                        trigger_error('UPDATE: YOU SHOULD NOT USE ID IN INSERT', E_USER_ERROR);
                    if($firstArg === 'insert') unset($import[$ID]);
                    list($import, $params) = $this -> constructImport($import, false);
                    $pattern = array(
                        'insert' => array(
                            'sqlite' => 'INSERT OR IGNORE INTO `%s` (%s) VALUES (%s)',
                            'mysql' => 'INSERT IGNORE INTO `%s` (%s) VALUES (%s)'
                        ),
                        'replace' => array(
                            'sqlite' => 'REPLACE INTO `%s` (%s) VALUES (%s)',
                            'mysql' => 'REPLACE INTO `%s` (%s) VALUES (%s)'
                        )
                    );
                    $pattern = $pattern[$firstArg][$this -> type];
                    return $this -> SQL(sprintf($pattern, $TABLE, $import, implode(', ', array_fill(0, count($params), '?'))), $params);
                }
                trigger_error("UNHANDLED CONSTRUCTOR TYPE", E_USER_ERROR);
                //
                // END CONSTRUCTOR
                //
            }
            if(in_array($firstArg, $possibleConstructor) and !$this -> isTable($TABLE))
                trigger_error("SECOND ARGUMENT SHOULD BE TABLE", E_USER_ERROR);
            //
            // DEFAULT BEHAVIOR
            //
            $args = func_get_args();
            $q = array_shift($args);
            $params = $this -> normalizeArgs($args);
            return $this -> SQL($q, $params);
        }
        private function SQL($q, $args) {
            if($this -> lastQuery < time() - DB_CONNECT_TIMEOUT) $this -> connect();
            $this -> lastQuery = time();
            try {
                if(!is_array($args)) {
                    $this -> log($q);
                    trigger_error('INVALID ARGS', E_USER_ERROR);
                }
                $sth = $this -> pdo -> prepare($q);
                if($this -> logQuery) $this -> log($q);
                if(is_assoc($args)) {
                    $arr = array();
                    preg_match_all('/:([a-zA-Z0-9_]+)/', $q, $match);
                    foreach($match[1] as $key)
                        @ $arr[':' . $key] = $args[$key];
                    $sth -> execute($arr);
                } else $sth -> execute($args);
                $this -> sth = $sth;
                return $this -> sth;
            } catch(Exception $e) {
                $this -> log($q);
                trigger_error($e -> getMessage(), E_USER_ERROR);
            }
        }
        private function isTable($table) {
            return preg_match('~^\w+$~', $table);
        }
        private function log($q) {
            $q = implode(chr(10), nsplit($q));
            trigger_error($q, E_USER_NOTICE);
        }
        public function delete() {
            $args = func_get_args();
            array_unshift($args, 'delete');
            $sth = call_user_func_array(array($this, 'q'), $args);
            return $this -> n();
        }
        public function update() {
            $args = func_get_args();
            array_unshift($args, 'update');
            $sth = call_user_func_array(array($this, 'q'), $args);
            return $this -> n();
        }
        public function insert() {
            $args = func_get_args();
            array_unshift($args, 'insert');
            $sth = call_user_func_array(array($this, 'q'), $args);
            if($this -> n()) return $this -> id();
            return null;
        }
        public function replace() {
            $args = func_get_args();
            array_unshift($args, 'replace');
            $sth = call_user_func_array(array($this, 'q'), $args);
            return $this -> id();
        }
        public function all() {
            $args = func_get_args();
            array_unshift($args, 'all');
            $sth = call_user_func_array(array($this, 'q'), $args);
            $all = $sth -> fetchAll(PDO::FETCH_ASSOC);
            return $all ? $all : array();
        }
        public function row() {
            $args = func_get_args();
            array_unshift($args, 'row');
            $sth = call_user_func_array(array($this, 'q'), $args);
            $row = $sth -> fetch(PDO::FETCH_ASSOC);
            return $row ? $row : array();
        }
        public function col() {
            $args = func_get_args();
            array_unshift($args, 'col');
            $sth = call_user_func_array(array($this, 'q'), $args);
            $col = $sth -> fetchAll(PDO::FETCH_COLUMN, 0);
            return $col ? $col : array();
        }
        public function val() {
            $args = func_get_args();
            array_unshift($args, 'val');
            $sth = call_user_func_array(array($this, 'q'), $args);
            $row = $sth -> fetch(PDO::FETCH_NUM);
            if(!$row) return null;
            return $row[0];
        }
        public function id() {
            return $this -> pdo -> lastInsertId();
        }
        public function n() {
            return $this -> sth -> rowCount();
        }
        private function constructImport($import, $append) {
            $params = array();
            $collector = array();
            $append = $append ? ' = ?' : '';
            foreach($import as $k => $v) {
                $collector[] = "`{$k}`{$append}";
                $params[] = $v;
            }
            return array(implode(', ', $collector), $params);
        }
        private function constructWhereAndParams($where, $extra, $ID) {
            if(!$where) $where = '';
            if(!$extra) $extra = '';
            if(is_numeric($where)) $where = array($where . '');
            if(is_string($where)) return array($where, $extra ? $extra : array());
            if(!is_string($extra) and is_array($extra) and is_string($extra[0]))
                $extra = $extra[0];
            if(!is_string($extra) and $extra)
                trigger_error('INVALID ARGS IN constructWhereAndParams()', E_USER_ERROR);
            if($where and is_assoc($where)) {
                $ph = array();
                $values = array();
                foreach($where as $key => $value) {
                    $negative = false;
                    if($key[0] === '!') {
                        $key = substr($key, 1);
                        $negative = true;
                    }
                    if(is_array($value)) {
                        $temp = implode(', ', array_fill(0, count($value), '?'));
                        if($negative)
                            $values[] = "`{$key}` NOT IN ({$temp})";
                        else $values[] = "`{$key}` IN ({$temp})";
                        foreach($value as $v) $ph[] = $v;
                    } elseif(!is_null($value)) {
                        $ph[] = $value;
                        if($negative)
                            $values[] = "`{$key}` != ?";
                        else $values[] = "`{$key}` = ?";
                    } elseif($negative)
                        $values[] = "`{$key}` IS NOT NULL";
                    else $values[] = "`{$key}` IS NULL";
                }
                $where = implode(' AND ', $values);
                $where = "({$where})";
                if($extra) $where .= " {$extra}";
                return array($where, $ph);
            } elseif(is_array($where)) {
                $pk = $ID;
                $where = implode(', ', array_filter($where, 'is_numeric'));
                if(!$where) $where = 'NULL';
                $where = "(`{$pk}` IN ({$where}))";
                if($extra) $where .= " {$extra}";
                return array($where, array());
            } else trigger_error('INVALID ARGS IN constructWhereAndParams()', E_USER_ERROR);
        }
        public function setListing($listing) {
            $this -> listing = $listing;
        }
        public function listing() {
            $listing = $this -> listing;
            $args = func_get_args();
            if(!count($args) or !$this -> isTable($args[0]))
                trigger_error("INVALID ARGS WHILE LISTING", E_USER_ERROR);
            return new DBListing($this, $listing, $args);
        }
        public function getNamingConvention($type) {
            return array(
                'TABLE' => sprintf(DB_NC_TABLE, $type),
                'ID' => sprintf(DB_NC_ID, $type)
            );
        }
    }
    class DBListing implements Iterator, Countable {
        private $all = array();
        private $args = array();
        private $listing = array();
        private $position = 0;
        private $key = 0;
        private $db = null;
        public function __construct($db, $listing, $args) {
            $this -> key = 0;
            $this -> db = $db;
            $this -> all = array();
            $this -> position = 0;
            $this -> args = $args;
            $this -> listing = $listing;
            $this -> listing['start'] = 1;
        }
        private function getAll() {
            if(!$this -> all) {
                // запихнуть вторым параметром наше условие
                $args = $this -> args;
                $type = $args[0];
                $nc = $this -> db -> getNamingConvention($type);
                $ID = $nc['ID'];
                if($this -> listing['type'] === 'byid') {
                    $start = intval($this -> listing['start']);
                    $limit = intval($this -> listing['limit']);
                    array_unshift($args, "(`{$ID}` >= {$start}) ORDER BY `{$ID}` ASC LIMIT {$limit}");
                } elseif($this -> listing['type'] === 'page') {
                    $start = intval($this -> listing['start']);
                    $limit = intval($this -> listing['limit']);
                    $limit = ($start - 1) * $limit . ', ' . $limit;
                    array_unshift($args, "LIMIT {$limit}");
                } else trigger_error("ERROR - WHILE DEFINING CONFIG FOR LISTING", E_USER_ERROR);
                list($args[0], $args[1]) = array($args[1], $args[0]);
                array_unshift($args, 'listing');
                // print_r($args);
                $sth = call_user_func_array(array($this -> db, 'q'), $args);
                $all = $sth -> fetchAll(PDO::FETCH_ASSOC);
                $this -> all = ($all ? $all : array());
                if($this -> all and $this -> listing['type'] === 'byid') {
                    $last = count($this -> all) - 1;
                    $this -> listing['start'] = $this -> all[$last][$ID] + 1;
                } elseif($this -> all and $this -> listing['type'] === 'page') {
                    $this -> listing['start'] += 1;
                }
                $this -> position = 0;
            }
            return $this -> all;
        }
        public function rewind() {
            $this -> key = 0;
            $this -> position = 0;
            $this -> all = array();
        }
        public function current() {
            $all = $this -> getAll();
            return $all[$this -> position];
        }
        public function key() {
            return $this -> key;
        }
        public function next() {
            $this -> key += 1;
            $this -> position += 1;
        }
        public function valid() {
            $all = $this -> getAll();
            if(!isset($all[$this -> position])) {
                $this -> all = array();
                $all = $this -> getAll();   
            }
            return isset($all[$this -> position]);
        }
        public function count() {
            // запихнуть вместо $fields наш "COUNT(*)"
            $args = $this -> args;
            if(count($args) > 1)
                $args[1] = "COUNT(*)";
            else $args[] = "COUNT(*)";
            array_unshift($args, 'val');
            $sth = call_user_func_array(array($this -> db, 'q'), $args);
            $row = $sth -> fetch(PDO::FETCH_NUM);
            if(!$row) return null;
            return $row[0];
        }
    }
