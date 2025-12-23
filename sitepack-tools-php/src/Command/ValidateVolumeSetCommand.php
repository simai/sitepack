<?php

declare(strict_types=1);

namespace SitePack\Command;

use SitePack\Validator\DigestCalculator;
use SitePack\Validator\FileUtil;
use SitePack\Validator\NdjsonValidator;
use SitePack\Validator\PackageValidator;
use SitePack\Validator\ReportWriter;
use SitePack\Validator\SafePath;
use SitePack\Validator\SchemaValidator;
use SitePack\Validator\VolumeSetValidator;
use SitePack\Report\ValidationReport;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateVolumeSetCommand extends Command
{
    protected static $defaultName = 'volumes';

    protected static $defaultDescription = 'Validate a SitePack Volume Set descriptor and assembled package';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addArgument('volumesPath', InputArgument::REQUIRED, 'Path to sitepack.volumes.json')
            ->addOption('schemas', null, InputOption::VALUE_REQUIRED, 'Path to JSON schemas directory')
            ->addOption('profile', null, InputOption::VALUE_REQUIRED, 'Profile for selective validation')
            ->addOption('no-digest', null, InputOption::VALUE_NONE, 'Skip digest verification')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Treat warnings as errors')
            ->addOption('check-asset-blobs', null, InputOption::VALUE_NONE, 'Check asset blob files and chunked assets')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text|json', 'text')
            ->addOption('quiet', null, InputOption::VALUE_NONE, 'Minimal output');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $volumesPath = (string) $input->getArgument('volumesPath');
        if ($volumesPath === '' || !is_file($volumesPath)) {
            $output->writeln('Error: volume set descriptor path does not exist or is not a file');
            return 2;
        }

        $schemasDir = $input->getOption('schemas');
        if ($schemasDir === null || $schemasDir === '') {
            $schemasDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'schemas';
        }
        $schemasDir = (string) $schemasDir;
        if (!is_dir($schemasDir)) {
            $output->writeln('Error: schemas directory not found');
            return 2;
        }

        $schemaValidator = new SchemaValidator($schemasDir);
        $ndjsonValidator = new NdjsonValidator($schemaValidator);
        $packageValidator = new PackageValidator(
            $schemaValidator,
            $ndjsonValidator,
            new DigestCalculator(),
            new SafePath(),
            new ReportWriter(),
            new FileUtil()
        );

        $validator = new VolumeSetValidator(
            $schemaValidator,
            new DigestCalculator(),
            new SafePath(),
            new ReportWriter(),
            new FileUtil(),
            $packageValidator
        );

        $toolInfo = [
            'name' => 'sitepack-validate',
            'version' => (string) $this->getApplication()?->getVersion(),
            'runtime' => 'php',
            'runtimeVersion' => PHP_VERSION,
        ];

        $profile = $input->getOption('profile');
        $skipDigest = (bool) $input->getOption('no-digest');
        $checkAssetBlobs = (bool) $input->getOption('check-asset-blobs');
        $result = $validator->validate(
            $volumesPath,
            $profile !== null ? (string) $profile : null,
            $skipDigest,
            $checkAssetBlobs,
            $toolInfo
        );

        $report = $result['report'];
        $reportPath = $result['reportPath'];
        $format = (string) $input->getOption('format');
        $quiet = (bool) $input->getOption('quiet');

        if ($format === 'json') {
            $output->writeln($report->toJson());
        } else {
            $this->printTextReport($report, $reportPath, $quiet, $output);
        }

        if ($result['usageError']) {
            return 2;
        }

        $strict = (bool) $input->getOption('strict');
        if ($report->getErrorCount() > 0) {
            return 1;
        }
        if ($strict && $report->getWarningCount() > 0) {
            return 1;
        }

        return 0;
    }

    /**
     * @param ValidationReport $report
     * @param string $reportPath
     * @param bool $quiet
     * @param OutputInterface $output
     * @return void
     */
    private function printTextReport(ValidationReport $report, string $reportPath, bool $quiet, OutputInterface $output): void
    {
        $data = $report->toArray();
        $summary = $data['summary'];

        $output->writeln(
            sprintf('Errors: %d, warnings: %d', $summary['errors'], $summary['warnings'])
        );
        $output->writeln(
            sprintf(
                'Artifacts: total %d, validated %d, skipped %d',
                $summary['artifactsTotal'],
                $summary['artifactsValidated'],
                $summary['artifactsSkipped']
            )
        );
        $output->writeln(
            sprintf('NDJSON lines validated: %d', $summary['ndjsonLinesValidated'])
        );
        $output->writeln('Report: ' . $reportPath);

        if ($quiet) {
            return;
        }

        if (!empty($data['messages'])) {
            $output->writeln('Messages:');
            foreach ($data['messages'] as $message) {
                $output->writeln(sprintf('- [%s] %s: %s', $message['level'], $message['code'], $message['message']));
            }
        }

        if (!empty($data['artifacts'])) {
            $output->writeln('Artifacts:');
            foreach ($data['artifacts'] as $artifact) {
                $title = sprintf('- %s (%s)', $artifact['id'], $artifact['status']);
                if (!empty($artifact['mediaType'])) {
                    $title .= ' ' . $artifact['mediaType'];
                }
                $output->writeln($title);
                foreach ($artifact['details'] as $detail) {
                    $lineInfo = $detail['line'] !== null ? ' (line ' . $detail['line'] . ')' : '';
                    $output->writeln(
                        sprintf('  - [%s] %s: %s%s', $detail['level'], $detail['code'], $detail['message'], $lineInfo)
                    );
                }
            }
        }
    }
}
