const fs = require('fs');
const readline = require('readline');
const { formatAjvErrors } = require('./json-validate');

async function validateNdjson(filePath, validator, options = {}) {
  const details = [];
  let linesValidated = 0;

  const input = fs.createReadStream(filePath, { encoding: 'utf8' });
  const rl = readline.createInterface({ input, crlfDelay: Infinity });

  let lineNumber = 0;
  for await (const line of rl) {
    lineNumber += 1;
    if (line.trim() === '') {
      details.push({
        level: 'warning',
        code: 'NDJSON_EMPTY_LINE',
        message: 'Empty NDJSON line skipped',
        line: lineNumber
      });
      continue;
    }

    linesValidated += 1;
    let record;
    try {
      record = JSON.parse(line);
    } catch (err) {
      details.push({
        level: 'error',
        code: 'NDJSON_PARSE_ERROR',
        message: `Invalid JSON on line ${lineNumber}: ${err.message}`,
        line: lineNumber
      });
      continue;
    }

    if (options.onRecord) {
      const extra = await options.onRecord(record, lineNumber);
      if (Array.isArray(extra)) {
        for (const item of extra) {
          details.push({
            level: item.level || 'warning',
            code: item.code || 'NDJSON_RECORD_WARNING',
            message: item.message || 'NDJSON record warning',
            line: lineNumber
          });
        }
      }
    }

    const valid = validator(record);
    if (!valid) {
      const errors = formatAjvErrors(validator.errors);
      const message = errors.map((e) => e.message).join('; ');
      details.push({
        level: 'error',
        code: 'NDJSON_SCHEMA_ERROR',
        message: `Schema violation on line ${lineNumber}: ${message}`,
        line: lineNumber
      });
    }
  }

  return { details, linesValidated };
}

module.exports = {
  validateNdjson
};
