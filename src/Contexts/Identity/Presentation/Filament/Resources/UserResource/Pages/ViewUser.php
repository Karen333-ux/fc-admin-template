<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Presentation\Filament\Resources\UserResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource;

final class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->authorize('update'),

            // البوابة هنا UX بس — `ListUserActivities::canAccess()` هي
            // البوابة الأمنية الحقيقية على الرابط المباشر. (CLAUDE.md بند ٣)
            Action::make('activity')
                ->label(__('identity::identity.actions.activity_log'))
                ->url(fn (): string => UserResource::getUrl('activity', ['record' => $this->record]))
                ->visible(fn (): bool => Gate::allows('view.activity_logs')),
        ];
    }
}
