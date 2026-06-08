<?php
declare(strict_types=1);

/** Тесты чистых функций booking_helpers.php (зависят только от констант config.php). */

test('booking_status_label: известные статусы переводятся', function () {
    assert_eq('Ожидает подтверждения', booking_status_label('pending'));
    assert_eq('Подтверждена', booking_status_label('confirmed'));
    assert_eq('Отклонена', booking_status_label('rejected'));
    assert_eq('Отменена', booking_status_label('cancelled'));
    assert_eq('Завершена', booking_status_label('completed'));
});

test('booking_status_label: неизвестный статус возвращается как есть', function () {
    assert_eq('xyz', booking_status_label('xyz'));
});

test('booking_time_slots: первый и последний слот в рабочих часах', function () {
    $slots = booking_time_slots();
    assert_eq('12:00:00', $slots[0]);
    assert_eq('21:30:00', $slots[count($slots) - 1]);
});

test('booking_time_slots: число слотов при шаге 30 мин с 12:00 до 22:00', function () {
    // (22 - 12) * 60 / 30 = 20 слотов, конец 22:00 не включается
    assert_eq(20, count(booking_time_slots()));
});

test('booking_time_slots: шаг ровно 30 минут', function () {
    $slots = booking_time_slots();
    assert_true(in_array('12:30:00', $slots, true), 'ожидался слот 12:30:00');
    assert_true(in_array('13:00:00', $slots, true), 'ожидался слот 13:00:00');
    assert_false(in_array('12:15:00', $slots, true), 'слота 12:15:00 быть не должно');
});
