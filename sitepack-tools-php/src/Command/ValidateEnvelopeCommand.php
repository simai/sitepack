<?php

declare(strict_types=1);

namespace SitePack\Command;

use SitePack\Validator\EnvelopeValidator;
use SitePack\Validator\FileUtil;
use SitePack\Validator\ReportWriter;
use SitePack\Validator\SafePath;
use SitePack\Validator\SchemaValidator;
use SitePack\Report\ValidationReport;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateEnvelopeCommand extends Command
{
    protected static $defaultName = 'envelope';

    protected static $defaultDescription = 'Validate encrypted envelope header';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addArgument('pathToEncJson', InputArgument::REQUIRED, 'Path to .sitepack.enc.json')
            ->addOption('schemas', null, InputOption::VALUE_REQUIRED, 'Path to JSON schemas directory')
            ->addOption('check-payload-file', null, InputOption::VALUE_NONE, 'Check that payload.file exists')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Treat warnings as errors')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text|json', 'text');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('pathToEncJson');
        if ($path === '' || !is_file($path)) {
            $output->writeln('Error: header file not found');
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
        $validator = new EnvelopeValidator(
            $schemaValidator,
            new FileUtil(),
            new SafePath(),
            new ReportWriter()
        );

        $toolInfo = [
            'name' => 'sitepack-validate',
            'version' => (string) $this->getApplication()?->getVersion(),
            'runtime' => 'php',
            'runtimeVersion' => PHP_VERSION,
        ];

        $result = $validator->validate(
            $path,
            (bool) $input->getOption('check-payload-file'),
            $toolInfo
        );

        $report = $result['report'];
        $reportPath = $result['reportPath'];
        $format = (string) $input->getOption('format');

        if ($format === 'json') {
            $output->writeln($report->toJson());
        } else {
            $this->printTextReport($report, $reportPath, $output);
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
     * @param OutputInterface $output
     * @return void
     */
    private function printTextReport(ValidationReport $report, string $reportPath, OutputInterface $output): void
    {
        $data = $report->toArray();
        $summary = $data['summary'];

        $output->writeln(
            sprintf('Errors: %d, warnings: %d', $summary['errors'], $summary['warnings'])
        );
        $output->writeln('Report: ' . $reportPath);

        if (!empty($data['messages'])) {
            $output->writeln('Messages:');
            foreach ($data['messages'] as $message) {
                $output->writeln(sprintf('- [%s] %s: %s', $message['level'], $message['code'], $message['message']));
            }
        }
    }
}
