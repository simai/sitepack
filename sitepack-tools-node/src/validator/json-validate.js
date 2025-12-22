function formatAjvErrors(errors) {
  if (!errors || errors.length === 0) {
    return [];
  }
  return errors.map((err) => {
    const instancePath = err.instancePath && err.instancePath.length > 0 ? err.instancePath : '/';
    const message = err.message ? `${instancePath} ${err.message}` : `${instancePath} invalid`;
    return {
      keyword: err.keyword,
      message,
      schemaPath: err.schemaPath,
      params: err.params
    };
  });
}

function validateWithSchema(validator, data) {
  const valid = validator(data);
  return {
    valid: Boolean(valid),
    errors: valid ? [] : formatAjvErrors(validator.errors)
  };
}

module.exports = {
  validateWithSchema,
  formatAjvErrors
};
