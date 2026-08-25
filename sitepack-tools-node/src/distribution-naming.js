const { domainToASCII } = require('url');

const MIN_VOLUME_WIDTH = 4;

function normalizeSiteToken(value) {
  const input = String(value || '').trim().replace(/\.$/, '');
  if (input === '' || input.includes('://') || /[/:?#\\]/.test(input)) {
    throw new Error('site must be a DNS host without protocol, port, path, query, or fragment');
  }

  const ascii = domainToASCII(input).toLowerCase();
  if (ascii === '' || ascii.length > 253) {
    throw new Error('site must be a valid DNS host');
  }

  const labels = ascii.split('.');
  if (labels.some((label) => label === ''
    || label.length > 63
    || !/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/.test(label))) {
    throw new Error('site must be a valid DNS host');
  }

  return ascii;
}

function normalizeMode(value) {
  const mode = String(value || '').trim().toLowerCase();
  if (!/^[a-z0-9](?:[a-z0-9-]{0,30}[a-z0-9])?$/.test(mode)) {
    throw new Error('mode must be a lowercase ASCII token of at most 32 characters');
  }
  return mode;
}

function formatUtcTimestamp(value) {
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) {
    throw new Error('created-at must be a valid date-time');
  }

  const pad = (part) => String(part).padStart(2, '0');
  return `${date.getUTCFullYear()}${pad(date.getUTCMonth() + 1)}${pad(date.getUTCDate())}`
    + `_${pad(date.getUTCHours())}${pad(date.getUTCMinutes())}${pad(date.getUTCSeconds())}`;
}

function buildDistributionBaseName(site, createdAt, mode) {
  return `${normalizeSiteToken(site)}_${formatUtcTimestamp(createdAt)}_${normalizeMode(mode)}`;
}

function assertSafeBaseName(value) {
  const baseName = String(value || '').trim();
  if (baseName === ''
    || baseName === '.'
    || baseName === '..'
    || baseName.includes('/')
    || baseName.includes('\\')
    || /[<>:"|?*\u0000-\u001f\u007f]/.test(baseName)
    || /[. ]$/.test(baseName)
    || /^(?:con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)/i.test(baseName)) {
    throw new Error('base-name must be a safe filename segment');
  }
  return baseName;
}

function buildVolumeFileName(baseName, index, count) {
  if (!Number.isInteger(index) || !Number.isInteger(count) || index < 1 || count < 1 || index > count) {
    throw new Error('volume index and count must be positive integers with index <= count');
  }
  const width = Math.max(MIN_VOLUME_WIDTH, String(count).length);
  return `${assertSafeBaseName(baseName)}.part-${String(index).padStart(width, '0')}`
    + `-of-${String(count).padStart(width, '0')}.sitepack`;
}

function buildPackageFileName(baseName) {
  return `${assertSafeBaseName(baseName)}.sitepack`;
}

function buildSignatureFileName(payloadName) {
  return `${assertSafeBaseName(payloadName)}.sig.json`;
}

module.exports = {
  buildDistributionBaseName,
  buildPackageFileName,
  buildSignatureFileName,
  buildVolumeFileName,
  formatUtcTimestamp,
  normalizeMode,
  normalizeSiteToken
};
