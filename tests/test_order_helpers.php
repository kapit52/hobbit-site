<?php
declare(strict_types=1);

/** Тесты чистых функций order_helpers.php. */

test('order_status_label: известные статусы переводятся', function () {
    assert_eq('Корзина', order_status_label('cart'));
    assert_eq('Ожидает подтверждения', order_status_label('pending'));
    assert_eq('Подтверждён', order_status_label('confirmed'));
    assert_eq('Завершён', order_status_label('completed'));
    assert_eq('Отменён', order_status_label('cancelled'));
});

test('order_status_label: неизвестный статус возвращается как есть', function () {
    assert_eq('whatever', order_status_label('whatever'));
});

test('order_type_label: типы заказа переводятся', function () {
    assert_eq('Доставка', order_type_label('delivery'));
    assert_eq('В заведении', order_type_label('dine_in'));
    assert_eq('С собой', order_type_label('takeaway'));
});

test('order_type_label: null и неизвестное → прочерк', function () {
    assert_eq('—', order_type_label(null));
    assert_eq('—', order_type_label('unknown'));
});

test('parse_menu_price: простое число', function () {
    assert_float_eq(500.0, parse_menu_price('500'));
});

test('parse_menu_price: пробелы-разделители и символ валюты убираются', function () {
    assert_float_eq(1200.0, parse_menu_price('1 200 ₽'));
});

test('parse_menu_price: текст «от» отбрасывается', function () {
    assert_float_eq(500.0, parse_menu_price('от 500 ₽'));
});

test('parse_menu_price: запятая как десятичный разделитель', function () {
    assert_float_eq(250.5, parse_menu_price('250,50'));
});

test('parse_menu_price: нечисловая строка → 0', function () {
    assert_float_eq(0.0, parse_menu_price('бесплатно'));
    assert_float_eq(0.0, parse_menu_price(''));
});
