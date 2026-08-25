<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use SitePack\Distribution\Naming;

function same(string $expected, string $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("Expected {$expected}, got {$actual}");
    }
}

function rejects(callable $callback): void
{
    try {
        $callback();
    } catch (\InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Expected InvalidArgumentException.');
}

$base = Naming::baseName(
    'School.OBR.Site.',
    new \DateTimeImmutable('2026-08-25T14:35:22+03:00'),
    'FULL'
);

same('school.obr.site_20260825_113522_full', $base);
same(
    'xn--80atdl2c.xn--p1ai_20260825_113522_compact',
    Naming::baseName(
        'xn--80atdl2c.xn--p1ai',
        new \DateTimeImmutable('2026-08-25T11:35:22Z'),
        'compact'
    )
);
same($base . '.sitepack', Naming::packageFile($base));
same($base . '.sitepack.sig.json', Naming::signatureFile($base . '.sitepack'));
same($base . '.part-0001-of-0012.sitepack', Naming::volumeFile($base, 1, 12));
same($base . '.part-10000-of-10000.sitepack', Naming::volumeFile($base, 10000, 10000));

rejects(static fn () => Naming::baseName('https://school.obr.site', new \DateTimeImmutable(), 'full'));
rejects(static fn () => Naming::baseName('school.obr.site/path', new \DateTimeImmutable(), 'full'));
rejects(static fn () => Naming::baseName('school_obr.site', new \DateTimeImmutable(), 'full'));
rejects(static fn () => Naming::baseName('school.obr.site', new \DateTimeImmutable(), '../full'));
rejects(static fn () => Naming::volumeFile($base, 13, 12));
foreach (['CON', 'backup?.sitepack', 'backup.'] as $unsafeBase) {
    rejects(static fn () => Naming::volumeFile($unsafeBase, 1, 1));
}

echo "Distribution naming checks passed.\n";
