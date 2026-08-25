const assert = require('assert');
const crypto = require('crypto');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { signFile, verifyFileSignature } = require('../src/signature');

(async () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'sitepack-signature-'));
  const payload = path.join(dir, 'example.sitepack');
  fs.writeFileSync(payload, 'sitepack bytes');
  const pair = crypto.generateKeyPairSync('ed25519');
  const privateDer = pair.privateKey.export({ format: 'der', type: 'pkcs8' });
  const publicDer = pair.publicKey.export({ format: 'der', type: 'spki' });
  const seed = privateDer.subarray(privateDer.length - 32).toString('base64');
  const publicKey = publicDer.subarray(publicDer.length - 32).toString('base64');
  const signature = await signFile(payload, 'test-key', seed, '2026-08-25T00:00:00Z');
  const trust = { keys: { 'test-key': { algorithm: 'Ed25519', publicKey } } };

  assert.strictEqual((await verifyFileSignature(payload, signature, trust)).status, 'valid');
  assert.strictEqual((await verifyFileSignature(payload, signature, { keys: {} })).status, 'untrusted');
  fs.appendFileSync(payload, ' modified');
  assert.strictEqual((await verifyFileSignature(payload, signature, trust)).status, 'invalid');
  fs.rmSync(dir, { recursive: true, force: true });
  console.log('signature tests: 3 checks passed');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
