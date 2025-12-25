<?php

declare(strict_types=1);

$examplesRoot = $argv[1] ?? getenv('SITEPACK_SPEC_EXAMPLES');
if ($examplesRoot === null || trim($examplesRoot) === '') {
    fwrite(STDERR, "Provide a path to examples/ or set SITEPACK_SPEC_EXAMPLES\n");
    exit(2);
}

$examplesRoot = realpath($examplesRoot);
if ($examplesRoot === false || !is_dir($examplesRoot)) {
    fwrite(STDERR, "Path does not exist: {$examplesRoot}\n");
    exit(2);
}

$binPath = realpath(__DIR__ . '/../bin/sitepack-validate');
if ($binPath === false) {
    fwrite(STDERR, "bin/sitepack-validate not found\n");
    exit(2);
}

$examples = [
    'hello-world',
    'config-only',
    'content-assets',
    'full',
    'full-code',
    'cross-relations',
    'chunked-assets',
    'objects-two-objects',
];

foreach ($examples as $name) {
    $target = $examplesRoot . DIRECTORY_SEPARATOR . $name;
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($binPath) . ' --quiet package ' . escapeshellarg($target);
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "Validation failed: {$name}\n");
        exit($exitCode);
    }
}

$envelopePath = $examplesRoot . DIRECTORY_SEPARATOR . 'encrypted' . DIRECTORY_SEPARATOR . 'example.sitepack.enc.json';
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($binPath) . ' envelope ' . escapeshellarg($envelopePath);
passthru($command, $exitCode);
if ($exitCode !== 0) {
    fwrite(STDERR, "Envelope validation failed\n");
    exit($exitCode);
}

fwrite(STDOUT, "All examples validated successfully.\n");
