<?php

declare(strict_types=1);

use App\Domains\Staff\ValueObjects\StaffRole;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\ConsoleOutput;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->hasAccountsToMigrate()) {
            $this->migrateAccounts();
        }

        $this->dropBusinessColumn();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('business_id')->nullable()->after('id')->index();
            $table->foreign('business_id')->references('uuid')->on('businesses')->nullOnDelete();
        });
    }

    private function migrateAccounts(): void
    {
        $this->insertMemberships();

        $ownerRoleId = $this->ownerRoleId();

        if ($ownerRoleId === null) {
            $this->warn(
                'Owner role not found: memberships were migrated without a role assignment. '.
                'Run AuthorizationSeeder, then give those accounts the owner role at their business.'
            );

            return;
        }

        $this->assignOwnerRole($ownerRoleId);
    }

    private function hasAccountsToMigrate(): bool
    {
        return DB::table('users')->whereNotNull('business_id')->exists();
    }

    private function insertMemberships(): void
    {
        DB::statement(<<<'SQL'
            insert into staff_members (uuid, business_id, account_id, created_at, updated_at)
            select gen_random_uuid(), businesses.id, users.id, now(), now()
            from users
            join businesses on businesses.uuid = users.business_id
            where users.business_id is not null
              and businesses.deleted_at is null
            on conflict do nothing
        SQL);
    }

    private function assignOwnerRole(int $ownerRoleId): void
    {
        DB::statement(<<<'SQL'
            insert into model_has_roles (role_id, model_type, model_id, business_id)
            select ?, ?, users.id, businesses.id
            from users
            join businesses on businesses.uuid = users.business_id
            where users.business_id is not null
              and businesses.deleted_at is null
            on conflict do nothing
        SQL, [$ownerRoleId, (new User)->getMorphClass()]);
    }

    private function ownerRoleId(): ?int
    {
        $id = DB::table('roles')
            ->where('name', StaffRole::Owner->value)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    private function warn(string $message): void
    {
        (new ConsoleOutput)->writeln('<comment>'.$message.'</comment>');

        Log::warning($message);
    }

    private function dropBusinessColumn(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['business_id']);
            $table->dropColumn('business_id');
        });
    }
};
