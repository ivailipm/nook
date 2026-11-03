@props(['subscription'])

{{-- The name's first letter, ringed in its category's colour. It's decorative, because the name is always beside it. --}}
<span aria-hidden="true" @class([
    'grid size-[34px] flex-none place-items-center rounded-avatar bg-white/8 font-display font-bold inset-ring-[1.5px]',
    $subscription->category->ringClass(),
    'opacity-60' => $subscription->isCancelled(),
])>{{ Str::substr($subscription->name, 0, 1) }}</span>
