<?php

declare(strict_types=1);

$invalidRoot = $argv[1] ?? getenv('SITEPACK_SPEC_EXAMPLES_INVALID');
if ($invalidRoot === null || trim($invalidRoot) === '') {
    fwrite(STDERR, "Provide a path to examples-invalid/ or set SITEPACK_SPEC_EXAMPLES_INVALID\n");
    exit(2);
}

$invalidRoot = realpath($invalidRoot);
$binPath = realpath(__DIR__ . '/../bin/sitepack-validate');
if ($invalidRoot === false || !is_dir($invalidRoot) || $binPath === false) {
    fwrite(STDERR, "Invalid fixture root or validator path\n");
    exit(2);
}

$examples = [
    'objects-missing-passport',
    'objects-passport-id-mismatch',
    'objects-bad-selector-op',
    'external-signed-url',
    'full-snapshot-external',
];

foreach ($examples as $name) {
    $target = $invalidRoot . DIRECTORY_SEPARATOR . $name;
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($binPath)
        . ' --quiet package ' . escapeshellarg($target);
    passthru($command, $exitCode);
    if ($exitCode === 0) {
        fwrite(STDERR, "Expected validation failure but succeeded: {$name}\n");
        exit(1);
    }
}

fwrite(STDOUT, "All invalid examples failed validation as expected.\n");
