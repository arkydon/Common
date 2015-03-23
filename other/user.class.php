<?php
    defined('USER_SESSION_TIMEOUT') or trigger_error('USER_SESSION_TIMEOUT is not defined!', E_USER_ERROR);
    defined('USER_STAT') or trigger_error('USER_STAT is not defined!', E_USER_ERROR);
    class User {
        private static $me = null;
        private static $hash = null;
        private static $prefix = null;
        public static function init() {
            self::$prefix = HOST . ':hash:';
            @ self::$hash = $_COOKIE['hash'];
            if(self::$hash and R::EXISTS(self::$prefix . self::$hash)) {
                $pipeline = R::pipeline();
                $pipeline -> HSET(self::$prefix . self::$hash, 'LAST', now());
                $pipeline -> EXPIRE(self::$prefix . self::$hash, USER_SESSION_TIMEOUT);
                $pipeline -> execute();
                self::setCookie();
                self::$me = R::toHash(R::HGETALL(self::$prefix . self::$hash));
            } else self::login();
            if(USER_STAT) {
                $IP = IP();
                $STAMP = curdate();
                $HOST = HOST;
                $HASH = self::$hash;
                $METHOD = (IS_AJAX ? 'AJAX' : (IS_POST ? 'POST' : 'GET'));
                R::RPUSH("{$HOST}:stat:{$STAMP}:{$IP}:{$HASH}", implode(chr(10), array(
                    now(), $METHOD, REF(), (SITE . URI())
                )));
            }
        }
        public static function login($me = array()) {
            $me['IP'] = IP();
            $me['FIRST'] = $me['LAST'] = now();
            self::$me = $me;
            if(!self::$hash) self::$hash = md5(microtime(true) . SECRET);
            self::setCookie();
            $pipeline = R::pipeline() -> DEL(self::$prefix . self::$hash) -> MULTI();
            foreach(self::$me as $k => $v)
                $pipeline -> HSET(self::$prefix . self::$hash, $k, $v);
            $pipeline -> EXPIRE(self::$prefix . self::$hash, USER_SESSION_TIMEOUT);
            $pipeline -> EXEC() -> execute();
        }
        public static function logout() {
            self::unsetCookie();
            if(!self::$hash) return null;
            R::DEL(self::$prefix . self::$hash);
            self::$hash = null;
        }
        public static function me($key = null, $setter = null) {
            if(is_null($key)) return self::$me;
            if(is_null($setter)) return self::$me[$key];
            if(!self::$hash) return null;
            if(is_string($setter)) {
                R::HSET(self::$prefix . self::$hash, $key, $setter);
                self::$me[$key] = $setter;
            } else trigger_error('INVALID $setter', E_USER_WARNING);
        }
        public static function checkGroup($group) {
            if(is_string($group)) $group = explode(',', $group);
            return in_array(self::me('group'), $group);
        }
        private static function setCookie() {
            setcookie('hash', self::$hash, time() + USER_SESSION_TIMEOUT, SITE_PATH . '/', HOST, IS_SECURE, true);
        }
        private static function unsetCookie() {
            setcookie('hash', '', time() - USER_SESSION_TIMEOUT, SITE_PATH . '/', HOST, IS_SECURE, true);
        }
        public static function saveCSV() {
            $HOST = HOST;
            $file = OTHER_ROOT . '/stat.csv';
            $csv = array();
            $csv[] = '"stamp";"ip";"sess";"ref";"uri"';
            foreach(R::KEYS($s = "{$HOST}:stat:*") as $key) {
                $k = substr($key, strlen($s) - 1);
                list($stamp, $ip, $sess) = explode(':', $k);
                foreach(R::LRANGE($key, 0, -1) as $list) {
                    list($time, $method, $ref, $uri) = explode(chr(10), $list);
                    $ref = trim($ref);
                    $uri = trim($uri);
                    $csv[] = "\"{$stamp}\";\"{$ip}\";\"{$sess}\";\"{$ref}\";\"{$uri}\"";
                }
            }
            file_put_contents($file, implode(chr(10), $csv));
        }
    }
