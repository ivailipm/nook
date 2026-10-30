@props(['label', 'name'])
@php($message = $errors->first($name))

{{-- A labelled input; other attributes go on the <input>. The prototype's form fields are 12px round, not the 16px quick-add radius. --}}
<div class="flex flex-col gap-1.5">
    <label for="{{ $name }}" class="text-[13px] font-semibold">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        @if ($message) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class([
            'min-h-[46px] w-full rounded-[12px] border bg-black/30 px-3.5 text-[16px]',
            'border-destructive-border' => $message,
            'border-input-border' => ! $message,
        ]) }}
    >
    @if ($message)
        <p id="{{ $name }}-error" class="text-[13px] text-destructive">{{ $message }}</p>
    @endif
</div>
