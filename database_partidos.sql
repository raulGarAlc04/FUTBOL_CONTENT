-- Partidos y eliminatorias (ejecutar install_partidos_tables.php o aplicar manualmente).

ALTER TABLE competicion_equipo
    ADD COLUMN eliminado TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN grupo_letra CHAR(1) NULL;

ALTER TABLE competicion
    ADD COLUMN football_data_code VARCHAR(16) NULL COMMENT 'Código API football-data.org',
    ADD COLUMN football_data_season SMALLINT NULL;

CREATE TABLE IF NOT EXISTS partido (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competicion_id INT NOT NULL,
    fase VARCHAR(64) NOT NULL DEFAULT 'Liga',
    jornada SMALLINT NULL,
    grupo_letra CHAR(1) NULL,
    es_eliminatoria TINYINT(1) NOT NULL DEFAULT 0,
    orden_fase SMALLINT NOT NULL DEFAULT 0,
    equipo_local_id INT NOT NULL,
    equipo_visitante_id INT NOT NULL,
    goles_local SMALLINT NULL,
    goles_visitante SMALLINT NULL,
    penales_local SMALLINT NULL,
    penales_visitante SMALLINT NULL,
    fecha DATE NULL,
    estado ENUM('programado','finalizado') NOT NULL DEFAULT 'programado',
    notas VARCHAR(255) NULL,
    api_match_id VARCHAR(40) NULL,
    api_fuente VARCHAR(24) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_comp_estado (competicion_id, estado),
    CONSTRAINT fk_partido_comp FOREIGN KEY (competicion_id) REFERENCES competicion(id) ON DELETE CASCADE,
    CONSTRAINT fk_partido_local FOREIGN KEY (equipo_local_id) REFERENCES equipo(id) ON DELETE RESTRICT,
    CONSTRAINT fk_partido_visit FOREIGN KEY (equipo_visitante_id) REFERENCES equipo(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
