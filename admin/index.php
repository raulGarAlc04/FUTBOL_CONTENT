<?php
require_once __DIR__ . '/../db.php';
include '../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cleanup_images_and_competition') {
    $pdo->beginTransaction();
    try {
        $pdo->exec("UPDATE jugadores SET foto_url = NULL");

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Error en la limpieza: " . addslashes($e->getMessage()) . "'); window.location.href='index.php';</script>";
        exit;
    }

    echo "<script>alert('Limpieza completada. Fotos de jugadores eliminadas.'); window.location.href='index.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_copa_del_rey') {
    $deleted = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM competicion WHERE nombre = ?");
        $stmt->execute(['Copa del Rey']);
        $deleted += $stmt->rowCount();

        if ($deleted === 0) {
            $stmt2 = $pdo->prepare("DELETE FROM competicion WHERE nombre LIKE ?");
            $stmt2->execute(['%Copa del Rey%']);
            $deleted += $stmt2->rowCount();
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Error eliminando la competición: " . addslashes($e->getMessage()) . "'); window.location.href='index.php';</script>";
        exit;
    }

    echo "<script>alert('Competiciones eliminadas: $deleted'); window.location.href='index.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_empty_teams') {
    try {
        $ids = $pdo->query("SELECT e.id FROM equipo e LEFT JOIN jugadores j ON j.equipo_actual_id = e.id WHERE j.id IS NULL")->fetchAll(PDO::FETCH_COLUMN);
        $ids = array_values(array_map('intval', $ids ?: []));

        if (count($ids) === 0) {
            echo "<script>alert('No hay equipos con 0 jugadores.'); window.location.href='index.php';</script>";
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("DELETE FROM clasificacion WHERE equipo_id IN ($placeholders)");
            $stmt->execute($ids);
        } catch (PDOException $e) {
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM competicion_equipo WHERE equipo_id IN ($placeholders)");
            $stmt->execute($ids);
        } catch (PDOException $e) {
        }

        $stmtDel = $pdo->prepare("DELETE FROM equipo WHERE id IN ($placeholders)");
        $stmtDel->execute($ids);
        $deleted = $stmtDel->rowCount();

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "<script>alert('Error borrando equipos sin plantilla: " . addslashes($e->getMessage()) . "'); window.location.href='index.php';</script>";
        exit;
    }

    try {
        $nextId = (int) $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM equipo")->fetchColumn();
        if ($nextId < 1) {
            $nextId = 1;
        }
        $pdo->exec("ALTER TABLE equipo AUTO_INCREMENT = " . $nextId);
    } catch (Exception $e) {
    }

    echo "<script>alert('Equipos eliminados: $deleted. AUTO_INCREMENT ajustado.'); window.location.href='index.php';</script>";
    exit;
}

// --- ESTADÍSTICAS RÁPIDAS ---
$stats = [
    'continentes' => $pdo->query("SELECT COUNT(*) FROM continente")->fetchColumn(),
    'paises' => $pdo->query("SELECT COUNT(*) FROM pais")->fetchColumn(),
    'ligas' => $pdo->query("SELECT COUNT(*) FROM competicion c JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id WHERE tc.nombre = 'Liga'")->fetchColumn(),
    'equipos' => $pdo->query("SELECT COUNT(*) FROM equipo")->fetchColumn(),
    'jugadores' => $pdo->query("SELECT COUNT(*) FROM jugadores")->fetchColumn(),
    'equipos_sin_liga' => $pdo->query("SELECT COUNT(*) FROM equipo e LEFT JOIN competicion_equipo ce ON ce.equipo_id = e.id WHERE ce.equipo_id IS NULL")->fetchColumn(),
    'equipos_sin_plantilla' => $pdo->query("SELECT COUNT(*) FROM equipo e LEFT JOIN jugadores j ON j.equipo_actual_id = e.id WHERE j.id IS NULL")->fetchColumn()
];

// --- ÚLTIMAS COMPETICIONES ---
$stmtRecentComps = $pdo->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM competicion_equipo ce WHERE ce.competicion_id = c.id) as equipos_inscritos
    FROM competicion c
    JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id
    WHERE tc.nombre = 'Liga'
    ORDER BY c.id DESC
    LIMIT 5
");
$recentComps = $stmtRecentComps->fetchAll();

$stmtActiveLeagues = $pdo->query("
    SELECT c.id, c.nombre, c.temporada_actual, c.logo_url,
           (SELECT COUNT(*) FROM competicion_equipo ce WHERE ce.competicion_id = c.id) as equipos_inscritos
    FROM competicion c
    JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id
    WHERE tc.nombre = 'Liga'
    ORDER BY c.id DESC
    LIMIT 12
");
$activeLeagues = $stmtActiveLeagues->fetchAll();
?>

<div class="container" style="margin-top: 40px; margin-bottom: 60px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 2.5rem; margin-bottom: 5px;">Panel de Control</h1>
            <p style="color: var(--text-muted);">Bienvenido al sistema de gestión de Futbol Data.</p>
        </div>
        <div style="background: rgba(212, 175, 55, 0.1); padding: 10px 20px; border-radius: 12px; border: 1px solid var(--accent-color);">
            <span style="color: var(--accent-color); font-weight: bold;">Versión 2026.1</span>
        </div>
    </div>

    <!-- GRID DE ESTADÍSTICAS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div class="admin-card" style="padding: 20px; margin-bottom: 0; text-align: center; border-left: 4px solid #3b82f6;">
            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 5px;">Ligas</div>
            <div style="font-size: 2rem; font-weight: bold; color: #fff;"><?= $stats['ligas'] ?></div>
        </div>
        <div class="admin-card" style="padding: 20px; margin-bottom: 0; text-align: center; border-left: 4px solid #10b981;">
            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 5px;">Equipos</div>
            <div style="font-size: 2rem; font-weight: bold; color: #fff;"><?= $stats['equipos'] ?></div>
        </div>
        <div class="admin-card" style="padding: 20px; margin-bottom: 0; text-align: center; border-left: 4px solid #f59e0b;">
            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 5px;">Jugadores</div>
            <div style="font-size: 2rem; font-weight: bold; color: #fff;"><?= $stats['jugadores'] ?></div>
        </div>
        <div class="admin-card" style="padding: 20px; margin-bottom: 0; text-align: center; border-left: 4px solid #8b5cf6;">
            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 5px;">Equipos sin Liga</div>
            <div style="font-size: 2rem; font-weight: bold; color: #fff;"><?= $stats['equipos_sin_liga'] ?></div>
        </div>
        <div class="admin-card" style="padding: 20px; margin-bottom: 0; text-align: center; border-left: 4px solid #ef4444;">
            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 5px;">Equipos sin Plantilla</div>
            <div style="font-size: 2rem; font-weight: bold; color: #fff;"><?= $stats['equipos_sin_plantilla'] ?></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">

        <!-- SECCIÓN DE ACCESO RÁPIDO -->
        <div>
            <h2 style="font-size: 1.5rem; margin-bottom: 20px;">Gestión de Entidades</h2>
            <div class="admin-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                <a href="continentes.php" class="comp-card" style="padding: 20px; text-decoration: none;">
                    <h3 style="font-size: 1.1rem; color: #fff;">Continentes</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Administrar regiones</p>
                </a>
                <a href="paises.php" class="comp-card" style="padding: 20px; text-decoration: none;">
                    <h3 style="font-size: 1.1rem; color: #fff;">Países</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Administrar naciones</p>
                </a>
                <a href="competiciones.php" class="comp-card" style="padding: 20px; text-decoration: none;">
                    <h3 style="font-size: 1.1rem; color: #fff;">Competiciones</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Ligas</p>
                </a>
                <a href="equipos.php" class="comp-card" style="padding: 20px; text-decoration: none;">
                    <h3 style="font-size: 1.1rem; color: #fff;">Equipos</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Clubes de fútbol</p>
                </a>
                <a href="selecciones.php" class="comp-card" style="padding: 20px; text-decoration: none;">
                    <h3 style="font-size: 1.1rem; color: #fff;">Selecciones</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Equipos nacionales</p>
                </a>
            </div>

            <div style="margin-top: 30px;">
                <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                    <h2 style="font-size: 1.5rem; margin: 0;">Ligas Activas</h2>
                    <a href="competicion_form.php" class="btn-admin" style="margin: 0; background: #3b82f6;">+ Nueva Liga</a>
                </div>
                <div class="admin-card" style="padding: 0; overflow: hidden;">
                    <div class="table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Liga</th>
                                    <th style="text-align:center;">Equipos</th>
                                    <th style="text-align:right; padding-right: 25px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeLeagues as $l): ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center; gap: 10px;">
                                                <?php if (!empty($l['logo_url'])): ?>
                                                    <img src="<?= htmlspecialchars($l['logo_url']) ?>" style="width: 26px; height: 26px; object-fit: contain;">
                                                <?php else: ?>
                                                    <div style="width: 26px; height: 26px; background: rgba(255,255,255,0.06); border-radius: 50%; display:flex; align-items:center; justify-content:center; font-size: 0.7rem;">🏆</div>
                                                <?php endif; ?>
                                                <div>
                                                    <div style="font-weight: 700; color: #fff;"><?= htmlspecialchars($l['nombre']) ?></div>
                                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($l['temporada_actual']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align:center; font-weight: 800; color: var(--text-muted);">
                                            <?= (int) $l['equipos_inscritos'] ?>
                                        </td>
                                        <td style="text-align:right; padding-right: 25px;">
                                            <div style="display:flex; justify-content:flex-end; gap: 10px; flex-wrap: wrap;">
                                                <a href="competicion_partidos.php?id=<?= (int) $l['id'] ?>" class="btn-admin" style="margin: 0; padding: 6px 12px; background: rgba(59,130,246,0.35);">Resultados</a>
                                                <a href="competicion_clasificacion.php?id=<?= (int) $l['id'] ?>" class="btn-admin" style="margin: 0; padding: 6px 12px; background: var(--accent-color);">Clasificación</a>
                                                <a href="competicion_participantes.php?id=<?= (int) $l['id'] ?>" class="btn-admin" style="margin: 0; padding: 6px 12px; background: rgba(255,255,255,0.05); color: var(--text-muted);">Equipos</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ÚLTIMAS COMPETICIONES AÑADIDAS -->
        <div>
            <h2 style="font-size: 1.5rem; margin-bottom: 20px;">Nuevas Competiciones</h2>
            <div style="background: var(--secondary-color); border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); overflow: hidden;">
                <?php foreach ($recentComps as $comp): ?>
                    <a href="competicion_clasificacion.php?id=<?= $comp['id'] ?>" style="display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.05); text-decoration: none; color: inherit; transition: background 0.2s;">
                        <?php if (!empty($comp['logo_url'])): ?>
                            <img src="<?= htmlspecialchars($comp['logo_url']) ?>" style="width: 32px; height: 32px; object-fit: contain;">
                        <?php else: ?>
                            <div style="width: 32px; height: 32px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">🏆</div>
                        <?php endif; ?>
                        <div>
                            <div style="font-weight: 600; font-size: 0.95rem; color: #fff;"><?= htmlspecialchars($comp['nombre']) ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($comp['temporada_actual']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
                <a href="competiciones.php" style="display: block; text-align: center; padding: 12px; font-size: 0.85rem; color: var(--accent-color); text-decoration: none; background: rgba(0,0,0,0.2);">Ver todas</a>
            </div>

            <div class="admin-card" style="margin-top: 25px; border-left: 4px solid #ef4444;">
                <h2 style="font-size: 1.25rem; margin-bottom: 15px;">Mantenimiento</h2>
                <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 15px;">
                    Elimina únicamente las fotos de los jugadores (campo foto_url).
                </div>
                <form method="POST" onsubmit="return confirm('Esto eliminará todas las fotos de jugadores. ¿Continuar?');">
                    <input type="hidden" name="action" value="cleanup_images_and_competition">
                    <button type="submit" class="btn-admin" style="margin-left: 0; background: #ef4444; border: none; cursor: pointer;">
                        Ejecutar Limpieza
                    </button>
                </form>

                <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,0.06);">
                    <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 12px;">
                        Elimina la competición "Copa del Rey".
                    </div>
                    <form method="POST" onsubmit="return confirm('Esto eliminará la competición Copa del Rey. ¿Continuar?');">
                        <input type="hidden" name="action" value="delete_copa_del_rey">
                        <button type="submit" class="btn-admin" style="margin-left: 0; background: rgba(255,255,255,0.05); color: var(--text-color); border: 1px solid rgba(255,255,255,0.12); cursor: pointer;">
                            Eliminar Copa del Rey
                        </button>
                    </form>
                </div>

                <div style="margin-top: 14px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,0.06);">
                    <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 12px;">
                        Elimina todos los equipos con 0 jugadores en plantilla y ajusta el AUTO_INCREMENT.
                    </div>
                    <form method="POST" onsubmit="return confirm('Esto eliminará TODOS los equipos sin jugadores. ¿Continuar?');">
                        <input type="hidden" name="action" value="delete_empty_teams">
                        <button type="submit" class="btn-admin" style="margin-left: 0; background: rgba(255,255,255,0.05); color: var(--text-color); border: 1px solid rgba(255,255,255,0.12); cursor: pointer;">
                            Borrar Equipos sin Plantilla
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
// Ajustamos ruta del footer
include '../includes/footer.php';
?>