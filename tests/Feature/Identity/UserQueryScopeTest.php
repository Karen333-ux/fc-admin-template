<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Contexts\Identity\Presentation\Filament\Resources\UserResource;
use Src\Support\Domain\Models\Tenant;

it('فلتر العضوية بيتطبّق جوه اللوحة وبرّاها', function (): void {
    // ⚠️ الفلتر الصريح في getEloquentQuery() هو **الطبقة الوحيدة**.
    // Filament مابيسجّلش global scope للمستأجر على User (شوف الاختبار اللي تحت)،
    // فأي استدعاء من command أو job أو اختبار بيعتمد على السطر ده لوحده.
    $tenant = Tenant::factory()->create();
    $admin = userWithRole('admin', $tenant);

    actingWithinTenant($tenant);
    $withoutPanel = UserResource::getEloquentQuery()->toSql();

    Auth::login($admin);
    Filament::setCurrentPanel('admin');
    Filament::setTenant($tenant);
    $withPanel = UserResource::getEloquentQuery()->toSql();

    expect($withoutPanel)->toContain('tenant_user')
        ->and($withPanel)->toContain('tenant_user');
});

it('مفيش global scope من Filament بيغطّي User', function (): void {
    // بيوثّق الواقع المقيس: لو إصدار Filament جاي ضاف السكوب ده تلقائياً،
    // الاختبار ده هيفشل وساعتها نراجع إذا كان الفلتر الصريح لسه لازم.
    $scopes = array_keys((new User)->getGlobalScopes());

    expect($scopes)->toBe([SoftDeletingScope::class]);
});
