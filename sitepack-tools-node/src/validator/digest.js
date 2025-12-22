const crypto = require('crypto');
const fs = require('fs');

function computeSha256(filePath) {
  return new Promise((resolve, reject) => {
    const hash = crypto.createHash('sha256');
    const stream = fs.createReadStream(filePath);

    stream.on('error', (err) => reject(err));
    stream.on('data', (chunk) => hash.update(chunk));
    stream.on('end', () => {
      resolve(`sha256:${hash.digest('hex')}`);
    });
  });
}

module.exports = {
  computeSha256
};
