<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Тесты чистых функций promo_helpers.php (без обращения к БД):
 * расчёт суммы скидки и её подпись.
 *
 * @see public/includes/promo_helpers.php
 */
#[TestDox('Хелперы промокодов')]
final class PromoHelpersTest extends TestCase
{
    private static function promo(string $type, float $value): array
    {
        return ['discount_type' => $type, 'discount_value' => $value];
    }

    public static function discountProvider(): array
    {
        return [
            // тип, величина, сумма заказа, ожидаемая скидка
            'процент 10% от 1000'                  => ['percent', 10.0,  1000.0, 100.0],
            'процент 20% от 250'                   => ['percent', 20.0,  250.0,  50.0],
            'фиксированная скидка 100'             => ['fixed',   100.0, 1000.0, 100.0],
            'фикс 500 режется до суммы заказа 300' => ['fixed',   500.0, 300.0,  300.0],
            'процент 200% режется до суммы 100'    => ['percent', 200.0, 100.0,  100.0],
        ];
    }

    #[DataProvider('discountProvider')]
    public function testDiscountAmount(string $type, float $value, float $subtotal, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, promo_discount_amount(self::promo($type, $value), $subtotal), 0.001);
    }

    public static function labelProvider(): array
    {
        return [
            'процент без лишних нулей'         => ['percent', 10.0,   '10%'],
            'дробный процент'                 => ['percent', 12.5,   '12.5%'],
            'фиксированная сумма'             => ['fixed',   500.0,  '500 ₽'],
            'фикс. сумма с разделителем тысяч' => ['fixed',  1500.0, '1 500 ₽'],
        ];
    }

    #[DataProvider('labelProvider')]
    public function testPromoLabel(string $type, float $value, string $expected): void
    {
        $this->assertSame($expected, promo_label(self::promo($type, $value)));
    }
}
