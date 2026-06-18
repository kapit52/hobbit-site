<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Тесты чистых функций booking_helpers.php: подписи статусов брони и генерация
 * временных слотов (с 12:00 до 22:00, шаг 30 минут — из констант config.php).
 *
 * @see public/includes/booking_helpers.php
 */
#[TestDox('Хелперы бронирования')]
final class BookingHelpersTest extends TestCase
{
    public static function statusProvider(): array
    {
        return [
            'pending → Ожидает подтверждения'      => ['pending',   'Ожидает подтверждения'],
            'confirmed → Подтверждена'             => ['confirmed', 'Подтверждена'],
            'rejected → Отклонена'                 => ['rejected',  'Отклонена'],
            'cancelled → Отменена'                 => ['cancelled', 'Отменена'],
            'completed → Завершена'                => ['completed', 'Завершена'],
            'неизвестный статус возвращается как есть' => ['xyz',    'xyz'],
        ];
    }

    #[DataProvider('statusProvider')]
    public function testBookingStatusLabel(string $status, string $expected): void
    {
        $this->assertSame($expected, booking_status_label($status));
    }

    #[TestDox('первый слот — время открытия 12:00')]
    public function testFirstSlotIsOpeningTime(): void
    {
        $slots = booking_time_slots();
        $this->assertSame('12:00:00', $slots[0]);
    }

    #[TestDox('последний слот — 21:30 (перед закрытием в 22:00)')]
    public function testLastSlotBeforeClosing(): void
    {
        $slots = booking_time_slots();
        $this->assertSame('21:30:00', $slots[array_key_last($slots)]);
    }

    #[TestDox('число слотов = (22−12)·60 ÷ 30 = 20')]
    public function testSlotCount(): void
    {
        $this->assertCount(20, booking_time_slots());
    }

    #[TestDox('шаг ровно 30 минут: есть 12:30, нет 12:15')]
    public function testSlotStepIsThirtyMinutes(): void
    {
        $slots = booking_time_slots();
        $this->assertContains('12:30:00', $slots);
        $this->assertContains('13:00:00', $slots);
        $this->assertNotContains('12:15:00', $slots);
    }
}
