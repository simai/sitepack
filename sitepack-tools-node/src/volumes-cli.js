const fs = require('fs/promises');
const fsSync = require('fs');
const path = require('path');
const readline = require('readline');
const { Command } = require('commander');
const unzipper = require('unzipper');
const yazl = require('yazl');
const { resolveSafePath } = require('./validator/path-safe');
const { computeSha256Hex } = require('./validator/digest');

const DEFAULT_MAX_PART_SIZE = 104857600;
const TOOL_VERSION = '0.4.0';

function normalizeRelPath(relPath) {
  const posixPath = relPath.replace(/\\/g, '/');
  const normalized = path.posix.normalize(posixPath);
  if (normalized.startsWith('./')) {
    return normalized.slice(2);
  }
  return normalized;
}

async function readJson(filePath) {
  const raw = await fs.readFile(filePath, 'utf8');
  return JSON.parse(raw);
}

async function fileSize(filePath) {
  const stat = await fs.stat(filePath);
  if (!stat.isFile()) {
    throw new Error(`Not a regular file: ${filePath}`);
  }
  return stat.size;
}

async function collectAssetIndexPaths(assetIndexPath, packageDir, paths, errors) {
  const safe = resolveSafePath(packageDir, assetIndexPath);
  if (!safe.ok) {
    errors.push(`Unsafe asset-index path: ${assetIndexPath} (${safe.message})`);
    return;
  }

  if (!fsSync.existsSync(safe.resolved)) {
    errors.push(`Asset-index file not found: ${assetIndexPath}`);
    return;
  }

  const input = fsSync.createReadStream(safe.resolved, { encoding: 'utf8' });
  const rl = readline.createInterface({ input, crlfDelay: Infinity });
  let lineNumber = 0;

  for await (const line of rl) {
    lineNumber += 1;
    if (line.trim() === '') {
      continue;
    }

    let record;
    try {
      record = JSON.parse(line);
    } catch (err) {
      errors.push(`Asset-index JSON error in ${assetIndexPath} line ${lineNumber}: ${err.message}`);
      continue;
    }

    if (record && typeof record === 'object') {
      if (typeof record.path === 'string' && record.path.trim() !== '') {
        await addPath(record.path, packageDir, paths, errors);
      }

      if (Array.isArray(record.chunks)) {
        for (const chunk of record.chunks) {
          if (!chunk || typeof chunk !== 'object') {
            continue;
          }
          if (typeof chunk.path === 'string' && chunk.path.trim() !== '') {
            await addPath(chunk.path, packageDir, paths, errors);
          }
        }
      }
    }
  }
}

async function addPath(relPath, packageDir, paths, errors) {
  const safe = resolveSafePath(packageDir, relPath);
  if (!safe.ok) {
    errors.push(`Unsafe path: ${relPath} (${safe.message})`);
    return;
  }

  const normalized = normalizeRelPath(relPath);
  if (!fsSync.existsSync(safe.resolved)) {
    errors.push(`Missing file: ${normalized}`);
    return;
  }

  if (!fsSync.statSync(safe.resolved).isFile()) {
    errors.push(`Not a regular file: ${normalized}`);
    return;
  }

  if (!paths.has(normalized)) {
    paths.set(normalized, safe.resolved);
  }
}

async function collectPackageFiles(packageDir) {
  const manifestPath = path.join(packageDir, 'sitepack.manifest.json');
  const catalogPath = path.join(packageDir, 'sitepack.catalog.json');
  const errors = [];

  if (!fsSync.existsSync(manifestPath)) {
    errors.push('sitepack.manifest.json not found');
  }
  if (!fsSync.existsSync(catalogPath)) {
    errors.push('sitepack.catalog.json not found');
  }
  if (errors.length > 0) {
    return { errors };
  }

  let manifest;
  let catalog;
  try {
    manifest = await readJson(manifestPath);
  } catch (err) {
    errors.push(`Failed to read manifest: ${err.message}`);
  }
  try {
    catalog = await readJson(catalogPath);
  } catch (err) {
    errors.push(`Failed to read catalog: ${err.message}`);
  }

  if (errors.length > 0) {
    return { errors };
  }

  const paths = new Map();
  await addPath('sitepack.manifest.json', packageDir, paths, errors);
  await addPath('sitepack.catalog.json', packageDir, paths, errors);

  const artifacts = Array.isArray(catalog?.artifacts) ? catalog.artifacts : [];
  for (const artifact of artifacts) {
    if (!artifact || typeof artifact !== 'object') {
      continue;
    }
    if (typeof artifact.path === 'string' && artifact.path.trim() !== '') {
      await addPath(artifact.path, packageDir, paths, errors);
    }
  }

  for (const artifact of artifacts) {
    if (!artifact || typeof artifact !== 'object') {
      continue;
    }
    if (artifact.mediaType === 'application/vnd.sitepack.asset-index+ndjson' && typeof artifact.path === 'string') {
      await collectAssetIndexPaths(artifact.path, packageDir, paths, errors);
    }
  }

  return { paths, manifest, catalog, errors };
}

function buildVolumes(entries, maxPartSize) {
  const required = ['sitepack.manifest.json', 'sitepack.catalog.json'];
  const requiredEntries = [];
  const remaining = [];

  for (const entry of entries) {
    if (required.includes(entry.relPath)) {
      requiredEntries.push(entry);
    } else {
      remaining.push(entry);
    }
  }

  const missingRequired = required.filter(
    (name) => !requiredEntries.some((entry) => entry.relPath === name)
  );
  if (missingRequired.length > 0) {
    throw new Error(`Missing required files: ${missingRequired.join(', ')}`);
  }

  const volumes = [];
  let current = [];
  let currentSize = 0;

  const sortedRequired = required.map((name) => requiredEntries.find((entry) => entry.relPath === name));
  for (const entry of sortedRequired) {
    if (!entry) {
      continue;
    }
    current.push(entry);
    currentSize += entry.size;
  }

  if (currentSize > maxPartSize) {
    throw new Error(`Required files exceed maxPartSize (${currentSize} > ${maxPartSize})`);
  }

  const sortedRemaining = remaining.sort((a, b) => a.relPath.localeCompare(b.relPath));
  for (const entry of sortedRemaining) {
    if (entry.size > maxPartSize) {
      throw new Error(`File exceeds maxPartSize: ${entry.relPath} (${entry.size} > ${maxPartSize})`);
    }

    if (currentSize + entry.size > maxPartSize) {
      volumes.push(current);
      current = [];
      currentSize = 0;
    }

    current.push(entry);
    currentSize += entry.size;
  }

  if (current.length > 0) {
    volumes.push(current);
  }

  return volumes;
}

async function writeZip(entries, outPath) {
  await fs.mkdir(path.dirname(outPath), { recursive: true });

  const zipfile = new yazl.ZipFile();
  for (const entry of entries) {
    const zipPath = entry.relPath.replace(/\\/g, '/');
    zipfile.addFile(entry.absPath, zipPath);
  }
  zipfile.end();

  await new Promise((resolve, reject) => {
    const out = fsSync.createWriteStream(outPath);
    zipfile.outputStream.pipe(out).on('close', resolve).on('error', reject);
  });
}

async function createVolumes(packageDir, outDir, options) {
  const maxPartSize = options.maxPartSize;
  const baseName = options.baseName || 'sitepack';
  const overwrite = options.overwrite || false;

  const result = await collectPackageFiles(packageDir);
  if (result.errors && result.errors.length > 0) {
    throw new Error(result.errors.join('\n'));
  }

  const manifest = result.manifest || {};
  const packageId = options.packageId
    || (manifest.package && typeof manifest.package.id === 'string' ? manifest.package.id : null)
    || path.basename(packageDir);

  const entries = [];
  for (const [relPath, absPath] of result.paths.entries()) {
    const size = await fileSize(absPath);
    entries.push({ relPath, absPath, size });
  }

  const volumes = buildVolumes(entries, maxPartSize);

  await fs.mkdir(outDir, { recursive: true });

  const volumeMetadata = [];
  const outputFiles = [];

  for (let i = 0; i < volumes.length; i += 1) {
    const index = i + 1;
    const filename = `${baseName}.part${index}.sitepack`;
    const outputPath = path.join(outDir, filename);
    outputFiles.push(outputPath);

    if (fsSync.existsSync(outputPath)) {
      if (!overwrite) {
        throw new Error(`Output file already exists: ${outputPath}`);
      }
      await fs.unlink(outputPath);
    }

    await writeZip(volumes[i], outputPath);

    const size = await fileSize(outputPath);
    if (size > maxPartSize) {
      throw new Error(`Volume exceeds maxPartSize: ${filename} (${size} > ${maxPartSize})`);
    }

    const sha256 = await computeSha256Hex(outputPath);
    volumeMetadata.push({
      index,
      file: filename,
      size,
      sha256
    });
  }

  const descriptorPath = path.join(outDir, 'sitepack.volumes.json');
  if (fsSync.existsSync(descriptorPath) && !overwrite) {
    throw new Error(`Output file already exists: ${descriptorPath}`);
  }

  const count = volumeMetadata.length;
  const descriptor = {
    spec: {
      name: 'sitepack',
      version: '0.4.0'
    },
    kind: 'volume-set',
    packageId,
    container: 'zip',
    maxPartSize,
    bootstrap: {
      volumeIndex: 1,
      containsManifest: true,
      containsCatalog: true
    },
    volumes: volumeMetadata.map((entry) => ({
      index: entry.index,
      count,
      file: entry.file,
      size: entry.size,
      sha256: entry.sha256
    }))
  };

  await fs.writeFile(descriptorPath, JSON.stringify(descriptor, null, 2) + '\n', 'utf8');

  return {
    descriptorPath,
    volumes: volumeMetadata,
    packageId
  };
}

async function extractZip(zipPath, destDir) {
  const directory = await unzipper.Open.file(zipPath);

  for (const entry of directory.files) {
    const safe = resolveSafePath(destDir, entry.path);
    if (!safe.ok) {
      entry.autodrain();
      throw new Error(`Unsafe entry path: ${entry.path}`);
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

async function extractVolumes(volumesPath, outDir, options) {
  const overwrite = options.overwrite || false;

  if (!fsSync.existsSync(volumesPath)) {
    throw new Error(`Volume set descriptor not found: ${volumesPath}`);
  }

  const volumes = await readJson(volumesPath);
  const entries = Array.isArray(volumes?.volumes) ? volumes.volumes : [];
  if (entries.length === 0) {
    throw new Error('Volume set contains no volumes');
  }

  if (!overwrite && fsSync.existsSync(outDir) && fsSync.readdirSync(outDir).length > 0) {
    throw new Error(`Output directory is not empty: ${outDir}`);
  }

  await fs.mkdir(outDir, { recursive: true });

  const baseDir = path.dirname(volumesPath);
  const ordered = entries
    .map((entry) => ({
      index: Number(entry.index) || 0,
      file: entry.file
    }))
    .sort((a, b) => a.index - b.index);

  for (const entry of ordered) {
    if (typeof entry.file !== 'string' || entry.file.trim() === '') {
      throw new Error('Volume file entry is missing');
    }
    const safe = resolveSafePath(baseDir, entry.file);
    if (!safe.ok) {
      throw new Error(`Unsafe volume file path: ${entry.file}`);
    }
    if (!fsSync.existsSync(safe.resolved)) {
      throw new Error(`Volume file not found: ${entry.file}`);
    }
    await extractZip(safe.resolved, outDir);
  }

  return outDir;
}

async function main() {
  const program = new Command();
  program.name('sitepack-volumes');
  program.description('SitePack volume set builder');

  program
    .command('create <packageDir> <outDir>')
    .description('Create sitepack.volumes.json and volume parts from an unpacked package')
    .option('--max-part-size <bytes>', 'Maximum size per volume in bytes', String(DEFAULT_MAX_PART_SIZE))
    .option('--package-id <id>', 'Override packageId in sitepack.volumes.json')
    .option('--base-name <name>', 'Base filename for volume parts', 'sitepack')
    .option('--overwrite', 'Overwrite existing output files')
    .action(async (packageDir, outDir, opts) => {
      try {
        const maxPartSize = Number.parseInt(opts.maxPartSize, 10);
        if (!Number.isInteger(maxPartSize) || maxPartSize <= 0) {
          throw new Error('max-part-size must be a positive integer');
        }

        const result = await createVolumes(path.resolve(packageDir), path.resolve(outDir), {
          maxPartSize,
          packageId: opts.packageId || null,
          baseName: opts.baseName || 'sitepack',
          overwrite: Boolean(opts.overwrite)
        });

        console.log(`Created volume set for package '${result.packageId}'.`);
        console.log(`Descriptor: ${result.descriptorPath}`);
        for (const volume of result.volumes) {
          console.log(`- ${volume.file} (${volume.size} bytes, sha256 ${volume.sha256})`);
        }
      } catch (err) {
        console.error(err.message || String(err));
        process.exit(1);
      }
    });

  program
    .command('extract <pathToVolumesJson> <outDir>')
    .description('Extract volume files into a directory (ZIP overlay)')
    .option('--overwrite', 'Allow extraction into a non-empty directory')
    .action(async (volumesPath, outDir, opts) => {
      try {
        const output = await extractVolumes(path.resolve(volumesPath), path.resolve(outDir), {
          overwrite: Boolean(opts.overwrite)
        });
        console.log(`Extracted volumes into ${output}`);
      } catch (err) {
        console.error(err.message || String(err));
        process.exit(1);
      }
    });

  program.version(TOOL_VERSION, '-v, --version');
  await program.parseAsync(process.argv);
}

main().catch((err) => {
  console.error(err.message || String(err));
  process.exit(1);
});
