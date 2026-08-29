const { ALLOWED_OPERATIONS } = require('./mapping');

function validateMappingRecipeSemantics(recipe) {
  const messages = [];
  for (const rule of Array.isArray(recipe?.rules) ? recipe.rules : []) {
    if (ALLOWED_OPERATIONS.has(rule.kind)) continue;
    messages.push({
      level: rule.required === false ? 'warning' : 'error',
      code: rule.required === false ? 'MAPPING_OPERATION_OPTIONAL_UNKNOWN' : 'MAPPING_OPERATION_REQUIRED_UNKNOWN',
      message: `Mapping rule '${rule.id || ''}' uses unknown operation '${rule.kind || ''}'.`
    });
  }
  return messages;
}

module.exports = { validateMappingRecipeSemantics };
