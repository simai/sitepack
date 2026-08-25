const assert = require('assert');
const {
  buildDistributionBaseName,
  buildPackageFileName,
  buildSignatureFileName,
  buildVolumeFileName
} = require('../src/distribution-naming');

const base = buildDistributionBaseName(
  'School.OBR.Site.',
  '2026-08-25T14:35:22+03:00',
  'FULL'
);

assert.strictEqual(base, 'school.obr.site_20260825_113522_full');
assert.strictEqual(
  buildDistributionBaseName('школа.рф', '2026-08-25T11:35:22Z', 'compact'),
  'xn--80atdl2c.xn--p1ai_20260825_113522_compact'
);
assert.strictEqual(buildPackageFileName(base), `${base}.sitepack`);
assert.strictEqual(buildSignatureFileName(`${base}.sitepack`), `${base}.sitepack.sig.json`);
assert.strictEqual(
  buildVolumeFileName(base, 1, 12),
  `${base}.part-0001-of-0012.sitepack`
);
assert.strictEqual(
  buildVolumeFileName(base, 10000, 10000),
  `${base}.part-10000-of-10000.sitepack`
);

for (const invalid of ['https://school.obr.site', 'school.obr.site/path', 'school_obr.site']) {
  assert.throws(() => buildDistributionBaseName(invalid, '2026-08-25T11:35:22Z', 'full'));
}
assert.throws(() => buildDistributionBaseName('school.obr.site', 'invalid', 'full'));
assert.throws(() => buildDistributionBaseName('school.obr.site', '2026-08-25T11:35:22Z', '../full'));
assert.throws(() => buildVolumeFileName(base, 13, 12));
for (const unsafeBase of ['CON', 'backup?.sitepack', 'backup.']) {
  assert.throws(() => buildVolumeFileName(unsafeBase, 1, 1));
}

console.log('Distribution naming checks passed.');
