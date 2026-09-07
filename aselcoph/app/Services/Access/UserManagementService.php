<?php

namespace App\Services\Access;

use App\Models\AccountLink;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TAccountRaw;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserManagementService
{
    public function __construct(
        private AccessService $access,
        private ActivityLogger $activity,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $actor, array $payload): User
    {
        $role = isset($payload['role_id']) ? Role::query()->find($payload['role_id']) : null;
        $department = isset($payload['department_id']) ? Department::query()->find($payload['department_id']) : null;
        $rawPassword = $payload['password'] ?? Str::random(10);

        $user = User::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'username' => $payload['username'] ?? null,
            'contact_no' => $payload['contact_no'] ?? null,
            'employee_ref' => $payload['employee_ref'] ?? null,
            'position' => $payload['position'] ?? null,
            'role_id' => $role?->id,
            'role' => $role?->name ?? ($payload['role'] ?? 'User'),
            'department_id' => $department?->id,
            'department_code' => $department?->code,
            'supervisor_id' => $payload['supervisor_id'] ?? null,
            'user_type' => $payload['user_type'] ?? ($role?->user_type ?? 'support'),
            'account_status' => $payload['account_status'] ?? User::STATUS_ACTIVE,
            'availability_status' => $payload['availability_status'] ?? 'offline',
            'password' => Hash::make($rawPassword),
            'email_verified_at' => now(),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        if ($department) {
            $user->departments()->sync([$department->id => ['is_primary' => true, 'assigned_by' => $actor->id]]);
        }

        if (! empty($payload['skills']) && is_array($payload['skills'])) {
            $this->syncSkills($user, $payload['skills']);
        }

        $this->activity->record('user.created', $actor, $user, null, $user->only(['name', 'email', 'role_id', 'department_id']));

        if (! empty($payload['send_credentials'])) {
            Mail::raw(
                "Welcome to ASELCO.\n\nEmail: {$user->email}\nPassword: {$rawPassword}",
                fn ($message) => $message->to($user->email)->subject('Your ASELCO account')
            );
        }

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(User $actor, User $user, array $payload): User
    {
        $old = $user->only(['name', 'email', 'role_id', 'department_id', 'account_status', 'availability_status']);

        $role = isset($payload['role_id']) ? Role::query()->find($payload['role_id']) : $user->accessRole;
        $department = array_key_exists('department_id', $payload)
            ? Department::query()->find($payload['department_id'])
            : $user->accessDepartment;

        $user->fill([
            'name' => $payload['name'] ?? $user->name,
            'email' => $payload['email'] ?? $user->email,
            'username' => $payload['username'] ?? $user->username,
            'contact_no' => $payload['contact_no'] ?? $user->contact_no,
            'employee_ref' => $payload['employee_ref'] ?? $user->employee_ref,
            'position' => $payload['position'] ?? $user->position,
            'role_id' => $role?->id,
            'department_id' => $department?->id,
            'department_code' => $department?->code,
            'supervisor_id' => $payload['supervisor_id'] ?? $user->supervisor_id,
            'user_type' => $payload['user_type'] ?? $role?->user_type ?? $user->user_type,
            'account_status' => $payload['account_status'] ?? $user->account_status,
            'availability_status' => $payload['availability_status'] ?? $user->availability_status,
            'updated_by' => $actor->id,
        ]);

        if (array_key_exists('email_validated', $payload)) {
            $user->email_verified_at = $payload['email_validated'] ? ($user->email_verified_at ?? now()) : null;
        }

        $user->save();

        if ($department) {
            $user->departments()->sync([$department->id => ['is_primary' => true, 'assigned_by' => $actor->id]]);
        }

        if (isset($payload['skills']) && is_array($payload['skills'])) {
            $this->syncSkills($user, $payload['skills']);
        }

        $this->activity->record('user.updated', $actor, $user, $old, $user->only(['name', 'email', 'role_id', 'department_id', 'account_status']));

        return $user->fresh();
    }

    public function updatePhoto(User $actor, User $user, UploadedFile $photo): User
    {
        $old = $user->profile_photo_path;
        $user->updateProfilePhoto($photo);
        $user->forceFill(['updated_by' => $actor->id])->save();

        $this->activity->record(
            'user.photo',
            $actor,
            $user,
            ['profile_photo_path' => $old],
            ['profile_photo_path' => $user->profile_photo_path]
        );

        return $user->fresh();
    }

    public function deletePhoto(User $actor, User $user): User
    {
        $old = $user->profile_photo_path;
        $user->deleteProfilePhoto();
        $user->forceFill(['updated_by' => $actor->id])->save();

        $this->activity->record(
            'user.photo',
            $actor,
            $user,
            ['profile_photo_path' => $old],
            ['profile_photo_path' => null]
        );

        return $user->fresh();
    }

    public function setStatus(User $actor, User $user, string $status): User
    {
        if (! in_array($status, [
            User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED,
            User::STATUS_PENDING, User::STATUS_LOCKED,
        ], true)) {
            throw new HttpException(422, 'Invalid account status.');
        }

        $old = $user->account_status;
        $user->forceFill([
            'account_status' => $status,
            'locked_until' => $status === User::STATUS_LOCKED ? now()->addMinutes((int) $this->access->setting('lockout_minutes', 15)) : null,
            'updated_by' => $actor->id,
        ])->save();

        $this->activity->record('user.status', $actor, $user, ['account_status' => $old], ['account_status' => $status]);

        return $user;
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $payload
     */
    public function bulk(User $actor, array $ids, array $payload): int
    {
        $count = 0;
        User::query()->whereIn('id', $ids)->each(function (User $user) use ($actor, $payload, &$count) {
            if (isset($payload['account_status'])) {
                $this->setStatus($actor, $user, (string) $payload['account_status']);
            }
            if (isset($payload['department_id']) || isset($payload['role_id']) || isset($payload['availability_status'])) {
                $this->update($actor, $user, $payload);
            }
            $count++;
        });

        return $count;
    }

    /**
     * @param  list<string>  $codes
     * @param  array<string, bool>  $overrides  code => allowed
     */
    public function syncUserPermissions(User $actor, User $user, array $overrides): void
    {
        $permissions = Permission::query()->whereIn('code', array_keys($overrides))->get()->keyBy('code');
        $user->permissionOverrides()->delete();

        foreach ($overrides as $code => $allowed) {
            $permission = $permissions->get($code);
            if (! $permission) {
                continue;
            }
            $user->permissionOverrides()->create([
                'permission_id' => $permission->id,
                'allowed' => (bool) $allowed,
            ]);
        }

        $this->activity->record('user.permissions', $actor, $user, null, $overrides);
    }

    /**
     * @param  list<string>  $skills
     */
    public function syncSkills(User $user, array $skills): void
    {
        $user->skills()->delete();
        foreach (array_filter($skills) as $skill) {
            $user->skills()->create(['skill' => (string) $skill]);
        }
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, mixed>>
     */
    public function previewImport(array $rows): array
    {
        $emails = User::query()->pluck('email')->map(fn ($e) => strtolower((string) $e))->all();
        $usernames = User::query()->whereNotNull('username')->pluck('username')->map(fn ($e) => strtolower((string) $e))->all();
        $roles = Role::query()->pluck('id', 'code');
        $departments = Department::query()->pluck('id', 'code');

        $preview = [];
        foreach ($rows as $index => $row) {
            $errors = [];
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $username = strtolower(trim((string) ($row['username'] ?? '')));
            $name = trim((string) ($row['name'] ?? ''));
            $roleCode = trim((string) ($row['role'] ?? ''));
            $deptCode = trim((string) ($row['department'] ?? ''));

            if ($name === '') {
                $errors[] = 'Missing name';
            }
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email';
            } elseif (in_array($email, $emails, true)) {
                $errors[] = 'Duplicate email';
            }
            if ($username !== '' && in_array($username, $usernames, true)) {
                $errors[] = 'Duplicate username';
            }
            if ($roleCode !== '' && ! $roles->has($roleCode)) {
                $errors[] = 'Invalid role';
            }
            if ($deptCode !== '' && ! $departments->has($deptCode)) {
                $errors[] = 'Invalid department';
            }

            $preview[] = [
                'row' => $index + 1,
                'name' => $name,
                'email' => $email,
                'username' => $username,
                'role' => $roleCode,
                'department' => $deptCode,
                'valid' => $errors === [],
                'errors' => $errors,
            ];
        }

        return $preview;
    }

    /**
     * @param  list<array<string, mixed>>  $preview
     */
    public function commitImport(User $actor, array $preview): int
    {
        $count = 0;
        foreach ($preview as $row) {
            if (empty($row['valid'])) {
                continue;
            }
            $role = Role::query()->where('code', $row['role'] ?? '')->first();
            $department = Department::query()->where('code', $row['department'] ?? '')->first();
            $this->create($actor, [
                'name' => $row['name'],
                'email' => $row['email'],
                'username' => $row['username'] ?: null,
                'role_id' => $role?->id,
                'department_id' => $department?->id,
                'user_type' => $role?->user_type ?? 'support',
                'send_credentials' => false,
            ]);
            $count++;
        }

        return $count;
    }

    public function resetAccess(User $actor, User $user): string
    {
        $password = Str::random(12);
        $user->forceFill([
            'password' => Hash::make($password),
            'failed_login_count' => 0,
            'locked_until' => null,
            'account_status' => User::STATUS_ACTIVE,
            'updated_by' => $actor->id,
        ])->save();

        $this->activity->record('user.reset_access', $actor, $user);

        return $password;
    }

    public function changePassword(User $actor, User $user, ?string $password = null): string
    {
        $password = $password ?: $this->makePassword();
        $user->forceFill([
            'password' => Hash::make($password),
            'updated_by' => $actor->id,
        ])->save();

        $this->activity->record('user.password', $actor, $user);

        return $password;
    }

    private function makePassword(): string
    {
        $min = (int) $this->access->setting('password_min_length', config('access.password_min_length', 8));
        $length = max($min, 12);

        if ($this->access->setting('password_require_mixed', config('access.password_require_mixed', false))) {
            return Str::password($length);
        }

        return Str::random($length);
    }

    public function addAccountLink(User $actor, User $user, string $accountNumber, ?string $ownerName, bool $validate = false): AccountLink
    {
        if (($user->user_type ?? '') !== 'customer') {
            throw new HttpException(422, 'Account links can only be added to customer accounts.');
        }

        $accountNumber = trim($accountNumber);
        if ($accountNumber === '') {
            throw new HttpException(422, 'Account number is required.');
        }

        $existingCount = AccountLink::query()->where('user_id', $user->id)->count();
        if ($existingCount >= 10) {
            throw new HttpException(422, 'This customer already has the maximum number of account links.');
        }

        $duplicate = AccountLink::query()
            ->where('user_id', $user->id)
            ->where('account_number', $accountNumber)
            ->exists();
        if ($duplicate) {
            throw new HttpException(422, 'This account number is already linked or requested for this customer.');
        }

        $taken = AccountLink::query()
            ->where('account_number', $accountNumber)
            ->whereNotNull('validated_at')
            ->where('user_id', '!=', $user->id)
            ->exists();
        if ($taken) {
            throw new HttpException(422, 'This account number is already validated on another profile.');
        }

        $raw = Schema::hasTable('t_accounts_raw')
            ? TAccountRaw::query()->where('account_no', $accountNumber)->first()
            : null;

        if ($raw && $raw->user_id && (int) $raw->user_id !== (int) $user->id) {
            throw new HttpException(422, 'This service account is already linked to another user.');
        }

        $owner = strtoupper(trim((string) ($ownerName ?: ($raw->customer ?? $user->name))));
        if ($owner === '') {
            throw new HttpException(422, 'Owner name is required when the account is not in the billing records.');
        }

        $link = AccountLink::query()->create([
            'user_id' => $user->id,
            'account_number' => $accountNumber,
            'owner_name' => $owner,
            'validated_at' => $validate ? now() : null,
            'validated_by' => $validate ? $actor->name : null,
        ]);

        if ($validate && $raw) {
            $raw->user_id = $user->id;
            $raw->status = 'Linked';
            $raw->save();
        }

        $this->activity->record('user.account_link', $actor, $user, null, [
            'account_number' => $accountNumber,
            'validated' => $validate,
        ]);

        return $link;
    }
}
