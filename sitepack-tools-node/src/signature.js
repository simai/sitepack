const crypto = require('crypto');
const fs = require('fs');

const SIGNATURE_SPEC = '1.0.0';
const SIGNATURE_ALGORITHM = 'Ed25519';
const PUBLIC_KEY_DER_PREFIX = Buffer.from('302a300506032b6570032100', 'hex');
const PRIVATE_KEY_DER_PREFIX = Buffer.from('302e020100300506032b657004220420', 'hex');

function sha256File(filePath) {
  return new Promise((resolve, reject) => {
    const hash = crypto.createHash('sha256');
    const stream = fs.createReadStream(filePath);
    stream.on('error', reject);
    stream.on('data', (chunk) => hash.update(chunk));
    stream.on('end', () => resolve(hash.digest('hex')));
  });
}

function signingInput(digest, size) {
  return Buffer.from(`sitepack-signature-v1\n${digest}\n${size}`, 'utf8');
}

function decodeBase64(value, expectedLength, label) {
  if (typeof value !== 'string' || value.trim() === '') {
    throw new Error(`${label} is missing.`);
  }
  const normalized = value.trim();
  const decoded = Buffer.from(normalized, 'base64');
  const canonical = decoded.toString('base64').replace(/=+$/, '');
  if (canonical !== normalized.replace(/=+$/, '') || decoded.length !== expectedLength) {
    throw new Error(`${label} is invalid.`);
  }
  return decoded;
}

function privateKeyFromSeed(seedBase64) {
  const seed = decodeBase64(seedBase64, 32, 'Ed25519 private seed');
  return crypto.createPrivateKey({
    key: Buffer.concat([PRIVATE_KEY_DER_PREFIX, seed]),
    format: 'der',
    type: 'pkcs8'
  });
}

function publicKeyFromRaw(publicKeyBase64) {
  const raw = decodeBase64(publicKeyBase64, 32, 'Ed25519 public key');
  return crypto.createPublicKey({
    key: Buffer.concat([PUBLIC_KEY_DER_PREFIX, raw]),
    format: 'der',
    type: 'spki'
  });
}

function validateSignatureDocument(document) {
  const errors = [];
  if (!document || typeof document !== 'object' || Array.isArray(document)) {
    return ['Signature document must be an object.'];
  }
  if (document.spec?.name !== 'sitepack-signature' || document.spec?.version !== SIGNATURE_SPEC) {
    errors.push('Unsupported signature specification.');
  }
  if (document.algorithm !== SIGNATURE_ALGORITHM) {
    errors.push('Unsupported signature algorithm.');
  }
  if (typeof document.keyId !== 'string' || !/^[A-Za-z0-9._:-]{1,128}$/.test(document.keyId)) {
    errors.push('Invalid keyId.');
  }
  if (typeof document.createdAt !== 'string' || Number.isNaN(Date.parse(document.createdAt))) {
    errors.push('Invalid createdAt.');
  }
  if (typeof document.payload?.digest !== 'string' || !/^sha256:[0-9a-f]{64}$/.test(document.payload.digest)) {
    errors.push('Invalid payload digest.');
  }
  if (!Number.isSafeInteger(document.payload?.size) || document.payload.size < 0) {
    errors.push('Invalid payload size.');
  }
  try {
    decodeBase64(document.signature, 64, 'Ed25519 signature');
  } catch (error) {
    errors.push(error.message);
  }
  return errors;
}

async function signFile(filePath, keyId, privateSeedBase64, createdAt = new Date().toISOString()) {
  if (!/^[A-Za-z0-9._:-]{1,128}$/.test(keyId)) {
    throw new Error('Invalid keyId.');
  }
  const stat = fs.statSync(filePath);
  if (!stat.isFile()) {
    throw new Error('Signed payload must be a regular file.');
  }
  const digest = `sha256:${await sha256File(filePath)}`;
  const signature = crypto.sign(null, signingInput(digest, stat.size), privateKeyFromSeed(privateSeedBase64));
  return {
    spec: { name: 'sitepack-signature', version: SIGNATURE_SPEC },
    createdAt,
    algorithm: SIGNATURE_ALGORITHM,
    keyId,
    payload: { digest, size: stat.size },
    signature: signature.toString('base64')
  };
}

async function verifyFileSignature(filePath, document, trustStore) {
  const errors = validateSignatureDocument(document);
  if (errors.length > 0) {
    return { status: 'invalid', code: 'signature_document_invalid', errors };
  }
  const trusted = trustStore?.keys?.[document.keyId];
  if (!trusted || trusted.algorithm !== SIGNATURE_ALGORITHM || typeof trusted.publicKey !== 'string') {
    return { status: 'untrusted', code: 'signature_key_untrusted', keyId: document.keyId };
  }
  let stat;
  try {
    stat = fs.statSync(filePath);
  } catch (error) {
    return { status: 'invalid', code: 'signature_payload_unavailable' };
  }
  if (!stat.isFile() || stat.size !== document.payload.size) {
    return { status: 'invalid', code: 'signature_size_mismatch' };
  }
  const digest = `sha256:${await sha256File(filePath)}`;
  if (!crypto.timingSafeEqual(Buffer.from(digest), Buffer.from(document.payload.digest))) {
    return { status: 'invalid', code: 'signature_digest_mismatch' };
  }
  try {
    const valid = crypto.verify(
      null,
      signingInput(digest, stat.size),
      publicKeyFromRaw(trusted.publicKey),
      decodeBase64(document.signature, 64, 'Ed25519 signature')
    );
    return valid
      ? { status: 'valid', code: 'signature_valid', keyId: document.keyId, digest }
      : { status: 'invalid', code: 'signature_verification_failed', keyId: document.keyId };
  } catch (error) {
    return { status: 'invalid', code: 'signature_key_invalid', keyId: document.keyId };
  }
}

module.exports = {
  signFile,
  signingInput,
  validateSignatureDocument,
  verifyFileSignature
};
