@php
    $lucideIcons = [
        'home' => 'house', 'capture' => 'list', 'collection' => 'folder', 'book' => 'bookmark',
        'trash' => 'trash-2', 'refresh' => 'refresh-cw', 'image' => 'image', 'study' => 'book-open',
        'chart' => 'chart-no-axes-column-increasing', 'sparkles' => 'sparkles', 'guide' => 'circle-help',
    ];
@endphp

<i data-lucide="{{ $lucideIcons[$icon] ?? 'circle-help' }}" class="size-5 shrink-0" aria-hidden="true"></i>
