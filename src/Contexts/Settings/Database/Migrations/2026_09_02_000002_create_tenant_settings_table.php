<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات المستأجر — الطبقة التانية فوق إعدادات spatie العامة. (docs/05 بند ٤)
 *
 * الجدول ده **تابع لمستأجر**، فالموديل بتاعه بيستخدم `BelongsToTenant`
 * والعزل بييجي من `TenantScope` مش من شروط يدوية. (ADR-020)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('group');
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
