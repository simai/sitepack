<?php

declare(strict_types=1);

namespace SitePack\Validator;

class SafePath
{
    /**
     * @param string $baseDir
     * @param string $relativePath
     * @return array{ok:bool,path:string|null,code:string|null,message:string|null}
     */
    public function resolve(string $baseDir, string $relativePath): array
    {
        if ($relativePath === '' || trim($relativePath) === '') {
            return $this->error('INVALID_PATH', 'Artifact path is empty');
        }

        if (str_contains($relativePath, "\0")) {
            return $this->error('INVALID_PATH', 'Artifact path contains null byte');
        }

        if ($this->isAbsolutePath($relativePath)) {
            return $this->error('INVALID_PATH', 'Absolute paths are not allowed');
        }

        $segments = preg_split('/[\\\\\/]+/', $relativePath) ?: [];
        foreach ($segments as $segment) {
            if ($segment === '..') {
                return $this->error('INVALID_PATH', 'Artifact path contains directory traversal (..)');
            }
        }

        $baseReal = realpath($baseDir);
        if ($baseReal === false) {
            $baseReal = $baseDir;
        }

        $normalizedBase = $this->normalizePath($baseReal);
        $combined = rtrim($normalizedBase, '/\\') . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\');
        $normalized = $this->normalizePath($combined);

        if (!$this->isInsideBase($normalizedBase, $normalized)) {
            return $this->error('INVALID_PATH', 'Artifact path escapes the package root');
        }

        return [
            'ok' => true,
            'path' => $normalized,
            'code' => null,
            'message' => null,
        ];
    }

    /**
     * @param string $path
     * @return bool
     */
    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }

    /**
     * @param string $base
     * @param string $path
     * @return bool
     */
    private function isInsideBase(string $base, string $path): bool
    {
        $baseNormalized = rtrim($base, '/\\');
        $pathNormalized = rtrim($path, '/\\');

        if ($baseNormalized === $pathNormalized) {
            return true;
        }

        return str_starts_with($pathNormalized, $baseNormalized . DIRECTORY_SEPARATOR);
    }

    /**
     * @param string $path
     * @return string
     */
    private function normalizePath(string $path): string
    {
        $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
        $prefix = '';

        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            $prefix = substr($path, 0, 2);
            $path = substr($path, 2);
        }

        $segments = preg_split('/[\\\\\/]+/', $path) ?: [];
        $normalized = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($normalized);
                continue;
            }
            $normalized[] = $segment;
        }

        $result = ($prefix !== '' ? $prefix : '') . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $normalized);
        return rtrim($result, '/\\');
    }

    /**
     * @param string $code
     * @param string $message
     * @return array{ok:bool,path:string|null,code:string,message:string}
     */
    private function error(string $code, string $message): array
    {
        return [
            'ok' => false,
            'path' => null,
            'code' => $code,
            'message' => $message,
        ];
    }
}
