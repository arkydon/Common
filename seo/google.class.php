<?php
    class Google {
        public static function getPR($list, $settings = array()) {
            if(is_string($list)) $list = array($list);
            if(!is_array($list)) $list = array();
            $list = array_values(array_filter($list));
            if(!count($list)) {
                trigger_error('LIST_IS_EMPTY', E_USER_WARNING);
                return array();
            }
            @ $get_url = is_callable($settings['get_url']) ? $settings['get_url'] : null;
            $settings['get_url'] = function($elem) use($get_url) {
                if($get_url) $elem = call_user_func($get_url, $elem);
                return Google::getQueryForPR($elem);
            };
            $settings['checker'] = function($content) {
                return is_numeric(Google::parsePR($content));
            };
            $list = Fetcher::fetch($list, $settings);
            return array_map(function($elem) {
                $elem['pr'] = Google::parsePR($elem['content']);
                unset($elem['content']);
                return $elem;
            }, $list);
        }
        public static function parsePR($content) {
            if(!$content) return -1;
            $regex = '/rank_[0-9]+:[0-9]+:([0-9]+)/i';
            $match = null;
            if(preg_match($regex, $content, $match)) return intval($match[1]);
            return null;
        }
        public static function getQueryForPR($url) {
            $link = 'http://toolbarqueries.google.com/tbr?client=navclient-auto&hl=en&ch=';
            $link .= self::checkHash(self::hashURL($url));
            $link .= '&ie=UTF-8&oe=UTF-8';
            $link .= '&features=Rank&q=info:' . urlencode($url);
            return $link;
        }
        public static function getGoogleSERP($list, $settings = array()) {
            if(is_string($list)) $list = array($list);
            if(!is_array($list)) $list = array();
            $list = array_values(array_filter($list));
            if(!count($list)) {
                trigger_error('LIST_IS_EMPTY', E_USER_WARNING);
                return array();
            }
            @ $get_url = is_callable($settings['get_url']) ? $settings['get_url'] : null;
            $settings['get_url'] = function($elem) use($get_url) {
                if($get_url) $elem = call_user_func($get_url, $elem);
                return Google::getQueryForGoogleSERP($elem);
            };
            $settings['checker'] = function($content) {
                return is_array(Google::parseGoogleSERP($content));
            };
            $list = Fetcher::fetch($list, $settings);
            return array_map(function($elem) {
                $serp = Google::parseGoogleSERP($elem['content']);
                $elem['serp'] = $serp['serp'];
                unset($elem['content']);
                return $elem;
            }, $list);
        }
        public static function fetchGoogleSERP($list, $settings = array()) {
            $list = Google::getGoogleSERP($list, $settings);
            $urls = array();
            foreach($list as $elem)
                foreach($elem['serp'] as $url)
                    $urls[] = array('elem' => $elem['elem'], 'url' => $url);
            $settings['get_url'] = index('url');
            $list = Fetcher::fetch($urls, $settings);
            return array_map(function($elem) {
                return array(
                    'elem' => $elem['elem']['elem'],
                    'url' => $elem['elem']['url'],
                    'content' => $elem['content']
                );
            }, $list);
        }
        public static function getGoogleIndex($list, $settings = array()) {
            if(is_string($list)) $list = array($list);
            if(!is_array($list)) $list = array();
            $list = array_values(array_filter($list));
            if(!count($list)) {
                trigger_error('LIST_IS_EMPTY', E_USER_WARNING);
                return array();
            }
            @ $get_url = is_callable($settings['get_url']) ? $settings['get_url'] : null;
            $settings['get_url'] = function($elem) use($get_url) {
                if($get_url) $elem = call_user_func($get_url, $elem);
                return Google::getQueryForGoogleIndex($elem);
            };
            $settings['checker'] = function($content) {
                return is_array(Google::parseGoogleSERP($content));
            };
            $list = Fetcher::fetch($list, $settings);
            return array_map(function($elem) {
                $serp = Google::parseGoogleSERP($elem['content']);
                $elem['gi'] = $serp['gi'];
                unset($elem['content']);
                return $elem;
            }, $list);
        }
        public static function parseGoogleSERP($content) {
            if(preg_match('/permission\s+to\s+get\s+url/i', $content)) return null;
            if(preg_match('/unusual\s+traffic\s+from\s+your\s+computer/i', $content)) return null;
            if(preg_match('/no\s+results\s+found\s+for/i', $content)) return array('serp' => array(), 'gi' => 0);
            if(preg_match('/did\s+not\s+match\s+any\s+documents/i', $content)) return array('serp' => array(), 'gi' => 0);
            $serp = null;
            if(preg_match_all('/<h3\s+class="r">\s*<a\s+[^>]*href="(.*?)"/i', $content, $serp)) ;
            elseif(preg_match_all('!<a[^>]*?href="/url[?]q[=](.*?)([&]amp[;]|"|\')!i', $content, $serp)) ;
            elseif(preg_match_all('!<a[^>]*?class=.?p.?[^>]*?href=.?(.*?)("|\')!i', $content, $serp)) ;
            if(!isset($serp[1])) return null;
            if(!is_array($serp[1])) return null;
            $serp = $serp[1];
            $serp = array_map(function($m) {
                if(preg_match('|^/url\?q=http|i', $m) and is_numeric(strpos($m, '&amp;sa=U')))
                    $m = preg_replace('|^/url\?q=(http.*?)&amp;sa=U.*|i', '$1', $m);
                if(!preg_match('|^http|i', $m)) return null;
                $m = urldecode($m);
                if(!host($m)) return null;
                return $m;
            }, $serp);
            $serp = array_filter($serp);
            $serp = array_unique($serp);
            $serp = array_values($serp);
            $gi = null;
            if(preg_match('/<div\s+id=resultstats\s*>\s*[a]?[b]?[o]?[u]?[t]?\s*([0-9,]+)\s*result/i', $content, $gi)) ;
            elseif(preg_match('|of\s+about\s+<b>\s*([0-9,]+)\s*</b>|i', $content, $gi)) ;
            elseif(preg_match('|[a]?[b]?[o]?[u]?[t]?\s*([0-9,]+)\s*result|i', $content, $gi)) ;
            else return null;
            $gi = intval(str_replace(array(',', ' '), '', $gi[1]));
            return array('serp' => $serp, 'gi' => $gi);
        }
        public static function getQueryForGoogleSERP($kw, $start = 0) {
            $link = 'http://www.google.com/search?q=';
            $link .= urlencode($kw);
            $link .= '&hl=en&gl=us&num=100&start=' . $start;
            return $link;
        }
        public static function getQueryForGoogleIndex($domain) {
            $link = 'http://www.google.com/search?q=';
            $link .= urlencode('site:' . $domain);
            $link .= '&hl=en&gl=us';
            return $link;
        }
        private static function hashURL($string) {
            $Check1 = self::strToNum($string, 0x1505, 0x21);
            $Check2 = self::strToNum($string, 0, 0x1003F);
            $Check1 >>= 2;
            $Check1 = (($Check1 >> 4) & 0x3FFFFC0) | ($Check1 & 0x3F);
            $Check1 = (($Check1 >> 4) & 0x3FFC00) | ($Check1 & 0x3FF);
            $Check1 = (($Check1 >> 4) & 0x3C000) | ($Check1 & 0x3FFF);
            $T1 = (((($Check1 & 0x3C0) << 4) | ($Check1 & 0x3C)) << 2) | ($Check2 & 0xF0F);
            $T2 = (((($Check1 & 0xFFFFC000) << 4) | ($Check1 & 0x3C00)) << 0xA) | ($Check2 & 0xF0F0000);
            return ($T1 | $T2);
        }
        private static function checkHash($hashNumber) {
            $check = 0;
            $flag = 0;
            $hashString = sprintf('%u', $hashNumber);
            $length = strlen($hashString);
            for($i = $length - 1;  $i >= 0;  $i--) {
                $r = $hashString{$i};
                if(1 === ($flag % 2)) {
                    $r += $r;
                    $r = (int)($r / 10) + ($r % 10);
                }
                $check += $r;
                $flag ++;
            }
            $check %= 10;
            if(0 !== $check) {
                $check = 10 - $check;
                if(1 === ($flag % 2)) {
                    if(1 === ($check % 2)) {
                        $check += 9;
                    }
                    $check >>= 1;
                }
            }
            return '7' . $check . $hashString;
        }
        private static function strToNum($Str, $Check, $Magic) {
            $Int32Unit = 4294967296;
            $length = strlen($Str);
            for($i = 0; $i < $length; $i++) {
                $Check *= $Magic;
                if($Check >= $Int32Unit) {
                    $Check = ($Check - $Int32Unit * (int)($Check / $Int32Unit));
                    $Check = ($Check < -2147483648) ? ($Check + $Int32Unit) : $Check;
                }
                $Check += ord($Str{$i});
            }
            return $Check;
        }
    }
