<?php

declare(strict_types=1);

namespace Src\Support\Presentation\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\ResultStores\ResultStore;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResult;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResults;
use Src\Support\Presentation\Filament\Navigation\NavigationGroup;

/**
 * صفحة صحة النظام. (docs/11 بند ٧)
 *
 * ⚠️ مفيش Livewire component جاهز من `spatie/laravel-health` للوحات
 *    Filament — الحزمة بترجّع HTML ثابت بس (`resources/views/list.blade.php`)
 *    أو JSON (`HealthCheckJsonResultsController`، endpoint `/health` المنفصل
 *    المضبوط في `config/health.php`). الصفحة دي بتبني عرض بمكوّنات Schema
 *    عادية زي أي صفحة تانية في المشروع، مش View مخصصة — نفس أسلوب باقي
 *    صفحات اللوحة.
 */
final class HealthPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 90;

    // ⚠️ مش public: Livewire مالوش synth لـ StoredCheckResults، وبيرمي وقت
    // التسلسل لو اتحطّت public. مش محتاجة تتسلسل أصلاً — بتتحسب من جديد
    // في mount() وفي كل نداء runChecks() في نفس الطلب.
    private ?StoredCheckResults $results = null;

    public static function canAccess(): bool
    {
        return Gate::allows('access.health');
    }

    public static function getNavigationLabel(): string
    {
        return __('health.title');
    }

    public function getTitle(): string
    {
        return __('health.title');
    }

    public function mount(): void
    {
        $this->runChecks();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(__('health.actions.refresh'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(fn () => $this->runChecks()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $results = $this->results->storedCheckResults ?? collect();

        return $schema->components(
            $results
                ->map(fn (StoredCheckResult $result): Section => Section::make($result->label)
                    ->icon($this->iconFor($result->status))
                    ->iconColor($this->colorFor($result->status))
                    ->columnSpanFull()
                    ->schema([
                        Text::make($result->shortSummary !== ''
                            ? $result->shortSummary
                            : ($result->notificationMessage ?: __('health.no_issues')))
                            ->badge()
                            ->color($this->colorFor($result->status)),
                    ]))
                ->all(),
        );
    }

    private function runChecks(): void
    {
        Artisan::call(RunHealthChecksCommand::class);

        $this->results = app(ResultStore::class)->latestResults();
    }

    private function colorFor(string $status): string
    {
        return match ($status) {
            'ok' => 'success',
            'warning' => 'warning',
            'failed', 'crashed' => 'danger',
            default => 'gray',
        };
    }

    private function iconFor(string $status): BackedEnum
    {
        return match ($status) {
            'ok' => Heroicon::OutlinedCheckCircle,
            'warning' => Heroicon::OutlinedExclamationTriangle,
            'failed', 'crashed' => Heroicon::OutlinedXCircle,
            default => Heroicon::OutlinedQuestionMarkCircle,
        };
    }
}
