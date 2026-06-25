-- Repară etichete site_pages corupte (Acas?? → Acasă etc.)
UPDATE `site_pages` SET `label` = 'Acasă (index)', `title` = 'Besoiu Piese Auto' WHERE `slug` = 'home';
UPDATE `site_pages` SET `label` = 'Header & Footer', `title` = 'Setări globale site' WHERE `slug` = 'global';
UPDATE `site_pages` SET `label` = 'Catalog', `title` = 'Catalog piese auto' WHERE `slug` = 'catalog';
UPDATE `site_pages` SET `label` = 'Cum comand', `title` = 'Cum comand' WHERE `slug` = 'cum-comand';
UPDATE `site_pages` SET `label` = 'Livrare și plată', `title` = 'Livrare și plată' WHERE `slug` = 'livrare-plata';
UPDATE `site_pages` SET `label` = 'Retur și garanție', `title` = 'Retur și garanție' WHERE `slug` = 'retur-garantie';
UPDATE `site_pages` SET `label` = 'Întrebări frecvente', `title` = 'Întrebări frecvente' WHERE `slug` = 'intrebari-frecvente';
UPDATE `site_pages` SET `label` = 'Termeni și condiții', `title` = 'Termeni și condiții' WHERE `slug` = 'termeni-conditii';
UPDATE `site_pages` SET `label` = 'Politica confidențialitate', `title` = 'Politica confidențialitate' WHERE `slug` = 'politica-confidentialitate';
UPDATE `site_pages` SET `label` = 'Politica cookies', `title` = 'Politica cookies' WHERE `slug` = 'politica-cookies';
UPDATE `site_pages` SET `label` = 'Cariere', `title` = 'Cariere' WHERE `slug` = 'cariere';
UPDATE `site_pages` SET `label` = 'Blog', `title` = 'Blog' WHERE `slug` = 'blog';
UPDATE `site_pages` SET `label` = 'Despre noi', `title` = 'Despre noi' WHERE `slug` = 'about';
UPDATE `site_pages` SET `label` = 'Contact', `title` = 'Contact' WHERE `slug` = 'contact';
