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
    /**
     * Retires users.business_id in favour of the membership table. Everyone who
     * had the column set had created the business themselves - there was no
     * other way to acquire it - so owner is the faithful reading, not a guess.
     *
     * down() puts the empty column back and leaves the memberships alone. One
     * account can hold several memberships and a single column cannot express
     * that, so refilling it would mean picking one and discarding the rest.
     */
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

    /**
     * Memberships first, because that is the half granting access: if the roles
     * cannot be written - the seeder has not run yet - the accounts still reach
     * their business, and only what they may do there is missing.
     */
    private function migrateAccounts(): void
    {
        $this->insertMemberships();

        $ownerRoleId = $this->ownerRoleId();

        if ($ownerRoleId === null) {
            // Roles are seeded, and a migration must not assume a seeder has
            // run. Failing here would leave the schema half migrated, and
            // continuing in silence would leave owners with no role at all -
            // so the memberships stand and the gap is reported.
            $this->warn(
                'Owner role not found: memberships were migrated without a role assignment. '.
                'Run AuthorizationSeeder, then give those accounts the owner role at their business.'
            );

            return;
        }

        $this->assignOwnerRole($ownerRoleId);
    }

    /**
     * Asked before anything is written, so a fresh install neither reports a
     * missing role nor implies work was done.
     */
    private function hasAccountsToMigrate(): bool
    {
        return DB::table('users')->whereNotNull('business_id')->exists();
    }

    /**
     * gen_random_uuid() is v4 while IdGenerator emits uuid7. Accepted here and
     * nowhere else: this is a one-shot backfill of pre-production rows, and
     * minting ids in PHP would buy ordering nothing reads.
     *
     * The conflict target is the partial unique index from the staff_members
     * migration, so a membership somebody already created is left as it is.
     */
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

    /**
     * Written straight onto Spatie's pivot rather than through its API, because
     * every call it offers is scoped to the current team - and the team is per
     * row here. model_type is the morph alias, which is what getMorphClass()
     * returns now that the map is enforced; writing the class name instead would
     * produce rows no role check matches.
     */
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

    /**
     * Looked up by name rather than by SeededStaffRole::OWNER_ID, so a database
     * whose roles were seeded before those ids were fixed still migrates.
     */
    private function ownerRoleId(): ?int
    {
        $id = DB::table('roles')
            ->where('name', StaffRole::Owner->value)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /** Both channels, because a migration that leaves work behind must not be missable. */
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
