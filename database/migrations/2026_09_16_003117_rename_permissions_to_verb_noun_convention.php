<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const RENAMES = [
        'business.manage' => 'manage_business',
        'staff.manage' => 'manage_staff',
    ];

    public function up(): void
    {
        $this->rename(self::RENAMES);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::RENAMES));
    }

    /**
     * @param  array<string, string>  $renames
     */
    private function rename(array $renames): void
    {
        $table = $this->table('permissions');

        foreach ($renames as $from => $to) {
            DB::table($table)
                ->where('name', $from)
                ->update([
                    'name' => $to,
                    'slug' => str_replace(['.', '_'], '-', $to),
                    'description' => 'permissions.'.$to.'.description',
                    'updated_at' => now(),
                ]);
        }

        $this->forgetPermissionCache();
    }

    private function forgetPermissionCache(): void
    {
        $store = config('permission.cache.store');

        app('cache')
            ->store($store !== 'default' ? $store : null)
            ->forget(config('permission.cache.key'));
    }

    private function table(string $key): string
    {
        return (string) config("permission.table_names.{$key}");
    }
};
