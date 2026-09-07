<x-app-layout>
    <x-slot name="title">AI Ticket Insights</x-slot>
    <x-slot name="url_1">{"link": "/tickets", "text": "Tickets"}</x-slot>
    <x-slot name="url_2">{"link": "/tickets/ai", "text": "AI Insights"}</x-slot>
    <x-slot name="active">Analytics</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('workspace.department') }}#ticket-reports" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
            <i class="bi bi-bar-chart"></i>Ticket Reports
        </a>
        @can('tickets.view')
            <a href="{{ route('tickets.queue') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                <i class="bi bi-ticket-detailed"></i>Ticket Queue
            </a>
        @endcan
    </x-slot>

    @php
        $analyzed = (int) ($stats['analyzed_tickets'] ?? 0);
        $ok = (int) ($stats['analysis_ok'] ?? 0);
        $failed = (int) ($stats['analysis_failed'] ?? 0);
        $okRate = (float) ($stats['analysis_ok_rate'] ?? 0);
        $matchRate = (float) ($stats['category_match_rate'] ?? 0);
        $avgConfidence = (float) ($stats['avg_confidence'] ?? 0);
        $overrides = (int) ($stats['human_overrides'] ?? 0);
        $drafts = (int) ($stats['response_drafts'] ?? 0);
        $pending = (int) ($stats['drafts_pending_review'] ?? 0);
        $approved = (int) ($stats['drafts_approved'] ?? 0);
        $rejected = (int) ($stats['drafts_rejected'] ?? 0);
        $sent = (int) ($stats['drafts_sent'] ?? 0);
        $autoSend = (bool) ($stats['auto_send_enabled'] ?? false);
        $recent = collect($stats['recent'] ?? []);
        $healthTone = static function (float $rate): string {
            return $rate >= 80 ? 'lime' : ($rate >= 50 ? 'amber' : 'rose');
        };
        $sentimentOrder = ['urgent_distressed', 'highly_negative', 'negative', 'neutral', 'positive'];
        $priorityOrder = ['urgent', 'high', 'normal', 'low'];
        $sortKnown = static function (array $items, array $order): array {
            uksort($items, static function (string $a, string $b) use ($order): int {
                $left = array_search($a, $order, true);
                $right = array_search($b, $order, true);
                $left = $left === false ? 99 : $left;
                $right = $right === false ? 99 : $right;

                return $left <=> $right;
            });

            return $items;
        };
        $sentiments = $sortKnown($stats['sentiment_distribution'] ?? [], $sentimentOrder);
        $priorities = $sortKnown($stats['priority_recommendations'] ?? [], $priorityOrder);
        $sentimentTotal = max(1, array_sum($sentiments));
        $priorityTotal = max(1, array_sum($priorities));
        $kpis = [
            ['Analyzed tickets', number_format($analyzed), 'bi-stars', 'is-indigo'],
            ['Soft failures', number_format($failed), 'bi-exclamation-triangle', 'is-rose'],
            ['Human overrides', number_format($overrides), 'bi-person-gear', 'is-amber'],
            ['Drafts pending', number_format($pending), 'bi-chat-quote', 'is-sky'],
        ];
        $pipeline = [
            ['Pending review', $pending, 'bi-hourglass-split', 'is-amber'],
            ['Approved', $approved, 'bi-check2-circle', 'is-lime'],
            ['Rejected', $rejected, 'bi-x-circle', 'is-rose'],
            ['Sent', $sent, 'bi-send', 'is-indigo'],
        ];
    @endphp

    <div class="ai-dash">
        <div class="box ul-card ai-layout">
            <div class="ai-layout-head">
                <div class="ai-layout-brand">
                    <span class="ai-layout-orb {{ $pending > 0 ? 'is-live' : '' }}" aria-hidden="true">
                        <i class="bi bi-stars"></i>
                    </span>
                    <div>
                        <div class="ai-layout-title">
                            AI Ticket Insights
                            <x-unified.badge tone="slate" icon="bi-shield-lock">Advisory only</x-unified.badge>
                            @if($autoSend)
                                <x-unified.badge tone="amber" icon="bi-lightning">Auto-send configured</x-unified.badge>
                            @else
                                <x-unified.badge tone="slate" icon="bi-pause-circle">Auto-send off</x-unified.badge>
                            @endif
                        </div>
                        <p class="ai-layout-kicker">
                            Model health and draft review for staff. Routing, SLA, and authorization stay with the ticket system.
                            Customer replies are never sent without CSR approval.
                        </p>
                    </div>
                </div>
                <div class="ai-layout-head-side">
                    <div class="ai-layout-actions">
                        <a href="{{ route('workspace.department') }}#ticket-reports" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                            <i class="bi bi-bar-chart"></i>Reports
                        </a>
                    </div>
                </div>
            </div>

            <div class="ai-health">
                <div class="ai-meter is-{{ $healthTone($okRate) }}">
                    <div class="ai-meter-top">
                        <span>Analysis OK</span>
                        <strong>{{ $okRate }}%</strong>
                    </div>
                    <div class="ai-meter-track" aria-hidden="true"><span style="width: {{ min(100, $okRate) }}%"></span></div>
                    <div class="ai-meter-meta">{{ number_format($ok) }} of {{ number_format($analyzed) }} succeeded</div>
                </div>
                <div class="ai-meter is-{{ $healthTone($matchRate) }}">
                    <div class="ai-meter-top">
                        <span>Category match</span>
                        <strong>{{ $matchRate }}%</strong>
                    </div>
                    <div class="ai-meter-track" aria-hidden="true"><span style="width: {{ min(100, $matchRate) }}%"></span></div>
                    <div class="ai-meter-meta">Recommended category vs submitted</div>
                </div>
                <div class="ai-meter is-{{ $healthTone($avgConfidence) }}">
                    <div class="ai-meter-top">
                        <span>Avg confidence</span>
                        <strong>{{ $avgConfidence }}%</strong>
                    </div>
                    <div class="ai-meter-track" aria-hidden="true"><span style="width: {{ min(100, $avgConfidence) }}%"></span></div>
                    <div class="ai-meter-meta">Successful analyses only</div>
                </div>
            </div>

            <div class="ai-dash-kpis">
                <div class="ul-kpi-grid is-four">
                    @foreach($kpis as $tile)
                        <div class="ul-kpi {{ $tile[3] }}">
                            <span class="ul-kpi-icon"><i class="bi {{ $tile[2] }}"></i></span>
                            <span class="ul-kpi-copy">
                                <span class="ul-kpi-value">{{ $tile[1] }}</span>
                                <span class="ul-kpi-label">{{ $tile[0] }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="ai-layout-grid">
                <section class="ai-pane">
                    <div class="ai-pane-head">
                        <span class="ai-pane-icon"><i class="bi bi-emoji-smile"></i></span>
                        <div>
                            <div class="ai-pane-title">Sentiment</div>
                            <div class="ai-pane-sub">How customers sound across analyzed tickets</div>
                        </div>
                    </div>
                    @if($sentiments === [])
                        <div class="ai-empty is-compact">
                            <i class="bi bi-stars"></i>
                            <div class="ai-empty-title">No sentiment data yet</div>
                            <p>Analyses will fill this after tickets are created or re-analyzed.</p>
                        </div>
                    @else
                        <div class="ai-meters">
                            @foreach($sentiments as $label => $total)
                                @php $tone = \App\Support\TicketUi::sentimentTone($label); @endphp
                                <div class="ai-meter is-{{ $tone }}">
                                    <div class="ai-meter-top">
                                        <span>{{ ucfirst(str_replace('_', ' ', $label)) }}</span>
                                        <strong>{{ number_format((int) $total) }}</strong>
                                    </div>
                                    <div class="ai-meter-track" aria-hidden="true">
                                        <span style="width: {{ min(100, round(((int) $total / $sentimentTotal) * 100, 1)) }}%"></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="ai-pane">
                    <div class="ai-pane-head">
                        <span class="ai-pane-icon"><i class="bi bi-bar-chart"></i></span>
                        <div>
                            <div class="ai-pane-title">Priority recommendations</div>
                            <div class="ai-pane-sub">What the model would escalate first</div>
                        </div>
                    </div>
                    @if($priorities === [])
                        <div class="ai-empty is-compact">
                            <i class="bi bi-stars"></i>
                            <div class="ai-empty-title">No priority data yet</div>
                            <p>Recommended priorities will appear here after analysis runs.</p>
                        </div>
                    @else
                        <div class="ai-meters">
                            @foreach($priorities as $label => $total)
                                @php $tone = \App\Support\TicketUi::priorityTone($label); @endphp
                                <div class="ai-meter is-{{ $tone }}">
                                    <div class="ai-meter-top">
                                        <span>{{ strtoupper($label) }}</span>
                                        <strong>{{ number_format((int) $total) }}</strong>
                                    </div>
                                    <div class="ai-meter-track" aria-hidden="true">
                                        <span style="width: {{ min(100, round(((int) $total / $priorityTotal) * 100, 1)) }}%"></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>

            <section class="ai-section">
                <div class="ai-pane-head">
                    <span class="ai-pane-icon"><i class="bi bi-chat-quote"></i></span>
                    <div>
                        <div class="ai-pane-title">Draft review</div>
                        <div class="ai-pane-sub">{{ number_format($drafts) }} generated replies · never auto-sent without approval</div>
                    </div>
                </div>
                <div class="ai-pipe">
                    @foreach($pipeline as $step)
                        <div class="ai-pipe-step {{ $step[3] }}">
                            <span class="ai-pipe-icon"><i class="bi {{ $step[2] }}"></i></span>
                            <span class="ai-pipe-value">{{ number_format($step[1]) }}</span>
                            <span class="ai-pipe-label">{{ $step[0] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <x-unified.table
            class="ul-ai-table"
            title="Recent analyses"
            :items="$recent"
            :colspan="7"
            empty-title="No analyses yet."
            empty-text="Create or re-analyze a ticket to see AI recommendations here."
        >
            <x-slot:headerActions>
                @can('tickets.view')
                    <a href="{{ route('tickets.queue') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                        <i class="bi bi-box-arrow-up-right"></i>Queue
                    </a>
                @endcan
            </x-slot:headerActions>
            <x-slot:head>
                <th class="ul-row-num">#</th>
                <th>Ticket</th>
                <th>Sentiment</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Analyzed</th>
                <th class="text-end ul-col-actions">Actions</th>
            </x-slot:head>
            @foreach($recent as $row)
                @php
                    $href = route('tickets.show', $row['ticket_id']);
                    $analyzedAt = ! empty($row['analyzed_at'])
                        ? \Illuminate\Support\Carbon::parse($row['analyzed_at'])->timezone('Asia/Manila')
                        : null;
                    $ticketMeta = trim(implode(' · ', array_filter([
                        $row['recommended_category'] ?: null,
                        $row['confidence'] !== null ? $row['confidence'].'%' : null,
                    ])));
                @endphp
                <x-unified.row :href="$href">
                    <td class="ul-row-num">{{ $loop->iteration }}</td>
                    <x-unified.td-primary :href="$href" :text="$row['ticket_no'] ?: 'Ticket #'.$row['ticket_id']">
                        <x-slot:meta>{{ $ticketMeta !== '' ? $ticketMeta : '—' }}</x-slot:meta>
                    </x-unified.td-primary>
                    <td>
                        @if(! empty($row['sentiment']))
                            <x-unified.badge :tone="\App\Support\TicketUi::sentimentTone($row['sentiment'])">
                                {{ str_replace('_', ' ', $row['sentiment']) }}
                            </x-unified.badge>
                        @else
                            <span class="ul-empty">—</span>
                        @endif
                    </td>
                    <td>
                        @if(! empty($row['recommended_priority']))
                            <x-unified.badge
                                :tone="\App\Support\TicketUi::priorityTone($row['recommended_priority'])"
                                icon="bi-bar-chart"
                            >{{ strtoupper($row['recommended_priority']) }}</x-unified.badge>
                        @else
                            <span class="ul-empty">—</span>
                        @endif
                    </td>
                    <td>
                        @if(! empty($row['human_override']))
                            <x-unified.badge tone="amber" icon="bi-person-gear">Override</x-unified.badge>
                        @elseif(! empty($row['ok']))
                            <x-unified.badge
                                :tone="! empty($row['category_matches_submitted']) ? 'lime' : 'sky'"
                                :icon="! empty($row['category_matches_submitted']) ? 'bi-check2' : 'bi-search'"
                            >{{ ! empty($row['category_matches_submitted']) ? 'OK' : 'Review' }}</x-unified.badge>
                        @else
                            <x-unified.badge tone="rose" icon="bi-exclamation-triangle">Failed</x-unified.badge>
                        @endif
                    </td>
                    <td class="ul-date">
                        @if($analyzedAt)
                            {{ $analyzedAt->diffForHumans() }}
                            <div class="ul-date-meta">{{ $analyzedAt->format('M d, Y h:i A') }}</div>
                        @else
                            <span class="ul-empty">—</span>
                        @endif
                    </td>
                    <x-unified.actions :view-url="$href" />
                </x-unified.row>
            @endforeach
        </x-unified.table>
    </div>
</x-app-layout>
