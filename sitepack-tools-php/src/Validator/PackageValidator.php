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
            'application/vnd.sitepack.object-index+json' => 'object-index',
            'application/vnd.sitepack.object-passport+json' => 'object-passport',
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

        $this->validateObjectsLayer($packageRoot, $catalogArtifacts, $report);

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
     * @param string $packageRoot
     * @param array<int, object> $catalogArtifacts
     * @param ValidationReport $report
     * @return void
     */
    private function validateObjectsLayer(string $packageRoot, array $catalogArtifacts, ValidationReport $report): void
    {
        $objectIndexArtifacts = [];
        foreach ($catalogArtifacts as $artifact) {
            $mediaType = is_string($artifact->mediaType ?? null) ? $artifact->mediaType : '';
            if ($mediaType === 'application/vnd.sitepack.object-index+json') {
                $objectIndexArtifacts[] = $artifact;
            }
        }

        if (count($objectIndexArtifacts) === 0) {
            return;
        }

        $artifactIds = [];
        $artifactByPath = [];
        foreach ($catalogArtifacts as $artifact) {
            if (is_string($artifact->id ?? null) && $artifact->id !== '') {
                $artifactIds[$artifact->id] = true;
            }
            if (is_string($artifact->path ?? null) && $artifact->path !== '') {
                $artifactByPath[$artifact->path] = $artifact;
            }
        }

        foreach ($objectIndexArtifacts as $artifact) {
            $indexPath = is_string($artifact->path ?? null) ? $artifact->path : '';
            if (trim($indexPath) === '') {
                $report->addMessage('error', 'OBJECT_INDEX_PATH_MISSING', 'Object index path is missing');
                continue;
            }

            $safeIndex = $this->safePath->resolve($packageRoot, $indexPath);
            if (!$safeIndex['ok'] || $safeIndex['path'] === null) {
                $report->addMessage(
                    'error',
                    $safeIndex['code'] ?? 'INVALID_PATH',
                    $safeIndex['message'] ?? 'Invalid path',
                    ['path' => $indexPath]
                );
                continue;
            }

            if (!is_file($safeIndex['path'])) {
                $report->addMessage(
                    'error',
                    'OBJECT_INDEX_MISSING',
                    'Object index file not found',
                    ['path' => $indexPath]
                );
                continue;
            }

            $indexResult = $this->fileUtil->readJsonFile($safeIndex['path']);
            if (!$indexResult['ok'] || $indexResult['data'] === null) {
                $report->addMessage(
                    'error',
                    'OBJECT_INDEX_PARSE_ERROR',
                    'Failed to read object index: ' . (string) ($indexResult['error'] ?? ''),
                    ['path' => $indexPath]
                );
                continue;
            }

            $indexValidation = $this->schemaValidator->validate('object-index', $indexResult['data']);
            if (!$indexValidation['valid']) {
                foreach ($indexValidation['errors'] as $message) {
                    $report->addMessage(
                        'error',
                        'OBJECT_INDEX_SCHEMA_ERROR',
                        $message,
                        ['path' => $indexPath]
                    );
                }
            }

            $objects = [];
            if (isset($indexResult['data']->objects) && is_array($indexResult['data']->objects)) {
                $objects = $indexResult['data']->objects;
            }

            foreach ($objects as $entry) {
                if (!is_object($entry)) {
                    $report->addMessage(
                        'error',
                        'OBJECT_INDEX_ENTRY_INVALID',
                        'Object index entry must be an object',
                        ['path' => $indexPath]
                    );
                    continue;
                }

                $objectId = is_string($entry->id ?? null) ? $entry->id : '';
                $passportPath = is_string($entry->passportPath ?? null) ? $entry->passportPath : '';

                if (trim($objectId) === '') {
                    $report->addMessage(
                        'error',
                        'OBJECT_INDEX_ENTRY_INVALID',
                        'Object id is missing or invalid',
                        ['path' => $indexPath]
                    );
                    continue;
                }

                if (trim($passportPath) === '') {
                    $report->addMessage(
                        'error',
                        'OBJECT_PASSPORT_PATH_MISSING',
                        "passportPath is missing for object '{$objectId}'",
                        ['objectId' => $objectId]
                    );
                    continue;
                }

                if (!isset($artifactByPath[$passportPath])) {
                    $report->addMessage(
                        'error',
                        'OBJECT_PASSPORT_NOT_IN_CATALOG',
                        'passportPath is not listed in catalog: ' . $passportPath,
                        ['objectId' => $objectId, 'passportPath' => $passportPath]
                    );
                }

                $safePassport = $this->safePath->resolve($packageRoot, $passportPath);
                if (!$safePassport['ok'] || $safePassport['path'] === null) {
                    $report->addMessage(
                        'error',
                        $safePassport['code'] ?? 'INVALID_PATH',
                        $safePassport['message'] ?? 'Invalid path',
                        ['objectId' => $objectId, 'passportPath' => $passportPath]
                    );
                    continue;
                }

                if (!is_file($safePassport['path'])) {
                    $report->addMessage(
                        'error',
                        'OBJECT_PASSPORT_MISSING',
                        'Object passport file not found',
                        ['objectId' => $objectId, 'passportPath' => $passportPath]
                    );
                    continue;
                }

                $passportResult = $this->fileUtil->readJsonFile($safePassport['path']);
                if (!$passportResult['ok'] || $passportResult['data'] === null) {
                    $report->addMessage(
                        'error',
                        'OBJECT_PASSPORT_PARSE_ERROR',
                        'Failed to read object passport: ' . (string) ($passportResult['error'] ?? ''),
                        ['objectId' => $objectId, 'passportPath' => $passportPath]
                    );
                    continue;
                }

                $passportValidation = $this->schemaValidator->validate('object-passport', $passportResult['data']);
                if (!$passportValidation['valid']) {
                    foreach ($passportValidation['errors'] as $message) {
                        $report->addMessage(
                            'error',
                            'OBJECT_PASSPORT_SCHEMA_ERROR',
                            $message,
                            ['objectId' => $objectId, 'passportPath' => $passportPath]
                        );
                    }
                }

                $passportId = is_string($passportResult['data']->id ?? null) ? $passportResult['data']->id : '';
                $objectRefId = '';
                if (is_object($passportResult['data']->objectRef ?? null)) {
                    $objectRefId = is_string($passportResult['data']->objectRef->id ?? null)
                        ? $passportResult['data']->objectRef->id
                        : '';
                }

                if ($passportId !== '' && $passportId !== $objectId) {
                    $report->addMessage(
                        'error',
                        'OBJECT_PASSPORT_ID_MISMATCH',
                        'Passport id does not match object index id: ' . $passportId . ' != ' . $objectId,
                        ['objectId' => $objectId, 'passportId' => $passportId]
                    );
                }

                if ($passportId !== '' && $objectRefId !== '' && $passportId !== $objectRefId) {
                    $report->addMessage(
                        'error',
                        'OBJECT_PASSPORT_ID_MISMATCH',
                        'Passport id does not match objectRef.id: ' . $passportId . ' != ' . $objectRefId,
                        ['objectId' => $objectId, 'passportId' => $passportId, 'objectRefId' => $objectRefId]
                    );
                }

                if ($objectRefId !== '' && $objectRefId !== $objectId) {
                    $report->addMessage(
                        'error',
                        'OBJECT_PASSPORT_REF_MISMATCH',
                        'objectRef.id does not match object index id: ' . $objectRefId . ' != ' . $objectId,
                        ['objectId' => $objectId, 'objectRefId' => $objectRefId]
                    );
                }

                $passportArtifacts = [];
                if (isset($passportResult['data']->artifacts) && is_array($passportResult['data']->artifacts)) {
                    $passportArtifacts = $passportResult['data']->artifacts;
                }

                foreach ($passportArtifacts as $artifactId) {
                    if (!is_string($artifactId) || trim($artifactId) === '') {
                        $report->addMessage(
                            'error',
                            'OBJECT_PASSPORT_ARTIFACT_INVALID',
                            'Passport artifacts entry must be a string',
                            ['objectId' => $objectId]
                        );
                        continue;
                    }

                    if (!isset($artifactIds[$artifactId])) {
                        $report->addMessage(
                            'error',
                            'OBJECT_PASSPORT_ARTIFACT_MISSING',
                            'Passport artifact is missing from catalog: ' . $artifactId,
                            ['objectId' => $objectId, 'artifactId' => $artifactId]
                        );
                    }
                }

                $datasets = [];
                if (isset($passportResult['data']->datasets) && is_array($passportResult['data']->datasets)) {
                    $datasets = $passportResult['data']->datasets;
                }

                foreach ($datasets as $selector) {
                    if (!is_object($selector)) {
                        $report->addMessage(
                            'error',
                            'OBJECT_PASSPORT_DATASET_INVALID',
                            'Dataset selector must be an object',
                            ['objectId' => $objectId]
                        );
                        continue;
                    }

                    $artifactId = is_string($selector->artifactId ?? null) ? $selector->artifactId : '';
                    if (trim($artifactId) === '') {
                        $report->addMessage(
                            'error',
                            'OBJECT_PASSPORT_DATASET_INVALID',
                            'datasetSelector.artifactId is missing',
                            ['objectId' => $objectId]
                        );
                        continue;
                    }

                    if (!isset($artifactIds[$artifactId])) {
                        $report->addMessage(
                            'error',
                            'OBJECT_PASSPORT_DATASET_ARTIFACT_MISSING',
                            'Dataset artifact is missing from catalog: ' . $artifactId,
                            ['objectId' => $objectId, 'artifactId' => $artifactId]
                        );
                    }
                }
            }
        }
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

            $details = [];

            $expectedSha = is_string($record->sha256 ?? null) ? strtolower($record->sha256) : null;
            $expectedSize = is_int($record->size ?? null) ? $record->size : null;

            if (property_exists($record, 'chunks') && is_array($record->chunks)) {
                $chunkEntries = [];
                $seenIndexes = [];
                $canAssemble = true;

                foreach ($record->chunks as $chunk) {
                    if (!is_object($chunk)) {
                        $details[] = [
                            'level' => 'error',
                            'code' => 'ASSET_CHUNK_INVALID',
                            'message' => 'Chunk entry must be an object',
                            'line' => $lineNumber,
                        ];
                        $canAssemble = false;
                        continue;
                    }

                    $index = $chunk->index ?? null;
                    if (!is_int($index) || $index < 1) {
                        $details[] = [
                            'level' => 'error',
                            'code' => 'ASSET_CHUNK_INDEX_INVALID',
                            'message' => 'Chunk index must be an integer >= 1',
                            'line' => $lineNumber,
                        ];
                        $canAssemble = false;
                        continue;
                    }

                    if (in_array($index, $seenIndexes, true)) {
                        $details[] = [
                            'level' => 'error',
                            'code' => 'ASSET_CHUNK_INDEX_DUPLICATE',
                            'message' => 'Duplicate chunk index: ' . (string) $index,
                            'line' => $lineNumber,
                        ];
                        $canAssemble = false;
                        continue;
                    }
                    $seenIndexes[] = $index;

                    if (!is_string($chunk->path ?? null) || trim((string) $chunk->path) === '') {
                        $details[] = [
                            'level' => 'error',
                            'code' => 'ASSET_CHUNK_PATH_MISSING',
                            'message' => 'Chunk path is missing or invalid',
                            'line' => $lineNumber,
                        ];
                        $canAssemble = false;
                        continue;
                    }

                    $safe = $this->safePath->resolve($packageRoot, (string) $chunk->path);
                    if (!$safe['ok'] || $safe['path'] === null) {
                        $details[] = [
                            'level' => 'error',
                            'code' => 'ASSET_CHUNK_PATH_UNSAFE',
                            'message' => 'Unsafe chunk path: ' . (string) $chunk->path,
                            'line' => $lineNumber,
                        ];
                        $canAssemble = false;
                        continue;
                    }

                    if (!is_file($safe['path'])) {
                        $details[] = [
                            'level' => 'error',
                            'code' => 'ASSET_CHUNK_MISSING',
                            'message' => 'Chunk file not found: ' . (string) $chunk->path,
                            'line' => $lineNumber,
                        ];
                        $canAssemble = false;
                        continue;
                    }

                    $actualSize = filesize($safe['path']);
                    if ($actualSize === false) {
                        $details[] = [
                            'level' => 'error',
                            'code' => 'ASSET_CHUNK_SIZE_ERROR',
                            'message' => 'Failed to read chunk size: ' . (string) $chunk->path,
                            'line' => $lineNumber,
                        ];
                        $canAssemble = false;
                        continue;
                    }

                    if (is_int($chunk->size ?? null) && $actualSize !== $chunk->size) {
                        $details[] = [
                            'level' => 'error',
                            'code' => 'ASSET_CHUNK_SIZE_MISMATCH',
                            'message' => 'Chunk size mismatch: ' . (string) $actualSize . ' != ' . (string) $chunk->size,
                            'line' => $lineNumber,
                        ];
                        $canAssemble = false;
                    }

                    if (is_string($chunk->sha256 ?? null)) {
                        try {
                            $actualSha = $this->digestCalculator->computeSha256Hex($safe['path']);
                            if ($actualSha !== strtolower((string) $chunk->sha256)) {
                                $details[] = [
                                    'level' => 'error',
                                    'code' => 'ASSET_CHUNK_DIGEST_MISMATCH',
                                    'message' => 'Chunk digest mismatch: ' . $actualSha . ' != ' . (string) $chunk->sha256,
                                    'line' => $lineNumber,
                                ];
                                $canAssemble = false;
                            }
                        } catch (Throwable $exception) {
                            $details[] = [
                                'level' => 'error',
                                'code' => 'ASSET_CHUNK_DIGEST_ERROR',
                                'message' => 'Chunk digest error: ' . $exception->getMessage(),
                                'line' => $lineNumber,
                            ];
                            $canAssemble = false;
                        }
                    }

                    $chunkEntries[] = [
                        'index' => $index,
                        'path' => $safe['path'],
                        'size' => (int) $actualSize,
                    ];
                }

                if (!empty($chunkEntries) && $canAssemble) {
                    usort(
                        $chunkEntries,
                        static fn (array $left, array $right): int => $left['index'] <=> $right['index']
                    );

                    $totalSize = 0;
                    $paths = [];
                    foreach ($chunkEntries as $entry) {
                        $totalSize += $entry['size'];
                        $paths[] = $entry['path'];
                    }

                    if ($expectedSize !== null && $totalSize !== $expectedSize) {
                        $details[] = [
                            'level' => 'error',
                            'code' => 'ASSET_SIZE_MISMATCH',
                            'message' => 'Asset size mismatch: ' . (string) $totalSize . ' != ' . (string) $expectedSize,
                            'line' => $lineNumber,
                        ];
                    }

                    if ($expectedSha !== null) {
                        try {
                            $actualSha = $this->digestCalculator->computeSha256HexFromFiles($paths);
                            if ($actualSha !== $expectedSha) {
                                $details[] = [
                                    'level' => 'error',
                                    'code' => 'ASSET_DIGEST_MISMATCH',
                                    'message' => 'Asset digest mismatch: ' . $actualSha . ' != ' . $expectedSha,
                                    'line' => $lineNumber,
                                ];
                            }
                        } catch (Throwable $exception) {
                            $details[] = [
                                'level' => 'error',
                                'code' => 'ASSET_DIGEST_ERROR',
                                'message' => 'Asset digest error: ' . $exception->getMessage(),
                                'line' => $lineNumber,
                            ];
                        }
                    }
                }

                return $details;
            }

            if (!is_string($record->path ?? null) || trim((string) $record->path) === '') {
                return $details;
            }

            $safe = $this->safePath->resolve($packageRoot, (string) $record->path);
            if (!$safe['ok'] || $safe['path'] === null) {
                $details[] = [
                    'level' => 'error',
                    'code' => 'ASSET_BLOB_PATH_UNSAFE',
                    'message' => 'Unsafe asset blob path: ' . (string) $record->path,
                    'line' => $lineNumber,
                ];
                return $details;
            }

            if (!is_file($safe['path'])) {
                $details[] = [
                    'level' => 'error',
                    'code' => 'ASSET_BLOB_MISSING',
                    'message' => 'Asset blob file not found: ' . (string) $record->path,
                    'line' => $lineNumber,
                ];
                return $details;
            }

            $actualSize = filesize($safe['path']);
            if ($actualSize === false) {
                $details[] = [
                    'level' => 'error',
                    'code' => 'ASSET_BLOB_SIZE_ERROR',
                    'message' => 'Failed to read asset blob size: ' . (string) $record->path,
                    'line' => $lineNumber,
                ];
                return $details;
            }

            if ($expectedSize !== null && $actualSize !== $expectedSize) {
                $details[] = [
                    'level' => 'error',
                    'code' => 'ASSET_BLOB_SIZE_MISMATCH',
                    'message' => 'Asset blob size mismatch: ' . (string) $actualSize . ' != ' . (string) $expectedSize,
                    'line' => $lineNumber,
                ];
            }

            if ($expectedSha !== null) {
                try {
                    $actualSha = $this->digestCalculator->computeSha256Hex($safe['path']);
                    if ($actualSha !== $expectedSha) {
                        $details[] = [
                            'level' => 'error',
                            'code' => 'ASSET_BLOB_DIGEST_MISMATCH',
                            'message' => 'Asset blob digest mismatch: ' . $actualSha . ' != ' . $expectedSha,
                            'line' => $lineNumber,
                        ];
                    }
                } catch (Throwable $exception) {
                    $details[] = [
                        'level' => 'error',
                        'code' => 'ASSET_BLOB_DIGEST_ERROR',
                        'message' => 'Asset blob digest error: ' . $exception->getMessage(),
                        'line' => $lineNumber,
                    ];
                }
            }

            return $details;
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
