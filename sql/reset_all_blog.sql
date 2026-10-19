-- Full reset for both legacy coolcatblog and current cci_blog.
-- Replace PREFIX_ with your PrestaShop database prefix before running manually.

DELETE tl
FROM `PREFIX_tab_lang` tl
INNER JOIN `PREFIX_tab` t ON t.`id_tab` = tl.`id_tab`
WHERE t.`class_name` IN (
  'AdminCoolCatBlog',
  'AdminCciBlogPosts',
  'AdminCoolCatBlogCategories',
  'AdminCciBlogComments',
  'AdminCoolCatBlogConfiguration',
  'AdminCciBlog',
  'AdminCciBlogPosts',
  'AdminCciBlogCategories',
  'AdminCciBlogComments',
  'AdminCciBlogConfiguration'
);

DELETE FROM `PREFIX_tab`
WHERE `class_name` IN (
  'AdminCoolCatBlog',
  'AdminCciBlogPosts',
  'AdminCoolCatBlogCategories',
  'AdminCciBlogComments',
  'AdminCoolCatBlogConfiguration',
  'AdminCciBlog',
  'AdminCciBlogPosts',
  'AdminCciBlogCategories',
  'AdminCciBlogComments',
  'AdminCciBlogConfiguration'
);

DELETE hm
FROM `PREFIX_hook_module` hm
INNER JOIN `PREFIX_module` m ON m.`id_module` = hm.`id_module`
WHERE m.`name` IN ('coolcatblog', 'cci_blog');

DELETE hme
FROM `PREFIX_hook_module_exceptions` hme
INNER JOIN `PREFIX_module` m ON m.`id_module` = hme.`id_module`
WHERE m.`name` IN ('coolcatblog', 'cci_blog');

DELETE ms
FROM `PREFIX_module_shop` ms
INNER JOIN `PREFIX_module` m ON m.`id_module` = ms.`id_module`
WHERE m.`name` IN ('coolcatblog', 'cci_blog');

DELETE ma
FROM `PREFIX_module_access` ma
INNER JOIN `PREFIX_authorization_role` ar ON ar.`id_authorization_role` = ma.`id_authorization_role`
WHERE ar.`slug` LIKE 'ROLE_MOD_MODULE_COOLCATBLOG_%'
   OR ar.`slug` LIKE 'ROLE_MOD_MODULE_CCI_BLOG_%';

DELETE FROM `PREFIX_authorization_role`
WHERE `slug` LIKE 'ROLE_MOD_MODULE_COOLCATBLOG_%'
   OR `slug` LIKE 'ROLE_MOD_MODULE_CCI_BLOG_%';

DELETE ml
FROM `PREFIX_meta_lang` ml
INNER JOIN `PREFIX_meta` m ON m.`id_meta` = ml.`id_meta`
WHERE m.`page` LIKE 'module-coolcatblog-%'
   OR m.`page` LIKE 'module-cci_blog-%';

DELETE FROM `PREFIX_meta`
WHERE `page` LIKE 'module-coolcatblog-%'
   OR `page` LIKE 'module-cci_blog-%';

DELETE FROM `PREFIX_module`
WHERE `name` IN ('coolcatblog', 'cci_blog');

DELETE FROM `PREFIX_configuration`
WHERE `name` IN (
  'CCB_POSTS_PER_PAGE',
  'CCB_SHOW_AUTHOR',
  'CCB_SHOW_DATE',
  'CCB_SHOW_VIEWS',
  'CCB_SHOW_READ_TIME',
  'CCB_COMMENTS_ENABLED',
  'CCB_COMMENTS_PROVIDER',
  'CCB_COMMENTS_MODERATION',
  'CCB_RELATED_POSTS',
  'CCB_RELATED_PRODUCTS',
  'CCB_BREADCRUMB',
  'CCB_SOCIAL_SHARE',
  'CCB_NEWSLETTER_WIDGET',
  'CCB_SCHEMA_ORG',
  'CCB_OG_TAGS',
  'CCB_DISQUS_SHORTNAME',
  'CCB_TABLE_OF_CONTENTS_ENABLED',
  'CCB_TABLE_OF_CONTENTS_MIN_HEADINGS',
  'CCB_GA_EVENT_TRACKING',
  'CCB_HIGHLIGHT_SYNTAX',
  'CCB_BASE_SLUG',
  'CCB_SIDEBAR_POSITION',
  'CCB_LAYOUT',
  'CCB_SHOW_FEATURED_WIDGET',
  'CCB_FEED_ENABLED',
  'CCB_FEED_ITEMS'
);

DROP TABLE IF EXISTS `PREFIX_coolcatblog_author_lang`;
DROP TABLE IF EXISTS `PREFIX_coolcatblog_author`;
DROP TABLE IF EXISTS `PREFIX_coolcatblog_slug_redirect`;
DROP TABLE IF EXISTS `PREFIX_coolcatblog_post_product`;
DROP TABLE IF EXISTS `PREFIX_coolcatblog_comment`;
DROP TABLE IF EXISTS `PREFIX_coolcatblog_post_image`;
DROP TABLE IF EXISTS `PREFIX_coolcatblog_post_tag`;
DROP TABLE IF EXISTS `PREFIX_coolcatblog_tag`;
DROP TABLE IF EXISTS `PREFIX_coolcatblog_post_shop`;
DROP TABLE IF EXISTS `PREFIX_coolcatblog_post_lang`;
DROP TABLE IF EXISTS `PREFIX_coolcatblog_post`;
DROP TABLE IF EXISTS `PREFIX_coolcatblog_category_shop`;
DROP TABLE IF EXISTS `PREFIX_coolcatblog_category_lang`;
DROP TABLE IF EXISTS `PREFIX_coolcatblog_category`;

DROP TABLE IF EXISTS `PREFIX_cci_blog_author_lang`;
DROP TABLE IF EXISTS `PREFIX_cci_blog_author`;
DROP TABLE IF EXISTS `PREFIX_cci_blog_slug_redirect`;
DROP TABLE IF EXISTS `PREFIX_cci_blog_post_product`;
DROP TABLE IF EXISTS `PREFIX_cci_blog_comment`;
DROP TABLE IF EXISTS `PREFIX_cci_blog_post_image`;
DROP TABLE IF EXISTS `PREFIX_cci_blog_post_tag`;
DROP TABLE IF EXISTS `PREFIX_cci_blog_tag`;
DROP TABLE IF EXISTS `PREFIX_cci_blog_post_shop`;
DROP TABLE IF EXISTS `PREFIX_cci_blog_post_lang`;
DROP TABLE IF EXISTS `PREFIX_cci_blog_post_category`;
DROP TABLE IF EXISTS `PREFIX_cci_blog_post`;
DROP TABLE IF EXISTS `PREFIX_cci_blog_category_shop`;
DROP TABLE IF EXISTS `PREFIX_cci_blog_category_lang`;
DROP TABLE IF EXISTS `PREFIX_cci_blog_category`;
