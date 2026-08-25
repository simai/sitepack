const assert = require('assert');
const crypto = require('crypto');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

function digest(buffer) {
  return `sha256:${crypto.createHash('sha256').update(buffer).digest('hex')}`;
}

const root = fs.mkdtempSync(path.join(os.tmpdir(), 'sitepack-volume-naming-'));
const packageDir = path.join(root, 'package');
const outputDir = path.join(root, 'output');
const artifactsDir = path.join(packageDir, 'artifacts');
fs.mkdirSync(artifactsDir, { recursive: true });

try {
  const first = crypto.randomBytes(800);
  const second = crypto.randomBytes(800);
  fs.writeFileSync(path.join(artifactsDir, 'first.bin'), first);
  fs.writeFileSync(path.join(artifactsDir, 'second.bin'), second);

  const manifest = {
    spec: { name: 'sitepack', version: '1.0.0' },
    package: { id: 'naming-test' },
    createdAt: '2026-08-25T11:35:22Z',
    profiles: ['full-snapshot'],
    artifacts: ['first', 'second']
  };
  const catalog = {
    artifacts: [
      { id: 'first', mediaType: 'application/octet-stream', path: 'artifacts/first.bin', size: first.length, digest: digest(first) },
      { id: 'second', mediaType: 'application/octet-stream', path: 'artifacts/second.bin', size: second.length, digest: digest(second) }
    ]
  };
  fs.writeFileSync(path.join(packageDir, 'sitepack.manifest.json'), `${JSON.stringify(manifest)}\n`);
  fs.writeFileSync(path.join(packageDir, 'sitepack.catalog.json'), `${JSON.stringify(catalog)}\n`);

  const cli = path.resolve(__dirname, '..', 'bin', 'sitepack-volumes');
  const result = spawnSync(process.execPath, [
    cli,
    'create',
    packageDir,
    outputDir,
    '--max-part-size', '2000',
    '--site', 'school.obr.site',
    '--created-at', '2026-08-25T11:35:22Z',
    '--mode', 'full'
  ], { encoding: 'utf8' });

  assert.strictEqual(result.status, 0, result.stderr || result.stdout);
  const descriptorPath = path.join(outputDir, 'sitepack.volumes.json');
  const descriptor = JSON.parse(fs.readFileSync(descriptorPath, 'utf8'));
  assert.strictEqual(descriptor.volumes.length, 2);
  assert.deepStrictEqual(
    descriptor.volumes.map((volume) => volume.file),
    [
      'school.obr.site_20260825_113522_full.part-0001-of-0002.sitepack',
      'school.obr.site_20260825_113522_full.part-0002-of-0002.sitepack'
    ]
  );
  for (const volume of descriptor.volumes) {
    assert.ok(fs.existsSync(path.join(outputDir, volume.file)), volume.file);
  }

  const conflicting = spawnSync(process.execPath, [
    cli,
    'create',
    packageDir,
    path.join(root, 'conflicting'),
    '--base-name', 'manual',
    '--site', 'school.obr.site',
    '--created-at', '2026-08-25T11:35:22Z',
    '--mode', 'full'
  ], { encoding: 'utf8' });
  assert.notStrictEqual(conflicting.status, 0);

  console.log('Volume naming integration checks passed.');
} finally {
  fs.rmSync(root, { recursive: true, force: true });
}
