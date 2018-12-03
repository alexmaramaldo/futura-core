-- Create syntax for TABLE 'v3_categories'
CREATE TABLE `v3_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_georegra` int(11) DEFAULT NULL,
  `title` varchar(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `slug` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `order` int(11) NOT NULL,
  `highlight` varchar(400) COLLATE utf8_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_categoria` (`id`) USING BTREE,
  UNIQUE KEY `slug` (`slug`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Create syntax for TABLE 'v3_genres'
CREATE TABLE `v3_genres` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag` (`title`) USING BTREE,
  UNIQUE KEY `id_tag` (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Create syntax for TABLE 'v3_seasons'
CREATE TABLE `v3_seasons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title_id` int(11) DEFAULT NULL,
  `title` varchar(80) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(600) COLLATE utf8_unicode_ci DEFAULT NULL,
  `cover` varchar(300) COLLATE utf8_unicode_ci NOT NULL,
  `highlight` varchar(300) COLLATE utf8_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL,
  `order` int(11) DEFAULT NULL,
  `availability` enum('SVOD','TVOD','MIXED') COLLATE utf8_unicode_ci DEFAULT 'SVOD',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_serie` (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Create syntax for TABLE 'v3_tags'
CREATE TABLE `v3_tags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag` (`title`) USING BTREE,
  UNIQUE KEY `id_tag` (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Create syntax for TABLE 'v3_tags_videos'
CREATE TABLE `v3_tags_videos` (
  `tag_id` int(11) unsigned NOT NULL,
  `video_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`tag_id`,`video_id`),
  KEY `v2_tags_episodios_v2_tags_foreign` (`tag_id`) USING BTREE,
  KEY `v2_tags_episodios_v2_episodios_foreign` (`video_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Create syntax for TABLE 'v3_titles'
CREATE TABLE `v3_titles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(80) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(600) COLLATE utf8_unicode_ci DEFAULT NULL,
  `type` enum('show','movie') COLLATE utf8_unicode_ci DEFAULT 'show',
  `cover` varchar(300) COLLATE utf8_unicode_ci NOT NULL,
  `highlight` varchar(300) COLLATE utf8_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL,
  `availability` enum('SVOD','TVOD','MIXED') COLLATE utf8_unicode_ci DEFAULT 'SVOD',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_serie` (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Create syntax for TABLE 'v3_titles_categories'
CREATE TABLE `v3_titles_categories` (
  `title_id` int(11) unsigned NOT NULL,
  `category_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`title_id`,`category_id`),
  KEY `v2_tags_episodios_v2_tags_foreign` (`title_id`) USING BTREE,
  KEY `v2_tags_episodios_v2_episodios_foreign` (`category_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Create syntax for TABLE 'v3_titles_genres'
CREATE TABLE `v3_titles_genres` (
  `title_id` int(11) unsigned NOT NULL,
  `genre_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`title_id`,`genre_id`),
  KEY `v2_tags_episodios_v2_tags_foreign` (`title_id`) USING BTREE,
  KEY `v2_tags_episodios_v2_episodios_foreign` (`genre_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Create syntax for TABLE 'v3_videos'
CREATE TABLE `v3_videos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_sambavideos` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `id_georegra` int(10) unsigned DEFAULT NULL,
  `title` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` varchar(400) COLLATE utf8_unicode_ci DEFAULT NULL,
  `highlight` varchar(400) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `highlight2` varchar(400) COLLATE utf8_unicode_ci DEFAULT NULL,
  `duracao` int(11) DEFAULT NULL,
  `status` tinyint(1) NOT NULL,
  `availability` enum('SVOD','TVOD','MIXED') COLLATE utf8_unicode_ci DEFAULT 'SVOD',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Create syntax for TABLE 'v3_videos_seasons'
CREATE TABLE `v3_videos_seasons` (
  `video_id` int(11) NOT NULL,
  `season_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create syntax for TABLE 'v3_videos_titles'
CREATE TABLE `v3_videos_titles` (
  `video_id` int(11) NOT NULL,
  `title_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;DB DEFAULT CHARSET=utf8;



DROP TABLE IF EXISTS buy_rent;

CREATE TABLE buy_rent
(
    id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    user_id INT(11),
    expiration_date DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    start_at DATETIME,
    apple_pay_receipt VARCHAR(255),
    rental_type ENUM('buy', 'rent'),
    payment_method ENUM('apple_pay', 'bank_slip', 'credit_card', 'gift_card'),
    status ENUM('active', 'pending', 'canceled')
);
CREATE UNIQUE INDEX buy_rent_apple_pay_receipt_uindex ON buy_rent (apple_pay_receipt);
CREATE INDEX id_user ON buy_rent (user_id);

DROP TABLE IF EXISTS buy_rent_items
CREATE TABLE buy_rent_items
(
    id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    item_id INT(11),
    item_type ENUM('show', 'season', 'video', 'movie'),
    price DECIMAL(10,2),
    buy_rent_id INT(11),
    created_at DATETIME,
    updated_at DATETIME
);
CREATE UNIQUE INDEX buy_rent_items_id_uindex ON buy_rent_items (id);

DROP TABLE IF EXISTS apple_pay_transactions
CREATE TABLE apple_pay_transactions
(
    id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    receipt VARCHAR(255),
    transaction_id INT(11),
    in_app_product_id INT(11),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    buy_rent_id INT(11)
);
CREATE UNIQUE INDEX apple_pay_transactions_id_uindex ON apple_pay_transactions (id);

DROP TABLE IF EXISTS gift_card_tvod

CREATE TABLE gift_card_tvod
(
    id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    user_id INT(11),
    code VARCHAR(60),
    valid_days INT(11),
    item_type ENUM('show', 'movie'),
    item_id INT(11),
    created_at DATETIME,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);


DROP TABLE IF EXISTS pricing_item;
DROP TABLE IF EXISTS price_range;
DROP TABLE IF EXISTS price_range_item;
DROP TABLE IF EXISTS pricing_expiration;

CREATE TABLE price_range
(
  id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  name VARCHAR(255),
  price DECIMAL(10,2) NOT NULL,
  discount DECIMAL(10,2),
  price_type ENUM('buy', 'rent'),
  expire_at DATETIME,
  created_at TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE price_range_item
(
  item_id INT(11) NOT NULL,
  item_type ENUM('show', 'season', 'movie', 'video') NOT NULL,
  price_range_id INT(11) NOT NULL,
  pricing_expiration_id INT(11) NOT NULL,
  price_type ENUM('buy', 'rent') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
  expire_at DATETIME
);
CREATE TABLE pricing_expiration
(
  id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  time_period INT(11)
);
CREATE TABLE pricing_item
(
  id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  item_id INT(11) NOT NULL,
  item_type ENUM('show', 'season', 'video', 'movie'),
  price DECIMAL(10,2),
  discount DECIMAL(10,2),
  price_type ENUM('buy', 'rent'),
  pricing_expiration_id INT(11) NOT NULL,
  status TINYINT(4) DEFAULT '0' NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
);

CREATE UNIQUE INDEX price_range_id_uindex ON price_range (id);
CREATE INDEX price_range_item_item_id_item_type_index ON price_range_item (item_id, item_type);
CREATE INDEX price_range_item_price_type_index ON price_range_item (price_type);
CREATE UNIQUE INDEX precificacao_validade_nome_uindex ON pricing_expiration (name);
CREATE UNIQUE INDEX precificacao_item_id_uindex ON pricing_item (id);

CREATE TABLE buy_rent
(
    id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    user_id INT(11),
    item_id INT(11) NOT NULL,
    item_type ENUM('show', 'video', 'season', 'movie') NOT NULL,
    expiration_date DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    start_at DATETIME,
    apple_pay_receipt VARCHAR(255),
    rental_type ENUM('buy', 'rent')
);
CREATE TABLE apple_pay_transactions
(
    id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    receipt VARCHAR(255),
    transaction_id INT(11),
    in_app_product_id INT(11),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
    buy_rent_id INT(11)
);
CREATE TABLE gift_card_tvod
(
    id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    user_id INT(11),
    code VARCHAR(60),
    valid_days INT(11),
    item_type ENUM('show', 'movie'),
    item_id INT(11),
    created_at DATETIME,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX buy_rent_apple_pay_receipt_uindex ON buy_rent (apple_pay_receipt);
CREATE INDEX id_user ON buy_rent (user_id);
CREATE UNIQUE INDEX apple_pay_transactions_id_uindex ON apple_pay_transactions (id);


INSERT INTO pricing_expiration (name, time_period) VALUES ('Indefinido', -1);
INSERT INTO pricing_expiration (name, time_period) VALUES ('48 horas', 2);
INSERT INTO pricing_expiration (name, time_period) VALUES ('1 ano', 365);
INSERT INTO pricing_expiration (name, time_period) VALUES ('30 dias', 30);

CREATE TABLE title_information
(
    genero VARCHAR(25),
    sub_genero VARCHAR(25),
    categoria VARCHAR(25),
    sub_categoria VARCHAR(25),
    temporada VARCHAR(25),
    titulo VARCHAR(125),
    titulo_original VARCHAR(125),
    numero_do_episodio VARCHAR(25),
    episodio VARCHAR(25),
    diretor VARCHAR(25),
    atores VARCHAR(255),
    ano INT(4),
    duracao VARCHAR(8),
    pais VARCHAR(25),
    lingua VARCHAR(25),
    audio VARCHAR(25),
    legendas VARCHAR(25),
    classificacao VARCHAR(25),
    tags VARCHAR(255),
    inclusao DATE,
    vencimento VARCHAR(25),
    sinopse TEXT,
    produtora VARCHAR(100),
    distribuidora VARCHAR(100),
    restricao VARCHAR(25),
    item_id INT(11) NOT NULL,
    item_type ENUM('show', 'season', 'movie', 'title')
);