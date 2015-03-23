<?php
    /* FORKED FROM - https://github.com/jdp/redisent/blob/master/src/redisent/Redis.php */
    class R {
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
            $rc = new ReflectionClass('RC');
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
    class RС {
        private $__sock;
        private $queue = array();
        private $pipelined = false;
        public function __construct($dsn) { // redis://localhost:6379
            $dsn = parse_url($dsn);
            @ $scheme = $dsn['scheme'];
            @ $host = $dsn['host'];
            @ $port = $dsn['port'];
            @ $pass = $dsn['pass'];
            if($scheme != 'redis' or !$host or !$port)
                trigger_error('INVALID CONNECTION', E_USER_ERROR);
            @ $this -> __sock = fsockopen($host, $port, $errno, $errstr);
            if($this -> __sock === false)
                trigger_error('CONNECTION FAILED - ' . trim($errstr), E_USER_ERROR);
            if($pass) $this -> AUTH($pass);
        }
        public function toHash($mb) {
            if(!$mb) return array();
            $keys = $mb;
            $values = $mb;
            foreach(array_keys($mb) as $key)
                if($key & 1) unset($keys[$key]);
                else unset($values[$key]);
            return array_combine($keys, $values);
        }
        public function __destruct() {
            fclose($this -> __sock);
        }
        function pipeline() {
            $this -> pipelined = true;
            return $this;
        }
        public function __call($name, $args) {
            /* Build the Redis unified protocol command */
            $CRLF = chr(13) . chr(10);
            array_unshift($args, $name);
            $this -> queue[] = sprintf('*%d%s%s%s', count($args), $CRLF, implode(
                array_map(function($arg) use($CRLF) {
                    return sprintf('$%d%s%s', strlen($arg), $CRLF, $arg);
                }, $args), $CRLF
            ), $CRLF);
            if($this -> pipelined) return $this;
            return $this -> execute();
        }
        function execute() {
            /* Open a Redis connection and execute the queued commands */
            foreach($this -> queue as $queue) {
                for($written = 0; $written < strlen($queue); $written += $fwrite) {
                    $fwrite = fwrite($this -> __sock, substr($queue, $written));
                    if($fwrite === false or $fwrite <= 0)
                        trigger_error('WRITE ERROR', E_USER_ERROR);
                }
            }
            // Read in the results from the pipelined commands
            $responses = array();
            for($i = 0; $i < count($this -> queue); $i++) {
                $responses[] = $this -> response();
            }
            // Clear the queue and return the response
            $this -> queue = array();
            if($this -> pipelined) {
                $this -> pipelined = false;
                return $responses;
            }
            return $responses[0];
        }
        private function response() {
            /* Parse the response based on the reply identifier */
            $reply = trim(fgets($this -> __sock, 512));
            switch(substr($reply, 0, 1)) {
                /* Error reply */
                case '-':
                    trigger_error('ERROR (' . trim($reply) . ')', E_USER_ERROR);
                /* Inline reply */
                case '+':
                    $response = substr(trim($reply), 1);
                    if($response === 'OK') $response = true;
                    break;
                /* Bulk reply */
                case '$':
                    $response = null;
                    if($reply === '$-1') break;
                    $read = 0;
                    $size = intval(substr($reply, 1));
                    if($size > 0) {
                        do {
                            $block_size = ($size - $read) > 1024 ? 1024 : ($size - $read);
                            $r = fread($this -> __sock, $block_size);
                            if($r === false)
                                trigger_error('READ FROM SERVER ERROR', E_USER_ERROR);
                            else {
                                $read += strlen($r);
                                $response .= $r;
                            }
                        } while($read < $size);
                    }
                    fread($this -> __sock, 2); /* CRLF */
                    break;
                /* Multi-bulk reply */
                case '*':
                    $count = intval(substr($reply, 1));
                    if($count === -1) return null;
                    $response = array();
                    for($i = 0; $i < $count; $i++)
                        $response[] = $this -> response();
                    break;
                /* Integer reply */
                case ':':
                    $response = intval(substr(trim($reply), 1));
                    break;
                default:
                    trigger_error('UNKNOWN RESPONSE ERROR', E_USER_ERROR);
            }
            return $response;
        }
    }
