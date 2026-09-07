window.ulSearch = function ulSearch(config) {
    return {
        q: config.q ?? '',
        live: config.live === true,
        debounceMs: config.debounceMs ?? 350,
        name: config.name ?? 'search',
        timer: null,
        isLocalForm() {
            const form = this.$el?.closest('form');
            return !!(form && form.hasAttribute('data-ul-local'));
        },
        clear() {
            this.q = '';
            this.$refs.input.value = '';
            this.$refs.input.focus();
            this.onType();
        },
        onType() {
            // Server-backed lists must not hide rows on the current page only.
            if (this.isLocalForm()) {
                this.filterLocal();
                return;
            }
            if (!this.live) {
                return;
            }
            clearTimeout(this.timer);
            const needle = (this.q || '').trim();
            if (needle.length === 1) {
                return;
            }
            this.timer = setTimeout(() => this.submitLive(), this.debounceMs);
        },
        filterLocal() {
            if (!this.isLocalForm() && !this.$el.closest('[data-ul-table-filter]')) {
                return;
            }
            const localRoot = this.$el.closest('[data-ul-table-filter]');
            if (localRoot && typeof window.ulApplyLocalFilters === 'function') {
                window.ulApplyLocalFilters(localRoot);
                return;
            }
            const toolbarBox = this.$el.closest('.box');
            const tableBox = toolbarBox ? toolbarBox.nextElementSibling : null;
            const rows = tableBox
                ? tableBox.querySelectorAll('tbody tr.ul-row')
                : document.querySelectorAll('.ul-table tbody tr.ul-row');
            const needle = (this.q || '').trim().toLowerCase();
            let visible = 0;

            rows.forEach((row) => {
                const hay = (row.textContent || '').toLowerCase();
                const show = needle === '' || hay.includes(needle);
                row.hidden = !show;
                row.style.display = show ? '' : 'none';
                if (show) {
                    visible++;
                }
            });

            tableBox?.querySelector('tbody tr.ul-live-empty')?.remove();

            if (needle !== '' && rows.length > 0 && visible === 0 && tableBox) {
                const tbody = tableBox.querySelector('tbody');
                const cols = tableBox.querySelectorAll('thead th').length || 8;
                if (tbody) {
                    const tr = document.createElement('tr');
                    tr.className = 'ul-live-empty';
                    const td = document.createElement('td');
                    td.colSpan = cols;
                    td.innerHTML = '<div class="ul-state"><div class="ul-state-title">No matching rows</div><div class="ul-state-text">No rows on this page match that search.</div></div>';
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                }
            }
        },
        submitLive() {
            const form = this.$el.closest('form');
            if (form && !form.hasAttribute('data-ul-local')) {
                form.requestSubmit();
                return;
            }
            this.filterLocal();
        },
    };
};

function ulApplyToolbarSearch(form) {
    const input = form?.querySelector('.ul-search-input');
    if (!input) {
        return;
    }
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-ul-search-apply]');
    if (!button) {
        return;
    }
    const form = button.closest('.ul-toolbar-form');
    if (form?.hasAttribute('data-ul-local')) {
        event.preventDefault();
        ulApplyToolbarSearch(form);
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.classList.contains('ul-toolbar-form')) {
        return;
    }
    if (form.hasAttribute('data-ul-local')) {
        event.preventDefault();
        ulApplyToolbarSearch(form);
    }
});

document.addEventListener('click', (event) => {
    const ignore = event.target.closest('.ul-no-row-click, a, button, input, select, textarea, label, form');
    if (ignore) {
        return;
    }

    const row = event.target.closest('tr.ul-row[data-href]');
    if (!row || row.hidden || row.style.display === 'none') {
        return;
    }

    const href = row.getAttribute('data-href');
    if (!href) {
        return;
    }

    if (event.metaKey || event.ctrlKey) {
        window.open(href, '_blank');
        return;
    }

    window.location.href = href;
});

window.ulOptionUi = window.ulOptionUi || { map: {} };

window.ulResolveOption = function ulResolveOption(value, label, select, option) {
    const map = window.ulOptionUi?.map || {};
    const raw = String(value ?? '').trim();
    const text = String(label ?? '').trim();
    const name = select?.getAttribute('name') || '';
    const selected = option || (select && raw !== ''
        ? Array.from(select.options).find((item) => item.value === raw)
        : null);
    if (selected?.dataset?.tone || selected?.dataset?.icon) {
        return {
            tone: selected.dataset.tone || 'slate',
            icon: selected.dataset.icon || 'bi-circle',
        };
    }

    if (['verified', 'email_validated', 'customer_confirmed'].includes(name)) {
        return raw === '1' || /^yes|verified$/i.test(text)
            ? { tone: 'lime', icon: 'bi-check-circle' }
            : { tone: 'rose', icon: 'bi-x-circle' };
    }
    if (name === 'needs_further_action') {
        return raw === '1' ? { tone: 'orange', icon: 'bi-arrow-repeat' } : { tone: 'lime', icon: 'bi-check2' };
    }

    const parts = window.ulSplitOptionLabel(text);
    if (raw && map[raw]) return map[raw];
    if (text && map[text]) return map[text];
    if (parts.title && map[parts.title]) return map[parts.title];
    if (!raw) {
        return { tone: 'slate', icon: 'bi-dash-circle' };
    }

    const dept = `${raw} ${text}`.match(/\b(TSD|COMD|CCAD|AO-CDS|ISD-CCSMDD|FOCAL|GUARD|AREA-ADMIN)\b/i);
    if (dept) {
        const code = dept[1].toUpperCase();
        if (map[code]) {
            return map[code];
        }
    }

    const tones = ['teal', 'indigo', 'rose', 'sky', 'lime', 'violet', 'amber', 'cyan', 'orange', 'purple'];
    const key = raw || text || 'option';
    let hash = 0;
    for (let i = 0; i < key.length; i++) {
        hash = ((hash << 5) - hash) + key.charCodeAt(i);
        hash |= 0;
    }
    return {
        tone: tones[Math.abs(hash) % tones.length],
        icon: 'bi-circle',
    };
};

window.ulChoiceMarkup = function ulChoiceMarkup(meta, title, hint) {
    const tone = meta.tone || 'neutral';
    const markClass = meta.dot ? `ul-choice-mark is-dot is-${tone}` : `ul-choice-mark ul-badge is-${tone}`;
    const icon = meta.dot
        ? ''
        : `<i class="bi ${meta.icon || 'bi-circle'}" aria-hidden="true"></i>`;
    const hintHtml = hint ? `<span class="ul-choice-hint">${hint}</span>` : '';
    return `<span class="${markClass}">${icon}</span><span class="ul-choice-copy"><span class="ul-choice-title"></span>${hintHtml}</span>`;
};

window.ulSplitOptionLabel = function ulSplitOptionLabel(text) {
    const raw = String(text || '').replace(/\s+/g, ' ').trim();
    const paren = raw.match(/^(.+?)\s+\((.+)\)\s*$/);
    if (paren) {
        return { title: paren[1], hint: paren[2] };
    }
    if (raw.includes(' - ')) {
        const [title, ...rest] = raw.split(' - ');
        return { title, hint: rest.join(' - ') };
    }
    if (raw.includes(' · ')) {
        const [title, ...rest] = raw.split(' · ');
        return { title, hint: rest.join(' · ') };
    }
    return { title: raw || 'Select', hint: '' };
};

window.ulEnhanceSelect = function ulEnhanceSelect(select) {
    if (!select || select.dataset.ulChoice === '1' || select.multiple || select.size > 1) {
        return;
    }
    if (select.classList.contains('ul-choice-skip') || select.hasAttribute('data-trigger') || select.classList.contains('lf-acct')) {
        return;
    }
    if (['account_link_id', 'per_page'].includes(select.name) || select.id === 'ul-per-page') {
        return;
    }
    if (select.closest('.ul-choice')) {
        return;
    }

    select.dataset.ulChoice = '1';
    const wrap = document.createElement('div');
    wrap.className = 'ul-choice' + (select.classList.contains('ti-form-select-sm') ? ' is-sm' : '');
    ['mb-1', 'mb-2', 'mb-3', 'mb-4', 'mt-1', 'mt-2', 'mt-3'].forEach((cls) => {
        if (select.classList.contains(cls)) {
            wrap.classList.add(cls);
            select.classList.remove(cls);
        }
    });

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'ul-choice-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    const menu = document.createElement('div');
    menu.className = 'ul-choice-menu';
    menu.setAttribute('role', 'listbox');

    select.classList.add('ul-choice-native');
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(trigger);
    wrap.appendChild(menu);
    wrap.appendChild(select);

    const syncPageLock = () => window.ulSyncPageLock?.();
    let searchQuery = '';

    const close = () => {
        wrap.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
        searchQuery = '';
        syncPageLock();
    };

    menu.addEventListener('wheel', (event) => {
        event.preventDefault();
        event.stopPropagation();
        menu.scrollTop += event.deltaY;
    }, { passive: false });

    menu.addEventListener('touchmove', (event) => {
        event.stopPropagation();
    }, { passive: true });

    const placeMenu = () => {
        const rect = trigger.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        const height = Math.min(280, menu.scrollHeight || 280);
        menu.style.position = 'fixed';
        menu.style.left = `${Math.max(8, rect.left)}px`;
        menu.style.width = `${Math.max(rect.width, wrap.closest('.ul-card-header-actions') ? 280 : 220)}px`;
        menu.style.zIndex = '2100';
        if (spaceBelow < height + 12 && rect.top > spaceBelow) {
            menu.style.top = `${Math.max(8, rect.top - height - 4)}px`;
        } else {
            menu.style.top = `${rect.bottom + 4}px`;
        }
    };

    const renderTrigger = () => {
        const selected = select.selectedOptions[0];
        const label = selected ? selected.textContent : 'Select';
        const parts = window.ulSplitOptionLabel(label);
        const meta = window.ulResolveOption(select.value, label, select, selected);
        trigger.innerHTML = window.ulChoiceMarkup(meta, parts.title, parts.hint);
        trigger.querySelector('.ul-choice-title').textContent = parts.title;
        trigger.insertAdjacentHTML('beforeend', '<i class="bi bi-chevron-down ul-choice-caret" aria-hidden="true"></i>');
    };

    const filterMenu = () => {
        const needle = searchQuery.trim().toLowerCase();
        let visible = 0;
        menu.querySelectorAll('.ul-choice-option').forEach((btn) => {
            const hay = `${btn.textContent || ''} ${btn.dataset.value || ''}`.toLowerCase();
            const match = needle === '' || hay.includes(needle);
            btn.hidden = btn.dataset.baseHidden === '1' || !match;
            if (!btn.hidden) {
                visible += 1;
            }
        });
        const empty = menu.querySelector('.ul-choice-empty');
        if (empty) {
            empty.hidden = visible > 0;
        }
    };

    const renderMenu = () => {
        menu.innerHTML = '';

        const search = document.createElement('div');
        search.className = 'ul-choice-search';
        search.innerHTML = '<i class="bi bi-search" aria-hidden="true"></i><input type="search" placeholder="Search options…" autocomplete="off">';
        const input = search.querySelector('input');
        input.value = searchQuery;
        input.addEventListener('click', (event) => event.stopPropagation());
        input.addEventListener('keydown', (event) => {
            event.stopPropagation();
            if (event.key === 'Enter') {
                event.preventDefault();
            }
            if (event.key === 'Escape') {
                close();
                trigger.focus();
            }
        });
        input.addEventListener('input', () => {
            searchQuery = input.value;
            filterMenu();
        });
        menu.appendChild(search);

        Array.from(select.options).forEach((opt) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ul-choice-option';
            btn.dataset.value = opt.value;
            btn.dataset.baseHidden = (opt.hidden || opt.disabled) ? '1' : '0';
            btn.hidden = opt.hidden || opt.disabled;
            if (opt.selected) {
                btn.classList.add('is-active');
            }
            const parts = window.ulSplitOptionLabel(opt.textContent);
            const meta = window.ulResolveOption(opt.value, opt.textContent, select, opt);
            btn.innerHTML = window.ulChoiceMarkup(meta, parts.title, parts.hint);
            btn.querySelector('.ul-choice-title').textContent = parts.title;
            btn.addEventListener('click', () => {
                select.value = opt.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                close();
            });
            menu.appendChild(btn);
        });

        const empty = document.createElement('div');
        empty.className = 'ul-choice-empty';
        empty.textContent = 'No matching options';
        empty.hidden = true;
        menu.appendChild(empty);

        filterMenu();
        return input;
    };

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        const open = !wrap.classList.contains('is-open');
        document.querySelectorAll('.ul-choice.is-open').forEach((other) => {
            if (other !== wrap) {
                other.classList.remove('is-open');
                other.querySelector('.ul-choice-trigger')?.setAttribute('aria-expanded', 'false');
            }
        });
        wrap.classList.toggle('is-open', open);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        window.ulCloseDates?.();
        syncPageLock();
        if (open) {
            const input = renderMenu();
            placeMenu();
            setTimeout(() => input?.focus(), 0);
        }
    });

    select.addEventListener('change', () => {
        renderTrigger();
        renderMenu();
    });

    select.addEventListener('invalid', () => wrap.classList.add('is-invalid'));
    select.addEventListener('input', () => wrap.classList.remove('is-invalid'));

    wrap.ulRefresh = () => {
        renderTrigger();
        if (wrap.classList.contains('is-open')) {
            renderMenu();
            placeMenu();
        }
    };

    renderTrigger();
};

window.ulEnhanceSelects = function ulEnhanceSelects(root) {
    (root || document).querySelectorAll('select.ti-form-select').forEach((select) => {
        window.ulEnhanceSelect(select);
    });
};

window.ulFormatDate = function ulFormatDate(value) {
    if (!value) {
        return '';
    }
    const parts = String(value).split('-').map(Number);
    if (parts.length < 3 || !parts[0] || !parts[1] || !parts[2]) {
        return '';
    }
    return new Date(parts[0], parts[1] - 1, parts[2]).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

window.ulCloseDates = function ulCloseDates(except) {
    document.querySelectorAll('.ul-datepicker.is-open').forEach((wrap) => {
        if (except && wrap === except) {
            return;
        }
        wrap.classList.remove('is-open');
        wrap.querySelector('.ul-date-trigger')?.setAttribute('aria-expanded', 'false');
    });
};

window.ulEnhanceDate = function ulEnhanceDate(input) {
    if (!input || input.dataset.ulDate === '1' || input.type !== 'date') {
        return;
    }
    if (input.classList.contains('ul-date-skip') || input.closest('.ul-datepicker')) {
        return;
    }

    input.dataset.ulDate = '1';
    const wrap = document.createElement('div');
    wrap.className = 'ul-datepicker';
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'ul-date-trigger';
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-expanded', 'false');
    const menu = document.createElement('div');
    menu.className = 'ul-date-menu';
    menu.setAttribute('role', 'dialog');
    menu.setAttribute('aria-label', 'Choose date');

    input.classList.add('ul-choice-native');
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(trigger);
    wrap.appendChild(menu);
    wrap.appendChild(input);

    const today = () => {
        const now = new Date();
        return [now.getFullYear(), now.getMonth(), now.getDate()];
    };
    const parse = (value) => {
        const parts = String(value || '').split('-').map(Number);
        if (parts.length < 3 || !parts[0] || !parts[1] || !parts[2]) {
            return null;
        }
        return [parts[0], parts[1] - 1, parts[2]];
    };
    const stamp = (year, month, day) => {
        const mm = String(month + 1).padStart(2, '0');
        const dd = String(day).padStart(2, '0');
        return `${year}-${mm}-${dd}`;
    };

    let view = parse(input.value) || today();
    view = [view[0], view[1], 1];

    const renderTrigger = () => {
        const label = window.ulFormatDate(input.value) || 'Select date';
        trigger.innerHTML = `<span class="ul-choice-mark ul-badge is-lime"><i class="bi bi-calendar3" aria-hidden="true"></i></span><span class="ul-choice-copy"><span class="ul-choice-title"></span></span><i class="bi bi-chevron-down ul-choice-caret" aria-hidden="true"></i>`;
        trigger.querySelector('.ul-choice-title').textContent = label;
        trigger.classList.toggle('is-empty', !input.value);
    };

    const close = () => {
        wrap.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
    };

    const placeMenu = () => {
        const rect = trigger.getBoundingClientRect();
        const width = Math.max(rect.width, 17.5 * 16);
        const height = Math.min(22 * 16, menu.scrollHeight || 320);
        const spaceBelow = window.innerHeight - rect.bottom;
        menu.style.position = 'fixed';
        menu.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - width - 8))}px`;
        menu.style.width = `${width}px`;
        menu.style.zIndex = '2100';
        if (spaceBelow < height + 12 && rect.top > spaceBelow) {
            menu.style.top = `${Math.max(8, rect.top - height - 4)}px`;
        } else {
            menu.style.top = `${rect.bottom + 4}px`;
        }
    };

    const setValue = (value) => {
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        renderTrigger();
    };

    const renderMenu = () => {
        const year = view[0];
        const month = view[1];
        const selected = parse(input.value);
        const [ty, tm, td] = today();
        const first = new Date(year, month, 1);
        const start = first.getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const title = first.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        const weekdays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
        let cells = '';
        for (let index = 0; index < 42; index += 1) {
            const day = index - start + 1;
            if (day < 1 || day > daysInMonth) {
                cells += '<span class="ul-date-day is-out"></span>';
                continue;
            }
            const value = stamp(year, month, day);
            const isSelected = selected && selected[0] === year && selected[1] === month && selected[2] === day;
            const isToday = ty === year && tm === month && td === day;
            cells += `<button type="button" class="ul-date-day${isSelected ? ' is-selected' : ''}${isToday ? ' is-today' : ''}" data-date="${value}">${day}</button>`;
        }

        menu.innerHTML = `
            <div class="ul-date-head">
                <button type="button" class="ul-date-nav" data-nav="-1" aria-label="Previous month"><i class="bi bi-chevron-left"></i></button>
                <div class="ul-date-title">${title}</div>
                <button type="button" class="ul-date-nav" data-nav="1" aria-label="Next month"><i class="bi bi-chevron-right"></i></button>
            </div>
            <div class="ul-date-week">${weekdays.map((day) => `<span>${day}</span>`).join('')}</div>
            <div class="ul-date-grid">${cells}</div>
            <div class="ul-date-foot">
                <button type="button" class="ul-date-action" data-action="clear">Clear</button>
                <button type="button" class="ul-date-action is-today" data-action="today">Today</button>
            </div>
        `;
    };

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const open = !wrap.classList.contains('is-open');
        document.querySelectorAll('.ul-choice.is-open').forEach((other) => {
            other.classList.remove('is-open');
            other.querySelector('.ul-choice-trigger')?.setAttribute('aria-expanded', 'false');
        });
        window.ulSyncPageLock?.();
        window.ulCloseDates(wrap);
        if (!open) {
            close();
            return;
        }
        const current = parse(input.value);
        view = current ? [current[0], current[1], 1] : [today()[0], today()[1], 1];
        renderMenu();
        wrap.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        placeMenu();
    });

    menu.addEventListener('click', (event) => {
        event.stopPropagation();
        const nav = event.target.closest('[data-nav]');
        if (nav) {
            view = [view[0], view[1] + Number(nav.dataset.nav), 1];
            if (view[1] < 0) {
                view = [view[0] - 1, 11, 1];
            }
            if (view[1] > 11) {
                view = [view[0] + 1, 0, 1];
            }
            renderMenu();
            placeMenu();
            return;
        }
        const action = event.target.closest('[data-action]');
        if (action?.dataset.action === 'clear') {
            setValue('');
            close();
            return;
        }
        if (action?.dataset.action === 'today') {
            const [year, month, day] = today();
            setValue(stamp(year, month, day));
            close();
            return;
        }
        const day = event.target.closest('[data-date]');
        if (day) {
            setValue(day.dataset.date);
            close();
        }
    });

    wrap.ulRefresh = renderTrigger;
    wrap.ulPlace = placeMenu;
    input.addEventListener('change', renderTrigger);
    const onReposition = () => {
        if (wrap.classList.contains('is-open')) {
            placeMenu();
        }
    };
    window.addEventListener('resize', onReposition);
    document.addEventListener('scroll', onReposition, true);
    renderTrigger();
};

window.ulEnhanceDates = function ulEnhanceDates(root) {
    (root || document).querySelectorAll('input[type="date"]').forEach((input) => {
        window.ulEnhanceDate(input);
    });
};

window.ulSanitizeComposer = function ulSanitizeComposer(html) {
    const allowed = new Set(['B', 'STRONG', 'I', 'EM', 'U', 'S', 'STRIKE', 'UL', 'OL', 'LI', 'P', 'BR', 'DIV']);
    const tmp = document.createElement('div');
    tmp.innerHTML = String(html || '');
    const walk = (node) => {
        Array.from(node.childNodes).forEach((child) => {
            if (child.nodeType !== 1) {
                return;
            }
            if (!allowed.has(child.tagName)) {
                const parent = child.parentNode;
                while (child.firstChild) {
                    parent.insertBefore(child.firstChild, child);
                }
                parent.removeChild(child);
                return;
            }
            Array.from(child.attributes).forEach((attr) => child.removeAttribute(attr.name));
            walk(child);
        });
    };
    walk(tmp);
    return tmp.innerHTML;
};

window.ulEnhanceTextarea = function ulEnhanceTextarea(textarea) {
    if (!textarea || textarea.dataset.ulComposer === '1' || textarea.hidden || textarea.closest('.ul-composer')) {
        return;
    }
    if (textarea.classList.contains('ul-composer-skip') || textarea.classList.contains('swal2-textarea') || textarea.getAttribute('aria-hidden') === 'true') {
        return;
    }
    if (textarea.closest('.swal2-container, .swal2-popup, .swal2-html-container')) {
        return;
    }

    textarea.dataset.ulComposer = '1';
    const wrap = document.createElement('div');
    wrap.className = 'ul-composer';
    ['mb-1', 'mb-2', 'mb-3', 'mb-4', 'mt-1', 'mt-2', 'mt-3'].forEach((cls) => {
        if (textarea.classList.contains(cls)) {
            wrap.classList.add(cls);
            textarea.classList.remove(cls);
        }
    });

    const surface = document.createElement('div');
    surface.className = 'ul-composer-surface';

    const editor = document.createElement('div');
    editor.className = 'ul-composer-editor';
    editor.contentEditable = textarea.disabled || textarea.readOnly ? 'false' : 'true';
    editor.setAttribute('role', 'textbox');
    editor.setAttribute('aria-multiline', 'true');
    if (textarea.placeholder) {
        editor.dataset.placeholder = textarea.placeholder;
    }
    const raw = textarea.value || '';
    editor.innerHTML = /<[a-z][\s\S]*>/i.test(raw)
        ? window.ulSanitizeComposer(raw)
        : raw.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\n/g, '<br>');

    const toolbar = document.createElement('div');
    toolbar.className = 'ul-composer-toolbar';
    const tools = [
        { cmd: 'bold', icon: 'bi-type-bold', title: 'Bold' },
        { cmd: 'italic', icon: 'bi-type-italic', title: 'Italic' },
        { cmd: 'underline', icon: 'bi-type-underline', title: 'Underline' },
        { cmd: 'insertUnorderedList', icon: 'bi-list-ul', title: 'Bullet list' },
        { cmd: 'insertOrderedList', icon: 'bi-list-ol', title: 'Numbered list' },
    ];
    tools.forEach((tool) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ul-composer-tool';
        button.title = tool.title;
        button.setAttribute('aria-label', tool.title);
        button.dataset.cmd = tool.cmd;
        button.innerHTML = `<i class="bi ${tool.icon}" aria-hidden="true"></i>`;
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            editor.focus();
            document.execCommand(tool.cmd, false, null);
            sync();
            refreshTools();
        });
        toolbar.appendChild(button);
    });

    const bar = document.createElement('div');
    bar.className = 'ul-composer-bar';
    bar.innerHTML = '<span class="ul-composer-hint"><i class="bi bi-fonts" aria-hidden="true"></i> Bold, italic, lists</span><span class="ul-composer-count"></span>';
    const count = bar.querySelector('.ul-composer-count');

    textarea.classList.add('ul-composer-native');
    textarea.setAttribute('tabindex', '-1');
    textarea.parentNode.insertBefore(wrap, textarea);
    wrap.appendChild(surface);
    surface.appendChild(toolbar);
    surface.appendChild(editor);
    surface.appendChild(textarea);
    surface.appendChild(bar);

    const textLength = () => (editor.innerText || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim().length;

    const refreshTools = () => {
        toolbar.querySelectorAll('.ul-composer-tool').forEach((button) => {
            let active = false;
            try {
                active = document.queryCommandState(button.dataset.cmd);
            } catch (error) {
                active = false;
            }
            button.classList.toggle('is-active', Boolean(active));
        });
    };

    const sync = () => {
        const html = window.ulSanitizeComposer(editor.innerHTML);
        const empty = textLength() === 0;
        textarea.value = empty ? '' : html;
        const max = textarea.maxLength > 0 ? textarea.maxLength : null;
        const length = textLength();
        count.textContent = max ? `${length} / ${max}` : `${length}`;
        editor.classList.toggle('is-empty', empty);
        wrap.classList.remove('is-invalid');
        textarea.setCustomValidity('');
        refreshTools();
    };

    editor.addEventListener('input', sync);
    editor.addEventListener('keyup', refreshTools);
    editor.addEventListener('mouseup', refreshTools);
    editor.addEventListener('focus', () => wrap.classList.add('is-focus'));
    editor.addEventListener('blur', sync);
    editor.addEventListener('blur', () => wrap.classList.remove('is-focus'));
    textarea.addEventListener('invalid', () => wrap.classList.add('is-invalid'));
    textarea.form?.addEventListener('submit', (event) => {
        sync();
        if (textarea.required && textarea.value === '') {
            textarea.setCustomValidity('This field is required.');
            wrap.classList.add('is-invalid');
            event.preventDefault();
            editor.focus();
        }
    });

    wrap.ulRefresh = sync;
    sync();
};

window.ulEnhanceTextareas = function ulEnhanceTextareas(root) {
    (root || document).querySelectorAll('textarea').forEach((textarea) => {
        window.ulEnhanceTextarea(textarea);
    });
};

window.ulEnhanceDropzone = function ulEnhanceDropzone(zone) {
    if (!zone || zone.dataset.ulDropzone === '1') {
        return;
    }

    const input = zone.querySelector('.ul-dropzone-input, input[type="file"]');
    const choose = zone.querySelector('.ul-dropzone-choose');
    const picked = zone.querySelector('.ul-dropzone-picked');
    const name = zone.querySelector('.ul-dropzone-name');
    const clear = zone.querySelector('.ul-dropzone-clear');
    if (!input) {
        return;
    }

    zone.dataset.ulDropzone = '1';

    const allowed = (input.getAttribute('accept') || '')
        .split(',')
        .map((item) => item.trim().toLowerCase())
        .filter(Boolean);

    const matches = (file) => {
        if (!file || allowed.length === 0) {
            return true;
        }
        const ext = `.${(file.name.split('.').pop() || '').toLowerCase()}`;
        const type = (file.type || '').toLowerCase();
        return allowed.some((rule) => rule === ext || rule === type || (rule.endsWith('/*') && type.startsWith(rule.replace('/*', '/'))));
    };

    const show = () => {
        const file = input.files && input.files[0];
        zone.classList.toggle('has-file', Boolean(file));
        zone.classList.remove('is-invalid');
        if (picked) {
            picked.hidden = !file;
        }
        if (name) {
            name.textContent = file ? `${file.name} · ${(file.size / 1024).toFixed(1)} KB` : '';
        }
        input.setCustomValidity('');
    };

    const assign = (file) => {
        if (!file) {
            return;
        }
        if (!matches(file)) {
            input.setCustomValidity('This file type is not allowed.');
            zone.classList.add('is-invalid');
            return;
        }
        const data = new DataTransfer();
        data.items.add(file);
        input.files = data.files;
        show();
    };

    choose?.addEventListener('click', (event) => {
        event.preventDefault();
        input.click();
    });
    clear?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        input.value = '';
        show();
    });
    zone.addEventListener('click', (event) => {
        if (event.target.closest('.ul-dropzone-choose, .ul-dropzone-clear, .ul-dropzone-input')) {
            return;
        }
        input.click();
    });
    ['dragenter', 'dragover'].forEach((type) => {
        zone.addEventListener(type, (event) => {
            event.preventDefault();
            event.stopPropagation();
            zone.classList.add('is-dragover');
        });
    });
    ['dragleave', 'dragend'].forEach((type) => {
        zone.addEventListener(type, (event) => {
            event.preventDefault();
            zone.classList.remove('is-dragover');
        });
    });
    zone.addEventListener('drop', (event) => {
        event.preventDefault();
        event.stopPropagation();
        zone.classList.remove('is-dragover');
        assign(event.dataTransfer?.files?.[0]);
    });
    input.addEventListener('change', show);
    input.addEventListener('invalid', () => zone.classList.add('is-invalid'));
    input.form?.addEventListener('submit', () => {
        if (input.required && !input.files?.length) {
            zone.classList.add('is-invalid');
        }
    });
    show();
};

window.ulEnhanceDropzones = function ulEnhanceDropzones(root) {
    (root || document).querySelectorAll('.ul-dropzone').forEach((zone) => {
        window.ulEnhanceDropzone(zone);
    });
};

window.ulEnhanceMinutes = function ulEnhanceMinutes(root) {
    (root || document).querySelectorAll('[data-ul-minutes]').forEach((wrap) => {
        if (wrap.dataset.ulMinutesReady === '1') {
            wrap.ulRefresh?.();
            return;
        }

        const preset = wrap.querySelector('.ul-minutes-preset');
        const input = wrap.querySelector('.ul-minutes-input');
        const custom = wrap.querySelector('.ul-minutes-custom');
        if (!preset || !input) {
            return;
        }

        wrap.dataset.ulMinutesReady = '1';

        const refreshChoice = () => preset.closest('.ul-choice')?.ulRefresh?.();
        const sync = () => {
            const isCustom = preset.value === 'custom';
            wrap.classList.toggle('is-custom', isCustom);
            if (custom) {
                custom.hidden = !isCustom;
            }
            if (!isCustom && preset.value !== '') {
                input.value = preset.value;
            }
        };

        preset.addEventListener('change', () => {
            if (preset.value === 'custom') {
                input.value = '';
                input.focus();
            } else if (preset.value !== '') {
                input.value = preset.value;
            }
            sync();
        });

        input.addEventListener('input', () => {
            const value = String(input.value || '');
            const hasPreset = Array.from(preset.options).some((option) => option.value === value);
            preset.value = hasPreset ? value : (value === '' ? '' : 'custom');
            sync();
            refreshChoice();
        });

        wrap.ulRefresh = sync;
        sync();
    });
};

window.ulEnhanceExpand = function ulEnhanceExpand(root) {
    (root || document).querySelectorAll('[data-ul-expand]').forEach((wrap) => {
        if (wrap.dataset.ulExpandReady === '1') {
            return;
        }

        const body = wrap.querySelector('.ul-expand-body');
        const toggle = wrap.querySelector('.ul-expand-toggle');
        if (!body || !toggle) {
            return;
        }

        const limit = Number(wrap.dataset.ulExpandMax || 44);
        const apply = () => {
            const wasOpen = wrap.classList.contains('is-open');
            wrap.classList.remove('is-long', 'is-open');
            const long = body.scrollHeight > limit + 12;
            wrap.classList.toggle('is-long', long);
            if (wasOpen && long) {
                wrap.classList.add('is-open');
            }
            toggle.hidden = !long;
            toggle.textContent = wrap.classList.contains('is-open') ? 'Show less' : 'Show more';
            toggle.setAttribute('aria-expanded', wrap.classList.contains('is-open') ? 'true' : 'false');
        };

        toggle.addEventListener('click', () => {
            wrap.classList.toggle('is-open');
            const open = wrap.classList.contains('is-open');
            toggle.textContent = open ? 'Show less' : 'Show more';
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        wrap.dataset.ulExpandReady = '1';
        requestAnimationFrame(apply);
        window.addEventListener('resize', apply);
    });
};

window.ulSyncPageLock = function ulSyncPageLock() {
    document.documentElement.classList.toggle(
        'ul-choice-lock',
        Boolean(document.querySelector('.ul-choice.is-open, .ul-modal.is-open')),
    );
};

window.ulCloseModal = function ulCloseModal(modal) {
    if (!modal) {
        return;
    }
    const onClose = modal._ulOnClose;
    modal._ulOnClose = null;
    modal.classList.remove('is-open');
    modal.setAttribute('hidden', 'hidden');
    window.ulSyncPageLock();
    if (typeof onClose === 'function') {
        onClose();
    }
};

window.ulConfirm = function ulConfirm(options = {}) {
    const verb = String(options.verb || options.confirmText || 'Continue').trim() || 'Continue';
    const title = String(options.title || verb).trim() || verb;
    const text = String(options.text || options.message || '').trim();
    const tone = options.tone === 'danger' ? 'danger' : 'primary';
    const icon = String(options.icon || (tone === 'danger' ? 'bi-exclamation-triangle' : 'bi-question-circle')).replace(/^bi\s+/, '');
    const modal = window.ulEnsureConfirmModal();
    const titleEl = modal.querySelector('[data-ul-confirm-title]');
    const textEl = modal.querySelector('[data-ul-confirm-text]');
    const iconWrap = modal.querySelector('[data-ul-confirm-icon]');
    const iconEl = iconWrap?.querySelector('i');
    const okBtn = modal.querySelector('[data-ul-confirm-ok]');

    if (titleEl) {
        titleEl.textContent = title;
    }
    if (textEl) {
        textEl.textContent = text;
        textEl.hidden = !text;
    }
    if (iconWrap) {
        iconWrap.className = `ul-modal-icon ul-badge ${tone === 'danger' ? 'is-rose' : 'is-indigo'}`;
    }
    if (iconEl) {
        iconEl.className = icon.startsWith('bi-') ? `bi ${icon}` : `bi bi-${icon}`;
    }
    if (okBtn) {
        okBtn.textContent = verb;
        okBtn.className = `ti-btn ti-btn-sm ul-btn ${tone === 'danger' ? 'ul-btn-danger' : 'ul-btn-primary'}`;
    }

    return new Promise((resolve) => {
        let settled = false;
        const finish = (ok) => {
            if (settled) {
                return;
            }
            settled = true;
            modal._ulOnClose = null;
            window.ulCloseModal(modal);
            resolve(Boolean(ok));
        };
        modal._ulOnClose = () => finish(false);
        okBtn.onclick = () => finish(true);
        window.ulOpenModal(modal);
        okBtn.focus?.();
    });
};

window.ulEnsureConfirmModal = function ulEnsureConfirmModal() {
    let modal = document.getElementById('ul-confirm-modal');
    if (modal) {
        return modal;
    }
    modal = document.createElement('div');
    modal.id = 'ul-confirm-modal';
    modal.className = 'ul-modal';
    modal.setAttribute('hidden', 'hidden');
    modal.innerHTML = `
        <div class="ul-modal-backdrop" data-ul-modal-close></div>
        <div class="ul-modal-dialog is-confirm" role="dialog" aria-modal="true" aria-labelledby="ul-confirm-title">
            <div class="ul-modal-header">
                <span class="ul-modal-icon ul-badge is-indigo" data-ul-confirm-icon><i class="bi bi-question-circle" aria-hidden="true"></i></span>
                <div class="ul-modal-copy">
                    <h6 id="ul-confirm-title" data-ul-confirm-title>Continue</h6>
                    <p data-ul-confirm-text></p>
                </div>
                <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Cancel">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="ul-modal-body">
                <div class="ul-modal-footer">
                    <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>Cancel</button>
                    <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-primary" data-ul-confirm-ok>Continue</button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    return modal;
};

window.ulConfirmFrom = function ulConfirmFrom(source) {
    const verb = source.getAttribute('data-ul-confirm-verb') || source.getAttribute('data-ul-confirm-title') || 'Continue';
    return window.ulConfirm({
        verb,
        title: source.getAttribute('data-ul-confirm-title') || verb,
        text: source.getAttribute('data-ul-confirm') || '',
        tone: source.getAttribute('data-ul-confirm-tone') || 'primary',
        icon: source.getAttribute('data-ul-confirm-icon') || '',
    });
};

window.ulProceedConfirmed = function ulProceedConfirmed(source) {
    source.dataset.ulConfirmed = '1';
    if (source.tagName === 'FORM') {
        if (typeof source.requestSubmit === 'function') {
            source.requestSubmit();
            return;
        }
        source.submit();
        return;
    }
    const form = source.form || source.closest('form');
    const isSubmit = source.matches('button:not([type]), button[type="submit"], input[type="submit"]');
    if (form) {
        form.dataset.ulConfirmed = '1';
        if (isSubmit && typeof form.requestSubmit === 'function') {
            form.requestSubmit(source);
            return;
        }
    }
    source.click();
};

window.ulOpenModal = function ulOpenModal(target) {
    const modal = typeof target === 'string' ? document.querySelector(target) : target;
    if (!modal) {
        return;
    }
    document.querySelectorAll('.ul-modal.is-open').forEach((open) => {
        if (open !== modal) {
            window.ulCloseModal(open);
        }
    });
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    modal.removeAttribute('hidden');
    modal.classList.add('is-open');
    window.ulSyncPageLock();
    window.ulEnhanceSelects?.(modal);
    window.ulEnhanceDates?.(modal);
    window.ulEnhanceTextareas?.(modal);
    window.ulEnhanceDropzones?.(modal);
    window.ulEnhanceMinutes?.(modal);
    modal.querySelectorAll('.ul-choice').forEach((wrap) => wrap.ulRefresh?.());
    modal.querySelectorAll('.ul-composer').forEach((wrap) => wrap.ulRefresh?.());
    modal.querySelectorAll('[data-ul-minutes]').forEach((wrap) => wrap.ulRefresh?.());
};

document.addEventListener('click', (event) => {
    const confirmSource = event.target.closest('[data-ul-confirm]');
    if (!confirmSource || confirmSource.tagName === 'FORM') {
        return;
    }
    if (confirmSource.dataset.ulConfirmed === '1') {
        delete confirmSource.dataset.ulConfirmed;
        return;
    }
    event.preventDefault();
    event.stopPropagation();
    window.ulConfirmFrom(confirmSource).then((ok) => {
        if (ok) {
            window.ulProceedConfirmed(confirmSource);
        }
    });
}, true);

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-ul-modal]');
    if (opener) {
        event.preventDefault();
        window.ulOpenModal(opener.getAttribute('data-ul-modal'));
        return;
    }
    if (event.target.closest('[data-ul-modal-close]')) {
        window.ulCloseModal(event.target.closest('.ul-modal'));
        return;
    }
    if (!event.target.closest('.ul-choice')) {
        document.querySelectorAll('.ul-choice.is-open').forEach((wrap) => {
            wrap.classList.remove('is-open');
            wrap.querySelector('.ul-choice-trigger')?.setAttribute('aria-expanded', 'false');
        });
        window.ulSyncPageLock();
    }
    if (!event.target.closest('.ul-datepicker')) {
        window.ulCloseDates?.();
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest?.('form[data-ul-confirm]');
    if (!form) {
        return;
    }
    if (form.dataset.ulConfirmed === '1') {
        delete form.dataset.ulConfirmed;
        return;
    }
    event.preventDefault();
    window.ulConfirmFrom(form).then((ok) => {
        if (ok) {
            window.ulProceedConfirmed(form);
        }
    });
}, true);

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }
    const openChoice = document.querySelector('.ul-choice.is-open');
    if (openChoice) {
        openChoice.classList.remove('is-open');
        openChoice.querySelector('.ul-choice-trigger')?.setAttribute('aria-expanded', 'false');
        window.ulSyncPageLock();
        return;
    }
    const openDate = document.querySelector('.ul-datepicker.is-open');
    if (openDate) {
        window.ulCloseDates?.();
        return;
    }
    const openModal = document.querySelector('.ul-modal.is-open');
    if (openModal) {
        window.ulCloseModal(openModal);
    }
});

window.ulApplyLocalFilters = function ulApplyLocalFilters(scope) {
    const root = typeof scope === 'string' ? document.querySelector(scope) : scope;
    if (!root) {
        return;
    }

    const searchInput = root.querySelector('[data-ul-filter-search], .ul-search-input');
    const needle = (searchInput?.value || '').trim().toLowerCase();
    const filters = {};
    root.querySelectorAll('[data-ul-filter]').forEach((el) => {
        const key = el.getAttribute('data-ul-filter');
        if (key) {
            filters[key] = (el.value || '').trim().toLowerCase();
        }
    });

    root.querySelectorAll('[data-ul-filter-table]').forEach((tableBox) => {
        const table = tableBox.matches('table') ? tableBox : tableBox.querySelector('table.ul-table, table');
        if (!table) {
            return;
        }
        const tbody = table.querySelector('tbody');
        const rows = table.querySelectorAll('tbody tr[data-ul-row]');
        tbody?.querySelector('tr.ul-live-empty')?.remove();
        let visible = 0;

        rows.forEach((row) => {
            const hay = (row.getAttribute('data-ul-search') || row.textContent || '').toLowerCase();
            let show = needle === '' || hay.includes(needle);
            if (show) {
                Object.entries(filters).forEach(([key, value]) => {
                    if (!value) {
                        return;
                    }
                    const attr = row.getAttribute('data-' + key);
                    if (attr !== null && attr.toLowerCase() !== value) {
                        show = false;
                    }
                });
            }
            row.hidden = !show;
            row.style.display = show ? '' : 'none';
            if (show) {
                visible += 1;
                const num = row.querySelector('.ul-row-num-value');
                if (num) {
                    num.textContent = visible + '.';
                }
            }
        });

        const countLabel = tableBox.querySelector('.ul-card-count');
        if (countLabel && rows.length > 0) {
            countLabel.textContent = visible + ' ' + (visible === 1 ? 'record' : 'records');
        }

        const hasFilter = needle !== '' || Object.values(filters).some(Boolean);
        if (hasFilter && rows.length > 0 && visible === 0 && tbody) {
            const tr = document.createElement('tr');
            tr.className = 'ul-live-empty';
            const td = document.createElement('td');
            td.colSpan = table.querySelectorAll('thead th').length || 8;
            td.innerHTML = '<div class="ul-state"><div class="ul-state-title">No matching rows</div><div class="ul-state-text">Try another search or clear the current filter.</div></div>';
            tr.appendChild(td);
            tbody.appendChild(tr);
        }
    });

    const active = (needle ? 1 : 0) + Object.values(filters).filter(Boolean).length;
    root.querySelectorAll('[data-ul-filter-count]').forEach((badge) => {
        badge.textContent = String(active);
        badge.hidden = active === 0;
    });
};

window.ulBindLocalTableFilters = function ulBindLocalTableFilters(root) {
    const scope = root || document;
    scope.querySelectorAll('[data-ul-table-filter]').forEach((box) => {
        if (box.dataset.ulFilterReady === '1') {
            return;
        }
        box.dataset.ulFilterReady = '1';
        const apply = () => window.ulApplyLocalFilters(box);
        const form = box.querySelector('form');
        form?.setAttribute('data-ul-local', '1');
        form?.addEventListener('submit', (event) => {
            event.preventDefault();
            apply();
        });
        box.addEventListener('input', (event) => {
            if (event.target.closest('[data-ul-filter-search], .ul-search-input, .ul-search')) {
                apply();
            }
        });
        box.addEventListener('change', (event) => {
            if (event.target.closest('[data-ul-filter]')) {
                apply();
            }
        });
        box.querySelectorAll('[data-ul-filter-reset]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                box.querySelectorAll('[data-ul-filter-search], .ul-search-input').forEach((input) => {
                    input.value = '';
                    const alpineRoot = input.closest('[x-data]');
                    if (alpineRoot && window.Alpine) {
                        const data = window.Alpine.$data(alpineRoot);
                        if (data && 'q' in data) {
                            data.q = '';
                        }
                    }
                });
                box.querySelectorAll('[data-ul-filter]').forEach((el) => {
                    el.value = '';
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                });
                apply();
            });
        });
        box.querySelectorAll('[data-ul-filter-apply]').forEach((button) => {
            button.addEventListener('click', apply);
        });
        apply();
    });
};

window.ulEnhanceChecks = function ulEnhanceChecks(root) {
    const scope = root || document;
    scope.querySelectorAll('[data-ul-check-all]').forEach((master) => {
        if (master.dataset.ulCheckReady === '1') {
            return;
        }
        master.dataset.ulCheckReady = '1';
        const name = master.getAttribute('data-ul-check-all') || 'ids[]';
        const box = master.closest('form, .ul-card, table') || document;
        const items = () => Array.from(box.querySelectorAll(`input[type="checkbox"][name="${name}"]`));
        const sync = () => {
            const list = items();
            const checked = list.filter((item) => item.checked).length;
            master.checked = list.length > 0 && checked === list.length;
            master.indeterminate = checked > 0 && checked < list.length;
        };
        master.addEventListener('change', () => {
            items().forEach((item) => {
                item.checked = master.checked;
            });
            master.indeterminate = false;
        });
        box.addEventListener('change', (event) => {
            if (event.target.matches?.(`input[type="checkbox"][name="${name}"]`)) {
                sync();
            }
        });
        sync();
    });
};

document.addEventListener('DOMContentLoaded', () => {
    window.ulEnhanceSelects();
    window.ulEnhanceDates();
    window.ulEnhanceTextareas();
    window.ulEnhanceDropzones();
    window.ulEnhanceMinutes();
    window.ulEnhanceExpand();
    window.ulEnhanceChecks();
    window.ulBindLocalTableFilters();
});
if (document.readyState !== 'loading') {
    window.ulEnhanceSelects();
    window.ulEnhanceDates();
    window.ulEnhanceTextareas();
    window.ulEnhanceDropzones();
    window.ulEnhanceMinutes();
    window.ulEnhanceExpand();
    window.ulEnhanceChecks();
    window.ulBindLocalTableFilters();
}

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
        return;
    }

    const row = event.target.closest?.('tr.ul-row[data-href]');
    if (!row || event.target !== row || row.hidden || row.style.display === 'none') {
        return;
    }

    event.preventDefault();
    const href = row.getAttribute('data-href');
    if (href) {
        window.location.href = href;
    }
});

window.ulBusyButton = function ulBusyButton(button) {
    if (!button || button.dataset.ulBusy === '1') {
        return;
    }
    if (button.classList.contains('ul-no-busy') || button.closest('.ul-no-busy')) {
        return;
    }

    button.dataset.ulBusy = '1';
    button.classList.add('is-busy');
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');

    const icon = button.querySelector(':scope > i, :scope > svg');
    if (icon) {
        icon.classList.add('ul-busy-hide');
    }

    if (button.matches('button, a') && !button.querySelector('.ul-btn-spin')) {
        const spin = document.createElement('span');
        spin.className = 'ul-btn-spin';
        spin.setAttribute('aria-hidden', 'true');
        button.insertBefore(spin, button.firstChild);
    }
};

window.ulReadyButton = function ulReadyButton(button) {
    if (!button || button.dataset.ulBusy !== '1') {
        return;
    }

    delete button.dataset.ulBusy;
    button.classList.remove('is-busy');
    button.disabled = false;
    button.removeAttribute('aria-busy');
    button.querySelector('.ul-btn-spin')?.remove();
    button.querySelectorAll('.ul-busy-hide').forEach((el) => el.classList.remove('ul-busy-hide'));
};

window.ulBusyForm = function ulBusyForm(form, submitter) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    if (form.classList.contains('ul-no-busy') || form.dataset.ulNoBusy === '1' || form.id === 'chat-form') {
        return;
    }

    const button = submitter instanceof HTMLElement
        ? submitter
        : form.querySelector('button[type="submit"], button:not([type="button"]):not([type="reset"]), input[type="submit"]');

    if (button) {
        window.ulBusyButton(button);
    }

    form.querySelectorAll('button[type="submit"], button:not([type="button"]):not([type="reset"]), input[type="submit"]').forEach((other) => {
        if (other !== button) {
            other.disabled = true;
        }
    });
};

if (!window.ulBusyBound) {
    window.ulBusyBound = true;

    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented || !(event.target instanceof HTMLFormElement)) {
            return;
        }
        window.ulBusyForm(event.target, event.submitter);
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-ul-busy]');
        if (!button || button.closest('.ul-no-busy')) {
            return;
        }
        window.ulBusyButton(button);
    });

    if (!HTMLFormElement.prototype.ulNativeSubmit) {
        HTMLFormElement.prototype.ulNativeSubmit = HTMLFormElement.prototype.submit;
        HTMLFormElement.prototype.submit = function ulSubmitWithBusy() {
            window.ulBusyForm(this);
            return HTMLFormElement.prototype.ulNativeSubmit.call(this);
        };
    }
}

(function () {
    const MIN_WRAP = 256;

    function fitRows(table) {
        return Array.from(table.querySelectorAll('tbody tr')).filter((row) => !row.querySelector('.ul-state'));
    }

    function recordLabel(count) {
        return count + (count === 1 ? ' record' : ' records');
    }

    function pageItems(current, last) {
        if (last <= 7) {
            return Array.from({ length: last }, (_, index) => index + 1);
        }

        const items = [1];
        const start = Math.max(2, current - 1);
        const end = Math.min(last - 1, current + 1);
        if (start > 2) {
            items.push('ellipsis');
        }
        for (let page = start; page <= end; page += 1) {
            items.push(page);
        }
        if (end < last - 1) {
            items.push('ellipsis');
        }
        items.push(last);
        return items;
    }

    function renderPages(nav, current, last, goTo) {
        nav.replaceChildren();
        if (last <= 1) {
            nav.hidden = true;
            return;
        }

        nav.hidden = false;

        const addControl = (label, page, options = {}) => {
            const isStatic = options.disabled || options.current;
            const el = document.createElement(isStatic ? 'span' : 'button');
            if (el.tagName === 'BUTTON') {
                el.type = 'button';
                el.addEventListener('click', () => goTo(page));
            }
            el.className = 'ul-page'
                + (options.current ? ' is-current' : '')
                + (options.disabled ? ' is-disabled' : '');
            el.innerHTML = label;
            if (options.current) {
                el.setAttribute('aria-current', 'page');
            }
            if (options.disabled) {
                el.setAttribute('aria-disabled', 'true');
            }
            nav.appendChild(el);
        };

        addControl('<i class="bi bi-chevron-left" aria-hidden="true"></i>', current - 1, { disabled: current <= 1 });
        pageItems(current, last).forEach((item) => {
            if (item === 'ellipsis') {
                addControl('…', 0, { disabled: true });
                return;
            }
            addControl(String(item), item, { current: item === current });
        });
        addControl('<i class="bi bi-chevron-right" aria-hidden="true"></i>', current + 1, { disabled: current >= last });
    }

    function bindFitTable(card) {
        if (card._ulFit) {
            card._ulFit.apply();
            return;
        }

        const wrap = card.querySelector('[data-ul-fit-wrap]') || card.querySelector('.ul-table-wrap');
        const table = wrap ? wrap.querySelector('table') : null;
        const count = card.querySelector('[data-ul-fit-count]') || card.querySelector('.ul-card-count');
        const meta = card.querySelector('[data-ul-fit-meta]');
        const nav = card.querySelector('[data-ul-fit-pages]');
        const bar = card.querySelector('[data-ul-fit-bar]') || card.querySelector('.ul-table-bar');
        const header = card.querySelector('.box-header');
        if (!wrap || !table) {
            return;
        }

        const rows = fitRows(table);
        if (!rows.length) {
            return;
        }

        let page = 1;

        const apply = () => {
            rows.forEach((row) => {
                row.hidden = false;
                row.style.display = '';
            });

            const cardTop = card.getBoundingClientRect().top + window.scrollY;
            const headerH = header ? header.getBoundingClientRect().height : 0;
            const barH = bar ? Math.max(bar.getBoundingClientRect().height, 52) : 0;
            const leftover = window.innerHeight - cardTop - headerH - barH - 24;
            const cssMax = parseFloat(window.getComputedStyle(wrap).maxHeight);
            let wrapH = Math.max(MIN_WRAP, leftover);
            if (Number.isFinite(cssMax) && cssMax > 0) {
                wrapH = Math.min(wrapH, cssMax);
            }
            wrap.style.height = Math.round(wrapH) + 'px';

            const theadH = table.tHead ? table.tHead.getBoundingClientRect().height : 0;
            const rowH = rows[0].getBoundingClientRect().height;
            if (rowH <= 0) {
                return;
            }

            const pageSize = Math.max(1, Math.floor((wrap.clientHeight - theadH - 2) / rowH));
            const last = Math.max(1, Math.ceil(rows.length / pageSize));
            if (page > last) {
                page = last;
            }

            const start = (page - 1) * pageSize;
            const end = Math.min(rows.length, start + pageSize);
            rows.forEach((row, index) => {
                const show = index >= start && index < end;
                row.hidden = !show;
                row.style.display = show ? '' : 'none';
                const num = row.querySelector('.ul-row-num-value');
                if (num && show) {
                    num.textContent = (index + 1) + '.';
                }
            });

            if (count) {
                count.textContent = recordLabel(end - start);
            }
            if (meta) {
                meta.textContent = 'Showing ' + (start + 1) + '–' + end + ' of ' + rows.length;
            }
            if (nav) {
                renderPages(nav, page, last, (next) => {
                    page = next;
                    apply();
                });
            }
        };

        card._ulFit = { apply };
        apply();
    }

    window.ulFitTables = function ulFitTables() {
        document.querySelectorAll('[data-ul-fit-table]').forEach(bindFitTable);
    };

    window.addEventListener('resize', () => window.ulFitTables());
    window.addEventListener('load', () => window.ulFitTables());
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.ulFitTables());
    } else {
        window.ulFitTables();
    }
})();
