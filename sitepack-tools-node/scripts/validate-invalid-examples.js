const path = require('path');
const { spawnSync } = require('child_process');
const fs = require('fs');

const invalidRoot = process.argv[2] || process.env.SITEPACK_SPEC_EXAMPLES_INVALID;

if (!invalidRoot) {
  console.error('Provide a path to examples-invalid/ or set SITEPACK_SPEC_EXAMPLES_INVALID');
  process.exit(2);
}

const resolvedRoot = path.resolve(invalidRoot);
if (!fs.existsSync(resolvedRoot)) {
  console.error(`Path does not exist: ${resolvedRoot}`);
  process.exit(2);
}

const validatorBin = path.resolve(__dirname, '..', 'bin', 'sitepack-validate');
const examples = [
  'objects-missing-passport',
  'objects-passport-id-mismatch',
  'objects-bad-selector-op'
];

for (const name of examples) {
  const target = path.join(resolvedRoot, name);
  const result = spawnSync(process.execPath, [validatorBin, target, '--quiet'], {
    stdio: 'inherit'
  });

  if (result.status === 0) {
    console.error(`Expected validation failure but succeeded: ${name}`);
    process.exit(1);
  }
}

console.log('All invalid examples failed validation as expected.');
