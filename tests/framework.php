<?php
declare(strict_types=1);

/**
 * Микро-фреймворк для юнит-тестов «Ширского уголка».
 *
 * Без внешних зависимостей (без PHPUnit/Composer) — в духе всего проекта.
 * Тест регистрируется функцией test('имя', fn), внутри используются assert_*.
 * Любая неудачная проверка бросает TestFailure и помечает тест как упавший,
 * но не прерывает прогон остальных тестов.
 */

final class TestFailure extends Exception {}

$GLOBALS['__TESTS'] = [];

/** Регистрирует тест-кейс. */
function test(string $name, callable $fn): void
{
    $GLOBALS['__TESTS'][] = ['name' => $name, 'fn' => $fn];
}

/** Принудительно проваливает тест. */
function fail(string $msg): void
{
    throw new TestFailure($msg);
}

function assert_true($cond, string $msg = 'ожидалось true'): void
{
    if ($cond !== true) {
        fail($msg . ' (получено: ' . var_export($cond, true) . ')');
    }
}

function assert_false($cond, string $msg = 'ожидалось false'): void
{
    if ($cond !== false) {
        fail($msg . ' (получено: ' . var_export($cond, true) . ')');
    }
}

/** Строгое сравнение (===): тип и значение, для массивов — ключи/порядок. */
function assert_eq($expected, $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        fail(($msg !== '' ? $msg . ': ' : '')
            . 'ожидалось ' . var_export($expected, true)
            . ', получено ' . var_export($actual, true));
    }
}

/** Сравнение чисел с плавающей точкой с допуском. */
function assert_float_eq(float $expected, float $actual, string $msg = '', float $eps = 0.001): void
{
    if (abs($expected - $actual) > $eps) {
        fail(($msg !== '' ? $msg . ': ' : '') . "ожидалось ~{$expected}, получено {$actual}");
    }
}

/** Прогоняет все зарегистрированные тесты, печатает отчёт, возвращает код выхода. */
function run_tests(): int
{
    $tests    = $GLOBALS['__TESTS'];
    $passed   = 0;
    $failures = [];

    foreach ($tests as $t) {
        try {
            ($t['fn'])();
            $passed++;
            echo "  ✓ {$t['name']}\n";
        } catch (Throwable $e) {
            $failures[] = [$t['name'], $e->getMessage()];
            echo "  ✗ {$t['name']}\n";
        }
    }

    echo "\n";
    if ($failures) {
        echo "ПРОВАЛЫ:\n";
        foreach ($failures as [$name, $msg]) {
            echo "  • {$name}\n      {$msg}\n";
        }
        echo "\n";
    }

    $failed = count($failures);
    $total  = $passed + $failed;
    echo "Итого: {$total} тестов · ✓ {$passed} прошли · ✗ {$failed} упали\n";

    return $failed === 0 ? 0 : 1;
}
