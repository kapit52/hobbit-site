<?php
declare(strict_types=1);

/** Тесты чистых функций promo_helpers.php (без обращения к БД). */

function _promo(string $type, float $value): array
{
    return ['discount_type' => $type, 'discount_value' => $value];
}

test('promo_discount_amount: процент от суммы', function () {
    assert_float_eq(100.0, promo_discount_amount(_promo('percent', 10), 1000.0));
    assert_float_eq(50.0, promo_discount_amount(_promo('percent', 20), 250.0));
});

test('promo_discount_amount: фиксированная скидка', function () {
    assert_float_eq(100.0, promo_discount_amount(_promo('fixed', 100), 1000.0));
});

test('promo_discount_amount: скидка не превышает сумму заказа', function () {
    // фикс 500 при заказе 300 — режется до 300
    assert_float_eq(300.0, promo_discount_amount(_promo('fixed', 500), 300.0));
    // процент 200% при заказе 100 — режется до 100
    assert_float_eq(100.0, promo_discount_amount(_promo('percent', 200), 100.0));
});

test('promo_label: процент без лишних нулей', function () {
    assert_eq('10%', promo_label(_promo('percent', 10)));
    assert_eq('12.5%', promo_label(_promo('percent', 12.5)));
});

test('promo_label: фиксированная сумма с разделителем тысяч', function () {
    assert_eq('500 ₽', promo_label(_promo('fixed', 500)));
    assert_eq('1 500 ₽', promo_label(_promo('fixed', 1500)));
});
