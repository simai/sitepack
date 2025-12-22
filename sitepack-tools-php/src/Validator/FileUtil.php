<?php

declare(strict_types=1);

namespace SitePack\Validator;

use JsonException;

class FileUtil
{
    /**
     * @param string $path
     * @return array{ok:bool,data:object|null,error:string|null}
     */
    public function readJsonFile(string $path): array
    {
        if (!is_file($path)) {
            return [
                'ok' => false,
                'data' => null,
                'error' => 'File not found',
            ];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return [
                'ok' => false,
                'data' => null,
                'error' => 'Failed to read file',
            ];
        }

        try {
            /** @var object|null $data */
            $data = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [
                'ok' => false,
                'data' => null,
                'error' => $exception->getMessage(),
            ];
        }

        if ($data === null) {
            return [
                'ok' => false,
                'data' => null,
                'error' => 'Empty JSON',
            ];
        }

        return [
            'ok' => true,
            'data' => $data,
            'error' => null,
        ];
    }
}
