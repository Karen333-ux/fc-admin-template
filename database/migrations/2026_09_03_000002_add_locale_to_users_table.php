<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لغة المستخدم المفضّلة. (docs/09 بند ٤ · docs/10 بند ٤)
 *
 * ⚠️ nullable بالقصد: «مفيش تفضيل» حالة حقيقية ومختلفة عن «اختار العربي».
 *    الفاضي بيرجع لـ `GeneralSettings::default_locale`، فالسلوك بيتحدّد من
 *    إعداد التثبيت مش من قيمة مزروعة في كل صف.
 *
 * ⚠️ مفيش قيمة افتراضية على مستوى العمود عشان مانثبّتش لغة في السكيما —
 *    اللغة الافتراضية إعداد قابل للتغيير من اللوحة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 10)->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
