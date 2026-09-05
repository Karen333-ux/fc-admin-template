<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;

/**
 * المهام المجدولة. (docs/13 بند ٣)
 */
function scheduledEvent(string $commandName): ?Event
{
    /** @var Collection<int, Event> $events */
    $events = collect(app(Schedule::class)->events());

    return $events->first(fn (Event $event): bool => str_contains((string) $event->command, $commandName));
}

it('horizon:snapshot مجدولة كل ٥ دقايق وعلى سيرفر واحد', function (): void {
    $event = scheduledEvent('horizon:snapshot');

    expect($event)->not->toBeNull()
        ->and($event->getExpression())->toBe('*/5 * * * *')
        ->and($event->onOneServer)->toBeTrue();
});

it('health:check مجدولة كل ١٥ دقيقة وعلى سيرفر واحد ومن غير تراكم', function (): void {
    $event = scheduledEvent('health:check');

    expect($event)->not->toBeNull()
        ->and($event->getExpression())->toBe('*/15 * * * *')
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('activitylog:prune لسه شهرية وبقت onOneServer', function (): void {
    $event = scheduledEvent('activitylog:prune');

    expect($event)->not->toBeNull()
        ->and($event->getExpression())->toBe('0 0 1 * *')
        ->and($event->onOneServer)->toBeTrue();
});

it('health:schedule-check-heartbeat لسه كل دقيقة وبقت onOneServer', function (): void {
    $event = scheduledEvent('health:schedule-check-heartbeat');

    expect($event)->not->toBeNull()
        ->and($event->getExpression())->toBe('* * * * *')
        ->and($event->onOneServer)->toBeTrue();
});

it('queue:prune-failed مجدولة أسبوعياً وعلى سيرفر واحد', function (): void {
    $event = scheduledEvent('queue:prune-failed');

    expect($event)->not->toBeNull()
        ->and($event->command)->toContain('--hours=168')
        ->and($event->getExpression())->toBe('0 0 * * 0')
        ->and($event->onOneServer)->toBeTrue();
});
