const fs = require('fs');
const path = require('path');
const Ajv = require('ajv/dist/2020');
const addFormats = require('ajv-formats');

const schemaFiles = {
  manifest: 'manifest.schema.json',
  catalog: 'catalog.schema.json',
  entity: 'entity.schema.json',
  assetIndex: 'asset-index.schema.json',
  configKv: 'config-kv.schema.json',
  recordset: 'recordset.schema.json',
  capabilities: 'capabilities.schema.json',
  transformPlan: 'transform-plan.schema.json',
  envelope: 'envelope.schema.json',
  volumeSet: 'volume-set.schema.json'
};

function loadSchemas(schemasDir) {
  const ajv = new Ajv({ allErrors: true, strict: false });
  addFormats(ajv);

  const schemas = {};
  for (const [key, fileName] of Object.entries(schemaFiles)) {
    const filePath = path.join(schemasDir, fileName);
    const raw = fs.readFileSync(filePath, 'utf8');
    const schema = JSON.parse(raw);
    ajv.addSchema(schema, schema.$id || fileName);
    schemas[key] = schema;
  }

  const validators = {};
  for (const [key, schema] of Object.entries(schemas)) {
    validators[key] = ajv.getSchema(schema.$id || schemaFiles[key]) || ajv.compile(schema);
  }

  return { ajv, schemas, validators };
}

module.exports = {
  loadSchemas
};
