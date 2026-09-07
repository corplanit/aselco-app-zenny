<li class="slide">
    <a href="{{ route('chats.support') }}" class="side-menu__item relative" data-unread-badge-anchor="support-messages">
        <i class="w-6 h-4 side-menu__icon bi bi-headset"></i>
        <span class="side-menu__label">Customer Support</span>

        @php
            $uid = Auth::id();
            $unread = 0;
            try {
                $unread = (int) \App\Models\Chats\ConversationParticipant::query()
                    ->where('user_id', $uid)
                    ->whereNull('left_at')
                    ->whereHas('conversation', function ($query) {
                        $query->support()->open();
                    })
                    ->sum('unread_count');
            } catch (\Throwable) {
                $unread = 0;
            }
        @endphp

        <span id="count_unread_msg"
              class="translate-middle badge !rounded-full bg-danger absolute top-0 end-0"
              style="{{ $unread > 0 ? '' : 'display:none' }}">
            {{ $unread > 9 ? '9+' : $unread }}
        </span>
    </a>
</li>
