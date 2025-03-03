<?php

require 'vendor/autoload.php';


function getJWT($privateKey) {
    $payload = array(
        'exp' => time()+600, // valid for the next 5 minutes
    );
    return \Firebase\JWT\JWT::encode($payload, file_get_contents($privateKey), 'RS256');

}

$options = getopt('', ['public-key:', 'private-key-file:', 'source-file:', 'environment-id:']);


if(isset($options['public-key'])) {
    $publicKey = $options['public-key'];
}

if(isset($options['private-key-file'])) {
    $privateKeyFile = $options['private-key-file'];
}

if(isset($options['source-file'])) {
    $sourceFile = $options['source-file'];
}

if(isset($options['environment-id'])) {
    $environmentId = $options['environment-id'];
}

echo PHP_EOL;
print_r((string)getJWT($privateKeyFile));
echo PHP_EOL;