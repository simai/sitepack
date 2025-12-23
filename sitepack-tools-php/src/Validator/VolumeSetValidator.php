<?php

declare(strict_types=1);

namespace SitePack\Validator;

use SitePack\Report\ValidationReport;
use Throwable;
use ZipArchive;

class VolumeSetValidator
{
    private SchemaValidator $schemaValidator;

    private DigestCalculator $digestCalculator;

    private SafePath $safePath;

    private ReportWriter $reportWriter;

    private FileUtil $fileUtil;

    private PackageValidator $packageValidator;

    /**
     * @param SchemaValidator $schemaValidator
     * @param DigestCalculator $digestCalculator
     * @param SafePath $safePath
     * @param ReportWriter $reportWriter
     * @param FileUtil $fileUtil
     * @param PackageValidator $packageValidator
     * @return void
     */
    public function __construct(
        SchemaValidator $schemaValidator,
        DigestCalculator $digestCalculator,
        SafePath $safePath,
        ReportWriter $reportWriter,
        FileUtil $fileUtil,
        PackageValidator $packageValidator
    ) {
        $this->schemaValidator = $schemaValidator;
        $this->digestCalculator = $digestCalculator;
        $this->safePath = $safePath;
        $this->reportWriter = $reportWriter;
        $this->fileUtil = $fileUtil;
        $this->packageValidator = $packageValidator;
    }

    /**
     * @param string $volumesPath
     * @param string|null $profile
     * @param bool $skipDigest
     * @param bool $checkAssetBlobs
     * @param array<string, string> $toolInfo
     * @return array{report:ValidationReport,reportPath:string,usageError:bool}
     */
    public function validate(
        string $volumesPath,
        ?string $profile,
        bool $skipDigest,
        bool $checkAssetBlobs,
        array $toolInfo
    ): array {
        $report = new ValidationReport($toolInfo, 'volume-set', $volumesPath);
        $usageError = false;
        $baseDir = dirname($volumesPath);

        $volumeMessages = [];
        $addMessage = function (string $level, string $code, string $message, array $context = []) use (
            $report,
            &$volumeMessages
        ): void {
            $report->addMessage($level, $code, $message, $context);
            $volumeMessages[] = [
                'level' => $level,
                'code' => $code,
                'message' => $message,
                'context' => $context,
            ];
        };

        if (!is_file($volumesPath)) {
            $addMessage('error', 'VOLUME_SET_MISSING', 'Volume set descriptor not found', ['path' => $volumesPath]);
            $report->markFinished();
            $reportPath = $this->reportWriter->write($report, $baseDir);
            return [
                'report' => $report,
                'reportPath' => $reportPath,
                'usageError' => $usageError,
            ];
        }

        $volumeSetResult = $this->fileUtil->readJsonFile($volumesPath);
        if (!$volumeSetResult['ok'] || $volumeSetResult['data'] === null) {
            $addMessage('error', 'VOLUME_SET_PARSE_ERROR', 'Failed to read volume set descriptor', ['path' => $volumesPath]);
            $report->markFinished();
            $reportPath = $this->reportWriter->write($report, $baseDir);
            return [
                'report' => $report,
                'reportPath' => $reportPath,
                'usageError' => $usageError,
            ];
        }

        $validation = $this->schemaValidator->validate('volume-set', $volumeSetResult['data']);
        if (!$validation['valid']) {
            foreach ($validation['errors'] as $message) {
                $addMessage('error', 'VOLUME_SET_SCHEMA_ERROR', $message, ['path' => $volumesPath]);
            }
        }

        $volumes = [];
        if (is_object($volumeSetResult['data']) && isset($volumeSetResult['data']->volumes) && is_array($volumeSetResult['data']->volumes)) {
            $volumes = $volumeSetResult['data']->volumes;
        }

        $volumeEntries = [];
        foreach ($volumes as $volume) {
            if (!is_object($volume)) {
                $addMessage('error', 'VOLUME_ENTRY_INVALID', 'Volume entry must be an object');
                continue;
            }

            if (!is_string($volume->file ?? null) || trim((string) $volume->file) === '') {
                $addMessage('error', 'VOLUME_FILE_INVALID', 'Volume file name is missing or invalid');
                continue;
            }

            $safeFile = $this->safePath->resolve($baseDir, (string) $volume->file);
            if (!$safeFile['ok'] || $safeFile['path'] === null) {
                $addMessage('error', 'INVALID_PATH', 'Unsafe volume file path: ' . (string) $volume->file);
                continue;
            }

            if (!is_file($safeFile['path'])) {
                $addMessage('error', 'VOLUME_FILE_MISSING', 'Volume file not found: ' . (string) $volume->file);
                continue;
            }

            $actualSize = filesize($safeFile['path']);
            if ($actualSize === false) {
                $addMessage('error', 'VOLUME_SIZE_ERROR', 'Failed to read volume file size: ' . (string) $volume->file);
                continue;
            }

            if (is_int($volume->size ?? null) && $actualSize !== $volume->size) {
                $addMessage(
                    'error',
                    'VOLUME_SIZE_MISMATCH',
                    'Volume size mismatch: ' . (string) $actualSize . ' != ' . (string) $volume->size
                );
            }

            if (is_string($volume->sha256 ?? null)) {
                try {
                    $actualSha = $this->digestCalculator->computeSha256Hex($safeFile['path']);
                    if ($actualSha !== strtolower((string) $volume->sha256)) {
                        $addMessage(
                            'error',
                            'VOLUME_DIGEST_MISMATCH',
                            'Volume digest mismatch: ' . $actualSha . ' != ' . (string) $volume->sha256
                        );
                    }
                } catch (Throwable $exception) {
                    $addMessage(
                        'error',
                        'VOLUME_DIGEST_ERROR',
                        'Failed to compute volume digest: ' . $exception->getMessage()
                    );
                }
            }

            if (is_object($volume->encryption ?? null) && ($volume->encryption->scheme ?? null) === 'age') {
                $envelopeFile = $volume->encryption->envelopeFile ?? null;
                if (!is_string($envelopeFile) || trim((string) $envelopeFile) === '') {
                    $addMessage('error', 'VOLUME_ENVELOPE_MISSING', 'encryption.envelopeFile is required for age volumes');
                } else {
                    $safeEnvelope = $this->safePath->resolve($baseDir, (string) $envelopeFile);
                    if (!$safeEnvelope['ok'] || $safeEnvelope['path'] === null) {
                        $addMessage('error', 'INVALID_PATH', 'Unsafe envelope file path: ' . (string) $envelopeFile);
                    } elseif (!is_file($safeEnvelope['path'])) {
                        $addMessage('error', 'VOLUME_ENVELOPE_NOT_FOUND', 'Envelope file not found: ' . (string) $envelopeFile);
                    }
                }

                $addMessage('error', 'VOLUME_ENCRYPTION_UNSUPPORTED', 'Encrypted volumes are not supported by this validator');
            }

            $volumeEntries[] = [
                'index' => is_int($volume->index ?? null) ? $volume->index : 0,
                'file' => (string) $volume->file,
                'path' => $safeFile['path'],
            ];
        }

        if ($report->getErrorCount() > 0) {
            $report->markFinished();
            $reportPath = $this->reportWriter->write($report, $baseDir);
            return [
                'report' => $report,
                'reportPath' => $reportPath,
                'usageError' => $usageError,
            ];
        }

        $tempDir = $this->createTempDir();
        if ($tempDir === null) {
            $addMessage('error', 'VOLUME_TEMP_DIR_ERROR', 'Failed to create temporary directory');
            $report->markFinished();
            $reportPath = $this->reportWriter->write($report, $baseDir);
            return [
                'report' => $report,
                'reportPath' => $reportPath,
                'usageError' => $usageError,
            ];
        }

        try {
            usort(
                $volumeEntries,
                static fn (array $left, array $right): int => $left['index'] <=> $right['index']
            );

            foreach ($volumeEntries as $entry) {
                $this->extractZip((string) $entry['path'], $tempDir, $addMessage);
            }

            if ($report->getErrorCount() > 0) {
                $report->markFinished();
                $reportPath = $this->reportWriter->write($report, $baseDir);
                return [
                    'report' => $report,
                    'reportPath' => $reportPath,
                    'usageError' => $usageError,
                ];
            }

            $packageResult = $this->packageValidator->validate(
                $tempDir,
                $profile,
                $skipDigest,
                $checkAssetBlobs,
                $toolInfo
            );

            $packageReport = $packageResult['report'];
            foreach ($volumeMessages as $message) {
                $packageReport->addMessage(
                    $message['level'],
                    $message['code'],
                    $message['message'],
                    $message['context']
                );
            }

            $packageReport->setTarget('volume-set', $volumesPath);
            $packageReport->markFinished();
            $reportPath = $this->reportWriter->write($packageReport, $baseDir);

            return [
                'report' => $packageReport,
                'reportPath' => $reportPath,
                'usageError' => $packageResult['usageError'],
            ];
        } finally {
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * @return string|null
     */
    private function createTempDir(): ?string
    {
        $base = rtrim(sys_get_temp_dir(), '/\\');
        $path = $base . DIRECTORY_SEPARATOR . 'sitepack-volumes-' . uniqid('', true);
        if (!mkdir($path, 0777, true) && !is_dir($path)) {
            return null;
        }

        return $path;
    }

    /**
     * @param string $zipPath
     * @param string $destination
     * @param callable(string,string,string,array<string,string>):void $addMessage
     * @return void
     */
    private function extractZip(string $zipPath, string $destination, callable $addMessage): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $addMessage(
                'error',
                'VOLUME_EXTRACT_ERROR',
                'Failed to open volume archive: ' . $zipPath,
                ['file' => $zipPath]
            );
            return;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if ($entryName === false || $entryName === '') {
                continue;
            }

            $safe = $this->safePath->resolve($destination, $entryName);
            if (!$safe['ok'] || $safe['path'] === null) {
                $addMessage(
                    'error',
                    'VOLUME_ENTRY_PATH_UNSAFE',
                    'Unsafe entry path: ' . $entryName,
                    ['entry' => $entryName]
                );
                continue;
            }

            if (str_ends_with($entryName, '/')) {
                if (!is_dir($safe['path'])) {
                    mkdir($safe['path'], 0777, true);
                }
                continue;
            }

            $dir = dirname($safe['path']);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $stream = $zip->getStream($entryName);
            if ($stream === false) {
                $addMessage(
                    'error',
                    'VOLUME_EXTRACT_ERROR',
                    'Failed to read zip entry: ' . $entryName,
                    ['entry' => $entryName]
                );
                continue;
            }

            $out = fopen($safe['path'], 'wb');
            if ($out === false) {
                fclose($stream);
                $addMessage(
                    'error',
                    'VOLUME_EXTRACT_ERROR',
                    'Failed to write zip entry: ' . $entryName,
                    ['entry' => $entryName]
                );
                continue;
            }

            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);
        }

        $zip->close();
    }

    /**
     * @param string $path
     * @return void
     */
    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
                continue;
            }

            unlink($itemPath);
        }

        rmdir($path);
    }
}
