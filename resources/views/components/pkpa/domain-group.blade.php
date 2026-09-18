@props([
    'name',
    'code' => null,
    'count' => 0,
    'anchor' => null,
])

<section @if($anchor) id="{{ $anchor }}" @endif class="scroll-mt-6 space-y-4">
    <header class="flex flex-wrap items-end justify-between gap-3 border-b border-sky-200 pb-3">
        <div>
            @if($code)
                <p class="text-xs font-black uppercase tracking-widest text-cyan-700">{{ $code }}</p>
            @endif
            <h2 class="mt-0.5 text-xl font-black text-slate-950">{{ $name }}</h2>
        </div>
        <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">{{ $count }} data</span>
    </header>
    {{ $slot }}
</section>
