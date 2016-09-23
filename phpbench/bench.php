<?php

$num = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '0';
$t = microtime(true);
$loops = 10000;
for ($i = 0; $i < $loops; $i++) {
    strtotime(mt_rand() % 2 == 0 ? "+1 week 2 days 4 hours 2 seconds" : "2016-01-01 10:01:01");
    sqrt(mt_rand());
    $array = range(1, 10000);
    shuffle($array);
    unset($array);
}
echo "({$num}) CPU + RAM: " . round(microtime(true) - $t, 1), "\n";

$t = microtime(true);
$files = array();
$loops = 1000;
$chunk = 1024 * 1024;
for ($i = 0; $i < $loops; $i++) {
    $temp = tempnam(sys_get_temp_dir(), 'bench');
    $files[] = $temp;
    file_put_contents($temp, str_repeat("_", $chunk));
}
echo "({$num}) Write: " . round(microtime(true) - $t, 1), "\n";

$t = microtime(true);
foreach ($files as $file) {
    md5_file($file);
    unlink($file);
}
echo "({$num}) Read + Delete: " . round(microtime(true) - $t, 1), "\n";
$py = "https://raw.github.com/sivel/speedtest-cli/master/speedtest_cli.py";
$network = shell_exec("wget --no-check-certificate -q -O - '{$py}' | python | grep -Ei '^(download|upload)'");
echo trim(preg_replace('~^~m', "({$num}) ", $network)), "\n";
