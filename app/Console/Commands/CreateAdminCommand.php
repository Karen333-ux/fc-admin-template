<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Src\Contexts\Identity\Domain\Models\User;
use Src\Support\Domain\Models\Tenant;

/**
 * إنشاء أول مستخدم super_admin ومستأجره — للتشغيل الأول بعد النشر.
 *
 * من غير الأمر ده مفيش طريق للدخول على لوحة جديدة: `authorization:sync`
 * بيعمل الأدوار بس مش المستخدمين، ومفيش سيدر للمستخدمين بالقصد (docs/02 بند ٢).
 * و`canAccessPanel` بيتحقق من `access.panel.admin`، فمستخدم من غير دور
 * بيتعمل بنجاح وبعدين يترفض عند الدخول — وده أسوأ من رسالة خطأ واضحة.
 *
 * الأمر آمن للتكرار: بيرفض بريد موجود، وبيستخدم المستأجر لو كان موجود.
 */
final class CreateAdminCommand extends Command
{
    protected $signature = 'fc:create-admin
                            {--name=     : اسم المستخدم}
                            {--email=    : البريد الإلكتروني}
                            {--password= : كلمة السر}
                            {--tenant=   : slug المستأجر — بيتعمل لو مش موجود}';

    protected $description = 'إنشاء مستخدم super_admin وربطه بمستأجر';

    public function handle(PermissionRegistrar $registrar): int
    {
        $guard = config('authorization.guard');
        $roleName = config('authorization.super_admin_role');

        // الدور لازم يكون موجود قبل الإسناد. لو الأمر ده اتنفّذ قبل
        // authorization:sync، الإسناد بيرمي استثناء غامض من الباكدج —
        // فالأوضح نمسكها هنا ونقول للمستخدم يعمل إيه.
        if (! Role::query()->where('name', $roleName)->where('guard_name', $guard)->exists()) {
            $this->components->error("الدور [{$roleName}] مش موجود. شغّلي `php artisan authorization:sync` الأول.");

            return self::FAILURE;
        }

        $data = $this->collectInput();

        if ($data === null) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($data, $registrar, $roleName): void {
            $tenant = Tenant::query()->firstOrCreate(
                ['slug' => $data['tenant']],
                ['name' => $data['tenant'], 'is_active' => true],
            );

            $this->components->twoColumnDetail(
                'المستأجر',
                $tenant->wasRecentlyCreated ? "{$data['tenant']} <info>(اتعمل)</info>" : "{$data['tenant']} (موجود)",
            );

            // كلمة السر بتتشفّر عبر cast `hashed` في الموديل، مش هنا.
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

            // الأدوار تعريفات عامة (team_id = null) والتخصيص بيحصل وقت
            // الإسناد (docs/03 بند ٤) — فلازم نحدد المستأجر قبل assignRole،
            // وإلا الصف بيتكتب بـ tenant_id فاضي ومايتطابقش مع أي فحص بعدين.
            //
            // نفس منطق SyncAuthorizationCommand: بنرجّع الـ team الأصلي في
            // finally لأن الأمر ممكن يتنادى in-process من اختبار أو job.
            $previousTeamId = $registrar->getPermissionsTeamId();
            $registrar->setPermissionsTeamId($tenant->id);

            try {
                $user->assignRole($roleName);
            } finally {
                $registrar->setPermissionsTeamId($previousTeamId);
                $registrar->forgetCachedPermissions();
            }

            $this->components->twoColumnDetail('المستخدم', $user->email);
            $this->components->twoColumnDetail('الدور', $roleName);
        });

        $this->newLine();
        $this->components->info('تم. تقدري تدخلي على /admin بالبريد وكلمة السر دول.');

        return self::SUCCESS;
    }

    /**
     * جمع المدخلات والتحقق منها.
     *
     * @return array{name:string,email:string,password:string,tenant:string}|null
     */
    private function collectInput(): ?array
    {

        $data = [
            'name' => $this->option('name') ?: $this->ask('الاسم'),
            'email' => $this->option('email') ?: $this->ask('البريد الإلكتروني'),
            'password' => $this->option('password') ?: $this->secret('كلمة السر'),
            'tenant' => $this->option('tenant') ?: $this->ask('slug المستأجر', 'default'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:'.User::class.',email'],
            'password' => ['required', 'string', 'min:12'],
            'tenant' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return null;
        }

        /** @var array{name:string,email:string,password:string,tenant:string} */
        return $data;
    }
}
