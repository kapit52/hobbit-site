<?php
declare(strict_types=1);

/** Тесты normalize_phone() — приведение телефона к виду +7XXXXXXXXXX. */

test('phone: пустой ввод считается валидным и пустым', function () {
    assert_eq(['', true], normalize_phone(''));
});

test('phone: только префикс 7/8 → пусто и валидно', function () {
    assert_eq(['', true], normalize_phone('7'));
    assert_eq(['', true], normalize_phone('8'));
    assert_eq(['', true], normalize_phone('+7'));
});

test('phone: 10 цифр получают префикс +7', function () {
    assert_eq(['+79123456789', true], normalize_phone('9123456789'));
});

test('phone: ведущая 8 заменяется на +7', function () {
    assert_eq(['+79123456789', true], normalize_phone('89123456789'));
});

test('phone: ведущая 7 нормализуется в +7', function () {
    assert_eq(['+79123456789', true], normalize_phone('79123456789'));
});

test('phone: разделители и скобки игнорируются', function () {
    assert_eq(['+79123456789', true], normalize_phone('+7 (912) 345-67-89'));
});

test('phone: слишком короткий номер невалиден', function () {
    assert_eq(['', false], normalize_phone('12345'));
});

test('phone: иностранный 11-значный номер не с 7/8 невалиден', function () {
    assert_eq(['', false], normalize_phone('12345678901'));
});

test('phone: строка без цифр трактуется как пустой ввод', function () {
    assert_eq(['', true], normalize_phone('телефон'));
});
