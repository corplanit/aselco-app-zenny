<?php

namespace Tests\Feature;

use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class UnifiedListUiTest extends TestCase
{
    public function test_list_query_normalizes_search_filters_and_sort(): void
    {
        $request = Request::create('/tickets', 'GET', [
            'search' => ' outage ',
            'status' => 'assigned',
            'sort' => 'sla_due_at',
            'dir' => 'asc',
            'per_page' => 50,
        ]);

        $list = ListQuery::from(
            $request,
            filterKeys: ['status', 'priority'],
            sortable: ['id' => 'id', 'sla_due_at' => 'sla_due_at'],
            defaultSort: 'id',
            defaultDir: 'desc',
            defaultPerPage: 25,
        );

        $this->assertSame('outage', $list['search']);
        $this->assertSame('assigned', $list['filters']['status']);
        $this->assertSame('sla_due_at', $list['sort']);
        $this->assertSame('asc', $list['dir']);
        $this->assertSame(50, $list['per_page']);
        $this->assertSame(2, $list['active_filter_count']);
    }

    public function test_unified_toolbar_and_table_components_render(): void
    {
        $toolbar = view('components.unified.toolbar', [
            'action' => '/tickets',
            'resetUrl' => '/tickets',
            'searchValue' => '',
            'searchPlaceholder' => 'Search ticket no., customer, description…',
            'activeFilterCount' => 2,
            'showFilter' => true,
            'showReset' => true,
        ])->render();

        $this->assertStringContainsString('ul-toolbar', $toolbar);
        $this->assertStringContainsString('Search ticket no.', $toolbar);
        $this->assertStringContainsString('ul-badge-count', $toolbar);

        $paginator = new LengthAwarePaginator([], 0, 25, 1, [
            'path' => '/tickets',
            'query' => [],
        ]);

        $table = view('components.unified.table', [
            'paginator' => $paginator,
            'hasFilters' => true,
            'resetUrl' => '/tickets',
            'emptyTitle' => 'No tickets in this queue.',
            'colspan' => 8,
        ])->render();

        $this->assertStringContainsString('ul-table', $table);
        $this->assertStringContainsString('No results match your current filters.', $table);
        $this->assertStringContainsString('Clear Filters', $table);
    }

    public function test_sort_url_toggles_direction(): void
    {
        $asc = ListQuery::sortUrl('/tickets', ['search' => 'x'], 'ticket_no', null, 'desc');
        $this->assertStringContainsString('sort=ticket_no', $asc);
        $this->assertStringContainsString('dir=asc', $asc);

        $desc = ListQuery::sortUrl('/tickets', ['search' => 'x'], 'ticket_no', 'ticket_no', 'asc');
        $this->assertStringContainsString('dir=desc', $desc);
    }

    public function test_rich_html_allows_formatting_and_strips_scripts(): void
    {
        $this->assertSame("Line 1<br>\nLine 2", \App\Support\TicketUi::richHtml("Line 1\nLine 2"));
        $this->assertSame('<b>Bold</b> and <i>italic</i>', \App\Support\TicketUi::richHtml('<b>Bold</b> and <i>italic</i>'));
        $this->assertSame('<b>safe</b>', \App\Support\TicketUi::richHtml('<b onclick="alert(1)">safe</b><script>alert(1)</script>'));
        $this->assertSame('<ul><li>Item</li></ul>', \App\Support\TicketUi::richHtml('<ul><li>Item</li></ul>'));
    }

    public function test_timeline_track_follows_path_instead_of_skipping_to_assigned(): void
    {
        $ticket = new \App\Models\Ticket(['status' => \App\Models\Ticket::STATUS_ASSIGNED]);
        $ticket->setRelation('statusHistory', collect());

        $track = \App\Support\TicketUi::timelineTrack($ticket);
        $byStatus = collect($track)->keyBy('status');

        $this->assertSame(\App\Support\TicketUi::timelinePath(), array_column($track, 'status'));
        $this->assertSame('done', $byStatus['new']['state']);
        $this->assertSame('done', $byStatus['endorsed']['state']);
        $this->assertSame('current', $byStatus['assigned']['state']);
        $this->assertSame('pending', $byStatus['in_progress']['state']);
        $this->assertSame('pending', $byStatus['closed']['state']);
        $this->assertArrayNotHasKey('escalated', $byStatus);
        $this->assertArrayNotHasKey('reopened', $byStatus);
    }
}
