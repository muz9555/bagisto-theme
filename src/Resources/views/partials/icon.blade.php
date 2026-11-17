@php
    $paths = [
        'search' => 'm20 20-4-4m2-5a7 7 0 1 1-14 0 7 7 0 0 1 14 0',
        'account' => 'M20 21v-2a6 6 0 0 0-6-6h-4a6 6 0 0 0-6 6v2m12-15a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'heart' => 'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8',
        'arrow' => 'M5 12h14m-6-6 6 6-6 6',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'plus' => 'M12 5v14M5 12h14',
        'box' => 'm12 3 9 5-9 5-9-5 9-5m-9 5v9l9 5 9-5V8m-9 5v9',
    ];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="mx-icon"><path d="{{ $paths[$name] ?? $paths['arrow'] }}" /></svg>
