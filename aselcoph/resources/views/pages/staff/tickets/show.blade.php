<x-app-layout>
    <x-slot name="title">{{ $ticket->ticket_no }}</x-slot>
    <x-slot name="url_1">{"link": "/tickets", "text": "Tickets"}</x-slot>
    <x-slot name="url_2">{"link": "/tickets/{{ $ticket->id }}", "text": "Detail"}</x-slot>
    <x-slot name="active">{{ \App\Support\TicketUi::statusLabel($ticket->status) }}</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('tickets.queue') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
            <i class="bi bi-arrow-left"></i>Queue
        </a>
        <a href="{{ route('tickets.escalations') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
            <i class="bi bi-signpost-split"></i>Escalations
        </a>
        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-more" data-ul-modal="#ticket-attach-modal">
            <i class="bi bi-paperclip"></i>Attachments
            @if($ticket->attachments->isNotEmpty())
                ({{ $ticket->attachments->count() }})
            @endif
        </button>
        @if($canAssign)
            <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-view" data-ul-modal="#ticket-assign-modal">
                <i class="bi bi-person-check"></i>Assign support
            </button>
        @endif
        @php
            $viewer = Auth::user();
            $highlightNextStep = $viewer
                && ! $viewer->isTicketAdmin()
                && (
                    (int) $ticket->assigned_to === (int) $viewer->id
                    || ($viewer->user_type ?? '') === 'support'
                );
        @endphp
        @if($canStartProgress)
            <form method="POST" action="{{ route('tickets.start', $ticket->id) }}" class="inline">
                @csrf
                <button
                    type="submit"
                    class="ti-btn ti-btn-sm ul-btn ul-btn-view{{ $highlightNextStep ? ' ul-btn-pulse' : '' }}"
                >
                    <i class="bi bi-play-circle"></i>Start work
                </button>
            </form>
        @endif
        @if($canLogActionNow)
            <button
                type="button"
                class="ti-btn ti-btn-sm ul-btn ul-btn-edit"
                data-ul-modal="#ticket-action-modal"
            >
                <i class="bi bi-clipboard-check"></i>Log action taken
            </button>
        @endif
        @if($canEscalate && !in_array($ticket->status, ['closed', 'resolved']))
            <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-danger" data-ul-modal="#ticket-escalate-modal">
                <i class="bi bi-exclamation-triangle"></i>Escalate
            </button>
        @endif
    </x-slot>

    @if(session('success')) <div class="alert alert-success mb-4">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger mb-4">{{ session('error') }}</div> @endif

    @php
        $overdue = $ticket->sla_due_at && $ticket->sla_due_at->isPast()
            && !in_array($ticket->status, ['closed', 'resolved']);
    @endphp

    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-8 col-span-12">
            <div class="box ul-card mb-6">
                <div class="box-header ul-card-header flex flex-wrap items-center justify-between gap-3">
                    <div class="td-hero-title">
                        <div class="box-title ul-card-title mb-0">{{ $ticket->ticket_no }}</div>
                        <div class="td-hero-badges">
                            <x-unified.badge
                                :tone="\App\Support\TicketUi::statusTone($ticket->status)"
                                :icon="\App\Support\TicketUi::statusIcon($ticket->status)"
                            >{{ \App\Support\TicketUi::statusLabel($ticket->status) }}</x-unified.badge>
                            @if($overdue)
                                <x-unified.badge tone="danger" icon="bi-exclamation-octagon">Overdue</x-unified.badge>
                            @endif
                        </div>
                        </div>
                        </div>
                <div class="box-body p-0">
                    <div class="ul-table-wrap">
                        <table class="table ul-table td-kv-table mb-0">
                            <tbody>
                                <tr>
                                    <th>Customer</th>
                                    <td>
                                        <div class="font-semibold">{{ $ticket->customer?->name ?: '—' }}</div>
                                        <div class="ul-date-meta">{{ $ticket->customer?->email }}</div>
                                    </td>
                                    <th>Category</th>
                                    <td>
                                        <x-unified.badge
                                            :tone="\App\Support\TicketUi::categoryTone($ticket->category)"
                                            :icon="\App\Support\TicketUi::categoryIcon($ticket->category)"
                                        >{{ \App\Support\TicketUi::categoryLabel($ticket->category) }}</x-unified.badge>
                                        <div class="ul-date-meta">Routes to {{ $ticket->category?->department_code ?: '—' }} · SLA {{ $ticket->category?->sla_minutes ?? '—' }} min</div>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Channel</th>
                                    <td>
                                        <x-unified.badge tone="slate" :icon="\App\Support\TicketUi::channelIcon($ticket->channel)">
                                            {{ strtoupper($ticket->channel) }}
                                        </x-unified.badge>
                                    </td>
                                    <th>Priority</th>
                                    <td>
                                        <x-unified.badge
                                            :tone="\App\Support\TicketUi::priorityTone($ticket->priority)"
                                            icon="bi-bar-chart"
                                        >{{ ucfirst($ticket->priority ?: 'normal') }}</x-unified.badge>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Assigned dept</th>
                                    <td>{{ $ticket->assigned_department ?: '—' }}</td>
                                    <th>Assigned to</th>
                                    <td>{{ $ticket->assignee?->name ?: 'Unassigned' }}</td>
                                </tr>
                                <tr>
                                    <th>SLA due</th>
                                    <td>
                                        @if($ticket->sla_due_at)
                            <div class="{{ $overdue ? 'text-danger font-semibold' : '' }}">
                                    {{ $ticket->sla_due_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}
                                            </div>
                                            <div class="ul-date-meta">{{ $overdue ? 'Overdue' : $ticket->sla_due_at->diffForHumans() }}</div>
                                @else
                                            <span class="ul-empty">—</span>
                                @endif
                                    </td>
                                    <th>Status</th>
                                    <td>
                                        <x-unified.badge
                                            :tone="\App\Support\TicketUi::statusTone($ticket->status)"
                                            :icon="\App\Support\TicketUi::statusIcon($ticket->status)"
                                        >{{ \App\Support\TicketUi::statusLabel($ticket->status) }}</x-unified.badge>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Description</th>
                                    <td colspan="3" class="td-kv-inline">
                                        <div class="ul-expand" data-ul-expand data-ul-expand-max="44">
                                            <div class="ul-expand-body">
                                                <x-unified.rich :value="$ticket->description" />
                            </div>
                                            <button type="button" class="ul-expand-toggle" hidden>Show more</button>
                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @php
                $aiConfidence = $aiAnalysis?->confidence !== null ? (int) round(((float) $aiAnalysis->confidence) * 100) : null;
                $aiConfidenceTone = $aiConfidence === null ? 'slate' : ($aiConfidence >= 80 ? 'lime' : ($aiConfidence >= 50 ? 'amber' : 'rose'));
                $aiNextAction = $aiAnalysis?->recommended_next_action
                    ? ucfirst(str_replace('_', ' ', $aiAnalysis->recommended_next_action))
                    : '—';
            @endphp
            <div class="box ul-card ai-layout mb-6">
                <div class="ai-layout-head">
                    <div class="ai-layout-brand">
                        <span class="ai-layout-orb" aria-hidden="true"><i class="bi bi-stars"></i></span>
                        <div>
                            <div class="ai-layout-title">
                                AI assistance
                                <x-unified.badge tone="slate" icon="bi-shield-lock">Internal only</x-unified.badge>
                            </div>
                            <p class="ai-layout-kicker">Advisory only. Routing, SLA, and permissions stay with staff. Customer description is never overwritten.</p>
                        </div>
                    </div>
                    <div class="ai-layout-head-side">
                        <div class="ai-layout-actions">
                            <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-view" data-ul-modal="#ticket-ai-replies-modal">
                                <i class="bi bi-chat-quote"></i>Suggested replies
                                @if($aiDrafts->count())
                                    <span class="ul-badge-count">{{ $aiDrafts->count() }}</span>
                                @endif
                            </button>
                    <form method="POST" action="{{ route('tickets.ai.analyze', $ticket->id) }}">
                        @csrf
                                <button class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                                    <i class="bi bi-arrow-repeat"></i>Re-analyze
                                </button>
                    </form>
                        </div>
                    </div>
                </div>
                @if($aiAnalysis)
                    <div class="ai-conf is-{{ $aiConfidenceTone }}">
                        <div class="ai-conf-top">
                            <span>Confidence</span>
                            <strong>{{ $aiConfidence !== null ? $aiConfidence.'%' : '—' }}</strong>
                        </div>
                        <div class="ai-conf-track" aria-hidden="true">
                            <span style="width: {{ $aiConfidence !== null ? $aiConfidence : 0 }}%"></span>
                        </div>
                        <div class="ai-conf-meta">
                            {{ $aiAnalysis->source ?: 'model' }}
                            · {{ optional($aiAnalysis->analyzed_at)->timezone('Asia/Manila')->format('M d, Y h:i A') ?: '—' }}
                        </div>
                    </div>
                @endif

                @if($aiAnalysis && ! $aiAnalysis->ok)
                    <div class="ai-banner">
                        <i class="bi bi-exclamation-triangle"></i>
                        Analysis soft-failed ({{ $aiAnalysis->error_code ?: 'unknown' }}). Review the suggestions before acting.
                    </div>
                @endif

                    @if($aiAnalysis)
                    <div class="ai-layout-grid">
                        <section class="ai-pane">
                            <div class="ai-pane-head">
                                <span class="ai-pane-icon"><i class="bi bi-search"></i></span>
                                <div>
                                    <div class="ai-pane-title">Recommendations</div>
                                    <div class="ai-pane-sub">Category, priority, sentiment</div>
                                </div>
                            </div>
                            <div class="ai-tiles">
                                <div class="ai-tile">
                                    <div class="ai-tile-label"><i class="bi bi-tag"></i> Category</div>
                                    <div class="ai-tile-value">{{ $aiAnalysis->recommendedCategory?->name ?: '—' }}</div>
                                    <x-unified.badge :tone="$aiAnalysis->category_matches_submitted ? 'lime' : 'amber'" :icon="$aiAnalysis->category_matches_submitted ? 'bi-check-circle' : 'bi-exclamation-circle'">
                                        {{ $aiAnalysis->category_matches_submitted ? 'Matches submitted' : 'Differs — review' }}
                                    </x-unified.badge>
                                </div>
                                <div class="ai-tile">
                                    <div class="ai-tile-label"><i class="bi bi-bar-chart"></i> Priority</div>
                                    <div class="ai-tile-value">{{ ucfirst($aiAnalysis->recommended_priority ?: '—') }}</div>
                                    <x-unified.badge :tone="\App\Support\TicketUi::priorityTone($aiAnalysis->recommended_priority)" icon="bi-bar-chart">
                                        {{ strtoupper($aiAnalysis->recommended_priority ?: '—') }}
                                    </x-unified.badge>
                                </div>
                                <div class="ai-tile">
                                    <div class="ai-tile-label"><i class="bi bi-emoji-smile"></i> Sentiment</div>
                                    <div class="ai-tile-value">{{ ucfirst(str_replace('_', ' ', $aiAnalysis->sentiment ?: '—')) }}</div>
                                    <x-unified.badge :tone="\App\Support\TicketUi::sentimentTone($aiAnalysis->sentiment)">
                                        {{ str_replace('_', ' ', $aiAnalysis->sentiment ?: '—') }}
                                    </x-unified.badge>
                                </div>
                            </div>
                        </section>

                        <section class="ai-pane">
                            <div class="ai-pane-head">
                                <span class="ai-pane-icon"><i class="bi bi-diagram-3"></i></span>
                                <div>
                                    <div class="ai-pane-title">Routing</div>
                                    <div class="ai-pane-sub">Department, assignee, next step</div>
                                </div>
                            </div>
                            <div class="ai-tiles">
                                <div class="ai-tile">
                                    <div class="ai-tile-label"><i class="bi bi-diagram-3"></i> Department</div>
                                    <div class="ai-tile-value">{{ $aiAnalysis->suggested_department ?: '—' }}</div>
                                    @if($aiAnalysis->suggested_department)
                                        <x-unified.badge
                                            :tone="\App\Support\TicketUi::departmentTone($aiAnalysis->suggested_department)"
                                            :icon="\App\Support\TicketUi::departmentIcon($aiAnalysis->suggested_department)"
                                        >{{ $aiAnalysis->suggested_department }}</x-unified.badge>
                                    @endif
                                </div>
                                <div class="ai-tile">
                                    <div class="ai-tile-label"><i class="bi bi-person-check"></i> Assignee</div>
                                    <div class="ai-tile-value">{{ $aiAnalysis->suggestedAssignee?->name ?: '—' }}</div>
                                </div>
                                <div class="ai-tile">
                                    <div class="ai-tile-label"><i class="bi bi-signpost"></i> Next action</div>
                                    <div class="ai-tile-value">{{ $aiNextAction }}</div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <section class="ai-section">
                        <div class="ai-pane-head">
                            <span class="ai-pane-icon"><i class="bi bi-lightbulb"></i></span>
                            <div>
                                <div class="ai-pane-title">Summary</div>
                                <div class="ai-pane-sub">Internal note for staff</div>
                            </div>
                            </div>
                        <div class="ai-insight">
                            <div class="ai-insight-body">
                                <x-unified.rich :value="$aiAnalysis->summary" />
                            </div>
                        </div>
                    </section>

                    @php
                        $latestDraft = $aiDrafts->first();
                        $analyzeResult = $aiAnalysis->suggested_response;
                        $resultBody = $latestDraft?->edited_body ?: $latestDraft?->draft ?: $analyzeResult;
                    @endphp
                    <section class="ai-section">
                        <div class="ai-pane-head">
                            <span class="ai-pane-icon"><i class="bi bi-stars"></i></span>
                            <div>
                                <div class="ai-pane-title">Analyze / generate result</div>
                                <div class="ai-pane-sub">
                                    @if($latestDraft)
                                        Latest generated reply · {{ str_replace('_', ' ', $latestDraft->status) }}
                                    @else
                                        Latest analyze output
                            @endif
                                </div>
                            </div>
                        </div>
                        @if(filled($resultBody))
                            <div class="ai-result">
                                <div class="ai-insight-body">
                                    <x-unified.rich :value="$resultBody" />
                                </div>
                            </div>
                        @else
                            <x-unified.note tone="slate" icon="bi-chat-dots" title="No generated result yet">
                                Use Suggested replies to compose a draft. The latest analyze or generated reply will show here.
                            </x-unified.note>
                        @endif
                        @if($aiAnalysis->recommended_priority && $aiAnalysis->recommended_priority !== $ticket->priority)
                            <form method="POST" action="{{ route('tickets.ai.apply-priority', $ticket->id) }}" class="ai-apply">
                                @csrf
                                <div class="ai-apply-copy">
                                    <strong>Priority differs</strong>
                                    Ticket is {{ ucfirst($ticket->priority ?: 'normal') }}. AI recommends {{ ucfirst($aiAnalysis->recommended_priority) }}.
                                </div>
                                <input type="text" name="reason" class="ti-form-input" placeholder="Override reason (optional)">
                                <button class="ti-btn ti-btn-sm ul-btn ul-btn-view">Apply AI priority</button>
                            </form>
                        @endif
                    </section>
                    @else
                    <div class="ai-empty">
                        <i class="bi bi-stars"></i>
                        <div class="ai-empty-title">No analysis yet</div>
                        <p>Use Re-analyze, or wait for auto-analysis after the ticket is created.</p>
                                    </div>
                            @endif
                        </div>
        </div>

        <div class="xl:col-span-4 col-span-12 space-y-6">
            <div class="box ul-card">
                <div class="box-header ul-card-header">
                    <div class="box-title ul-card-title">
                        Timeline
                        <a href="{{ route('access.assignments.history', $ticket->id) }}" class="text-xs font-semibold">History</a>
                        <x-unified.badge
                            :tone="\App\Support\TicketUi::statusTone($ticket->status)"
                            :icon="\App\Support\TicketUi::statusIcon($ticket->status)"
                        >{{ \App\Support\TicketUi::statusLabel($ticket->status) }}</x-unified.badge>
                    </div>
                </div>
                <div class="box-body">
                    @php
                        $track = \App\Support\TicketUi::timelineTrack($ticket, $timeline);
                        $verifyEventKey = null;
                        if ($canLogFeedback && $ticket->status === 'awaiting_feedback') {
                            foreach ($track as $stepIndex => $step) {
                                foreach ($step['events'] as $eventIndex => $event) {
                                    if (($event['kind'] ?? '') === 'status' && ($event['status'] ?? '') === 'awaiting_feedback') {
                                        $verifyEventKey = $stepIndex.'-'.$eventIndex;
                                    }
                                }
                            }
                        }
                    @endphp
                    <ol class="td-track">
                        @foreach($track as $stepIndex => $step)
                            <li class="td-track-step is-{{ $step['state'] }} is-{{ $step['tone'] }}">
                                <span class="td-track-mark" aria-hidden="true">
                                    <i class="bi {{ $step['icon'] }}"></i>
                                </span>
                                <div class="td-track-copy">
                                    <div class="td-track-title">
                                        {{ $step['label'] }}
                                        @if($step['state'] === 'current')
                                            <x-unified.badge :tone="$step['tone']">Now</x-unified.badge>
                                        @elseif($step['state'] === 'pending')
                                            <span class="td-track-pending">Pending</span>
                                        @endif
                                    </div>
                                    @if($step['state'] !== 'pending')
                                        <div class="ul-date-meta">
                                            @if($step['at'])
                                                {{ $step['at']->timezone('Asia/Manila')->format('M d, Y h:i A') }}
                                                · {{ $step['actor'] ?: 'System' }}
                                            @else
                                                Reached
                                            @endif
                                        </div>
                                        @foreach($step['events'] as $eventIndex => $item)
                                            @if(($item['kind'] ?? '') !== 'status' || filled($item['body'] ?? null))
                                                @php
                                                    $canVerifyHere = $verifyEventKey === $stepIndex.'-'.$eventIndex;
                                                @endphp
                                                <div class="td-track-event{{ $canVerifyHere ? ' has-verify' : '' }}">
                                                    <div class="td-track-event-title">{{ $item['title'] }}</div>
                                                    @if(!empty($item['body']))
                                                        @if(($item['kind'] ?? '') === 'action')
                                                            <div class="ul-expand" data-ul-expand data-ul-expand-max="44">
                                                                <div class="ul-expand-body">
                                                                    <x-unified.rich class="text-sm mb-0" :value="$item['body']" />
                                                                </div>
                                                                <button type="button" class="ul-expand-toggle" hidden>Show more</button>
                                                            </div>
                                                        @else
                                                            <x-unified.rich class="text-sm mb-0" :value="$item['body']" />
                                                        @endif
                                                    @endif
                                                    @if($canVerifyHere)
                                                        <button
                                                            type="button"
                                                            class="td-verify-btn"
                                                            data-ul-modal="#ticket-verify-modal"
                                                        >
                                                            <i class="bi bi-patch-check" aria-hidden="true"></i>
                                                            <span>Verify</span>
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif
                                        @endforeach
                                    @else
                                        <div class="td-track-hint">Not reached yet</div>
                                @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            @if($canClose)
                <div class="box ul-card">
                    <div class="box-header ul-card-header"><div class="box-title ul-card-title">Close ticket</div></div>
                <div class="box-body">
                        <x-unified.note tone="lime" icon="bi-check-circle" title="Ready to close" class="mb-3">
                            Verification confirmed the concern is resolved and no further action is needed.
                        </x-unified.note>
                        <form method="POST" action="{{ route('tickets.close', $ticket->id) }}">
                        @csrf
                            <input type="text" name="remarks" class="ti-form-input mb-2" placeholder="Close remarks (optional)">
                            <button class="ti-btn ti-btn-success w-full">Close ticket</button>
                    </form>
                    </div>
                </div>
            @else
                <div class="box ul-card">
                    <div class="box-header ul-card-header">
                        <div class="box-title ul-card-title">
                            Close ticket
                            <x-unified.badge tone="slate" icon="bi-lock">Disabled</x-unified.badge>
                        </div>
                    </div>
                    <div class="box-body">
                        <x-unified.note tone="amber" icon="bi-lock" title="Close is disabled">
                            Close stays unavailable until verification confirms the concern is resolved and no further action is needed.
                        </x-unified.note>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div id="ticket-attach-modal" class="ul-modal" hidden>
        <div class="ul-modal-backdrop" data-ul-modal-close></div>
        <div class="ul-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ticket-attach-title">
            <div class="ul-modal-header">
                <span class="ul-modal-icon ul-badge is-indigo"><i class="bi bi-paperclip" aria-hidden="true"></i></span>
                <div class="ul-modal-copy">
                    <h6 id="ticket-attach-title">Attachments</h6>
                    <p>Download existing files or upload a new attachment.</p>
                </div>
                <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="ul-modal-body">
                @forelse($ticket->attachments as $file)
                    <div class="td-file">
                        <div>
                            <div class="font-semibold text-sm">{{ strtoupper($file->file_type) }}</div>
                            <div class="ul-date-meta">{{ number_format($file->file_size / 1024, 1) }} KB · {{ optional($file->uploaded_at)->timezone('Asia/Manila')->format('M d, h:i A') }}</div>
                        </div>
                        <a href="{{ route('tickets.attachments.download', $file->id) }}" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                            <i class="bi bi-download"></i>
                        </a>
                    </div>
                @empty
                    <p class="text-textmuted text-sm mb-0">No attachments yet.</p>
                @endforelse
                <form method="POST" action="{{ route('tickets.attach', $ticket->id) }}" enctype="multipart/form-data" class="ul-drop-form mt-3">
                    @csrf
                    <x-unified.dropzone name="attachment" required />
                    <div class="ul-modal-footer">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>
                            <i class="bi bi-x-lg"></i>Cancel
                        </button>
                        <button class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                            <i class="bi bi-upload"></i>Upload
                        </button>
                    </div>
                </form>
                </div>
            </div>
        </div>

    @if($canAssign)
        <div id="ticket-assign-modal" class="ul-modal" hidden>
            <div class="ul-modal-backdrop" data-ul-modal-close></div>
            <div class="ul-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ticket-assign-title">
                <div class="ul-modal-header">
                    <span class="ul-modal-icon ul-badge is-indigo"><i class="bi bi-person-check" aria-hidden="true"></i></span>
                    <div class="ul-modal-copy">
                        <h6 id="ticket-assign-title">Assign support</h6>
                        <p>Pick a department, then assign a support account by department or role.</p>
                    </div>
                    <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('tickets.reassign', $ticket->id) }}" id="ticket-assign-form" class="ul-modal-body">
                            @csrf
                    <label class="ti-form-label">Department</label>
                    <select name="assigned_department" class="ti-form-select mb-3" required>
                        @foreach($departments as $dept)
                            <option value="{{ $dept }}" @selected($dept === $ticket->assigned_department)>
                                {{ \App\Support\TicketUi::departmentOptionLabel($dept) }}
                            </option>
                        @endforeach
                    </select>
                    <label class="ti-form-label">Filter support by role</label>
                    <select name="assignee_role_filter" class="ti-form-select mb-3">
                        <option value="">All support roles (Show every assignable support account)</option>
                        @foreach($assigneeRoles as $role)
                            <option value="{{ $role }}">{{ \App\Support\TicketUi::roleOptionLabel($role) }}</option>
                        @endforeach
                    </select>
                    <label class="ti-form-label">Assign to</label>
                    <select name="assigned_to" class="ti-form-select mb-3">
                        <option value="">Auto (round-robin in department)</option>
                        @foreach($assignees as $person)
                            <option
                                value="{{ $person->id }}"
                                data-dept="{{ $person->department_code }}"
                                data-role="{{ $person->role }}"
                                @selected((int) $ticket->assigned_to === (int) $person->id)
                            >
                                {{ $person->name }}
                                · {{ $person->role ?: 'staff' }}
                                · {{ $person->department_code ?: 'No dept' }}
                            </option>
                        @endforeach
                    </select>
                    <label class="ti-form-label">Remarks</label>
                    <input type="text" name="remarks" class="ti-form-input mb-3" placeholder="Optional assignment note">
                    <div class="ul-modal-footer">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>
                            <i class="bi bi-x-lg"></i>Cancel
                        </button>
                        <button class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                            <i class="bi bi-person-check"></i>Assign / reassign
                        </button>
                    </div>
                        </form>
                    </div>
                </div>
            @endif

            @if($canLogFeedback && $ticket->status === 'awaiting_feedback')
        <div id="ticket-verify-modal" class="ul-modal" hidden>
            <div class="ul-modal-backdrop" data-ul-modal-close></div>
            <div class="ul-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ticket-verify-title">
                <div class="ul-modal-header">
                    <span class="ul-modal-icon ul-badge is-amber"><i class="bi bi-chat-dots" aria-hidden="true"></i></span>
                    <div class="ul-modal-copy">
                        <h6 id="ticket-verify-title">Was this resolved?</h6>
                        <p>CSR verification call / message — flowchart decision diamond.</p>
                    </div>
                    <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('tickets.feedback', $ticket->id) }}" class="ul-modal-body">
                            @csrf
                            <label class="ti-form-label">Method</label>
                            <select name="method" class="ti-form-select mb-3">
                                <option value="call">Call</option>
                                <option value="message">Message</option>
                            </select>
                            <label class="ti-form-label">Customer confirmed resolved?</label>
                            <select name="customer_confirmed" class="ti-form-select mb-3" id="confirmedSelect">
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                            <label class="ti-form-label">Needs further action?</label>
                            <select name="needs_further_action" class="ti-form-select mb-3" id="furtherSelect">
                                <option value="0">No — close after verification</option>
                                <option value="1">Yes — reopen and re-route</option>
                            </select>
                            <textarea name="notes" class="ti-form-input mb-3" rows="2" placeholder="Notes from the verification"></textarea>
                    <div class="ul-modal-footer">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>
                            <i class="bi bi-x-lg"></i>Cancel
                        </button>
                        <button class="ti-btn ti-btn-sm ul-btn ul-btn-edit">
                            <i class="bi bi-chat-dots"></i>Record verification
                        </button>
                    </div>
                        </form>
                    </div>
                </div>
            @endif

    @if($canLogActionNow)
        <div id="ticket-action-modal" class="ul-modal" hidden>
            <div class="ul-modal-backdrop" data-ul-modal-close></div>
            <div class="ul-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ticket-action-title">
                <div class="ul-modal-header">
                    <span class="ul-modal-icon ul-badge is-amber"><i class="bi bi-clipboard-check" aria-hidden="true"></i></span>
                    <div class="ul-modal-copy">
                        <h6 id="ticket-action-title">Log action taken</h6>
                        <p>Records the crew action and requests CSR verification unless TSD is needed.</p>
                    </div>
                    <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('tickets.action', $ticket->id) }}" class="ul-modal-body">
                    @csrf
                    <textarea name="action_taken" class="ti-form-input mb-3" rows="3" required placeholder="What did the crew / unit do?"></textarea>
                    @php
                        $oldMinutes = old('minutes_taken');
                        $minutePresets = array_keys(\App\Support\TicketUi::minutesOptions());
                        $minutesAreCustom = $oldMinutes !== null && $oldMinutes !== '' && ! in_array((int) $oldMinutes, $minutePresets, true);
                    @endphp
                    <label class="ti-form-label required">Minutes taken</label>
                    <div class="ul-minutes mb-3" data-ul-minutes>
                        <select class="ti-form-select ul-minutes-preset" required>
                            <option value="">Select duration</option>
                            @foreach(\App\Support\TicketUi::minutesOptions() as $minutes => $label)
                                <option value="{{ $minutes }}" @selected(! $minutesAreCustom && (string) $oldMinutes === (string) $minutes)>{{ $label }}</option>
                            @endforeach
                            <option value="custom" @selected($minutesAreCustom)>Custom time</option>
                        </select>
                        <div class="ul-minutes-custom" @if(! $minutesAreCustom) hidden @endif>
                            <label class="ti-form-label">Custom minutes</label>
                            <input type="number" name="minutes_taken" class="ti-form-input ul-minutes-input" min="0" required placeholder="Enter minutes" value="{{ $oldMinutes }}">
                        </div>
                    </div>
                    @if($ticket->category?->requires_payment_check)
                        <input type="hidden" name="requires_payment" value="0">
                        <x-unified.check name="requires_payment" value="1" class="mb-3">Requires payment</x-unified.check>
                    @endif
                    @if($ticket->category?->requires_tsd_check)
                        <input type="hidden" name="requires_tsd_intervention" value="0">
                        <x-unified.check name="requires_tsd_intervention" value="1" id="tsdFlag" class="mb-2">Requires TSD intervention</x-unified.check>
                        <textarea name="tsd_reason" class="ti-form-input mb-3" rows="2" placeholder="Why TSD is needed"></textarea>
                    @endif
                    <div class="ul-modal-footer">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>
                            <i class="bi bi-x-lg"></i>Cancel
                        </button>
                        <button class="ti-btn ti-btn-sm ul-btn ul-btn-edit">
                            <i class="bi bi-clipboard-check"></i>Save action
                        </button>
                    </div>
                </form>
                    </div>
                </div>
            @endif

            @if($canEscalate && !in_array($ticket->status, ['closed', 'resolved']))
        <div id="ticket-escalate-modal" class="ul-modal" hidden>
            <div class="ul-modal-backdrop" data-ul-modal-close></div>
            <div class="ul-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ticket-escalate-title">
                <div class="ul-modal-header">
                    <span class="ul-modal-icon ul-badge is-rose"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
                    <div class="ul-modal-copy">
                        <h6 id="ticket-escalate-title">Escalate</h6>
                        <p>Send this ticket to a second-tier department.</p>
                    </div>
                    <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('tickets.escalate', $ticket->id) }}" class="ul-modal-body">
                            @csrf
                            <label class="ti-form-label">Escalate to department</label>
                            <select name="escalated_to" class="ti-form-select mb-3" required>
                                @foreach($departments as $dept)
                            <option value="{{ $dept }}" @selected($dept === config('tickets.escalation.second_tier_department'))>{{ \App\Support\TicketUi::departmentOptionLabel($dept) }}</option>
                                @endforeach
                            </select>
                            <textarea name="reason" class="ti-form-input mb-3" rows="2" required placeholder="Reason"></textarea>
                    <div class="ul-modal-footer">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>
                            <i class="bi bi-x-lg"></i>Cancel
                        </button>
                        <button class="ti-btn ti-btn-sm ul-btn ul-btn-danger">
                            <i class="bi bi-exclamation-triangle"></i>Escalate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div id="ticket-ai-replies-modal" class="ul-modal" hidden>
        <div class="ul-modal-backdrop" data-ul-modal-close></div>
        <div class="ul-modal-dialog is-wide" role="dialog" aria-modal="true" aria-labelledby="ticket-ai-replies-title">
            <div class="ul-modal-header">
                <span class="ul-modal-icon ul-badge is-indigo"><i class="bi bi-chat-quote" aria-hidden="true"></i></span>
                <div class="ul-modal-copy">
                    <h6 id="ticket-ai-replies-title">Suggested replies</h6>
                    <p>Draft, review, then send manually. Customer description is never overwritten.</p>
                </div>
                <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="ul-modal-body">
                <form method="POST" action="{{ route('tickets.ai.suggest-response', $ticket->id) }}" class="ai-generate">
                    @csrf
                    <textarea name="guidance" class="ti-form-input" rows="2" placeholder="Optional CSR guidance for the draft"></textarea>
                    <button class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                        <i class="bi bi-magic"></i>Generate draft
                    </button>
                </form>
                <div class="ai-drafts">
                    @forelse($aiDrafts as $draft)
                        @php
                            $draftTone = $draft->status === \App\Models\TicketAiResponseDraft::STATUS_PENDING
                                ? 'amber'
                                : ($draft->status === \App\Models\TicketAiResponseDraft::STATUS_APPROVED || $draft->status === \App\Models\TicketAiResponseDraft::STATUS_SENT ? 'lime' : 'rose');
                        @endphp
                        <article class="ai-draft">
                            <div class="ai-draft-head">
                                <x-unified.badge :tone="$draftTone" icon="bi-file-text">
                                    #{{ $draft->id }} · {{ str_replace('_', ' ', $draft->status) }}
                                </x-unified.badge>
                                <x-unified.badge :tone="$draft->auto_sent ? 'lime' : 'slate'">
                                    {{ $draft->auto_sent ? 'Auto-sent' : 'Not sent' }}
                                </x-unified.badge>
                            </div>
                            <div class="ai-draft-body">
                                <x-unified.rich :value="$draft->edited_body ?: $draft->draft" />
                            </div>
                            @if($draft->status === \App\Models\TicketAiResponseDraft::STATUS_PENDING)
                                <form method="POST" action="{{ route('tickets.ai.review-draft', [$ticket->id, $draft->id]) }}" class="ai-draft-review">
                                    @csrf
                                    <textarea name="edited_body" class="ti-form-input" rows="3" placeholder="Edit before approving (optional)">{{ $draft->draft }}</textarea>
                                    <input type="text" name="notes" class="ti-form-input" placeholder="Review notes">
                                    <div class="ai-draft-actions">
                                        <button name="decision" value="approve" class="ti-btn ti-btn-sm ul-btn ul-btn-view">Approve (manual send)</button>
                                        <button name="decision" value="reject" class="ti-btn ti-btn-sm ul-btn ul-btn-danger">Reject</button>
                                    </div>
                                </form>
            @endif
                        </article>
                    @empty
                        <div class="ai-empty is-compact">
                            <i class="bi bi-chat-dots"></i>
                            <div class="ai-empty-title">No drafts yet</div>
                            <p>Generate a customer reply for supervisor review.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('ticket-assign-form');
            if (!form) return;
            const deptSelect = form.querySelector('[name="assigned_department"]');
            const roleSelect = form.querySelector('[name="assignee_role_filter"]');
            const assigneeSelect = form.querySelector('[name="assigned_to"]');

            const applyFilter = () => {
                const dept = deptSelect.value;
                const role = roleSelect.value;
                assigneeSelect.querySelectorAll('option[data-role]').forEach((opt) => {
                    const personDept = opt.dataset.dept || '';
                    const matchRole = !role || opt.dataset.role === role;
                    const matchDept = !personDept || personDept === dept;
                    const visible = matchRole && matchDept;
                    opt.hidden = !visible;
                    opt.disabled = !visible;
                });
                const selected = assigneeSelect.selectedOptions[0];
                if (selected && selected.disabled) {
                    assigneeSelect.value = '';
                }
                assigneeSelect.closest('.ul-choice')?.ulRefresh?.();
                deptSelect.closest('.ul-choice')?.ulRefresh?.();
            };

            deptSelect.addEventListener('change', applyFilter);
            roleSelect.addEventListener('change', applyFilter);
            applyFilter();
        });
    </script>
</x-app-layout>
