<?php
/**
 * Именованные «слоты» для фиксированных фотографий сайта (главная, команда и т.п.).
 *
 * Идея: админ загружает фото в админке и привязывает его к слоту с английским
 * ключом (см. images-needed.md). Страницы сайта показывают фото слота через
 * site_image(). Если фото для слота нет — показывается заглушка.
 *
 * Хранилище — таблица gallery_images (поле slot_key).
 */

/** Канонический список слотов: ключ => [раздел, размер, что на фото]. */
function site_image_slots(): array {
    return [
        // --- Главная страница ---
        'hero-main'      => ['Главная', '1920×1080', 'Большое фото в шапке — фасад/зал таверны'],
        'legend-hostess' => ['Главная', '720×900',   'Блок «Легенда» — хозяйка у очага'],
        // --- Команда таверны (главная) ---
        'team-chef'      => ['Команда', '800×800', 'Шеф-повар и хозяйка — Капитонова Елизавета'],
        'team-pastry'    => ['Команда', '600×450', 'Кондитер — Тимофей Беляев'],
        'team-barista'   => ['Команда', '600×450', 'Бариста — Анна Соколова'],
        'team-host'      => ['Команда', '600×450', 'Хостес — Михаил Дрозд'],
        'team-sommelier' => ['Команда', '600×450', 'Сомелье — Вера Хмельницкая'],
        'team-musician'  => ['Команда', '600×450', 'Музыкант — Олег Стрижевский'],
        'team-gardener'  => ['Команда', '600×450', 'Садовник — Пётр Иволгин'],
        // --- Галерея / «Атмосфера уголка» (главная + страница галереи) ---
        'gallery-hall'    => ['Галерея', '1200×800', 'Зал у очага · вечер'],
        'gallery-bar'     => ['Галерея', '1200×800', 'Барная стойка · медовый эль'],
        'gallery-hearth'  => ['Галерея', '1200×800', 'Очаг · живой огонь крупно'],
        'gallery-door'    => ['Галерея', '1200×800', 'Круглая зелёная дверь · вход'],
        'gallery-terrace' => ['Галерея', '1200×800', 'Терраса · летний вечер'],
        'gallery-vip'     => ['Галерея', '1200×800', 'VIP-зал у камина'],
        'gallery-music'   => ['Галерея', '1200×800', 'Скрипач у очага · пятница'],
        'gallery-garden'  => ['Галерея', '1200×800', 'Зимний сад · травы и свечи'],
    ];
}

/**
 * Группы для каскадных списков загрузки в админке.
 * Первый список — тип (Команда / Блюдо / Галерея / Главная), второй — конкретная цель.
 * Значение цели: ключ слота (team-*, gallery-*, hero-main…), 'dish:<id>' для блюда,
 * либо '' для свободного фото галереи (без слота).
 */
function image_assign_groups(mysqli $conn): array {
    $bySection = [];
    foreach (site_image_slots() as $key => $info) {
        $bySection[$info[0]][] = [$key, $info[2] . ' · ' . $info[1]];
    }
    if (isset($bySection['Галерея'])) {
        $bySection['Галерея'][] = ['', '+ Свободное фото (без слота)'];
    }

    $dishes = [];
    try {
        $res = $conn->query("SELECT id, title, category FROM menu_items WHERE category <> 'decor' ORDER BY category, id");
        if ($res) while ($r = $res->fetch_assoc()) {
            $dishes[] = ['dish:' . $r['id'], $r['title'] . ' · ' . $r['category']];
        }
    } catch (mysqli_sql_exception $e) {
        // таблицы может не быть — список блюд будет пуст
    }

    // Порядок групп в первом списке: Команда, Блюдо, Галерея, Главная
    $groups = [];
    if (!empty($bySection['Команда'])) $groups['Команда'] = $bySection['Команда'];
    if (!empty($dishes))               $groups['Блюдо']   = $dishes;
    if (!empty($bySection['Галерея'])) $groups['Галерея'] = $bySection['Галерея'];
    if (!empty($bySection['Главная'])) $groups['Главная'] = $bySection['Главная'];
    return $groups;
}

/** Два каскадных <select> (тип + цель) для формы загрузки. $prefix — уникальный префикс id. */
function render_assign_selects(array $groups, string $prefix): string {
    $sel = 'padding:9px 12px;border:1px solid var(--line);border-radius:var(--r-sm);font:inherit;font-size:0.9rem;';
    $opts = '';
    foreach (array_keys($groups) as $t) {
        $opts .= '<option value="' . htmlspecialchars($t) . '">' . htmlspecialchars($t) . '</option>';
    }
    return '<select id="' . $prefix . '_type" onchange="populateTargets(\'' . $prefix . '\')" title="Тип фото" style="' . $sel . '">' . $opts . '</select>'
         . '<select name="assign_target" id="' . $prefix . '_target" required title="Куда поставить фото" style="' . $sel . 'min-width:240px;max-width:340px;"></select>';
}

/** true, если ключ — известный именованный слот. */
function is_named_slot(string $slot): bool {
    return array_key_exists($slot, site_image_slots());
}

/** Категория gallery_images для именованного слота. */
function slot_category(string $slot): string {
    return strpos($slot, 'team-') === 0 ? 'team' : 'gallery';
}

/** <option>-ы для выбора слота в админке (сгруппированы по разделам). */
function site_slot_options_html(): string {
    $groups = [];
    foreach (site_image_slots() as $key => $info) {
        $groups[$info[0]][$key] = $info;
    }
    $html = '<option value="">— Свободное фото галереи —</option>';
    foreach ($groups as $section => $slots) {
        $html .= '<optgroup label="' . htmlspecialchars($section) . '">';
        foreach ($slots as $key => $info) {
            $html .= '<option value="' . htmlspecialchars($key) . '">'
                . htmlspecialchars($info[2] . ' · ' . $info[1])
                . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html;
}

/**
 * Путь к картинке слота (или '' если не задано / файла нет на диске).
 * Результаты кэшируются на время запроса.
 */
function site_image(mysqli $conn, string $slot): string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $res = $conn->query("SELECT slot_key, image_path FROM gallery_images WHERE slot_key <> ''");
            if ($res) while ($r = $res->fetch_assoc()) $cache[$r['slot_key']] = $r['image_path'];
        } catch (mysqli_sql_exception $e) {
            // Таблица ещё не создана — слоты пока пусты
        }
    }
    $path = $cache[$slot] ?? '';
    if ($path !== '' && file_exists(__DIR__ . '/../' . $path)) {
        return $path;
    }
    return '';
}
