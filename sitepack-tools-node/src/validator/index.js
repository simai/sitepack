const fs = require('fs/promises');
const fsSync = require('fs');
const path = require('path');
const os = require('os');
const unzipper = require('unzipper');
const { createReport, finalizeReport, writeReport } = require('./report');
const { loadSchemas } = require('./schema-loader');
const { validateWithSchema } = require('./json-validate');
const { validateNdjson } = require('./ndjson-validate');
const { computeSha256, computeSha256Hex, computeSha256HexFromFiles } = require('./digest');
const { resolveSafePath } = require('./path-safe');

function addMessage(report, level, code, message, context = {}) {
  report.messages.push({ level, code, message, context });
  if (level === 'error') {
    report.summary.errors += 1;
  } else if (level === 'warning') {
    report.summary.warnings += 1;
  }
}

function addArtifactDetail(report, artifact, level, code, message, line) {
  artifact.details.push({ level, code, message, line });
  if (level === 'error') {
    report.summary.errors += 1;
  } else if (level === 'warning') {
    report.summary.warnings += 1;
  }
}

function finalizeArtifactStatus(artifact) {
  if (artifact.status === 'skipped') {
    return artifact;
  }
  const hasError = artifact.details.some((d) => d.level === 'error');
  const hasWarning = artifact.details.some((d) => d.level === 'warning');
  if (hasError) {
    artifact.status = 'error';
  } else if (hasWarning) {
    artifact.status = 'warning';
  } else {
    artifact.status = 'ok';
  }
  return artifact;
}

async function readJsonFile(filePath) {
  try {
    const raw = await fs.readFile(filePath, 'utf8');
    return { ok: true, data: JSON.parse(raw) };
  } catch (err) {
    return { ok: false, error: err };
  }
}

function selectArtifactsForProfile(manifest, profile, report) {
  const manifestArtifacts = Array.isArray(manifest?.artifacts) ? manifest.artifacts : [];
  if (!profile) {
    return { selectedIds: new Set(manifestArtifacts), usedProfileMap: false };
  }

  if (Array.isArray(manifest?.profiles)) {
    if (!manifest.profiles.includes(profile)) {
      addMessage(
        report,
        'error',
        'PROFILE_NOT_DECLARED',
        `Profile '${profile}' is not listed in manifest.profiles`,
        { profile }
      );
    }
    return { selectedIds: new Set(manifestArtifacts), usedProfileMap: false };
  }

  if (manifest?.profiles && typeof manifest.profiles === 'object') {
    const list = manifest.profiles[profile];
    if (Array.isArray(list)) {
      return { selectedIds: new Set(list), usedProfileMap: true };
    }
    addMessage(
      report,
      'error',
      'PROFILE_MAP_INVALID',
      `manifest.profiles['${profile}'] must be an array of artifact.id`,
      { profile }
    );
    return { selectedIds: new Set(manifestArtifacts), usedProfileMap: false };
  }

  addMessage(
    report,
    'error',
    'PROFILE_FIELD_INVALID',
    'manifest.profiles has an invalid type',
    { profile }
  );
  return { selectedIds: new Set(manifestArtifacts), usedProfileMap: false };
}

async function validateAssetIndexRecord(record, lineNumber, options) {
  const { packageRoot, checkAssetBlobs, mediaType } = options;
  const details = [];

  if (!checkAssetBlobs || mediaType !== 'application/vnd.sitepack.asset-index+ndjson') {
    return details;
  }

  if (!record || typeof record !== 'object') {
    return details;
  }

  const expectedSha = typeof record.sha256 === 'string' ? record.sha256.toLowerCase() : null;
  const expectedSize = typeof record.size === 'number' ? record.size : null;

  if (Array.isArray(record.chunks)) {
    const chunkEntries = [];
    const seenIndexes = new Set();
    let canAssemble = true;

    for (const chunk of record.chunks) {
      if (!chunk || typeof chunk !== 'object') {
        details.push({
          level: 'error',
          code: 'ASSET_CHUNK_INVALID',
          message: 'Chunk entry must be an object'
        });
        canAssemble = false;
        continue;
      }

      const index = Number(chunk.index);
      if (!Number.isInteger(index) || index < 1) {
        details.push({
          level: 'error',
          code: 'ASSET_CHUNK_INDEX_INVALID',
          message: 'Chunk index must be an integer >= 1'
        });
        canAssemble = false;
        continue;
      }

      if (seenIndexes.has(index)) {
        details.push({
          level: 'error',
          code: 'ASSET_CHUNK_INDEX_DUPLICATE',
          message: `Duplicate chunk index: ${index}`
        });
        canAssemble = false;
        continue;
      }
      seenIndexes.add(index);

      if (typeof chunk.path !== 'string' || chunk.path.trim() === '') {
        details.push({
          level: 'error',
          code: 'ASSET_CHUNK_PATH_MISSING',
          message: 'Chunk path is missing or not a string'
        });
        canAssemble = false;
        continue;
      }

      const safe = resolveSafePath(packageRoot, chunk.path);
      if (!safe.ok) {
        details.push({
          level: 'error',
          code: 'ASSET_CHUNK_PATH_UNSAFE',
          message: `Unsafe chunk path: ${chunk.path}`
        });
        canAssemble = false;
        continue;
      }

      let stat;
      try {
        stat = await fs.stat(safe.resolved);
      } catch (err) {
        details.push({
          level: 'error',
          code: 'ASSET_CHUNK_MISSING',
          message: `Chunk file not found: ${chunk.path}`
        });
        canAssemble = false;
        continue;
      }

      if (!stat.isFile()) {
        details.push({
          level: 'error',
          code: 'ASSET_CHUNK_NOT_REGULAR',
          message: `Chunk path is not a regular file: ${chunk.path}`
        });
        canAssemble = false;
        continue;
      }

      if (typeof chunk.size === 'number' && stat.size !== chunk.size) {
        details.push({
          level: 'error',
          code: 'ASSET_CHUNK_SIZE_MISMATCH',
          message: `Chunk size mismatch: ${stat.size} != ${chunk.size}`
        });
        canAssemble = false;
      }

      if (typeof chunk.sha256 === 'string') {
        try {
          const actualChunkSha = await computeSha256Hex(safe.resolved);
          if (actualChunkSha !== chunk.sha256.toLowerCase()) {
            details.push({
              level: 'error',
              code: 'ASSET_CHUNK_DIGEST_MISMATCH',
              message: `Chunk digest mismatch: ${actualChunkSha} != ${chunk.sha256}`
            });
            canAssemble = false;
          }
        } catch (err) {
          details.push({
            level: 'error',
            code: 'ASSET_CHUNK_DIGEST_ERROR',
            message: `Chunk digest error: ${err.message}`
          });
          canAssemble = false;
        }
      }

      chunkEntries.push({ index, path: safe.resolved, size: stat.size });
    }

    if (chunkEntries.length > 0 && canAssemble) {
      chunkEntries.sort((a, b) => a.index - b.index);

      let expectedIndex = 1;
      for (const entry of chunkEntries) {
        if (entry.index !== expectedIndex) {
          details.push({
            level: 'error',
            code: 'ASSET_CHUNK_INDEX_GAP',
            message: `Chunk index gap: expected ${expectedIndex}, got ${entry.index}`
          });
          canAssemble = false;
          break;
        }
        expectedIndex += 1;
      }
    }

    if (chunkEntries.length > 0 && canAssemble) {
      const totalSize = chunkEntries.reduce((sum, entry) => sum + entry.size, 0);

      if (expectedSize !== null && totalSize !== expectedSize) {
        details.push({
          level: 'error',
          code: 'ASSET_SIZE_MISMATCH',
          message: `Asset size mismatch: ${totalSize} != ${expectedSize}`
        });
      }

      if (expectedSha) {
        try {
          const actualSha = await computeSha256HexFromFiles(chunkEntries.map((entry) => entry.path));
          if (actualSha !== expectedSha) {
            details.push({
              level: 'error',
              code: 'ASSET_DIGEST_MISMATCH',
              message: `Asset digest mismatch: ${actualSha} != ${expectedSha}`
            });
          }
        } catch (err) {
          details.push({
            level: 'error',
            code: 'ASSET_DIGEST_ERROR',
            message: `Asset digest error: ${err.message}`
          });
        }
      }
    }

    return details;
  }

  if (typeof record.path !== 'string' || record.path.trim() === '') {
    return details;
  }

  const safe = resolveSafePath(packageRoot, record.path);
  if (!safe.ok) {
    details.push({
      level: 'error',
      code: 'ASSET_BLOB_PATH_UNSAFE',
      message: `Unsafe asset blob path: ${record.path}`
    });
    return details;
  }

  let stat;
  try {
    stat = await fs.stat(safe.resolved);
  } catch (err) {
    details.push({
      level: 'error',
      code: 'ASSET_BLOB_MISSING',
      message: `Asset blob file not found: ${record.path}`
    });
    return details;
  }

  if (!stat.isFile()) {
    details.push({
      level: 'error',
      code: 'ASSET_BLOB_NOT_REGULAR',
      message: `Asset blob path is not a regular file: ${record.path}`
    });
    return details;
  }

  if (expectedSize !== null && stat.size !== expectedSize) {
    details.push({
      level: 'error',
      code: 'ASSET_BLOB_SIZE_MISMATCH',
      message: `Asset blob size mismatch: ${stat.size} != ${expectedSize}`
    });
  }

  if (expectedSha) {
    try {
      const actualSha = await computeSha256Hex(safe.resolved);
      if (actualSha !== expectedSha) {
        details.push({
          level: 'error',
          code: 'ASSET_BLOB_DIGEST_MISMATCH',
          message: `Asset blob digest mismatch: ${actualSha} != ${expectedSha}`
        });
      }
    } catch (err) {
      details.push({
        level: 'error',
        code: 'ASSET_BLOB_DIGEST_ERROR',
        message: `Asset blob digest error: ${err.message}`
      });
    }
  }

  return details;
}

async function validateObjectsLayer(options) {
  const {
    packageRoot,
    catalogArtifacts,
    validators,
    report,
    validatedIds
  } = options;

  const objectIndexArtifacts = catalogArtifacts.filter(
    (artifact) => artifact.mediaType === 'application/vnd.sitepack.object-index+json'
  );

  if (objectIndexArtifacts.length === 0) {
    return;
  }

  const artifactIds = new Set();
  const artifactByPath = new Map();
  for (const artifact of catalogArtifacts) {
    if (typeof artifact.id === 'string') {
      artifactIds.add(artifact.id);
    }
    if (typeof artifact.path === 'string') {
      artifactByPath.set(artifact.path, artifact);
    }
  }

  for (const artifact of objectIndexArtifacts) {
    if (typeof artifact.path !== 'string' || artifact.path.trim() === '') {
      addMessage(report, 'error', 'OBJECT_INDEX_PATH_MISSING', 'Object index path is missing');
      continue;
    }

    const safeIndexPath = resolveSafePath(packageRoot, artifact.path);
    if (!safeIndexPath.ok) {
      addMessage(report, 'error', safeIndexPath.code, safeIndexPath.message, {
        path: artifact.path
      });
      continue;
    }

    if (!fsSync.existsSync(safeIndexPath.resolved)) {
      addMessage(report, 'error', 'OBJECT_INDEX_MISSING', 'Object index file not found', {
        path: artifact.path
      });
      continue;
    }

    const indexResult = await readJsonFile(safeIndexPath.resolved);
    if (!indexResult.ok) {
      addMessage(report, 'error', 'OBJECT_INDEX_PARSE_ERROR', `Failed to read object index: ${indexResult.error.message}`, {
        path: artifact.path
      });
      continue;
    }

    if (!validatedIds.has(artifact.id)) {
      const validation = validateWithSchema(validators.objectIndex, indexResult.data);
      if (!validation.valid) {
        for (const err of validation.errors) {
          addMessage(report, 'error', 'OBJECT_INDEX_SCHEMA_ERROR', err.message, {
            path: artifact.path,
            schemaPath: err.schemaPath
          });
        }
      }
    }

    const objects = Array.isArray(indexResult.data?.objects) ? indexResult.data.objects : [];
    for (const obj of objects) {
      if (!obj || typeof obj !== 'object') {
        addMessage(report, 'error', 'OBJECT_INDEX_ENTRY_INVALID', 'Object index entry must be an object');
        continue;
      }

      const objectId = typeof obj.id === 'string' ? obj.id : '';
      const passportPath = typeof obj.passportPath === 'string' ? obj.passportPath : '';

      if (objectId.trim() === '') {
        addMessage(report, 'error', 'OBJECT_INDEX_ENTRY_INVALID', 'Object id is missing or invalid');
        continue;
      }

      if (passportPath.trim() === '') {
        addMessage(report, 'error', 'OBJECT_PASSPORT_PATH_MISSING', `passportPath is missing for object '${objectId}'`);
        continue;
      }

      const passportArtifact = artifactByPath.get(passportPath);
      if (!passportArtifact) {
        addMessage(
          report,
          'error',
          'OBJECT_PASSPORT_NOT_IN_CATALOG',
          `passportPath is not listed in catalog: ${passportPath}`,
          { objectId, passportPath }
        );
      }

      const safePassportPath = resolveSafePath(packageRoot, passportPath);
      if (!safePassportPath.ok) {
        addMessage(report, 'error', safePassportPath.code, safePassportPath.message, {
          objectId,
          passportPath
        });
        continue;
      }

      if (!fsSync.existsSync(safePassportPath.resolved)) {
        addMessage(report, 'error', 'OBJECT_PASSPORT_MISSING', 'Object passport file not found', {
          objectId,
          passportPath
        });
        continue;
      }

      const passportResult = await readJsonFile(safePassportPath.resolved);
      if (!passportResult.ok) {
        addMessage(
          report,
          'error',
          'OBJECT_PASSPORT_PARSE_ERROR',
          `Failed to read object passport: ${passportResult.error.message}`,
          { objectId, passportPath }
        );
        continue;
      }

      if (!validatedIds.has(passportArtifact?.id)) {
        const validation = validateWithSchema(validators.objectPassport, passportResult.data);
        if (!validation.valid) {
          for (const err of validation.errors) {
            addMessage(report, 'error', 'OBJECT_PASSPORT_SCHEMA_ERROR', err.message, {
              objectId,
              passportPath,
              schemaPath: err.schemaPath
            });
          }
        }
      }

      const passportId = typeof passportResult.data?.id === 'string' ? passportResult.data.id : null;
      const objectRefId = typeof passportResult.data?.objectRef?.id === 'string' ? passportResult.data.objectRef.id : null;

      if (passportId && passportId !== objectId) {
        addMessage(report, 'error', 'OBJECT_PASSPORT_ID_MISMATCH', `Passport id does not match object index id: ${passportId} != ${objectId}`, {
          objectId,
          passportId
        });
      }

      if (passportId && objectRefId && passportId !== objectRefId) {
        addMessage(
          report,
          'error',
          'OBJECT_PASSPORT_ID_MISMATCH',
          `Passport id does not match objectRef.id: ${passportId} != ${objectRefId}`,
          { objectId, passportId, objectRefId }
        );
      }

      if (objectRefId && objectRefId !== objectId) {
        addMessage(
          report,
          'error',
          'OBJECT_PASSPORT_REF_MISMATCH',
          `objectRef.id does not match object index id: ${objectRefId} != ${objectId}`,
          { objectId, objectRefId }
        );
      }

      const passportArtifacts = Array.isArray(passportResult.data?.artifacts) ? passportResult.data.artifacts : [];
      for (const artifactId of passportArtifacts) {
        if (typeof artifactId !== 'string' || artifactId.trim() === '') {
          addMessage(report, 'error', 'OBJECT_PASSPORT_ARTIFACT_INVALID', 'Passport artifacts entry must be a string', {
            objectId
          });
          continue;
        }
        if (!artifactIds.has(artifactId)) {
          addMessage(
            report,
            'error',
            'OBJECT_PASSPORT_ARTIFACT_MISSING',
            `Passport artifact is missing from catalog: ${artifactId}`,
            { objectId, artifactId }
          );
        }
      }

      const datasets = Array.isArray(passportResult.data?.datasets) ? passportResult.data.datasets : [];
      for (const selector of datasets) {
        if (!selector || typeof selector !== 'object') {
          addMessage(report, 'error', 'OBJECT_PASSPORT_DATASET_INVALID', 'Dataset selector must be an object', {
            objectId
          });
          continue;
        }
        const artifactId = typeof selector.artifactId === 'string' ? selector.artifactId : '';
        if (artifactId.trim() === '') {
          addMessage(report, 'error', 'OBJECT_PASSPORT_DATASET_INVALID', 'datasetSelector.artifactId is missing', {
            objectId
          });
          continue;
        }
        if (!artifactIds.has(artifactId)) {
          addMessage(
            report,
            'error',
            'OBJECT_PASSPORT_DATASET_MISSING',
            `Dataset artifact is missing from catalog: ${artifactId}`,
            { objectId, artifactId }
          );
        }
      }
    }
  }
}

async function extractZip(zipPath, destDir, report) {
  const directory = await unzipper.Open.file(zipPath);

  for (const entry of directory.files) {
    const safe = resolveSafePath(destDir, entry.path);
    if (!safe.ok) {
      addMessage(report, 'error', 'VOLUME_ENTRY_PATH_UNSAFE', `Unsafe entry path: ${entry.path}`, {
        entryPath: entry.path,
        volume: zipPath
      });
      entry.autodrain();
      continue;
    }

    if (entry.type === 'Directory') {
      await fs.mkdir(safe.resolved, { recursive: true });
      continue;
    }

    await fs.mkdir(path.dirname(safe.resolved), { recursive: true });
    await new Promise((resolve, reject) => {
      entry
        .stream()
        .pipe(fsSync.createWriteStream(safe.resolved))
        .on('finish', resolve)
        .on('error', reject);
    });
  }
}

async function validatePackage(options) {
  const {
    packageRoot,
    schemasDir,
    profile,
    noDigest,
    checkAssetBlobs,
    toolName,
    toolVersion
  } = options;

  const report = createReport({
    toolName,
    toolVersion,
    targetType: 'package',
    targetPath: packageRoot
  });

  const { validators } = loadSchemas(schemasDir);

  const manifestPath = path.join(packageRoot, 'sitepack.manifest.json');
  const catalogPath = path.join(packageRoot, 'sitepack.catalog.json');

  let manifest = null;
  let catalog = null;

  if (!fsSync.existsSync(manifestPath)) {
    addMessage(report, 'error', 'MANIFEST_MISSING', 'sitepack.manifest.json not found', {
      path: manifestPath
    });
  } else {
    const manifestResult = await readJsonFile(manifestPath);
    if (!manifestResult.ok) {
      addMessage(report, 'error', 'MANIFEST_PARSE_ERROR', `Failed to read manifest: ${manifestResult.error.message}`, {
        path: manifestPath
      });
    } else {
      manifest = manifestResult.data;
      const validation = validateWithSchema(validators.manifest, manifest);
      if (!validation.valid) {
        for (const err of validation.errors) {
          addMessage(report, 'error', 'MANIFEST_SCHEMA_ERROR', err.message, {
            path: manifestPath,
            schemaPath: err.schemaPath
          });
        }
      }
    }
  }

  if (!fsSync.existsSync(catalogPath)) {
    addMessage(report, 'error', 'CATALOG_MISSING', 'sitepack.catalog.json not found', {
      path: catalogPath
    });
  } else {
    const catalogResult = await readJsonFile(catalogPath);
    if (!catalogResult.ok) {
      addMessage(report, 'error', 'CATALOG_PARSE_ERROR', `Failed to read catalog: ${catalogResult.error.message}`, {
        path: catalogPath
      });
    } else {
      catalog = catalogResult.data;
      const validation = validateWithSchema(validators.catalog, catalog);
      if (!validation.valid) {
        for (const err of validation.errors) {
          addMessage(report, 'error', 'CATALOG_SCHEMA_ERROR', err.message, {
            path: catalogPath,
            schemaPath: err.schemaPath
          });
        }
      }
    }
  }

  const catalogArtifacts = Array.isArray(catalog?.artifacts) ? catalog.artifacts : [];
  report.summary.artifactsTotal = catalogArtifacts.length;

  const manifestArtifacts = Array.isArray(manifest?.artifacts) ? manifest.artifacts : [];
  const catalogIds = new Set(catalogArtifacts.map((item) => item.id));
  const manifestIds = new Set(manifestArtifacts);

  for (const id of manifestArtifacts) {
    if (!catalogIds.has(id)) {
      addMessage(report, 'error', 'MANIFEST_ARTIFACT_MISSING', `Artifact '${id}' is listed in manifest but missing in catalog`, {
        artifactId: id
      });
    }
  }

  for (const art of catalogArtifacts) {
    if (!manifestIds.has(art.id)) {
      addMessage(report, 'warning', 'CATALOG_ARTIFACT_EXTRA', `Artifact '${art.id}' is missing from manifest.artifacts`, {
        artifactId: art.id
      });
    }
  }

  let selectedIds = new Set(catalogArtifacts.map((item) => item.id));
  if (profile) {
    if (!manifest) {
      addMessage(report, 'error', 'PROFILE_NO_MANIFEST', 'Cannot apply profile filter without manifest', {
        profile
      });
    } else {
      const selection = selectArtifactsForProfile(manifest, profile, report);
      selectedIds = selection.selectedIds;
      if (selection.usedProfileMap) {
        for (const id of selectedIds) {
          if (!catalogIds.has(id)) {
            addMessage(report, 'error', 'PROFILE_ARTIFACT_MISSING', `Artifact '${id}' is missing in catalog for profile '${profile}'`, {
              artifactId: id,
              profile
            });
          }
        }
      }
    }
  }

  const ndjsonValidators = {
    'application/vnd.sitepack.entity-graph+ndjson': validators.entity,
    'application/vnd.sitepack.asset-index+ndjson': validators.assetIndex,
    'application/vnd.sitepack.config-kv+ndjson': validators.configKv,
    'application/vnd.sitepack.recordset+ndjson': validators.recordset
  };

  const jsonValidators = {
    'application/vnd.sitepack.capabilities+json': validators.capabilities,
    'application/vnd.sitepack.transform-plan+json': validators.transformPlan,
    'application/vnd.sitepack.object-index+json': validators.objectIndex,
    'application/vnd.sitepack.object-passport+json': validators.objectPassport
  };

  const validatedIds = new Set();

  for (const art of catalogArtifacts) {
    const artifactEntry = {
      id: art.id,
      mediaType: art.mediaType || null,
      path: art.path || null,
      sizeExpected: typeof art.size === 'number' ? art.size : null,
      sizeActual: null,
      digestExpected: art.digest || null,
      digestActual: null,
      status: 'ok',
      details: []
    };

    if (profile && !selectedIds.has(art.id)) {
      artifactEntry.status = 'skipped';
      report.summary.artifactsSkipped += 1;
      report.artifacts.push(artifactEntry);
      continue;
    }

    report.summary.artifactsValidated += 1;
    validatedIds.add(art.id);

    const safePath = resolveSafePath(packageRoot, art.path);
    if (!safePath.ok) {
      addArtifactDetail(report, artifactEntry, 'error', safePath.code, safePath.message);
      finalizeArtifactStatus(artifactEntry);
      report.artifacts.push(artifactEntry);
      continue;
    }

    let stat;
    try {
      stat = await fs.stat(safePath.resolved);
    } catch (err) {
      addArtifactDetail(
        report,
        artifactEntry,
        'error',
        'FILE_MISSING',
        `Artifact file not found: ${art.path}`
      );
      finalizeArtifactStatus(artifactEntry);
      report.artifacts.push(artifactEntry);
      continue;
    }

    if (!stat.isFile()) {
      addArtifactDetail(report, artifactEntry, 'error', 'FILE_NOT_REGULAR', 'Artifact is not a regular file');
    }

    artifactEntry.sizeActual = stat.size;
    if (typeof art.size === 'number' && stat.size !== art.size) {
      addArtifactDetail(
        report,
        artifactEntry,
        'error',
        'SIZE_MISMATCH',
        `File size mismatch: ${stat.size} != ${art.size}`
      );
    }

    if (art.digest && !noDigest) {
      try {
        artifactEntry.digestActual = await computeSha256(safePath.resolved);
        if (artifactEntry.digestActual !== art.digest) {
          addArtifactDetail(
            report,
            artifactEntry,
            'error',
            'DIGEST_MISMATCH',
            `Digest mismatch: ${artifactEntry.digestActual} != ${art.digest}`
          );
        }
      } catch (err) {
        addArtifactDetail(report, artifactEntry, 'error', 'DIGEST_ERROR', `Digest calculation error: ${err.message}`);
      }
    }

    const ndjsonValidator = ndjsonValidators[art.mediaType];
    const jsonValidator = jsonValidators[art.mediaType];

    if (ndjsonValidator) {
      const ndjsonResult = await validateNdjson(safePath.resolved, ndjsonValidator, {
        onRecord: (record, line) =>
          validateAssetIndexRecord(record, line, {
            packageRoot,
            checkAssetBlobs,
            mediaType: art.mediaType
          })
      });

      report.summary.ndjsonLinesValidated += ndjsonResult.linesValidated;
      for (const detail of ndjsonResult.details) {
        addArtifactDetail(report, artifactEntry, detail.level, detail.code, detail.message, detail.line);
      }
    } else if (jsonValidator) {
      const jsonResult = await readJsonFile(safePath.resolved);
      if (!jsonResult.ok) {
        addArtifactDetail(
          report,
          artifactEntry,
          'error',
          'JSON_ARTIFACT_PARSE_ERROR',
          `Failed to read JSON artifact: ${jsonResult.error.message}`
        );
      } else {
        const validation = validateWithSchema(jsonValidator, jsonResult.data);
        if (!validation.valid) {
          for (const err of validation.errors) {
            addArtifactDetail(
              report,
              artifactEntry,
              'error',
              'JSON_ARTIFACT_SCHEMA_ERROR',
              err.message
            );
          }
        }
      }
    } else {
      addArtifactDetail(
        report,
        artifactEntry,
        'warning',
        'UNKNOWN_MEDIA_TYPE',
        `Unknown mediaType: ${art.mediaType}`
      );
    }

    finalizeArtifactStatus(artifactEntry);
    report.artifacts.push(artifactEntry);
  }

  await validateObjectsLayer({
    packageRoot,
    catalogArtifacts,
    validators,
    report,
    validatedIds
  });

  finalizeReport(report);
  await writeReport(report, packageRoot);
  return report;
}

async function validateEnvelope(options) {
  const {
    encJsonPath,
    schemasDir,
    checkPayloadFile,
    toolName,
    toolVersion
  } = options;

  const report = createReport({
    toolName,
    toolVersion,
    targetType: 'envelope',
    targetPath: encJsonPath
  });

  const { validators } = loadSchemas(schemasDir);

  if (!fsSync.existsSync(encJsonPath)) {
    addMessage(report, 'error', 'ENVELOPE_MISSING', 'Envelope header file not found', { path: encJsonPath });
    finalizeReport(report);
    await writeReport(report, path.dirname(encJsonPath));
    return report;
  }

  const envelopeResult = await readJsonFile(encJsonPath);
  if (!envelopeResult.ok) {
    addMessage(
      report,
      'error',
      'ENVELOPE_PARSE_ERROR',
      `Failed to read envelope: ${envelopeResult.error.message}`,
      { path: encJsonPath }
    );
    finalizeReport(report);
    await writeReport(report, path.dirname(encJsonPath));
    return report;
  }

  const validation = validateWithSchema(validators.envelope, envelopeResult.data);
  if (!validation.valid) {
    for (const err of validation.errors) {
      addMessage(report, 'error', 'ENVELOPE_SCHEMA_ERROR', err.message, {
        path: encJsonPath,
        schemaPath: err.schemaPath
      });
    }
  }

  if (checkPayloadFile) {
    const payloadFile = envelopeResult.data?.payload?.file;
    if (typeof payloadFile !== 'string' || payloadFile.trim() === '') {
      addMessage(report, 'error', 'ENVELOPE_PAYLOAD_FILE_MISSING', 'payload.file is missing or not a string', {
        path: encJsonPath
      });
    } else {
      const safePayload = resolveSafePath(path.dirname(encJsonPath), payloadFile);
      if (!safePayload.ok) {
        addMessage(report, 'error', safePayload.code, safePayload.message, { payloadFile });
      } else if (!fsSync.existsSync(safePayload.resolved)) {
        addMessage(report, 'error', 'ENVELOPE_PAYLOAD_FILE_NOT_FOUND', 'payload.file not found', {
          payloadFile
        });
      }
    }
  }

  finalizeReport(report);
  await writeReport(report, path.dirname(encJsonPath));
  return report;
}

async function validateVolumes(options) {
  const {
    volumesPath,
    schemasDir,
    profile,
    noDigest,
    checkAssetBlobs,
    toolName,
    toolVersion
  } = options;

  const report = createReport({
    toolName,
    toolVersion,
    targetType: 'volume-set',
    targetPath: volumesPath
  });

  const { validators } = loadSchemas(schemasDir);
  const baseDir = path.dirname(volumesPath);

  if (!fsSync.existsSync(volumesPath)) {
    addMessage(report, 'error', 'VOLUME_SET_MISSING', 'Volume set descriptor not found', { path: volumesPath });
    finalizeReport(report);
    await writeReport(report, baseDir);
    return report;
  }

  const volumesResult = await readJsonFile(volumesPath);
  if (!volumesResult.ok) {
    addMessage(report, 'error', 'VOLUME_SET_PARSE_ERROR', `Failed to read volume set: ${volumesResult.error.message}`, {
      path: volumesPath
    });
    finalizeReport(report);
    await writeReport(report, baseDir);
    return report;
  }

  const validation = validateWithSchema(validators.volumeSet, volumesResult.data);
  if (!validation.valid) {
    for (const err of validation.errors) {
      addMessage(report, 'error', 'VOLUME_SET_SCHEMA_ERROR', err.message, {
        path: volumesPath,
        schemaPath: err.schemaPath
      });
    }
  }

  const volumes = Array.isArray(volumesResult.data?.volumes) ? volumesResult.data.volumes : [];
  const volumeEntries = [];

  for (const volume of volumes) {
    if (!volume || typeof volume !== 'object') {
      addMessage(report, 'error', 'VOLUME_ENTRY_INVALID', 'Volume entry must be an object');
      continue;
    }

    if (typeof volume.file !== 'string' || volume.file.trim() === '') {
      addMessage(report, 'error', 'VOLUME_FILE_INVALID', 'Volume file name is missing or invalid');
      continue;
    }

    const safeFile = resolveSafePath(baseDir, volume.file);
    if (!safeFile.ok) {
      addMessage(report, 'error', safeFile.code, safeFile.message, { file: volume.file });
      continue;
    }

    let stat;
    try {
      stat = await fs.stat(safeFile.resolved);
    } catch (err) {
      addMessage(report, 'error', 'VOLUME_FILE_MISSING', `Volume file not found: ${volume.file}`, {
        file: volume.file
      });
      continue;
    }

    if (!stat.isFile()) {
      addMessage(report, 'error', 'VOLUME_FILE_NOT_REGULAR', `Volume is not a regular file: ${volume.file}`, {
        file: volume.file
      });
      continue;
    }

    if (typeof volume.size === 'number' && stat.size !== volume.size) {
      addMessage(report, 'error', 'VOLUME_SIZE_MISMATCH', `Volume size mismatch: ${stat.size} != ${volume.size}`, {
        file: volume.file
      });
    }

    if (typeof volume.sha256 === 'string') {
      try {
        const actualSha = await computeSha256Hex(safeFile.resolved);
        if (actualSha !== volume.sha256.toLowerCase()) {
          addMessage(report, 'error', 'VOLUME_DIGEST_MISMATCH', `Volume digest mismatch: ${actualSha} != ${volume.sha256}`, {
            file: volume.file
          });
        }
      } catch (err) {
        addMessage(report, 'error', 'VOLUME_DIGEST_ERROR', `Volume digest error: ${err.message}`, {
          file: volume.file
        });
      }
    }

    const encryption = volume.encryption;
    if (encryption && encryption.scheme === 'age') {
      if (typeof encryption.envelopeFile !== 'string' || encryption.envelopeFile.trim() === '') {
        addMessage(report, 'error', 'VOLUME_ENVELOPE_MISSING', 'encryption.envelopeFile is required for age volumes', {
          file: volume.file
        });
      } else {
        const safeEnvelope = resolveSafePath(baseDir, encryption.envelopeFile);
        if (!safeEnvelope.ok) {
          addMessage(report, 'error', safeEnvelope.code, safeEnvelope.message, { envelopeFile: encryption.envelopeFile });
        } else if (!fsSync.existsSync(safeEnvelope.resolved)) {
          addMessage(report, 'error', 'VOLUME_ENVELOPE_NOT_FOUND', 'Envelope file not found', {
            envelopeFile: encryption.envelopeFile
          });
        }
      }

      addMessage(
        report,
        'error',
        'VOLUME_ENCRYPTION_UNSUPPORTED',
        'Encrypted volumes are not supported by this validator'
      );
    }

    volumeEntries.push({
      index: Number(volume.index) || 0,
      path: safeFile.resolved,
      file: volume.file
    });
  }

  if (report.summary.errors > 0) {
    finalizeReport(report);
    await writeReport(report, baseDir);
    return report;
  }

  const tempDir = await fs.mkdtemp(path.join(os.tmpdir(), 'sitepack-volumes-'));

  try {
    const ordered = volumeEntries.sort((a, b) => a.index - b.index);
    for (const volume of ordered) {
      try {
        await extractZip(volume.path, tempDir, report);
      } catch (err) {
        addMessage(report, 'error', 'VOLUME_EXTRACT_ERROR', `Failed to extract volume: ${err.message}`, {
          file: volume.file
        });
      }
    }

    if (report.summary.errors > 0) {
      finalizeReport(report);
      await writeReport(report, baseDir);
      return report;
    }

    const packageReport = await validatePackage({
      packageRoot: tempDir,
      schemasDir,
      profile,
      noDigest,
      checkAssetBlobs,
      toolName,
      toolVersion
    });

    for (const msg of report.messages) {
      addMessage(packageReport, msg.level, msg.code, msg.message, msg.context || {});
    }

    packageReport.target = {
      type: 'volume-set',
      path: volumesPath
    };

    finalizeReport(packageReport);
    await writeReport(packageReport, baseDir);
    return packageReport;
  } finally {
    await fs.rm(tempDir, { recursive: true, force: true });
  }
}

module.exports = {
  validatePackage,
  validateEnvelope,
  validateVolumes
};
