<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Domain\Models;

use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;
use Src\Contexts\Identity\Database\Factories\UserFactory;
use Src\Support\Application\Contracts\LocaleDefaults;
use Src\Support\Application\Contracts\PanelAccess;
use Src\Support\Domain\Models\Tenant;

/**
 * ⚠️ `users` **مافيهوش** `tenant_id`، والموديل ده **مابيستخدمش** `BelongsToTenant`. (ADR-002)
 *
 * العضوية بجدول `tenant_user` بس. العزل بيتحقق **بفلترة على العلاقة**،
 * مش بـ global scope — شوف `UserResource::getEloquentQuery()`.
 *
 * الموديل في `Domain`: بيعلن قدرات عبر واجهات الإطار (FilamentUser / HasTenants
 * / HasAppAuthentication / HasAppAuthenticationRecovery) — وده مسموح — لكنه
 * **مابينادیش** `Filament::` ولا أي facade. (ADR-012)
 *
 * @property ?array<string> $two_factor_recovery_codes متشفّرة — cast بـ encrypted:array
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasLocalePreference, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use LogsActivity;
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
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /** @return HasMany<UserDevice, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /** @return HasMany<PasswordHistory, $this> */
    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    /** @return BelongsToMany<Tenant, $this> */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user', 'user_id', 'tenant_id')
            ->withPivot('joined_at');
    }

    /**
     * الدخول للوحة — قدرة، مش اسم صلاحية.
     *
     * `app(PanelAccess::class)` مش كلاس محسوس: الموديل في Domain. (ADR-012)
     * من غير `access.panel.admin` في الكتالوج، محدش غير super_admin بيفتح اللوحة. (ADR-003)
     *
     * ⚠️ الفحص بيدور على **كل** مستأجري المستخدم، مش على المستأجر الحالي.
     *    Filament بينادي الدالة دي وقت الدخول — قبل ما يتحدد مستأجر — وإسناد
     *    الدور مخصّص بمستأجر، فسؤال «في المستأجر الحالي؟» بيرجع false دايماً
     *    وقتها. (ADR-026)
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return app(PanelAccess::class)->allowsInAnyTenant(
            $this,
            'access.panel.'.$panel->getId(),
            $this->tenants->modelKeys(),
        );
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

    /**
     * إعدادات سجل النشاط. (docs/11 بند ٤)
     *
     * ⚠️ `logOnly` بالأعمدة الحقيقية بس — `docs/11` بيفترض عمود `status`
     *    مش موجود أصلاً في `users`. `password`/`remember_token` مستبعدين
     *    بالقصد رغم إنهم مش في القايمة أصلاً — التغيير فيهم بيتسجّل
     *    بحدث تاني (تغيير كلمة مرور) مش بحدث `updated` العام.
     *
     * ⚠️ تخصيب `tenant_id`/`request_id`/`ip` **مش هنا** — بيحصل مركزياً
     *    في `AppServiceProvider::enrichActivityLog()` عبر
     *    `LogActivityAction::beforeLogging()`. الموديل في `Domain` ومابينادیش
     *    `request()->ip()` ولا أي اعتماد HTTP. (ADR-012)
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'locale'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('identity')
            ->setDescriptionForEvent(fn (string $event): string => __("audit.events.user.{$event}"));
    }

    /**
     * السرّ وأكواد الاسترجاع مشفّرين وقت التخزين — نفس معيار docs/12 بند ١
     * ("// مشفّر")، عبر cast الإطار العادي مش استيراد `Crypt` صراحةً. (ADR-012)
     */
    public function getAppAuthenticationSecret(): ?string
    {
        return $this->two_factor_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->two_factor_secret = $secret;
        $this->save();
    }

    /**
     * اسم صاحب الحساب اللي بيظهر في تطبيق المصادقة (Google Authenticator
     * وشبهه) — البريد أوضح تعريف. (docs/12 بند ١)
     */
    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @return ?array<string> */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->two_factor_recovery_codes;
    }

    /** @param  ?array<string>  $codes */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->two_factor_recovery_codes = $codes;
        $this->save();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    /** @return Factory<self> */
    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }
}
