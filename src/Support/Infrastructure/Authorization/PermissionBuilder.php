<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Authorization;

/**
 * بيفكّ config/authorization.php لأسماء صلاحيات نهائية. (docs/02 بند ٣)
 */
final class PermissionBuilder
{
    /**
     * كل أسماء الصلاحيات المعرّفة في الكتالوج.
     *
     * @return list<string>
     */
    public function allPermissionNames(): array
    {
        /** @var array<string, string> $pages */
        $pages = config('authorization.pages', []);
        /** @var array<string, string> $widgets */
        $widgets = config('authorization.widgets', []);

        return array_values(array_unique([
            ...$this->resourcePermissionNames(),
            ...array_keys($pages),
            ...array_keys($widgets),
        ]));
    }

    /**
     * بيفكّ أنماط زي `*.users` لأسماء حقيقية.
     *
     * @param  list<string>  $patterns
     * @return list<string>
     */
    public function expandPatterns(array $patterns): array
    {
        $all = $this->allPermissionNames();
        $matched = [];

        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return $all;
            }

            foreach ($all as $name) {
                if ($this->matches($pattern, $name)) {
                    $matched[] = $name;
                }
            }
        }

        return array_values(array_unique($matched));
    }

    /**
     * الصلاحيات مجمّعة للعرض في شاشة الأدوار.
     *
     * @return array<string, list<string>>
     */
    public function groups(): array
    {
        $groups = [];

        /** @var array<string, array{group?: string, actions?: list<string>|null, extra?: list<string>}> $resources */
        $resources = config('authorization.resources', []);

        foreach ($resources as $resource => $definition) {
            $group = $definition['group'] ?? 'other';

            foreach ($this->actionsFor($definition) as $action) {
                $groups[$group][] = $action.config('authorization.separator').$resource;
            }
        }

        foreach (['pages', 'widgets'] as $catalog) {
            /** @var array<string, string> $entries */
            $entries = config("authorization.{$catalog}", []);

            foreach ($entries as $name => $group) {
                $groups[$group][] = $name;
            }
        }

        return $groups;
    }

    /**
     * القسمة على **أول فاصل بس** — الشق الشمال فعل واليمين مورد.
     * مفيش تخمين هل ده فعل ولا مورد. (ADR-001)
     */
    private function matches(string $pattern, string $name): bool
    {
        $separator = (string) config('authorization.separator');

        [$actionPattern, $resourcePattern] = array_pad(explode($separator, $pattern, 2), 2, null);
        [$action, $resource] = array_pad(explode($separator, $name, 2), 2, null);

        // اسم من غير فاصل — مقارنة حرفية بس
        if ($resourcePattern === null || $resource === null) {
            return $pattern === $name;
        }

        $matches = static fn (string $p, string $value): bool => $p === '*' || $p === $value;

        return $matches($actionPattern, $action) && $matches($resourcePattern, $resource);
    }

    /**
     * @return list<string>
     */
    private function resourcePermissionNames(): array
    {
        $separator = (string) config('authorization.separator');
        $names = [];

        /** @var array<string, array{group?: string, actions?: list<string>|null, extra?: list<string>}> $resources */
        $resources = config('authorization.resources', []);

        foreach ($resources as $resource => $definition) {
            foreach ($this->actionsFor($definition) as $action) {
                $names[] = $action.$separator.$resource;
            }
        }

        return $names;
    }

    /**
     * actions فاضية أو null → default_actions. لو محددة → دي بس. زائد extra دايماً.
     *
     * @param  array{group?: string, actions?: list<string>|null, extra?: list<string>}  $definition
     * @return list<string>
     */
    private function actionsFor(array $definition): array
    {
        /** @var list<string> $defaults */
        $defaults = config('authorization.default_actions', []);

        $actions = $definition['actions'] ?? null;
        $actions = is_array($actions) ? $actions : $defaults;

        return array_values(array_unique([...$actions, ...($definition['extra'] ?? [])]));
    }
}
