<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Presentation\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\CreateUser;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\EditUser;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\ListUsers;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages\ViewUser;
use Src\Support\Application\Contracts\TenantContext;

final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

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
            );
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
                ->maxLength(255),
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

                TextColumn::make('created_at')
                    ->label(__('identity::identity.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                // الإخفاء تجربة استخدام — الـ Policy هي الأمان. (CLAUDE.md بند ٣)
                ViewAction::make()->authorize('view'),
                EditAction::make()->authorize('update'),
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
