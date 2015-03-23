<?php
    //
    // Автозагрузка классов
    //
    function __autoload($c) {
        $class = LIB_ROOT . '/' . strtolower($c) . '.class.php';
        $act = ACT_ROOT . '/' . strtolower($c) . '.act.php';
        if(is_file($class)) include $class;
        if(is_file($act)) include $act;
    }
    //
    // ACT
    //
    // форматы вызова из браузера:
    //
    // ?ACT=function --> вызывает функцию ACT_function()
    // ?ACT=test_function --> вызывает функцию ACT_function() из test.act.php
    // ?ACT=class_function --> вызывает Class::ACT_function()
    // ?ACT=class_function --> вызывает Model_Class::ACT_function()
    // *** вместо ACT может быть POST и AJAX ***
    //
    // форматы вызова из консоли:
    //
    // # php index.php ACT=function --> вызывает функцию ACT_function()
    // # php index.php ACT=test_function --> вызывает функцию ACT_function() из test.act.php
    // # php index.php ACT=class_function --> вызывает Class::ACT_function()
    // # php index.php ACT=class_function --> вызывает Model_Class::ACT_function()
    // *** вместо ACT может быть CLI ***
    // *** можно добавлять параметры, например, ACT=class_function&verbose=1 ***
    //
    function ACT($RACT = array()) {
        global $R;
        if(isset($_SERVER['argv'][1])) {
            $args = array();
            parse_str($_SERVER['argv'][1], $args);
            foreach($args as $k => $v) $R[$k] = $v;
        }
        foreach(array_keys($RACT) as $ACT)
            if(isset($R[$ACT])) {
                call_user_func($RACT[$ACT]);
                $act = $R[$ACT];
                break;
            }
        if(isset($act) and $act and $ACT) {
            if(is_numeric(strpos($act, '_')))
                @ list($class, $method) = explode('_', $act, 2);
            $act = "{$ACT}_{$act}";
            @ $method = "{$ACT}_{$method}";
            @ class_exists($class);
            if(is_callable($act)) call_user_func($act);
            elseif(isset($method) and is_callable(array($class, $method)))
                call_user_func(array($class, $method));
            elseif(isset($method) and is_callable(array($_ = 'Model_' . $class, $method)))
                call_user_func(array($_, $method));
            else trigger_error('INVALID ACT', E_USER_ERROR);
            return true;
        } else return false;
    }
    //
    // Самый главный обработчик
    //
    // $nodes - это список нодов
    // каждый нод должен содержит 'regex', 'callstack' и 'values'
    //
    // regex - это регулярка или название файла, где срабатывает нода
    // если в регулярке есть именной патерн, то его значение запишется в $R
    // например, "~^/post/(?P<post>[0-9]+)\.html$~" при "/post/7.html" запишет в $R['post'] = 7
    //
    // callstack - это список вызовов (call)
    // call имеют след. форматы
    // 1) 'function' --> просто вызов function()
    // 2) array('function') --> просто вызов function()
    // 3) array('function', 1, 2, 3) --> вызов function(1, 2, 3)
    // 4) array(array('class', 'function')) --> вызов class::function()
    // 5) array(array('class', 'function'), 1, 2, 3) --> вызов class::function(1, 2, 3)
    // 6) function() { ;;; } --> вызов этой анонимной функции
    //
    // values - хэш-массив значений, все они запишутся в $T
    //
    function HANDLER($nodes, $url) {
        foreach($nodes as $node)
            foreach(@ (array)$node['regex'] as $regex)
                if($regex === $url or @ preg_match($regex, $url, $match)) {
                    global $R, $T;
                    if(isset($match))
                        foreach($match as $k => $v)
                            if(is_string($k)) {
                                $R[$k] = $v;
                                $T["is_{$v}"] = true;
                            }
                    $T['name'] = $node['name'];
                    $T["is_{$T['name']}"] = true;
                    $R['URL'] = $url;
                    //
                    // BEGIN CALLSTACK
                    //
                    Profiling::start('callstack');
                    $GC = CARRY('get_class');
                    foreach($node['callstack'] as $call) {
                        if(is_string($call)) $name = "{$call}";
                        elseif(is_array($call) and is_string($call[0]))
                            $name = "{$call[0]}";
                        elseif(is_array($call) and is_array($call[0]))
                            $name = "{$call[0][0]}, {$call[0][1]}";
                        elseif(is_closure($call))
                            $name = "CLOSURE";
                        else trigger_error('INVALID CALL', E_USER_ERROR);
                        Profiling::start($name);
                        if(!is_closure($call)) {
                            if(is_string($call)) $call = array($call);
                            $function = array_shift($call);
                            call_user_func_array($function, $call);
                        } else $call();
                        Profiling::stop();
                    }
                    Profiling::stop();
                    return true;
                }
        return false;
    }
    //
    // Обработчик ошибок
    //
    function error_handler($errno, $errstr, $errfile, $errline) {
        $errstr = trim($errstr);
        if(!$errstr) return true;
        $IS_ERROR = $errno & (E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR | E_USER_ERROR);
        $IS_USER = $errno & (E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED);
        if((error_reporting() & $errno) or $IS_ERROR) ; else return true;
        static $E = array(
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        );
        $db = debug_backtrace();
        array_shift($db);
        $db = array_filter(array_map(function($frame) {
            @ $file = $frame['file'];
            @ $line = $frame['line'];
            @ $function = $frame['function'];
            @ $class = $frame['class'];
            @ $type = $frame['type'];
            if($function === 'trigger_error') return null;
            if($class and $type) $function = implode('', array($class, $type, $function));
            if($file) {
                $file = explode('/', $file);
                $file = end($file);
                $function .= "@{$file}";
            }
            if($line) $function .= ":{$line}";
            if($function === '{closure}') return null;
            return $function;
        }, $db));
        $db = implode(' - ', $db);
        $ECHO = '--- ';
        if(isset($E[$errno])) $ECHO .= $E[$errno];
        else $ECHO .= 'E_UNKNOWN (' . $errno . ')';
        $ECHO .= ' --- ' . now() . ' ---' . chr(10);
        if(!$IS_USER) $ECHO .= "({$errline}) " . trim($errfile) . chr(10);
        $ECHO .= $db . chr(10) . $errstr . chr(10) . chr(10);
        $display = ini_get('display_errors');
        if($display or $IS_ERROR) echo $ECHO;
        if(!$display or $IS_ERROR) {
            static $file = null;
            if(!is_resource($file)) {
                $file = LOG_FILE;
                if(!is_file($file)) exec("> '{$file}'");
                if(!is_writable($file)) { echo LOG_FILE, ' - is not writable!', chr(10); exit(1); }
                $file = fopen($file, 'a');
            }
            fwrite($file, $ECHO);
        }
        if($IS_ERROR) exit(1);
        return true;
    }
    //
    // Обработчик фатальных ошибок
    //
    function fatal_error_handler() {
        if($error = error_get_last() and $error['type'] & (E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR))
            error_handler($error['type'], $error['message'], $error['file'], $error['line']);
    }
    //
    // Разбить по строкам, сделать trim и убрать пустые
    //
    function nsplit($value) {
        $value = str_replace(chr(13), chr(10), $value);
        $value = explode(chr(10), $value);
        $value = array_map('trim', $value);
        $value = array_filter($value);
        return array_values($value);
    }
    //
    // Получить IP
    //
    function IP() {
        $ip = getenv('REMOTE_ADDR');
        $xip = getenv('HTTP_X_REAL_IP');
        if($ip === '127.0.0.1' and $xip) $ip = $xip;
        return $ip;
    }
    //
    // Получить UA
    //
    function UA() {
        return getenv('HTTP_USER_AGENT');
    }
    //
    // Получить REF
    //
    function REF() {
        return getenv('HTTP_REFERER');
    }
    //
    // Получить URI ()
    //
    function URI() {
        static $uri = null;
        if($uri) return $uri;
        $uri = getenv('REQUEST_URI');
        $url = URL();
        $qs = QS();
        if($qs) $qs = '?' . $qs;
        $uri = $url . $qs;
        return URI();
    }
    //
    // Получить URL
    //
    function URL() {
        static $url = null;
        if($url) return $url;
        @ $url = '/' . ltrim($_GET['Q'], '/');
        unset($_GET['Q']);
        return URL();
    }
    //
    // Получить QUERY_STRING (проблема в том, что исходная содержит Q)
    //
    function QS() {
        static $qs = array();
        if(is_string($qs)) return $qs;
        foreach($_GET as $k => $v)
            if($k != 'Q')
                $qs[] = $k . '=' . urlencode($v);
        $qs = implode('&', $qs);
        return QS();
    }
    //
    // Шаблонизатор
    //
    function template() { // $template, $args, $canvas = true
        if(func_num_args() >= 2) extract(func_get_arg(1));
        ob_start();
        @ include(TEMPLATE_ROOT . '/' . func_get_arg(0));
        $result = ob_get_contents();
        ob_end_clean();
        if(
            (func_num_args() === 3 and func_get_arg(2))
                or
            (func_num_args() <= 2)
        )
            return sprintf(
                '<div class="%s">',
                str_replace('.', '-', basename(func_get_arg(0)))
            ) . $result . '</div>';
        return $result;
    }
    //
    // Является ли объект анонимной функцией
    //
    function is_closure($obj) {
        return is_callable($obj) and is_object($obj);
    }
    //
    // str_replace только один раз
    //
    function str_replace_once($needle, $replace, $haystack) {
        $pos = strpos($haystack, $needle);
        if($pos === false) return $haystack;
        return substr_replace($haystack, $replace, $pos, strlen($needle));
    }
    //
    // Кадрировать строку
    //
    function string_crop($string, $begin, $end) {
        $one = strpos($string, $begin);
        if($one === false) return null;
        $one += strlen($begin);
        $string = substr($string, $one);
        $two = strpos($string, $end);
        if($two === false) return null;
        return substr($string, 0, $two);
    }
    //
    // Выдернуть host из урла
    //
    function host($url) {
        return parse_url($url, PHP_URL_HOST);
    }
    //
    // Домен по частям
    //
    function domain_part($domain, $part = 0) {
        $domain = explode('.', $domain);
        if($part < 0) return @ $domain[count($domain) + $part];
        return @ $domain[$part];
    }
    //
    // GEOIP
    //
    function geoip($ip) {
        // apt-get install geoip-bin
        $exec = shell_exec("geoiplookup '{$ip}' | grep -v -i 'not found'");
        if(preg_match('~\b[A-Z0-9]{2}\b~', trim($exec), $match))
            return $match[0];
        else return '';
    }
    //
    // WHOIS
    //
    function whois($domain) {
        $exec = shell_exec("whois '{$domain}' | grep -i '{$domain}' | grep -v -i 'no match' | grep -c .");
        if(intval(trim($exec))) return true;
        else return false;
    }
    //
    // Является ли массив полностью ассоциативным
    //
    function is_assoc($arr) {
        if(!is_array($arr)) return false;
        $count0 = count($arr);
        $count1 = count(array_filter(array_keys($arr), 'is_string'));
        return $count0 === $count1;
    }
    //
    // Является ли урл ссылкой на главную страницу
    //
    function is_url_main($url) {
        if(parse_url($url, PHP_URL_PATH) === '/' and !parse_url($url, PHP_URL_QUERY)) return true;
        return false;
    }
    //
    // Получение функции для обхода массива (лучше использовать array_column)
    //
    function index($index) {
        return function($array) use ($index) {
            return @ $array[$index];
        };
    }
    //
    // array_column
    //
    if(!function_exists('array_column')) {
        function array_column($input = null, $columnKey = null, $indexKey = null) {
            $argc = func_num_args();
            $params = func_get_args();
            // Проверка параметров
            if($argc < 2) {
                trigger_error("array_column() expects at least 2 parameters, {$argc} given", E_USER_WARNING);
                return null;
            }
            if(!is_array($params[0])) {
                trigger_error('array_column() expects parameter 1 to be array, ' . gettype($params[0]) . ' given', E_USER_WARNING);
                return null;
            }
            if(
                !is_int($params[1])
                and !is_float($params[1])
                and !is_string($params[1])
                and $params[1] !== null
                and !(
                    is_object($params[1]) and method_exists($params[1], '__toString')
                )
            ) {
                trigger_error('array_column(): The column key should be either a string or an integer', E_USER_WARNING);
                return false;
            }
            if(
                isset($params[2])
                and !is_int($params[2])
                and !is_float($params[2])
                and !is_string($params[2])
                and !(
                    is_object($params[2]) and method_exists($params[2], '__toString')
                )
            ) {
                trigger_error('array_column(): The index key should be either a string or an integer', E_USER_WARNING);
                return false;
            }
            // Все хорошо с параметрами - начнем подготовку к циклу
            $paramsInput = $params[0];
            $paramsColumnKey = ($params[1] === null) ? null : (string) $params[1];
            $paramsIndexKey = null;
            if(isset($params[2]))
                if(is_float($params[2]) or is_int($params[2]))
                    $paramsIndexKey = (int) $params[2];
                else
                    $paramsIndexKey = (string) $params[2];
            $resultArray = array();
            // Поехали
            foreach($paramsInput as $row) {
                $key = $value = null;
                $keySet = $valueSet = false;
                if($paramsIndexKey !== null and array_key_exists($paramsIndexKey, $row)) {
                    $keySet = true;
                    $key = (string) $row[$paramsIndexKey];
                }
                if($paramsColumnKey === null) {
                    $valueSet = true;
                    $value = $row;
                } elseif(is_array($row) and array_key_exists($paramsColumnKey, $row)) {
                    $valueSet = true;
                    $value = $row[$paramsColumnKey];
                }
                if($valueSet and $keySet) $resultArray[$key] = $value;
                elseif($valueSet) $resultArray[] = $value;
            }
            return $resultArray;
        }
    }
    //
    // Обрезать строку
    //
    function truncate($string, $len = 39) {
        if(strlen($string) > $len) {
            $l = intval($len / 2);
            return substr($string, 0, $l) . '...' . substr($string, - $l + 3);
        } else return $string;
    }
    //
    // Текущая дата
    //
    function curdate($days = 0) {
        return date(SQL_FORMAT_DATE, time() + $days * 24 * 3600);
    }
    //
    // Текущее время
    //
    function now($seconds = 0) {
        return date(SQL_FORMAT_DATETIME, time() + $seconds);
    }
    //
    // Умный шаффл
    //
    function mt_shuffle(& $items, $seed = null) {
        $keys = array_keys($items);
        $SEED = '';
        for($i = count($items) - 1; $i > 0; $i--) {
            if($seed) {
                $j = rand_from_string($SEED . $seed) % ($i + 1);
                $SEED .= $j;
            }
            else $j = mt_rand(0, $i);
            list($items[$keys[$i]], $items[$keys[$j]]) = array($items[$keys[$j]], $items[$keys[$i]]);
        }
    }
    //
    // Подождать пока спадет нагрузка
    //
    function wait_while_load_is_high($LOAD = 3) {
        while(1) {
            list($load) = sys_getloadavg();
            if($load > $LOAD) {
                trigger_error('SLEEP', E_USER_NOTICE);
                sleep(60);
            } else return null;
        }
    }
    //
    // Получить расширение файла
    //
    function file_get_ext($file) {
        $ext = $file;
        $ext = trim($ext);
        $ext = explode('/', $ext);
        $ext = end($ext);
        $ext = explode('.', $ext);
        $ext = end($ext);
        $ext = strtolower($ext);
        if($ext === strtolower(basename($file))) return '';
        return $ext;
    }
    //
    // Получить имя файла, без расширения
    //
    function file_get_name($file) {
        $file = trim($file);
        $file = explode('/', $file);
        $file = end($file);
        list($file) = explode('.', $file);
        return $file;
    }
    //
    // Изменить размер картинки
    //
    function resize($file, $size = '128x128') {
        $SR = STORAGE_ROOT;
        // need imagemagick
        $path = $SR . $file;
        $ext = file_get_ext($file);
        // файл должен существовать и иметь опред. расширения
        if(!is_file($path) or !in_array($ext, array('jpeg', 'jpg', 'gif', 'png')))
            return null;
        $append = preg_replace('~[^0-9x]~i', '', $size);
        $target = preg_replace('~(\.[a-z]{3,4}$)~i', "-{$append}\$1", $file);
        if(!is_file($SR . $target))
            exec("convert '{$SR}{$file}' -resize {$size} '{$SR}{$target}' > /dev/null 2>&1");
        return $target;
    }
    //
    // Кинуть файл в каталог где подкаталоги от 0 до 999
    //
    function toStorage($file) {
        $add = 0;
        clearstatcache();
        $dir = STORAGE_ROOT;
        $dir = rtrim($dir, '/');
        $hash = CARRY('hash', array(0 => 'sha256', 2 => false));
        //
        // Файл должен существовать и директория должна существовать
        //
        if(!is_file($file) or !is_dir($dir))
            trigger_error("INVALID FILE ({$file}) OR DIR ({$dir})", E_USER_ERROR);
        //
        // Нормализация имени файла
        //
        $name = file_get_name($file);
        $ext = file_get_ext($file);
        if($ext) $ext = ".{$ext}";
        //
        $name = Normalize::go($name, 'tr,trRu,en');
        if(!$name) $name = mt_rand();
        $name = str_replace(' ', '-', $name);
        //
        // Найти имя файла, что не занято и туда записать файл
        // Если занято и хэши совпадают, то просто вернуть это имя
        //
        $fileHash = $hash(file_get_contents($file));
        $subdir = rand_from_string($fileHash) % 1000;
        if(!is_dir($dir . '/' . $subdir))
            trigger_error('INVALID SUBDIR', E_USER_ERROR);
        $_ = "/{$subdir}/{$name}{$ext}";
        while(true):
            if(is_file($dir . $_) and $hash(file_get_contents($dir . $_)) === $fileHash)
                return $_;
            if(!is_file($dir . $_)) {
                rename($file, $dir . $_);
                return $_;
            }
            $_ = "/{$subdir}/{$name}-{$add}{$ext}";
            $add += 1;
        endwhile;
    }
    //
    // Случайное число из строки
    //
    function rand_from_string($string) {
        $int = md5($string);
        $int = preg_replace('/[^0-9]/', '', $int);
        $int = substr($int, 0, strlen(mt_getrandmax() . '') - 1);
        return intval($int);
    }
    //
    // POST
    //
    function file_post_contents($url, $array) {
        $postdata = http_build_query($array);
        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  =>
                    'Content-type: application/x-www-form-urlencoded' . "\r\n" .
                    'User-Agent: ' . getUserAgent() . "\r\n"
                ,
                'content' => $postdata
            )
        );
        $context  = stream_context_create($opts);
        return file_get_contents($url, false, $context);
    }
    //
    // Получить страницу через curl
    //
    function curl($url) {
        $options = array(
            CURLOPT_RETURNTRANSFER => true, // return web page
            CURLOPT_HEADER => false, // don't return headers
            CURLOPT_FOLLOWLOCATION => true, // follow redirects
            CURLOPT_ENCODING => "", // handle all encodings
            CURLOPT_USERAGENT => getUserAgent(), // who am i
            CURLOPT_AUTOREFERER => true, // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120, // timeout on connect
            CURLOPT_TIMEOUT => 120, // timeout on response
            CURLOPT_MAXREDIRS => 10 // stop after 10 redirects
        );
        
        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $content = curl_exec($ch);
        $err = curl_errno($ch);
        $errmsg = curl_error($ch);
        $header = curl_getinfo($ch);
        curl_close($ch);

        if($err) trigger_error($errmsg, E_USER_WARNING);
        else return $content;
    }
    //
    // awmproxy.com getter
    //
    function getProxy() {
        $PROXY_LIST = LIST_ROOT . '/proxy.list';
        $PROXY_LIST_URL = 'http://awmproxy.com/701proxy.txt';
        $PROXY_LIST_TIMEOUT = 3600 * 24;
        $PROXY_LOGIN = PROXY_LOGIN;
        $PROXY_PASSWD = PROXY_PASSWD;
        clearstatcache();
        if(filemtime($PROXY_LIST) < time() - $PROXY_LIST_TIMEOUT) {
            trigger_error('UPDATE', E_USER_NOTICE);
            $list = curl($PROXY_LIST_URL);
            if(!$list) trigger_error('INVALID LIST', E_USER_ERROR);
            $list = nsplit($list);
            $list = implode(chr(10), array_map(function($line) use($PROXY_LOGIN, $PROXY_PASSWD) {
                return "proxy://{$PROXY_LOGIN}:{$PROXY_PASSWD}@{$line}";
            }, $list));
            file_put_contents($PROXY_LIST, $list);
        }
        $list = file_get_contents($PROXY_LIST);
        $list = nsplit($list);
        $proxy = $list[mt_rand(0, count($list) - 1)];
        if($proxy) return $proxy;
        trigger_error('INVALID PROXY', E_USER_ERROR);
    }
    //
    // ua getter
    //
    function getUserAgent($seed = null) {
        if($seed === null) $seed = rand_from_string(microtime(true));
        $UA_LIST = LIST_ROOT . '/ua.list';
        $list = file_get_contents($UA_LIST);
        $list = nsplit($list);
        $ua = $list[$seed % count($list)];
        if($ua) return $ua;
        trigger_error('INVALID UA', E_USER_ERROR);
    }
    //
    // ua getter using seed and regular expression
    //
    function getSpecialUserAgent($seed = null, $re = null) {
        if($seed and is_string($seed)) $seed = rand_from_string($seed);
        else $seed = rand_from_string(microtime(true));
        $UA_LIST = LIST_ROOT . '/ua.list';
        $list = file_get_contents($UA_LIST);
        $list = nsplit($list);
        if($re) $list = array_values(array_filter($list, function($line) use($re) {
            return preg_match($re, $line);
        }));
        $ua = $list[$seed % count($list)];
        if($ua) return $ua;
        trigger_error('INVALID UA', E_USER_ERROR);
    }
    //
    // gauss
    //
    function gauss($peak = 0, $stdev = 1, $seed = null) {
        $x = ($seed ? rand_from_string($seed) : mt_rand()) / mt_getrandmax();
        $y = ($seed ? rand_from_string($seed . $x) : mt_rand()) / mt_getrandmax();
        $gauss = sqrt(-2 * log($x)) * cos(2 * pi() * $y);
        return $gauss * $stdev + $peak;
    }
    //
    // Получить функцию в переменную с подстановкой аргументов
    // $f = CARRY('function', array(0 => 'первый аргумент', 2 => 'третий аргумент'));
    // $f('второй аргумент') --> вызовет function('первый аргумент', 'второй аргумент', 'третий аргумент');
    //
    function CARRY() {
        $args = func_get_args();
        if(!count($args)) trigger_error('INVALID ARGS', E_USER_ERROR);
        $function = array_shift($args);
        if(!is_callable($function)) trigger_error('INVALID FUNCTION', E_USER_ERROR);
        $map = count($args) ? array_shift($args) : array();
        if(!is_array($map)) trigger_error('INVALID MAP', E_USER_ERROR);
        if(count($args)) trigger_error('INVALID ARGS', E_USER_ERROR);
        return function() use($function, $map) {
            $ARGS = func_get_args();
            $final = array();
            $counter = count($ARGS) + count($map);
            for($i = 0; $i < $counter; $i++)
                if(array_key_exists($i, $map))
                    $final[] = $map[$i];
                else $final[] = array_shift($ARGS);
            return call_user_func_array($function, $final);
        };
    }
    //
    // Получить все заголовки
    //
    if(!function_exists('getallheaders')) {
        function getallheaders() {
            $headers = array();
            foreach($_SERVER as $name => $value)
                if(substr($name, 0, 5) == 'HTTP_')
                    $headers[
                        str_replace(
                            ' ',
                            '-',
                            ucwords(
                                strtolower(str_replace('_', ' ', substr($name, 5)))
                            )
                        )
                    ] = $value;
            return $headers;
        }
    }
    //
    // Экранировать HTML
    //
    function esc($s) {
        return htmlspecialchars($s, ENT_NOQUOTES);
    }
    //
    // Полное HTML экранирование для аттрибутов
    //
    function fesc($s) {
        return htmlspecialchars($s, ENT_QUOTES);
    }
    //
    //
    //
