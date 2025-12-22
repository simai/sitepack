<?php

declare(strict_types=1);

namespace SitePack\Validator;

use RuntimeException;

class DigestCalculator
{
    /**
     * @param string $filePath
     * @return string
     * @throws RuntimeException
     */
    public function computeSha256(string $filePath): string
    {
        $hash = hash_file('sha256', $filePath);
        if ($hash === false) {
            throw new RuntimeException('Failed to compute sha256');
        }

        return 'sha256:' . $hash;
    }
}
