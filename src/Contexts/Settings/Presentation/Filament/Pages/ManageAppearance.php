<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Presentation\Filament\Pages;

use BackedEnum;
use Closure;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Src\Contexts\Settings\Domain\Settings\AppearanceSettings;
use Src\Support\Infrastructure\Theming\BrandPalette;

final class ManageAppearance extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static string $settings = AppearanceSettings::class;

    /**
     * الصفحة دي **مالهاش موديل**، فالسؤال بيروح للـ Gate مش للـ Policy.
     *
     * الاسم `{action}.{resource}` زي أي صلاحية تانية (ADR-001)، والـ Gate
     * بيتعرّف تلقائياً لكل أسماء الكتالوج (ADR-003) وبيرجّع `Response`
     * برسالة رفض مترجمة (ADR-019).
     */
    public static function canAccess(): bool
    {
        return Gate::allows('manage_appearance.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('settings::settings.pages.appearance');
    }

    public function getTitle(): string
    {
        return __('settings::settings.pages.appearance');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('settings::settings.sections.brand'))
                ->description(__('settings::settings.sections.brand_help'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    ColorPicker::make('primary_color')
                        ->label(__('settings::settings.fields.primary_color'))
                        ->hex()
                        ->required()
                        ->helperText(__('settings::settings.help.primary_color'))
                        // معيار قبول docs/06 بند ٢: درجة ٦٠٠ لازم تعدّي تباين
                        // ٤.٥:١ على الأبيض، والحفظ بيترفض لو رسبت. لون فاتح
                        // بيخلّي الأزرار والروابط غير مقروءة على اللوحة كلها.
                        ->rule(static function (): Closure {
                            return static function (string $attribute, mixed $value, Closure $fail): void {
                                if (! app(BrandPalette::class)->meetsTextContrast((string) $value)) {
                                    $fail(__('settings::settings.validation.low_contrast', [
                                        'ratio' => config('theme.min_contrast_ratio'),
                                    ]));
                                }
                            };
                        }),

                    Select::make('font_family')
                        ->label(__('settings::settings.fields.font'))
                        ->options([
                            'IBM Plex Sans Arabic' => 'IBM Plex Sans Arabic',
                            'Noto Sans Arabic' => 'Noto Sans Arabic',
                            'Cairo' => 'Cairo',
                        ])
                        ->required(),
                ]),

            Section::make(__('settings::settings.sections.layout'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('default_theme')
                        ->label(__('settings::settings.fields.default_theme'))
                        ->options([
                            'light' => __('settings::settings.options.theme.light'),
                            'dark' => __('settings::settings.options.theme.dark'),
                            'system' => __('settings::settings.options.theme.system'),
                        ])
                        ->required(),

                    Select::make('sidebar_default')
                        ->label(__('settings::settings.fields.sidebar_default'))
                        ->options([
                            'expanded' => __('settings::settings.options.sidebar.expanded'),
                            'collapsed' => __('settings::settings.options.sidebar.collapsed'),
                        ])
                        ->required(),

                    Toggle::make('allow_theme_switch')
                        ->label(__('settings::settings.fields.allow_theme_switch')),
                ]),
        ]);
    }
}
