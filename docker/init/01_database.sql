-- ============================================================
-- Snapshot of the shire_corner database (schema + data).
-- Auto-loaded by MySQL on a FRESH volume (docker compose up).
-- Regenerate after admin changes:  docker/snapshot-db.ps1 (or .sh)
-- ============================================================
-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: shire_corner
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `booking_status_history`
--

DROP TABLE IF EXISTS `booking_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_status_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `old_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` enum('admin','system','customer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  CONSTRAINT `booking_status_history_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_status_history`
--

LOCK TABLES `booking_status_history` WRITE;
/*!40000 ALTER TABLE `booking_status_history` DISABLE KEYS */;
INSERT INTO `booking_status_history` VALUES (1,2,NULL,'pending','customer','Заявка отправлена','2026-05-31 19:10:22'),(2,2,'pending','rejected','admin','','2026-05-31 19:19:13');
/*!40000 ALTER TABLE `booking_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `guest_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guest_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guest_email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `party_size` int NOT NULL DEFAULT '2',
  `table_id` int DEFAULT NULL,
  `status` enum('pending','confirmed','rejected','cancelled','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `admin_comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `table_id` (`table_id`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (2,3,'Елизавета','+7999свммакп№№№\"','kapitonova.2004@inbox.ru','2026-06-04','16:30:00',3,5,'rejected','Можно кальян пожалуйста',NULL,'2026-05-31 19:10:22','2026-05-31 19:19:13');
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gallery_images`
--

DROP TABLE IF EXISTS `gallery_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gallery_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slot_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` enum('gallery','dish','team') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gallery',
  `sort_order` int NOT NULL DEFAULT '0',
  `alt_text` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slot_key` (`slot_key`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gallery_images`
--

LOCK TABLES `gallery_images` WRITE;
/*!40000 ALTER TABLE `gallery_images` DISABLE KEYS */;
INSERT INTO `gallery_images` VALUES (6,'hero-main','hero-main','uploads/img_1780512441_2288.png','gallery',0,NULL,NULL,'2026-06-03 18:47:36'),(8,'legend-hostess','legend-hostess','uploads/img_1780863271_3623.png','gallery',0,NULL,NULL,'2026-06-03 18:58:26'),(9,'team-chef','','uploads/img_1780863286_2475.png','team',0,NULL,NULL,'2026-06-03 19:02:53'),(10,'team-pastry','','uploads/img_1780513503_9399.png','team',0,NULL,NULL,'2026-06-03 19:05:10'),(11,'team-barista','','uploads/img_1780513663_8393.png','team',0,NULL,NULL,'2026-06-03 19:07:48'),(12,'team-host','','uploads/img_1780513934_6508.jpg','team',0,NULL,NULL,'2026-06-03 19:12:19'),(13,'team-sommelier','','uploads/img_1780514015_8906.jpg','team',0,NULL,NULL,'2026-06-03 19:13:40'),(14,'team-musician','','uploads/img_1780514119_1194.jpg','team',0,NULL,NULL,'2026-06-03 19:15:23'),(15,'team-gardener','','uploads/img_1780514179_1989.jpg','team',0,NULL,NULL,'2026-06-03 19:16:25'),(17,'gallery-hall','Зал у очага · вечер','uploads/img_1780548051_7597.jpg','gallery',0,NULL,NULL,'2026-06-04 04:40:57'),(18,'gallery-bar','Барная стойка · медовый эль','uploads/img_1780548061_5698.jpg','gallery',0,NULL,NULL,'2026-06-04 04:41:06'),(19,'gallery-hearth','Очаг · живой огонь крупно','uploads/img_1780548071_7079.jpg','gallery',0,NULL,NULL,'2026-06-04 04:41:17'),(20,'gallery-door','Круглая зелёная дверь · вход','uploads/img_1780548081_2414.jpg','gallery',0,NULL,NULL,'2026-06-04 04:41:27'),(21,'gallery-terrace','Терраса · летний вечер','uploads/img_1780548092_5456.jpg','gallery',0,NULL,NULL,'2026-06-04 04:41:37'),(22,'gallery-vip','VIP-зал у камина','uploads/img_1780548101_7995.jpg','gallery',0,NULL,NULL,'2026-06-04 04:41:46'),(23,'gallery-music','Скрипач у очага · пятница','uploads/img_1780548110_1791.jpg','gallery',0,NULL,NULL,'2026-06-04 04:41:54'),(24,'gallery-garden','Зимний сад · травы и свечи','uploads/img_1780548118_8638.jpg','gallery',0,NULL,NULL,'2026-06-04 04:42:02');
/*!40000 ALTER TABLE `gallery_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu_items`
--

DROP TABLE IF EXISTS `menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `badge` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_special` tinyint(1) NOT NULL DEFAULT '0',
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `images_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_items`
--

LOCK TABLES `menu_items` WRITE;
/*!40000 ALTER TABLE `menu_items` DISABLE KEYS */;
INSERT INTO `menu_items` VALUES (1,'Утка, томлённая в брусничном эле','Половинка фермерской утки, шесть часов в чугунке с тёмным элем, брусникой и можжевельником. Подаём с печёным яблоком.','Горячие угощения','920','480 г','Гордость кухни',1,'uploads/img_1780549038_4767.jpg',NULL),(2,'Рёбрышки кабана на углях','Дымные рёбра из дровяной печи под медово-горчичной глазурью, с печёным картофелем и квашеной капустой.','Горячие угощения','1180','600 г','Хит',0,'uploads/img_1780549171_8102.jpg',NULL),(3,'Жаркое путника в горшочке','Говядина, коренья и белые грибы, томлённые под хлебной крышкой. Горшочек открывают прямо у стола.','Горячие угощения','740','450 г','',1,'uploads/img_1780549255_5256.jpg',NULL),(4,'Оленина с ягодным соусом','Вырезка дикого оленя средней прожарки, соус из чёрной смородины и розмарина, гратен из корня сельдерея.','Горячие угощения','1340','380 г','Шеф советует',1,'uploads/img_1780550166_3475.jpg',NULL),(5,'Форель с берёзовых углей','Целая речная форель, фаршированная лимоном и травами, на углях ольхи. С молодым картофелем и зеленью.','Горячие угощения','880','500 г','',0,'uploads/img_1780550232_1469.jpg',NULL),(6,'Курник деревенский','Высокий пирог с курицей, грибами, гречей и яйцом — печётся с утра, режется на щедрые ломти.','Горячие угощения','560','400 г','',0,'uploads/img_1780550322_6799.jpg',NULL),(7,'Похлёбка с белыми грибами','Густой суп на белых грибах со сливками, картофелем и ржаными гренками с чесноком. Рецепт 1893 года.','Горячие угощения','430','380 мл','',0,'uploads/img_1780550481_9439.jpg',NULL),(8,'Тыквенный суп с копчёным окороком','Бархатная похлёбка из печёной тыквы, копчёный окорок, тыквенные семечки и капля орехового масла.','Горячие угощения','460','350 мл','Сезонное',1,'uploads/img_1780550672_1318.jpg',NULL),(9,'Голубцы хозяйки в томлёном соусе','Капустные голубцы с говядиной и рисом, тушёные в томатно-сметанном соусе с укропом.','Горячие угощения','490','420 г','',0,'uploads/img_1780550737_9778.jpg',NULL),(10,'Бефстроганов с гречаными блинцами','Тонкие полоски говядины в сливочно-грибном соусе, поданные с тёплыми гречаными блинцами.','Горячие угощения','680','380 г','',0,'uploads/img_1780550840_3743.jpg',NULL),(11,'Каша гречневая с лесными грибами','Рассыпчатая гречка, томлённая с белыми и лисичками, жареным луком и топлёным маслом.','Горячие угощения','380','320 г','Постное',0,'uploads/img_1780551711_8399.jpg',NULL),(12,'Кролик, тушённый в сметане','Фермерский кролик в сметанном соусе с тимьяном и чесноком, гарнир из печёных кореньев.','Горячие угощения','960','450 г','',0,'uploads/img_1780551778_2085.jpg',NULL),(13,'Сырная доска таверны','Пять выдержанных сыров, гречишный мёд, грецкий орех, виноград и тёплые крекеры из печи.','Яства и ломтики','690','320 г','Под эль',1,'uploads/img_1780566581_2494.jpg',NULL),(14,'Мясная доска охотника','Вяленая оленина, копчёный окорок, домашние колбаски и маринованные грузди с горчицей.','Яства и ломтики','780','340 г','',0,'uploads/img_1780566666_5969.jpg',NULL),(15,'Хлеб на закваске с травяным маслом','Тёплый каравай из каменной печи, домашнее масло с чесноком и зеленью, морская соль.','Яства и ломтики','190','220 г','',0,'uploads/img_1780566739_9369.jpg',NULL),(16,'Грузди солёные со сметаной','Хрусткие лесные грузди по бабушкиному засолу, лук и густая деревенская сметана.','Яства и ломтики','340','200 г','',0,'uploads/img_1780566811_4479.jpg',NULL),(17,'Селёдочка с печёным картофелем','Малосольное филе под маслом, тёплый печёный картофель, маринованный лук и укроп.','Яства и ломтики','320','260 г','',0,'uploads/img_1780566881_8280.jpg',NULL),(18,'Паштет из печени с брусникой','Нежный паштет из куриной печени с коньяком, брусничный конфитюр и тосты из хлеба на закваске.','Яства и ломтики','380','180 г','Новинка',0,'uploads/img_1780567074_1926.jpg',NULL),(19,'Драники с грибным соусом','Картофельные драники, жаренные до хруста, со сливочно-грибным соусом из лесных грибов.','Яства и ломтики','360','240 г','',0,'uploads/img_1780567133_7188.jpg',NULL),(20,'Соленья из погреба','Бочковые огурцы, квашеная капуста с клюквой, маринованные помидоры и чеснок.','Яства и ломтики','290','300 г','Под настойку',0,'uploads/img_1780567269_3343.jpg',NULL),(21,'Тёплый салат с томлёной свёклой','Печёная свёкла, козий сыр, грецкий орех и руккола под медово-горчичной заправкой.','Яства и ломтики','420','240 г','',0,'uploads/img_1780567410_9500.jpg',NULL),(22,'Икра грибная с гренками','Икра из жареных лесных грибов с луком, подаётся с ржаными чесночными гренками.','Яства и ломтики','350','200 г','Постное',0,'uploads/img_1780567579_7141.jpg',NULL),(23,'Жульен в булочке','Курица и грибы под сырной корочкой, запечённые внутри хрустящей булочки.','Яства и ломтики','390','220 г','',0,'uploads/img_1780567661_2665.jpg',NULL),(24,'Брускетты с вяленым томатом','Поджаренный хлеб, вяленые томаты, моцарелла, базилик и капля бальзамика.','Яства и ломтики','310','180 г','',0,'uploads/img_1780567813_8806.jpg',NULL),(25,'Медовик «Старая хозяйка»','Восемь тонких медовых коржей со сметанным кремом, грецкий орех и капля цветочного мёда.','Ласковые лакомства','360','200 г','Любимое',1,'uploads/img_1780551886_9668.jpg',NULL),(26,'Яблочный пирог с корицей','Открытый пирог из песочного теста, томлёные яблоки с корицей и тёплый карамельный соус.','Ласковые лакомства','300','220 г','',0,'uploads/img_1780551952_2403.jpg',NULL),(27,'Тыквенный чизкейк','Нежный чизкейк на тыквенном пюре с пряностями и песочной основой из имбирного печенья.','Ласковые лакомства','340','180 г','Сезонное',1,'uploads/img_1780552041_6670.jpg',NULL),(28,'Гурьевская каша с орехами','Манная каша на топлёных сливочных пенках, лесные орехи, мёд и ягодное варенье.','Ласковые лакомства','320','250 г','',0,'uploads/img_1780552422_9665.jpg',NULL),(29,'Блинцы с гречишным мёдом','Тонкие блины со сливочным маслом, гречишным мёдом и густой сметаной.','Ласковые лакомства','260','4 шт.','',0,'uploads/img_1780552582_7688.jpg',NULL),(30,'Кисель из лесных ягод','Брусника, черника и малина в тёплом густом киселе — как варили в Шире из поколения в поколение.','Чарующие напитки','210','300 мл','Постное',0,'uploads/img_1780552706_9355.jpg',NULL),(31,'Творожная запеканка с изюмом','Воздушная запеканка из деревенского творога с изюмом, ванилью и ягодным соусом.','Ласковые лакомства','280','220 г','',0,'uploads/img_1780552774_4104.jpg',NULL),(32,'Пряники медовые с глазурью','Ароматные пряники на меду с корицей и гвоздикой, сахарная глазурь — к чаю у очага.','Ласковые лакомства','180','150 г','',0,'uploads/img_1780552847_5670.jpg',NULL),(33,'Груша, запечённая в меду','Половинки груши, томлённые с мёдом и тимьяном, шарик сливочного мороженого и карамель.','Ласковые лакомства','290','200 г','Новинка',0,'uploads/img_1780552918_9805.jpg',NULL),(34,'Шарлотка с лесными ягодами','Воздушный бисквит с яблоками и лесными ягодами, припорошённый сахарной пудрой.','Ласковые лакомства','270','200 г','',0,'uploads/img_1780555702_7530.jpg',NULL),(35,'Мороженое на топлёных сливках','Домашнее пломбирное мороженое из топлёных сливок с вареньем из лесной земляники.','Ласковые лакомства','220','150 г','',0,'uploads/img_1780555876_7275.jpg',NULL),(36,'Эль «Ширский» светлый','Домашний светлый эль с медовой ноткой, варится раз в неделю в подвале таверны.','Чарующие напитки','290','500 мл','Хит',1,'uploads/img_1780556055_7640.jpg',NULL),(37,'Тёмный медовый эль','Плотный тёмный эль на гречишном мёде с карамельным послевкусием. Подаём чуть охлаждённым.','Чарующие напитки','320','500 мл','Гордость пивовара',1,'uploads/img_1780556245_4579.jpg',NULL),(38,'Медовуха хозяйки на травах','Слабоалкогольная медовуха по старому рецепту, настоянная на луговых травах и мяте.','Чарующие напитки','280','400 мл','',0,'uploads/img_1780556335_9220.jpg',NULL),(39,'Сбитень горячий','Согревающий напиток с мёдом, имбирём, гвоздикой и корицей — для холодных вечеров.','Чарующие напитки','190','300 мл','Безалкогольный',0,'uploads/img_1780556579_2497.jpg',NULL),(40,'Морс из лесных ягод','Насыщенный морс из брусники и клюквы с мёдом — кисло-сладкий и бодрящий.','Чарующие напитки','170','400 мл','Безалкогольный',0,'uploads/img_1780565288_2005.jpg',NULL),(41,'Травяной чай таверны','Авторский сбор: чабрец, мята, мелисса, шиповник и липа. Подаём в глиняном чайнике.','Чарующие напитки','220','500 мл','Безалкогольный',0,'uploads/img_1780565392_6598.jpg',NULL),(42,'Глинтвейн пряный','Красное вино с апельсином, мёдом и зимними пряностями, томлённое на медленном огне.','Чарующие напитки','340','250 мл','Сезонное',0,'uploads/img_1780565712_7389.jpg',NULL),(43,'Грог у очага','Тёмный ром, чёрный чай, мёд, лимон и пряности — крепкий и согревающий.','Чарующие напитки','360','250 мл','',0,'uploads/img_1780565970_4628.jpg',NULL),(44,'Квас домашний на ржаном хлебе','Натуральный хлебный квас двойного брожения с изюмом — кислинка и хлебный аромат.','Чарующие напитки','150','400 мл','Безалкогольный',0,'uploads/img_1780566033_7675.jpg',NULL),(45,'Настойка на бруснике','Крепкая домашняя настойка на лесной бруснике, подаётся охлаждённой в гранёной стопке.','Чарующие напитки','260','50 мл','',0,'uploads/img_1780566345_2859.jpg',NULL),(46,'Узвар из сухофруктов','Томлёный компот из сушёных яблок, груш и чернослива с мёдом — тёплый или холодный.','Чарующие напитки','160','400 мл','Безалкогольный',0,'uploads/img_1780566416_1002.jpg',NULL),(47,'Кофе по-таврски со сливками','Двойной эспрессо с топлёными сливками и каплей мёда, щепотка корицы сверху.','Чарующие напитки','240','180 мл','',0,'uploads/img_1780566486_1072.jpg',NULL);
/*!40000 ALTER TABLE `menu_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `menu_item_id` int NOT NULL,
  `item_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_price` decimal(10,2) NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (5,2,1,'Утка, томлённая в брусничном эле',920.00,1),(6,3,1,'Утка, томлённая в брусничном эле',920.00,1),(7,4,2,'Рёбрышки кабана на углях',1180.00,1),(8,5,1,'Утка, томлённая в брусничном эле',920.00,1),(9,6,47,'Кофе по-таврски со сливками',240.00,1),(10,6,32,'Пряники медовые с глазурью',180.00,1),(11,6,24,'Брускетты с вяленым томатом',310.00,1),(12,6,7,'Похлёбка с белыми грибами',430.00,1),(13,6,1,'Утка, томлённая в брусничном эле',920.00,1),(14,7,11,'Каша гречневая с лесными грибами',380.00,1);
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_status_history`
--

DROP TABLE IF EXISTS `order_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_status_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `old_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` enum('admin','system','customer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_status_history`
--

LOCK TABLES `order_status_history` WRITE;
/*!40000 ALTER TABLE `order_status_history` DISABLE KEYS */;
INSERT INTO `order_status_history` VALUES (1,2,'cart','pending','customer','Заказ оформлен','2026-05-31 19:44:48'),(2,3,'cart','pending','customer','Заказ оформлен','2026-05-31 19:46:56'),(3,4,'cart','pending','customer','Заказ оформлен','2026-05-31 19:48:19'),(4,4,'pending','confirmed','admin',NULL,'2026-05-31 19:51:13'),(5,4,'confirmed','preparing','admin',NULL,'2026-05-31 19:51:20'),(6,4,'preparing','ready','admin',NULL,'2026-05-31 19:51:24'),(7,4,'ready','completed','admin',NULL,'2026-05-31 19:51:27'),(8,3,'pending','cancelled','admin',NULL,'2026-05-31 19:51:37'),(9,3,'cancelled','preparing','admin',NULL,'2026-05-31 19:51:44'),(10,3,'preparing','ready','admin',NULL,'2026-05-31 19:51:54'),(11,3,'ready','confirmed','admin',NULL,'2026-05-31 19:51:59'),(12,3,'confirmed','preparing','admin',NULL,'2026-05-31 19:52:16'),(13,2,'pending','confirmed','admin',NULL,'2026-05-31 19:52:19'),(14,2,'confirmed','preparing','admin',NULL,'2026-05-31 19:52:20'),(15,3,'preparing','cancelled','admin',NULL,'2026-05-31 19:52:25'),(16,3,'cancelled','pending','admin',NULL,'2026-05-31 19:52:30'),(17,3,'pending','confirmed','admin',NULL,'2026-05-31 19:52:33'),(18,3,'confirmed','preparing','admin',NULL,'2026-05-31 19:52:35'),(19,3,'preparing','pending','admin',NULL,'2026-05-31 19:53:15'),(20,3,'pending','confirmed','admin',NULL,'2026-05-31 19:53:19'),(21,3,'confirmed','preparing','admin',NULL,'2026-05-31 19:53:20'),(22,4,'completed','pending','admin',NULL,'2026-06-03 18:46:02'),(23,4,'pending','completed','admin',NULL,'2026-06-04 05:41:19'),(24,2,'preparing','completed','admin',NULL,'2026-06-04 05:41:27'),(25,3,'preparing','completed','admin',NULL,'2026-06-04 05:41:36'),(26,6,'cart','pending','customer','Заказ оформлен','2026-06-04 10:14:48'),(27,6,'pending','confirmed','admin','хорошо, мы вас поняла','2026-06-04 10:15:29'),(28,6,'confirmed','completed','admin',NULL,'2026-06-04 10:15:57');
/*!40000 ALTER TABLE `order_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `customer_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `customer_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `promo_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `customer_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `total_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('cart','pending','confirmed','preparing','ready','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cart',
  `order_type` enum('dine_in','delivery','takeaway') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total` decimal(10,2) DEFAULT '0.00',
  `delivery_address` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_user` (`user_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (2,3,'Елизавета','84848484994','Бебра пж',NULL,0.00,'',920.00,'completed','dine_in',920.00,'','2026-05-31 19:41:49','2026-06-04 05:41:27'),(3,3,'Елизавета','33333333333','',NULL,0.00,'',920.00,'completed','dine_in',920.00,'','2026-05-31 19:45:21','2026-06-04 05:41:36'),(4,3,'Елизавета','83838383838','пися бебра',NULL,0.00,'Улица пушкина, дом колотушкина',1180.00,'completed','delivery',1180.00,'Улица пушкина, дом колотушкина','2026-05-31 19:47:49','2026-06-04 05:41:19'),(5,NULL,'','',NULL,NULL,0.00,NULL,0.00,'cart',NULL,0.00,NULL,'2026-05-31 20:04:48','2026-05-31 20:04:48'),(6,6,'\'uoiyutryte','+79053241046','Можете принести кофе через некоторое время после основых блюд',NULL,0.00,'',2080.00,'completed','dine_in',2080.00,'','2026-06-04 10:11:00','2026-06-04 10:15:57'),(7,NULL,'','',NULL,NULL,0.00,NULL,0.00,'cart',NULL,0.00,NULL,'2026-06-05 16:00:52','2026-06-05 16:00:52');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promo_codes`
--

DROP TABLE IF EXISTS `promo_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promo_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_type` enum('percent','fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percent',
  `discount_value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `min_order` decimal(10,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `expires_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promo_codes`
--

LOCK TABLES `promo_codes` WRITE;
/*!40000 ALTER TABLE `promo_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `promo_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `restaurant_tables`
--

DROP TABLE IF EXISTS `restaurant_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `restaurant_tables` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `seats` int NOT NULL DEFAULT '2',
  `zone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Зал',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `restaurant_tables`
--

LOCK TABLES `restaurant_tables` WRITE;
/*!40000 ALTER TABLE `restaurant_tables` DISABLE KEYS */;
INSERT INTO `restaurant_tables` VALUES (1,'Очаг №1',2,'Зал',1,'2026-05-29 21:12:25'),(2,'Очаг №2',2,'Зал',1,'2026-05-29 21:12:25'),(3,'Очаг №3',4,'Зал',1,'2026-05-29 21:12:25'),(4,'Круглый стол',6,'Зал',1,'2026-05-29 21:12:25'),(5,'VIP-очаг',4,'VIP-зал',1,'2026-05-29 21:12:25'),(6,'Терраса №1',4,'Терраса',1,'2026-05-29 21:12:25'),(7,'Терраса №2',6,'Терраса',1,'2026-05-29 21:12:25'),(8,'У камина',8,'Зал',1,'2026-05-29 21:12:25'),(9,'Терраса №3',2,'Терраса',1,'2026-05-29 21:12:26');
/*!40000 ALTER TABLE `restaurant_tables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `review` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_reply` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rating` tinyint(1) NOT NULL DEFAULT '5',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reviews_user` (`user_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
INSERT INTO `reviews` VALUES (1,2,'Бильбо Бэггинс','Уютное место, как в Шире! Пирог просто волшебный.',NULL,NULL,5,'2026-05-29 21:12:26'),(2,2,'Сэм','Отличный эль и приветливый хозяин. Вернёмся с друзьями.',NULL,NULL,5,'2026-05-29 21:12:26'),(3,NULL,'Гэндальф','Древние рецепты и тёплый очаг — рекомендую путникам.',NULL,NULL,5,'2026-05-29 21:12:26'),(4,3,'Елизавета','Моя самая любимая таверна. Вкусно, уютно, с душой)',NULL,'Спасибо большое, мы очень стараемся )',5,'2026-05-31 19:13:12');
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_notifications`
--

DROP TABLE IF EXISTS `user_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `entity_type` enum('order','booking') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_notifications`
--

LOCK TABLES `user_notifications` WRITE;
/*!40000 ALTER TABLE `user_notifications` DISABLE KEYS */;
INSERT INTO `user_notifications` VALUES (1,3,'booking',2,'Бронь №2 отправлена и ожидает подтверждения',1,'2026-05-31 19:10:22'),(2,3,'booking',2,'Бронь №2: статус изменён на «Отклонена»',0,'2026-05-31 19:19:13'),(3,3,'order',2,'Заказ №2 оформлен и ожидает подтверждения',0,'2026-05-31 19:44:48'),(4,3,'order',3,'Заказ №3 оформлен и ожидает подтверждения',0,'2026-05-31 19:46:56'),(5,3,'order',4,'Заказ №4 оформлен и ожидает подтверждения',0,'2026-05-31 19:48:19'),(6,3,'order',4,'Заказ №4: статус изменён на «Подтверждён»',0,'2026-05-31 19:51:13'),(7,3,'order',4,'Заказ №4: статус изменён на «Готовится»',0,'2026-05-31 19:51:20'),(8,3,'order',4,'Заказ №4: статус изменён на «Готов»',0,'2026-05-31 19:51:24'),(9,3,'order',4,'Заказ №4: статус изменён на «Завершён»',0,'2026-05-31 19:51:27'),(10,3,'order',3,'Заказ №3: статус изменён на «Отменён»',0,'2026-05-31 19:51:37'),(11,3,'order',3,'Заказ №3: статус изменён на «Готовится»',0,'2026-05-31 19:51:44'),(12,3,'order',3,'Заказ №3: статус изменён на «Готов»',0,'2026-05-31 19:51:54'),(13,3,'order',3,'Заказ №3: статус изменён на «Подтверждён»',0,'2026-05-31 19:51:59'),(14,3,'order',3,'Заказ №3: статус изменён на «Готовится»',0,'2026-05-31 19:52:16'),(15,3,'order',2,'Заказ №2: статус изменён на «Подтверждён»',0,'2026-05-31 19:52:19'),(16,3,'order',2,'Заказ №2: статус изменён на «Готовится»',0,'2026-05-31 19:52:20'),(17,3,'order',3,'Заказ №3: статус изменён на «Отменён»',0,'2026-05-31 19:52:25'),(18,3,'order',3,'Заказ №3: статус изменён на «Ожидает подтверждения»',0,'2026-05-31 19:52:30'),(19,3,'order',3,'Заказ №3: статус изменён на «Подтверждён»',0,'2026-05-31 19:52:33'),(20,3,'order',3,'Заказ №3: статус изменён на «Готовится»',0,'2026-05-31 19:52:35'),(21,3,'order',3,'Заказ №3: статус изменён на «Ожидает подтверждения»',0,'2026-05-31 19:53:15'),(22,3,'order',3,'Заказ №3: статус изменён на «Подтверждён»',0,'2026-05-31 19:53:19'),(23,3,'order',3,'Заказ №3: статус изменён на «Готовится»',0,'2026-05-31 19:53:20'),(24,3,'order',4,'Заказ №4: статус изменён на «Ожидает подтверждения»',0,'2026-06-03 18:46:02'),(25,3,'order',4,'Заказ №4: статус изменён на «Завершён»',0,'2026-06-04 05:41:19'),(26,3,'order',2,'Заказ №2: статус изменён на «Завершён»',0,'2026-06-04 05:41:27'),(27,3,'order',3,'Заказ №3: статус изменён на «Завершён»',0,'2026-06-04 05:41:36'),(28,6,'order',6,'Заказ №6 оформлен и ожидает подтверждения',0,'2026-06-04 10:14:48'),(29,6,'order',6,'Заказ №6: статус изменён на «Подтверждён». хорошо, мы вас поняла',0,'2026-06-04 10:15:29'),(30,6,'order',6,'Заказ №6: статус изменён на «Завершён»',0,'2026-06-04 10:15:57');
/*!40000 ALTER TABLE `user_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('user','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@shire.com','$2y$10$ysmLYNqIAT3aTyn1M/SVkehU/c1s18rfSfprNJowqEpTiVUCk/dPe','admin','Хозяин таверны',NULL,'2026-05-29 21:12:25'),(2,'guest','guest@example.com','$2y$10$ysmLYNqIAT3aTyn1M/SVkehU/c1s18rfSfprNJowqEpTiVUCk/dPe','user','Бильбо Бэггинс',NULL,'2026-05-29 21:12:25'),(3,'kapit','kapitonova.2004@inbox.ru','$2y$10$ROGIOrjLeY883A3Qx7IhkO4Jzak5I05df9hrZMmzEBBAZ.453op/2','user','Елизавета','+79053241046','2026-05-31 19:03:39'),(4,'kapitonova.2004@inbox.sd','kapitonova.2004@inbox.sd','$2y$10$vpTnABfjVuIApcukZxnjJOoSzQl5al4SGKSWqLutK83J.Ed8KiqM2','user','Елизавета','11111111111','2026-05-31 19:56:53'),(5,'lkjghgc@mail.ru','lkjghgc@mail.ru','$2y$10$Jy0oBAkrvizXG6GmA5iF7edt4lni.CTxQ9clb8uQe7xQ488DtgTKi','user','длорап','+79865431865','2026-06-04 05:12:31'),(6,'jhkgj@mail.ru','jhkgj@mail.ru','$2y$10$zcKzuybR0.AfvNIIkNd.CuojRFghLA6VWthf8/Cjy9irSeQPKpWX2','user','\'uoiyutryte','+79065431854','2026-06-04 10:14:04');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-06 11:47:07
