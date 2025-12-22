<?php

declare(strict_types=1);

namespace SitePack\Validator;

use SitePack\Report\ValidationReport;

class ReportWriter
{
    /**
     * @param ValidationReport $report
     * @param string $targetDir
     * @return string
     */
    public function write(ValidationReport $report, string $targetDir): string
    {
        $reportsDir = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . 'reports';
        if (!is_dir($reportsDir)) {
            mkdir($reportsDir, 0777, true);
        }

        $reportPath = $reportsDir . DIRECTORY_SEPARATOR . 'validate.json';
        file_put_contents($reportPath, $report->toJson());

        return $reportPath;
    }
}
