<?php

declare(strict_types=1);

namespace Src\Support\Infrastructure\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Support\Domain\Models\Tenant;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'slug' => $slug,
            'domain' => null,
            'name' => [
                'ar' => 'مؤسسة '.fake()->unique()->word(),
                'en' => fake()->unique()->company(),
            ],
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
