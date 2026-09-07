<?php

namespace App\Models;

use App\Services\Access\AccessService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_PENDING = 'pending_activation';
    public const STATUS_LOCKED = 'locked';

    private ?int $openAssignedTicketCountCache = null;

    private ?int $openEscalationCountCache = null;

    private ?int $openSlaWatchCountCache = null;

    private ?int $unreadNotificationCountCache = null;

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'contact_no',
        'employee_ref',
        'position',
        'email_verified_at',
        'role',
        'role_id',
        'department_code',
        'department_id',
        'supervisor_id',
        'user_type',
        'account_status',
        'availability_status',
        'last_login_at',
        'failed_login_count',
        'locked_until',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
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
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            $user->syncAccessColumns();
        });
    }

    public function accessRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function accessDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_users')
            ->withPivot(['is_primary', 'assigned_by'])
            ->withTimestamps();
    }

    public function accountLinks(): HasMany
    {
        return $this->hasMany(AccountLink::class, 'user_id');
    }

    public function memberProfile(): HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function staffAvailability(): HasOne
    {
        return $this->hasOne(UserAvailability::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(UserSchedule::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(UserSkill::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class);
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function unreadNotificationCount(): int
    {
        return $this->unreadNotificationCountCache ??= AppNotification::query()
            ->where('user_id', $this->id)
            ->whereNull('read_at')
            ->count();
    }

    public function openAssignedTicketCount(): int
    {
        return $this->openAssignedTicketCountCache ??= $this->assignedTickets()
            ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED])
            ->count();
    }

    public function openEscalationCount(): int
    {
        return $this->openEscalationCountCache ??= TicketEscalation::query()
            ->whereNull('resolved_at')
            ->when(! $this->canOpenUnassignedTickets(), function ($query) {
                $query->whereHas('ticket', fn ($ticket) => $ticket->where('assigned_to', $this->id));
            }, function ($query) {
                if (! $this->canSeeAllTicketDepartments() && filled($this->department_code)) {
                    $query->whereHas('ticket', function ($ticket) {
                        $ticket->where('assigned_department', $this->department_code)
                            ->orWhere('assigned_to', $this->id);
                    });
                }
            })
            ->count();
    }

    /**
     * Open tickets on the SLA Monitoring list that are already overdue
     * or due within the next 4 hours.
     */
    public function openSlaWatchCount(): int
    {
        return $this->openSlaWatchCountCache ??= Ticket::query()
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now()->addHours(4))
            ->whereNotIn('status', [Ticket::STATUS_CLOSED, Ticket::STATUS_RESOLVED])
            ->when(! $this->isTicketAdmin() && ! $this->isTicketSupervisor(), function ($query) {
                $query->where('assigned_to', $this->id);
            }, function ($query) {
                if (! $this->isTicketAdmin() && filled($this->department_code)) {
                    $query->where('assigned_department', $this->department_code);
                }
            })
            ->count();
    }

    public function syncAccessColumns(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('roles')) {
            return;
        }

        if ($this->role_id && $this->accessRole) {
            $this->role = $this->legacyRoleLabel($this->accessRole->code);
            $this->user_type = $this->accessRole->user_type ?: $this->user_type;
        }

        if ($this->department_id && $this->accessDepartment) {
            $this->department_code = $this->accessDepartment->code;
        } elseif ($this->department_code && ! $this->department_id) {
            $department = Department::query()->where('code', $this->department_code)->first();
            if ($department) {
                $this->department_id = $department->id;
            }
        }

        if (! $this->account_status) {
            $this->account_status = $this->email_verified_at ? self::STATUS_ACTIVE : self::STATUS_PENDING;
        }
    }

    public function legacyRoleLabel(string $code): string
    {
        return match ($code) {
            'super_admin', 'administrator' => 'Administrator',
            'supervisor' => 'Supervisor',
            'csr', 'support_manager' => 'Customer Service',
            'content_manager' => 'Content Manager',
            'customer' => 'User',
            default => $this->role ?: $code,
        };
    }

    public function canAccess(string $permission): bool
    {
        return app(AccessService::class)->allows($this, $permission);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'customer_id');
    }

    public function walletLoadRequests(): HasMany
    {
        return $this->hasMany(WalletLoadRequest::class, 'customer_id');
    }

    public function canLoadWallet(): bool
    {
        return $this->canAccess('wallet.load');
    }

    public function isActiveCustomer(): bool
    {
        return $this->hasVerifiedEmail()
            && ! app(AccessService::class)->isSupport($this)
            && ($this->account_status ?? self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    public function isTicketAdmin(): bool
    {
        return app(AccessService::class)->isSuperAdmin($this);
    }

    /**
     * Where this account should land after a successful web login.
     * Admins and customers stay on the general dashboard. Support and
     * other non-admin ticket staff go to the department queue.
     */
    public function loginHomePath(): string
    {
        if ((string) ($this->role ?? '') === 'staff') {
            return '/t/dashboard';
        }

        if ($this->isTicketAdmin()) {
            return '/u/dashboard';
        }

        if (app(AccessService::class)->isSupport($this)) {
            return '/workspace/department-queue';
        }

        return '/u/dashboard';
    }

    /**
     * Roles an administrator can assign on staff accounts.
     *
     * @return list<string>
     */
    public static function managedRoles(): array
    {
        return [
            'User',
            'Administrator',
            'support',
            'Customer Service',
            'Supervisor',
            'Content Manager',
        ];
    }

    /**
     * Support-style roles that can be assigned tickets.
     *
     * @return list<string>
     */
    public static function assignableStaffRoles(): array
    {
        return array_values(array_unique(array_merge(
            config('tickets.csr_roles', []),
            config('tickets.supervisor_roles', []),
            ['support'],
        )));
    }

    public function canAssignTickets(): bool
    {
        return $this->canAccess('tickets.assign') || $this->canAccess('tickets.reassign');
    }

    public function isTicketSupervisor(): bool
    {
        $code = $this->accessRole?->code;

        return $code === 'supervisor'
            || in_array((string) ($this->role ?? ''), config('tickets.supervisor_roles', []), true);
    }

    public function isTicketCsr(): bool
    {
        $code = $this->accessRole?->code;

        return in_array($code, ['csr', 'support_manager'], true)
            || in_array((string) ($this->role ?? ''), config('tickets.csr_roles', []), true);
    }

    public function canManageTickets(): bool
    {
        return $this->canAccess('tickets.view');
    }

    public function canSeeAllTicketDepartments(): bool
    {
        return app(AccessService::class)->scopeFor($this) === AccessService::SCOPE_ALL;
    }

    public function canOpenUnassignedTickets(): bool
    {
        return $this->isTicketAdmin() || $this->isTicketSupervisor() || $this->isTicketCsr();
    }

    public function canIntakeTickets(): bool
    {
        return $this->canAccess('tickets.create');
    }

    public function canVerifyTicketFeedback(): bool
    {
        return $this->canAccess('tickets.close') || $this->canIntakeTickets();
    }

    public function isAccountActive(): bool
    {
        return app(AccessService::class)->isAccountUsable($this);
    }
}
