# Фотографии сайта «Ширский уголок»

Здесь — все фото, которые нужны сайту, их размеры и промты для генерации.

## Как загрузить фото (без правки кода)

1. Зайдите в админку → раздел **«Галерея»**.
2. В панели **«Добавить изображение»** загрузите файл (перетащите или выберите; до 20 МБ, JPEG/PNG/WebP/GIF).
3. После загрузки внизу появятся **два выпадающих списка**:
   - **первый** — тип: **Команда / Блюдо / Галерея / Главная**;
   - **второй** — конкретное место (подставляется автоматически под выбранный тип).
4. Выберите, куда поставить фото, и нажмите **«Сохранить»** — фото сразу появится на сайте.

> Имя файла и подпись вводить не нужно — фото привязывается к выбранному месту,
> а подпись для галереи подставляется автоматически.
> Чтобы заменить фото — просто загрузите новое и выберите то же место (старый файл
> при этом удаляется из `uploads/`, если больше нигде не используется).
> Состояние всех слотов видно в блоке **«Слоты сайта»** (что заполнено, что пусто).

---

## Главная страница (тип «Главная»)

| Слот (ключ) | Размер | Что на фото | Промт для генерации |
|-------------|--------|-------------|---------------------|
| `hero-main` | 1920×1080 | Большое фото в шапке — фасад/зал таверны в сумерках | `A cozy hobbit-style tavern exterior at dusk, warm glowing windows, green round door, old oak tree, cobblestone path, fairy lights, warm amber light, photorealistic` |
| `legend-hostess` | 720×900 | Блок «Легенда» — хозяйка у очага | `Beautiful young female tavern owner by the fireplace, professional attire, warm amber light, cozy medieval fantasy interior, photorealistic` |

---

## Команда таверны (тип «Команда», главная страница)

> Хозяйка и шеф-повар — одно лицо: **Капитонова Елизавета Ильинична**
> (молодая девушка ~25–30 лет, длинные каштановые волосы, тёплая улыбка).

| Слот (ключ) | Размер | Кто | Промт для генерации |
|-------------|--------|-----|---------------------|
| `team-chef` | 800×800 | Шеф-повар и хозяйка — Елизавета | `Portrait of a beautiful young woman chef, 25-30 years old, long chestnut hair, warm smile, white chef coat with apron, medieval fantasy tavern kitchen, warm candlelight, photorealistic` |
| `team-pastry` | 600×450 | Кондитер — Тимофей Беляев | `Young male pastry chef, 30s, holding a freshly baked pie, warm rustic kitchen, medieval fantasy tavern style` |
| `team-barista` | 600×450 | Бариста — Анна Соколова | `Young female barista at rustic coffee counter, warm cozy light, fantasy tavern style` |
| `team-host` | 600×450 | Хостес — Михаил Дрозд | `Young male host at tavern entrance, welcoming smile, smart casual medieval-inspired costume` |
| `team-sommelier` | 600×450 | Сомелье — Вера Хмельницкая | `Female sommelier, 40s, holding wine glass in medieval tavern cellar, warm amber light` |
| `team-musician` | 600×450 | Музыкант — Олег Стрижевский | `Male violinist, 40s, playing violin by fireplace in medieval tavern, atmospheric candlelight` |
| `team-gardener` | 600×450 | Садовник — Пётр Иволгин | `Elderly male gardener, 60s, in lush herb garden near tavern, holding fresh herbs, kind face` |

---

## Галерея / «Атмосфера уголка» (тип «Галерея», главная + страница галереи)

Это **именованные слоты** — выберите тип «Галерея» и нужный пункт во втором списке.
На главной в блоке «Атмосфера» показываются первые 6, остальное — на странице галереи.
Если нужно добавить ещё фото сверх списка — выберите **«+ Свободное фото (без слота)»**.

| Слот (ключ) | Размер | Что на фото | Промт для генерации |
|-------------|--------|-------------|---------------------|
| `gallery-hall` | 1200×800 | Зал у очага вечером | `Cozy hobbit tavern interior, fireplace, wooden beams, low warm lighting, candles, photorealistic` |
| `gallery-bar` | 1200×800 | Барная стойка с элями | `Rustic tavern bar counter with ale taps, wooden shelves with bottles, warm candlelight, photorealistic` |
| `gallery-hearth` | 1200×800 | Живой огонь в очаге крупно | `Close-up of crackling fireplace in medieval tavern, warm orange glow, stone hearth, photorealistic` |
| `gallery-door` | 1200×800 | Круглая зелёная дверь (вход) | `Round green hobbit-style wooden door of a tavern, brass handle, lantern, cobblestone, warm light, photorealistic` |
| `gallery-terrace` | 1200×800 | Летняя терраса вечером | `Outdoor tavern terrace in summer evening, fairy lights, wooden furniture, lush plants, photorealistic` |
| `gallery-vip` | 1200×800 | VIP-зал у камина | `Private VIP dining room near fireplace, candlelit table, medieval luxury interior, photorealistic` |
| `gallery-music` | 1200×800 | Скрипач у очага (пятница) | `Musician playing violin by the fireplace in a medieval tavern, guests, warm glow, photorealistic` |
| `gallery-garden` | 1200×800 | Зимний сад · травы и свечи | `Cozy tavern winter garden corner with hanging herbs, candles, jars, warm light, photorealistic` |

---

## Меню — фото блюд (тип «Блюдо»)

Выберите тип **«Блюдо»** и нужное блюдо во втором списке — фото привяжется к нему.
Рекомендованный размер для всех — **800×600**. Общий стиль для промтов:
`…, rustic wooden table, warm candlelight, medieval fantasy tavern, food photography, appetizing, photorealistic`.

### Горячие угощения

| Блюдо | Размер | Промт |
|-------|--------|-------|
| Утка, томлённая в брусничном эле | 800×600 | `Braised duck in lingonberry ale glaze, half a farm duck, glossy dark sauce, fresh herbs` |
| Рёбрышки кабана на углях | 800×600 | `Grilled wild boar ribs on charcoal, smoky glaze, rosemary` |
| Жаркое путника в горшочке | 800×600 | `Rustic meat and potato stew in a clay pot, steaming, herbs` |
| Оленина с ягодным соусом | 800×600 | `Roasted venison slices with berry sauce, seasonal greens` |
| Форель с берёзовых углей | 800×600 | `Whole grilled trout from birch charcoal, lemon, dill` |
| Курник деревенский | 800×600 | `Traditional Russian layered chicken pie (kurnik), golden crust, one slice cut` |
| Грибная похлёбка с белыми | 800×600 | `Creamy wild porcini mushroom soup in a bowl, herbs, bread on the side` |
| Тыквенный суп с копчёным окороком | 800×600 | `Pumpkin soup with smoked ham, cream swirl, pumpkin seeds` |
| Голубцы хозяйки в томлёном соусе | 800×600 | `Cabbage rolls (golubtsy) in tomato sour-cream sauce, fresh herbs` |
| Бефстроганов с гречаными блинцами | 800×600 | `Beef stroganoff with buckwheat blini, creamy mushroom sauce` |
| Каша гречневая с лесными грибами | 800×600 | `Buckwheat porridge with forest mushrooms, butter, herbs` |
| Кролик, тушённый в сметане | 800×600 | `Rabbit braised in sour cream sauce, herbs, rustic plate` |

### Ласковые лакомства (десерты)

| Блюдо | Размер | Промт |
|-------|--------|-------|
| Медовик «Старая хозяйка» | 800×600 | `Slice of Russian honey layer cake (medovik), creamy layers, honey drizzle` |
| Яблочный пирог с корицей | 800×600 | `Apple pie with cinnamon, golden crust, dusted with sugar` |
| Тыквенный чизкейк | 800×600 | `Pumpkin cheesecake slice, creamy, autumn spices` |
| Гурьевская каша с орехами | 800×600 | `Guryev semolina porridge with nuts and candied fruit, caramelized top` |
| Блинцы с гречишным мёдом | 800×600 | `Stack of thin Russian blini with buckwheat honey` |
| Кисель из лесных ягод | 800×600 | `Forest berry kissel in a glass, thick fruit drink, berries` |
| Творожная запеканка с изюмом | 800×600 | `Cottage cheese casserole (zapekanka) with raisins, golden top` |
| Пряники медовые с глазурью | 800×600 | `Honey gingerbread cookies with white glaze, rustic` |
| Груша, запечённая в меду | 800×600 | `Pear baked in honey, caramelized, cinnamon, on a plate` |
| Шарлотка с лесными ягодами | 800×600 | `Charlotte sponge cake with forest berries, dusted sugar` |
| Мороженое на топлёных сливках | 800×600 | `Scoops of baked-cream ice cream in a bowl, golden tone` |

### Чарующие напитки

> Стиль для напитков: `…, in a rustic mug or glass, warm candlelight, cozy medieval tavern, beverage photography, photorealistic`.

| Напиток | Размер | Промт |
|---------|--------|-------|
| Эль «Ширский» светлый | 800×600 | `Glass mug of light golden ale, frothy head, on a wooden bar` |
| Тёмный медовый эль | 800×600 | `Mug of dark honey ale, rich amber color, frothy head` |
| Медовуха хозяйки на травах | 800×600 | `Mug of herbal mead, golden, sprigs of herbs` |
| Сбитень горячий | 800×600 | `Hot sbiten spiced honey drink in a clay mug, steam, spices` |
| Морс из лесных ягод | 800×600 | `Forest berry mors in a glass jug, deep red, fresh berries` |
| Травяной чай таверны | 800×600 | `Herbal tea in a rustic cup with a teapot, dried herbs` |
| Глинтвейн пряный | 800×600 | `Spiced mulled wine in a glass mug, cinnamon, orange, star anise` |
| Грог у очага | 800×600 | `Hot grog in a mug by the fireplace, citrus slice, steam` |
| Квас домашний на ржаном хлебе | 800×600 | `Glass of homemade rye bread kvass, dark, bubbly` |
| Настойка на бруснике | 800×600 | `Small glass of lingonberry liqueur, deep red, berries` |
| Узвар из сухофруктов | 800×600 | `Uzvar dried-fruit compote in a glass jug, amber color` |
| Кофе по-таврски со сливками | 800×600 | `Cup of coffee with cream foam, rustic saucer` |

### Яства и ломтики (закуски)

| Закуска | Размер | Промт |
|---------|--------|-------|
| Сырная доска таверны | 800×600 | `Cheese board with assorted cheeses, grapes, nuts, honey, wooden board` |
| Мясная доска охотника | 800×600 | `Charcuterie board with cured meats, sausages, pickles, wooden board` |
| Хлеб на закваске с травяным маслом | 800×600 | `Sourdough bread with herb butter, rustic board` |
| Грузди солёные со сметаной | 800×600 | `Salted milk-cap mushrooms with sour cream and onion, bowl` |
| Селёдочка с печёным картофелем | 800×600 | `Herring with baked potatoes, onion, dill` |
| Паштет из печени с брусникой | 800×600 | `Liver pâté with lingonberry jam and toasted bread` |
| Драники с грибным соусом | 800×600 | `Potato pancakes (draniki) with mushroom sauce and sour cream` |
| Соленья из погреба | 800×600 | `Assorted pickled vegetables from the cellar, jar and bowls` |
| Тёплый салат с томлёной свёклой | 800×600 | `Warm salad with braised beetroot, greens, soft cheese` |
| Икра грибная с гренками | 800×600 | `Mushroom "caviar" spread with toasted bread` |
| Жульен в булочке | 800×600 | `Mushroom julienne baked in a bread bun, creamy, cheese top` |
| Брускетты с вяленым томатом | 800×600 | `Bruschetta with sun-dried tomato and herbs, rustic` |
