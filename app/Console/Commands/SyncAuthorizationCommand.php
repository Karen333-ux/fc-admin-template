<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Src\Support\Infrastructure\Authorization\PermissionBuilder;

/**
 * مزامنة الصلاحيات والأدوار من config/authorization.php. (docs/02 بند ٣)
 *
 * لازم يتنفّذ في سكربت النشر بعد migrate.
 */
final class SyncAuthorizationCommand extends Command
{
    protected $signature = 'authorization:sync
                            {--prune : حذف الصلاحيات غير الموجودة في الكونفيج}
                            {--dry   : عرض التغييرات من غير تنفيذ}';

    protected $description = 'مزامنة الصلاحيات والأدوار من config/authorization.php';

    public function handle(PermissionBuilder $builder, PermissionRegistrar $registrar): int
    {
        // الأدوار **تعريفات عامة** (team_id = null). التخصيص لمستأجر بيحصل
        // في model_has_roles وقت الإسناد، مش هنا. (docs/03 بند ٤)
        $registrar->setPermissionsTeamId(null);

        $guard = config('authorization.guard');
        $defined = $builder->allPermissionNames();
        $existing = Permission::query()->where('guard_name', $guard)->pluck('name')->all();

        $toCreate = array_diff($defined, $existing);
        $toPrune = array_diff($existing, $defined);

        $this->table(['العملية', 'العدد'], [
            ['إضافة', count($toCreate)],
            ['حذف محتمل', count($toPrune)],
        ]);

        if ($this->option('dry')) {
            return self::SUCCESS;
        }

        foreach ($toCreate as $name) {
            Permission::create(['name' => $name, 'guard_name' => $guard]);
        }

        if ($this->option('prune') && $toPrune !== []) {
            Permission::query()
                ->whereIn('name', $toPrune)
                ->where('guard_name', $guard)
                ->delete();
        }

        foreach (config('authorization.roles') as $roleName => $patterns) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);
            $role->syncPermissions($builder->expandPatterns($patterns));

            $this->line("  ✔ الدور <info>{$roleName}</info> — {$role->permissions()->count()} صلاحية");
        }

        $registrar->forgetCachedPermissions();

        $this->info('تمت المزامنة ومسح الكاش.');

        return self::SUCCESS;
    }
}
