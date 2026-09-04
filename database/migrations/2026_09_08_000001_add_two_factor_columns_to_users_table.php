<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عمودين بس — `two_factor_confirmed_at` بره النطاق بالقصد. (docs/12 بند ١)
 *
 * ⚠️ نظام Filament الأصلي للمصادقة الثنائية (`Filament\Auth\MultiFactor\App`)
 *    بيعتبر السرّ «مفعّل» لو مش فاضي بس (`AppAuthentication::isEnabled()` =
 *    `filled($secret)`) — والسرّ **مايتحفظش أصلاً إلا بعد التأكيد**
 *    (`SetUpAppAuthenticationAction::action()` بينادي `saveSecret()` بعد
 *    التحقق من الكود، مش قبله). يعني عمود «متأكد وقتها» زيادة بيانات ميتة —
 *    مفيش كود بيقراه ولا بيكتبه. (اتفحص السورس المُثبَّت، مش مفترض من الذاكرة)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes']);
        });
    }
};
