# Рідна Віра

Офіційний сайт Духовного центру «Рідна Віра» для домену `ridnavira.com.ua`.

## Стек

- Laravel 13
- PHP 8.3+
- Blade
- Bootstrap 5
- SCSS
- Vite
- MySQL / MariaDB

## Запуск

```bash
cp .env.example .env
composer install
php artisan key:generate
npm install
npm run build
php artisan serve
```

## Перший етап

Створено Laravel-каркас, адаптивну шапку, рухомий рядок, головну сторінку, блок громад, Коло Свароже, блоки новин/статей/творчості, футер і маршрути основних сторінок.
