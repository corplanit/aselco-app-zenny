<li class="slide">
    <a href="/calendar" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-calendar-event" ></i>
        <span class="side-menu__label">Calendar Activities</span>
    </a>
</li>
{{-- Customer Relationship is covered by User Management (Customers + account links).
<li class="slide__category"><span class="category-name">Customer Relationsip</span></li>
<li class="slide">
    <a href="{{ route('consumer.list') }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-people" ></i>
        <span class="side-menu__label">List of Consumers</span>
    </a>
</li>
<li class="slide">
    <a href="/validation" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-people" ></i>
        <span class="side-menu__label">
            Account Request
            @php
                $count = App\Models\AccountLink::whereNull('validated_by')->count();
            @endphp
            @if ($count)
                <span class="side-menu__badge badge !rounded-full bg-danger">{{ $count }}</span>
            @endif
        </span>
    </a>
</li>
--}}
{{-- <li class="slide__category"><span class="category-name">Content Management</span></li>
<li class="slide">
    <a href="/ublog" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-newspaper" ></i>
        <span class="side-menu__label">List of Articles</span>
    </a>
</li>
<li class="slide">
    <a href="/ublog/new" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-pencil-square" ></i>
        <span class="side-menu__label">Create New Article</span>
    </a>
</li> --}}
<li class="slide__category"><span class="category-name">Manage Services</span></li>

<li class="slide">
    <a href="/announcements" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-megaphone" ></i>
        <span class="side-menu__label">Mobile Announcements</span>
    </a>
</li>

@include('components.menu._support-chat')

@include('components.menu.tickets')
<li class="slide">
    <a href="/complaint" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-hand-index" ></i>
        <span class="side-menu__label">Legacy Complaints</span>
    </a>
</li>
<li class="slide__category"><span class="category-name">AST Wallet</span></li>
<li class="slide">
    <a href="{{ route('ast.admin.dashboard') }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-wallet2" ></i>
        <span class="side-menu__label">Wallet Dashboard</span>
    </a>
</li>
<li class="slide">
    <a href="{{ route('ast.admin.request') }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-send" ></i>
        <span class="side-menu__label">Request AST</span>
    </a>
</li>
@can('wallet.load')
<li class="slide">
    <a href="{{ route('ast.admin.load') }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-plus-circle" ></i>
        <span class="side-menu__label">Load AST</span>
    </a>
</li>
<li class="slide">
    <a href="{{ route('ast.admin.load', ['mode' => 'reduce']) }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-sliders" ></i>
        <span class="side-menu__label">Adjust AST</span>
    </a>
</li>
@endcan
<li class="slide">
    <a href="{{ route('ast.admin.load-requests') }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-clock-history" ></i>
        <span class="side-menu__label">
            Load Requests
            @php
                $_pendingLoads = \App\Models\WalletLoadRequest::where('status', 'pending')->count();
            @endphp
            @if($_pendingLoads > 0)
                <span class="side-menu__badge badge !rounded-full bg-warning text-dark">{{ $_pendingLoads }}</span>
            @endif
        </span>
    </a>
</li>
<li class="slide">
    <a href="{{ route('ast.cis.queue') }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-arrow-left-right" ></i>
        <span class="side-menu__label">AST CIS Queue</span>
    </a>
</li>
<li class="slide">
    <a href="#" onclick="openUpdateSwal()" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-graph-up-arrow" ></i>
        <span class="side-menu__label">Satisfaction Survey</span>
    </a>
</li>

@can('tickets.view')
<li class="slide__category"><span class="category-name">Support Workspace</span></li>
<li class="slide"><a href="{{ route('workspace.sla') }}" class="side-menu__item"><i class="w-6 h-4 side-menu__icon bi bi-clock-history"></i><span class="side-menu__label">SLA Monitoring @include('components.menu._sla-count')</span></a></li>
<li class="slide"><a href="{{ route('workspace.notifications') }}" class="side-menu__item"><i class="w-6 h-4 side-menu__icon bi bi-bell"></i><span class="side-menu__label">Notifications</span></a></li>
@endcan
@can('users.view')
@php
    $accessOpen = request()->routeIs('access.*');
    $accessGroups = [
        [
            'label' => 'Accounts',
            'items' => [
                ['route' => 'access.users.index', 'match' => 'access.users.*', 'label' => 'Users', 'icon' => 'bi-people', 'can' => 'users.view'],
                ['route' => 'access.customers.index', 'match' => 'access.customers.*', 'label' => 'Customers', 'icon' => 'bi-person', 'can' => 'customers.view'],
                ['route' => 'access.support.index', 'match' => 'access.support.*', 'label' => 'Support', 'icon' => 'bi-headset', 'can' => 'users.view'],
            ],
        ],
        [
            'label' => 'Organization',
            'items' => [
                ['route' => 'access.departments.index', 'match' => 'access.departments.*', 'label' => 'Departments', 'icon' => 'bi-diagram-3', 'can' => 'departments.view'],
                ['route' => 'access.roles.index', 'match' => 'access.roles.*', 'label' => 'Roles', 'icon' => 'bi-shield', 'can' => 'roles.view'],
                ['route' => 'access.permissions.index', 'match' => 'access.permissions.*', 'label' => 'Permissions', 'icon' => 'bi-key', 'can' => 'permissions.view'],
            ],
        ],
        [
            'label' => 'Operations',
            'items' => [
                ['route' => 'access.availability.index', 'match' => 'access.availability.*', 'label' => 'Availability', 'icon' => 'bi-person-check', 'can' => 'users.view'],
                ['route' => 'access.assignments.index', 'match' => 'access.assignments.*', 'label' => 'Assignments', 'icon' => 'bi-ticket-detailed', 'can' => 'tickets.view'],
                ['route' => 'access.reports.index', 'match' => 'access.reports.*', 'label' => 'Reports', 'icon' => 'bi-bar-chart', 'can' => 'reports.view'],
            ],
        ],
        [
            'label' => 'Security',
            'items' => [
                ['route' => 'access.sessions.index', 'match' => 'access.sessions.*', 'label' => 'Sessions', 'icon' => 'bi-laptop', 'can' => 'sessions.view'],
                ['route' => 'access.activity.index', 'match' => 'access.activity.*', 'label' => 'Activity', 'icon' => 'bi-clock-history', 'can' => 'audit.view'],
                ['route' => 'access.settings.index', 'match' => 'access.settings.*', 'label' => 'Settings', 'icon' => 'bi-gear', 'can' => 'settings.view'],
            ],
        ],
    ];
@endphp
<li class="slide__category"><span class="category-name">User Management</span></li>
<li class="slide has-sub {{ $accessOpen ? 'open' : '' }}">
    <a href="javascript:void(0);" class="side-menu__item {{ $accessOpen ? 'active-parent-menu' : '' }}">
        <i class="ri-arrow-down-s-line side-menu__angle"></i>
        <i class="w-6 h-4 side-menu__icon bi bi-person-gear"></i>
        <span class="side-menu__label">User Management</span>
    </a>
    <ul class="slide-menu child1 ul-side-sub">
        @foreach($accessGroups as $group)
            @php
                $visible = collect($group['items'])->first(fn ($item) => Auth::user()?->can($item['can']));
            @endphp
            @if($visible)
                <li class="ul-side-sub-label">
                    <span class="ul-side-sub-label-text">{{ $group['label'] }}</span>
                </li>
                @foreach($group['items'] as $item)
                    @can($item['can'])
                        <li class="slide">
                            <a href="{{ route($item['route']) }}" class="side-menu__item {{ request()->routeIs($item['match']) ? 'active' : '' }}">
                                <i class="side-menu__icon bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                <span class="side-menu__label">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endcan
                @endforeach
            @endif
        @endforeach
    </ul>
</li>
@endcan

<script>
function openUpdateSwal() {

    // ✅ Safe blade-to-JS (handles quotes, etc.)
    const currentLink = @json(optional(\App\Models\Survery::find(1))->link);

    Swal.fire({
        title: 'Update Survey Link',
        html: `
            <div class="text-start">
                <label class="text-sm font-semibold mb-1">Survey Link</label>

                <div style="display:flex; gap:8px; align-items:center;">
                    <input id="swal-link-input"
                        class="swal2-input"
                        value="${currentLink || ''}"
                        style="flex:1; margin:0; height:42px;">

                    <button type="button" id="copy-btn"
                        class="swal2-styled"
                        style="background:#6c757d; padding:6px 10px; height:42px;">
                        📋
                    </button>
                </div>

                <small style="display:block; margin-top:8px; opacity:.7;">
                    Tip: Click 📋 to copy the link.
                </small>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Update',
        cancelButtonText: 'Cancel',

        didOpen: () => {
            const copyBtn = document.getElementById('copy-btn');
            copyBtn.addEventListener('click', async () => {
                const input = document.getElementById('swal-link-input');
                const text = input.value || '';

                try {
                    await navigator.clipboard.writeText(text);
                    Swal.showValidationMessage('Copied to clipboard ✔');
                } catch (e) {
                    // fallback
                    input.select();
                    document.execCommand('copy');
                    Swal.showValidationMessage('Copied to clipboard ✔');
                }

                setTimeout(() => Swal.resetValidationMessage(), 1000);
            });
        },

        preConfirm: () => {
            const value = document.getElementById('swal-link-input').value.trim();
            if (!value) {
                Swal.showValidationMessage('Survey link is required');
                return false;
            }
            return value;
        }

    }).then((result) => {
        if (!result.isConfirmed) return;

        $.ajax({
            url: '/survey/update-link',
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: {
                id: 1,
                link: result.value
            },
            success: function(res) {
                Swal.fire({
                    icon: 'success',
                    title: 'Updated!',
                    text: 'Survey link updated successfully',
                    timer: 1400,
                    showConfirmButton: false
                });

                // ✅ simplest: refresh page or update a label if you have one
                setTimeout(() => window.location.reload(), 900);
            },
            error: function(xhr) {
                let msg = 'Failed to update survey link';
                if (xhr.responseJSON?.message) msg = xhr.responseJSON.message;
                Swal.fire('Error', msg, 'error');
            }
        });
    });
}
</script>



{{-- <li class="slide__category"><span class="category-name">Administrator</span></li>
<li class="slide has-sub" id="profit-tracker-menu">
    <a href="javascript:void(0);" class="side-menu__item">
        <i class="ri-arrow-down-s-line side-menu__angle"></i>
        <i class="w-6 h-4 side-menu__icon bi bi-globe-americas" ></i>
        <span class="side-menu__label">Landing Page</span>
    </a>
    <ul class="slide-menu child1" style="padding-left: 10px">
        <li class="slide side-menu__label1">
            <a href="javascript:void(0)">Landing Page</a>
        </li>
        <li class="slide" id="income-tracking-menu">
            <a href="/ublog" class="side-menu__item">Manage Post</a>
        </li>
        <li class="slide" id="profit-tracker-menu-1">
            <a href="/pages" class="side-menu__item">Manage Pages</a>
        </li>
        <li class="slide" id="expense-tracking-menu">
            <a href="/file-manager/list" class="side-menu__item">Manage Resources</a>
        </li>
        <li class="slide" id="expense-tracking-menu">
            <a href="/menus" class="side-menu__item">Manage Menu</a>
        </li>
    </ul>
</li> --}}
