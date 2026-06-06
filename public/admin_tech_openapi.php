<?php
/**
 * Отдаёт OpenAPI 3.0 спецификацию HTTP-API сайта «Ширский уголок» в JSON.
 * Используется вкладкой «API (Swagger)» технической админки.
 * Доступно только администратору.
 */
require_once __DIR__ . '/includes/admin_auth_lite.php';

ini_set('display_errors', '0'); // чистый JSON для Swagger UI
header('Content-Type: application/json; charset=utf-8');

// Базовый URL текущего деплоя (учитывает подпапку, напр. /hobbit-site/public).
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir     = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$baseUrl = $scheme . '://' . $host . $dir;

$textResponse = [
    'description' => 'Текстовый ответ (plain text)',
    'content' => ['text/plain' => ['schema' => ['type' => 'string']]],
];

$spec = [
    'openapi' => '3.0.3',
    'info' => [
        'title'       => 'Ширский уголок — HTTP API',
        'version'     => '1.0.0',
        'description' => "Эндпоинты сайта-ресторана: корзина, загрузка изображений (админ) и служебный API технической панели.\n\n"
                       . "Большинство публичных эндпоинтов работают с PHP-сессией (cookie). Админские — требуют сессию `SHIREADMIN`.",
    ],
    'servers' => [['url' => $baseUrl, 'description' => 'Текущий сервер']],
    'tags' => [
        ['name' => 'Корзина',   'description' => 'Управление корзиной гостя (PHP-сессия)'],
        ['name' => 'Админ',     'description' => 'Загрузка изображений — требует прав администратора'],
        ['name' => 'Тех. панель', 'description' => 'Метрики, статусы, логи (только администратор)'],
        ['name' => 'Авторизация', 'description' => 'Вход и регистрация'],
    ],
    'paths' => [
        '/add_to_cart.php' => [
            'post' => [
                'tags' => ['Корзина'],
                'summary' => 'Добавить блюдо в корзину',
                'description' => 'Создаёт заказ-черновик (status=cart) в сессии при первом обращении и добавляет позицию.',
                'requestBody' => [
                    'required' => true,
                    'content' => ['application/x-www-form-urlencoded' => ['schema' => [
                        'type' => 'object',
                        'required' => ['menu_item_id'],
                        'properties' => [
                            'menu_item_id' => ['type' => 'integer', 'example' => 12],
                            'quantity'     => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                        ],
                    ]]],
                ],
                'responses' => ['200' => $textResponse],
            ],
        ],
        '/update_cart.php' => [
            'post' => [
                'tags' => ['Корзина'],
                'summary' => 'Изменить количество позиции',
                'description' => 'quantity ≤ 0 удаляет позицию из корзины.',
                'requestBody' => [
                    'required' => true,
                    'content' => ['application/x-www-form-urlencoded' => ['schema' => [
                        'type' => 'object',
                        'required' => ['item_id', 'quantity'],
                        'properties' => [
                            'item_id'  => ['type' => 'integer', 'description' => 'id строки order_items'],
                            'quantity' => ['type' => 'integer'],
                        ],
                    ]]],
                ],
                'responses' => ['200' => $textResponse],
            ],
        ],
        '/remove_from_cart.php' => [
            'get' => [
                'tags' => ['Корзина'],
                'summary' => 'Удалить позицию из корзины',
                'parameters' => [[
                    'name' => 'id', 'in' => 'query', 'required' => true,
                    'schema' => ['type' => 'integer'], 'description' => 'id строки order_items',
                ]],
                'responses' => ['200' => $textResponse],
            ],
        ],
        '/includes/admin_upload.php' => [
            'post' => [
                'tags' => ['Админ'],
                'summary' => 'Загрузка / получение по URL / удаление изображения',
                'description' => "Действие задаётся полем `action`:\n"
                               . "- `upload` — multipart-файл `file` (JPEG/PNG/GIF/WebP)\n"
                               . "- `fetch_url` — скачать по `url`\n"
                               . "- `delete` — удалить по `path`",
                'security' => [['adminSession' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => ['multipart/form-data' => ['schema' => [
                        'type' => 'object',
                        'required' => ['action'],
                        'properties' => [
                            'action' => ['type' => 'string', 'enum' => ['upload', 'fetch_url', 'delete']],
                            'file'   => ['type' => 'string', 'format' => 'binary'],
                            'url'    => ['type' => 'string'],
                            'path'   => ['type' => 'string'],
                        ],
                    ]]],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'JSON-результат',
                        'content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'ok'    => ['type' => 'boolean'],
                                'path'  => ['type' => 'string', 'example' => 'uploads/img_1717.jpg'],
                                'error' => ['type' => 'string'],
                            ],
                        ]]],
                    ],
                ],
            ],
        ],
        '/admin_tech_api.php' => [
            'get' => [
                'tags' => ['Тех. панель'],
                'summary' => 'Метрики / статусы / логи / системная информация',
                'description' => "Действие задаётся параметром `action`: `metrics`, `status`, `sysinfo`, `logs`, `loggers_get`.\n"
                               . "Для `logs` используйте `offset` (байтовый курсор) для инкрементального опроса в реальном времени.",
                'security' => [['adminSession' => []]],
                'parameters' => [
                    ['name' => 'action', 'in' => 'query', 'required' => true,
                     'schema' => ['type' => 'string', 'enum' => ['metrics', 'status', 'sysinfo', 'logs', 'loggers_get']]],
                    ['name' => 'offset', 'in' => 'query', 'schema' => ['type' => 'integer'], 'description' => 'Байтовый курсор для action=logs'],
                    ['name' => 'level',  'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Фильтр по минимальному уровню'],
                    ['name' => 'channel','in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Фильтр по каналу'],
                ],
                'responses' => ['200' => ['description' => 'JSON', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]]],
            ],
            'post' => [
                'tags' => ['Тех. панель'],
                'summary' => 'Управление логерами и логом',
                'description' => "Действие в поле `action`: `loggers_set`, `log_clear`, `log_test`. Требует CSRF-токен.",
                'security' => [['adminSession' => []]],
                'requestBody' => [
                    'required' => true,
                    'content' => ['application/x-www-form-urlencoded' => ['schema' => [
                        'type' => 'object',
                        'required' => ['action', 'csrf_token'],
                        'properties' => [
                            'action'     => ['type' => 'string', 'enum' => ['loggers_set', 'log_clear', 'log_test']],
                            'csrf_token' => ['type' => 'string'],
                            'channel'    => ['type' => 'string'],
                            'enabled'    => ['type' => 'integer', 'enum' => [0, 1]],
                            'min_level'  => ['type' => 'string'],
                        ],
                    ]]],
                ],
                'responses' => ['200' => ['description' => 'JSON', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]]],
            ],
        ],
        '/admin_login.php' => [
            'post' => [
                'tags' => ['Авторизация'],
                'summary' => 'Вход администратора',
                'description' => 'Устанавливает сессию SHIREADMIN. Принимает почту или логин.',
                'requestBody' => [
                    'required' => true,
                    'content' => ['application/x-www-form-urlencoded' => ['schema' => [
                        'type' => 'object',
                        'required' => ['username', 'password'],
                        'properties' => [
                            'username' => ['type' => 'string', 'example' => 'admin@shire-corner.local'],
                            'password' => ['type' => 'string', 'format' => 'password'],
                        ],
                    ]]],
                ],
                'responses' => ['302' => ['description' => 'Редирект на admin_panel.php при успехе']],
            ],
        ],
        '/login.php' => [
            'post' => [
                'tags' => ['Авторизация'],
                'summary' => 'Вход гостя',
                'requestBody' => [
                    'required' => true,
                    'content' => ['application/x-www-form-urlencoded' => ['schema' => [
                        'type' => 'object',
                        'required' => ['email', 'password'],
                        'properties' => [
                            'email'    => ['type' => 'string', 'format' => 'email'],
                            'password' => ['type' => 'string', 'format' => 'password'],
                        ],
                    ]]],
                ],
                'responses' => ['302' => ['description' => 'Редирект при успехе']],
            ],
        ],
    ],
    'components' => [
        'securitySchemes' => [
            'adminSession' => [
                'type' => 'apiKey',
                'in'   => 'cookie',
                'name' => 'SHIREADMIN',
                'description' => 'Cookie-сессия администратора (выставляется admin_login.php).',
            ],
        ],
    ],
];

echo json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
