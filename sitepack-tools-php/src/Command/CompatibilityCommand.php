<?php

declare(strict_types=1);

namespace SitePack\Command;

use SitePack\Mapping\CompatibilityCompiler;
use SitePack\Validator\DigestCalculator;
use SitePack\Validator\FileUtil;
use SitePack\Validator\NdjsonValidator;
use SitePack\Validator\PackageValidator;
use SitePack\Validator\ReportWriter;
use SitePack\Validator\SafePath;
use SitePack\Validator\SchemaValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class CompatibilityCommand extends Command
{
    protected static $defaultName = 'compatibility';
    protected static $defaultDescription = 'Compile a deterministic SitePack compatibility plan';

    protected function configure(): void
    {
        $this
            ->addArgument('packageRoot', InputArgument::REQUIRED, 'Path to an unpacked SitePack package')
            ->addOption('capabilities', null, InputOption::VALUE_REQUIRED, 'Local target adapter capabilities')
            ->addOption('recipe', null, InputOption::VALUE_REQUIRED, 'Declarative mapping recipe')
            ->addOption('schemas', null, InputOption::VALUE_REQUIRED, 'Path to JSON schemas directory')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Write transform-plan.json and compatibility.json')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text|json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $root = realpath((string)$input->getArgument('packageRoot'));
            if ($root === false || !is_dir($root)) throw new \RuntimeException('Package directory not found.');
            $capabilitiesPath = (string)$input->getOption('capabilities');
            $recipePath = (string)$input->getOption('recipe');
            if (!is_file($capabilitiesPath) || !is_file($recipePath)) throw new \RuntimeException('Capabilities or recipe file not found.');
            $capabilities = $this->readJson($capabilitiesPath);
            $recipe = $this->readJson($recipePath);
            $schemas = (string)($input->getOption('schemas') ?: dirname(__DIR__, 2) . '/schemas');
            $validator = new SchemaValidator($schemas);
            $packageValidator = new PackageValidator(
                $validator,
                new NdjsonValidator($validator),
                new DigestCalculator(),
                new SafePath(),
                new ReportWriter(),
                new FileUtil()
            );
            $packageValidation = $packageValidator->validate(
                $root,
                null,
                false,
                true,
                [
                    'name' => 'sitepack-compatibility',
                    'version' => (string)$this->getApplication()?->getVersion(),
                    'runtime' => 'php',
                    'runtimeVersion' => PHP_VERSION,
                ],
                false
            );
            if ($packageValidation['report']->getErrorCount() > 0) {
                throw new \RuntimeException('Package validation failed with '
                    . $packageValidation['report']->getErrorCount() . ' error(s).');
            }
            $context = $this->readPackageContext($root, $recipe);
            $profile = $context['profile'];
            foreach ([
                'semantic-profile' => $profile,
                'capabilities' => $capabilities,
                'mapping-recipe' => $recipe,
            ] as $schema => $document) {
                $result = $validator->validate($schema, $this->toObject($document));
                if (!$result['valid']) throw new \RuntimeException($schema . ' is invalid: ' . implode('; ', $result['errors']));
            }
            $compiled = (new CompatibilityCompiler())->compile($profile, $capabilities, $recipe, $context['inputs']);
            foreach (['transform-plan' => $compiled['plan'], 'compatibility-report' => $compiled['report']] as $schema => $document) {
                $result = $validator->validate($schema, $this->toObject($document));
                if (!$result['valid']) throw new \RuntimeException('Compiled ' . $schema . ' is invalid: ' . implode('; ', $result['errors']));
            }
            $outputDir = $input->getOption('output');
            if (is_string($outputDir) && $outputDir !== '') {
                if (!is_dir($outputDir) && !mkdir($outputDir, 0770, true) && !is_dir($outputDir)) throw new \RuntimeException('Cannot create output directory.');
                file_put_contents(rtrim($outputDir, '/\\') . '/transform-plan.json', $this->prettyJson($compiled['plan']));
                file_put_contents(rtrim($outputDir, '/\\') . '/compatibility.json', $this->prettyJson($compiled['report']));
            }
            if ((string)$input->getOption('format') === 'json') {
                $output->writeln(rtrim($this->prettyJson($compiled)));
            } else {
                $summary = $compiled['report']['summary'];
                $output->writeln(sprintf('%s: exact %d, transformed %d, fallback %d, skipped %d, blocked %d',
                    $compiled['report']['status'], $summary['exact'], $summary['transformed'], $summary['fallback'], $summary['skipped'], $summary['blocked']));
                $output->writeln('Plan: ' . $compiled['plan']['digest']);
            }
            return $compiled['report']['status'] === 'incompatible' ? 1 : 0;
        } catch (Throwable $exception) {
            $output->writeln('Error: ' . $exception->getMessage());
            return 2;
        }
    }

    /** @return array<string,mixed> */
    private function readJson(string $path): array
    {
        $data = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new \RuntimeException('JSON document must be an object: ' . $path);
        return $data;
    }

    /**
     * @param array<string,mixed> $recipe
     * @return array{profile:array<string,mixed>,inputs:array<int,array{id:string,digest:string}>}
     */
    private function readPackageContext(string $root, array $recipe): array
    {
        $catalog = $this->readJson($root . '/sitepack.catalog.json');
        $inputs = [];
        $selectedProfile = null;
        $safePath = new SafePath();
        $digestCalculator = new DigestCalculator();
        foreach ($catalog['artifacts'] ?? [] as $artifact) {
            if (!is_array($artifact)) continue;
            $relative = (string)($artifact['path'] ?? '');
            $resolved = $safePath->resolve($root, $relative);
            if (!$resolved['ok'] || !is_string($resolved['path']) || !is_file($resolved['path'])) {
                throw new \RuntimeException($resolved['message'] ?? 'Unsafe package artifact path.');
            }
            $inputs[] = [
                'id' => (string)($artifact['id'] ?? ''),
                'digest' => $digestCalculator->computeSha256($resolved['path']),
            ];
            if (($artifact['mediaType'] ?? null) === 'application/vnd.sitepack.semantic-profile+json') {
                $profile = $this->readJson($resolved['path']);
                if (($profile['id'] ?? null) === ($recipe['source']['profile'] ?? null)) {
                    $selectedProfile = $profile;
                }
            }
        }
        usort($inputs, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));
        if (is_array($selectedProfile)) return ['profile' => $selectedProfile, 'inputs' => $inputs];
        throw new \RuntimeException("Semantic profile '" . ($recipe['source']['profile'] ?? '') . "' was not found in the package catalog.");
    }

    private function toObject(array $document): object
    {
        return json_decode((string)json_encode($document, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
    }

    private function prettyJson(array $document): string
    {
        return (string)json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
    }
}
