@props(['shop', 'user', 'mobile' => false])

@php
    $canOpenShop = $shop && $user && ($user->canSellAtShop($shop) || $user->isActiveShopAccountant($shop));
    $manifest = $canOpenShop ? app(\App\Services\SellerNavigationService::class)->forUser($shop, $user, 'web') : ['sections' => [], 'secondary' => []];
    $linkClass = 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition';
    $idleClass = $mobile ? 'text-slate-700 hover:bg-slate-100' : 'text-slate-300 hover:bg-white/10 hover:text-white';
    $activeClass = 'bg-blue-600 text-white';
@endphp

<div data-navigation-tree="{{ $mobile ? 'mobile' : 'desktop' }}" class="space-y-1">
    @foreach ($manifest['sections'] as $section)
        @if ($section['id'] === 'home')
            @foreach ($section['items'] as $item)
                <a wire:navigate href="{{ $item['url'] }}" data-navigation-id="{{ $item['id'] }}" @if ($item['active']) aria-current="page" @endif class="{{ $linkClass }} {{ $item['active'] ? $activeClass : $idleClass }}">{{ $section['label'] }}</a>
            @endforeach
        @else
            <details data-navigation-section="{{ $section['id'] }}" @if ($section['active'] || $section['id'] === 'sales') open @endif class="rounded-xl">
                <summary class="{{ $linkClass }} cursor-pointer {{ $section['active'] ? ($mobile ? 'bg-blue-50 text-blue-700' : 'bg-white/10 text-white') : $idleClass }}">{{ $section['label'] }}</summary>
                <div class="ml-3 space-y-1 border-l {{ $mobile ? 'border-slate-200' : 'border-white/10' }} py-1 pl-2">
                    @foreach ($section['items'] as $item)
                        <a wire:navigate href="{{ $item['url'] }}" data-navigation-id="{{ $item['id'] }}" @if ($item['active']) aria-current="page" @endif @if ($item['external']) target="_blank" rel="noopener" @endif @if ($item['id'] === 'sellers') aria-label="Vendedores" @endif class="{{ $linkClass }} {{ $item['active'] ? $activeClass : $idleClass }}">
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </details>
        @endif
    @endforeach
    @if ($manifest['secondary'] !== [])
        <div class="mt-3 space-y-1 border-t {{ $mobile ? 'border-slate-200' : 'border-white/10' }} pt-3" aria-label="Cuenta y ayuda">
            @foreach ($manifest['secondary'] as $item)
                <a wire:navigate href="{{ $item['url'] }}" data-navigation-id="{{ $item['id'] }}" @if ($item['active']) aria-current="page" @endif class="{{ $linkClass }} {{ $item['active'] ? $activeClass : $idleClass }}">{{ $item['label'] }}</a>
            @endforeach
        </div>
    @endif
</div>
