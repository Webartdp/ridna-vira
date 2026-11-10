# Громади

Публічний каталог: `/hromady`. Кожна громада чи представництво має власну адресу `/hromady/{slug}`.

## Редагування

Адмінка → Сайт → **Громади** (`/admin/community-pages`). Доступ мають адміністратор і редактор.

- **Матеріал**: назва, короткий опис, окреме головне зображення та основний текст.
- **Громада та головна**: тип осередку, місто, область, провідник, посада, адреса, телефони, пошта.
- **Показувати на головній** вмикає картку. **Порядок на головній** задає черговість: менші числа першими, за однакових чисел — за назвою.
- Чернетки, заплановані на майбутнє та видалені громади приховані з каталогу, головної й sitemap. Власні фото автоматично отримують адаптивні WebP-варіанти; без фото використовується символічний образ гаю.
- SEO можна задавати окремо; основне зображення використовується також для OG.

Міграція `2026_11_10_000004_add_community_profiles` створює початкові сторінки з поточного налаштування мапи (або конфігурації для нового встановлення). Наявні адреси не перезаписуються. Початкові тексти містять лише відомі назву й географію та запрошення зв’язатися: докладну історію кожної громади слід додати редакторові.

**Громади та мапа** (`/admin/communities`) залишається окремим налаштуванням географічного довідника й координат. Контакти сторінок спочатку копіюються з мапи, надалі редагуються окремо. Зміна профілю не змінює координати й контакти на мапі автоматично. Кнопка **Всі громади** на головній зберігає попередню адресу `/zviazok#communities-map-title`; поруч додане посилання на новий каталог. Каталог також є в меню **Про центр → Громади**.

У старих збережених CMS-макетах головної при відображенні оновлюються тільки сітка карток і фон блоку громад. Інші тексти та посилання власника зберігаються. Нові макети використовують `[[live:featured-communities]]` і `[[live:community-banner]]`.

Міграція навмисно зберігає профілі та JSON-колонку при rollback, щоб не втратити редакторські тексти й налаштування. Повторний `up` безпечний щодо вже наявних адрес і колонки.

## Зображення

Згенеровано вбудованим imagegen. Символічна сцена, не документальне фото справжньої громади. Файли:

- `public/assets/communities/sacred-grove-800.webp`: 67 654 байти.
- `public/assets/communities/sacred-grove-1600.webp`: 247 348 байтів.
- `public/assets/communities/sacred-grove-2172.webp`: 410 560 байтів.

Завантаження через `srcset`, `sizes`, `loading="lazy"`, `decoding="async"` із заданими розмірами. Старий фотографічний CSS-фон більше не завантажується. Перемикач мов видалено з шапки сайту.

Prompt:

> Use case: photorealistic-natural. Asset type: panoramic banner photograph for Ukrainian spiritual centre Ridna Vira, replacing a group photo. Create a premium, restrained, sacred image evoking Ukrainian native faith and connection with nature: a majestic ancient oak in a quiet Ukrainian oak grove at dawn, warm sunlight through mist, a small ceremonial flame in a simple low stone hearth in the clearing, natural meadow grasses. Landscape composition very wide 3:1, keep oak canopy and fire visible in the middle horizontal band for a website banner crop, calm spacious scene, earthy graphite greens and muted gold, realistic fine textures and natural cinematic light. This is a symbolic atmosphere image, not a documentary photograph of a real community. No people, no buildings, no Christian symbols, no crosses, no swastikas, no runes, no invented religious emblems, no text, no watermark, no fantasy illustration, no kitsch. Generate a landscape image.

## Деплой

Потрібні міграція та збірка стилів. Нових Composer-залежностей немає.

```bash
sudo -u ridnavira-com-ua -H /bin/bash -c '
set -e
cd /home/ridnavira-com-ua/web/ridnavira.com.ua/public_html/app
php artisan down
trap "php artisan up" EXIT
git pull --ff-only origin feature/site-build
php artisan migrate --force
npm ci --no-audit --no-fund
npm run build
php artisan optimize:clear
php artisan filament:optimize-clear
php artisan view:cache
php artisan route:cache
'
```
