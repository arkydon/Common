<?php
    class Tree {
        private static $Q = null;
        private static $tree = null;
        private static $active = null;
        public static $currentIndex = null;
        public static function init($tree, $Q) {
            global $R, $T;
            //
            Tree::$Q = $Q;
            Tree::$tree = $tree;
            Tree::$active = null;
            Tree::activate(Tree::$tree, Tree::$Q);
            //
            $T = array();
            $R['Q'] = $Q;
            $R['SITE'] = SITE;
            $R['SITE_PATH'] = SITE_PATH;
            $R['INCLUDE_PATH'] = INCLUDE_PATH;
        }
        public static function activate(& $tree, $Q) {
            array_walk($tree, function(& $node) use($Q) {
                global $R, $T;
                $regex = $node['regex'];
                if(!is_array($regex)) $regex = array($regex);
                foreach($regex as $r)
                    if(preg_match("!^{$r}$!", $Q, $match)) {
                        $node['isCurrent'] = true;
                        $node['isActive'] = true;
                        foreach($match as $k => $v) if(is_string($k)) $R[$k] = $v;
                        $T['R'] = $R;
                        return null;
                    }
                if(!isset($node['children'])) return null;
                Tree::activate($node['children'], $Q);
                foreach($node['children'] as $child)
                    if(isset($child['isActive'])) {
                        $node['isActive'] = true;
                        return null;
                    }
            });
        }
        private static function getActiveCallstack($tree = null) {
            if(is_null($tree)) $tree = Tree::$tree;
            $return = array();
            foreach($tree as $node)
                if(isset($node['isCurrent'])) {
                    if(isset($node['callstack']))
                        $return[] = $node['callstack'];
                    return $return;
                } elseif(isset($node['isActive'])) {
                    if(isset($node['callstack']))
                        $return[] = $node['callstack'];
                    if(isset($node['children']))
                        $return = array_merge($return, Tree::getActiveCallstack($node['children']));
                }
            return $return;
        }
        public static function getActiveNodes(& $tree = null) {
            if(!is_null(Tree::$active)) return Tree::$active;
            if(is_null($tree)) $tree = & Tree::$tree;
            $return = array();
            foreach(array_keys($tree) as $key)
                if(isset($tree[$key]['isCurrent'])) {
                    $return[] = & $tree[$key];
                    return $return;
                } elseif(isset($tree[$key]['isActive'])) {
                    $return[] = & $tree[$key];
                    if(isset($tree[$key]['children']))
                        $return = array_merge($return, Tree::getActiveNodes($tree[$key]['children']));
                }
            Tree::$active = $return;
            return $return;
        }
        public static function & getCurrentNode() {
            $active = self::getActiveNodes();
            return $active[Tree::$currentIndex];
        }
        public static function callStack() {
            $GC = CARRY('get_class');
            $callstack = Tree::getActiveCallstack();
            $count = count($callstack);
            Tree::$currentIndex = count(Tree::getActiveNodes()) - 1;
            if(!$count) return false;
            for($i = 0; $i < $count; $i++)
                foreach(array_keys($callstack[$i]) as $key)
                    if(strpos($key, '_') === 0) {
                        $callstack[$i][substr($key, 1)] = $callstack[$i][$key];
                        unset($callstack[$i][$key]);
                    } elseif($i < $count - 1) unset($callstack[$i][$key]);
            $final = array();
            for($i = $count - 1; $i >= 0; $i--) $final += $callstack[$i]; 
            ksort($final);
            foreach($final as $key => $value) {
                if(is_string($value))
                    $name = "{$value}";
                elseif(is_array($value) and is_string($value[0]))
                    $name = "{$value[0]}";
                elseif(is_array($value) and is_array($value[0]) and is_string($value[0][0]))
                    $name = "{$value[0][0]}, {$value[0][1]}";
                elseif(is_array($value) and is_array($value[0]) and is_object($value[0][0]))
                    $name = "{$GC($value[0][0])}, {$value[0][1]}";
                elseif(is_closure($value))
                    $name = "CLOSURE ({$key})";
                else continue;
                Profiling::start($name);
                if(is_closure($value)) $value();
                else {
                    if(is_string($value)) $value = array($value);
                    $function = array_shift($value);
                    call_user_func_array($function, $value);
                }
                Profiling::stop();
            }
            return true;
        }
    }
