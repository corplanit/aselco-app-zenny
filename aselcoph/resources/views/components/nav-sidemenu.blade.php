<aside class="app-sidebar" id="sidebar">

    <div class="main-sidebar-header">
        <a href="/u/dashboard" class="header-logo">
            <img src="/assets/logo_favicon.png" alt="ASELCO" class="sidebar-brand-mark">
            <span class="sidebar-brand-copy">
                <strong>ASELCO Inc.</strong>
                <span class="sidebar-brand-tagline">Serbisyong Mapahiyumon</span>
            </span>
        </a>
    </div>

    <div class="main-sidebar" id="sidebar-scroll">

        <nav class="main-menu-container nav nav-pills flex-col sub-open">
            <div class="slide-left" id="slide-left">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24"
                    viewBox="0 0 24 24">
                    <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z"></path>
                </svg>
            </div>
            <ul class="main-menu">
                <li class="slide__category"><span class="category-name">Dashboard</span></li>
                <li class="slide">
                    <a href="/u/dashboard" class="side-menu__item">
                        <i class="w-6 h-4 side-menu__icon bi bi-layers"></i>
                        <span class="side-menu__label">Dashboard</span>
                    </a>
                </li>
                @php
                    $access = app(\App\Services\Access\AccessService::class);
                    $authUser = Auth::user();
                    $roleCode = $authUser->accessRole?->code;
                    $legacyRole = (string) ($authUser->role ?? '');
                @endphp
                @if ($authUser->isTicketAdmin())
                    @include('components.menu.admin')
                @elseif ($legacyRole === 'Content Manager' || $roleCode === 'content_manager')
                    @include('components.menu.cms')
                @elseif ($access->isSupport($authUser) || $legacyRole === 'support')
                    @include('components.menu.agent')
                @elseif ($authUser->canManageTickets())
                    <li class="slide__category"><span class="category-name">Manage Services</span></li>
                    @include('components.menu._support-chat')
                    @include('components.menu.tickets')
                @else
                    <li class="slide__category"><span class="category-name">Manage Account</span></li>
                    <li class="slide">
                        <a href="/customer/registration" class="side-menu__item">
                            <i class="w-6 h-4 side-menu__icon bi bi-link-45deg"></i>
                            <span class="side-menu__label">Link My Account</span>
                        </a>
                    </li>
                    <li class="slide__category"><span class="category-name">Customer Services</span></li>
                    @include('components.menu._support-chat')
                    <li class="slide">
                        <a href="#" data-hs-overlay="#complaint" class="side-menu__item">
                            <i class="w-6 h-4 side-menu__icon bi bi-hand-index"></i>
                            <span class="side-menu__label">Customer Complaint</span>
                        </a>
                    </li>
                    <li class="slide">
                        <a href="{{ optional(\App\Models\Survery::find(1))->link }}" target="_blank" class="side-menu__item">
                            <i class="w-6 h-4 side-menu__icon bi bi-graph-up-arrow"></i>
                            <span class="side-menu__label">Satisfaction Survey</span>
                        </a>
                    </li>
                @endif
                <li class="slide__category"><span class="category-name">Account Settings</span></li>
                <li class="slide">
                    <a href="/user/profile" class="side-menu__item">
                        <i class="w-6 h-4 side-menu__icon bi bi-person-gear"></i>
                        <span class="side-menu__label">Profile Settings</span>
                    </a>
                </li>
                <li class="slide">
                    <a href="{{ route('logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                        class="side-menu__item">
                        <i class="w-6 h-4 side-menu__icon bi bi-power"></i>
                        <span class="side-menu__label">Sign Out</span>
                    </a>
                </li>
            </ul>
            <div class="slide-right" id="slide-right"><svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191"
                    width="24" height="24" viewBox="0 0 24 24">
                    <path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z"></path>
                </svg></div>
        </nav>
    </div>

    <div class="sidebar-dock">
        <div class="sidebar-theme" role="group" aria-label="Color theme">
            <button type="button" data-theme-set="light" aria-pressed="true">
                <i class="bi bi-sun"></i>
                Light
            </button>
            <button type="button" data-theme-set="dark" aria-pressed="false">
                <i class="bi bi-moon"></i>
                Dark
            </button>
        </div>
    </div>
</aside>

