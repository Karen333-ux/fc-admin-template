<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Presentation\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Src\Contexts\Settings\Domain\Settings\MailSettings;
use Src\Support\Presentation\Filament\Navigation\NavigationGroup;

final class ManageMail extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string $settings = MailSettings::class;

    /** كل صفحات الإعدادات تحت مجموعة «النظام». (docs/07 بند ٢) */
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 30;

    /**
     * الصفحة دي **مالهاش موديل**، فالسؤال بيروح للـ Gate مش للـ Policy.
     * (ADR-001 · ADR-003 · ADR-019 — نفس نمط `ManageAppearance`)
     *
     * ⚠️ `canAccess()` هي البوابة الأمنية، مش إخفاء اللينك من القايمة.
     */
    public static function canAccess(): bool
    {
        return Gate::allows('manage_mail.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('settings::settings.pages.mail');
    }

    public function getTitle(): string
    {
        return __('settings::settings.pages.mail');
    }

    /**
     * ⚠️ كلمة المرور **عمرها ما بتنزل للمتصفح**.
     *
     * `SettingsPage::fillForm()` بيملا الفورم من `$settings->toArray()` —
     * وده بيرجّع السر بعد فكّ التشفير. لو سبناه، السر بيتبعت في HTML كل
     * مرة الصفحة تتفتح، وبيبان في الـ DOM وفي حالة Livewire.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['password'] = '';

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('settings::settings.sections.mail_transport'))
                ->description(__('settings::settings.sections.mail_transport_help'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('driver')
                        ->label(__('settings::settings.fields.mail_driver'))
                        ->options([
                            'log' => __('settings::settings.options.mail_driver.log'),
                            'smtp' => __('settings::settings.options.mail_driver.smtp'),
                            'array' => __('settings::settings.options.mail_driver.array'),
                        ])
                        ->live()
                        ->required(),

                    Select::make('encryption')
                        ->label(__('settings::settings.fields.mail_encryption'))
                        ->options([
                            '' => __('settings::settings.options.mail_encryption.none'),
                            'tls' => 'TLS',
                            'ssl' => 'SSL',
                        ])
                        ->visible(fn (callable $get): bool => $get('driver') === 'smtp'),

                    TextInput::make('host')
                        ->label(__('settings::settings.fields.mail_host'))
                        // ⚠️ SSRF مقبولة وموثّقة: السيرفر بيتصل بالقيمة دي.
                        //    الحماية هي الصلاحية (manage_mail.settings) — نفس
                        //    مستوى التحكم اللي كان في `.env`. مفيش allow-list
                        //    بالقصد (قرار مراجعة الجاهزية بند ٣).
                        ->helperText(__('settings::settings.help.mail_host'))
                        ->visible(fn (callable $get): bool => $get('driver') === 'smtp')
                        ->required(fn (callable $get): bool => $get('driver') === 'smtp'),

                    TextInput::make('port')
                        ->label(__('settings::settings.fields.mail_port'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(65535)
                        ->visible(fn (callable $get): bool => $get('driver') === 'smtp')
                        ->required(fn (callable $get): bool => $get('driver') === 'smtp'),

                    TextInput::make('username')
                        ->label(__('settings::settings.fields.mail_username'))
                        ->visible(fn (callable $get): bool => $get('driver') === 'smtp'),

                    // ⚠️ الحقل الحسّاس — نفس صيغة ADR-009/ADR-017:
                    //
                    //    `dehydrated(filled($state))` معناه إن الحقل الفاضي
                    //    **بيتشال من الحمولة خالص**، و`$settings->fill()`
                    //    مابيلمسش مفتاح مش موجود — فكلمة المرور القديمة
                    //    بتفضل زي ما هي.
                    //
                    //    من غير ده، فتح الصفحة وحفظها كان بيمسح السر
                    //    (لأن الفورم بيتملى فاضي فوق) — يعني البريد بيقف
                    //    من غير ما حد يغيّر حاجة عن قصد.
                    TextInput::make('password')
                        ->label(__('settings::settings.fields.mail_password'))
                        ->password()
                        ->helperText(__('settings::settings.help.mail_password'))
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->visible(fn (callable $get): bool => $get('driver') === 'smtp')
                        ->maxLength(255),
                ]),

            Section::make(__('settings::settings.sections.mail_sender'))
                ->columnSpanFull()
                ->schema([
                    TextInput::make('from_address')
                        ->label(__('settings::settings.fields.mail_from_address'))
                        ->email()
                        ->required(),

                    KeyValue::make('from_name')
                        ->label(__('settings::settings.fields.mail_from_name'))
                        ->keyLabel(__('settings::settings.locale'))
                        ->valueLabel(__('settings::settings.text'))
                        ->addable(false)
                        ->deletable(false),
                ]),
        ]);
    }
}
