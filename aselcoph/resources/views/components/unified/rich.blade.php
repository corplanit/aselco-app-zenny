@props([
    'value' => '',
])

<div {{ $attributes->class(['ul-rich']) }}>
    {!! \App\Support\TicketUi::richHtml($value !== '' ? $value : (string) $slot) !!}
</div>
