const fs = require('fs/promises');
const fsSync = require('fs');
const path = require('path');
const { createReport, finalizeReport, writeReport } = require('./report');
const { loadSchemas } = require('./schema-loader');
const { validateWithSchema } = require('./json-validate');
const { validateNdjson } = require('./ndjson-validate');
const { computeSha256 } = require('./digest');
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
    'application/vnd.sitepack.transform-plan+json': validators.transformPlan
  };

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
        onRecord: (record, line) => {
          if (!checkAssetBlobs || art.mediaType !== 'application/vnd.sitepack.asset-index+ndjson') {
            return [];
          }
          const details = [];
          if (typeof record.path !== 'string' || record.path.trim() === '') {
            return details;
          }
          const blobPath = resolveSafePath(packageRoot, record.path);
          if (!blobPath.ok) {
            details.push({
              level: 'warning',
              code: 'ASSET_BLOB_PATH_UNSAFE',
              message: `Unsafe asset blob path: ${record.path}`
            });
            return details;
          }
          if (!fsSync.existsSync(blobPath.resolved)) {
            details.push({
              level: 'warning',
              code: 'ASSET_BLOB_MISSING',
              message: `Asset blob file not found: ${record.path}`
            });
          }
          return details;
        }
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

module.exports = {
  validatePackage,
  validateEnvelope
};
