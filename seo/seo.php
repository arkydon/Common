<?php
    function generator($string = null, $generator = array(), $recursive = false) {
        if($recursive === false) {
            if(!$string) return null;
            $string = "[{$string}]";
            if(!isset($generator['callstack'])) $generator['callstack'] = array();
            return generator($string, $generator, true);
        }
        if(!$string) return '';
        $check = $string;
        $string = preg_replace_callback('/@([a-z0-9_]+)/', function($m) use($generator) {
            if(!isset($generator[$m[1]])) return '';
            $generator = nsplit($generator[$m[1]]);
            $generator = '[' . implode('|', $generator) . ']';
            return $generator;
        }, $string);
        if($check === $string) ; else return generator($string, $generator, true);
        $depth = 0;
        $alt = array();
        $collector = '';
        for($i = 0; $i < strlen($string); $i++) {
            $char = $string[$i];
            if($char === '[') $depth += 1;
            if($char === ']') $depth -= 1;
            if($char === '|' and $depth === 0) {
                $alt[] = $collector;
                $collector = '';
            } else $collector .= $char;
        }
        $alt[] = $collector;
        $collector = '';
        $alt = array_map(function($elem) {
            $match = array();
            if(preg_match('|(.*)\(([a-z0-9_,]+)\)$|', $elem, $match)) {
                $paren = explode(',', $match[2]);
                $array = array(array(), array());
                foreach($paren as $elem)
                    if(is_numeric($elem)) $array[0][] = $elem;
                    else $array[1][] = $elem;
                if(isset($array[0][0]))
                    return array($match[1], $array[0][0], $array[1]);
                return array($match[1], 1, $array[1]);
            }
            return array($elem, 1, array());
        }, $alt);
        reset($alt);
        $choser = array();
        while(list(, list($elem, $count, $call)) = each($alt))
            for($i = 0; $i < $count; $i++)
                $choser[] = array($elem, $call);
        if(!count($choser)) return '';
        list($elem, $call) = $choser[mt_rand(0, count($choser) - 1)];
        unset($choser);
        //
        $depth = 0;
        $alt = array();
        $collector = '';
        for($i = 0; $i < strlen($elem); $i++) {
            $char = $elem[$i];
            if($char === '[') {
                if($depth === 0) {
                    $alt[] = $collector;
                    $collector = '';
                } else $collector .= $char;
                $depth += 1;
            } elseif($char === ']') {
                if($depth === 1) {
                    $alt[] = $collector;
                    $collector = '';
                } else $collector .= $char;
                $depth -= 1;
            } else $collector .= $char;
        }
        $alt[] = $collector;
        $collector = '';
        if($depth) {
            trigger_error("ERROR - {$string}", E_USER_NOTICE);
            return '';
        }
        foreach(array_keys($alt) as $key)
            if(
                strpos($alt[$key], ']') === false and
                strpos($alt[$key], '[') === false and
                strpos($alt[$key], '|') === false and
                !preg_match('|\(([a-z0-9_,]+)\)$|', $alt[$key])
            ) ; else $alt[$key] = generator($alt[$key], $generator, true);
        $elem = implode('', $alt);
        foreach($call as $c)
            if(is_callable($c))
                $elem = call_user_func($c, $elem);
            elseif(isset($generator['callstack'][$c]) and is_callable($generator['callstack'][$c]))
                $elem = call_user_func($generator['callstack'][$c], $elem);
        return $elem;
    }
