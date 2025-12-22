<?php

declare(strict_types=1);

namespace SitePack\Validator;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

class SchemaValidator
{
    private Validator $validator;

    private ErrorFormatter $formatter;

    /** @var array<string, string> */
    private array $schemaIds;

    /**
     * @param string $schemasDir
     * @return void
     */
    public function __construct(string $schemasDir)
    {
        $this->validator = new Validator();
        $this->formatter = new ErrorFormatter();
        $this->schemaIds = [];

        $this->registerSchemas($schemasDir);
    }

    /**
     * @param string $schemaName
     * @param object $data
     * @return array{valid:bool,errors:array<int, string>}
     */
    public function validate(string $schemaName, object $data): array
    {
        if (!isset($this->schemaIds[$schemaName])) {
            return [
                'valid' => false,
                'errors' => ["Unknown schema: {$schemaName}"],
            ];
        }

        $schemaId = $this->schemaIds[$schemaName];
        $result = $this->validator->validate($data, $schemaId);

        if ($result->isValid()) {
            return [
                'valid' => true,
                'errors' => [],
            ];
        }

        $messages = [];
        $formatted = $this->formatter->format($result->error());

        foreach ($formatted as $pointer => $errors) {
            foreach ($errors as $message) {
                $prefix = $pointer === '' ? '' : $pointer . ': ';
                $messages[] = $prefix . (string) $message;
            }
        }

        return [
            'valid' => false,
            'errors' => $messages,
        ];
    }

    /**
     * @param string $schemasDir
     * @return void
     */
    private function registerSchemas(string $schemasDir): void
    {
        $schemaFiles = [
            'manifest' => 'manifest.schema.json',
            'catalog' => 'catalog.schema.json',
            'entity' => 'entity.schema.json',
            'asset-index' => 'asset-index.schema.json',
            'config-kv' => 'config-kv.schema.json',
            'recordset' => 'recordset.schema.json',
            'capabilities' => 'capabilities.schema.json',
            'transform-plan' => 'transform-plan.schema.json',
            'envelope' => 'envelope.schema.json',
        ];

        foreach ($schemaFiles as $name => $file) {
            $path = rtrim($schemasDir, '/\\') . DIRECTORY_SEPARATOR . $file;
            $schemaId = $this->extractSchemaId($path);
            $this->validator->resolver()->registerFile($schemaId, $path);
            $this->schemaIds[$name] = $schemaId;
        }
    }

    /**
     * @param string $path
     * @return string
     */
    private function extractSchemaId(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return $path;
        }

        $schema = json_decode($contents, true);
        if (!is_array($schema) || !isset($schema['$id']) || !is_string($schema['$id'])) {
            return $path;
        }

        return $schema['$id'];
    }
}
