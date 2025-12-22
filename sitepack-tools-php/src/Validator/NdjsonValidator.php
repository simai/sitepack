<?php

declare(strict_types=1);

namespace SitePack\Validator;

use JsonException;
use SplFileObject;

class NdjsonValidator
{
    private SchemaValidator $schemaValidator;

    /**
     * @param SchemaValidator $schemaValidator
     * @return void
     */
    public function __construct(SchemaValidator $schemaValidator)
    {
        $this->schemaValidator = $schemaValidator;
    }

    /**
     * @param string $filePath
     * @param string $schemaName
     * @param bool $warnOnEmptyLine
     * @param callable(object, int):array<int, array{level:string,code:string,message:string,line:int|null}>|null $onRecord
     * @return array{details:array<int, array{level:string,code:string,message:string,line:int|null}>,linesValidated:int}
     */
    public function validateFile(
        string $filePath,
        string $schemaName,
        bool $warnOnEmptyLine = true,
        ?callable $onRecord = null
    ): array {
        $details = [];
        $linesValidated = 0;

        $file = new SplFileObject($filePath, 'r');
        $file->setFlags(SplFileObject::DROP_NEW_LINE);

        $lineNumber = 0;
        while (!$file->eof()) {
            $line = $file->fgets();
            if ($line === false) {
                break;
            }
            $lineNumber += 1;
            $trimmed = trim($line);
            if ($trimmed === '') {
                if ($warnOnEmptyLine) {
                    $details[] = [
                        'level' => 'warning',
                        'code' => 'EMPTY_LINE',
                        'message' => 'Empty NDJSON line skipped',
                        'line' => $lineNumber,
                    ];
                }
                continue;
            }

            $linesValidated += 1;
            try {
                $record = json_decode($trimmed, false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                $details[] = [
                    'level' => 'error',
                    'code' => 'NDJSON_INVALID_LINE_JSON',
                    'message' => 'Invalid JSON: ' . $exception->getMessage(),
                    'line' => $lineNumber,
                ];
                continue;
            }

            if (!is_object($record)) {
                $details[] = [
                    'level' => 'error',
                    'code' => 'NDJSON_INVALID_LINE_JSON',
                    'message' => 'Expected JSON object',
                    'line' => $lineNumber,
                ];
                continue;
            }

            if ($onRecord !== null) {
                $extraDetails = $onRecord($record, $lineNumber);
                foreach ($extraDetails as $detail) {
                    $details[] = [
                        'level' => $detail['level'],
                        'code' => $detail['code'],
                        'message' => $detail['message'],
                        'line' => $detail['line'] ?? $lineNumber,
                    ];
                }
            }

            $validation = $this->schemaValidator->validate($schemaName, $record);
            if (!$validation['valid']) {
                $message = $validation['errors'][0] ?? 'NDJSON schema error';
                $details[] = [
                    'level' => 'error',
                    'code' => 'NDJSON_SCHEMA_FAILED',
                    'message' => $message,
                    'line' => $lineNumber,
                ];
            }
        }

        return [
            'details' => $details,
            'linesValidated' => $linesValidated,
        ];
    }
}
