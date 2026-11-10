# Sitemap, robots, Open Graph та іконки

`/sitemap.xml` генерується при кожному запиті з актуальних каталогів, стану CMS та активних товарів. Cron або ручна генерація не потрібні. Новий опублікований матеріал з’являється в наступній відповіді sitemap; прихований, видалений або запланований на майбутнє — виключається. Після настання запланованої дати матеріал потрапляє до карти автоматично. `lastmod` для редакторських змін і товарів містить справжній час оновлення. Кеш відповіді вимагає повторної перевірки, тому CDN не має віддавати застарілий XML без перевірки.

Карта не містить адмінку, MCP, кошик, замовлення, платежі, посилання на завантаження та відомі перенаправлення. Для імпортованих статей додатково перевіряється наявність файлу в локальному сховищі. Тексти статей не завантажуються з БД для генерації sitemap.

`/robots.txt` відкриває публічний сайт, містить посилання на sitemap та закриває службові розділи. Додавання нових публічних сторінок не потребує зміни robots. Приватні сторінки додатково мають X-Robots-Tag noindex. Sitemap повідомляє про сторінки; дату повторного обходу визначає пошукова система.

## Метадані

У публічних HTML-сторінках формується один узгоджений комплект title, description, canonical та метаданих:

- Open Graph: `og:title`, `og:type`, `og:url`, `og:description`, `og:site_name`, `og:locale`, `og:image`, `og:image:url`, `og:image:secure_url`, `og:image:type`, `og:image:width`, `og:image:height`, `og:image:alt`.
- Для статей і новин: `article:published_time`, `article:modified_time`, `article:section`, якщо відповідні дані відомі.
- Для вбудованого YouTube: `og:video`, `og:video:secure_url`, `og:video:type`, `og:video:width`, `og:video:height`.
- Twitter Card: card, title, description, image та image:alt.

Розміри й MIME зображень читаються з локального файлу; зовнішні зображення сервер не завантажує заради метаданих. Якщо розміри невідомі, поля не вигадуються. Для завантажених обкладинок використовується варіант до 1200 пікселів. Основне фото матеріалу автоматично стає фото прев’ю. Коли фото немає, використовується іконка бренду. Facebook app ID, неіснуючі мовні версії, автори та дати не вигадуються.

JSON-LD пов’язує Organization, WebSite, WebPage / AboutPage / ContactPage / CollectionPage, NewsArticle / Article, ImageObject та BreadcrumbList. Товар має окремий Product із Offer: реальною ціною в UAH і наявністю. Довільних рейтингів або відгуків немає. Логотип організації береться з поточних налаштувань сайту або з локального логотипу бренду. Тексти виводяться з безпечним JSON-екрануванням.

## Іконки

Комплект побудований на наявному векторному символі `public/assets/figma/home/logo-sva.svg`, у кольорах бренду. Растрові файли вже входять у Git; генерація на сервері та сторонні бібліотеки не потрібні.

- `public/favicon.svg` — векторна іконка браузера.
- `public/favicon.ico` — розміри 16, 32 і 48 в одному файлі.
- `public/favicon-16x16.png`, `public/favicon-32x32.png` — PNG-варіанти.
- `public/apple-touch-icon.png` — 180×180 для iPhone/iPad.
- `public/icons/icon-192.png`, `public/icons/icon-512.png` — іконки для manifest і резервного соціального прев’ю.
- `public/site.webmanifest` — назва, мова, кольори й іконки сайту. Service worker не додається.

Посилання на комплект підключені у загальному layout; адмінка також використовує favicon бренду.

## Оновлення сервера

Залежності, схема БД і CSS/JS у цьому оновленні не змінені. Composer, міграції та npm build для нього не потрібні.

```bash
sudo -u ridnavira-com-ua -H /bin/bash -c '
set -e
cd /home/ridnavira-com-ua/web/ridnavira.com.ua/public_html/app
git pull --ff-only origin feature/site-build
php artisan optimize:clear
php artisan filament:optimize-clear
php artisan view:cache
php artisan route:cache
'
```

Перевірте `/sitemap.xml`, `/robots.txt`, `/favicon.svg`, `/apple-touch-icon.png`, `/site.webmanifest` і вихідний HTML новини. Sitemap можна подати в Google Search Console. Перевірки JSON-LD виконуються також через Google Rich Results Test; наявність валідних даних не гарантує конкретного вигляду результату пошуку.
