<x-app-layout>
    <x-slot name="title">Manage Calendar</x-slot>
    <x-slot name="url_1">{"link": "{{ route('calendar.view') }}", "text": "Calendar Activities"}</x-slot>
    <x-slot name="active">Calendar</x-slot>
    <x-slot name="buttons">
        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-view" data-ul-modal="#calendar-event-form" data-calendar-new>
            <i class="bi bi-plus-lg"></i>New event
        </button>
    </x-slot>

    <div class="grid grid-cols-12 gap-x-6">
        <div class="xxl:col-span-9 xl:col-span-8 col-span-12">
            <div class="box ul-card">
                <div class="box-header ul-card-header">
                    <div class="box-title ul-card-title mb-0">My calendar</div>
                </div>
                <div class="box-body">
                    <div id="calendar" class="rounded-lg overflow-hidden"></div>
                </div>
            </div>
        </div>
        <div class="xxl:col-span-3 xl:col-span-4 col-span-12">
            <div class="box ul-card">
                <div class="box-header ul-card-header">
                    <div class="box-title ul-card-title mb-0">
                        <i class="bi bi-calendar2-check me-1"></i>Today’s schedule
                    </div>
                </div>
                <div class="box-body">
                    <ul id="today-events" class="ul-stack text-sm"></ul>
                </div>
            </div>
        </div>
    </div>

    <div id="calendar-event-form" class="ul-modal" hidden>
        <div class="ul-modal-backdrop" data-ul-modal-close></div>
        <div class="ul-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="calendar-event-form-title">
            <div class="ul-modal-header">
                <span class="ul-modal-icon ul-badge is-indigo"><i class="bi bi-calendar-plus" aria-hidden="true"></i></span>
                <div class="ul-modal-copy">
                    <h6 id="calendar-event-form-title">Create event</h6>
                    <p id="calendar-event-form-copy">Add a calendar activity for your team schedule.</p>
                </div>
                <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="eventForm" class="ul-modal-body" autocomplete="off">
                <div class="ul-form-grid" style="padding: 0 0 1rem;">
                    <div class="ul-field" style="grid-column: 1 / -1;">
                        <label class="ti-form-label" for="modal-title">Event title</label>
                        <input type="text" id="modal-title" class="ti-form-input" placeholder="Event title" required>
                    </div>
                    <div class="ul-field" style="grid-column: 1 / -1;">
                        <label class="ti-form-label" for="modal-description">Description</label>
                        <textarea id="modal-description" rows="3" class="ti-form-input" placeholder="Optional details"></textarea>
                    </div>
                    <div class="ul-field">
                        <label class="ti-form-label" for="modal-start">Start</label>
                        <input type="datetime-local" id="modal-start" class="ti-form-input" required>
                    </div>
                    <div class="ul-field">
                        <label class="ti-form-label" for="modal-end">End</label>
                        <input type="datetime-local" id="modal-end" class="ti-form-input">
                    </div>
                    <div class="ul-field" style="grid-column: 1 / -1;">
                        <label class="ti-form-label" for="modal-link">Meeting link</label>
                        <input type="url" id="modal-link" class="ti-form-input" placeholder="https://">
                    </div>
                    <div class="grid grid-cols-12 gap-3 items-end" style="grid-column: 1 / -1;">
                        <div class="ul-field col-span-3">
                            <label class="ti-form-label" for="modal-color">Color</label>
                            <input type="color" id="modal-color" class="ti-form-input" value="#2ecc71" title="Event color">
                        </div>
                        <div class="ul-field col-span-9">
                            <label class="ti-form-label">Type</label>
                            <div class="flex flex-wrap items-center gap-4 min-h-[2.5rem]">
                                <label class="ul-check">
                                    <input type="radio" name="event_type" value="event" id="modal-event-type" checked>
                                    <span class="ul-check-text">Event</span>
                                </label>
                                <label class="ul-check">
                                    <input type="radio" name="event_type" value="appointment" id="modal-appointment">
                                    <span class="ul-check-text">Appointment (auto-generate link)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="modal-font-color" value="#ffffff">
                </div>
                <div class="ul-modal-footer">
                    <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close id="cancelModal">
                        <i class="bi bi-x-lg"></i>Cancel
                    </button>
                    <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-danger" id="deleteEventBtn" hidden>
                        <i class="bi bi-trash"></i>Delete
                    </button>
                    <button type="submit" class="ti-btn ti-btn-sm ul-btn ul-btn-view" id="saveEventBtn">
                        <i class="bi bi-check2"></i>Save event
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="calendar-event-view" class="ul-modal" hidden>
        <div class="ul-modal-backdrop" data-ul-modal-close></div>
        <div class="ul-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="calendar-event-view-title">
            <div class="ul-modal-header">
                <span class="ul-modal-icon ul-badge is-sky" id="calendar-view-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                <div class="ul-modal-copy">
                    <h6 id="calendar-event-view-title">Event</h6>
                    <p id="calendar-event-view-copy">Calendar activity</p>
                </div>
                <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="ul-modal-body">
                <div class="ul-stack" id="calendar-view-details"></div>
                <div class="ul-modal-footer">
                    <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>
                        <i class="bi bi-x-lg"></i>Close
                    </button>
                    <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-danger" id="viewDeleteBtn">
                        <i class="bi bi-trash"></i>Delete
                    </button>
                    <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-view" id="viewEditBtn">
                        <i class="bi bi-pencil"></i>Edit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <style>
        .fc-event,
        .fc-event-time,
        .fc-event-title {
            color: #fff !important;
        }
        .fc-event {
            text-transform: uppercase;
        }
        .fc .fc-toolbar-title {
            font-size: 1.1rem;
            font-weight: 700;
        }
        #today-events .ul-stack-row {
            cursor: pointer;
            width: 100%;
            border: 0;
            background: transparent;
            text-align: left;
        }
        #today-events .ul-stack-row:hover {
            color: var(--ul-text);
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const calendarEl = document.getElementById('calendar');
            const formModal = document.getElementById('calendar-event-form');
            const viewModal = document.getElementById('calendar-event-view');
            const form = document.getElementById('eventForm');
            const deleteEventBtn = document.getElementById('deleteEventBtn');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
            let viewingEvent = null;

            const modalFields = {
                title: document.getElementById('modal-title'),
                description: document.getElementById('modal-description'),
                color: document.getElementById('modal-color'),
                fontColor: document.getElementById('modal-font-color'),
                start: document.getElementById('modal-start'),
                end: document.getElementById('modal-end'),
                link: document.getElementById('modal-link'),
                appointment: document.getElementById('modal-appointment'),
            };

            const pad = (n) => String(n).padStart(2, '0');
            const formatLocalDatetime = (dateInput) => {
                const date = new Date(dateInput);
                return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
            };
            const localDateKey = (dateInput) => {
                const date = new Date(dateInput);
                return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
            };
            const formatWhen = (start, end) => {
                const startText = start ? new Date(start).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '—';
                if (!end) {
                    return startText;
                }
                return startText + ' – ' + new Date(end).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
            };

            const setFormMode = (editing) => {
                document.getElementById('calendar-event-form-title').textContent = editing ? 'Edit event' : 'Create event';
                document.getElementById('calendar-event-form-copy').textContent = editing
                    ? 'Update this calendar activity.'
                    : 'Add a calendar activity for your team schedule.';
                deleteEventBtn.hidden = !editing;
            };

            const resetForm = () => {
                form.reset();
                delete form.dataset.editing;
                modalFields.color.value = '#2ecc71';
                modalFields.fontColor.value = '#ffffff';
                setFormMode(false);
            };

            const openFormModal = () => window.ulOpenModal?.(formModal);
            const closeFormModal = () => window.ulCloseModal?.(formModal);

            document.querySelectorAll('input[name="event_type"]').forEach((input) => {
                input.addEventListener('change', function () {
                    modalFields.link.value = modalFields.appointment.checked
                        ? `https://meet.hillbcs.com/${Math.random().toString(36).substring(2, 12)}`
                        : '';
                });
            });

            document.querySelector('[data-calendar-new]')?.addEventListener('click', () => {
                resetForm();
            });

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                editable: true,
                selectable: true,
                selectMirror: true,
                nowIndicator: true,
                slotMinTime: '06:00:00',
                slotMaxTime: '22:00:00',
                eventDisplay: 'block',
                dayMaxEventRows: true,
                timeZone: 'local',

                eventDidMount: function (info) {
                    const titleEl = info.el.querySelector('.fc-event-title');
                    const timeEl = info.el.querySelector('.fc-event-time');

                    if (timeEl && info.event.start) {
                        timeEl.innerText = new Date(info.event.start).toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true,
                        });
                    }

                    if (titleEl && info.event.extendedProps.text_color) {
                        titleEl.style.color = info.event.extendedProps.text_color;
                    }
                    if (titleEl) {
                        titleEl.style.display = 'none';
                    }
                },

                events: function (fetchInfo, successCallback) {
                    fetch('{{ url('/calendar-events') }}')
                        .then((res) => res.json())
                        .then((data) => {
                            const formatted = data.map((event) => ({
                                id: event.id,
                                title: event.title,
                                start: event.start_time,
                                end: event.end_time,
                                color: event.color || '#3b82f6',
                                textColor: event.text_color || '#ffffff',
                                extendedProps: {
                                    description: event.description,
                                    meeting_link: event.meeting_link,
                                    is_appointment: event.is_appointment,
                                    text_color: event.text_color
                                }
                            }));
                            renderTodayEvents(formatted);
                            successCallback(formatted);
                        });
                },

                select: function (info) {
                    resetForm();
                    modalFields.start.value = formatLocalDatetime(info.start);
                    modalFields.end.value = info.end ? formatLocalDatetime(info.end) : '';
                    openFormModal();
                    modalFields.title.focus();
                },

                eventClick: function (info) {
                    openViewModal(info.event);
                },

                eventDrop: function (info) {
                    const event = info.event;
                    const originalStart = info.oldEvent.start;
                    const originalEnd = info.oldEvent.end;
                    const droppedDate = event.start;
                    const newStart = new Date(
                        droppedDate.getFullYear(),
                        droppedDate.getMonth(),
                        droppedDate.getDate(),
                        originalStart.getHours(),
                        originalStart.getMinutes()
                    );
                    let newEnd = null;
                    if (originalEnd) {
                        newEnd = new Date(
                            droppedDate.getFullYear(),
                            droppedDate.getMonth(),
                            droppedDate.getDate(),
                            originalEnd.getHours(),
                            originalEnd.getMinutes()
                        );
                    }
                    fetch(`{{ url('/calendar-events') }}/${event.id}`, {
                        method: 'PUT',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            start_time: newStart.toISOString(),
                            end_time: newEnd ? newEnd.toISOString() : null
                        })
                    }).then(() => calendar.refetchEvents());
                },

                eventResize: function (info) {
                    updateEventTime(info.event);
                }
            });

            calendar.render();

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const saveBtn = document.getElementById('saveEventBtn');
                window.ulBusyButton?.(saveBtn);

                const data = {
                    title: modalFields.title.value,
                    description: modalFields.description.value,
                    color: modalFields.color.value,
                    text_color: modalFields.fontColor.value,
                    meeting_link: modalFields.link.value || null,
                    is_appointment: modalFields.appointment.checked,
                    start_time: modalFields.start.value,
                    end_time: modalFields.end.value || null,
                };

                const eventId = form.dataset.editing;
                const method = eventId ? 'PUT' : 'POST';
                const url = eventId ? `{{ url('/calendar-events') }}/${eventId}` : '{{ url('/calendar-events') }}';

                fetch(url, {
                    method,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                }).then((res) => {
                    if (res.ok) {
                        calendar.refetchEvents();
                        closeFormModal();
                        resetForm();
                    }
                }).finally(() => window.ulReadyButton?.(saveBtn));
            });

            document.getElementById('cancelModal').addEventListener('click', function () {
                resetForm();
            });

            deleteEventBtn.addEventListener('click', function () {
                if (form.dataset.editing) {
                    confirmDelete(form.dataset.editing);
                }
            });

            document.getElementById('viewEditBtn').addEventListener('click', function () {
                if (viewingEvent) {
                    openEditModal(viewingEvent);
                }
            });

            document.getElementById('viewDeleteBtn').addEventListener('click', function () {
                if (viewingEvent) {
                    confirmDelete(viewingEvent.id);
                }
            });

            document.getElementById('today-events').addEventListener('click', function (event) {
                const button = event.target.closest('[data-today-event]');
                if (!button) {
                    return;
                }
                const calEvent = calendar.getEventById(button.getAttribute('data-today-event'));
                if (calEvent) {
                    openViewModal(calEvent);
                }
            });

            function updateEventTime(event) {
                const originalEvent = calendar.getEventById(event.id);
                const duration = originalEvent.end
                    ? originalEvent.end.getTime() - originalEvent.start.getTime()
                    : 0;
                const newStart = event.start;
                const newEnd = duration ? new Date(newStart.getTime() + duration) : null;

                fetch(`{{ url('/calendar-events') }}/${event.id}`, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        start_time: newStart.toISOString(),
                        end_time: newEnd ? newEnd.toISOString() : null
                    })
                }).then(() => calendar.refetchEvents());
            }

            function openEditModal(event) {
                form.dataset.editing = event.id;
                setFormMode(true);
                modalFields.title.value = event.title || '';
                modalFields.description.value = event.extendedProps.description || '';
                modalFields.color.value = event.backgroundColor || event.color || '#3b82f6';
                modalFields.fontColor.value = event.extendedProps.text_color || '#ffffff';
                modalFields.link.value = event.extendedProps.meeting_link || '';
                const isAppointment = Boolean(event.extendedProps.is_appointment);
                document.getElementById('modal-event-type').checked = !isAppointment;
                modalFields.appointment.checked = isAppointment;
                modalFields.start.value = event.start ? formatLocalDatetime(event.start) : '';
                modalFields.end.value = event.end ? formatLocalDatetime(event.end) : '';
                openFormModal();
                modalFields.title.focus();
            }

            function openViewModal(event) {
                viewingEvent = event;
                const props = event.extendedProps || {};
                document.getElementById('calendar-event-view-title').textContent = event.title || 'Event';
                document.getElementById('calendar-event-view-copy').textContent = props.is_appointment ? 'Appointment' : 'Calendar event';
                const meeting = props.meeting_link
                    ? `<a href="${props.meeting_link}" target="_blank" rel="noopener">${props.meeting_link}</a>`
                    : '—';
                document.getElementById('calendar-view-details').innerHTML = `
                    <div class="ul-stack-row">
                        <span class="text-textmuted">When</span>
                        <span>${formatWhen(event.start, event.end)}</span>
                    </div>
                    <div class="ul-stack-row">
                        <span class="text-textmuted">Description</span>
                        <span>${props.description || '—'}</span>
                    </div>
                    <div class="ul-stack-row">
                        <span class="text-textmuted">Meeting</span>
                        <span>${meeting}</span>
                    </div>
                `;
                window.ulOpenModal?.(viewModal);
            }

            function confirmDelete(id) {
                const ask = window.ulConfirm
                    ? window.ulConfirm({
                        title: 'Delete this event?',
                        text: 'This action cannot be undone.',
                        verb: 'Delete',
                        tone: 'danger',
                        icon: 'bi-trash',
                    })
                    : Promise.resolve(window.confirm('Delete this event?'));

                ask.then((ok) => {
                    if (!ok) {
                        return;
                    }
                    fetch(`{{ url('/calendar-events') }}/${id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrfToken }
                    }).then((res) => {
                        if (res.ok) {
                            calendar.refetchEvents();
                            window.ulCloseModal?.(formModal);
                            window.ulCloseModal?.(viewModal);
                            resetForm();
                            viewingEvent = null;
                        }
                    });
                });
            }

            function renderTodayEvents(events) {
                const container = document.getElementById('today-events');
                if (!container) {
                    return;
                }
                const today = localDateKey(new Date());
                const todaysEvents = events.filter((e) => e.start && localDateKey(e.start) === today);
                if (todaysEvents.length === 0) {
                    container.innerHTML = '<li class="text-textmuted">No events today.</li>';
                    return;
                }
                container.innerHTML = todaysEvents.map((e) => {
                    const time = new Date(e.start).toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true
                    });
                    return `<li>
                        <button type="button" class="ul-stack-row" data-today-event="${e.id}">
                            <span><strong>${time}</strong> · ${e.title}</span>
                            <i class="bi bi-chevron-right text-textmuted"></i>
                        </button>
                    </li>`;
                }).join('');
            }
        });
    </script>
</x-app-layout>
