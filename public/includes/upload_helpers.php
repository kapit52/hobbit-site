<?php
/**
 * Очистка физических файлов в public/uploads/ при удалении или замене картинок
 * в админке. Файл удаляется с диска ТОЛЬКО если на него больше не ссылается
 * ни одна запись в БД — чтобы не убить изображение, используемое в другом месте
 * (например, одно и то же фото назначено и блюду, и слоту галереи).
 */

/** Таблицы и колонки, где могут храниться пути к загруженным файлам. */
function upload_path_columns(): array {
    return [
        ['menu_items', 'image_path'],
        ['gallery_images', 'image_path'],
        ['reviews', 'photo_path'],
    ];
}

/** true, если путь ведёт в нашу папку uploads/ (а не внешний URL или пусто). */
function is_managed_upload(string $path): bool {
    $path = trim($path);
    return $path !== '' && strpos($path, 'uploads/') === 0 && strpos($path, '..') === false;
}

/** Текущий путь к картинке из одной записи по id (или '' если нет). */
function current_upload_path(mysqli $conn, string $table, string $col, int $id): string {
    try {
        $res = $conn->query("SELECT `$col` AS p FROM `$table` WHERE id = " . $id);
        if ($res && ($row = $res->fetch_assoc())) {
            return (string)($row['p'] ?? '');
        }
    } catch (mysqli_sql_exception $e) {
        // Таблицы/колонки может не быть в старой БД — путь считаем неизвестным
    }
    return '';
}

/** Сколько записей в БД ещё ссылается на этот путь. */
function upload_reference_count(mysqli $conn, string $path): int {
    $count = 0;
    $esc = $conn->real_escape_string($path);
    foreach (upload_path_columns() as [$table, $col]) {
        try {
            $res = $conn->query("SELECT COUNT(*) AS c FROM `$table` WHERE `$col` = '$esc'");
            if ($res) $count += (int)$res->fetch_assoc()['c'];
        } catch (mysqli_sql_exception $e) {
            // Таблицы/колонки нет — пропускаем
        }
    }
    return $count;
}

/**
 * Удаляет файл из uploads/, если он наш и больше нигде не используется.
 * Вызывать ПОСЛЕ удаления/обновления записи в БД, чтобы счётчик ссылок
 * уже не учитывал саму изменённую запись.
 *
 * @return bool true, если файл был удалён с диска.
 */
function delete_upload_if_unused(mysqli $conn, ?string $path): bool {
    if ($path === null) return false;
    $path = trim($path);
    if (!is_managed_upload($path)) return false;
    if (upload_reference_count($conn, $path) > 0) return false; // ещё используется

    $full = __DIR__ . '/../' . $path; // includes/ -> public/uploads/...
    if (is_file($full)) {
        return @unlink($full);
    }
    return false;
}
