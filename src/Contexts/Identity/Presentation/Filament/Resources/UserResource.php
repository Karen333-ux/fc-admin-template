<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Presentation\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Infrastructure\Validation\PasswordHistoryValidation;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\CreateUser;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\EditUser;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\ListUserActivities;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\ListUsers;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\ViewUser;
use Src\Support\Application\Contracts\TenantContext;
use Src\Support\Presentation\Filament\Navigation\NavigationGroup;
use STS\FilamentImpersonate\Actions\Impersonate;

final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    /**
     * المجموعة بتتحدّد بحالة الـ enum نفسها — `getNavigationGroup()` توقيعها
     * `string|UnitEnum|null` في v5، والترتيب بييجي من ترتيب تعريف الحالات.
     * (docs/07 بند ٢)
     */
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Identity;

    protected static ?int $navigationSort = 10;

    /** عنوان السجل في نتائج البحث الشامل (docs/07 بند ٥) */
    protected static ?string $recordTitleAttribute = 'name';

    /**
     * علاقة الملكية للمستأجر عند Filament.
     *
     * الافتراضي عند Filament هو `tenant` (BelongsTo مفرد) — وده صح للموديلات
     * اللي بتستخدم `BelongsToTenant` بتاعنا. لكن `User` **مالوش** `tenant_id`
     * ومالوش العلاقة دي (ADR-002)، فلازم نقوله صراحةً إن العضوية اسمها `tenants`.
     *
     * من غير السطر ده Filament بيرمي `LogicException` أول ما تتفتح أي صفحة
     * للمورد جوه لوحة عندها `->tenant()`. (ADR-016)
     */
    protected static ?string $tenantOwnershipRelationshipName = 'tenants';

    /**
     * ⚠️ **أهم دالة في الشريحة.**
     *
     * `User` مالوش `tenant_id` ومالوش global scope خاص بينا (ADR-002)، فالعزل هنا
     * **يدوي وصريح** — فلترة على العضوية. الاختبار في docs/23 بند ٦-هـ هو
     * اللي بيفرّق بين شريحة شغّالة وشريحة مسرّبة.
     *
     * **متحقّق منه بالقياس:** الـ global scope بتاع Filament للمستأجر **مش**
     * متسجّل على `User` خالص — الـ scope الوحيد على الموديل هو `SoftDeletingScope`،
     * وعدد شروط `exists` في الاستعلام واحد سواء جوه اللوحة أو برّاها.
     *
     * يعني **مفيش شبكة أمان تحت السطر ده**. لو اتشال، العزل بيقع بالكامل ومفيش
     * حاجة تانية بتمسك. ده بالظبط اللي `docs/23` بند ٤ بيحذّر منه. (ADR-016)
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas(
                'tenants',
                fn (Builder $query) => $query->whereKey(app(TenantContext::class)->id()),
            )
            // التحميل المسبق للأدوار — `docs/08` بند ٥-أ.
            //
            // ⚠️ **مش ده اللي بيمنع N+1.** Filament بيحمّل علاقات الأعمدة
            //    لوحده: `Column::applyEagerLoading()` بيضيف `->with([$relation])`
            //    لو العلاقة مش متحمّلة أصلاً
            //    (`vendor/filament/tables/src/Columns/Concerns/InteractsWithTableQuery.php`
            //    سطر ٤١-٥٤). اتحقّقنا: بشيل السطر ده عدد الاستعلامات مابيزدش.
            //
            //    الفايدة الحقيقية هنا إن السطر ده **بيسبق** Filament، وبما إنه
            //    بيتخطّى العلاقة المتحمّلة، بنكسب تحديد الأعمدة `id,name` بدل
            //    جلب صف الدور كامل. ده عقد `docs/08` بند ٥-أ حرفياً.
            ->with(['roles:id,name']);
    }

    /**
     * الأعمدة اللي البحث الشامل بيدوّر فيها. (docs/07 بند ٥)
     *
     * ⚠️ مفيش `phone` — العمود ده مش موجود في `users` (شوف الهجرة).
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    /**
     * ⚠️⚠️ **أخطر دالة في الشريحة دي.**
     *
     * الافتراضي في Filament هو `getGlobalSearchEloquentQuery() => static::getEloquentQuery()`
     * (`vendor/filament/filament/src/Resources/Resource/Concerns/HasGlobalSearch.php`)،
     * يعني فلتر العضوية اللي في `getEloquentQuery()` بيتطبّق على البحث كمان.
     *
     * `parent::` هنا **إلزامي**. لو اتكتب `User::query()` بدلها، البحث الشامل
     * بيبقى نافذة على مستخدمين كل المستأجرين — و`User` مالوش global scope
     * يمسكها (ADR-016: «مفيش شبكة أمان تحت السطر ده»). فيه اختبار سلبي
     * بيثبت إن مستأجر ب مش بيلاقي مستخدم مستأجر أ.
     *
     * الـ eager load بيمنع N+1 في التفاصيل تحت.
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['roles']);
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var User $record */
        return [
            __('identity::identity.fields.email') => $record->email,
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return self::getUrl('edit', ['record' => $record]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('identity::identity.fields.name'))
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label(__('identity::identity.fields.email'))
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            // ⚠️ حقل حسّاس — مربوط بصلاحية `reset_password.users` المنفصلة.
            //
            // ADR-009 بيقول: نفس القدرة في `visible()` و`saved()`، والـ fallback
            // لاسم الكلاس (`$record ?? User::class`) عشان صفحة الإنشاء.
            //
            // ⚠️ لكن `saved()` **لوحدها مش كفاية هنا**، وده مكمّل لـ ADR-014:
            // `isDehydrated()` بترجّع `$this->isDehydrated ?? $this->isSaved()`.
            // بما إن الحقل ده لازم يحدّد `dehydrated()` (سيبها فاضية = ماتغيّرش
            // كلمة المرور)، فالقيمة الصريحة دي **بتغلب** `saved()` على حمولة
            // الحالة. يعني لو حطّينا التفويض في `saved()` بس، مستخدم غير مخوّل
            // كان هيقدر يبعت الحقل وهو **بيتحفظ** فعلاً.
            //
            // عشان كده الشرطين مدموجين جوّه `dehydrated()`: مليان **و** مخوّل.
            // `saved()` باقية عشان الشكل المعياري ولحفظ العلاقات.
            TextInput::make('password')
                ->label(__('identity::identity.fields.password'))
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->visible(fn (?User $record): bool => self::canResetPassword($record))
                ->saved(fn (?User $record): bool => self::canResetPassword($record))
                ->dehydrated(fn (?string $state, ?User $record): bool => filled($state)
                    && self::canResetPassword($record))
                ->maxLength(255)
                // سياسة كلمات المرور — docs/12 بند ٤. `Password::default()`
                // بيقرا من `Password::defaults()` المسجّلة في AppServiceProvider.
                ->rule(Password::default())
                // منع إعادة استخدام آخر N كلمة مرور — بترجع فوراً على صفحة
                // الإنشاء لأن $record فاضي وقتها (ADR-009).
                ->rule(fn (?User $record) => PasswordHistoryValidation::rule($record)),
        ]);
    }

    /**
     * هل المستخدم الحالي يقدر يعيّن كلمة مرور السجل ده؟
     *
     * `$record ?? User::class` — على صفحة الإنشاء السؤال بيروح للـ Policy
     * بالكلاس، مش بـ `$record !== null &&` اللي بيمنع الحفظ وقت الإنشاء. (ADR-009)
     */
    private static function canResetPassword(?User $record): bool
    {
        return auth()->user()?->can('resetPassword', $record ?? User::class) ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('identity::identity.fields.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('identity::identity.fields.email'))
                    ->searchable()
                    ->sortable(),

                // ⚠️ اللون مابيقفش لوحده: الشارة فيها اسم الدور كنص، فالمعلومة
                //    توصل حتى لو المستخدم مش شايف الألوان. (docs/08 بند ٢ قاعدة ٣)
                TextColumn::make('roles.name')
                    ->label(__('identity::identity.fields.roles'))
                    ->badge()
                    ->separator('،')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('identity::identity.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // الترتيب الافتراضي محدد صراحةً مش متروك للقاعدة (docs/08 بند ٨)
            ->defaultSort('created_at', 'desc')
            // الصف كله قابل للنقر — التفويض لسه على صفحة التعديل نفسها
            ->recordUrl(fn (User $record): string => self::getUrl('edit', ['record' => $record]))
            ->filters([
                SelectFilter::make('roles')
                    ->label(__('identity::identity.fields.roles'))
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),

                // ⚠️ `indicateUsing()` إلزامية لأي فلتر مخصص (docs/08 بند ٣):
                //    من غيرها المستخدم بيفلتر، ينسى، وبعدين يبلّغ إن البيانات ناقصة.
                Filter::make('created_at')
                    ->label(__('common.created_between'))
                    ->schema([
                        DatePicker::make('from')->label(__('common.from')),
                        DatePicker::make('until')->label(__('common.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date),
                        ))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make(
                                __('common.from').': '.$data['from'],
                            )->removeField('from');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = Indicator::make(
                                __('common.until').': '.$data['until'],
                            )->removeField('until');
                        }

                        return $indicators;
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            // الحالة الفارغة بتفرّق بين «مفيش بيانات» و«الفلتر مارجّعش حاجة»
            // (docs/08 بند ٧) — الرسالة العامة من إعدادات الجدول بتتدهس هنا.
            ->emptyStateHeading(fn (Table $table): string => $table->isFiltered()
                ? __('table.empty.no_results')
                : __('identity::identity.empty.heading'))
            ->emptyStateDescription(fn (Table $table): string => $table->isFiltered()
                ? __('table.empty.no_results_description')
                : __('identity::identity.empty.description'))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('identity::identity.empty.cta'))
                    ->authorize('create'),
            ])
            ->recordActions([
                // الإخفاء تجربة استخدام — الـ Policy هي الأمان. (CLAUDE.md بند ٣)
                ViewAction::make()->authorize('view'),
                EditAction::make()->authorize('update'),
                // انتحال شخصية — docs/12 بند ٣. authorize('impersonate') بيودّي
                // على UserPolicy::impersonate() بكل قواعدها (نفس الحساب، مدير
                // عام، انتحال جوّه انتحال) — الحزمة بتضيف حواجزها الخاصة كمان
                // (isSoftDeleted, canImpersonate/canBeImpersonated) لكن دي مش
                // بديل عن الـ Policy، البوابة الحقيقية هي الـ Policy.
                Impersonate::make()
                    ->label(__('identity::identity.actions.impersonate'))
                    ->authorize('impersonate')
                    ->requiresConfirmation()
                    ->redirectTo(fn (): string => Dashboard::getUrl()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // بيشغّل الـ Policy على كل سجل ويسقط اللي بيفشل — مش فحص واحد شامل
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
            'activity' => ListUserActivities::route('/{record}/activity'),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('identity::identity.resource.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity::identity.resource.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('identity::identity.resource.plural');
    }
}
