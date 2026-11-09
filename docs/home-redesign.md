# Головна Духовного центру «Рідна Віра»

Сторінка використовує чинний layout, header, footer і маршрути. Додано лише клас body для ізоляції фону головної. Форма зв’язку та внутрішні сторінки не змінені.

## Контент

- Найближчі чотири свята визначаються за `config/faith_calendar.php` у часовому поясі `Europe/Kyiv`, включно з поточним днем та переходом на наступний рік. Посилання використовують той самий slug, що й HolidayController.
- Статті беруться з `storage/app/content/articles/manifest.json`, останні у порядку імпорту. Відсутні файли пропускаються. Імпорт не має дат публікації, тому сторінка не вигадує дату або хронологію новин.
- Без імпорту показуються посилання на книги й основи віри. Маршрут `/novyny` наразі є наявною сторінкою-заготовкою; на головній залишено перехід до нього.
- CTA громад ведуть на чинну мапу `/zviazok#communities-map-title`; Академія — на адресу, яка вже використовується в header.

## Зображення та шрифти

WebP-похідні локальних PNG закомічені в `public/assets/home`. Hero має 640/1280px, фото громади — 480/960px, іконки — 192px. Оригінали не змінені. Для повторної підготовки: `python -m pip install Pillow`, потім `python scripts/prepare-home-assets.py`. На сервері Python не потрібен.

Шрифти надані власником у `KyivRegion-Regular.zip` та `Holos-bold.zip`, збережені в `public/fonts`. Ліцензію Holos збережено поряд. Великі заголовки — Holos Bold; інший текст — Kyiv Region. Старе підключення Google Fonts прибране.

Hero завантажується пріоритетно, нижні фото й іконки — через native lazy loading. У зображень задані розміри; для великих фото є srcset. Головна не завантажує стару фонову текстуру 15 МБ. Декоративні кола Академії виконані в CSS.

## Деплой

Потрібна production-збірка Vite: змінені SCSS і доданий окремий entry головної. Node.js має відповідати `engines` встановленого Vite (для поточного lock-файлу: 20.19+ або 22.12+).

```bash
sudo -u ridnavira-com-ua -H /bin/bash <<'DEPLOY'
set -euo pipefail
cd /home/ridnavira-com-ua/web/ridnavira.com.ua/public_html/app
git switch feature/site-build
git pull --ff-only origin feature/site-build
npm ci --include=dev
npm run build
php artisan optimize:clear
php artisan view:cache
git rev-parse HEAD
DEPLOY

curl --fail --silent --output /dev/null https://ridnavira.com.ua/
curl --fail --silent --output /dev/null https://ridnavira.com.ua/fonts/Holos-Bold.ttf
curl --fail --silent --output /dev/null https://ridnavira.com.ua/fonts/KyivRegion-Regular.woff2
```

Міграції, повторний імпорт матеріалів і Composer update не потрібні. Не очищати `storage/app/content`: головна використовує поточний імпорт.

## Перевірки

`npm run build`, `php artisan view:cache`, `php artisan test`, `php vendor/bin/pint --test app/Http/Controllers/HomeController.php tests/Feature/HomeTest.php routes/web.php`.

Feature-тести охоплюють порожній імпорт, реальні переходи, поточний день, зміну року, 29 лютого, відсутні/некоректні файли статей. Візуальну перевірку виконано на ширинах 320, 390, 768, 1024, 1440px.
