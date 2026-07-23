-- Mapare Autonet QWP: OEM (RefNr+ReferenceBrand) → ArtNr (cod comandă)
-- Folosită de AutonetSearchBuilder / AutonetParser

CREATE TABLE IF NOT EXISTS autonet_qwp_data (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ArtNr VARCHAR(64) NOT NULL,
  ReferenceBrand VARCHAR(128) NOT NULL DEFAULT '',
  RefNr VARCHAR(128) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_qwp_art_ref_brand (ArtNr, RefNr, ReferenceBrand),
  KEY idx_qwp_ref (RefNr),
  KEY idx_qwp_art (ArtNr),
  KEY idx_qwp_ref_brand (RefNr, ReferenceBrand)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
