@props([
    'viewUrl' => null,
    'viewLabel' => 'View',
])

<td class="ul-no-row-click ul-col-actions text-end" {{ $attributes }}>
    <div class="ul-actions ul-no-row-click" x-data="{ open: false }" @keydown.escape.window="open = false">
        @if($viewUrl)
            <a href="{{ $viewUrl }}" class="ti-btn ti-btn-sm ul-btn-view" title="{{ $viewLabel }}" aria-label="{{ $viewLabel }}">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </a>
        @endif

        @if(isset($menu) && trim((string) $menu) !== '')
            <div class="ul-menu-wrap">
                <button
                    type="button"
                    class="ti-btn ti-btn-sm ul-btn-more"
                    @click.stop="open = !open"
                    :aria-expanded="open.toString()"
                    aria-haspopup="menu"
                    title="More actions"
                    aria-label="More actions"
                >
                    <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                </button>
                <div
                    class="ul-menu"
                    x-show="open"
                    x-cloak
                    x-transition
                    @click.outside="open = false"
                    @click="open = false"
                    role="menu"
                >
                    {{ $menu }}
                </div>
            </div>
        @endif

        {{ $slot }}
    </div>
</td>
