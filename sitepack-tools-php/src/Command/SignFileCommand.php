<?php

declare(strict_types=1);

namespace SitePack\Command;

use SitePack\Signature\SignatureService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class SignFileCommand extends Command
{
    protected static $defaultName = 'sign';
    protected static $defaultDescription = 'Create an Ed25519 detached SitePack signature';

    protected function configure(): void
    {
        $this->addArgument('pathToFile', InputArgument::REQUIRED)
            ->addOption('key-id', null, InputOption::VALUE_REQUIRED)
            ->addOption('private-key', null, InputOption::VALUE_REQUIRED, 'File containing a base64 Ed25519 seed')
            ->addOption('output', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = (string)$input->getArgument('pathToFile');
        $keyId = (string)$input->getOption('key-id');
        $keyFile = (string)$input->getOption('private-key');
        if ($keyId === '' || !is_file($keyFile)) {
            $output->writeln('Error: --key-id and --private-key are required');
            return 2;
        }
        $seed = file_get_contents($keyFile);
        if ($seed === false) {
            $output->writeln('Error: private key is unavailable');
            return 2;
        }
        try {
            $document = (new SignatureService())->sign($file, $keyId, trim($seed));
            $target = (string)($input->getOption('output') ?: $file . '.sig.json');
            file_put_contents($target, json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            chmod($target, 0640);
            $output->writeln($target);
            return 0;
        } catch (\Throwable $exception) {
            $output->writeln('Error: ' . $exception->getMessage());
            return 1;
        }
    }
}
