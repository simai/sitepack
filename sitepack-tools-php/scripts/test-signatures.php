<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use SitePack\Signature\SignatureService;
use SitePack\Validator\SchemaValidator;

$dir = sys_get_temp_dir() . '/sitepack-signature-' . bin2hex(random_bytes(6));
mkdir($dir, 0770, true);
$payload = $dir . '/example.sitepack';
file_put_contents($payload, 'sitepack bytes');
$seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
$pair = sodium_crypto_sign_seed_keypair($seed);
$public = sodium_crypto_sign_publickey($pair);
$service = new SignatureService(new SchemaValidator(dirname(__DIR__) . '/schemas'));
$document = $service->sign($payload, 'test-key', base64_encode($seed), '2026-08-25T00:00:00Z');
$trust = ['keys' => ['test-key' => ['algorithm' => 'Ed25519', 'publicKey' => base64_encode($public)]]];

if (($service->verify($payload, $document, $trust)['status'] ?? '') !== 'valid') {
    throw new RuntimeException('Valid signature was rejected.');
}
if (($service->verify($payload, $document, ['keys' => []])['status'] ?? '') !== 'untrusted') {
    throw new RuntimeException('Unknown key was not reported as untrusted.');
}
file_put_contents($payload, ' modified', FILE_APPEND);
if (($service->verify($payload, $document, $trust)['status'] ?? '') !== 'invalid') {
    throw new RuntimeException('Modified payload was accepted.');
}

@unlink($payload);
@rmdir($dir);
echo "signature tests: 3 checks passed\n";
