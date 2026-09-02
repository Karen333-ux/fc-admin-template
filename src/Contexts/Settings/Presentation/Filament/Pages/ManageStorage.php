<?php

declare(strict_types=1);

namespace Src\Contexts\Settings\Presentation\Filament\Pages;

use BackedEnum;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Src\Contexts\Settings\Domain\Settings\StorageSettings;
use Src\Support\Presentation\Filament\Navigation\NavigationGroup;

final class ManageStorage extends SettingsPage
{
    /** نوع MIME ممنوع — ملف XML بينفّذ سكربت. (CLAUDE.md · docs/20) */
    private const FORBIDDEN_MIME = 'image/svg+xml';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string $settings = StorageSettings::class;

    /** كل صفحات الإعدادات تحت مجموعة «النظام». (docs/07 بند ٢) */
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 40;

    /**
     * الصفحة دي **مالهاش موديل**، فالسؤال بيروح للـ Gate مش للـ Policy.
     * (ADR-001 · ADR-003 · ADR-019 — نفس نمط `ManageAppearance`)
     *
     * ⚠️ `canAccess()` هي البوابة الأمنية مش إخفاء اللينك. (docs/04 بند ٨)
     */
    public static function canAccess(): bool
    {
        return Gate::allows('manage_storage.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('settings::settings.pages.storage');
    }

    public function getTitle(): string
    {
        return __('settings::settings.pages.storage');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('settings::settings.sections.storage_disks'))
                ->description(__('settings::settings.sections.storage_disks_help'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    // المصدر `config/filesystems.php` نفسه — مفيش أسماء
                    // ديسكات مكتوبة في الكود. (docs/04 بند ٨ و بند ١٠)
                    Select::make('default_disk')
                        ->label(__('settings::settings.fields.default_disk'))
                        ->options($this->diskOptions())
                        ->required(),

                    Select::make('private_disk')
                        ->label(__('settings::settings.fields.private_disk'))
                        ->helperText(__('settings::settings.help.private_disk'))
                        ->options($this->diskOptions())
                        ->required()
                        // ⚠️ ديسك خاص مابيدعمش الروابط المؤقتة بيخلّي
                        //    `MediaUrlResolver` يرمي عند أول تحميل (ADR-022 بند ٥).
                        //    الرفض هنا بيمسك الغلطة وقت الإعداد مش وقت الاستخدام.
                        ->rule(static function (): Closure {
                            return static function (string $attribute, mixed $value, Closure $fail): void {
                                if (! Storage::disk((string) $value)->providesTemporaryUrls()) {
                                    $fail(__('settings::settings.validation.no_temporary_urls'));
                                }
                            };
                        }),
                ]),

            Section::make(__('settings::settings.sections.storage_collections'))
                ->description(__('settings::settings.sections.storage_collections_help'))
                ->columnSpanFull()
                ->schema([
                    // multi-select زي ما `docs/04` بند ٨ بيقول. المفردات
                    // مقفولة بالقصد: المجموعة بيعرّفها الموديل اللي بيسجّلها
                    // في الكود، مش نص بيتكتب في صفحة إعدادات.
                    Select::make('private_collections')
                        ->label(__('settings::settings.fields.private_collections'))
                        ->helperText(__('settings::settings.help.private_collections'))
                        ->multiple()
                        ->options($this->collectionOptions())
                        ->searchable(),
                ]),

            Section::make(__('settings::settings.sections.storage_uploads'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('max_upload_size_kb')
                        ->label(__('settings::settings.fields.max_upload_size_kb'))
                        ->helperText(__('settings::settings.help.max_upload_size_kb'))
                        ->numeric()
                        ->minValue(1)
                        ->required(),

                    TagsInput::make('allowed_mimes')
                        ->label(__('settings::settings.fields.allowed_mimes'))
                        ->helperText(__('settings::settings.help.allowed_mimes'))
                        // ⚠️ SVG ممنوع: ملف XML بينفّذ سكربت، ورفعه في مجموعة
                        //    عامة ثغرة XSS مخزّنة — واحدة من أعلى تلات مخاطر
                        //    في المشروع (CLAUDE.md · docs/20).
                        ->rule(static function (): Closure {
                            return static function (string $attribute, mixed $value, Closure $fail): void {
                                $mimes = array_map(
                                    static fn (mixed $mime): string => mb_strtolower(trim((string) $mime)),
                                    is_array($value) ? $value : [],
                                );

                                if (in_array(self::FORBIDDEN_MIME, $mimes, true)) {
                                    $fail(__('settings::settings.validation.forbidden_mime', [
                                        'mime' => self::FORBIDDEN_MIME,
                                    ]));
                                }
                            };
                        }),
                ]),
        ]);
    }

    /**
     * الديسكات المعرّفة في `config/filesystems.php`.
     *
     * @return array<string, string>
     */
    private function diskOptions(): array
    {
        $disks = array_keys((array) config('filesystems.disks', []));

        return array_combine($disks, $disks);
    }

    /**
     * المجموعات اللي التثبيت ده يعرفها.
     *
     * اتحاد المجموعات الخاصة الحالية مع مفاتيح `collection_disks` — دي
     * المفردات الحقيقية الموجودة، ومفيش سجل مجموعات في المشروع لسه.
     *
     * @return array<string, string>
     */
    private function collectionOptions(): array
    {
        $settings = app(StorageSettings::class);

        $collections = array_values(array_unique([
            ...$settings->private_collections,
            ...array_keys($settings->collection_disks),
        ]));

        sort($collections);

        return array_combine($collections, $collections);
    }
}
