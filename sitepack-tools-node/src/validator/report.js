const fs = require('fs/promises');
const path = require('path');

function createReport({ toolName, toolVersion, targetType, targetPath }) {
  return {
    tool: {
      name: toolName,
      version: toolVersion
    },
    startedAt: new Date().toISOString(),
    finishedAt: null,
    target: {
      type: targetType,
      path: targetPath
    },
    summary: {
      errors: 0,
      warnings: 0,
      artifactsTotal: 0,
      artifactsValidated: 0,
      artifactsSkipped: 0,
      ndjsonLinesValidated: 0
    },
    artifacts: [],
    messages: []
  };
}

function finalizeReport(report) {
  report.finishedAt = new Date().toISOString();
  return report;
}

async function writeReport(report, targetRoot) {
  const reportsDir = path.join(targetRoot, 'reports');
  await fs.mkdir(reportsDir, { recursive: true });
  const reportPath = path.join(reportsDir, 'validate.json');
  await fs.writeFile(reportPath, JSON.stringify(report, null, 2), 'utf8');
  return reportPath;
}

module.exports = {
  createReport,
  finalizeReport,
  writeReport
};
