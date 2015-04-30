<?php

// curl -sS https://getcomposer.org/installer | php
// php composer.phar require rackspace/php-opencloud

require('vendor/autoload.php');
use OpenCloud\Rackspace;

$username = 'username';
$apiKey = 'f25b8b114bd4f9b774fe9409bdaaf2ee';
$region = 'IAD'; // Virginia
$container = 'mycontainer';
$folder = '123/'; // if root, leave it empty
$origin = "*";

//
// GET TOKEN AND ENDPOINT FOR CLOUDFILES
//
$json = array(
    'auth' => array(
        'RAX-KSKEY:apiKeyCredentials' => array(
            'username' => $username,
            'apiKey' => $apiKey
        )
    )
);
$json = json_encode($json);
$_ = `curl -d '{$json}' -H "Content-Type: application/json" "https://identity.api.rackspacecloud.com/v2.0/tokens" 2>/dev/null`;
@ $_ = json_decode($_, true);
@ $token = $_['access']['token']['id'];
if(!$token) {
    echo 'COULD NOT AUTH!', chr(10);
    exit(1);
}
$catalog = $_['access']['serviceCatalog'];
foreach($catalog as $service)
    if($service['name'] === 'cloudFiles') break;
    else $service = null;
foreach($service['endpoints'] as $elem)
    if($elem['region'] === $region) break;
    else $elem = null;
$endpoint = $elem['publicURL'];
echo "endpoint = '{$endpoint}'", chr(10);
echo "token = '{$token}'", chr(10);

//
// GO!
//
$client = new Rackspace(Rackspace::US_IDENTITY_ENDPOINT, array(
  'username' => $username,
  'apiKey'   => $apiKey,
));
$service = $client -> objectStoreService(null, $region);
$c = $service -> getContainer($container);
$files = $c -> objectList(array('prefix' => $folder));

foreach($files as $file) {
    $file = $file -> getName();
    $cmd = array();
    $cmd[] = "curl -i -X POST";
    $cmd[] = "-H 'Origin: {$origin}'";
    $cmd[] = "-H 'Access-Control-Allow-Origin: {$origin}'";
    $cmd[] = "-H 'X-Auth-Token: {$token}'";
    $cmd[] = "-H 'X-Container-Meta-Access-Control-Allow-Origin: {$origin}'";
    $cmd[] = "'{$endpoint}/{$container}/{$file}'";
    $cmd[] = "2>/dev/null | grep HTTP";
    $cmd = implode(' ', $cmd);
    $_ = rtrim(shell_exec($cmd));
    echo "CMD: '{$cmd}'", chr(10);
    echo "RESULT: '{$_}'", chr(10);
}

exit(0);
