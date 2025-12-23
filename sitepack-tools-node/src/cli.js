const path = require('path');
const fs = require('fs');
const { Command } = require('commander');
const pkg = require('../package.json');
const { validatePackage, validateEnvelope } = require('./validator');

const defaultSchemasDir = path.resolve(__dirname, '..', 'schemas');

function printTextReport(report, quiet) {
  const { summary } = report;
  console.log(`Errors: ${summary.errors}, warnings: ${summary.warnings}`);
  console.log(
    `Artifacts: total ${summary.artifactsTotal}, validated ${summary.artifactsValidated}, skipped ${summary.artifactsSkipped}`
  );
  console.log(`NDJSON lines validated: ${summary.ndjsonLinesValidated}`);

  if (quiet) {
    return;
  }

  if (report.messages.length > 0) {
    console.log('\nMessages:');
    for (const msg of report.messages) {
      console.log(`- [${msg.level}] ${msg.code}: ${msg.message}`);
    }
  }

  if (report.artifacts.length > 0) {
    console.log('\nArtifacts:');
    for (const art of report.artifacts) {
      console.log(`- ${art.id} (${art.status}) ${art.mediaType || ''}`.trim());
      for (const detail of art.details || []) {
        const lineInfo = detail.line ? ` (line ${detail.line})` : '';
        console.log(`  - [${detail.level}] ${detail.code}: ${detail.message}${lineInfo}`);
      }
    }
  }
}

function outputReport(report, options) {
  if (options.format === 'json') {
    console.log(JSON.stringify(report, null, 2));
  } else {
    printTextReport(report, options.quiet);
  }
}

function computeExitCode(report, strict) {
  if (report.summary.errors > 0) {
    return 1;
  }
  if (strict && report.summary.warnings > 0) {
    return 1;
  }
  return 0;
}

async function runPackageValidation(packageRoot, options) {
  const root = path.resolve(packageRoot);
  if (!fs.existsSync(root)) {
    console.error(`Path does not exist: ${root}`);
    process.exit(2);
  }
  if (!fs.statSync(root).isDirectory()) {
    console.error(`Expected package directory: ${root}`);
    process.exit(2);
  }

  const schemasDir = options.schemas ? path.resolve(options.schemas) : defaultSchemasDir;
  if (!fs.existsSync(schemasDir)) {
    console.error(`Schemas directory not found: ${schemasDir}`);
    process.exit(2);
  }

  const report = await validatePackage({
    packageRoot: root,
    schemasDir,
    profile: options.profile,
    noDigest: options.digest === false,
    checkAssetBlobs: Boolean(options.checkAssetBlobs),
    toolName: 'sitepack-validate',
    toolVersion: pkg.version
  });

  outputReport(report, options);
  process.exit(computeExitCode(report, options.strict));
}

async function runEnvelopeValidation(encJsonPath, options) {
  const targetPath = path.resolve(encJsonPath);
  if (!fs.existsSync(targetPath)) {
    console.error(`Path does not exist: ${targetPath}`);
    process.exit(2);
  }
  if (!fs.statSync(targetPath).isFile()) {
    console.error(`Expected envelope header file: ${targetPath}`);
    process.exit(2);
  }

  const schemasDir = options.schemas ? path.resolve(options.schemas) : defaultSchemasDir;
  if (!fs.existsSync(schemasDir)) {
    console.error(`Schemas directory not found: ${schemasDir}`);
    process.exit(2);
  }

  const report = await validateEnvelope({
    encJsonPath: targetPath,
    schemasDir,
    checkPayloadFile: Boolean(options.checkPayloadFile),
    toolName: 'sitepack-validate',
    toolVersion: pkg.version
  });

  outputReport(report, options);
  process.exit(computeExitCode(report, options.strict));
}

async function main() {
  const program = new Command();
  program.name('sitepack-validate');
  program.description('CLI validator for unpacked SitePack v0.2.0 packages');
  program.option('-f, --format <format>', 'Output format: text|json', 'text');
  program.option('--quiet', 'Minimal console output');
  program.option('--strict', 'Treat warnings as errors');

  program
    .argument('<packageRoot>', 'Path to the unpacked package')
    .option('--schemas <dir>', 'Path to JSON schemas directory', defaultSchemasDir)
    .option('--profile <name>', 'Validate only artifacts for the selected profile')
    .option('--no-digest', 'Skip digest verification even if present')
    .option('--check-asset-blobs', 'Check existence of files referenced in asset-index')
    .action(runPackageValidation);

  program
    .command('envelope <pathToEncJson>')
    .description('Validate encrypted envelope header (.enc.json)')
    .option('-f, --format <format>', 'Output format: text|json', 'text')
    .option('--quiet', 'Minimal console output')
    .option('--strict', 'Treat warnings as errors')
    .option('--schemas <dir>', 'Path to JSON schemas directory', defaultSchemasDir)
    .option('--check-payload-file', 'Check that payload.file exists next to enc.json')
    .action(runEnvelopeValidation);

  program.exitOverride();

  try {
    await program.parseAsync(process.argv);
  } catch (err) {
    if (err.code && err.code.startsWith('commander.')) {
      console.error(err.message);
      process.exit(2);
    }
    console.error(err.message || String(err));
    process.exit(1);
  }
}

main();
