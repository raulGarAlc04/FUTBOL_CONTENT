-- Script de creación de base de datos para Futbol Data
-- Autor: Antigravity
-- Fecha: 2026-02-05

-- 1. Creación de la Base de Datos
DROP DATABASE IF EXISTS futbol_data;
CREATE DATABASE futbol_data CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE futbol_data;

-- 2. Creación del Usuario
DROP USER IF EXISTS 'futbol_admin'@'localhost';
CREATE USER 'futbol_admin'@'localhost' IDENTIFIED BY 'Futbol_Pass_2026!';
GRANT ALL PRIVILEGES ON futbol_data.* TO 'futbol_admin'@'localhost';
FLUSH PRIVILEGES;

-- 3. Tabla: Continente
CREATE TABLE continente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 4. Tabla: Pais
CREATE TABLE pais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    codigo_iso CHAR(3),
    bandera_url VARCHAR(255),
    continente_id INT,
    FOREIGN KEY (continente_id) REFERENCES continente(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. Tabla: Tipo de Competicion
CREATE TABLE tipo_competicion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT
) ENGINE=InnoDB;

INSERT INTO tipo_competicion (nombre) VALUES 
('Liga');

-- 6. Tabla: Competicion
CREATE TABLE competicion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    pais_id INT,
    continente_id INT,
    tipo_competicion_id INT NOT NULL,
    logo_url VARCHAR(255),
    temporada_actual VARCHAR(20),
    FOREIGN KEY (pais_id) REFERENCES pais(id) ON DELETE SET NULL,
    FOREIGN KEY (continente_id) REFERENCES continente(id) ON DELETE SET NULL,
    FOREIGN KEY (tipo_competicion_id) REFERENCES tipo_competicion(id)
) ENGINE=InnoDB;

-- 7. Tabla: Equipo
CREATE TABLE equipo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    nombre_corto VARCHAR(50),
    pais_id INT,
    estadio VARCHAR(150),
    fundacion YEAR,
    escudo_url VARCHAR(255), -- Solicitado explícitamente ("necesito el escudo")
    FOREIGN KEY (pais_id) REFERENCES pais(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 8. Tabla: Jugadores (Modificado: solo nombre, sin apellido/apodo)
CREATE TABLE jugadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL, -- Un solo campo para el nombre completo
    fecha_nacimiento DATE,
    pais_id INT,
    equipo_actual_id INT,
    posicion VARCHAR(50),
    dorsal INT,
    foto_url VARCHAR(255),
    FOREIGN KEY (pais_id) REFERENCES pais(id) ON DELETE SET NULL,
    FOREIGN KEY (equipo_actual_id) REFERENCES equipo(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 9. Tabla: Participantes (Equipos en Competición)
CREATE TABLE competicion_equipo (
    competicion_id INT,
    equipo_id INT,
    PRIMARY KEY (competicion_id, equipo_id),
    FOREIGN KEY (competicion_id) REFERENCES competicion(id) ON DELETE CASCADE,
    FOREIGN KEY (equipo_id) REFERENCES equipo(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. Tabla: Clasificación Manual
CREATE TABLE clasificacion (
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
