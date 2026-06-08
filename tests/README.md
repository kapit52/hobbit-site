# Юнит-тесты «Ширского уголка»

Лёгкий набор тестов на чистом PHP — **без PHPUnit и Composer** (в духе всего
проекта: внешних зависимостей нет). Покрывают «чистые» функции-хелперы, которые
не обращаются к БД, поэтому тесты быстрые и не требуют поднятой базы.

## Что покрыто

| Файл | Тестируемые функции | Источник |
|------|---------------------|----------|
| `test_phone.php` | `normalize_phone()` | `public/includes/phone.php` |
| `test_order_helpers.php` | `order_status_label()`, `order_type_label()`, `parse_menu_price()` | `public/includes/order_helpers.php` |
| `test_promo_helpers.php` | `promo_discount_amount()`, `promo_label()` | `public/includes/promo_helpers.php` |
| `test_booking_helpers.php` | `booking_status_label()`, `booking_time_slots()` | `public/includes/booking_helpers.php` |

Функции, завязанные на БД (`validate_promo`, `hall_has_capacity`,
`table_is_available`, `update_*_status` и т.п.), здесь намеренно не трогаются —
им нужны интеграционные тесты с тестовой базой.

## Как запустить

PHP в этом проекте живёт в Docker, поэтому тесты гоняются через контейнер.

**Если стек уже поднят** (`docker compose up -d`):
```bash
docker compose exec web php /var/www/html/tests/run.php
```

**Если стек не поднят** — одноразовый контейнер (проект монтируется в `/app`):
```bash
# Linux/Mac
docker run --rm -v "$PWD:/app" -w /app php:8.2-apache php tests/run.php
```
```powershell
# Windows PowerShell
docker run --rm -v "${PWD}:/app" -w /app php:8.2-apache php tests/run.php
```

**Локально**, если установлен PHP CLI ≥ 8.0:
```bash
php tests/run.php
```

## Результат

Раннер печатает по строке на тест (`✓`/`✗`) и итог. Код выхода:
**`0`** — все прошли, **`1`** — есть упавшие (удобно для CI).

```
Итого: 28 тестов · ✓ 28 прошли · ✗ 0 упали
```

## Как добавить тест

1. Создай файл `test_<что-то>.php` в этой папке (раннер сам подхватит все
   `test_*.php`).
2. Внутри регистрируй кейсы функцией `test('описание', function () { ... })`.
3. Проверяй результат хелперами из `framework.php`:
   `assert_eq`, `assert_true`, `assert_false`, `assert_float_eq`, `fail`.
4. Если функция лежит в новом файле `includes/*.php` — подключи его в `run.php`.
