const path = require('path');
const { spawnSync } = require('child_process');
const fs = require('fs');

const examplesRoot = process.argv[2] || process.env.SITEPACK_SPEC_EXAMPLES;

if (!examplesRoot) {
  console.error('Provide a path to examples/ or set SITEPACK_SPEC_EXAMPLES');
  process.exit(2);
}

const resolvedRoot = path.resolve(examplesRoot);
if (!fs.existsSync(resolvedRoot)) {
  console.error(`Path does not exist: ${resolvedRoot}`);
  process.exit(2);
}

const validatorBin = path.resolve(__dirname, '..', 'bin', 'sitepack-validate');
const examples = [
  'hello-world',
  'config-only',
  'content-assets',
  'full',
  'full-code',
  'cross-relations',
  'chunked-assets',
  'objects-two-objects'
];

for (const name of examples) {
  const target = path.join(resolvedRoot, name);
  const result = spawnSync(process.execPath, [validatorBin, target, '--quiet'], {
    stdio: 'inherit'
  });
  if (result.status !== 0) {
    console.error(`Validation failed: ${name}`);
    process.exit(result.status || 1);
  }
}

console.log('All examples validated successfully.');
