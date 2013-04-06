<?php
    /* "AS IS" GENERATOR */
    class Generator {
        private static function checkSquareBrackets($string) {
            $depth = 0;
            for($i = 0; $i < strlen($string); $i++) {
                $char = $string[$i];
                $depth += $char === '[';
                $depth -= $char === ']';
                if($depth < 0) break;
            }
            if($depth) return false;
            return true;
        }
        private static function syntaxSquareBrackets($string) {
            if(!self::checkSquareBrackets($string)) return '';
            return preg_replace_callback('#\[(([^\[\]]+|(?R))*)\]#', function($m) {
                $depth = 0;
                $alt = array();
                $collector = '';
                for($i = 0; $i < strlen($m[1]); $i++) {
                    $char = $m[1][$i];
                    $depth += $char === '[';
                    $depth -= $char === ']';
                    if($depth === 0 and $char === '|') {
                        $alt[] = $collector;
                        $collector = '';
                    } else $collector .= $char;
                }
                $alt[] = $collector;
                $alt = array_map(function($elem) {
                    $match = array();
                    if(preg_match('|(.*)\(([0-9]+)\)|', $elem, $match))
                        return array($match[1], $match[2]);
                    return array($elem, 1);
                }, $alt);
                reset($alt);
                $choser = array();
                while(list(, list($elem, $count)) = each($alt))
                    for($i = 0; $i < $count; $i++)
                        $choser[] = $elem;
                return $choser[mt_rand(0, count($choser) - 1)];
            }, $string);
        }
        /*
        private static function syntaxDoubleAt($string) {
            $GENERATOR = include GENERATOR_DB;
            return preg_replace_callback('/\(@@([A-Za-z0-9_]+)\)/', function($m) use($GENERATOR) {
                if(!isset($GENERATOR[$m[1]])) return '(0)';
                return '(' . count(nsplit($GENERATOR[$m[1]])) . ')';
            }, $string);
        }
        */
        private static function syntaxAt($string) {
            $GENERATOR = include GENERATOR_DB;
            return preg_replace_callback('/@([A-Za-z0-9_]+)/', function($m) use($GENERATOR) {
                if(!isset($GENERATOR[$m[1]])) return '';
                $generator = nsplit($GENERATOR[$m[1]]);
                return '<!-- ' . $generator[mt_rand(0, count($generator) - 1)] . ' -->';
            }, $string);
        }
        private static function syntax($string) {
            //echo $string, chr(10);
            do {
                $old = $string;
                //$check = $string;
                $string = self::syntaxSquareBrackets($string);
                //if($check !== $string) {
                    //echo $string, chr(10);
                //    $check = $string;
                //}
                $string = self::syntaxAt($string);
                //if($check !== $string) {
                    //echo $string, chr(10);
                //    $check = $string;
                //}
            } while($old !== $string);
            return $string;
        }
        public static function generateComment($comment = '@comment') {
            $comment = self::syntax($comment);
            $comment = preg_replace('/<!-- | -->/', '', $comment);
            $comment = preg_replace_callback('/^[a-z]/', function($m) { return strtoupper($m[0]); }, $comment);
            $comment = preg_replace('/\s+/', ' ', $comment);
            $comment = trim($comment);
            return $comment;
            ;

            $comment = array();
            $frame = $frame[mt_rand(0, count($frame) - 1)];
            $transform = $transform[mt_rand(0, count($transform) - 1)];
            foreach(explode(',', $frame) as $f) {
                $f = trim($f);
                if(!$f) continue;
                if(preg_match('|(.*?)\s*\(([0-9]+)\)|', $f, $match))
                    if(mt_rand(1, 100) > intval($match[2]))
                        continue;
                    else
                        $f = $match[1];
                if(!isset($GENERATOR[$f])) continue;
                $stack = nsplit($GENERATOR[$f]);
                $comment[] = $stack[mt_rand(0, count($stack) - 1)];
            }
            $comment = implode($glue, $comment);
            foreach(explode(',', $prettify) as $p) {
                $p = trim($p);
                if(!$p) continue;
                echo $p, chr(10);
                if(preg_match('|(.*?)\s*\(([0-9]+)\)|', $p, $match))
                    if(is_callable($call = array(get_class(), 'transform' . ucfirst($match[1]))))
                        $comment = call_user_func_array($call, array($comment, $match[2]));
            }
            return $comment;
        }
        public static function transformKeyboard($p, $chance) {
            return preg_replace_callback('|[a-z_-]+|i', function($match) use($chance) {
                $match = $match[0];
                if(strlen($match) < 5) return $match;
                if(mt_rand(1, 100) > $chance)
                    return $match;
                $index = mt_rand(1, count($match) - 3);
                $letter = $match[$index];
                $match[$index] = $match[$index + 1];
                $match[$index + 1] = $letter;
                echo $match, chr(10);
                return $match;
            }, $p);
        }
    }
