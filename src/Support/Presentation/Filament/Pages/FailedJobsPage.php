<?php

declare(strict_types=1);

namespace Src\Support\Presentation\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Src\Support\Infrastructure\Queue\FailedJob;
use Src\Support\Presentation\Filament\Navigation\NavigationGroup;

/**
 * صفحة الوظائف الفاشلة — زرار «إعادة المحاولة» بصلاحية access.horizon
 * (نفس صلاحية Horizon، مفيش صلاحية كتالوج جديدة). (docs/13 بند ٥)
 *
 * ⚠️ Horizon نفسه بيعرض الفاشلة مع الـ stack trace من مخزنه في Redis —
 *    ده سلوكه الجاهز، مش محتاج بناء تاني هنا. الصفحة دي عن `failed_jobs`
 *    (جدول Laravel القياسي)، عشان تدي زرار إعادة محاولة من جوّه اللوحة
 *    من غير ما تحتاج SSH لسطر أوامر `queue:retry`.
 *
 * ⚠️ `access.horizon` بوابة مباشرة زي `HorizonPage`/`HealthPage` بالظبط —
 *    مفيش Policy لأن `failed_jobs` مش موديل دومين، جدول بنية تحتية نظامي.
 */
final class FailedJobsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 92;

    public static function canAccess(): bool
    {
        return Gate::allows('access.horizon');
    }

    public static function getNavigationLabel(): string
    {
        return __('queues.failed_jobs.title');
    }

    public function getTitle(): string
    {
        return __('queues.failed_jobs.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => FailedJob::query())
            ->defaultSort('failed_at', 'desc')
            ->columns([
                TextColumn::make('job')
                    ->label(__('queues.failed_jobs.fields.job'))
                    ->state(fn (FailedJob $record): string => $record->jobName()),

                TextColumn::make('connection')
                    ->label(__('queues.failed_jobs.fields.connection')),

                TextColumn::make('queue')
                    ->label(__('queues.failed_jobs.fields.queue'))
                    ->badge(),

                TextColumn::make('exception')
                    ->label(__('queues.failed_jobs.fields.exception'))
                    ->limit(80)
                    ->wrap(),

                TextColumn::make('failed_at')
                    ->label(__('queues.failed_jobs.fields.failed_at'))
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label(__('queues.failed_jobs.actions.retry'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => Gate::allows('access.horizon'))
                    ->action(function (FailedJob $record): void {
                        Artisan::call('queue:retry', ['id' => [$record->uuid]]);

                        Notification::make()
                            ->title(__('queues.failed_jobs.notifications.retried'))
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading(__('queues.failed_jobs.empty.heading'))
            ->emptyStateIcon(Heroicon::OutlinedExclamationTriangle);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }
}
