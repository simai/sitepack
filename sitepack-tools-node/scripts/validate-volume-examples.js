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
const volumesPath = path.join(resolvedRoot, 'volume-set-real', 'sitepack.volumes.json');

const result = spawnSync(process.execPath, [validatorBin, 'volumes', volumesPath, '--quiet'], {
  stdio: 'inherit'
});

if (result.status !== 0) {
  console.error('Volume set validation failed');
  process.exit(result.status || 1);
}

console.log('Volume set example validated successfully.');
