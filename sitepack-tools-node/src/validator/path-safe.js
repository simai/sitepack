const path = require('path');

function resolveSafePath(baseDir, relPath) {
  if (typeof relPath !== 'string') {
    return { ok: false, code: 'PATH_NOT_STRING', message: 'Artifact path must be a string' };
  }
  if (relPath.includes('\0')) {
    return { ok: false, code: 'PATH_NULL_BYTE', message: 'Artifact path contains null byte' };
  }
  if (path.isAbsolute(relPath) || path.win32.isAbsolute(relPath)) {
    return { ok: false, code: 'PATH_ABSOLUTE', message: 'Absolute paths are not allowed' };
  }

  const parts = relPath.split(/[\\/]+/);
  if (parts.includes('..')) {
    return { ok: false, code: 'PATH_TRAVERSAL', message: 'Artifact path contains directory traversal (..)' };
  }

  const base = path.resolve(baseDir);
  const resolved = path.resolve(base, relPath);
  const relative = path.relative(base, resolved);
  if (relative.startsWith('..') || path.isAbsolute(relative)) {
    return { ok: false, code: 'PATH_OUTSIDE_ROOT', message: 'Artifact path escapes the package root' };
  }

  return { ok: true, resolved };
}

module.exports = {
  resolveSafePath
};
