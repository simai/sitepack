<?php

declare(strict_types=1);

namespace SitePack\Validator;

use SitePack\Report\ArtifactResult;
use SitePack\Report\ValidationReport;
use Throwable;

class PackageValidator
{
    private SchemaValidator $schemaValidator;

    private NdjsonValidator $ndjsonValidator;

    private DigestCalculator $digestCalculator;

    private SafePath $safePath;

    private ReportWriter $reportWriter;

    private FileUtil $fileUtil;

    /**
     * @param SchemaValidator $schemaValidator
     * @param NdjsonValidator $ndjsonValidator
     * @param DigestCalculator $digestCalculator
     * @param SafePath $safePath
     * @param ReportWriter $reportWriter
     * @param FileUtil $fileUtil
     * @return void
     */
    public function __construct(
        SchemaValidator $schemaValidator,
        NdjsonValidator $ndjsonValidator,
        DigestCalculator $digestCalculator,
        SafePath $safePath,
        ReportWriter $reportWriter,
        FileUtil $fileUtil
    ) {
        $this->schemaValidator = $schemaValidator;
        $this->ndjsonValidator = $ndjsonValidator;
        $this->digestCalculator = $digestCalculator;
        $this->safePath = $safePath;
        $this->reportWriter = $reportWriter;
        $this->fileUtil = $fileUtil;
    }

    /**
     * @param string $packageRoot
     * @param string|null $profile
     * @param bool $skipDigest
     * @param bool $checkAssetBlobs
     * @param array<string, string> $toolInfo
     * @return array{report:ValidationReport,reportPath:string,usageError:bool}
     */
    public function validate(
        string $packageRoot,
        ?string $profile,
        bool $skipDigest,
        bool $checkAssetBlobs,
        array $toolInfo
    ): array {
        $report = new ValidationReport($toolInfo, 'package', $packageRoot);
        $usageError = false;

        $manifestPath = rtrim($packageRoot, '/\\') . DIRECTORY_SEPARATOR . 'sitepack.manifest.json';
        $catalogPath = rtrim($packageRoot, '/\\') . DIRECTORY_SEPARATOR . 'sitepack.catalog.json';

        $manifest = $this->loadJson($manifestPath, 'manifest', $report);
        $catalog = $this->loadJson($catalogPath, 'catalog', $report);

        $manifestArtifacts = [];
        if (is_object($manifest) && isset($manifest->artifacts) && is_array($manifest->artifacts)) {
            foreach ($manifest->artifacts as $id) {
                if (is_string($id)) {
                    $manifestArtifacts[] = $id;
                }
            }
        }

        $catalogArtifacts = [];
        if (is_object($catalog) && isset($catalog->artifacts) && is_array($catalog->artifacts)) {
            foreach ($catalog->artifacts as $artifact) {
                if (is_object($artifact)) {
                    $catalogArtifacts[] = $artifact;
                }
            }
        }

        $report->setArtifactsTotal(count($catalogArtifacts));

        $catalogIds = [];
        foreach ($catalogArtifacts as $artifact) {
            $catalogIds[] = is_string($artifact->id ?? null) ? $artifact->id : '';
        }

        foreach ($manifestArtifacts as $id) {
            if (!in_array($id, $catalogIds, true)) {
                $report->addMessage('error', 'MISSING_FILE', "Artifact '{$id}' is listed in manifest but missing in catalog");
            }
        }

        foreach ($catalogIds as $id) {
            if ($id !== '' && !in_array($id, $manifestArtifacts, true)) {
                $report->addMessage('warning', 'MISSING_FILE', "Artifact '{$id}' is missing from manifest.artifacts");
            }
        }

        $selectedIds = $manifestArtifacts;
        if ($profile !== null) {
            $profileResult = $this->resolveProfileSelection($manifest, $profile, $report);
            $selectedIds = $profileResult['selected'];
            $usageError = $profileResult['usageError'];
        }

        $ndjsonMap = [
            'application/vnd.sitepack.entity-graph+ndjson' => 'entity',
            'application/vnd.sitepack.asset-index+ndjson' => 'asset-index',
            'application/vnd.sitepack.config-kv+ndjson' => 'config-kv',
            'application/vnd.sitepack.recordset+ndjson' => 'recordset',
        ];

        $jsonMap = [
            'application/vnd.sitepack.capabilities+json' => 'capabilities',
            'application/vnd.sitepack.transform-plan+json' => 'transform-plan',
        ];

        foreach ($catalogArtifacts as $artifact) {
            $artifactId = is_string($artifact->id ?? null) ? $artifact->id : '';
            $artifactEntry = new ArtifactResult(
                $artifactId,
                is_string($artifact->mediaType ?? null) ? $artifact->mediaType : null,
                is_string($artifact->path ?? null) ? $artifact->path : null,
                is_int($artifact->size ?? null) ? $artifact->size : null,
                is_string($artifact->digest ?? null) ? $artifact->digest : null
            );

            if ($profile !== null && !in_array($artifactId, $selectedIds, true)) {
                $artifactEntry->status = 'skipped';
                $report->incrementArtifactsSkipped();
                $report->addArtifact($artifactEntry);
                continue;
            }

            $report->incrementArtifactsValidated();

            if (!is_string($artifact->path ?? null) || $artifact->path === '') {
                $artifactEntry->addDetail('error', 'INVALID_PATH', 'Missing path');
                $report->incrementError();
                $artifactEntry->finalizeStatus();
                $report->addArtifact($artifactEntry);
                continue;
            }

            $safePath = $this->safePath->resolve($packageRoot, $artifact->path);
            if (!$safePath['ok'] || $safePath['path'] === null) {
                $artifactEntry->addDetail('error', 'INVALID_PATH', $safePath['message'] ?? 'Invalid path');
                $report->incrementError();
                $artifactEntry->finalizeStatus();
                $report->addArtifact($artifactEntry);
                continue;
            }

            if (!is_file($safePath['path'])) {
                $artifactEntry->addDetail('error', 'MISSING_FILE', 'Artifact file not found');
                $report->incrementError();
                $artifactEntry->finalizeStatus();
                $report->addArtifact($artifactEntry);
                continue;
            }

            $sizeActual = filesize($safePath['path']);
            $artifactEntry->sizeActual = is_int($sizeActual) ? $sizeActual : null;

            if (is_int($artifact->size ?? null) && $artifactEntry->sizeActual !== $artifact->size) {
                $artifactEntry->addDetail(
                    'error',
                    'SIZE_MISMATCH',
                    'File size mismatch: ' . (string) $artifactEntry->sizeActual . ' != ' . (string) $artifact->size
                );
                $report->incrementError();
            }

            if (!$skipDigest && is_string($artifact->digest ?? null) && $artifact->digest !== '') {
                try {
                    $artifactEntry->digestActual = $this->digestCalculator->computeSha256($safePath['path']);
                    if ($artifactEntry->digestActual !== $artifact->digest) {
                        $artifactEntry->addDetail(
                            'error',
                            'DIGEST_MISMATCH',
                            'Digest mismatch: ' . $artifactEntry->digestActual . ' != ' . $artifact->digest
                        );
                        $report->incrementError();
                    }
                } catch (Throwable $exception) {
                    $artifactEntry->addDetail('error', 'DIGEST_MISMATCH', 'Digest calculation error: ' . $exception->getMessage());
                    $report->incrementError();
                }
            }

            $mediaType = is_string($artifact->mediaType ?? null) ? $artifact->mediaType : '';

            if (isset($ndjsonMap[$mediaType])) {
                $schemaName = $ndjsonMap[$mediaType];
                $ndjsonResult = $this->ndjsonValidator->validateFile(
                    $safePath['path'],
                    $schemaName,
                    true,
                    $this->buildAssetBlobChecker($packageRoot, $checkAssetBlobs, $mediaType)
                );

                $report->incrementNdjsonLinesValidated($ndjsonResult['linesValidated']);
                foreach ($ndjsonResult['details'] as $detail) {
                    $artifactEntry->addDetail(
                        $detail['level'],
                        $detail['code'],
                        $detail['message'],
                        $detail['line']
                    );
                    $this->incrementReportLevel($report, $detail['level']);
                }
            } elseif (isset($jsonMap[$mediaType])) {
                $jsonResult = $this->fileUtil->readJsonFile($safePath['path']);
                if (!$jsonResult['ok'] || $jsonResult['data'] === null) {
                    $artifactEntry->addDetail('error', 'INVALID_JSON', 'Failed to read JSON artifact');
                    $report->incrementError();
                } else {
                    $validation = $this->schemaValidator->validate($jsonMap[$mediaType], $jsonResult['data']);
                    if (!$validation['valid']) {
                        foreach ($validation['errors'] as $message) {
                            $artifactEntry->addDetail('error', 'SCHEMA_VALIDATION_FAILED', $message);
                            $report->incrementError();
                        }
                    }
                }
            } else {
                $artifactEntry->addDetail('warning', 'UNKNOWN_MEDIA_TYPE', 'Unknown mediaType: ' . $mediaType);
                $report->incrementWarning();
            }

            $artifactEntry->finalizeStatus();
            $report->addArtifact($artifactEntry);
        }

        $report->markFinished();
        $reportPath = $this->reportWriter->write($report, $packageRoot);

        return [
            'report' => $report,
            'reportPath' => $reportPath,
            'usageError' => $usageError,
        ];
    }

    /**
     * @param string $path
     * @param string $schemaName
     * @param ValidationReport $report
     * @return object|null
     */
    private function loadJson(string $path, string $schemaName, ValidationReport $report): ?object
    {
        if (!is_file($path)) {
            $report->addMessage('error', 'MISSING_FILE', 'File not found: ' . $path);
            return null;
        }

        $jsonResult = $this->fileUtil->readJsonFile($path);
        if (!$jsonResult['ok'] || $jsonResult['data'] === null) {
            $report->addMessage('error', 'INVALID_JSON', 'Failed to read JSON: ' . ($jsonResult['error'] ?? ''));
            return null;
        }

        $validation = $this->schemaValidator->validate($schemaName, $jsonResult['data']);
        if (!$validation['valid']) {
            foreach ($validation['errors'] as $message) {
                $report->addMessage('error', 'SCHEMA_VALIDATION_FAILED', $message);
            }
        }

        return $jsonResult['data'];
    }

    /**
     * @param object|null $manifest
     * @param string $profile
     * @param ValidationReport $report
     * @return array{selected:array<int, string>,usageError:bool}
     */
    private function resolveProfileSelection(?object $manifest, string $profile, ValidationReport $report): array
    {
        if ($manifest === null || !isset($manifest->profiles)) {
            $report->addMessage('error', 'PROFILE_NOT_FOUND', 'Profile not found in manifest.profiles');
            return [
                'selected' => [],
                'usageError' => true,
            ];
        }

        $profiles = $manifest->profiles;

        if (is_array($profiles)) {
            if (!in_array($profile, $profiles, true)) {
                $report->addMessage('error', 'PROFILE_NOT_FOUND', "Profile '{$profile}' is missing from manifest.profiles");
                return [
                    'selected' => [],
                    'usageError' => true,
                ];
            }

            $selected = [];
            if (isset($manifest->artifacts) && is_array($manifest->artifacts)) {
                foreach ($manifest->artifacts as $id) {
                    if (is_string($id)) {
                        $selected[] = $id;
                    }
                }
            }

            return [
                'selected' => $selected,
                'usageError' => false,
            ];
        }

        if (is_object($profiles)) {
            if (!property_exists($profiles, $profile)) {
                $report->addMessage('error', 'PROFILE_NOT_FOUND', "Profile '{$profile}' is missing from manifest.profiles map");
                return [
                    'selected' => [],
                    'usageError' => true,
                ];
            }

            $list = $profiles->{$profile};
            if (!is_array($list)) {
                $report->addMessage('error', 'PROFILE_NOT_FOUND', "manifest.profiles['{$profile}'] must be an array");
                return [
                    'selected' => [],
                    'usageError' => true,
                ];
            }

            $selected = [];
            foreach ($list as $id) {
                if (is_string($id)) {
                    $selected[] = $id;
                }
            }

            return [
                'selected' => $selected,
                'usageError' => false,
            ];
        }

        $report->addMessage('error', 'PROFILE_NOT_FOUND', 'manifest.profiles has an invalid type');

        return [
            'selected' => [],
            'usageError' => true,
        ];
    }

    /**
     * @param string $packageRoot
     * @param bool $checkAssetBlobs
     * @param string $mediaType
     * @return callable(object, int):array<int, array{level:string,code:string,message:string,line:int|null}>
     */
    private function buildAssetBlobChecker(
        string $packageRoot,
        bool $checkAssetBlobs,
        string $mediaType
    ): callable {
        return function (object $record, int $lineNumber) use ($packageRoot, $checkAssetBlobs, $mediaType): array {
            if (!$checkAssetBlobs || $mediaType !== 'application/vnd.sitepack.asset-index+ndjson') {
                return [];
            }

            if (!property_exists($record, 'path') || !is_string($record->path) || trim($record->path) === '') {
                return [];
            }

            $safe = $this->safePath->resolve($packageRoot, $record->path);
            if (!$safe['ok'] || $safe['path'] === null) {
                return [[
                    'level' => 'warning',
                    'code' => 'INVALID_PATH',
                    'message' => 'Unsafe asset blob path: ' . $record->path,
                    'line' => $lineNumber,
                ]];
            }

            if (!is_file($safe['path'])) {
                return [[
                    'level' => 'warning',
                    'code' => 'MISSING_ASSET_BLOB',
                    'message' => 'Asset blob file not found: ' . $record->path,
                    'line' => $lineNumber,
                ]];
            }

            return [];
        };
    }

    /**
     * @param ValidationReport $report
     * @param string $level
     * @return void
     */
    private function incrementReportLevel(ValidationReport $report, string $level): void
    {
        if ($level === 'error') {
            $report->incrementError();
            return;
        }

        if ($level === 'warning') {
            $report->incrementWarning();
        }
    }
}
