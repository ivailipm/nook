{{-- Shows the window event toast with { message } for 2.6s; a new message replaces the current one and restarts the timer. --}}
<div
    role="status"
    x-data="{ message: '', timer: null }"
    x-on:toast.window="message = $event.detail.message; clearTimeout(timer); timer = setTimeout(() => message = '', 2600)"
    class="pointer-events-none fixed inset-x-3.5 bottom-24 flex justify-center wide:bottom-7"
>
    <div x-text="message" class="rounded-[12px] bg-paper px-4 py-2.5 text-center text-[13px] font-semibold text-receipt-ink shadow-toast empty:hidden"></div>
</div>
