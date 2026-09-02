<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Presentation\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Src\Contexts\Settings\Domain\Settings\GeneralSettings;

final class ManageGeneral extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string $settings = GeneralSettings::class;

    /**
     * الصفحة دي **مالهاش موديل**، فالسؤال بيروح للـ Gate مش للـ Policy.
     *
     * صلاحية منفصلة لكل مجموعة إعدادات (`docs/05` بند ٧)، والاسم
     * `{action}.{resource}` زي أي صلاحية تانية (ADR-001). الـ Gate بيتعرّف
     * تلقائياً لكل أسماء الكتالوج (ADR-003) وبيرجّع `Response` برسالة رفض
     * مترجمة (ADR-019).
     *
     * ⚠️ `canAccess()` هي البوابة الأمنية. إخفاء اللينك من القايمة تجربة
     * استخدام بس — Filament بيستدعي `canAccess()` على الـ URL المباشر كمان.
     */
    public static function canAccess(): bool
    {
        return Gate::allows('manage_general.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('settings::settings.pages.general');
    }

    public function getTitle(): string
    {
        return __('settings::settings.pages.general');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('settings::settings.sections.identity'))
                ->description(__('settings::settings.sections.identity_help'))
                ->columnSpanFull()
                ->schema([
                    // الاسم والوصف مترجمين: مفتاح لكل لغة. `addable(false)`
                    // و`deletable(false)` عشان اللغات تفضل هي هي — إضافة لغة
                    // قرار في `config/app.php` مش في صفحة إعدادات.
                    KeyValue::make('app_name')
                        ->label(__('settings::settings.fields.app_name'))
                        ->helperText(__('settings::settings.help.app_name'))
                        ->keyLabel(__('settings::settings.locale'))
                        ->valueLabel(__('settings::settings.text'))
                        ->addable(false)
                        ->deletable(false)
                        ->required(),

                    KeyValue::make('app_description')
                        ->label(__('settings::settings.fields.app_description'))
                        ->keyLabel(__('settings::settings.locale'))
                        ->valueLabel(__('settings::settings.text'))
                        ->addable(false)
                        ->deletable(false),
                ]),

            Section::make(__('settings::settings.sections.contact'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('support_email')
                        ->label(__('settings::settings.fields.support_email'))
                        ->email()
                        ->required(),

                    TextInput::make('support_phone')
                        ->label(__('settings::settings.fields.support_phone'))
                        ->tel(),
                ]),

            Section::make(__('settings::settings.sections.localization'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    // اللغات المتاحة من الكونفيج — مش مكتوبة في الكود.
                    Select::make('default_locale')
                        ->label(__('settings::settings.fields.default_locale'))
                        ->options($this->localeOptions())
                        ->required(),

                    Select::make('timezone')
                        ->label(__('settings::settings.fields.timezone'))
                        ->helperText(__('settings::settings.help.timezone'))
                        ->options($this->timezoneOptions())
                        ->searchable()
                        ->required(),
                ]),

            Section::make(__('settings::settings.sections.maintenance'))
                ->description(__('settings::settings.sections.maintenance_help'))
                ->columnSpanFull()
                ->schema([
                    Toggle::make('maintenance_mode')
                        ->label(__('settings::settings.fields.maintenance_mode')),

                    KeyValue::make('maintenance_message')
                        ->label(__('settings::settings.fields.maintenance_message'))
                        ->keyLabel(__('settings::settings.locale'))
                        ->valueLabel(__('settings::settings.text'))
                        ->addable(false)
                        ->deletable(false),
                ]),
        ]);
    }

    /** @return array<string, string> */
    private function localeOptions(): array
    {
        /** @var array<int, string> $locales */
        $locales = config('app.supported_locales', []);

        $options = [];

        foreach ($locales as $locale) {
            $options[$locale] = __("common.locales.{$locale}");
        }

        return $options;
    }

    /** @return array<string, string> */
    private function timezoneOptions(): array
    {
        $identifiers = timezone_identifiers_list();

        return array_combine($identifiers, $identifiers);
    }
}
