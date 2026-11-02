@use('App\Support\Money')
@props(['totals', 'subscriptions'])
@php
    $teeth = collect(range(20, 1))->map(fn (int $tooth) => 'L'.($tooth * 5 - 2.5).' 4 L'.(($tooth - 1) * 5).' 0')->implode(' ');
@endphp

{{-- The till receipt of the live subscriptions, with a zig-zag tear along its bottom edge. --}}
<div class="px-1.5 pt-1">
    <div class="-rotate-1 drop-shadow-receipt">
        <div class="flex flex-col gap-1 bg-receipt px-[18px] pt-[18px] pb-2.5 font-mono text-receipt-ink">
            <div class="text-center text-[11px] tracking-[0.16em] text-receipt-muted">* * * NOOK · {{ Str::upper(today()->format('F')) }} * * *</div>
            <div class="mt-2 text-center text-[11px] tracking-[0.16em] text-receipt-muted">THIS MONTH</div>
            <div class="text-center font-display text-[46px] leading-[1.05] font-bold break-words">{{ Money::format($totals->monthly) }}</div>
            <div class="mb-2.5 text-center text-[12px] break-words text-receipt-muted">≈ {{ Money::format($totals->yearly) }} a year</div>

            @foreach ($subscriptions as $subscription)
                <div wire:key="receipt-{{ $subscription->id }}" class="flex items-start justify-between gap-2.5 border-t border-dashed border-receipt-rule py-2">
                    <div class="flex min-w-0 flex-1 flex-col break-words">
                        <span class="text-[14px] font-semibold">{{ $subscription->name }}</span>
                        <span @class(['text-[11px]', 'text-ends-soon' => $subscription->is_trial, 'text-receipt-muted' => ! $subscription->is_trial])>{{ $subscription->receiptMeta() }}</span>
                    </div>
                    <span class="flex-none text-[14px] font-semibold">{{ Money::format($subscription->is_trial ? 0.0 : $subscription->monthlyPrice()) }}</span>
                </div>
            @endforeach

            <div class="flex justify-between gap-2.5 border-t-2 border-receipt-ink pt-2.5 pb-1 text-[13px] font-semibold">
                <span>TOTAL / MONTH</span>
                <span>{{ Money::format($totals->monthly) }}</span>
            </div>
            <div class="pt-2 pb-1 text-center text-[10px] tracking-[0.16em] text-receipt-muted">THANK YOU · COME AGAIN</div>
        </div>
        <svg viewBox="0 0 100 4" preserveAspectRatio="none" width="100%" height="10" aria-hidden="true" class="block fill-receipt"><path d="M0 0 H100 V0 {{ $teeth }} Z"></path></svg>
    </div>
</div>
