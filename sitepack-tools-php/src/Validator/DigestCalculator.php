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

    /**
     * @param string $filePath
     * @return string
     * @throws RuntimeException
     */
    public function computeSha256Hex(string $filePath): string
    {
        $hash = hash_file('sha256', $filePath);
        if ($hash === false) {
            throw new RuntimeException('Failed to compute sha256');
        }

        return $hash;
    }

    /**
     * @param array<int, string> $paths
     * @return string
     * @throws RuntimeException
     */
    public function computeSha256HexFromFiles(array $paths): string
    {
        $context = hash_init('sha256');
        foreach ($paths as $path) {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                throw new RuntimeException('Failed to open file for hashing: ' . $path);
            }

            while (!feof($handle)) {
                $data = fread($handle, 8192);
                if ($data === false) {
                    fclose($handle);
                    throw new RuntimeException('Failed to read file for hashing: ' . $path);
                }
                if ($data !== '') {
                    hash_update($context, $data);
                }
            }

            fclose($handle);
        }

        return hash_final($context);
    }
}
