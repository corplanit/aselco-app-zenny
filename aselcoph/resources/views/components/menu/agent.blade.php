<li class="slide__category"><span class="category-name">Manage Services</span></li>

@include('components.menu._support-chat')


@include('components.menu.tickets')
@can('customers.view')
<li class="slide__category"><span class="category-name">Members</span></li>
<li class="slide">
    <a href="{{ route('access.customers.index') }}" class="side-menu__item {{ request()->routeIs('access.customers.*') ? 'active' : '' }}">
        <i class="w-6 h-4 side-menu__icon bi bi-person" ></i>
        <span class="side-menu__label">Customers</span>
    </a>
</li>
@endcan
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
