<?php

declare(strict_types=1);

namespace SitePack\Mapping;

use RuntimeException;

final class CompatibilityCompiler
{
    private const ALLOWED_OPERATIONS = [
        'entity.map', 'field.copy', 'field.rename', 'field.cast', 'value.default',
        'enum.map', 'relation.map', 'asset.bind', 'content.convert',
        'fallback.wrap', 'value.drop',
    ];

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $capabilities
     * @param array<string, mixed> $recipe
     * @param array<int,array{id:string,digest:string}> $inputs
     * @return array{plan:array<string,mixed>,report:array<string,mixed>}
     */
    public function compile(array $profile, array $capabilities, array $recipe, array $inputs = []): array
    {
        $findings = [];
        $summary = ['exact' => 0, 'transformed' => 0, 'fallback' => 0, 'skipped' => 0, 'blocked' => 0];
        $missingRequired = [];
        $losses = [];
        $unsupportedExtensions = [];
        $rules = is_array($recipe['rules'] ?? null) ? $recipe['rules'] : [];
        $rulesBySubject = [];
        foreach ($rules as $rule) {
            if (is_array($rule)) {
                $rulesBySubject[$this->endpointSubject(is_array($rule['from'] ?? null) ? $rule['from'] : [])] = $rule;
            }
        }
        $mapping = is_array($capabilities['mapping'] ?? null) ? $capabilities['mapping'] : [];
        $supportedTransforms = array_fill_keys(is_array($mapping['transforms'] ?? null) ? $mapping['transforms'] : [], true);
        $supportedExtensions = array_fill_keys(is_array($mapping['extensions'] ?? null) ? $mapping['extensions'] : [], true);
        $supportedContentFormats = array_fill_keys(is_array($mapping['contentFormats'] ?? null) ? $mapping['contentFormats'] : [], true);
        $inventory = ['entities' => 0, 'fields' => 0, 'relations' => 0, 'assets' => 0];

        $add = function (string $category, string $subject, string $message, ?array $rule = null, ?string $loss = null) use (&$findings, &$summary, &$losses): void {
            $summary[$category]++;
            $finding = ['category' => $category, 'subject' => $subject, 'message' => $message];
            if (is_array($rule) && is_string($rule['id'] ?? null)) {
                $finding['rule'] = $rule['id'];
            }
            if ($loss !== null && $loss !== '') {
                $finding['loss'] = $loss;
                $losses[$loss] = true;
            }
            $findings[] = $finding;
        };

        $source = is_array($recipe['source'] ?? null) ? $recipe['source'] : [];
        $target = is_array($recipe['target'] ?? null) ? $recipe['target'] : [];
        if (($profile['id'] ?? null) !== ($source['profile'] ?? null)
            || !$this->versionMatches((string)($profile['version'] ?? ''), (string)($source['version'] ?? ''))) {
            $add('blocked', 'source.profile', 'Source semantic profile does not satisfy the recipe constraint.');
            $missingRequired[] = (string)($source['profile'] ?? '') . '@' . (string)($source['version'] ?? '');
        }
        if (is_string($source['digest'] ?? null) && $this->digest($profile) !== $source['digest']) {
            $add('blocked', 'source.profile.digest', 'Source semantic profile digest does not satisfy the recipe constraint.');
            $missingRequired[] = (string)($source['profile'] ?? '') . '@' . (string)($source['version'] ?? '')
                . '#' . $source['digest'];
        }
        if (!$this->profileSupported($capabilities, $target)) {
            $add('blocked', 'target.profile', 'Target adapter does not declare the recipe target semantic profile.');
            $missingRequired[] = (string)($target['profile'] ?? '') . '@' . (string)($target['version'] ?? '');
        }
        if (is_string($target['adapter'] ?? null)
            && ($capabilities['tool']['name'] ?? null) !== $target['adapter']) {
            $add('blocked', 'target.adapter', 'Target adapter identity does not satisfy the recipe constraint.');
            $missingRequired[] = 'adapter.' . $target['adapter'];
        }

        $requires = is_array($recipe['requires'] ?? null) ? $recipe['requires'] : [];
        foreach (is_array($requires['extensions'] ?? null) ? $requires['extensions'] : [] as $extension) {
            if (!isset($supportedExtensions[$extension])) {
                $unsupportedExtensions[] = $extension;
                $add('blocked', 'extension.' . $extension, 'Required extension is not supported by the target adapter.');
            }
        }
        foreach (is_array($requires['transforms'] ?? null) ? $requires['transforms'] : [] as $transform) {
            if (!isset($supportedTransforms[$transform])) {
                $add('blocked', 'transform.' . $transform, 'Required transform is not supported by the target adapter.');
                $missingRequired[] = 'transform.' . $transform;
            }
        }

        $entities = is_array($profile['entities'] ?? null) ? $profile['entities'] : [];
        foreach ($entities as $entityId => $entity) {
            if (!is_array($entity)) continue;
            $inventory['entities']++;
            $inventory['fields'] += count(is_array($entity['fields'] ?? null) ? $entity['fields'] : []);
            $inventory['relations'] += count(is_array($entity['relations'] ?? null) ? $entity['relations'] : []);
            $inventory['assets'] += count(is_array($entity['assets'] ?? null) ? $entity['assets'] : []);
            $entityRule = is_array($rulesBySubject[$entityId] ?? null) ? $rulesBySubject[$entityId] : null;
            $entityRequired = $this->containsRequired($entity['fields'] ?? [])
                || $this->containsRequired($entity['relations'] ?? [])
                || $this->containsRequired($entity['assets'] ?? []);
            $this->evaluate(['entity' => $entityId], $entityRule, $entityRequired,
                $this->targetEndpoint(['entity' => $entityId], $entityRule), $capabilities,
                $supportedTransforms, $rulesBySubject, $add, $missingRequired);

            foreach (['fields' => 'field', 'relations' => 'relation', 'assets' => 'asset'] as $group => $kind) {
                foreach (is_array($entity[$group] ?? null) ? $entity[$group] : [] as $itemId => $definition) {
                    $endpoint = ['entity' => $entityId, $kind => $itemId];
                    $subject = $this->endpointSubject($endpoint);
                    $rule = is_array($rulesBySubject[$subject] ?? null) ? $rulesBySubject[$subject] : null;
                    $required = is_array($definition) && !empty($definition['required']);
                    if ($group === 'fields' && is_array($definition) && is_string($definition['format'] ?? null)
                        && !isset($supportedContentFormats[$definition['format']])) {
                        $targetFormat = ($rule['kind'] ?? null) === 'content.convert'
                            && is_array($rule['params'] ?? null) ? ($rule['params']['format'] ?? null) : null;
                        if (!is_string($targetFormat) || !isset($supportedContentFormats[$targetFormat])) {
                            $mustMap = $required || (is_array($rule) && ($rule['required'] ?? true) !== false);
                            $add($mustMap ? 'blocked' : 'skipped', $subject,
                                'The target adapter does not support the field content format.', $rule,
                                is_array($rule) && is_string($rule['loss'] ?? null) ? $rule['loss'] : null);
                            if ($mustMap) $missingRequired[] = $subject;
                            continue;
                        }
                    }
                    $this->evaluate($endpoint, $rule, $required, $this->targetEndpoint($endpoint, $entityRule),
                        $capabilities, $supportedTransforms, $rulesBySubject, $add, $missingRequired);
                }
            }
        }

        $failOn = array_fill_keys(is_array($recipe['lossPolicy']['failOn'] ?? null) ? $recipe['lossPolicy']['failOn'] : [], true);
        foreach (array_keys($losses) as $loss) {
            if (isset($failOn[$loss])) {
                $add('blocked', 'loss.' . $loss, 'The recipe loss policy forbids this loss level.', null, $loss);
            }
        }

        $inputsDigest = $this->digest(['profile' => $profile, 'capabilities' => $capabilities, 'recipe' => $recipe, 'artifacts' => $inputs]);
        $planSteps = [];
        foreach ($rules as $rule) {
            if (!is_array($rule) || !in_array($rule['kind'] ?? null, self::ALLOWED_OPERATIONS, true) || ($rule['kind'] ?? null) === 'value.drop') continue;
            if (($rule['kind'] ?? null) === 'content.convert' && !isset($supportedTransforms[$rule['converter'] ?? ''])) continue;
            $planSteps[] = $rule;
        }
        $plan = [
            'version' => '1.0.0',
            'recipe' => ['id' => $recipe['id'], 'version' => $recipe['version']],
            'source' => ['id' => $profile['id'], 'version' => $profile['version']],
            'target' => ['id' => $target['profile'], 'version' => $target['version']],
            'inputsDigest' => $inputsDigest,
            'inputs' => $inputs,
            'expectedOutputs' => $summary,
            'lossPolicy' => $recipe['lossPolicy'] ?? ['allowed' => ['none'], 'failOn' => ['security']],
            'steps' => $planSteps,
        ];
        $plan['digest'] = $this->digest($plan);

        $status = $summary['blocked'] > 0 ? 'incompatible'
            : (($summary['fallback'] > 0 || $summary['skipped'] > 0) ? 'partial' : 'compatible');
        $lossList = array_keys($losses);
        sort($lossList, SORT_STRING);
        $missingRequired = array_values(array_unique($missingRequired));
        sort($missingRequired, SORT_STRING);
        $unsupportedExtensions = array_values(array_unique($unsupportedExtensions));
        sort($unsupportedExtensions, SORT_STRING);
        $tool = is_array($capabilities['tool'] ?? null) ? $capabilities['tool'] : [];
        $report = [
            'spec' => ['name' => 'sitepack-compatibility-report', 'version' => '1.0.0'],
            'status' => $status,
            'source' => ['id' => $profile['id'], 'version' => $profile['version']],
            'target' => ['id' => $tool['name'], 'version' => $tool['version']],
            'recipe' => ['id' => $recipe['id'], 'version' => $recipe['version']],
            'inputsDigest' => $inputsDigest,
            'planDigest' => $plan['digest'],
            'inventory' => $inventory,
            'summary' => $summary,
            'losses' => $lossList,
            'missingRequired' => $missingRequired,
            'unsupportedExtensions' => $unsupportedExtensions,
            'findings' => $findings,
        ];

        return ['plan' => $plan, 'report' => $report];
    }

    /** @param array<string,mixed> $sourceEndpoint @param array<string,mixed>|null $rule @param array<string,mixed> $inheritedTarget */
    private function evaluate(array $sourceEndpoint, ?array $rule, bool $required, array $inheritedTarget,
        array $capabilities, array $supportedTransforms, array $rulesBySubject, callable $add, array &$missingRequired): void
    {
        $subject = $this->endpointSubject($sourceEndpoint);
        if ($rule === null && $this->capabilitySupports($capabilities, $inheritedTarget)) {
            $entityRule = is_array($rulesBySubject[$sourceEndpoint['entity']] ?? null) ? $rulesBySubject[$sourceEndpoint['entity']] : null;
            if (($entityRule['kind'] ?? null) !== 'entity.map') $entityRule = null;
            $transformed = $inheritedTarget['entity'] !== $sourceEndpoint['entity'];
            $add($transformed ? 'transformed' : 'exact', $subject,
                $transformed ? 'Supported through the entity mapping.' : 'Supported without transformation.', $entityRule);
            return;
        }
        if ($rule === null) {
            $add($required ? 'blocked' : 'skipped', $subject,
                $required ? 'Required subject has no supported target mapping.' : 'Optional subject is unsupported.');
            if ($required) $missingRequired[] = $subject;
            return;
        }
        $ruleRequired = ($rule['required'] ?? true) !== false;
        $kind = (string)($rule['kind'] ?? '');
        if (!in_array($kind, self::ALLOWED_OPERATIONS, true)) {
            $add($ruleRequired ? 'blocked' : 'skipped', $subject,
                $ruleRequired ? 'Unknown required mapping operation.' : 'Unknown optional mapping operation was skipped.', $rule, $rule['loss'] ?? null);
            if ($ruleRequired) $missingRequired[] = $subject;
            return;
        }
        if ($kind === 'content.convert' && !isset($supportedTransforms[$rule['converter'] ?? ''])) {
            $add($ruleRequired ? 'blocked' : 'skipped', $subject,
                'The target adapter does not provide the requested content converter.', $rule, $rule['loss'] ?? null);
            if ($ruleRequired) $missingRequired[] = $subject;
            return;
        }
        if ($kind === 'value.drop') {
            $add($ruleRequired ? 'blocked' : 'skipped', $subject,
                $ruleRequired ? 'A required rule cannot drop data.' : 'The recipe explicitly drops this optional subject.', $rule, $rule['loss'] ?? 'content');
            if ($ruleRequired) $missingRequired[] = $subject;
            return;
        }
        $target = is_array($rule['to'] ?? null) ? $rule['to'] : $inheritedTarget;
        if (!$this->capabilitySupports($capabilities, $target)) {
            $add(($ruleRequired || $required) ? 'blocked' : 'skipped', $subject, 'The mapped target subject is unsupported.', $rule, $rule['loss'] ?? null);
            if ($ruleRequired || $required) $missingRequired[] = $subject;
            return;
        }
        $add($kind === 'fallback.wrap' ? 'fallback' : 'transformed', $subject,
            $kind === 'fallback.wrap' ? 'The recipe preserves this subject through a fallback.' : 'The recipe transforms this subject.',
            $rule, $rule['loss'] ?? 'none');
    }

    private function versionMatches(string $version, string $constraint): bool
    {
        if ($constraint === '*' || $constraint === $version) return true;
        return preg_match('/^(\d+)\.x$/', $constraint, $match) === 1 && str_starts_with($version, $match[1] . '.');
    }

    /** @param array<string,mixed> $target */
    private function profileSupported(array $capabilities, array $target): bool
    {
        foreach ($capabilities['mapping']['semanticProfiles'] ?? [] as $profile) {
            if (!is_array($profile) || ($profile['id'] ?? null) !== ($target['profile'] ?? null)) continue;
            if (isset($target['digest']) && ($profile['digest'] ?? null) !== $target['digest']) continue;
            foreach ($profile['versions'] ?? [] as $version) {
                if ($this->versionMatches((string)($target['version'] ?? ''), (string)$version)
                    || $this->versionMatches((string)$version, (string)($target['version'] ?? ''))) return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $endpoint */
    private function capabilitySupports(array $capabilities, array $endpoint): bool
    {
        $entity = $capabilities['mapping']['entities'][$endpoint['entity'] ?? ''] ?? null;
        if (!is_array($entity)) return false;
        foreach (['field' => 'fields', 'relation' => 'relations', 'asset' => 'assets'] as $key => $group) {
            if (isset($endpoint[$key])) return in_array($endpoint[$key], $entity[$group] ?? [], true);
        }
        return true;
    }

    /** @param array<string,mixed> $endpoint */
    private function endpointSubject(array $endpoint): string
    {
        foreach (['field', 'relation', 'asset'] as $key) {
            if (isset($endpoint[$key])) return $endpoint['entity'] . '.' . $key . '.' . $endpoint[$key];
        }
        return (string)($endpoint['entity'] ?? '');
    }

    /** @param array<string,mixed> $endpoint @param array<string,mixed>|null $entityRule @return array<string,mixed> */
    private function targetEndpoint(array $endpoint, ?array $entityRule): array
    {
        if (is_array($entityRule['to'] ?? null)) $endpoint['entity'] = $entityRule['to']['entity'];
        return $endpoint;
    }

    private function containsRequired(mixed $definitions): bool
    {
        if (!is_array($definitions)) return false;
        foreach ($definitions as $definition) if (is_array($definition) && !empty($definition['required'])) return true;
        return false;
    }

    private function digest(mixed $value): string
    {
        return 'sha256:' . hash('sha256', $this->canonicalJson($value));
    }

    private function canonicalJson(mixed $value): string
    {
        $encoded = json_encode($this->canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (!is_string($encoded)) throw new RuntimeException('Unable to canonicalize mapping input.');
        return $encoded;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = $this->canonicalize($item);
        return $value;
    }
}
