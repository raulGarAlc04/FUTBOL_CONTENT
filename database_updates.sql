-- Archivo de actualizaciones de base de datos
-- Ejecutar en orden si ya tienes la base de datos creada con database_setup.sql

-- Actualización 1: Añadir banderas a países
-- Fecha: 2026-02-05
USE futbol_data;
-- ALTER TABLE pais ADD COLUMN bandera_url VARCHAR(255) AFTER codigo_iso;

-- Actualización 2: Tabla de Participantes (Equipos en Competición)
-- Fecha: 2026-02-05
CREATE TABLE IF NOT EXISTS competicion_equipo (
    competicion_id INT,
    equipo_id INT,
    PRIMARY KEY (competicion_id, equipo_id),
    FOREIGN KEY (competicion_id) REFERENCES competicion(id) ON DELETE CASCADE,
    FOREIGN KEY (equipo_id) REFERENCES equipo(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Actualización 4: Eliminar nombre corto de equipos
-- Fecha: 2026-02-05
-- ALTER TABLE equipo DROP COLUMN nombre_corto;

-- Actualización 5: Tabla de Clasificación Manual
-- Fecha: 2026-02-05
CREATE TABLE IF NOT EXISTS clasificacion (
    competicion_id INT,
    equipo_id INT,
    pj INT DEFAULT 0,
    pg INT DEFAULT 0,
    pe INT DEFAULT 0,
    pp INT DEFAULT 0,
    gf INT DEFAULT 0,
    gc INT DEFAULT 0,
    puntos INT DEFAULT 0,
    PRIMARY KEY (competicion_id, equipo_id),
    FOREIGN KEY (competicion_id) REFERENCES competicion(id) ON DELETE CASCADE,
    FOREIGN KEY (equipo_id) REFERENCES equipo(id) ON DELETE CASCADE
) ENGINE=InnoDB;
