<?php

declare(strict_types=1);

namespace SitePack\Command;

use SitePack\Signature\SignatureService;
use SitePack\Validator\SchemaValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class VerifySignatureCommand extends Command
{
    protected static $defaultName = 'signature';
    protected static $defaultDescription = 'Verify an Ed25519 detached SitePack signature';

    protected function configure(): void
    {
        $this->addArgument('pathToFile', InputArgument::REQUIRED)
            ->addArgument('pathToSignature', InputArgument::REQUIRED)
            ->addOption('trust-store', null, InputOption::VALUE_REQUIRED)
            ->addOption('schemas', null, InputOption::VALUE_REQUIRED)
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'text|json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $signaturePath = (string)$input->getArgument('pathToSignature');
        $trustPath = (string)$input->getOption('trust-store');
        if (!is_file($signaturePath) || !is_file($trustPath)) {
            $output->writeln('Error: signature and trust store files are required');
            return 2;
        }
        try {
            $document = json_decode((string)file_get_contents($signaturePath), true, 512, JSON_THROW_ON_ERROR);
            $trustStore = json_decode((string)file_get_contents($trustPath), true, 512, JSON_THROW_ON_ERROR);
            $schemas = (string)($input->getOption('schemas') ?: dirname(__DIR__, 2) . '/schemas');
            $result = (new SignatureService(new SchemaValidator($schemas)))->verify(
                (string)$input->getArgument('pathToFile'),
                is_array($document) ? $document : [],
                is_array($trustStore) ? $trustStore : []
            );
            $output->writeln((string)$input->getOption('format') === 'json'
                ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                : $result['status'] . ': ' . $result['code']);
            return $result['status'] === 'valid' ? 0 : 1;
        } catch (\Throwable $exception) {
            $output->writeln('Error: ' . $exception->getMessage());
            return 1;
        }
    }
}
