<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * العضوية many-to-many — مستخدم ممكن يكون في أكتر من مؤسسة.
 * `users` **مافيهوش** tenant_id. (ADR-002)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->primary(['tenant_id', 'user_id']);

            // للاستعلام العكسي: «كل مستخدمي المؤسسة دي»
            $table->index(['user_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user');
    }
};
