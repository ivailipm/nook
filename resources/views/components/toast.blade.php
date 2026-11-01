@php($flashed = session('toast'))

{{-- Shows the window event toast with { message } for 2.6s; a new message replaces the current one and restarts the timer. A flashed session('toast') shows the same way once the page has loaded: set after load, the role="status" region announces it. --}}
<div
    role="status"
    x-data="{ message: '', timer: null, show(message) { this.message = message; clearTimeout(this.timer); this.timer = setTimeout(() => this.message = '', 2600) } }"
    @if ($flashed) x-init="setTimeout(() => show(@js($flashed)), 100)" @endif
    x-on:toast.window="show($event.detail.message)"
    class="pointer-events-none fixed inset-x-3.5 bottom-24 flex justify-center wide:bottom-7"
>
    <div x-text="message" class="rounded-[12px] bg-paper px-4 py-2.5 text-center text-[13px] font-semibold text-receipt-ink shadow-toast empty:hidden"></div>
</div>
