const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { Command } = require('commander');
const pkg = require('../package.json');
const { validatePackage } = require('./validator');
const { loadSchemas } = require('./validator/schema-loader');
const { validateWithSchema } = require('./validator/json-validate');
const { resolveSafePath } = require('./validator/path-safe');
const { compileMapping } = require('./mapping');

const defaultSchemasDir = path.resolve(__dirname, '..', 'schemas');

function readJson(file) {
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

function readPackageContext(packageRoot, recipe) {
  const catalog = readJson(path.join(packageRoot, 'sitepack.catalog.json'));
  const inputs = [];
  let selectedProfile = null;
  for (const artifact of catalog.artifacts || []) {
    const safe = resolveSafePath(packageRoot, artifact.path);
    if (!safe.ok) throw new Error(safe.message);
    inputs.push({
      id: artifact.id,
      digest: `sha256:${crypto.createHash('sha256').update(fs.readFileSync(safe.resolved)).digest('hex')}`
    });
    if (artifact.mediaType === 'application/vnd.sitepack.semantic-profile+json') {
      const profile = readJson(safe.resolved);
      if (profile.id === recipe.source.profile) selectedProfile = profile;
    }
  }
  if (!selectedProfile) throw new Error(`Semantic profile '${recipe.source.profile}' was not found in the package catalog.`);
  inputs.sort((left, right) => left.id.localeCompare(right.id));
  return { profile: selectedProfile, inputs };
}

async function main() {
  const program = new Command();
  program
    .name('sitepack-compatibility')
    .argument('<packageRoot>', 'Path to an unpacked SitePack package')
    .requiredOption('--capabilities <file>', 'Local target adapter capabilities')
    .requiredOption('--recipe <file>', 'Declarative mapping recipe')
    .option('--schemas <dir>', 'Path to JSON schemas directory', defaultSchemasDir)
    .option('--output <dir>', 'Write transform-plan.json and compatibility.json')
    .option('-f, --format <format>', 'Output format: json|text', 'text');
  program.parse(process.argv);
  const options = program.opts();
  const packageRoot = path.resolve(program.args[0]);
  const capabilities = readJson(path.resolve(options.capabilities));
  const recipe = readJson(path.resolve(options.recipe));
  const packageValidation = await validatePackage({
    packageRoot,
    schemasDir: path.resolve(options.schemas),
    checkAssetBlobs: true,
    writeValidationReport: false,
    toolName: 'sitepack-compatibility',
    toolVersion: pkg.version
  });
  if (packageValidation.summary.errors > 0) {
    throw new Error(`Package validation failed with ${packageValidation.summary.errors} error(s).`);
  }
  const { profile, inputs } = readPackageContext(packageRoot, recipe);
  const { validators } = loadSchemas(path.resolve(options.schemas));
  for (const [name, validator, document] of [
    ['semantic profile', validators.semanticProfile, profile],
    ['capabilities', validators.capabilities, capabilities],
    ['mapping recipe', validators.mappingRecipe, recipe]
  ]) {
    const result = validateWithSchema(validator, document);
    if (!result.valid) throw new Error(`${name} is invalid: ${result.errors.map((item) => item.message).join('; ')}`);
  }
  const result = compileMapping(profile, capabilities, recipe, inputs);
  const reportValidation = validateWithSchema(validators.compatibilityReport, result.report);
  const planValidation = validateWithSchema(validators.transformPlan, result.plan);
  if (!reportValidation.valid || !planValidation.valid) {
    throw new Error('Compiled mapping output failed schema validation.');
  }
  if (options.output) {
    const output = path.resolve(options.output);
    fs.mkdirSync(output, { recursive: true });
    fs.writeFileSync(path.join(output, 'transform-plan.json'), `${JSON.stringify(result.plan, null, 2)}\n`);
    fs.writeFileSync(path.join(output, 'compatibility.json'), `${JSON.stringify(result.report, null, 2)}\n`);
  }
  if (options.format === 'json') {
    console.log(JSON.stringify(result, null, 2));
  } else {
    console.log(`${result.report.status}: exact ${result.report.summary.exact}, transformed ${result.report.summary.transformed}, fallback ${result.report.summary.fallback}, skipped ${result.report.summary.skipped}, blocked ${result.report.summary.blocked}`);
    console.log(`Plan: ${result.plan.digest}`);
  }
  process.exit(result.report.status === 'incompatible' ? 1 : 0);
}

main().catch((error) => {
  console.error(error.message || String(error));
  process.exit(2);
});
