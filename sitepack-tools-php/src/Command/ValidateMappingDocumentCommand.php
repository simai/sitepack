<?php

declare(strict_types=1);

namespace SitePack\Command;

use SitePack\Validator\SchemaValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use SitePack\Mapping\MappingRecipeValidator;

final class ValidateMappingDocumentCommand extends Command
{
    public function __construct(private string $schemaName, string $name, string $description)
    {
        parent::__construct($name);
        $this->setDescription($description);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the JSON document')
            ->addOption('schemas', null, InputOption::VALUE_REQUIRED, 'Path to JSON schemas directory')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text|json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string)$input->getArgument('file');
        if (!is_file($path)) {
            $output->writeln('Error: document not found');
            return 2;
        }
        $schemas = (string)($input->getOption('schemas') ?: dirname(__DIR__, 2) . '/schemas');
        try {
            $document = json_decode((string)file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
            if (!is_object($document)) throw new \RuntimeException('Document must be a JSON object.');
            $result = (new SchemaValidator($schemas))->validate($this->schemaName, $document);
            $messages = $this->schemaName === 'mapping-recipe' && $result['valid']
                ? (new MappingRecipeValidator())->validate($document)
                : [];
            if (array_filter($messages, static fn (array $message): bool => $message['level'] === 'error')) {
                $result['valid'] = false;
            }
            $result['messages'] = $messages;
        } catch (Throwable $exception) {
            $output->writeln('Error: ' . $exception->getMessage());
            return 2;
        }
        if ((string)$input->getOption('format') === 'json') {
            $output->writeln((string)json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $output->writeln($result['valid'] ? 'valid' : implode(PHP_EOL, $result['errors']));
            foreach ($result['messages'] ?? [] as $message) {
                $output->writeln(sprintf('[%s] %s: %s', $message['level'], $message['code'], $message['message']));
            }
        }
        return $result['valid'] ? 0 : 1;
    }
}
