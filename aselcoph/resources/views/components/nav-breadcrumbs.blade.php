@if (isset($title))
    <div class="page-crumbs-bar">
        <div class="container-fluid">
            <div class="page-crumbs-bar__inner">
                <nav class="page-crumbs" aria-label="Breadcrumb">
                    <ol>
                        <li>
                            <a class="page-crumbs__home" href="{{ url('/u/dashboard') }}" aria-label="Home">
                                <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M2 13.5V7h1v6.5a.5.5 0 0 0 .5.5h9a.5.5 0 0 0 .5-.5V7h1v6.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5zm11-11V6l-2-2V2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5z" />
                                    <path fill-rule="evenodd"
                                        d="M7.293 1.5a1 1 0 0 1 1.414 0l6.647 6.646a.5.5 0 0 1-.708.708L8 2.207 1.354 8.854a.5.5 0 1 1-.708-.708L7.293 1.5z" />
                                </svg>
                            </a>
                        </li>
                        @for ($i = 0; $i < 10; $i++)
                            @php
                                $slotVar = 'url_' . $i;
                                $slotValue = isset($$slotVar) ? json_decode($$slotVar, true) : null;
                            @endphp

                            @if (!empty($slotValue) && is_array($slotValue))
                                <li>
                                    <a href="{{ $slotValue['link'] ?? 'javascript:void(0);' }}">
                                        {{ $slotValue['text'] ?? '' }}
                                    </a>
                                </li>
                            @endif
                        @endfor

                        @if (!empty($active))
                            <li>
                                <span aria-current="page">{{ $active }}</span>
                            </li>
                        @endif
                    </ol>
                </nav>
                <time class="page-crumbs-clock" id="page-live-clock" data-timezone="Asia/Manila" datetime="{{ now('Asia/Manila')->toIso8601String() }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span data-clock-date>{{ now('Asia/Manila')->format('D, M j, Y') }}</span>
                    <span data-clock-time>{{ now('Asia/Manila')->format('g:i:s A') }}</span>
                </time>
            </div>
        </div>
    </div>

    <div class="page-header-wrap{{ ! empty($hidePageHeader) ? ' d-none' : '' }}" @if (! empty($hidePageHeader)) hidden @endif>
        <div class="container-fluid">
            <header class="page-header-card">
                <div class="page-header-card__inner">
                    <div class="page-header-card__identity">
                        @if (!empty(trim($headerAvatar ?? '')))
                            <div class="page-header-card__avatar">
                                {{ $headerAvatar }}
                            </div>
                        @endif
                        <div class="page-header-card__copy">
                            <h1>{{ $title }}</h1>
                            <p class="page-header-card__sub">{{ \App\Support\PageHeader::subtitle($subtitle ?? null, $title ?? null, $active ?? null) }}</p>
                        </div>
                    </div>
                    @if (!empty(trim($buttons ?? '')))
                        <div class="page-header-card__actions">
                            {{ $buttons }}
                        </div>
                    @endif
                </div>
            </header>
        </div>
    </div>
@endif
