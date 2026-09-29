<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;

test('guest sees the complete public landing page without external requests', function () {
    Http::preventStrayRequests();

    $response = $this->get('/');
    $html = $response->getContent();

    $response
        ->assertOk()
        ->assertSee('Web Learning | 読むだけで、学びがたまる。', false)
        ->assertSee('Web Learningは、普段のWeb閲覧からAIが学習候補を見つけ、4択問題と復習につなげるWeb連動型学習アプリです。', false)
        ->assertSee('読むだけで、')
        ->assertSee('学びがたまる。')
        ->assertSee('読むだけで、学びがたまる。')
        ->assertSee('学習候補を3件発見')
        ->assertSee('4択問題へ')
        ->assertSee(asset('images/pwa/icon-192.png'), false)
        ->assertSee('systemd')
        ->assertSee('READ / 読む')
        ->assertSee('DISCOVER / 見つかる')
        ->assertSee('QUIZ / 問題になる')
        ->assertSee('REVIEW / 復習する')
        ->assertSee('id="chrome-extension"', false)
        ->assertSee('CHROME EXTENSION')
        ->assertSee('Chrome拡張機能をダウンロード')
        ->assertSee('href="'.asset('downloads/web-learning-extension.zip').'"', false)
        ->assertSee('STEP 02で展開した「web-learning-extension」フォルダを選択します。')
        ->assertSee('※ manifest.json が入っている「web-learning-extension」フォルダを選択してください。')
        ->assertSee('インストール完了')
        ->assertSee('準備ができたら、使ってみよう！')
        ->assertSee('lg:whitespace-nowrap', false)
        ->assertSee('01')
        ->assertSee('Web Learningにログイン')
        ->assertSee('02')
        ->assertSee('Chrome右上の拡張機能アイコンからWeb Learningをピン留め')
        ->assertSee(asset('images/extension-icon.svg'), false)
        ->assertSee('03')
        ->assertSee('学びたいWebページでWeb Learningを開き、Learning Modeを「ON」')
        ->assertDontSee('Chrome右上のパズルピース型アイコン')
        ->assertDontSee('Web Learning拡張機能を開き、Learning Modeを「ON」にする')
        ->assertSee('chrome://extensions/', false)
        ->assertSee('Chromeデスクトップ版')
        ->assertSee('Chrome Web Store公開前のテスト版です')
        ->assertDontSee('学校発表用')
        ->assertSee('<span class="block whitespace-nowrap">読むだけでは</span><span class="block whitespace-nowrap">終わらせない</span>', false)
        ->assertSee('text-left', false)
        ->assertSee('id="screenshot-learning"', false)
        ->assertSee('SCREENSHOT LEARNING')
        ->assertSee('id="pricing"', false)
        ->assertSee('月100回')
        ->assertSee('月1,000回')
        ->assertSee('<span class="block whitespace-nowrap">読むだけでは</span><span class="block whitespace-nowrap">終わらせない</span>', false)
        ->assertSee('id="final-message-title"', false)
        ->assertSee('<span class="block whitespace-nowrap">読んだその先を、</span><span class="block whitespace-nowrap">学びに変える。</span>', false)
        ->assertSee('id="final-cta-title"', false)
        ->assertSee('href="'.route('register').'"', false)
        ->assertSee('無料ではじめる')
        ->assertSee('href="'.route('login').'"', false)
        ->assertSee('href="#how-it-works"', false)
        ->assertDontSee('<video', false);

    preg_match_all('/<img[^>]+src="([^"]+)"/', $html, $imageSources);

    expect($imageSources[1])->toContain(asset('images/pwa/icon-192.png'))
        ->and($html)->toContain(Vite::asset('resources/images/toppage/sample8.png'))
        ->and($html)->toContain(Vite::asset('resources/images/icon/sample10.png'));
    Http::assertNothingSent();
    $this->assertDatabaseCount('ai_usage_logs', 0);
});

test('the school extension archive is a clean loadable unpacked extension', function (): void {
    $archivePath = public_path('downloads/web-learning-extension.zip');
    expect(is_file($archivePath))->toBeTrue();

    $archive = new ZipArchive;
    expect($archive->open($archivePath))->toBeTrue();

    $names = [];
    for ($index = 0; $index < $archive->numFiles; $index++) {
        $names[] = $archive->getNameIndex($index);
    }
    $archive->close();

    expect($names)->toContain('manifest.json', 'base-url.js', 'background.js', 'popup.html', 'popup.js', 'content.js', 'review.html', 'review.js')
        ->not->toContain('background.test.js');
});

test('authenticated user sees dashboard calls to action instead of registration', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response
        ->assertOk()
        ->assertSee('href="'.route('dashboard').'"', false)
        ->assertSee('Dashboardへ →')
        ->assertDontSee('href="'.route('register').'"', false)
        ->assertDontSee('無料ではじめる');
});
