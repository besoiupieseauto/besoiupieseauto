-- Web site CMS: pagini + blog
CREATE TABLE IF NOT EXISTS `site_pages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(100) NOT NULL,
    `label` VARCHAR(255) NOT NULL,
    `title` VARCHAR(255) DEFAULT '',
    `meta_description` TEXT,
    `hero_label` VARCHAR(255) DEFAULT '',
    `hero_title` VARCHAR(255) DEFAULT '',
    `hero_subtitle` TEXT,
    `body_html` MEDIUMTEXT,
    `sections_json` LONGTEXT,
    `faq_json` LONGTEXT,
    `cta_json` LONGTEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `sort_order` INT DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_site_pages_slug` (`slug`),
    KEY `idx_site_pages_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `blog_posts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(180) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `tag` VARCHAR(80) DEFAULT 'Articole',
    `excerpt` TEXT,
    `body_html` MEDIUMTEXT,
    `featured_image` VARCHAR(500) DEFAULT '',
    `is_published` TINYINT(1) DEFAULT 0,
    `published_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_blog_posts_slug` (`slug`),
    KEY `idx_blog_published` (`is_published`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `routes` (`method`, `path`, `controller`, `action`, `load_type`, `dir`, `is_active`) VALUES
('GET',  '/admin/public/website',   'Admin', 'index',        'loadPage', '/admin/Templates/admin/pages/website/', 1),
('POST', '/admin/public/crudwebsite','Admin', 'rootFunction', NULL,       '/admin/src/Controllers/Website/crudu.php', 1),
('GET',  '/admin/public/blog',      'Admin', 'index',        'loadPage', '/admin/Templates/admin/pages/blog/', 1),
('GET',  '/admin/public/addblog',   'Admin', 'index',        'loadPage', '/admin/Templates/admin/pages/blog/', 1),
('GET',  '/admin/public/editblog',  'Admin', 'index',        'loadPage', '/admin/Templates/admin/pages/blog/', 1),
('POST', '/admin/public/crudblog',  'Admin', 'rootFunction', NULL,       '/admin/src/Controllers/Blog/crudu.php', 1);

INSERT INTO `site_pages` (`slug`, `label`, `title`, `sort_order`, `is_active`) VALUES
('cum-comand', 'Cum comand', 'Cum comand', 10, 1),
('livrare-plata', 'Livrare și plată', 'Livrare și plată', 20, 1),
('retur-garantie', 'Retur și garanție', 'Retur și garanție', 30, 1),
('intrebari-frecvente', 'Întrebări frecvente', 'Întrebări frecvente', 40, 1),
('termeni-conditii', 'Termeni și condiții', 'Termeni și condiții', 50, 1),
('politica-confidentialitate', 'Politica confidențialitate', 'Politica confidențialitate', 60, 1),
('politica-cookies', 'Politica cookies', 'Politica cookies', 70, 1),
('cariere', 'Cariere', 'Cariere', 80, 1),
('blog', 'Blog', 'Blog', 90, 1),
('about', 'Despre noi', 'Despre noi', 100, 1),
('contact', 'Contact', 'Contact', 110, 1);
