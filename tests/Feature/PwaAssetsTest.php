<?php

test('offline page and service worker assets are available', function () {
    $offlinePage = public_path('offline.html');
    $serviceWorker = public_path('sw.js');

    expect(is_readable($offlinePage))->toBeTrue();
    expect(is_readable($serviceWorker))->toBeTrue();
    expect(file_get_contents($offlinePage))
        ->toContain('インターネットに接続できません')
        ->toContain('location.reload()');
    expect(file_get_contents($serviceWorker))->toContain("self.addEventListener('fetch'");
});

test('manifest retains the Web Learning PWA configuration', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)
        ->toMatchArray([
            'name' => 'Web Learning',
            'short_name' => 'ウェブラー',
            'display' => 'standalone',
            'start_url' => './',
            'scope' => './',
        ]);
    expect($manifest['icons'])->toHaveCount(4);
});

test('service worker precaches only public PWA assets', function () {
    $source = file_get_contents(public_path('sw.js'));
    preg_match('/const PRECACHE_PATHS = Object\\.freeze\\((\\[[\\s\\S]*?\\])\\);/', $source, $matches);

    $precachePaths = json_decode($matches[1] ?? '', true, flags: JSON_THROW_ON_ERROR);

    expect($precachePaths)->toBe([
        'offline.html',
        'images/pwa/icon-192.png',
        'images/pwa/icon-512.png',
        'images/pwa/icon-maskable-192.png',
        'images/pwa/icon-maskable-512.png',
        'images/pwa/apple-touch-icon.png',
    ]);
    expect($source)
        ->toContain("request.method !== 'GET'")
        ->toContain("request.mode === 'navigate'")
        ->toContain('fetch(request).catch(() => caches.match(OFFLINE_URL))')
        ->toContain('const PRECACHE_URLS = new Set(precacheUrls)')
        ->toContain('if (!PRECACHE_URLS.has(request.url))');
});

test('service worker registration derives its URL and scope from the manifest', function () {
    $registrationSource = file_get_contents(resource_path('js/pwa.js'));

    expect($registrationSource)
        ->toContain("document.querySelector('link[rel=\"manifest\"]')")
        ->toContain("new URL('sw.js', manifestUrl)")
        ->toContain("new URL('./', manifestUrl)")
        ->not->toContain('/web_learning/');
});
