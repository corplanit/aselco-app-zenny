@props([
    'name' => 'attachment',
    'accept' => '.jpg,.jpeg,.png,.pdf,.mp4',
    'required' => false,
    'hint' => null,
])

@php
    $hint = $hint ?? 'JPG, PNG, PDF, or MP4. Max '.number_format(config('tickets.attachment.max_kb', 10240) / 1024, 0).' MB.';
@endphp

<div {{ $attributes->class(['ul-dropzone']) }}>
    <input
        type="file"
        name="{{ $name }}"
        class="ul-dropzone-input"
        accept="{{ $accept }}"
        @required($required)
    >
    <div class="ul-dropzone-frame">
        <span class="ul-dropzone-icon" aria-hidden="true"><i class="bi bi-cloud-arrow-up"></i></span>
        <div class="ul-dropzone-copy">
            <div class="ul-dropzone-title">Drag and drop file here</div>
            <div class="ul-dropzone-or">or</div>
            <button type="button" class="ul-dropzone-choose">Choose file</button>
            <div class="ul-dropzone-hint">{{ $hint }}</div>
        </div>
        <div class="ul-dropzone-picked" hidden>
            <i class="bi bi-paperclip" aria-hidden="true"></i>
            <span class="ul-dropzone-name"></span>
            <button type="button" class="ul-dropzone-clear" aria-label="Remove file">&times;</button>
        </div>
    </div>
</div>
