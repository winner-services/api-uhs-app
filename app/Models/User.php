<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     required={"id","name","email"},
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Jean Dupont"),
 *     @OA\Property(property="email", type="string", format="email", example="jean.dupont@example.com"),
 *     @OA\Property(property="phone", type="string", example="+243900000000"),
 *     @OA\Property(property="role", type="string", example="admin"),
 *     @OA\Property(property="permissions", type="array", @OA\Items(type="string", example="create_user")),
 *     @OA\Property(property="point_eau_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'active',
        'point_eau_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'created_at',
        'updated_at',
        'email_verified_at',
    ];

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
        ];
    }

    protected $appends = [
        'role_id',
        'role_name',
        'permissions_list',
    ];

    public function scopeSearh($query, string $term): void
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('users.name', 'like', $term)
                ->orWhere('phone', 'like', $term)
                ->orWhere('email', 'like', $term);
        });
    }
    public function scopeSearch($query, string $term): void
    {
        $term = "%$term%";
        $query->where(function ($query) use ($term) {
            $query->where('users.name', 'like', $term)
                ->orWhere('phone', 'like', $term)
                ->orWhere('email', 'like', $term);
        });
    }

    public static function findUser(string $email, array $selects = ['*']): ?self
    {
        return self::select($selects)
            ->where('email', $email)
            ->first();
    }

    public function getRoleData(): array
    {
        $roles = $this->roles->map(fn($role) => [
            'id'   => $role->id,
            'name' => $role->name,
        ]);
        return $roles->first() ?? [];
    }

    public function getRoleIdAttribute(): ?int
    {
        return $this->getRoleData()['id'] ?? null;
    }

    public function getRoleNameAttribute(): ?string
    {
        return $this->getRoleData()['name'] ?? null;
    }

    public function getPermissionsListAttribute(): array
    {
        return $this->getAllPermissions()
            ->pluck('name')
            ->toArray();
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
