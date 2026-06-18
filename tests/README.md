# Юнит-тесты «Ширского уголка» (PHPUnit)

Модульные тесты на **PHPUnit 10** — стандартном фреймворке тестирования для PHP.
Покрывают «чистые» функции-хелперы, которые не обращаются к БД, поэтому тесты
быстрые и не требуют поднятой базы.

PHPUnit подключается как самостоятельный **PHAR-архив** (`tests/tools/phpunit.phar`),
без Composer — в духе всего проекта (внешних зависимостей в продакшене нет;
PHPUnit нужен только для разработки).

## Что покрыто

| Тест-класс | Тестируемые функции | Источник |
|------------|---------------------|----------|
| `PhoneTest.php` | `normalize_phone()` | `public/includes/phone.php` |
| `OrderHelpersTest.php` | `order_status_label()`, `order_type_label()`, `parse_menu_price()` | `public/includes/order_helpers.php` |
| `PromoHelpersTest.php` | `promo_discount_amount()`, `promo_label()` | `public/includes/promo_helpers.php` |
| `BookingHelpersTest.php` | `booking_status_label()`, `booking_time_slots()` | `public/includes/booking_helpers.php` |

Всего **47 тестов / 49 проверок**. Параметризованные кейсы оформлены через
data-провайдеры (`#[DataProvider]`).

Функции, завязанные на БД (`validate_promo`, `hall_has_capacity`,
`table_is_available`, `update_*_status` и т. п.), здесь намеренно не трогаются —
им нужны интеграционные тесты с тестовой базой.

## Подготовка: скачать PHPUnit (один раз)

`phpunit.phar` не хранится в репозитории. Скачать в `tests/tools/`:

```powershell
# Windows PowerShell
New-Item -ItemType Directory -Force tests/tools | Out-Null
Invoke-WebRequest https://phar.phpunit.de/phpunit-10.5.phar -OutFile tests/tools/phpunit.phar
```
```bash
# Linux/Mac
mkdir -p tests/tools && curl -L https://phar.phpunit.de/phpunit-10.5.phar -o tests/tools/phpunit.phar
```

## Как запустить

PHP в этом проекте живёт в Docker, поэтому тесты гоняются через контейнер.
Конфигурация — `phpunit.xml` в корне проекта (он сам находит классы `*Test.php`).

**Если стек поднят** (`docker compose up -d`):
```bash
docker compose exec web php tests/tools/phpunit.phar --testdox
```

**Если стек не поднят** — одноразовый контейнер (проект монтируется в `/app`):
```powershell
# Windows PowerShell
docker run --rm -v "${PWD}:/app" -w /app php:8.2-apache php tests/tools/phpunit.phar --testdox
```
```bash
# Linux/Mac
docker run --rm -v "$PWD:/app" -w /app php:8.2-apache php tests/tools/phpunit.phar --testdox
```

**Локально**, если установлен PHP CLI ≥ 8.2:
```bash
php tests/tools/phpunit.phar --testdox
```

Флаг `--testdox` даёт человекочитаемый вывод; без него — компактные точки.
Код выхода: **`0`** — все прошли, **`1`** — есть упавшие (удобно для CI).

## Результат

```
OK (47 tests, 49 assertions)
```

## Как добавить тест

1. Создай класс `<Имя>Test.php` в этой папке (PHPUnit сам подхватит `*Test.php`).
2. Унаследуй его от `PHPUnit\Framework\TestCase`.
3. Методы-тесты называй `testЧтоПроверяем()` и проверяй результат:
   `assertSame`, `assertEqualsWithDelta`, `assertCount`, `assertContains` и т. д.
4. Для набора входных данных используй data-провайдер с атрибутом
   `#[DataProvider('имяМетодаПровайдера')]` (провайдер — `public static`).
5. Если функция в новом файле `includes/*.php` — подключи его в `bootstrap.php`.
