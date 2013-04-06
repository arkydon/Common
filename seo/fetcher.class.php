<?php
    class Fetcher {
        public static function fetch($list, $settings = array()) {
            if(is_string($list)) $list = array($list);
            if(!is_array($list)) $list = array();
            $list = array_values(array_filter($list));
            $tt = count($list);
            if(!$tt) {
                trigger_error('LIST_IS_EMPTY', E_USER_WARNING);
                return array();
            }
            $START = microtime(true);
            @ $settings['notfull'] = $settings['notfull'] ? : false;
            @ $settings['wait'] = $settings['wait'] ? : 1;
            @ $settings['loop'] = $settings['loop'] ? : 10;
            @ $settings['debug'] = $settings['debug'] ? : false;
            @ $settings['timeout'] = $settings['timeout'] ? : 10;
            @ $settings['needinfo'] = $settings['needinfo'] ? : false;
            @ $settings['threads'] = $settings['threads'] ? : 20;
            @ $settings['noproxy'] = $settings['noproxy'] ? : false;
            @ $settings['checker'] = is_callable($settings['checker']) ? $settings['checker'] : null;
            @ $settings['get_url'] = is_callable($settings['get_url']) ? $settings['get_url'] : null;
            @ $settings['get_proxy'] = is_callable($settings['get_proxy']) ? $settings['get_proxy'] : 'getProxy';
            @ $settings['get_ua'] = is_callable($settings['get_ua']) ? $settings['get_ua'] : 'getUserAgent';
            @ $settings['get_cookie'] = is_callable($settings['get_cookie']) ? $settings['get_cookie'] : null;
            $RETURN = array();
            for($LOOP = $settings['loop'], $loop = 1; count($list) and $loop <= $LOOP; $loop++) {
                $list = self::go($list, $settings);
                $new = array();
                foreach($list as $value)
                    if(isset($value['content']) and (!$settings['checker'] or call_user_func($settings['checker'], $value['content'])))
                        $RETURN[] = $value;
                    else $new[] = $value['elem'];
                $list = $new;
                if(count($list) and $loop <= $LOOP) sleep($settings['wait']);
            }
            trigger_error(sprintf(
                '(%s) - Efficiency (%s, %s, %s, %s, %s) = %s out of %s ~ %s%%',
                    round(microtime(true) - $START),
                    "timeout = {$settings['timeout']}",
                    "threads = {$settings['threads']}",
                    "LOOP = {$LOOP}",
                    "loop = " . ($loop - 1),
                    "wait = {$settings['wait']}",
                    count($RETURN), $tt,
                    round(100 * count($RETURN) / $tt)
            ), E_USER_WARNING);
            return $RETURN;
        }
        private static function go($list, $settings) {
            if(!is_array($list)) $list = array();
            $list = array_values(array_filter($list));
            $tt = count($list);
            if(!$tt) {
                trigger_error('LIST_IS_EMPTY', E_USER_WARNING);
                return array();
            }
            $list = array_values(array_filter(array_map(function($elem) use($settings) {
                $ch = curl_init();
                $cookie = $settings['get_cookie'] ? call_user_func($settings['get_cookie'], $elem) : null;
                $url = $settings['get_url'] ? call_user_func($settings['get_url'], $elem) : $elem;
                $ua = call_user_func($settings['get_ua'], $elem);
                $proxy = call_user_func($settings['get_proxy'], $elem);
                if(!$url or !is_string($url) or !host($url) or !$ua or (!$settings['noproxy'] and !$proxy)) {
                    trigger_error('RETURN NULL', E_USER_NOTICE);
                    return null;
                }
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_AUTOREFERER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $settings['timeout']);
                curl_setopt($ch, CURLOPT_TIMEOUT, $settings['timeout']);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_USERAGENT, $ua);
                if($proxy) {
                    $proxy = parse_url($proxy);
                    if($proxy['scheme'] === 'proxy' and $proxy['host'] and $proxy['port']) {
                        @ $user = $proxy['user'] ? : '';
                        @ $pass = $proxy['pass'] ? : '';
                        if($user and $pass) $user .= ':' . $pass;
                        $proxy = "{$proxy['host']}:{$proxy['port']}";
                    } else {
                        trigger_error('RETURN NULL', E_USER_NOTICE);
                        return null;
                    }
                    curl_setopt($ch, CURLOPT_PROXY, $proxy);
                    // curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                    if($user) {
                        curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
                        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $user);
                    }
                }
                if($cookie) curl_setopt($ch, CURLOPT_COOKIE, $cookie);
                return array('elem' => $elem, 'ch' => $ch);
            }, $list)));
            if(!count($list)) {
                trigger_error('LIST_AFTER_IS_EMPTY', E_USER_WARNING);
                return array();
            }
            $mh = array();
            for($i = 0; $i < ceil(count($list) / $settings['threads']); $i++) $mh[$i] = curl_multi_init();
            for($i = 0; $i < count($list); $i++) curl_multi_add_handle($mh[$i / $settings['threads']], $list[$i]['ch']);
            for($i = 0; $i < count($mh); $i++) {
                $running = null;
                do {
                    curl_multi_exec($mh[$i], $running);
                    usleep(400000);
                } while($running > 0);
                curl_multi_close($mh[$i]);
            }
            return array_map(function($elem) use($settings) {
                $ch = $elem['ch'];
                if(!is_resource($ch)) return null;
                $info = curl_getinfo($ch);
                $err = curl_error($ch);
                $code = $info['http_code'];
                $last = $info['url'];
                $content = curl_multi_getcontent($ch);
                if(is_null($content)) $content = '';
                if((!$settings['notfull'] and $err) or !in_array($code, array(200, 400, 401, 402, 403, 404, 405, 500, 501, 502, 503)))
                    if($settings['debug'])
                        trigger_error(implode(chr(10), array_filter(array($err, "code = {$code}", "last = {$last}"))), E_USER_NOTICE);
                    else ;
                else {
                    if($settings['needinfo']) $elem['info'] = $info;
                    $elem['content'] = $content;
                }
                unset($elem['ch']);
                return $elem;
            }, $list);
        }
    }
