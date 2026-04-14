<?php
/**
 * Ejecutar una vez desde el navegador o CLI para crear tablas/columnas de partidos y eliminatorias.
 * Idempotente: se puede volver a ejecutar sin error.
 * En producción, elimina o protege este archivo tras migrar.
 */
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

function columnExists(PDO $pdo, string $table, string $col): bool
{
    $s = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $s->execute([$table, $col]);
    return (int) $s->fetchColumn() > 0;
}

function tableExists(PDO $pdo, string $table): bool
{
    $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $s->execute([$table]);
    return (int) $s->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $table, string $indexName): bool
{
    $s = $pdo->query('SHOW INDEX FROM `' . str_replace('`', '', $table) . '`');
    if (!$s) {
        return false;
    }
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (($row['Key_name'] ?? '') === $indexName) {
            return true;
        }
    }
    return false;
}

try {
    if (!columnExists($pdo, 'competicion_equipo', 'eliminado')) {
        $pdo->exec('ALTER TABLE competicion_equipo ADD COLUMN eliminado TINYINT(1) NOT NULL DEFAULT 0');
        echo "OK: competicion_equipo.eliminado\n";
    } else {
        echo "Skip: competicion_equipo.eliminado ya existe\n";
    }

    if (!columnExists($pdo, 'competicion_equipo', 'grupo_letra')) {
        $pdo->exec('ALTER TABLE competicion_equipo ADD COLUMN grupo_letra CHAR(1) NULL');
        echo "OK: competicion_equipo.grupo_letra\n";
    } else {
        echo "Skip: competicion_equipo.grupo_letra ya existe\n";
    }

    if (!columnExists($pdo, 'competicion', 'football_data_code')) {
        $pdo->exec("ALTER TABLE competicion ADD COLUMN football_data_code VARCHAR(16) NULL DEFAULT NULL COMMENT 'Código API football-data.org (ej PD, CL)'");
        echo "OK: competicion.football_data_code\n";
    } else {
        echo "Skip: competicion.football_data_code ya existe\n";
    }

    if (!columnExists($pdo, 'competicion', 'football_data_season')) {
        $pdo->exec("ALTER TABLE competicion ADD COLUMN football_data_season SMALLINT NULL DEFAULT NULL COMMENT 'Año inicio temporada API (ej 2024)'");
        echo "OK: competicion.football_data_season\n";
    } else {
        echo "Skip: competicion.football_data_season ya existe\n";
    }

    if (!columnExists($pdo, 'competicion', 'thesportsdb_league_id')) {
        $pdo->exec('ALTER TABLE competicion ADD COLUMN thesportsdb_league_id VARCHAR(24) NULL DEFAULT NULL');
        echo "OK: competicion.thesportsdb_league_id\n";
    } else {
        echo "Skip: competicion.thesportsdb_league_id ya existe\n";
    }

    if (!columnExists($pdo, 'competicion', 'thesportsdb_season')) {
        $pdo->exec("ALTER TABLE competicion ADD COLUMN thesportsdb_season VARCHAR(20) NULL DEFAULT NULL COMMENT 'Ej 2024-2025'");
        echo "OK: competicion.thesportsdb_season\n";
    } else {
        echo "Skip: competicion.thesportsdb_season ya existe\n";
    }

    if (!columnExists($pdo, 'competicion', 'api_football_league_id')) {
        $pdo->exec('ALTER TABLE competicion ADD COLUMN api_football_league_id INT NULL DEFAULT NULL');
        echo "OK: competicion.api_football_league_id\n";
    } else {
        echo "Skip: competicion.api_football_league_id ya existe\n";
    }

    if (!columnExists($pdo, 'competicion', 'api_football_season')) {
        $pdo->exec('ALTER TABLE competicion ADD COLUMN api_football_season SMALLINT NULL DEFAULT NULL');
        echo "OK: competicion.api_football_season\n";
    } else {
        echo "Skip: competicion.api_football_season ya existe\n";
    }

    if (!tableExists($pdo, 'partido')) {
        $pdo->exec("
            CREATE TABLE partido (
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
                KEY idx_comp_jornada (competicion_id, jornada),
                KEY idx_comp_api (competicion_id, api_match_id),
                CONSTRAINT fk_partido_comp FOREIGN KEY (competicion_id) REFERENCES competicion(id) ON DELETE CASCADE,
                CONSTRAINT fk_partido_local FOREIGN KEY (equipo_local_id) REFERENCES equipo(id) ON DELETE RESTRICT,
                CONSTRAINT fk_partido_visit FOREIGN KEY (equipo_visitante_id) REFERENCES equipo(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "OK: tabla partido creada\n";
    } else {
        echo "Skip: tabla partido ya existe — comprobando columnas…\n";
        $nPartidos = (int) $pdo->query('SELECT COUNT(*) FROM partido')->fetchColumn();
        $hasRows = $nPartidos > 0;
        $fkInt = $hasRows ? 'INT NULL' : 'INT NOT NULL';

        $partidoCols = [
            ['competicion_id', $fkInt],
            ['fase', "VARCHAR(64) NOT NULL DEFAULT 'Liga'"],
            ['jornada', 'SMALLINT NULL'],
            ['grupo_letra', 'CHAR(1) NULL'],
            ['es_eliminatoria', 'TINYINT(1) NOT NULL DEFAULT 0'],
            ['orden_fase', 'SMALLINT NOT NULL DEFAULT 0'],
            ['equipo_local_id', $fkInt],
            ['equipo_visitante_id', $fkInt],
            ['goles_local', 'SMALLINT NULL'],
            ['goles_visitante', 'SMALLINT NULL'],
            ['penales_local', 'SMALLINT NULL'],
            ['penales_visitante', 'SMALLINT NULL'],
            ['fecha', 'DATE NULL'],
            ["estado", "ENUM('programado','finalizado') NOT NULL DEFAULT 'programado'"],
            ['notas', 'VARCHAR(255) NULL'],
            ['api_match_id', 'VARCHAR(40) NULL'],
            ['api_fuente', 'VARCHAR(24) NULL'],
            ['created_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'],
            ['updated_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        ];
        foreach ($partidoCols as [$col, $def]) {
            if (!columnExists($pdo, 'partido', $col)) {
                $pdo->exec("ALTER TABLE partido ADD COLUMN `{$col}` {$def}");
                echo "OK: partido.{$col} añadida\n";
            }
        }
        if ($hasRows) {
            echo "AVISO: la tabla partido tenía filas. Si se añadieron columnas como NULL, complétalas en phpMyAdmin o borra filas de prueba.\n";
        }
    }

    if (tableExists($pdo, 'partido')) {
        if (!indexExists($pdo, 'partido', 'idx_comp_estado')) {
            try {
                $pdo->exec('ALTER TABLE partido ADD KEY idx_comp_estado (competicion_id, estado)');
                echo "OK: índice idx_comp_estado\n";
            } catch (PDOException $e) {
                echo "Skip índice idx_comp_estado: " . $e->getMessage() . "\n";
            }
        }
        if (!indexExists($pdo, 'partido', 'idx_comp_jornada')) {
            try {
                $pdo->exec('ALTER TABLE partido ADD KEY idx_comp_jornada (competicion_id, jornada)');
                echo "OK: índice idx_comp_jornada\n";
            } catch (PDOException $e) {
                echo "Skip índice idx_comp_jornada: " . $e->getMessage() . "\n";
            }
        }
        if (!indexExists($pdo, 'partido', 'idx_comp_api')) {
            try {
                $pdo->exec('ALTER TABLE partido ADD KEY idx_comp_api (competicion_id, api_match_id)');
                echo "OK: índice idx_comp_api\n";
            } catch (PDOException $e) {
                echo "Skip índice idx_comp_api: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\nInstalación / comprobación completada.\n";
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
