<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();                 // acme
            $table->string('domain')->nullable()->unique();   // acme.fc-admin.test
            $table->json('name');                             // {"ar": "...", "en": "..."}
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 9)->default('#12454F');
            $table->boolean('is_active')->default(true);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
