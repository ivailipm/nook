@props(['label', 'name', 'gold' => false])
@php
    $message = $errors->first($name);
    $date = $attributes->get('type') === 'date';
@endphp

{{-- A labelled input; other attributes go on the <input>. The prototype's form fields are 12px round, not the 16px quick-add radius. A date input is mono 15px with a dark picker. `gold` marks a key field with a gold border until it has an error. --}}
<div class="flex flex-col gap-1.5">
    <label for="{{ $name }}" class="text-[13px] font-semibold">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        @if ($message) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class([
            'min-h-[46px] w-full rounded-[12px] border bg-black/30',
            'px-3.5 text-[16px]' => ! $date,
            'px-3 font-mono text-[15px] [color-scheme:dark]' => $date,
            'border-destructive-border' => $message,
            'border-gold' => ! $message && $gold,
            'border-input-border' => ! $message && ! $gold,
        ]) }}
    >
    @if ($message)
        <p id="{{ $name }}-error" class="text-[13px] text-destructive">{{ $message }}</p>
    @endif
</div>
