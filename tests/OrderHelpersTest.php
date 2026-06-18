<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Тесты чистых функций order_helpers.php: подписи статусов и типов заказа,
 * разбор цены из строки в число.
 *
 * @see public/includes/order_helpers.php
 */
#[TestDox('Хелперы заказов')]
final class OrderHelpersTest extends TestCase
{
    public static function statusProvider(): array
    {
        return [
            'cart → Корзина'                       => ['cart',      'Корзина'],
            'pending → Ожидает подтверждения'      => ['pending',   'Ожидает подтверждения'],
            'confirmed → Подтверждён'              => ['confirmed', 'Подтверждён'],
            'completed → Завершён'                 => ['completed', 'Завершён'],
            'cancelled → Отменён'                  => ['cancelled', 'Отменён'],
            'неизвестный статус возвращается как есть' => ['whatever', 'whatever'],
        ];
    }

    #[DataProvider('statusProvider')]
    public function testOrderStatusLabel(string $status, string $expected): void
    {
        $this->assertSame($expected, order_status_label($status));
    }

    public static function typeProvider(): array
    {
        return [
            'delivery → Доставка'     => ['delivery', 'Доставка'],
            'dine_in → В заведении'   => ['dine_in',  'В заведении'],
            'takeaway → С собой'      => ['takeaway', 'С собой'],
            'null → прочерк'          => [null,       '—'],
            'неизвестный тип → прочерк' => ['unknown', '—'],
        ];
    }

    #[DataProvider('typeProvider')]
    public function testOrderTypeLabel(?string $type, string $expected): void
    {
        $this->assertSame($expected, order_type_label($type));
    }

    public static function priceProvider(): array
    {
        return [
            'простое число'                          => ['500',      500.0],
            'пробелы-разделители и символ валюты'     => ['1 200 ₽',  1200.0],
            'текст «от» отбрасывается'                => ['от 500 ₽', 500.0],
            'запятая как десятичный разделитель'      => ['250,50',   250.5],
            'нечисловая строка → 0'                   => ['бесплатно', 0.0],
            'пустая строка → 0'                       => ['',         0.0],
        ];
    }

    #[DataProvider('priceProvider')]
    public function testParseMenuPrice(string $input, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, parse_menu_price($input), 0.001);
    }
}
