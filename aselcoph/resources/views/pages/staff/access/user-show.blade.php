<x-app-layout>
    @php
        $isCustomer = $isCustomer ?? (($user->user_type ?? '') === 'customer');
        $isSupport = ! $isCustomer;
        $roleKey = $user->accessRole?->code ?: $user->role;
        $deptCode = $user->accessDepartment?->code ?: $user->department_code;
        $typeLabel = $isCustomer
            ? 'Customer'
            : ($user->accessRole?->name ?: \App\Support\TicketUi::roleLabel($roleKey) ?: 'Support');
        $typeTone = $isCustomer ? 'sky' : \App\Support\TicketUi::roleTone($roleKey);
        $typeIcon = $isCustomer ? 'bi-person' : \App\Support\TicketUi::roleIcon($roleKey);
        $accountNoun = $isCustomer ? 'customer' : 'staff account';
        $ticketQueueUrl = $isCustomer
            ? route('tickets.queue', ['search' => $user->email])
            : route('tickets.queue', ['search' => $user->name]);
        $ticketIntakeUrl = route('tickets.intake', ['customer' => $user->id]);
        $walletUrl = route('ast.admin.customer-wallet', $user->id);
        $astBalance = collect($astWallets ?? [])->sum(fn ($wallet) => (float) $wallet->balance);
        $displayBalance = ($astWallets ?? collect())->isNotEmpty()
            ? $astBalance
            : (float) ($wallet->balance ?? 0);
        $hasTwoFactor = filled($user->two_factor_secret);
        $isLocked = $user->account_status === 'locked' || ($user->locked_until && $user->locked_until->isFuture());
        $hasProfilePhoto = filled($user->profile_photo_path);
        $defaultAvatarUrl = asset('/user.png');
        $profilePhotoUrl = $hasProfilePhoto
            ? asset('storage/'.ltrim((string) $user->profile_photo_path, '/'))
            : $defaultAvatarUrl;
        $passwordMin = (int) app(\App\Services\Access\AccessService::class)->setting('password_min_length', config('access.password_min_length', 8));
        $passwordMixed = (bool) app(\App\Services\Access\AccessService::class)->setting('password_require_mixed', config('access.password_require_mixed', false));
        if ($isCustomer) {
            $backRoute = route('access.customers.index');
            $backLabel = 'Customers';
        } elseif (request()->routeIs('access.support.*')) {
            $backRoute = route('access.support.index');
            $backLabel = 'Support';
        } else {
            $backRoute = route('access.users.index');
            $backLabel = 'Users';
        }
    @endphp

    <x-slot name="title">{{ $user->name }}</x-slot>
    <x-slot name="subtitle">{{ $isCustomer ? 'Customer account details for '.$user->email.'.' : $typeLabel.' account details for '.$user->email.'.' }}</x-slot>
    <x-slot name="url_1">{"link": "{{ $backRoute }}", "text": "{{ $backLabel }}"}</x-slot>
    <x-slot name="url_2">{"link": "{{ url()->current() }}", "text": "Detail"}</x-slot>
    <x-slot name="active">{{ $user->name }}</x-slot>
    <x-slot name="headerAvatar">
        @can('users.edit')
            <button type="button" class="page-header-card__avatar-btn" data-ul-modal="#account-photo-modal" title="{{ $hasProfilePhoto ? 'Change photo' : 'Add photo' }}" aria-label="{{ $hasProfilePhoto ? 'Change photo' : 'Add photo' }}">
                <img
                    src="{{ $profilePhotoUrl }}"
                    alt="{{ $user->name }}"
                    class="page-header-card__avatar-img"
                    onerror="this.onerror=null; this.src='{{ $defaultAvatarUrl }}';"
                >
            </button>
            <span class="page-header-card__avatar-camera" aria-hidden="true"><i class="bi bi-camera"></i></span>
        @else
            <span class="page-header-card__avatar-face">
                <img
                    src="{{ $profilePhotoUrl }}"
                    alt="{{ $user->name }}"
                    class="page-header-card__avatar-img"
                    onerror="this.onerror=null; this.src='{{ $defaultAvatarUrl }}';"
                >
            </span>
        @endcan
    </x-slot>
    <x-slot name="buttons">
        <a href="{{ $backRoute }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
            <i class="bi bi-arrow-left"></i>{{ $backLabel }}
        </a>
        @can('tickets.view')
            <a href="{{ $ticketQueueUrl }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                <i class="bi bi-ticket-detailed"></i>{{ $isCustomer ? 'View tickets' : 'Assigned tickets' }}
            </a>
        @endcan
        @if($isCustomer)
            @canany(['users.edit', 'customers.edit'])
                <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-view" data-ul-modal="#account-link-modal">
                    <i class="bi bi-plug"></i>Add account
                </button>
            @endcanany
            @can('tickets.create')
                <a href="{{ $ticketIntakeUrl }}" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                    <i class="bi bi-plus-lg"></i>Create ticket
                </a>
            @endcan
        @endif
        @can('users.suspend')
            <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-more" data-ul-modal="#account-status-modal">
                <i class="bi bi-shield-check"></i>Update status
            </button>
        @endcan
        @can('users.edit')
            <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-edit" data-ul-modal="#account-edit-modal">
                <i class="bi bi-pencil"></i>Edit profile
            </button>
        @endcan
        @can('users.edit')
            <form
                method="POST"
                action="{{ route('access.users.reset', $user) }}"
                class="inline"
                data-ul-confirm="Reset access and issue a temporary password."
                data-ul-confirm-verb="Reset"
                data-ul-confirm-tone="danger"
                data-ul-confirm-icon="bi-arrow-counterclockwise"
            >
                @csrf
                <button class="ti-btn ti-btn-sm ul-btn ul-btn-danger">
                    <i class="bi bi-arrow-counterclockwise"></i>Reset access
                </button>
            </form>
        @endcan
    </x-slot>

    @if(session('success')) <div class="alert alert-success mb-4">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger mb-4">{{ session('error') }}</div> @endif
    @include('pages.staff.access._nav')

    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-8 col-span-12 space-y-6">
            <div class="box ul-card">
                <div class="box-header ul-card-header flex flex-wrap items-center justify-between gap-3">
                    <div class="td-hero-title">
                        <div class="box-title ul-card-title mb-0">{{ $user->name }}</div>
                        <div class="td-hero-badges">
                            <x-unified.badge :tone="$typeTone" :icon="$typeIcon">{{ $typeLabel }}</x-unified.badge>
                            <x-unified.badge
                                :tone="\App\Support\AccessUi::accountStatusTone($user->account_status)"
                                :icon="\App\Support\AccessUi::accountStatusIcon($user->account_status)"
                            >{{ \App\Support\AccessUi::accountStatusLabel($user->account_status) }}</x-unified.badge>
                            @if($isSupport)
                                <x-unified.badge
                                    :tone="\App\Support\AccessUi::availabilityTone($user->availability_status)"
                                    :icon="\App\Support\AccessUi::availabilityIcon($user->availability_status)"
                                >{{ \App\Support\AccessUi::availabilityLabel($user->availability_status) }}</x-unified.badge>
                            @endif
                            @if($user->email_verified_at)
                                <x-unified.badge tone="lime" icon="bi-envelope-check">Verified</x-unified.badge>
                            @else
                                <x-unified.badge tone="amber" icon="bi-envelope">Unverified</x-unified.badge>
                            @endif
                            @if($hasTwoFactor)
                                <x-unified.badge tone="indigo" icon="bi-shield-lock">2FA</x-unified.badge>
                            @endif
                            @if($isLocked)
                                <x-unified.badge tone="rose" icon="bi-lock">Locked</x-unified.badge>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="box-body p-0">
                    <div class="ul-table-wrap">
                        <table class="table ul-table td-kv-table mb-0">
                            <tbody>
                                <tr>
                                    <th>Email</th>
                                    <td>
                                        <div class="font-semibold">{{ $user->email }}</div>
                                        <div class="ul-date-meta">{{ $user->email_verified_at?->timezone('Asia/Manila')?->format('M d, Y h:i A') ?: 'Not verified' }}</div>
                                    </td>
                                    <th>Username</th>
                                    <td>{{ $user->username ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <th>Mobile</th>
                                    <td>{{ $user->contact_no ?: '—' }}</td>
                                    <th>Last login</th>
                                    <td>{{ optional($user->last_login_at)->timezone('Asia/Manila')?->format('M d, Y h:i A') ?: 'Never' }}</td>
                                </tr>
                                @if($isSupport)
                                    <tr>
                                        <th>Position</th>
                                        <td>{{ $user->position ?: '—' }}</td>
                                        <th>Employee ref</th>
                                        <td>{{ $user->employee_ref ?: '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Department</th>
                                        <td>
                                            @if($deptCode)
                                                <x-unified.badge
                                                    :tone="\App\Support\TicketUi::departmentTone($deptCode)"
                                                    :icon="\App\Support\TicketUi::departmentIcon($deptCode)"
                                                >{{ $deptCode }}</x-unified.badge>
                                                <div class="ul-date-meta">{{ \App\Support\TicketUi::departmentMeaning($deptCode) ?: ($user->accessDepartment?->name ?: '') }}</div>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <th>Role</th>
                                        <td>
                                            @if($roleKey)
                                                <x-unified.badge
                                                    :tone="\App\Support\TicketUi::roleTone($roleKey)"
                                                    :icon="\App\Support\TicketUi::roleIcon($roleKey)"
                                                >{{ $user->accessRole?->name ?: \App\Support\TicketUi::roleLabel($roleKey) }}</x-unified.badge>
                                                <div class="ul-date-meta">{{ \App\Support\TicketUi::roleMeaning($roleKey) }}</div>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Supervisor</th>
                                        <td>{{ $user->supervisor?->name ?: '—' }}</td>
                                        <th>Availability</th>
                                        <td>{{ \App\Support\AccessUi::availabilityLabel($user->availability_status) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <th>Failed logins</th>
                                    <td>{{ (int) $user->failed_login_count }}</td>
                                    <th>Locked until</th>
                                    <td>{{ optional($user->locked_until)->timezone('Asia/Manila')?->format('M d, Y h:i A') ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <th>Created</th>
                                    <td>{{ optional($user->created_at)->timezone('Asia/Manila')?->format('M d, Y h:i A') ?: '—' }}</td>
                                    <th>Updated</th>
                                    <td>{{ optional($user->updated_at)->timezone('Asia/Manila')?->format('M d, Y h:i A') ?: '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if($isCustomer)
                @php $memberProfile = $memberProfile ?? $user->memberProfile; @endphp
                <div class="box ul-card">
                    <div class="box-header ul-card-header flex flex-wrap items-center justify-between gap-3">
                        <div class="box-title ul-card-title mb-0">Membership application</div>
                        <div class="flex flex-wrap gap-2">
                            @if($memberProfile)
                                <x-unified.badge tone="lime" icon="bi-check-circle">Personal information on file</x-unified.badge>
                            @else
                                <x-unified.badge tone="amber" icon="bi-hourglass">Awaiting personal information</x-unified.badge>
                            @endif
                            <a href="{{ route('access.customers.membership-application', $user) }}" target="_blank" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                                <i class="bi bi-printer"></i>Print / Save as PDF
                            </a>
                        </div>
                    </div>
                    <div class="box-body p-0">
                        <div class="ul-table-wrap">
                            <table class="table ul-table td-kv-table mb-0">
                                <tbody>
                                    <tr>
                                        <th>Address</th>
                                        <td colspan="3">{{ $memberProfile?->address ?: '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Region</th>
                                        <td>{{ $memberProfile?->region_name ?: '—' }}</td>
                                        <th>Province</th>
                                        <td>{{ $memberProfile?->province_name ?: '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Municipality / City</th>
                                        <td>{{ $memberProfile?->city_municipality_name ?: '—' }}</td>
                                        <th>Barangay</th>
                                        <td>{{ $memberProfile?->barangay_name ?: '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Street</th>
                                        <td>{{ $memberProfile?->street ?: '—' }}</td>
                                        <th>Sitio</th>
                                        <td>{{ $memberProfile?->sitio ?: '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Civil status</th>
                                        <td>{{ $memberProfile?->civilStatusLabel() ?: '—' }}</td>
                                        <th>Sex</th>
                                        <td>{{ $memberProfile?->sexLabel() ?: '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Contact #</th>
                                        <td>{{ $memberProfile?->contact_no ?: ($user->contact_no ?: '—') }}</td>
                                        <th>Date of seminar</th>
                                        <td>{{ $memberProfile?->date_of_seminar?->timezone('Asia/Manila')?->format('M d, Y') ?: '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Remarks</th>
                                        <td colspan="3">{{ $memberProfile?->remarks ?: '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            @if($isCustomer)
                <x-unified.table title="Service accounts" :items="$accounts" :colspan="6" empty-title="No linked service accounts." empty-text="This customer has no linked billing or service accounts.">
                    <x-slot:head>
                        <th class="ul-row-num">#</th>
                        <th>Account</th>
                        <th>Meter</th>
                        <th>Rate class</th>
                        <th>Status</th>
                        <th>Address</th>
                    </x-slot:head>
                    @foreach($accounts as $account)
                        <tr>
                            <td class="ul-row-num">{{ $loop->iteration }}</td>
                            <td>
                                <div class="font-semibold">{{ $account->account_no ?? $account->id }}</div>
                                <div class="ul-date-meta">{{ $account->customer ?: ($account->name ?? 'Linked account') }}</div>
                            </td>
                            <td>{{ $account->meter_no ?: '—' }}</td>
                            <td>{{ $account->rate_class ?: '—' }}</td>
                            <td>
                                @if($account->status)
                                    <x-unified.badge :tone="$account->status === 'Linked' ? 'lime' : 'slate'" icon="bi-plug">
                                        {{ $account->status }}
                                    </x-unified.badge>
                                @else
                                    <span class="ul-empty">—</span>
                                @endif
                            </td>
                            <td>{{ $account->address ?: '—' }}</td>
                        </tr>
                    @endforeach
                </x-unified.table>

                <x-unified.table title="Account link requests" :items="$accountLinks" :colspan="5" empty-title="No account link requests." empty-text="Membership link requests will appear here.">
                    <x-slot:head>
                        <th class="ul-row-num">#</th>
                        <th>Account</th>
                        <th>Owner name</th>
                        <th>Status</th>
                        <th class="text-end ul-col-actions">Actions</th>
                    </x-slot:head>
                    @foreach($accountLinks as $link)
                        <tr>
                            <td class="ul-row-num">{{ $loop->iteration }}</td>
                            <td class="font-semibold">{{ $link->account_number }}</td>
                            <td>{{ $link->owner_name ?: '—' }}</td>
                            <td>
                                @if($link->validated_at)
                                    <x-unified.badge tone="lime" icon="bi-check-circle">Validated</x-unified.badge>
                                    <div class="ul-date-meta">{{ $link->validated_at?->timezone('Asia/Manila')?->format('M d, Y') }} · {{ $link->validated_by ?: '—' }}</div>
                                @else
                                    <x-unified.badge tone="amber" icon="bi-hourglass">Pending</x-unified.badge>
                                @endif
                            </td>
                            <td class="text-end ul-col-actions">
                                @if(! $link->validated_at)
                                    <form
                                        method="POST"
                                        action="{{ route('link.update') }}"
                                        data-ul-confirm="Validate this service account link."
                                        data-ul-confirm-verb="Validate"
                                        data-ul-confirm-icon="bi-check2"
                                    >
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $link->id }}">
                                        <button class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                                            <i class="bi bi-check2"></i>Validate
                                        </button>
                                    </form>
                                @else
                                    <span class="ul-empty">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-unified.table>
            @endif

            <x-unified.table
                :title="$isCustomer ? 'Tickets' : 'Assigned tickets'"
                :items="$recentTickets"
                :colspan="6"
                :empty-title="$isCustomer ? 'No tickets yet.' : 'No assigned tickets.'"
                :empty-text="$isCustomer ? 'Create a ticket from this customer when a concern is received.' : 'Tickets assigned to this staff account will appear here.'"
            >
                <x-slot:headerActions>
                    @if($isCustomer)
                        @can('tickets.create')
                            <a href="{{ $ticketIntakeUrl }}" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                                <i class="bi bi-plus-lg"></i>Create
                            </a>
                        @endcan
                    @endif
                    @can('tickets.view')
                        <a href="{{ $ticketQueueUrl }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                            <i class="bi bi-box-arrow-up-right"></i>Queue
                        </a>
                    @endcan
                </x-slot:headerActions>
                <x-slot:head>
                    <th class="ul-row-num">#</th>
                    <th>Ticket</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>SLA</th>
                    <th class="text-end ul-col-actions">Actions</th>
                </x-slot:head>
                @foreach($recentTickets as $ticket)
                    @php
                        $href = route('tickets.show', $ticket->id);
                        $overdue = $ticket->sla_due_at && $ticket->sla_due_at->isPast()
                            && ! in_array($ticket->status, ['closed', 'resolved']);
                        $ticketMeta = $isCustomer
                            ? ($ticket->assignee?->name ?: 'Unassigned')
                            : ($ticket->customer?->name ?: 'No customer');
                    @endphp
                    <x-unified.row :href="$href">
                        <td class="ul-row-num">{{ $loop->iteration }}</td>
                        <x-unified.td-primary :href="$href" :text="$ticket->ticket_no">
                            <x-slot:meta>{{ $ticketMeta }}</x-slot:meta>
                        </x-unified.td-primary>
                        <td>
                            <x-unified.badge
                                :tone="\App\Support\TicketUi::categoryTone($ticket->category)"
                                :icon="\App\Support\TicketUi::categoryIcon($ticket->category)"
                            >{{ \App\Support\TicketUi::categoryLabel($ticket->category) }}</x-unified.badge>
                        </td>
                        <td>
                            <x-unified.badge
                                :tone="\App\Support\TicketUi::statusTone($ticket->status)"
                                :icon="\App\Support\TicketUi::statusIcon($ticket->status)"
                            >{{ \App\Support\TicketUi::statusLabel($ticket->status) }}</x-unified.badge>
                        </td>
                        <td class="ul-date">
                            @if($ticket->sla_due_at)
                                @if($overdue)
                                    <x-unified.badge tone="danger" icon="bi-exclamation-octagon">Overdue</x-unified.badge>
                                @else
                                    {{ $ticket->sla_due_at->diffForHumans() }}
                                @endif
                                <div class="ul-date-meta">{{ $ticket->sla_due_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}</div>
                            @else
                                <span class="ul-empty">—</span>
                            @endif
                        </td>
                        <x-unified.actions :view-url="$href" />
                    </x-unified.row>
                @endforeach
            </x-unified.table>

            <x-unified.table title="Sessions" :items="$sessions" :colspan="5" empty-title="No active sessions." empty-text="Signed-in devices for this {{ $accountNoun }} will appear here.">
                <x-slot:headerActions>
                    @can('sessions.revoke')
                        @if($sessions->isNotEmpty())
                            <form
                                method="POST"
                                action="{{ route('access.sessions.revoke-user', $user) }}"
                                data-ul-confirm="Revoke every signed-in session for this {{ $accountNoun }}."
                                data-ul-confirm-verb="Revoke"
                                data-ul-confirm-tone="danger"
                                data-ul-confirm-icon="bi-x-lg"
                            >
                                @csrf
                                <button class="ti-btn ti-btn-sm ul-btn ul-btn-danger">
                                    <i class="bi bi-x-lg"></i>Revoke all
                                </button>
                            </form>
                        @endif
                    @endcan
                </x-slot:headerActions>
                <x-slot:head>
                    <th class="ul-row-num">#</th>
                    <th>IP</th>
                    <th>Browser</th>
                    <th>Last activity</th>
                    <th class="text-end ul-col-actions">Actions</th>
                </x-slot:head>
                @foreach($sessions as $session)
                    <tr>
                        <td class="ul-row-num">{{ $loop->iteration }}</td>
                        <td>{{ $session->ip_address ?: '—' }}</td>
                        <td>
                            <div class="text-sm">{{ \Illuminate\Support\Str::limit($session->user_agent, 56) }}</div>
                        </td>
                        <td class="ul-date">{{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->timezone('Asia/Manila')->format('M d, Y h:i A') }}</td>
                        <td class="text-end ul-col-actions">
                            @can('sessions.revoke')
                                <form
                                    method="POST"
                                    action="{{ route('access.sessions.revoke', $session->id) }}"
                                    data-ul-confirm="Revoke this signed-in session."
                                    data-ul-confirm-verb="Revoke"
                                    data-ul-confirm-tone="danger"
                                    data-ul-confirm-icon="bi-x-lg"
                                >
                                    @csrf
                                    <button class="ti-btn ti-btn-sm ul-btn ul-btn-danger" title="Revoke">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-unified.table>

            <x-unified.table title="Activity" :items="$activity" :colspan="4" empty-title="No activity yet." empty-text="Account actions for this {{ $accountNoun }} will appear here.">
                <x-slot:head>
                    <th class="ul-row-num">#</th>
                    <th>When</th>
                    <th>Action</th>
                    <th>Actor</th>
                </x-slot:head>
                @foreach($activity as $log)
                    <tr>
                        <td class="ul-row-num">{{ $loop->iteration }}</td>
                        <td class="ul-date">{{ $log->created_at?->timezone('Asia/Manila')?->format('M d, Y h:i A') }}</td>
                        <td>
                            <div class="font-semibold">{{ \App\Support\AccessUi::label($log->action) }}</div>
                            @if($log->ip_address)
                                <div class="ul-date-meta">{{ $log->ip_address }}</div>
                            @endif
                        </td>
                        <td>{{ $log->user?->name ?: '—' }}</td>
                    </tr>
                @endforeach
            </x-unified.table>

            <x-unified.table title="Notifications" :items="$notifications" :colspan="4" empty-title="No notifications." empty-text="Push and in-app notices for this {{ $accountNoun }} will appear here.">
                <x-slot:head>
                    <th class="ul-row-num">#</th>
                    <th>When</th>
                    <th>Notice</th>
                    <th>Status</th>
                </x-slot:head>
                @foreach($notifications as $notice)
                    <tr>
                        <td class="ul-row-num">{{ $loop->iteration }}</td>
                        <td class="ul-date">{{ $notice->created_at?->timezone('Asia/Manila')?->format('M d, Y h:i A') }}</td>
                        <td>
                            <div class="font-semibold">{{ $notice->title }}</div>
                            <div class="ul-date-meta">{{ \Illuminate\Support\Str::limit($notice->body, 90) }}</div>
                        </td>
                        <td>
                            @if($notice->isUnread())
                                <x-unified.badge tone="amber" icon="bi-dot">Unread</x-unified.badge>
                            @else
                                <x-unified.badge tone="slate" icon="bi-check2">Read</x-unified.badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-unified.table>

            @if($isSupport)
                <x-unified.table title="Access matrix" :items="$matrix" :colspan="7" empty-title="No permissions." empty-text="This account has no catalog permissions yet.">
                    <x-slot:head>
                        <th>Module</th>
                        @foreach(['view','create','edit','delete','assign','approve'] as $action)
                            <th>{{ ucfirst($action) }}</th>
                        @endforeach
                    </x-slot:head>
                    @foreach($matrix as $module => $actions)
                        <tr>
                            <td class="font-semibold">{{ ucfirst(str_replace('_', ' ', $module)) }}</td>
                            @foreach(['view','create','edit','delete','assign','approve'] as $action)
                                <td>
                                    @if(!empty($actions[$action]))
                                        <x-unified.badge tone="lime" icon="bi-check2">Yes</x-unified.badge>
                                    @else
                                        <span class="ul-empty">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </x-unified.table>
            @endif
        </div>

        <div class="xl:col-span-4 col-span-12 space-y-6">
            @if($isCustomer)
                <section class="ul-ast-card" aria-label="Remaining ASELCO Tokens">
                    <div class="ul-ast-card-top">
                        <span class="ul-ast-card-badge">
                            <i class="bi bi-wallet2" aria-hidden="true"></i>
                            Wallet
                        </span>
                        <a class="ul-ast-card-chip" href="{{ $walletUrl }}">View</a>
                    </div>
                    <p class="ul-ast-card-label">Remaining AST</p>
                    <p class="ul-ast-card-amount">{{ number_format($displayBalance, 2) }} AST</p>
                    <p class="ul-ast-card-meta">
                        ASELCO Token · 1 AST ≈ ₱1 · only payment method
                        @if(($astWallets ?? collect())->isEmpty() && ! $wallet)
                            · no wallet yet
                        @endif
                    </p>
                    @if(($astWallets ?? collect())->isNotEmpty() || $wallet)
                        <ul class="ul-ast-card-accounts">
                            @foreach($astWallets ?? [] as $ast)
                                <li class="ul-ast-card-account">
                                    <span class="ul-ast-card-account-icon" aria-hidden="true"><i class="bi bi-lightning-charge"></i></span>
                                    <span class="ul-ast-card-account-copy">
                                        <span class="ul-ast-card-account-no">{{ $ast->account_number }}</span>
                                        <span class="ul-ast-card-account-meta">Electric account</span>
                                    </span>
                                    <span class="ul-ast-card-account-amt">{{ number_format((float) $ast->balance, 2) }} AST</span>
                                </li>
                            @endforeach
                            @if($wallet)
                                <li class="ul-ast-card-account">
                                    <span class="ul-ast-card-account-icon" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
                                    <span class="ul-ast-card-account-copy">
                                        <span class="ul-ast-card-account-no">Legacy wallet</span>
                                        <span class="ul-ast-card-account-meta">{{ \App\Support\AccessUi::label($wallet->status) }}</span>
                                    </span>
                                    <span class="ul-ast-card-account-amt">{{ number_format((float) $wallet->balance, 2) }} AST</span>
                                </li>
                            @endif
                        </ul>
                    @endif
                    <div class="ul-ast-card-actions">
                        <a class="ti-btn ti-btn-sm ul-btn" href="{{ $walletUrl }}">
                            <i class="bi bi-wallet2"></i>View wallet
                        </a>
                        <a class="ti-btn ti-btn-sm ul-btn" href="{{ route('ast.admin.request', ['customer' => $user->id]) }}">
                            <i class="bi bi-send"></i>Request AST
                        </a>
                        @can('wallet.load')
                            <a class="ti-btn ti-btn-sm ul-btn" href="{{ route('ast.admin.load', ['customer' => $user->id]) }}">
                                <i class="bi bi-plus-lg"></i>Load AST
                            </a>
                            <a class="ti-btn ti-btn-sm ul-btn" href="{{ route('ast.admin.load', ['customer' => $user->id, 'mode' => 'reduce']) }}">
                                <i class="bi bi-sliders"></i>Adjust AST
                            </a>
                        @endcan
                    </div>
                </section>
            @endif

            <div class="ul-kpi-grid">
                @if($isCustomer)
                    <div class="ul-kpi is-green">
                        <span class="ul-kpi-icon"><i class="bi bi-wallet2"></i></span>
                        <span class="ul-kpi-copy">
                            <span class="ul-kpi-value">{{ ($astWallets ?? collect())->isNotEmpty() ? number_format($astBalance, 2) : ($wallet?->balance ?? '—') }}</span>
                            <span class="ul-kpi-label">AST</span>
                            <span class="ul-kpi-hint">Token balance</span>
                        </span>
                    </div>
                    <div class="ul-kpi is-cyan">
                        <span class="ul-kpi-icon"><i class="bi bi-plug"></i></span>
                        <span class="ul-kpi-copy">
                            <span class="ul-kpi-value">{{ $accounts->count() }}</span>
                            <span class="ul-kpi-label">Accounts</span>
                            <span class="ul-kpi-hint">Linked service</span>
                        </span>
                    </div>
                @endif
                <div class="ul-kpi is-indigo">
                    <span class="ul-kpi-icon"><i class="bi bi-ticket-detailed"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ $ticketCounts['open'] ?? 0 }}</span>
                        <span class="ul-kpi-label">{{ $isCustomer ? 'Open tickets' : 'Assigned open' }}</span>
                        <span class="ul-kpi-hint">{{ $isCustomer ? 'Active concerns' : 'On this desk' }}</span>
                    </span>
                </div>
                <div class="ul-kpi is-rose">
                    <span class="ul-kpi-icon"><i class="bi bi-exclamation-octagon"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ $ticketCounts['sla'] ?? 0 }}</span>
                        <span class="ul-kpi-label">SLA breaches</span>
                        <span class="ul-kpi-hint">Overdue work</span>
                    </span>
                </div>
                <div class="ul-kpi is-teal">
                    <span class="ul-kpi-icon"><i class="bi bi-laptop"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ $sessions->count() }}</span>
                        <span class="ul-kpi-label">Sessions</span>
                        <span class="ul-kpi-hint">Signed-in now</span>
                    </span>
                </div>
                <div class="ul-kpi is-amber">
                    <span class="ul-kpi-icon"><i class="bi bi-bell"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ $unreadNotifications ?? 0 }}</span>
                        <span class="ul-kpi-label">Unread</span>
                        <span class="ul-kpi-hint">Notices waiting</span>
                    </span>
                </div>
            </div>

            <div class="box ul-card">
                <div class="box-header ul-card-header"><div class="box-title ul-card-title">Quick actions</div></div>
                <div class="ul-qa-grid">
                    @if($isCustomer)
                        @can('tickets.create')
                            <a class="ul-qa" href="{{ $ticketIntakeUrl }}">
                                <span class="ul-qa-icon"><i class="bi bi-plus-lg"></i></span>
                                <span class="ul-qa-copy">
                                    <span class="ul-qa-title">Create ticket</span>
                                    <span class="ul-qa-hint">Log a new concern</span>
                                </span>
                            </a>
                        @endcan
                    @endif
                    @can('users.suspend')
                        <button type="button" class="ul-qa is-slate" data-ul-modal="#account-status-modal">
                            <span class="ul-qa-icon"><i class="bi bi-shield-check"></i></span>
                            <span class="ul-qa-copy">
                                <span class="ul-qa-title">Update status</span>
                                <span class="ul-qa-hint">Activate or lock</span>
                            </span>
                        </button>
                    @endcan
                    @can('users.edit')
                        <button type="button" class="ul-qa is-sky" data-ul-modal="#account-photo-modal">
                            <span class="ul-qa-icon"><i class="bi bi-camera"></i></span>
                            <span class="ul-qa-copy">
                                <span class="ul-qa-title">{{ $hasProfilePhoto ? 'Change photo' : 'Add photo' }}</span>
                                <span class="ul-qa-hint">Upload a profile avatar</span>
                            </span>
                        </button>
                    @endcan
                    @can('tickets.view')
                        <a class="ul-qa is-indigo" href="{{ $ticketQueueUrl }}">
                            <span class="ul-qa-icon"><i class="bi bi-ticket-detailed"></i></span>
                            <span class="ul-qa-copy">
                                <span class="ul-qa-title">{{ $isCustomer ? 'View tickets' : 'Assigned tickets' }}</span>
                                <span class="ul-qa-hint">{{ $isCustomer ? 'Open this queue' : 'Open assigned work' }}</span>
                            </span>
                        </a>
                    @endcan
                    @can('users.edit')
                        <button type="button" class="ul-qa is-orange" data-ul-modal="#account-password-modal">
                            <span class="ul-qa-icon"><i class="bi bi-key"></i></span>
                            <span class="ul-qa-copy">
                                <span class="ul-qa-title">Change password</span>
                                <span class="ul-qa-hint">Generate or type a new one</span>
                            </span>
                        </button>
                        <button type="button" class="ul-qa is-amber" data-ul-modal="#account-edit-modal">
                            <span class="ul-qa-icon"><i class="bi bi-pencil"></i></span>
                            <span class="ul-qa-copy">
                                <span class="ul-qa-title">Edit profile</span>
                                <span class="ul-qa-hint">{{ $isCustomer ? 'Change contact details' : 'Change role and desk' }}</span>
                            </span>
                        </button>
                    @endcan
                    @can('sessions.revoke')
                        <form
                            method="POST"
                            action="{{ route('access.sessions.revoke-user', $user) }}"
                            data-ul-confirm="Revoke every signed-in session for this {{ $accountNoun }}."
                            data-ul-confirm-verb="Revoke"
                            data-ul-confirm-tone="danger"
                            data-ul-confirm-icon="bi-x-lg"
                        >
                            @csrf
                            <button class="ul-qa is-rose">
                                <span class="ul-qa-icon"><i class="bi bi-x-lg"></i></span>
                                <span class="ul-qa-copy">
                                    <span class="ul-qa-title">Revoke sessions</span>
                                    <span class="ul-qa-hint">Sign out all devices</span>
                                </span>
                            </button>
                        </form>
                    @endcan
                    @can('users.edit')
                        <form
                            method="POST"
                            action="{{ route('access.users.reset', $user) }}"
                            data-ul-confirm="Reset access and issue a temporary password."
                            data-ul-confirm-verb="Reset"
                            data-ul-confirm-tone="danger"
                            data-ul-confirm-icon="bi-arrow-counterclockwise"
                        >
                            @csrf
                            <button class="ul-qa is-rose">
                                <span class="ul-qa-icon"><i class="bi bi-arrow-counterclockwise"></i></span>
                                <span class="ul-qa-copy">
                                    <span class="ul-qa-title">Reset access</span>
                                    <span class="ul-qa-hint">Issue a temp password</span>
                                </span>
                            </button>
                        </form>
                    @endcan
                </div>
            </div>

            <div class="box ul-card">
                <div class="box-header ul-card-header"><div class="box-title ul-card-title">{{ $isCustomer ? 'Ticket load' : 'Assignment load' }}</div></div>
                <div class="box-body p-0">
                    <div class="ul-table-wrap">
                        <table class="table ul-table td-kv-table mb-0">
                            <tbody>
                                @foreach(['total' => 'Total', 'new' => 'New', 'pending' => 'Assigned', 'in_progress' => 'In progress', 'escalated' => 'Escalated', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $key => $label)
                                    <tr>
                                        <th>{{ $label }}</th>
                                        <td>{{ $ticketCounts[$key] ?? 0 }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @can('users.edit')
        <div id="account-photo-modal" class="ul-modal" hidden>
            <div class="ul-modal-backdrop" data-ul-modal-close></div>
            <div class="ul-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="account-photo-title">
                <div class="ul-modal-header">
                    <span class="ul-modal-icon ul-badge is-sky"><i class="bi bi-camera" aria-hidden="true"></i></span>
                    <div class="ul-modal-copy">
                        <h6 id="account-photo-title">{{ $hasProfilePhoto ? 'Change photo' : 'Add photo' }}</h6>
                        <p>Upload a square JPG, PNG, or WEBP. Maximum 2 MB.</p>
                    </div>
                    <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="ul-modal-body">
                    <form
                        id="account-photo-upload"
                        method="POST"
                        action="{{ route('access.users.photo', $user) }}"
                        enctype="multipart/form-data"
                    >
                        @csrf
                        <div class="td-photo-stage">
                            <img
                                id="account-photo-preview"
                                class="td-photo-preview"
                                src="{{ $profilePhotoUrl }}"
                                alt="{{ $user->name }}"
                                onerror="this.onerror=null; this.src='{{ $defaultAvatarUrl }}';"
                            >
                            <input
                                id="account-photo-input"
                                class="td-photo-input"
                                type="file"
                                name="photo"
                                accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                            >
                            <div class="td-photo-actions">
                                <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-view" data-ul-photo-pick>
                                    <i class="bi bi-image"></i>Choose photo
                                </button>
                            </div>
                            <p class="td-photo-hint">This photo appears on chats, tickets, and this profile.</p>
                            @error('photo')
                                <p class="td-photo-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </form>
                    <div class="ul-modal-footer">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>
                            <i class="bi bi-x-lg"></i>Cancel
                        </button>
                        @if($hasProfilePhoto)
                            <form
                                method="POST"
                                action="{{ route('access.users.photo.destroy', $user) }}"
                                data-ul-confirm="Remove this profile photo."
                                data-ul-confirm-verb="Remove"
                                data-ul-confirm-tone="danger"
                                data-ul-confirm-icon="bi-trash"
                            >
                                @csrf
                                @method('DELETE')
                                <button class="ti-btn ti-btn-sm ul-btn ul-btn-danger">
                                    <i class="bi bi-trash"></i>Remove
                                </button>
                            </form>
                        @endif
                        <button type="submit" form="account-photo-upload" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                            <i class="bi bi-upload"></i>Upload
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="account-password-modal" class="ul-modal" hidden>
            <div class="ul-modal-backdrop" data-ul-modal-close></div>
            <div class="ul-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="account-password-title">
                <div class="ul-modal-header">
                    <span class="ul-modal-icon ul-badge is-violet"><i class="bi bi-key" aria-hidden="true"></i></span>
                    <div class="ul-modal-copy">
                        <h6 id="account-password-title">Change password</h6>
                        <p>Generate a new password or type one manually. Minimum {{ $passwordMin }} characters{{ $passwordMixed ? ', with mixed case and a number' : '' }}.</p>
                    </div>
                    <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form
                    id="account-password-form"
                    method="POST"
                    action="{{ route('access.users.password', $user) }}"
                    class="ul-modal-body"
                    data-pw-min="{{ $passwordMin }}"
                    data-pw-mixed="{{ $passwordMixed ? '1' : '0' }}"
                >
                    @csrf
                    <input type="hidden" name="mode" id="account-pw-mode" value="generate">
                    <div class="td-pw-modes" role="tablist" aria-label="Password method">
                        <button type="button" class="is-active" data-pw-mode="generate">
                            <i class="bi bi-shuffle"></i>Generate
                        </button>
                        <button type="button" data-pw-mode="manual">
                            <i class="bi bi-keyboard"></i>Manual
                        </button>
                    </div>
                    <div class="ul-field">
                        <label class="ti-form-label" for="account-pw-input">Password</label>
                        <div class="td-pw-row">
                            <input
                                id="account-pw-input"
                                name="password"
                                class="ti-form-input"
                                type="text"
                                autocomplete="new-password"
                                readonly
                                value="{{ old('password') }}"
                            >
                            <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-more" data-pw-copy title="Copy password">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    <div class="ul-field" id="account-pw-confirm-wrap" hidden>
                        <label class="ti-form-label" for="account-pw-confirm">Confirm password</label>
                        <input
                            id="account-pw-confirm"
                            name="password_confirmation"
                            class="ti-form-input"
                            type="password"
                            autocomplete="new-password"
                        >
                    </div>
                    <div class="td-pw-actions">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-view" data-pw-regen>
                            <i class="bi bi-arrow-repeat"></i>Generate password
                        </button>
                    </div>
                    <p class="td-photo-hint" id="account-pw-hint">Share this password with the account owner after you save.</p>
                    @error('password')
                        <p class="td-photo-error">{{ $message }}</p>
                    @enderror
                    <div class="ul-modal-footer">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>
                            <i class="bi bi-x-lg"></i>Cancel
                        </button>
                        <button class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                            <i class="bi bi-check2"></i>Save password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="account-edit-modal" class="ul-modal" hidden>
            <div class="ul-modal-backdrop" data-ul-modal-close></div>
            <div class="ul-modal-dialog is-wide" role="dialog" aria-modal="true" aria-labelledby="account-edit-title">
                <div class="ul-modal-header">
                    <span class="ul-modal-icon ul-badge is-indigo"><i class="bi bi-pencil" aria-hidden="true"></i></span>
                    <div class="ul-modal-copy">
                        <h6 id="account-edit-title">Edit profile</h6>
                        <p>{{ $isCustomer ? 'Update this customer’s contact details and email verification.' : 'Update this staff account’s role, department, and availability.' }}</p>
                    </div>
                    <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('access.users.update-full', $user) }}" class="ul-modal-body">
                    @csrf @method('PUT')
                    <input type="hidden" name="user_type" value="{{ $isCustomer ? 'customer' : 'support' }}">
                    <div class="ul-form-grid" style="padding: 0 0 1rem;">
                        <div class="ul-field">
                            <label class="ti-form-label">Full name</label>
                            <input name="name" class="ti-form-input" value="{{ $user->name }}" required>
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Email</label>
                            <input name="email" type="email" class="ti-form-input" value="{{ $user->email }}" required>
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Username</label>
                            <input name="username" class="ti-form-input" value="{{ $user->username }}">
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Mobile</label>
                            <input name="contact_no" class="ti-form-input" value="{{ $user->contact_no }}">
                        </div>
                        @if($isSupport)
                            <div class="ul-field">
                                <label class="ti-form-label">Position</label>
                                <input name="position" class="ti-form-input" value="{{ $user->position }}">
                            </div>
                            <div class="ul-field">
                                <label class="ti-form-label">Employee ref</label>
                                <input name="employee_ref" class="ti-form-input" value="{{ $user->employee_ref }}">
                            </div>
                            <div class="ul-field">
                                <label class="ti-form-label">Role</label>
                                <select name="role_id" class="ti-form-select">
                                    @foreach($roles as $role)
                                        <option
                                            value="{{ $role->id }}"
                                            @selected($user->role_id === $role->id)
                                            data-tone="{{ \App\Support\TicketUi::roleTone($role->code) }}"
                                            data-icon="{{ \App\Support\TicketUi::roleIcon($role->code) }}"
                                        >{{ \App\Support\AccessUi::roleOptionLabel($role->code, $role->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ul-field">
                                <label class="ti-form-label">Department</label>
                                <select name="department_id" class="ti-form-select">
                                    <option value="">No department</option>
                                    @foreach($departments as $dept)
                                        <option
                                            value="{{ $dept->id }}"
                                            @selected($user->department_id === $dept->id)
                                            data-tone="{{ \App\Support\TicketUi::departmentTone($dept->code) }}"
                                            data-icon="{{ \App\Support\TicketUi::departmentIcon($dept->code) }}"
                                        >{{ \App\Support\AccessUi::departmentOptionLabel($dept->code, $dept->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ul-field">
                                <label class="ti-form-label">Availability</label>
                                <select name="availability_status" class="ti-form-select">
                                    @foreach(['available','busy','away','offline','on_leave'] as $status)
                                        <option
                                            value="{{ $status }}"
                                            @selected($user->availability_status === $status)
                                            data-tone="{{ \App\Support\AccessUi::availabilityTone($status) }}"
                                            data-icon="{{ \App\Support\AccessUi::availabilityIcon($status) }}"
                                        >{{ \App\Support\AccessUi::availabilityLabel($status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="ul-field">
                            <label class="ti-form-label">Email verification</label>
                            <select name="email_validated" class="ti-form-select">
                                <option value="1" @selected($user->email_verified_at) data-tone="lime" data-icon="bi-envelope-check">Verified</option>
                                <option value="0" @selected(! $user->email_verified_at) data-tone="amber" data-icon="bi-envelope">Unverified</option>
                            </select>
                        </div>
                    </div>
                    <div class="ul-modal-footer">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>
                            <i class="bi bi-x-lg"></i>Cancel
                        </button>
                        <button class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                            <i class="bi bi-check2"></i>Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @can('users.suspend')
        <div id="account-status-modal" class="ul-modal" hidden>
            <div class="ul-modal-backdrop" data-ul-modal-close></div>
            <div class="ul-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="account-status-title">
                <div class="ul-modal-header">
                    <span class="ul-modal-icon ul-badge is-amber"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
                    <div class="ul-modal-copy">
                        <h6 id="account-status-title">Update status</h6>
                        <p>Activate, suspend, or lock this {{ $accountNoun }}.</p>
                    </div>
                    <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('access.users.status', $user) }}" class="ul-modal-body">
                    @csrf
                    <label class="ti-form-label">Status</label>
                    <select name="account_status" class="ti-form-select mb-3">
                        @foreach(['active','inactive','pending_activation','suspended','locked'] as $status)
                            <option
                                value="{{ $status }}"
                                @selected($user->account_status === $status)
                                data-tone="{{ \App\Support\AccessUi::accountStatusTone($status) }}"
                                data-icon="{{ \App\Support\AccessUi::accountStatusIcon($status) }}"
                            >{{ \App\Support\AccessUi::accountStatusLabel($status) }}</option>
                        @endforeach
                    </select>
                    <div class="ul-modal-footer">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>
                            <i class="bi bi-x-lg"></i>Cancel
                        </button>
                        <button class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                            <i class="bi bi-check2"></i>Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @if($isCustomer)
        @canany(['users.edit', 'customers.edit'])
            <div id="account-link-modal" class="ul-modal" hidden>
                <div class="ul-modal-backdrop" data-ul-modal-close></div>
                <div class="ul-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="account-link-title">
                    <div class="ul-modal-header">
                        <span class="ul-modal-icon ul-badge is-teal"><i class="bi bi-plug" aria-hidden="true"></i></span>
                        <div class="ul-modal-copy">
                            <h6 id="account-link-title">Add account link</h6>
                            <p>Link an electric service account to this customer profile.</p>
                        </div>
                        <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('access.users.account-links.store', $user) }}" class="ul-modal-body" id="account-link-form" autocomplete="off">
                        @csrf
                        <div class="ul-field">
                            <label class="ti-form-label required" for="account-link-search">Search service account</label>
                            <div class="td-search-wrap ul-ast-form-search">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input
                                    type="text"
                                    id="account-link-search"
                                    class="ti-form-input"
                                    placeholder="Account number, customer name, or meter"
                                    autocomplete="off"
                                    spellcheck="false"
                                    role="combobox"
                                    aria-autocomplete="list"
                                    aria-expanded="false"
                                    aria-controls="account-link-dropdown"
                                    value="{{ old('account_number') }}"
                                >
                                <button type="button" id="account-link-clear" class="ul-ast-search-clear{{ old('account_number') ? '' : ' hidden' }}" title="Clear" aria-label="Clear account">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                <div id="account-link-dropdown" class="td-search-menu hidden" role="listbox"></div>
                            </div>
                            <input type="hidden" id="account-link-number" name="account_number" value="{{ old('account_number') }}" required>
                            <p id="account-link-hint" class="td-photo-hint" style="margin-top: 0.4rem; text-align: left;">
                                Search by account number, name, or meter. If nothing matches, you can still type it and add it by hand.
                            </p>
                            @error('account_number')
                                <p class="td-photo-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div id="account-link-pick" class="ul-ast-form-pick is-empty" style="margin: 0.75rem 0 0;" hidden>
                            <span class="ul-ast-mode-icon is-lime" aria-hidden="true"><i class="bi bi-lightning-charge"></i></span>
                            <div class="ul-ast-form-pick-copy">
                                <span id="account-link-pick-name" class="ul-pick-name">Select an account</span>
                                <span id="account-link-pick-meta" class="ul-pick-meta">Service account</span>
                                <span id="account-link-pick-acct" class="ul-pick-acct">No account selected</span>
                            </div>
                            <span class="ul-ast-form-pick-amt">
                                <span class="ul-ast-form-pick-amt-label" id="account-link-pick-source">Source</span>
                                <span id="account-link-pick-status">—</span>
                            </span>
                        </div>

                        <div class="ul-field" style="margin-top: 0.75rem;">
                            <label class="ti-form-label" for="account-link-owner" id="account-link-owner-label">Owner name</label>
                            <input
                                id="account-link-owner"
                                name="owner_name"
                                class="ti-form-input"
                                value="{{ old('owner_name', $user->name) }}"
                                autocomplete="off"
                            >
                            <p id="account-link-owner-hint" class="td-photo-hint" style="margin-top: 0.4rem; text-align: left;">Filled automatically when a billing record is selected. Required if you add the account by hand.</p>
                            @error('owner_name')
                                <p class="td-photo-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="ul-field" style="margin-top: 0.9rem;">
                            <x-unified.check name="validate_now" :checked="old('validate_now', true)">
                                Validate now and link this service account
                            </x-unified.check>
                        </div>
                        <div class="ul-modal-footer">
                            <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>
                                <i class="bi bi-x-lg"></i>Cancel
                            </button>
                            <button class="ti-btn ti-btn-sm ul-btn ul-btn-primary">
                                <i class="bi bi-plus-lg"></i>Add account
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            @if($errors->has('account_number') || $errors->has('owner_name'))
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        if (window.ulOpenModal) {
                            window.ulOpenModal('#account-link-modal');
                        }
                    });
                </script>
            @endif

            <script>
            (function () {
                const searchUrl = @json(route('access.users.accounts.search'));
                const defaultOwner = @json($user->name);
                const searchInput = document.getElementById('account-link-search');
                const accountField = document.getElementById('account-link-number');
                const ownerField = document.getElementById('account-link-owner');
                const ownerLabel = document.getElementById('account-link-owner-label');
                const ownerHint = document.getElementById('account-link-owner-hint');
                const hint = document.getElementById('account-link-hint');
                const dropdown = document.getElementById('account-link-dropdown');
                const clearBtn = document.getElementById('account-link-clear');
                const pick = document.getElementById('account-link-pick');
                if (!searchInput || !accountField || !dropdown) {
                    return;
                }

                let debounceTimer;
                let results = [];
                let activeIndex = -1;
                let lastQuery = '';
                let fromCis = false;

                function escapeHtml(value) {
                    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;',
                    }[char]));
                }

                function setOpen(open) {
                    dropdown.classList.toggle('hidden', !open);
                    searchInput.setAttribute('aria-expanded', open ? 'true' : 'false');
                }

                function setClearVisible(show) {
                    clearBtn?.classList.toggle('hidden', !show);
                }

                function setManual(accountNo) {
                    fromCis = false;
                    accountField.value = accountNo;
                    ownerLabel?.classList.add('required');
                    if (ownerHint) {
                        ownerHint.textContent = 'This account is not in the billing records. Enter the owner name to add it by hand.';
                    }
                    if (hint) {
                        hint.textContent = accountNo
                            ? 'No billing record found. This will be added as a typed account.'
                            : 'Search by account number, name, or meter. If nothing matches, you can still type it and add it by hand.';
                    }
                    if (pick) {
                        pick.hidden = !accountNo;
                        pick.classList.toggle('is-empty', true);
                        document.getElementById('account-link-pick-name').textContent = 'Typed account';
                        document.getElementById('account-link-pick-meta').textContent = 'Not found in billing records';
                        document.getElementById('account-link-pick-acct').textContent = accountNo || 'No account selected';
                        document.getElementById('account-link-pick-source').textContent = 'Source';
                        document.getElementById('account-link-pick-status').textContent = 'Manual';
                    }
                }

                function setCis(row) {
                    fromCis = true;
                    accountField.value = row.account_no || '';
                    searchInput.value = row.account_no || '';
                    ownerField.value = row.customer || defaultOwner;
                    ownerLabel?.classList.remove('required');
                    if (ownerHint) {
                        ownerHint.textContent = 'Filled from the billing record. You can still edit the owner name before saving.';
                    }
                    if (hint) {
                        hint.textContent = row.linked
                            ? 'This service account is already linked to a member. Saving will be blocked if it belongs to someone else.'
                            : 'Found in billing records. Validate now to link it to this customer.';
                    }
                    setClearVisible(true);
                    pick.hidden = false;
                    pick.classList.remove('is-empty');
                    document.getElementById('account-link-pick-name').textContent = row.customer || 'Service account';
                    document.getElementById('account-link-pick-meta').textContent = [row.meter_no ? 'Meter '+row.meter_no : null, row.rate_class || null, row.address || null].filter(Boolean).join(' · ') || 'Billing record';
                    document.getElementById('account-link-pick-acct').textContent = row.account_no || '';
                    document.getElementById('account-link-pick-source').textContent = 'Billing';
                    document.getElementById('account-link-pick-status').textContent = row.status || (row.linked ? 'Linked' : 'Available');
                }

                function highlight() {
                    dropdown.querySelectorAll('[data-account], [data-manual]').forEach((item, index) => {
                        item.classList.toggle('is-active', index === activeIndex);
                        if (index === activeIndex) {
                            item.scrollIntoView({ block: 'nearest' });
                        }
                    });
                }

                function pickActive() {
                    const items = dropdown.querySelectorAll('[data-account], [data-manual]');
                    const item = items[Math.max(0, activeIndex)] || items[0];
                    if (!item) {
                        return;
                    }
                    if (item.hasAttribute('data-manual')) {
                        setManual(item.dataset.manual || searchInput.value.trim());
                    } else {
                        setCis({
                            account_no: item.dataset.account,
                            customer: item.dataset.customer,
                            meter_no: item.dataset.meter,
                            address: item.dataset.address,
                            rate_class: item.dataset.rate,
                            status: item.dataset.status,
                            linked: item.dataset.linked === '1',
                        });
                    }
                    setOpen(false);
                    ownerField?.focus();
                }

                function render(rows, query) {
                    results = rows || [];
                    activeIndex = 0;
                    const manual = `
                        <button type="button" class="td-search-item" role="option" data-manual="${escapeHtml(query)}">
                            <span class="ul-ast-mode-icon is-slate" aria-hidden="true"><i class="bi bi-pencil"></i></span>
                            <span class="td-search-item-copy">
                                <span class="td-search-item-name">Add “${escapeHtml(query)}” by hand</span>
                                <span class="td-search-item-meta">Use this if it is not in the billing records. Owner name is required.</span>
                            </span>
                        </button>
                    `;
                    if (!results.length) {
                        dropdown.innerHTML = `<p class="td-search-empty">No billing record matches “${escapeHtml(query)}”.</p>${manual}`;
                        setOpen(true);
                        return;
                    }
                    dropdown.innerHTML = results.map((row, index) => `
                        <button type="button" class="td-search-item${index === 0 ? ' is-active' : ''}" role="option"
                            data-account="${escapeHtml(row.account_no)}"
                            data-customer="${escapeHtml(row.customer)}"
                            data-meter="${escapeHtml(row.meter_no)}"
                            data-address="${escapeHtml(row.address)}"
                            data-rate="${escapeHtml(row.rate_class)}"
                            data-status="${escapeHtml(row.status)}"
                            data-linked="${row.linked ? '1' : '0'}">
                            <span class="td-search-item-copy">
                                <span class="td-search-item-name">${escapeHtml(row.customer || 'Unnamed customer')}</span>
                                <span class="td-search-item-meta">${escapeHtml([row.meter_no ? 'Meter '+row.meter_no : null, row.address || null].filter(Boolean).join(' · ') || 'Service account')}</span>
                            </span>
                            <span class="td-search-item-side">
                                <span class="td-search-item-acct">${escapeHtml(row.account_no)}</span>
                                <span class="td-search-item-bal">${escapeHtml(row.status || (row.linked ? 'Linked' : 'Available'))}</span>
                            </span>
                        </button>
                    `).join('') + manual;
                    setOpen(true);
                }

                async function runSearch(query) {
                    const q = query.trim();
                    const minLength = /^\d/.test(q) ? 2 : 3;
                    if (q.length < minLength) {
                        setOpen(false);
                        return;
                    }
                    if (q === lastQuery && dropdown.innerHTML) {
                        setOpen(true);
                        return;
                    }
                    lastQuery = q;
                    dropdown.innerHTML = '<p class="td-search-empty">Searching billing records…</p>';
                    setOpen(true);
                    try {
                        const res = await fetch(`${searchUrl}?q=${encodeURIComponent(q)}`, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        });
                        const json = await res.json();
                        if (searchInput.value.trim() !== q) {
                            return;
                        }
                        render(json.data || [], q);
                    } catch (err) {
                        dropdown.innerHTML = '<p class="td-search-empty">Could not search billing records right now. Type the account and add it by hand.</p>';
                        setOpen(true);
                    }
                }

                searchInput.addEventListener('input', function () {
                    const q = this.value.trim();
                    accountField.value = q;
                    fromCis = false;
                    setClearVisible(q.length > 0);
                    if (!q) {
                        setManual('');
                        pick.hidden = true;
                    }
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => runSearch(q), 350);
                });

                searchInput.addEventListener('focus', function () {
                    if (this.value.trim().length >= 2 && !fromCis) {
                        runSearch(this.value);
                    }
                });

                searchInput.addEventListener('keydown', function (event) {
                    const items = dropdown.querySelectorAll('[data-account], [data-manual]');
                    const open = !dropdown.classList.contains('hidden');
                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        if (!open) {
                            runSearch(this.value);
                            return;
                        }
                        activeIndex = items.length ? (activeIndex + 1) % items.length : -1;
                        highlight();
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        activeIndex = items.length ? (activeIndex <= 0 ? items.length - 1 : activeIndex - 1) : -1;
                        highlight();
                    } else if (event.key === 'Enter' && open && items.length) {
                        event.preventDefault();
                        pickActive();
                    } else if (event.key === 'Escape') {
                        setOpen(false);
                    }
                });

                dropdown.addEventListener('click', function (event) {
                    const item = event.target.closest('[data-account], [data-manual]');
                    if (!item) {
                        return;
                    }
                    if (item.hasAttribute('data-manual')) {
                        setManual(item.dataset.manual || searchInput.value.trim());
                        setOpen(false);
                        ownerField?.focus();
                        return;
                    }
                    activeIndex = Array.from(dropdown.querySelectorAll('[data-account], [data-manual]')).indexOf(item);
                    pickActive();
                });

                clearBtn?.addEventListener('click', function () {
                    searchInput.value = '';
                    accountField.value = '';
                    ownerField.value = defaultOwner;
                    lastQuery = '';
                    results = [];
                    setManual('');
                    pick.hidden = true;
                    setClearVisible(false);
                    setOpen(false);
                    searchInput.focus();
                });

                document.addEventListener('click', function (event) {
                    if (!dropdown.contains(event.target) && event.target !== searchInput && event.target !== clearBtn) {
                        setOpen(false);
                    }
                });

                document.getElementById('account-link-form')?.addEventListener('submit', function (event) {
                    if (!accountField.value.trim()) {
                        accountField.value = searchInput.value.trim();
                    }
                    if (!fromCis && !ownerField.value.trim()) {
                        event.preventDefault();
                        ownerField.focus();
                        if (ownerHint) {
                            ownerHint.textContent = 'Owner name is required for a manual account.';
                        }
                    }
                });

                if (accountField.value) {
                    setManual(accountField.value);
                }
            })();
            </script>
        @endcanany
    @endif

    @can('users.edit')
        <script>
            (function () {
                const input = document.getElementById('account-photo-input');
                const preview = document.getElementById('account-photo-preview');
                const picker = document.querySelector('[data-ul-photo-pick]');
                if (!input || !preview) return;

                if (picker) {
                    picker.addEventListener('click', function () {
                        input.click();
                    });
                }

                input.addEventListener('change', function () {
                    const file = this.files && this.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = function (event) {
                        preview.onerror = function () {
                            preview.onerror = null;
                            preview.src = @json($defaultAvatarUrl);
                        };
                        preview.src = event.target.result;
                        preview.hidden = false;
                    };
                    reader.readAsDataURL(file);
                });

                @if($errors->has('photo'))
                    if (window.ulOpenModal) {
                        window.ulOpenModal('#account-photo-modal');
                    }
                @endif
            })();

            (function () {
                const form = document.getElementById('account-password-form');
                if (!form) return;

                const modeInput = document.getElementById('account-pw-mode');
                const passwordInput = document.getElementById('account-pw-input');
                const confirmInput = document.getElementById('account-pw-confirm');
                const confirmWrap = document.getElementById('account-pw-confirm-wrap');
                const regen = form.querySelector('[data-pw-regen]');
                const copy = form.querySelector('[data-pw-copy]');
                const hint = document.getElementById('account-pw-hint');
                const min = Math.max(parseInt(form.getAttribute('data-pw-min') || '8', 10), 8);
                const mixed = form.getAttribute('data-pw-mixed') === '1';

                function pick(chars) {
                    return chars.charAt(Math.floor(Math.random() * chars.length));
                }

                function shuffle(value) {
                    return value.split('').sort(function () { return Math.random() - 0.5; }).join('');
                }

                function generatePassword() {
                    const length = Math.max(min, 12);
                    const lower = 'abcdefghijkmnopqrstuvwxyz';
                    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
                    const nums = '23456789';
                    const symbols = '!@#$%*?';
                    let next = mixed ? pick(lower) + pick(upper) + pick(nums) : '';
                    const pool = mixed ? lower + upper + nums + symbols : lower + nums;
                    while (next.length < length) {
                        next += pick(pool);
                    }
                    return shuffle(next);
                }

                function setMode(mode, resetValues) {
                    const next = mode === 'manual' ? 'manual' : 'generate';
                    modeInput.value = next;
                    form.querySelectorAll('[data-pw-mode]').forEach(function (button) {
                        button.classList.toggle('is-active', button.getAttribute('data-pw-mode') === next);
                    });

                    if (next === 'generate') {
                        passwordInput.type = 'text';
                        passwordInput.readOnly = true;
                        confirmWrap.hidden = true;
                        if (regen) regen.hidden = false;
                        hint.textContent = 'Share this password with the account owner after you save.';
                        if (resetValues || !passwordInput.value) {
                            passwordInput.value = generatePassword();
                        }
                        confirmInput.value = passwordInput.value;
                    } else {
                        passwordInput.type = 'password';
                        passwordInput.readOnly = false;
                        confirmWrap.hidden = false;
                        if (regen) regen.hidden = true;
                        hint.textContent = 'Type the new password twice to confirm.';
                        if (resetValues) {
                            passwordInput.value = '';
                            confirmInput.value = '';
                        }
                    }
                }

                form.querySelectorAll('[data-pw-mode]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        setMode(button.getAttribute('data-pw-mode'), true);
                    });
                });

                if (regen) {
                    regen.addEventListener('click', function () {
                        passwordInput.value = generatePassword();
                        confirmInput.value = passwordInput.value;
                    });
                }

                if (copy) {
                    copy.addEventListener('click', async function () {
                        if (!passwordInput.value) return;
                        try {
                            await navigator.clipboard.writeText(passwordInput.value);
                            copy.innerHTML = '<i class="bi bi-check2"></i>';
                            setTimeout(function () {
                                copy.innerHTML = '<i class="bi bi-clipboard"></i>';
                            }, 1200);
                        } catch (error) {
                            passwordInput.select();
                        }
                    });
                }

                passwordInput.addEventListener('input', function () {
                    if (modeInput.value === 'generate') {
                        confirmInput.value = passwordInput.value;
                    }
                });

                form.addEventListener('submit', function () {
                    if (modeInput.value === 'generate') {
                        confirmInput.value = passwordInput.value;
                    }
                });

                setMode(@json(old('mode', 'generate')));

                @if($errors->has('password'))
                    if (window.ulOpenModal) {
                        window.ulOpenModal('#account-password-modal');
                    }
                @endif
            })();
        </script>
    @endcan
</x-app-layout>
