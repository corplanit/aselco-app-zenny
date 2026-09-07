<x-app-layout>
    <x-slot name="title">Import users</x-slot>
    <x-slot name="url_1">{"link": "{{ route('access.users.index') }}", "text": "Users"}</x-slot>
    <x-slot name="active">Import</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('access.users.index') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
            <i class="bi bi-arrow-left"></i>Users
        </a>
    </x-slot>
    @include('pages.staff.access._nav')

    <div class="box ul-card mb-6">
        <div class="box-header ul-card-header"><div class="box-title ul-card-title">Upload CSV</div></div>
        <form method="POST" action="{{ route('access.users.import.preview') }}" enctype="multipart/form-data">
            @csrf
            <div class="box-body">
                <x-unified.note title="CSV format">
                    Required columns: name, email, username, role, department.
                </x-unified.note>
                <x-unified.dropzone name="file" accept=".csv,text/csv" :required="true" hint="CSV only. Preview before committing valid rows." />
            </div>
            <div class="ul-form-actions">
                <button class="ti-btn ti-btn-sm ul-btn ul-btn-view"><i class="bi bi-eye"></i>Preview</button>
            </div>
        </form>
    </div>

    @if($preview)
        <x-unified.table title="Import preview" :items="$preview" :colspan="7">
            <x-slot:head>
                <th class="ul-row-num">#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Department</th>
                <th>Result</th>
            </x-slot:head>
            @foreach($preview as $row)
                <tr>
                    <td class="ul-row-num">{{ $row['row'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['email'] }}</td>
                    <td>{{ $row['role'] }}</td>
                    <td>{{ $row['department'] }}</td>
                    <td>
                        @if($row['valid'])
                            <x-unified.badge tone="lime" icon="bi-check-circle">Ready</x-unified.badge>
                        @else
                            <x-unified.badge tone="rose" icon="bi-exclamation-circle">{{ implode(', ', $row['errors']) }}</x-unified.badge>
                        @endif
                    </td>
                </tr>
            @endforeach
            <x-slot:footer>
                <form method="POST" action="{{ route('access.users.import.commit') }}">
                    @csrf
                    <button
                        class="ti-btn ti-btn-sm ul-btn ul-btn-view"
                        data-ul-confirm="Import the valid rows into user accounts."
                        data-ul-confirm-verb="Import"
                        data-ul-confirm-icon="bi-upload"
                    >
                        <i class="bi bi-check2"></i>Commit valid rows
                    </button>
                </form>
            </x-slot:footer>
        </x-unified.table>
    @endif
</x-app-layout>
