<?php

declare(strict_types=1);

namespace SitePack\Validator;

use SitePack\Report\ValidationReport;

class EnvelopeValidator
{
    private SchemaValidator $schemaValidator;

    private FileUtil $fileUtil;

    private SafePath $safePath;

    private ReportWriter $reportWriter;

    /**
     * @param SchemaValidator $schemaValidator
     * @param FileUtil $fileUtil
     * @param SafePath $safePath
     * @param ReportWriter $reportWriter
     * @return void
     */
    public function __construct(
        SchemaValidator $schemaValidator,
        FileUtil $fileUtil,
        SafePath $safePath,
        ReportWriter $reportWriter
    ) {
        $this->schemaValidator = $schemaValidator;
        $this->fileUtil = $fileUtil;
        $this->safePath = $safePath;
        $this->reportWriter = $reportWriter;
    }

    /**
     * @param string $encJsonPath
     * @param bool $checkPayloadFile
     * @param array<string, string> $toolInfo
     * @return array{report:ValidationReport,reportPath:string,usageError:bool}
     */
    public function validate(string $encJsonPath, bool $checkPayloadFile, array $toolInfo): array
    {
        $report = new ValidationReport($toolInfo, 'envelope', $encJsonPath);
        $usageError = false;

        $jsonResult = $this->fileUtil->readJsonFile($encJsonPath);
        if (!$jsonResult['ok'] || $jsonResult['data'] === null) {
            $report->addMessage('error', 'INVALID_JSON', 'Failed to read envelope: ' . ($jsonResult['error'] ?? ''));
            $report->markFinished();
            $reportPath = $this->reportWriter->write($report, dirname($encJsonPath));
            return [
                'report' => $report,
                'reportPath' => $reportPath,
                'usageError' => $usageError,
            ];
        }

        $validation = $this->schemaValidator->validate('envelope', $jsonResult['data']);
        if (!$validation['valid']) {
            foreach ($validation['errors'] as $message) {
                $report->addMessage('error', 'SCHEMA_VALIDATION_FAILED', $message);
            }
        }

        if ($checkPayloadFile) {
            $payload = $jsonResult['data']->payload ?? null;
            $payloadFile = is_object($payload) && isset($payload->file) ? (string) $payload->file : '';
            if ($payloadFile === '') {
                $report->addMessage('error', 'MISSING_FILE', 'payload.file is missing');
            } else {
                $safe = $this->safePath->resolve(dirname($encJsonPath), $payloadFile);
                if (!$safe['ok']) {
                    $report->addMessage('error', $safe['code'] ?? 'INVALID_PATH', $safe['message'] ?? 'Invalid path');
                } elseif ($safe['path'] !== null && !is_file($safe['path'])) {
                    $report->addMessage('error', 'MISSING_FILE', 'payload.file not found');
                }
            }
        }

        $report->markFinished();
        $reportPath = $this->reportWriter->write($report, dirname($encJsonPath));

        return [
            'report' => $report,
            'reportPath' => $reportPath,
            'usageError' => $usageError,
        ];
    }
}
