<?php
/**
 * Приводит телефон к виду +7XXXXXXXXXX.
 * Возвращает [значение, корректно?].
 *   - пустой ввод (или только префикс)  → ['', true]
 *   - валидный номер                     → ['+7XXXXXXXXXX', true]
 *   - некорректный                       → ['', false]
 */
function normalize_phone(string $raw): array {
    $d = preg_replace('/\D/', '', $raw);
    if ($d === '' || $d === '7' || $d === '8') {
        return ['', true];
    }
    if (strlen($d) === 11 && ($d[0] === '7' || $d[0] === '8')) {
        $d = substr($d, 1);
    }
    if (strlen($d) !== 10) {
        return ['', false];
    }
    return ['+7' . $d, true];
}
