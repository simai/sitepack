<?php

declare(strict_types=1);

namespace SitePack\Distribution;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class Naming
{
    private const MIN_VOLUME_WIDTH = 4;

    public static function baseName(string $site, DateTimeImmutable $createdAt, string $mode): string
    {
        return self::siteToken($site)
            . '_'
            . $createdAt->setTimezone(new DateTimeZone('UTC'))->format('Ymd_His')
            . '_'
            . self::mode($mode);
    }

    public static function packageFile(string $baseName): string
    {
        return self::safeSegment($baseName) . '.sitepack';
    }

    public static function signatureFile(string $payloadName): string
    {
        return self::safeSegment($payloadName) . '.sig.json';
    }

    public static function volumeFile(string $baseName, int $index, int $count): string
    {
        if ($index < 1 || $count < 1 || $index > $count) {
            throw new InvalidArgumentException('Volume index and count must be positive with index <= count.');
        }

        $width = max(self::MIN_VOLUME_WIDTH, strlen((string)$count));
        return sprintf(
            '%s.part-%0' . $width . 'd-of-%0' . $width . 'd.sitepack',
            self::safeSegment($baseName),
            $index,
            $count
        );
    }

    public static function siteToken(string $value): string
    {
        $site = strtolower(rtrim(trim($value), '.'));
        if ($site === ''
            || str_contains($site, '://')
            || preg_match('~[/:?#\\\\]~', $site) === 1
            || preg_match('/[^\x20-\x7E]/', $site) === 1) {
            throw new InvalidArgumentException('Site must be an ASCII DNS host without protocol, port, or path.');
        }

        if (strlen($site) > 253) {
            throw new InvalidArgumentException('Site must be a valid DNS host.');
        }
        foreach (explode('.', $site) as $label) {
            if ($label === ''
                || strlen($label) > 63
                || preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label) !== 1) {
                throw new InvalidArgumentException('Site must be a valid DNS host.');
            }
        }

        return $site;
    }

    private static function mode(string $value): string
    {
        $mode = strtolower(trim($value));
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?$/', $mode) !== 1) {
            throw new InvalidArgumentException('Mode must be a lowercase ASCII token of at most 32 characters.');
        }
        return $mode;
    }

    private static function safeSegment(string $value): string
    {
        $segment = trim($value);
        if ($segment === ''
            || $segment === '.'
            || $segment === '..'
            || str_contains($segment, '/')
            || str_contains($segment, '\\')
            || preg_match('/[<>:"|?*\x00-\x1F\x7F]/', $segment) === 1
            || preg_match('/[. ]$/', $segment) === 1
            || preg_match('/^(?:con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)/i', $segment) === 1) {
            throw new InvalidArgumentException('Value must be a safe filename segment.');
        }
        return $segment;
    }
}
