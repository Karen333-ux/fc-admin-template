<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Domain\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;
use Src\Contexts\Identity\Database\Factories\UserFactory;
use Src\Support\Application\Contracts\LocaleDefaults;
use Src\Support\Domain\Models\Tenant;

/**
 * ⚠️ `users` **مافيهوش** `tenant_id`، والموديل ده **مابيستخدمش** `BelongsToTenant`. (ADR-002)
 *
 * العضوية بجدول `tenant_user` بس. العزل بيتحقق **بفلترة على العلاقة**،
 * مش بـ global scope — شوف `UserResource::getEloquentQuery()`.
 *
 * الموديل في `Domain`: بيعلن قدرات عبر واجهات الإطار (FilamentUser / HasTenants)
 * — وده مسموح — لكنه **مابينادیش** `Filament::` ولا أي facade. (ADR-012)
 */
class User extends Authenticatable implements FilamentUser, HasLocalePreference, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return BelongsToMany<Tenant, $this> */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user', 'user_id', 'tenant_id')
            ->withPivot('joined_at');
    }

    /**
     * الدخول للوحة — قدرة، مش اسم صلاحية.
     *
     * `app(Gate::class)` مش `Gate::` facade: الموديل في Domain. (ADR-012)
     * من غير `access.panel.admin` في الكتالوج، محدش غير super_admin بيفتح اللوحة. (ADR-003)
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return app(Gate::class)
            ->forUser($this)
            ->allows('access.panel.'.$panel->getId());
    }

    /** @return Collection<int, Tenant> */
    public function getTenants(Panel $panel): Collection
    {
        return $this->tenants;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->tenants()->whereKey($tenant->getKey())->exists();
    }

    /**
     * لغة المستقبِل المفضّلة. (docs/09 بند ٤)
     *
     * Laravel بيلفّ `toMail`/`toDatabase` في `App::setLocale()` بيها
     * تلقائياً، فالرسالة بتطلع بلغة **المستقبِل** مش لغة اللي بعت.
     * مفيش `->locale()` يدوي في أي إشعار.
     *
     * ⚠️ الاحتياطي بيعدّي على عقد `LocaleDefaults` في `Support` مش على
     *    `GeneralSettings` مباشرةً: `docs/09` بند ٤ بيكتبها بالاستيراد
     *    المباشر، وده بيخلّي Identity يستورد موديل من Settings —
     *    ممنوع في `CLAUDE.md`. العقد بيعكس الاتجاه. (نمط ADR-022)
     *
     * ⚠️ `app(...)` مش facade — الموديل في Domain. (ADR-012)
     */
    public function preferredLocale(): string
    {
        $locale = $this->locale;

        if (is_string($locale) && $locale !== '') {
            return $locale;
        }

        return app(LocaleDefaults::class)->default();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return Factory<self> */
    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }
}
