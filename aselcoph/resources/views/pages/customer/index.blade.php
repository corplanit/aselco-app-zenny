<x-app-layout>
    <x-slot name="title">Manage Consumer</x-slot>
    <x-slot name="url_1">{"link": "{{ route('consumer.list') }}", "text": "Manage Consumer"}</x-slot>
    <x-slot name="active">Consumers</x-slot>
    <x-slot name="buttons">
        <button class="ti-btn ti-btn-sm ul-btn ul-btn-view" type="button" data-ul-modal="#create-contact">
            <i class="bi bi-person-plus"></i>Register consumer
        </button>
    </x-slot>

    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <x-unified.toolbar
        :action="route('consumer.list')"
        :reset-url="route('consumer.list')"
        :search-value="$filters['search'] ?? ''"
        search-placeholder="Search account number, consumer, email, contact…"
        :active-filter-count="$activeFilterCount"
    >
        <x-slot:filters>
            <div>
                <label class="ti-form-label">Status</label>
                <select name="status" class="ti-form-select">
                    <option value="">All</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ti-form-label">Portal account</label>
                <select name="portal" class="ti-form-select">
                    <option value="">All</option>
                    <option value="linked" @selected(($filters['portal'] ?? '') === 'linked')>Linked</option>
                    <option value="unlinked" @selected(($filters['portal'] ?? '') === 'unlinked')>Not linked</option>
                </select>
            </div>
        </x-slot:filters>
        <x-slot:footer>
            <div class="flex gap-1 flex-wrap mt-3">
                <a href="{{ route('consumer.list', array_filter(['search' => $filters['search'] ?? null])) }}"
                   class="ti-btn ti-btn-sm {{ empty($filters['status']) && empty($filters['portal']) ? 'ti-btn-primary' : 'ti-btn-light' }}">
                    All
                </a>
                @foreach($statuses as $status)
                    <a href="{{ route('consumer.list', array_filter(['status' => $status, 'search' => $filters['search'] ?? null, 'portal' => $filters['portal'] ?? null])) }}"
                       class="ti-btn ti-btn-sm {{ ($filters['status'] ?? '') === $status ? 'ti-btn-primary' : 'ti-btn-light' }}">
                        {{ $status }}
                    </a>
                @endforeach
            </div>
        </x-slot:footer>
    </x-unified.toolbar>

    <x-unified.table
        title="Consumers"
        :paginator="$consumers"
        :has-filters="$activeFilterCount > 0"
        :reset-url="route('consumer.list')"
        empty-title="No consumers found."
        empty-text="Try a different search, or register a new consumer account."
        :colspan="7"
    >
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <x-unified.th label="Account No." column="account_no" :current-sort="$list['sort'] ?? null" :current-dir="$list['dir'] ?? 'asc'" :query="$list['query']" default-sort="customer" />
            <x-unified.th label="Consumer" column="customer" :current-sort="$list['sort'] ?? null" :current-dir="$list['dir'] ?? 'asc'" :query="$list['query']" default-sort="customer" />
            <x-unified.th label="Email" column="email" :current-sort="$list['sort'] ?? null" :current-dir="$list['dir'] ?? 'asc'" :query="$list['query']" default-sort="customer" />
            <th>Contact</th>
            <x-unified.th label="Status" column="status" :current-sort="$list['sort'] ?? null" :current-dir="$list['dir'] ?? 'asc'" :query="$list['query']" default-sort="customer" />
            <th class="text-end ul-col-actions">Actions</th>
        </x-slot:head>

        @foreach($consumers as $consumer)
            @php
                $status = $consumer->status ?: 'Inactive';
                $isLinked = $status === 'Linked' || filled($consumer->user_id);
                $statusUi = $isLinked
                    ? ['tone' => 'lime', 'icon' => 'bi-check-circle']
                    : ['tone' => 'rose', 'icon' => 'bi-x-circle'];
                $contact = trim((string) $consumer->contact);
                $contactDigits = preg_replace('/\D+/', '', $contact);
                if (strlen($contactDigits) === 10) {
                    $contactLabel = '(+63) '.preg_replace('/^(\d{3})(\d{3})(\d{4})$/', '$1 $2 $3', $contactDigits);
                } elseif ($contact !== '') {
                    $contactLabel = $contact;
                } else {
                    $contactLabel = null;
                }
            @endphp
            <x-unified.row
                class="ul-row"
                data-consumer-open
                data-account-no="{{ $consumer->account_no }}"
                data-customer="{{ $consumer->customer }}"
                data-email="{{ $consumer->email }}"
                data-contact="{{ $consumer->contact }}"
                data-user-id="{{ $consumer->user_id }}"
                data-status="{{ $status }}"
            >
                <x-unified.td-num :paginator="$consumers" :iteration="$loop->iteration" />
                <td><code class="text-xs">{{ $consumer->account_no }}</code></td>
                <td class="ul-td-primary">
                    <button type="button" class="ul-primary-link" data-consumer-open>{{ $consumer->customer ?: '—' }}</button>
                    <div class="ul-meta">{{ $consumer->user_id ? 'Portal user #'.$consumer->user_id : 'No portal account' }}</div>
                </td>
                <td>
                    @if($consumer->email)
                        {{ $consumer->email }}
                    @else
                        <span class="ul-empty">—</span>
                    @endif
                </td>
                <td>
                    @if($contactLabel)
                        {{ $contactLabel }}
                    @else
                        <span class="ul-empty">—</span>
                    @endif
                </td>
                <td>
                    <x-unified.badge :tone="$statusUi['tone']" :icon="$statusUi['icon']">{{ $status }}</x-unified.badge>
                </td>
                <x-unified.actions>
                    <x-slot:menu>
                        <button type="button" class="ul-menu-item" role="menuitem" data-consumer-open>
                            <i class="bi bi-eye"></i> View
                        </button>
                        <form method="POST" action="{{ url('/delete') }}" data-ul-confirm="Remove this consumer from the list?" data-ul-confirm-verb="Remove" data-ul-confirm-tone="danger" data-ul-confirm-icon="bi-trash">
                            @csrf
                            <input type="hidden" name="id" value="{{ $consumer->account_no }}">
                            <input type="hidden" name="type" value="consumer">
                            <button type="submit" class="ul-menu-item" role="menuitem">
                                <i class="bi bi-trash"></i> Remove
                            </button>
                        </form>
                    </x-slot:menu>
                </x-unified.actions>
            </x-unified.row>
        @endforeach
    </x-unified.table>

    @include('pages.customer.modals.register')
    @include('pages.customer.modals.details')
    @include('pages.customer.modals.script')

    <script>
        (function () {
            function openConsumer(source) {
                const row = source.closest('[data-account-no]');
                if (!row) {
                    return;
                }

                const data = row.dataset;
                const accountNo = (data.accountNo || '').trim();
                const customer = (data.customer || '').trim();
                const email = (data.email || '').trim();
                const contact = (data.contact || '').trim();
                const userId = (data.userId || '').trim();
                const status = (data.status || '').trim();

                $('#PopupInfo #popup-id').text(accountNo);
                $('#PopupInfo #d_account_no').val(accountNo);
                $('#PopupInfo #d_consumer').val(customer);
                $('#PopupInfo #d_email').val(email);
                $('#PopupInfo #d_mobile').val(contact);
                $('#PopupInfo #pw_user_id').val(userId);
                if (typeof window.loadAstWalletTab === 'function') {
                    window.loadAstWalletTab(accountNo);
                }

                $('#PopupInfo #client_form').show();
                $('#PopupInfo #pwd_content').removeClass('hidden');
                $('#PopupInfo #check-status').addClass('hidden').html('');
                $('#PopupInfo #link-ui').addClass('hidden');
                $('#PopupInfo #pwd_msg').html('');
                $('#PopupInfo #save-changes').show();
                $('#PopupInfo #create-account').hide();
                $('#PopupInfo #link-account').hide();
                $('#PopupInfo #d_email, #PopupInfo #d_mobile').removeClass('!border !border-danger');

                const tbody = $('#linkedAccountsTable tbody');
                tbody.empty();

                if (status === 'Inactive') {
                    $('#PopupInfo #d_email, #PopupInfo #d_mobile').addClass('!border !border-danger');
                    $('#PopupInfo #save-changes').hide();
                    $('#PopupInfo #pwd_content').addClass('hidden');
                    $('#PopupInfo #pwd_msg').html('<i class="text-danger">Sorry, this feature is not available for inactive accounts.</i>');
                    $('#PopupInfo #check-status').removeClass('hidden').html(
                        '<i>This account number is not currently linked to any portal account.<br>' +
                        'You can create a new account or link to an existing one using the options below.</i>'
                    ).show();
                    $('#PopupInfo #create-account').show();
                    $('#PopupInfo #link-account').show();
                }

                if (userId) {
                    $.get('/accounts/user/' + userId, function (accounts) {
                        tbody.empty();
                        if (accounts.length > 0) {
                            accounts.forEach(function (acc) {
                                const accStatus = acc.status || 'Unknown';
                                const linked = accStatus === 'Linked';
                                tbody.append(
                                    '<tr class="hover:bg-gray-50 transition">' +
                                        '<td class="px-4 py-2 font-medium">' + (acc.account_no || '') + '</td>' +
                                        '<td class="px-4 py-2">' + (acc.customer || '') + '</td>' +
                                        '<td class="px-4 py-2"><span class="inline-block px-2 py-1 text-xs rounded-full ' +
                                            (linked ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600') +
                                        '">' + accStatus + '</span></td>' +
                                    '</tr>'
                                );
                            });
                        } else {
                            tbody.html('<tr><td colspan="3" class="text-center text-gray-500 py-4">No linked accounts found.</td></tr>');
                        }
                    });
                } else {
                    tbody.html('<tr><td colspan="3" class="text-center text-gray-500 py-4">No linked accounts found.</td></tr>');
                }

                const modal = document.querySelector('#PopupInfo');
                if (!modal) {
                    return;
                }
                if (typeof window.ulOpenModal === 'function' && modal.classList.contains('ul-modal')) {
                    window.ulOpenModal(modal);
                    return;
                }
                if (window.HSOverlay) {
                    HSOverlay.open(modal);
                }
            }

            document.addEventListener('click', function (event) {
                const opener = event.target.closest('[data-consumer-open]');
                if (!opener) {
                    return;
                }
                if (event.target.closest('.ul-no-row-click') && !event.target.closest('[data-consumer-open].ul-menu-item, button[data-consumer-open]')) {
                    return;
                }
                event.preventDefault();
                openConsumer(opener);
            });
        })();
    </script>
</x-app-layout>
