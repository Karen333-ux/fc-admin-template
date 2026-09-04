<?php

declare(strict_types=1);

namespace Src\Contexts\Identity\Presentation\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
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
use RuntimeException;
use Src\Contexts\Identity\Application\Actions\ForceLogoutAction;
use Src\Contexts\Identity\Application\Actions\TerminateDeviceAction;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Domain\Models\UserDevice;
use Src\Support\Presentation\Filament\Navigation\NavigationGroup;

/**
 * شاشة «أجهزتي» — ذاتية الخدمة، كل مستخدم بيدير جلساته هو بس. (docs/12 بند ٢)
 *
 * ⚠️ مفيش صلاحية كتالوج جديدة — نفس منطق إدارة 2FA الذاتية في `EditProfile`
 *    (Slice 4.4): إدارة حساب المستخدم لنفسه مش قدرة تحتاج Gate، البوابة
 *    الحقيقية هي المصادقة نفسها اللي middleware اللوحة أصلاً بيفرضها.
 *    الاستعلام مقفول على `Filament::auth()->id()` صراحةً — مش بارامتر
 *    من الطلب — فمفيش وصول لأجهزة مستخدم تاني حتى لو اتغيّر ID في الرابط.
 */
final class MyDevices extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDeviceTablet;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Identity;

    protected static ?int $navigationSort = 15;

    public static function canAccess(): bool
    {
        return true;
    }

    public static function getNavigationLabel(): string
    {
        return __('identity::identity.pages.my_devices');
    }

    public function getTitle(): string
    {
        return __('identity::identity.pages.my_devices');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => UserDevice::query()->where('user_id', Filament::auth()->id()))
            ->defaultSort('last_active_at', 'desc')
            ->columns([
                TextColumn::make('device_name')
                    ->label(__('identity::identity.devices.fields.device'))
                    ->description(fn (UserDevice $record): string => $record->ip_address)
                    ->default(__('identity::identity.devices.unknown_device')),

                TextColumn::make('last_active_at')
                    ->label(__('identity::identity.devices.fields.last_active_at'))
                    ->since()
                    ->sortable(),

                TextColumn::make('current')
                    ->label(__('identity::identity.devices.fields.status'))
                    ->state(fn (UserDevice $record): string => $this->isCurrentDevice($record)
                        ? __('identity::identity.devices.current_device')
                        : '')
                    ->badge()
                    ->color('success'),
            ])
            ->recordActions([
                Action::make('terminate')
                    ->label(__('identity::identity.devices.actions.terminate'))
                    ->color('danger')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->requiresConfirmation()
                    // الجهاز الحالي مايتقفلش من هنا — ده تسجيل خروج، فعل مختلف
                    ->hidden(fn (UserDevice $record): bool => $this->isCurrentDevice($record))
                    ->action(function (UserDevice $record): void {
                        app(TerminateDeviceAction::class)->handle($this->currentUser(), $record);

                        Notification::make()
                            ->title(__('identity::identity.devices.notifications.terminated'))
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Action::make('terminateOthers')
                    ->label(__('identity::identity.devices.actions.terminate_others'))
                    ->color('danger')
                    ->icon(Heroicon::OutlinedXMark)
                    ->requiresConfirmation()
                    ->action(function (): void {
                        app(ForceLogoutAction::class)->handle(
                            $this->currentUser(),
                            session()->getId(),
                        );

                        Notification::make()
                            ->title(__('identity::identity.devices.notifications.others_terminated'))
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading(__('identity::identity.devices.empty.heading'))
            ->emptyStateIcon(Heroicon::OutlinedDeviceTablet);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    private function isCurrentDevice(UserDevice $record): bool
    {
        return $record->session_id === session()->getId();
    }

    private function currentUser(): User
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            throw new RuntimeException('صفحة أجهزتي لازم تشتغل على موديل User.');
        }

        return $user;
    }
}
