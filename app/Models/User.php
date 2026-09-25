<?php

namespace App\Models;

use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffProfileModel;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['uuid', 'name', 'email', 'email_verified_at', 'password', 'must_change_password'])]
#[Hidden(['password', 'remember_token', 'temporary_password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function mustChangePassword(): bool
    {
        return $this->must_change_password === true;
    }

    /**
     * @return HasMany<StaffMemberModel, $this>
     */
    public function staffMembers(): HasMany
    {
        return $this->hasMany(StaffMemberModel::class, 'account_id');
    }

    /**
     * @return HasManyThrough<StaffProfileModel, StaffMemberModel, $this>
     */
    public function staffProfiles(): HasManyThrough
    {
        return $this->hasManyThrough(
            StaffProfileModel::class,
            StaffMemberModel::class,
            'account_id',
            'staff_member_id',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'temporary_password' => 'encrypted',
        ];
    }
}
