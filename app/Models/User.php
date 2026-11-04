<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use jeremykenedy\LaravelRoles\Traits\HasRoleAndPermission;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser, Auditable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, 
        Notifiable, 
        PasskeyAuthenticatable, 
        TwoFactorAuthenticatable, 
        HasRoleAndPermission,
        SoftDeletes,
        \OwenIt\Auditing\Auditable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public static function setSapServer($company)
    {
        $sap_server = SapServer::where('sap_server', $company)
            ->get()
            ->first();

        return $sap_server;
    }

    public static function setSapUser($company, $user_id)
    {
        $sap_user = SapUser::where('sap_server', $company)
            ->where('user_id', $user_id)
            ->get()
            ->first();

        return $sap_user;
    }

    public function company()
    {
        return $this->belongsToMany(Company::class);
    }

    public function plants()
    {
        return $this->belongsToMany(Plant::class, 'plant_users', 'user_id', 'plant_code', 'id', 'plant_code')
            ->withPivot('id', 'deleted_at')
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }
}
