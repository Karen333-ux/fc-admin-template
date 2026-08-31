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

            // `users.password` عمود NOT NULL — من غير الحقل ده صفحة الإنشاء
            // بتفتح وبتفشل عند الحفظ بقيد قاعدة البيانات.
            //
            // ملاحظة على ADR-009: `dehydrated()` هنا **مسألة ترطيب مش تفويض**
            // (سيبها فاضية على التعديل = ماتغيّرش كلمة المرور). ADR-009 بيتكلم
            // عن الحقول الحسّاسة المربوطة بصلاحية، وبيفرض `saved()` هناك.
            TextInput::make('password')
                ->label(__('identity::identity.fields.password'))
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->maxLength(255),
        ]);
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
