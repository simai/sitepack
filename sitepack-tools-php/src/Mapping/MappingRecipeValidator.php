<?php

declare(strict_types=1);

namespace SitePack\Mapping;

final class MappingRecipeValidator
{
    private const ALLOWED = [
        'entity.map', 'field.copy', 'field.rename', 'field.cast', 'value.default',
        'enum.map', 'relation.map', 'asset.bind', 'content.convert',
        'fallback.wrap', 'value.drop',
    ];

    /** @return array<int,array{level:string,code:string,message:string}> */
    public function validate(object $recipe): array
    {
        $messages = [];
        foreach (is_array($recipe->rules ?? null) ? $recipe->rules : [] as $rule) {
            if (!is_object($rule) || in_array($rule->kind ?? null, self::ALLOWED, true)) continue;
            $optional = ($rule->required ?? true) === false;
            $messages[] = [
                'level' => $optional ? 'warning' : 'error',
                'code' => $optional ? 'MAPPING_OPERATION_OPTIONAL_UNKNOWN' : 'MAPPING_OPERATION_REQUIRED_UNKNOWN',
                'message' => sprintf("Mapping rule '%s' uses unknown operation '%s'.", (string)($rule->id ?? ''), (string)($rule->kind ?? '')),
            ];
        }
        return $messages;
    }
}
