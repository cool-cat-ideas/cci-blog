-- CCI Blog – install.sql
-- Uses PREFIX_ placeholder, replaced at install time.

-- -----------------------------------------------------------------------
-- Blog Categories
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_category` (
  `id_category` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_parent`   INT UNSIGNED NOT NULL DEFAULT 0,
  `id_author`   INT UNSIGNED NOT NULL DEFAULT 0,
  `active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `position`    INT UNSIGNED NOT NULL DEFAULT 0,
  `image_url`   VARCHAR(2048) DEFAULT NULL,
  `date_add`    DATETIME     NOT NULL,
  `date_upd`    DATETIME     NOT NULL,
  PRIMARY KEY (`id_category`),
  KEY `idx_author` (`id_author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_category_lang` (
  `id_category`      INT UNSIGNED  NOT NULL,
  `id_lang`          INT UNSIGNED  NOT NULL,
  `id_shop`          INT UNSIGNED  NOT NULL DEFAULT 1,
  `name`             VARCHAR(255)  NOT NULL,
  `slug`             VARCHAR(255)  NOT NULL,
  `description`      TEXT,
  `meta_title`       VARCHAR(255),
  `meta_description` VARCHAR(512),
  `meta_keywords`    VARCHAR(255),
  PRIMARY KEY (`id_category`, `id_lang`, `id_shop`),
  UNIQUE KEY `slug_lang_shop` (`slug`, `id_lang`, `id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_category_shop` (
  `id_category` INT UNSIGNED NOT NULL,
  `id_shop`     INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_category`, `id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Blog Posts
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_post` (
  `id_post`      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `id_category`  INT UNSIGNED  NOT NULL DEFAULT 1,
  `id_author`    INT UNSIGNED  NOT NULL DEFAULT 0 COMMENT 'References ps_employee id',
  `active`       TINYINT(1)    NOT NULL DEFAULT 1,
  `featured`     TINYINT(1)    NOT NULL DEFAULT 0,
  `allow_comments` TINYINT(1)  NOT NULL DEFAULT 1,
  `views`        INT UNSIGNED  NOT NULL DEFAULT 0,
  `cover_image`  VARCHAR(255),
  `og_image`     VARCHAR(255),
  `date_published` DATETIME,
  `date_add`     DATETIME      NOT NULL,
  `date_upd`     DATETIME      NOT NULL,
  PRIMARY KEY (`id_post`),
  KEY `idx_category` (`id_category`),
  KEY `idx_author`   (`id_author`),
  KEY `idx_active`   (`active`),
  KEY `idx_featured` (`featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_post_lang` (
  `id_post`          INT UNSIGNED NOT NULL,
  `id_lang`          INT UNSIGNED NOT NULL,
  `id_shop`          INT UNSIGNED NOT NULL DEFAULT 1,
  `title`            VARCHAR(512) NOT NULL,
  `slug`             VARCHAR(512) NOT NULL,
  `intro`            TEXT,
  `content`          LONGTEXT,
  `content_blocks`   LONGTEXT,
  `meta_title`       VARCHAR(512),
  `meta_description` VARCHAR(512),
  `meta_keywords`    VARCHAR(255),
  `focus_keyword`    VARCHAR(255),
  `seo_content_type` VARCHAR(32) DEFAULT 'article',
  `seo_score`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `og_title`         VARCHAR(255),
  `og_description`   VARCHAR(512),
  PRIMARY KEY (`id_post`, `id_lang`, `id_shop`),
  UNIQUE KEY `slug_lang_shop` (`slug`(200), `id_lang`, `id_shop`),
  KEY `idx_seo_score` (`seo_score`),
  FULLTEXT KEY `ft_content` (`title`, `intro`, `content`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_post_shop` (
  `id_post` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_post`, `id_shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_post_category` (
  `id_post`     INT UNSIGNED NOT NULL,
  `id_category` INT UNSIGNED NOT NULL,
  `is_primary`  TINYINT(1)   NOT NULL DEFAULT 0,
  `position`    INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_post`, `id_category`),
  KEY `idx_category` (`id_category`),
  KEY `idx_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Tags
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_tag` (
  `id_tag`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_lang` INT UNSIGNED NOT NULL,
  `name`    VARCHAR(128) NOT NULL,
  `slug`    VARCHAR(128) NOT NULL,
  PRIMARY KEY (`id_tag`),
  UNIQUE KEY `slug_lang` (`slug`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_post_tag` (
  `id_post` INT UNSIGNED NOT NULL,
  `id_tag`  INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id_post`, `id_tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Gallery (multiple images per post)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_post_image` (
  `id_image`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_post`     INT UNSIGNED NOT NULL,
  `filename`    VARCHAR(255) NOT NULL,
  `alt_text`    VARCHAR(255),
  `caption`     VARCHAR(512),
  `position`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_cover`    TINYINT(1) NOT NULL DEFAULT 0,
  `date_add`    DATETIME NOT NULL,
  PRIMARY KEY (`id_image`),
  KEY `idx_post` (`id_post`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Comments
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_comment` (
  `id_comment`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_post`     INT UNSIGNED NOT NULL,
  `id_parent`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'For threaded replies',
  `id_customer` INT UNSIGNED NOT NULL DEFAULT 0,
  `author_name` VARCHAR(128) NOT NULL,
  `author_email` VARCHAR(255) NOT NULL,
  `author_website` VARCHAR(255),
  `content`     TEXT NOT NULL,
  `status`      ENUM('pending','approved','spam','deleted') NOT NULL DEFAULT 'pending',
  `ip_address`  VARCHAR(45),
  `date_add`    DATETIME NOT NULL,
  PRIMARY KEY (`id_comment`),
  KEY `idx_post`   (`id_post`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Related Products (manual linking)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_post_product` (
  `id_post`    INT UNSIGNED NOT NULL,
  `id_product` INT UNSIGNED NOT NULL,
  `position`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_post`, `id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- SEO / Redirect log (for slug changes)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_slug_redirect` (
  `id_redirect` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(16) NOT NULL DEFAULT 'post',
  `old_slug`    VARCHAR(512) NOT NULL,
  `id_post`     INT UNSIGNED NULL,
  `id_category` INT UNSIGNED NULL,
  `id_lang`     INT UNSIGNED NOT NULL,
  `date_add`    DATETIME NOT NULL,
  PRIMARY KEY (`id_redirect`),
  KEY `idx_slug` (`entity_type`, `old_slug`(190), `id_lang`),
  KEY `idx_post` (`id_post`),
  KEY `idx_category` (`id_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Author profiles (overrides for employee)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_author` (
  `id_author`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
  `display_name` VARCHAR(128) NOT NULL,
  `avatar`      VARCHAR(255),
  `twitter`     VARCHAR(128),
  `linkedin`    VARCHAR(255),
  `active`      TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_author`),
  KEY `idx_employee` (`id_employee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_author_lang` (
  `id_author` INT UNSIGNED NOT NULL,
  `id_lang`   INT UNSIGNED NOT NULL,
  `bio`       TEXT,
  PRIMARY KEY (`id_author`, `id_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
