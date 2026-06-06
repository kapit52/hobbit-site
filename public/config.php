<?php
// Конфигурация (не коммитить пароли в публичный репозиторий)
define('ADMIN_PASSWORD', 'shire123');
define('SITE_NAME', 'Ширский Уголок');
define('MAIL_FROM', 'noreply@shire-corner.local');

// Бронирование: длительность слота в минутах, окно конфликта в часах
define('BOOKING_SLOT_MINUTES', 30);
define('BOOKING_CONFLICT_HOURS', 2);

// Рабочие часы бронирования
define('BOOKING_OPEN_HOUR', 12);
define('BOOKING_CLOSE_HOUR', 22);
