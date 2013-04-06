<?php
    class RFetcher {
        public static function rfetch($list, $settings = array()) {
            start:
            if(is_string($list)) $list = array($list);
            if(!is_array($list)) $list = array();
            $list = array_values(array_filter($list));
            $tt = count($list);
            if(!$tt) {
                trigger_error('LIST_IS_EMPTY', E_USER_WARNING);
                return true;
            }
            trigger_error("GO ({$tt})", E_USER_NOTICE);
            $a = array();
            @ $url_filter = is_callable($settings['url_filter']) ? $settings['url_filter'] : null;
            @ $table = $settings['table'];
            if(!$table) trigger_error('RFETCH_WITHOUT_TABLE', E_USER_ERROR);
            if(!is_callable($url_filter)) trigger_error('RFETCH_URL_FILTER_IS_NOT_CALLABLE', E_USER_ERROR);
            // CREATE TABLE IF NO
            if(!DB::value("SHOW TABLES LIKE '{$table}'")) DB::q(trim(RFetcher::table($table)));
            if(!isset($trigger_ready)) {
                $trigger_ready = true;
                DB::q("UPDATE `{$table}` SET ready = 0");
            }
            $list = array_filter($list, $url_filter);
            $list = array_filter($list, function($url) use($table, & $a) {
                $row = DB::row("SELECT content, ancs, ready FROM `{$table}` WHERE url = ?", array($url));
                if(@ $row['ready']) return false;
                if(@ $row['content']) {
                    // trigger_error("{$url} is saved locally to {$file}", E_USER_NOTICE);
                    $a[] = $row['ancs'];
                    DB::q("UPDATE `{$table}` SET ready = 1 WHERE url = ?", array($url));
                    return false;
                }
                return true;
            });
            $list = array_values($list);
            $ttt = count($list);
            if(!$ttt) {
                trigger_error('LIST_IS_EMPTY_AFTER_FILTER', E_USER_WARNING);
                goto restart;
            }
            trigger_error("GOOO ({$ttt})", E_USER_NOTICE);
            foreach(array_chunk($list, 1000) as $l)
                foreach(Fetcher::fetch($l, $settings) as $elem) {
                    $url = $elem['elem'];
                    $content = $elem['content'];
                    $ancs = RFetcher::ancs($url, $content);
                    $ancs = array_filter(nsplit($ancs), $url_filter);
                    $ancs = array_values($ancs);
                    $ancs = implode(chr(10), $ancs);
                    DB::q("INSERT INTO `{$table}` SET url = ?, content = ?, ancs = ?, ready = 1", array($url, $content, $ancs));
                    $a[] = $ancs;
                }
            restart:
            $a = implode(chr(10), $a);
            $a = nsplit($a);
            mt_shuffle($a);
            $list = array_unique($a);
            $list = array_values(array_filter($list, $url_filter));
            trigger_error("FINAL - " . count($list), E_USER_NOTICE);
            if($settings['level']--) goto start;
        }
        public static function table($table) {
            return "
                CREATE TABLE `{$table}` (
                    `url` VARCHAR(255) NOT NULL,
                    `content` MEDIUMTEXT NOT NULL,
                    `virtualize` MEDIUMTEXT NOT NULL,
                    `ancs` TEXT NOT NULL,
                    `ready` INT NOT NULL,
                    PRIMARY KEY (`url`)
                )
                COLLATE='utf8_general_ci'
                ENGINE=MyISAM;
            ";
        }
        public static function ancs($url, $content) {
            @ $doc = phpQuery::newDocumentHTML($content);
            if(!$doc) return array();
            $a = array();
            $url = parse_url($url);
            @ $port = $url['port'] ? ":{$url['port']}" : '';
            @ $path = $url['path'];
            $mpath = preg_replace('~/[^/]+$~', '/', $path);
            foreach($doc -> find('a[href]') as $anchor) {
                $href = trim(pq($anchor) -> attr('href'));
                if(!$href) continue;
                elseif(strpos($href, '#') === 0) continue;
                elseif(strpos($href, 'javascript:') === 0) continue;
                elseif(strpos($href, 'mailto:') === 0) continue;
                elseif(host($href)) $a[] = $href;
                elseif(strpos($href, '/') === 0) $a[] = "{$url['scheme']}://{$url['host']}{$port}{$href}";
                elseif(strpos($path, '/') === strlen($path) - 1) $a[] = "{$url['scheme']}://{$url['host']}{$port}{$path}{$href}";
                else $a[] = "{$url['scheme']}://{$url['host']}{$port}{$mpath}{$href}";
            }
            $a = array_map(function($a) {
                $init = $a;
                $a = preg_replace('~#.*$~', '', $a);
                $a = preg_replace('~/[^/]+/\.\./~', '/', $a);
                $a = preg_replace('~[/]+~', '/', $a);
                $a = str_replace_once('/', '//', $a);
                $a = str_replace('/./', '/', $a);
                if($init !== $a) trigger_error("A: {$init} --> {$a}", E_USER_NOTICE);
                return $a;
            }, $a);
            return implode(chr(10), $a);
        }
    }
