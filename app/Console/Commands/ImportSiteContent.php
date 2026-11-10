<?php

namespace App\Console\Commands;

use App\Models\ContentPage;
use App\Support\BookLibrary;
use App\Support\Cms;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Http\Request;

class ImportSiteContent extends Command
{
    protected $signature = 'site:import';

    protected $description = 'Додати наявні сторінки й матеріали в адмінку, зберігши зроблені редактором зміни';

    public function handle(): int
    {
        app()->instance('cms.importing', true);
        // This exemption exists only in this CLI process, so deployment can import while the public site is down.
        PreventRequestsDuringMaintenance::except(['*']);
        $queue = ['/', '/pro-tsentr', '/pro-tsentr/upravlinnia', '/ridna-vira', '/ridna-vira/kalendar', '/ridna-vira/knyhy', '/ridna-vira/bohy', '/ridna-vira/sviatyni', '/ridna-vira/obriady', '/ridna-vira/molytvy', '/zviazok', '/novyny', '/statti', '/tvorchist'];
        $seen = [];
        $created = BookLibrary::import();
        $failed = [];
        $kernel = app(Kernel::class);
        $originalRequest = app('request');
        try {
            if (! ContentPage::withTrashed()->where('path', '/umovy-kramnytsi')->exists()) {
                ContentPage::forceCreate(['path' => '/umovy-kramnytsi', 'title' => 'Замовлення, доставка та конфіденційність', 'section' => 'pages', 'editor_mode' => 'reading', 'is_override' => true,
                    'body' => '<h2>Замовлення та оплата</h2><p>Додайте товари до кошика, вкажіть контакти та спосіб доставки. Оплата карткою відбувається на захищеній сторінці WayForPay. Підтверджений статус і склад покупки доступні за особистим посиланням замовлення.</p><h2>Доставка</h2><p>Доставку Новою поштою або Укрпоштою оплачує покупець за тарифами перевізника при отриманні. Самовивіз потрібно попередньо узгодити з представником центру.</p><h2>Обмін і повернення</h2><p>Для питань щодо товару, обміну, повернення або оплати зверніться до нас через <a href="/zviazok">сторінку зв’язку</a> та вкажіть номер замовлення.</p><h2>Персональні дані</h2><p>Ім’я, телефон, електронна пошта й адреса доставки використовуються для виконання замовлення та зв’язку з покупцем. Дані платіжної картки обробляє WayForPay; сайт не зберігає номер картки чи її захисний код. Особисте посилання замовлення не слід передавати стороннім.</p>',
                ]);
            }
            while ($queue !== []) {
                $uri = array_shift($queue);
                if (isset($seen[$uri])) {
                    continue;
                }
                $seen[$uri] = true;
                $request = Request::create(rtrim(config('app.url'), '/').$uri, 'GET');
                $response = $kernel->handle($request);
                if ($response->getStatusCode() !== 200 || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
                    if (! $response->isRedirection()) {
                        $failed[] = $uri.' ('.$response->getStatusCode().')';
                    }

                    continue;
                }
                $html = $response->getContent();
                if (! preg_match('~<main\b[^>]*>(.*?)</main>~s', $html, $match)) {
                    continue;
                }
                $main = $match[1];
                $document = Cms::document($main);
                foreach ($document->getElementsByTagName('a') as $link) {
                    $url = $link->getAttribute('href');
                    $parts = parse_url($url);
                    if (! is_array($parts) || (isset($parts['host']) && $parts['host'] !== parse_url(config('app.url'), PHP_URL_HOST))) {
                        continue;
                    }
                    $path = $parts['path'] ?? '';
                    if (! preg_match('~^/(?:pro-tsentr(?:/upravlinnia)?|ridna-vira(?:/(?:kalendar|knyhy|bohy|sviatyni|obriady|molytvy)(?:/[a-z0-9-]+)?)?|novyny(?:/[a-z0-9-]+)?|statti(?:/[a-z0-9-]+)?|tvorchist(?:/[a-z0-9-]+)?|dokumenti/[a-z0-9-]+|zviazok)/?$~D', $path)) {
                        continue;
                    }
                    parse_str($parts['query'] ?? '', $query);
                    $candidate = rtrim($path, '/').(isset($query['page']) ? '?page='.(int) $query['page'] : '');
                    if (! isset($seen[$candidate])) {
                        $queue[] = $candidate;
                    }
                }
                if (str_contains($uri, '?')) {
                    continue;
                }
                $path = $uri;
                if (ContentPage::withTrashed()->where('path', $path)->exists()) {
                    continue;
                }
                $title = trim($document->getElementsByTagName('h1')->item(0)?->textContent ?? 'Рідна Віра');
                $date = $document->getElementsByTagName('time')->item(0)?->getAttribute('datetime');
                [$body] = Cms::islands($main);
                $fragment = Cms::fragment($main);
                $section = match (true) {
                    str_starts_with($path, '/novyny/') => 'news', str_starts_with($path, '/statti/') => 'articles',
                    str_starts_with($path, '/tvorchist/') => 'creativity', str_starts_with($path, '/ridna-vira/bohy/') => 'gods',
                    str_starts_with($path, '/ridna-vira/kalendar/') => 'holidays', str_starts_with($path, '/ridna-vira/sviatyni/') => 'shrines',
                    str_starts_with($path, '/ridna-vira/obriady/') => 'rituals', str_starts_with($path, '/ridna-vira/molytvy/') => 'prayers',
                    str_starts_with($path, '/dokumenti/') => 'documents', default => 'pages',
                };
                ContentPage::forceCreate([
                    'path' => $path, 'section' => $section, 'title' => $title,
                    'body' => $fragment ?? $body, 'editor_mode' => $fragment !== null ? 'fragment' : 'layout',
                    'excerpt' => mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($fragment ?? $main))), 0, 250),
                    'is_imported' => true, 'is_override' => false,
                    'published_at' => is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) ? $date : null,
                ]);
                $created++;
            }
        } finally {
            app()->forgetInstance('cms.importing');
            PreventRequestsDuringMaintenance::flushState();
            app()->instance('request', $originalRequest);
        }
        $this->info('Додано сторінок: '.$created.'. Перевірено адрес: '.count($seen).'. Редакторські зміни збережено.');
        foreach ($failed as $failure) {
            $this->warn('Не імпортовано: '.$failure);
        }

        return self::SUCCESS;
    }
}
