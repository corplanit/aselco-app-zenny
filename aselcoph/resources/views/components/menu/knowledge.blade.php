@can('knowledge.view')
<li class="slide__category"><span class="category-name">AI Knowledge Base</span></li>
<li class="slide">
    <a href="{{ route('knowledge.dashboard') }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-book" ></i>
        <span class="side-menu__label">Knowledge Dashboard</span>
    </a>
</li>
<li class="slide">
    <a href="{{ route('knowledge.index') }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-file-earmark-text" ></i>
        <span class="side-menu__label">Knowledge Documents</span>
    </a>
</li>
<li class="slide">
    <a href="{{ route('knowledge.chat') }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-chat-dots" ></i>
        <span class="side-menu__label">Chat AI Testing</span>
    </a>
</li>
@endcan
