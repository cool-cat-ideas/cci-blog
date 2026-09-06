-- CCI Blog
-- Normalize editable content rows after switching Free to the shop default language.
--
-- This migration is safe for existing content:
-- - it does not delete translations,
-- - it only creates missing rows for the default shop language,
-- - it copies the first available language row as a starting point when the default row is missing.
--
-- Replace PREFIX_ with your database prefix before running manually, e.g. ps_.

SET @cci_blog_default_lang_id := (
  SELECT CAST(`value` AS UNSIGNED)
  FROM `PREFIX_configuration`
  WHERE `name` = 'PS_LANG_DEFAULT'
  ORDER BY `id_shop` DESC, `id_shop_group` DESC, `id_configuration` DESC
  LIMIT 1
);

SET @cci_blog_default_lang_id := COALESCE(
  @cci_blog_default_lang_id,
  (SELECT `id_lang` FROM `PREFIX_lang` WHERE `active` = 1 ORDER BY `id_lang` ASC LIMIT 1),
  (SELECT `id_lang` FROM `PREFIX_lang` ORDER BY `id_lang` ASC LIMIT 1)
);

INSERT INTO `PREFIX_cci_blog_post_lang` (
  `id_post`,
  `id_lang`,
  `id_shop`,
  `title`,
  `slug`,
  `intro`,
  `content`,
  `content_blocks`,
  `meta_title`,
  `meta_description`,
  `meta_keywords`,
  `focus_keyword`,
  `seo_content_type`,
  `seo_score`,
  `og_title`,
  `og_description`
)
SELECT
  source.`id_post`,
  @cci_blog_default_lang_id,
  source.`id_shop`,
  source.`title`,
  source.`slug`,
  source.`intro`,
  source.`content`,
  source.`content_blocks`,
  source.`meta_title`,
  source.`meta_description`,
  source.`meta_keywords`,
  source.`focus_keyword`,
  COALESCE(source.`seo_content_type`, 'article'),
  COALESCE(source.`seo_score`, 0),
  source.`og_title`,
  source.`og_description`
FROM `PREFIX_cci_blog_post_lang` source
INNER JOIN (
  SELECT `id_post`, `id_shop`, MIN(`id_lang`) AS `source_lang_id`
  FROM `PREFIX_cci_blog_post_lang`
  GROUP BY `id_post`, `id_shop`
) pick
  ON pick.`id_post` = source.`id_post`
 AND pick.`id_shop` = source.`id_shop`
 AND pick.`source_lang_id` = source.`id_lang`
WHERE NOT EXISTS (
  SELECT 1
  FROM `PREFIX_cci_blog_post_lang` target
  WHERE target.`id_post` = source.`id_post`
    AND target.`id_shop` = source.`id_shop`
    AND target.`id_lang` = @cci_blog_default_lang_id
);

INSERT INTO `PREFIX_cci_blog_category_lang` (
  `id_category`,
  `id_lang`,
  `id_shop`,
  `name`,
  `slug`,
  `description`,
  `meta_title`,
  `meta_description`,
  `meta_keywords`
)
SELECT
  source.`id_category`,
  @cci_blog_default_lang_id,
  source.`id_shop`,
  source.`name`,
  source.`slug`,
  source.`description`,
  source.`meta_title`,
  source.`meta_description`,
  source.`meta_keywords`
FROM `PREFIX_cci_blog_category_lang` source
INNER JOIN (
  SELECT `id_category`, `id_shop`, MIN(`id_lang`) AS `source_lang_id`
  FROM `PREFIX_cci_blog_category_lang`
  GROUP BY `id_category`, `id_shop`
) pick
  ON pick.`id_category` = source.`id_category`
 AND pick.`id_shop` = source.`id_shop`
 AND pick.`source_lang_id` = source.`id_lang`
WHERE NOT EXISTS (
  SELECT 1
  FROM `PREFIX_cci_blog_category_lang` target
  WHERE target.`id_category` = source.`id_category`
    AND target.`id_shop` = source.`id_shop`
    AND target.`id_lang` = @cci_blog_default_lang_id
);

-- If an older admin save stored the SEO score in a non-default language row,
-- keep the default shop language row aligned without recalculating scores on every list request.
UPDATE `PREFIX_cci_blog_post_lang` target
INNER JOIN (
  SELECT `id_post`, `id_shop`, MAX(`seo_score`) AS `seo_score`
  FROM `PREFIX_cci_blog_post_lang`
  WHERE `id_lang` <> @cci_blog_default_lang_id
    AND `seo_score` > 0
  GROUP BY `id_post`, `id_shop`
) source
  ON source.`id_post` = target.`id_post`
 AND source.`id_shop` = target.`id_shop`
SET target.`seo_score` = source.`seo_score`
WHERE target.`id_lang` = @cci_blog_default_lang_id
  AND COALESCE(target.`seo_score`, 0) = 0;
