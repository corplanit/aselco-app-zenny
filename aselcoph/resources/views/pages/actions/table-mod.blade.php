{{-- Unified chrome for legacy DataTables pages --}}
@php
    $dtPlaceholder = $dtPlaceholder ?? 'Search…';
    $dtTableId = $dtTableId ?? null;
@endphp

<div class="ul-toolbar mb-3 ul-dt-chrome" data-dt-table="{{ $dtTableId }}">
    <div class="ul-search">
        <i class="bi bi-search ul-search-icon" aria-hidden="true"></i>
        <input
            type="search"
            class="ti-form-input ul-search-input ul-dt-search"
            placeholder="{{ $dtPlaceholder }}"
            aria-label="{{ $dtPlaceholder }}"
            autocomplete="off"
        >
    </div>
    <div class="ul-toolbar-actions">
        <div id="customLengthWrapper"></div>
        <div id="customSearchWrapper" class="hidden"></div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $("#selectAll").on("click", function() {
            $(".rowCheckbox").prop("checked", this.checked);
        });

        function boldNumbersInInfo() {
            let info = $('.dataTables_info').html();
            if (!info) return;
            info = info.replace(/(\d+)/g, '<strong>$1</strong>');
            $('.dataTables_info').html(info);
        }

        $(document).on('draw.dt', function() {
            boldNumbersInInfo();
        });

        // Wire unified search input to the nearest / configured DataTable
        $('.ul-dt-chrome').each(function() {
            const $chrome = $(this);
            const tableId = $chrome.data('dt-table');
            const $input = $chrome.find('.ul-dt-search');
            let debounce = null;

            $input.on('input', function() {
                const q = this.value;
                clearTimeout(debounce);
                debounce = setTimeout(function() {
                    let api = null;
                    if (tableId && $.fn.dataTable.isDataTable('#' + tableId)) {
                        api = $('#' + tableId).DataTable();
                    } else {
                        const $table = $chrome.closest('.box, .custom-box, .ul-dt-wrap').find('table.dataTable, table[id]').first();
                        if ($table.length && $.fn.dataTable.isDataTable($table)) {
                            api = $table.DataTable();
                        }
                    }
                    if (api) {
                        api.search(q).draw();
                    }
                }, 300);
            });

            $input.on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $(this).trigger('input');
                }
            });
        });
    });
</script>
