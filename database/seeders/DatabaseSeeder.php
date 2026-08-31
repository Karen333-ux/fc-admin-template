<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * فاضي عن قصد.
     *
     * الصلاحيات والأدوار مصدرها `config/authorization.php` عبر
     * `php artisan authorization:sync` — مش سيدر. (docs/02 بند ٢)
     * فاكتوري المستخدمين في سياقه: `Src\Contexts\Identity\Database\Factories`.
     */
    public function run(): void
    {
        //
    }
}
