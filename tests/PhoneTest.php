<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Тесты normalize_phone() — приведение телефона к виду +7XXXXXXXXXX.
 * Функция возвращает пару [значение, признак корректности].
 *
 * @see public/includes/phone.php
 */
#[TestDox('Нормализация телефона')]
final class PhoneTest extends TestCase
{
    public static function phoneProvider(): array
    {
        return [
            'пустой ввод считается валидным и пустым'        => ['',                    ['', true]],
            'только префикс 7 → пусто и валидно'             => ['7',                   ['', true]],
            'только префикс 8 → пусто и валидно'             => ['8',                   ['', true]],
            'только +7 → пусто и валидно'                    => ['+7',                  ['', true]],
            '10 цифр получают префикс +7'                    => ['9123456789',          ['+79123456789', true]],
            'ведущая 8 заменяется на +7'                     => ['89123456789',         ['+79123456789', true]],
            'ведущая 7 нормализуется в +7'                   => ['79123456789',         ['+79123456789', true]],
            'разделители и скобки игнорируются'              => ['+7 (912) 345-67-89',  ['+79123456789', true]],
            'слишком короткий номер невалиден'               => ['12345',               ['', false]],
            'иностранный 11-значный номер не с 7/8 невалиден' => ['12345678901',        ['', false]],
            'строка без цифр трактуется как пустой ввод'     => ['телефон',             ['', true]],
        ];
    }

    #[DataProvider('phoneProvider')]
    #[TestDox('$input → ожидается заданный результат')]
    public function testNormalizePhone(string $input, array $expected): void
    {
        $this->assertSame($expected, normalize_phone($input));
    }
}
