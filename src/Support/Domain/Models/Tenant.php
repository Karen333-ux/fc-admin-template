<?php

declare(strict_types=1);

namespace Src\Support\Domain\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Src\Support\Infrastructure\Database\Factories\TenantFactory;

/**
 * نواة مشتركة — مش سياق. (ADR-011)
 *
 * `Tenant` **مابيستخدمش** BelongsToTenant — هو اللي بيعرّف الحد. (ADR-002)
 *
 * الموديل ده في Domain، فممنوع يستورد facade أو HTTP أو Livewire أو كلاس
 * Filament محسوس. الوراثة من Eloquent\Model والـ traits الخاصة بالتخزين مسموحة. (ADR-012)
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasTranslations;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'domain',
        'name',
        'logo_path',
        'primary_color',
        'is_active',
        'trial_ends_at',
    ];

    /**
     * موديل المستخدم بيتقرا من الكونفيج — عشان Support ماتستوردش من Contexts (ADR-011).
     *
     * @return BelongsToMany<Model, $this>
     */
    public function users(): BelongsToMany
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('authorization.user_model');

        return $this->belongsToMany(
            $userModel,
            'tenant_user',
            'tenant_id',
            'user_id',
        )->withPivot('joined_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'trial_ends_at' => 'datetime',
        ];
    }

    /** @return Factory<self> */
    protected static function newFactory(): Factory
    {
        return TenantFactory::new();
    }
}
