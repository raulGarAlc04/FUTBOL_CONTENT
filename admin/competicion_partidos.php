<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/partidos_service.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID de competición no válido.');
}
$competicion_id = (int) $_GET['id'];

function partidos_tabla_existe(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM partido LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

if (!partidos_tabla_existe($pdo)) {
    die('Falta la tabla de partidos. Ejecuta primero <a href="../install_partidos_tables.php">install_partidos_tables.php</a> en el navegador.');
}

$stmt = $pdo->prepare('SELECT c.*, tc.nombre AS tipo_nombre FROM competicion c JOIN tipo_competicion tc ON tc.id = c.tipo_competicion_id WHERE c.id = ?');
$stmt->execute([$competicion_id]);
$comp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$comp) {
    die('Competición no encontrada.');
}

$tipoNombre = (string) $comp['tipo_nombre'];
$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $el = (int) ($_POST['equipo_local_id'] ?? 0);
            $ev = (int) ($_POST['equipo_visitante_id'] ?? 0);
            $fase = trim((string) ($_POST['fase'] ?? 'Liga'));
            if ($fase === '') {
                $fase = 'Liga';
            }
            $jornada = ($_POST['jornada'] ?? '') !== '' ? (int) $_POST['jornada'] : null;
            $grupo = trim((string) ($_POST['grupo_letra'] ?? ''));
            $grupo = $grupo !== '' ? strtoupper(substr($grupo, 0, 1)) : null;
            $es_el = !empty($_POST['es_eliminatoria']) ? 1 : 0;
            $orden = ($_POST['orden_fase'] ?? '') !== '' ? (int) $_POST['orden_fase'] : 0;
            $fecha = trim((string) ($_POST['fecha'] ?? ''));
            $fechaSql = $fecha !== '' ? $fecha : null;
            $estado = ($_POST['estado'] ?? 'programado') === 'finalizado' ? 'finalizado' : 'programado';
            $gl = ($_POST['goles_local'] ?? '') !== '' ? (int) $_POST['goles_local'] : null;
            $gv = ($_POST['goles_visitante'] ?? '') !== '' ? (int) $_POST['goles_visitante'] : null;
            $pl = ($_POST['penales_local'] ?? '') !== '' ? (int) $_POST['penales_local'] : null;
            $pv = ($_POST['penales_visitante'] ?? '') !== '' ? (int) $_POST['penales_visitante'] : null;
            $notas = trim((string) ($_POST['notas'] ?? ''));
            $notas = $notas !== '' ? mb_substr($notas, 0, 255) : null;

            $row = [
                'estado' => $estado,
                'goles_local' => $gl,
                'goles_visitante' => $gv,
                'penales_local' => $pl,
                'penales_visitante' => $pv,
                'es_eliminatoria' => $es_el,
            ];
            $v = partidos_validar_finalizado($row);
            if ($v) {
                $err = $v;
            } elseif ($el <= 0 || $ev <= 0 || $el === $ev) {
                $err = 'Selecciona dos equipos distintos.';
            } else {
                $pdo->prepare(
                    'INSERT INTO partido (competicion_id, fase, jornada, grupo_letra, es_eliminatoria, orden_fase, equipo_local_id, equipo_visitante_id, goles_local, goles_visitante, penales_local, penales_visitante, fecha, estado, notas)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $competicion_id, $fase, $jornada, $grupo, $es_el, $orden, $el, $ev, $gl, $gv, $pl, $pv, $fechaSql, $estado, $notas,
                ]);
                partidos_despues_de_cambio($pdo, $competicion_id);
                $msg = 'Partido añadido.';
            }
        } elseif ($action === 'save' && isset($_POST['partido_id'])) {
            $pid = (int) $_POST['partido_id'];
            $fase = trim((string) ($_POST['fase'] ?? 'Liga'));
            if ($fase === '') {
                $fase = 'Liga';
            }
            $jornada = ($_POST['jornada'] ?? '') !== '' ? (int) $_POST['jornada'] : null;
            $grupo = trim((string) ($_POST['grupo_letra'] ?? ''));
            $grupo = $grupo !== '' ? strtoupper(substr($grupo, 0, 1)) : null;
            $es_el = !empty($_POST['es_eliminatoria']) ? 1 : 0;
            $orden = ($_POST['orden_fase'] ?? '') !== '' ? (int) $_POST['orden_fase'] : 0;
            $fecha = trim((string) ($_POST['fecha'] ?? ''));
            $fechaSql = $fecha !== '' ? $fecha : null;
            $estado = ($_POST['estado'] ?? 'programado') === 'finalizado' ? 'finalizado' : 'programado';
            $gl = ($_POST['goles_local'] ?? '') !== '' ? (int) $_POST['goles_local'] : null;
            $gv = ($_POST['goles_visitante'] ?? '') !== '' ? (int) $_POST['goles_visitante'] : null;
            $pl = ($_POST['penales_local'] ?? '') !== '' ? (int) $_POST['penales_local'] : null;
            $pv = ($_POST['penales_visitante'] ?? '') !== '' ? (int) $_POST['penales_visitante'] : null;
            $notas = trim((string) ($_POST['notas'] ?? ''));
            $notas = $notas !== '' ? mb_substr($notas, 0, 255) : null;

            $chk = $pdo->prepare('SELECT id FROM partido WHERE id = ? AND competicion_id = ?');
            $chk->execute([$pid, $competicion_id]);
            if (!$chk->fetch()) {
                $err = 'Partido no válido.';
            } else {
                $row = [
                    'estado' => $estado,
                    'goles_local' => $gl,
                    'goles_visitante' => $gv,
                    'penales_local' => $pl,
                    'penales_visitante' => $pv,
                    'es_eliminatoria' => $es_el,
                ];
                $v = partidos_validar_finalizado($row);
                if ($v) {
                    $err = $v;
                } else {
                    $pdo->prepare(
                        'UPDATE partido SET fase=?, jornada=?, grupo_letra=?, es_eliminatoria=?, orden_fase=?, goles_local=?, goles_visitante=?, penales_local=?, penales_visitante=?, fecha=?, estado=?, notas=?
                         WHERE id=? AND competicion_id=?'
                    )->execute([$fase, $jornada, $grupo, $es_el, $orden, $gl, $gv, $pl, $pv, $fechaSql, $estado, $notas, $pid, $competicion_id]);
                    partidos_despues_de_cambio($pdo, $competicion_id);
                    $msg = 'Partido actualizado.';
                }
            }
        } elseif ($action === 'delete' && isset($_POST['partido_id'])) {
            $pid = (int) $_POST['partido_id'];
            $pdo->prepare('DELETE FROM partido WHERE id = ? AND competicion_id = ?')->execute([$pid, $competicion_id]);
            partidos_despues_de_cambio($pdo, $competicion_id);
            $msg = 'Partido eliminado.';
        }
    } catch (PDOException $e) {
        $err = $e->getMessage();
    }
}

$stmtEq = $pdo->prepare(
    'SELECT e.id, e.nombre FROM equipo e
     JOIN competicion_equipo ce ON ce.equipo_id = e.id
     WHERE ce.competicion_id = ? AND COALESCE(ce.eliminado,0) = 0
     ORDER BY e.nombre ASC'
);
$stmtEq->execute([$competicion_id]);
$equiposSel = $stmtEq->fetchAll(PDO::FETCH_ASSOC);

$stmtAll = $pdo->prepare(
    'SELECT e.id, e.nombre, COALESCE(ce.eliminado,0) AS eliminado FROM equipo e
     JOIN competicion_equipo ce ON ce.equipo_id = e.id
     WHERE ce.competicion_id = ?
     ORDER BY ce.eliminado ASC, e.nombre ASC'
);
$stmtAll->execute([$competicion_id]);
$equiposTodos = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

$stmtP = $pdo->prepare(
    'SELECT p.*, el.nombre AS nombre_local, ev.nombre AS nombre_visit
     FROM partido p
     JOIN equipo el ON el.id = p.equipo_local_id
     JOIN equipo ev ON ev.id = p.equipo_visitante_id
     WHERE p.competicion_id = ?
     ORDER BY p.es_eliminatoria ASC, p.orden_fase ASC, p.jornada ASC, p.fecha ASC, p.id ASC'
);
$stmtP->execute([$competicion_id]);
$partidos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

$page_full_width = true;
include '../includes/header.php';

$sugEl = partidos_sugerir_es_eliminatoria($tipoNombre);
?>

<div class="container page-shell" style="max-width: 1100px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; margin-bottom:24px;">
        <div>
            <h1 style="margin:0;">Resultados · <?= htmlspecialchars($comp['nombre']) ?></h1>
            <p style="color:var(--text-muted); margin:8px 0 0;"><?= htmlspecialchars($tipoNombre) ?> · <?= htmlspecialchars((string) $comp['temporada_actual']) ?></p>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:10px;">
            <a href="competicion_import_footballdata.php?id=<?= $competicion_id ?>" class="btn-admin" style="margin:0; background:rgba(59,130,246,0.25); border:1px solid rgba(59,130,246,0.4);">Importar API</a>
            <a href="competicion_participantes.php?id=<?= $competicion_id ?>" class="btn-admin" style="margin:0; background:var(--secondary-color);">Equipos</a>
            <a href="competicion_clasificacion.php?id=<?= $competicion_id ?>" class="btn-admin" style="margin:0; background:rgba(255,255,255,0.06); color:var(--text-muted);">Clasificación</a>
            <a href="competiciones.php" class="btn-admin btn-admin--ghost" style="margin:0;">Competiciones</a>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="admin-card" style="padding:14px 18px; margin-bottom:18px; border-color:rgba(34,197,94,0.35); background:rgba(34,197,94,0.08);"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="admin-card" style="padding:14px 18px; margin-bottom:18px; border-color:rgba(239,68,68,0.4); background:rgba(239,68,68,0.08);"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <div class="admin-card" style="padding:18px 20px; margin-bottom:22px;">
        <strong>Cómo funciona</strong>
        <ul style="margin:10px 0 0 18px; color:var(--text-muted); line-height:1.6;">
            <li><strong>Liga</strong>: partidos sin “eliminatoria”. Al finalizar, la tabla de clasificación se recalcula sola (3-1-0, goles).</li>
            <li><strong>Eliminatoria</strong> o <strong>Grupos + eliminatoria</strong>: marca partidos KO con “Eliminatoria”. El perdedor queda <strong>eliminado</strong> (no sale en la web ni puede usarse en nuevos partidos hasta revertir resultados).</li>
            <li>Empate en KO: rellena <strong>penales</strong> (ej. 4-3). Opción automática: <a href="competicion_import_footballdata.php?id=<?= $competicion_id ?>">football-data.org</a>.</li>
        </ul>
    </div>

    <?php if ($tipoNombre === 'Eliminatoria' || $tipoNombre === 'Fase de grupos y eliminatoria'): ?>
        <div class="admin-card" style="padding:16px 20px; margin-bottom:22px;">
            <strong>Equipos eliminados</strong> (siguen en BD; no compiten más en esta edición)
            <div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:8px;">
                <?php
                $elims = array_filter($equiposTodos, static fn ($r) => !empty($r['eliminado']));
                if (!$elims) {
                    echo '<span style="color:var(--text-muted);">Ninguno.</span>';
                } else {
                    foreach ($elims as $e) {
                        echo '<span class="badge badge-danger">' . htmlspecialchars($e['nombre']) . '</span>';
                    }
                }
                ?>
            </div>
        </div>
    <?php endif; ?>

    <h2 class="section-title">Partidos (<?= count($partidos) ?>)</h2>

    <?php foreach ($partidos as $p): ?>
        <form method="post" class="admin-card" style="padding:16px 18px; margin-bottom:14px;">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="partido_id" value="<?= (int) $p['id'] ?>">
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap:12px; align-items:end;">
                <div>
                    <label class="form-label">Local</label>
                    <div style="font-weight:800; padding:10px 0;"><?= htmlspecialchars($p['nombre_local']) ?></div>
                </div>
                <div>
                    <label class="form-label">Visitante</label>
                    <div style="font-weight:800; padding:10px 0;"><?= htmlspecialchars($p['nombre_visit']) ?></div>
                </div>
                <div>
                    <label class="form-label">Goles L / V</label>
                    <div style="display:flex; gap:8px;">
                        <input class="form-control-admin" type="number" name="goles_local" min="0" max="99" value="<?= $p['goles_local'] !== null ? (int) $p['goles_local'] : '' ?>" style="width:70px;">
                        <input class="form-control-admin" type="number" name="goles_visitante" min="0" max="99" value="<?= $p['goles_visitante'] !== null ? (int) $p['goles_visitante'] : '' ?>" style="width:70px;">
                    </div>
                </div>
                <div>
                    <label class="form-label">Pen. L / V (KO empate)</label>
                    <div style="display:flex; gap:8px;">
                        <input class="form-control-admin" type="number" name="penales_local" min="0" max="50" value="<?= $p['penales_local'] !== null ? (int) $p['penales_local'] : '' ?>" style="width:70px;">
                        <input class="form-control-admin" type="number" name="penales_visitante" min="0" max="50" value="<?= $p['penales_visitante'] !== null ? (int) $p['penales_visitante'] : '' ?>" style="width:70px;">
                    </div>
                </div>
                <div>
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-control-admin">
                        <option value="programado" <?= $p['estado'] === 'programado' ? 'selected' : '' ?>>Programado</option>
                        <option value="finalizado" <?= $p['estado'] === 'finalizado' ? 'selected' : '' ?>>Finalizado</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Fase</label>
                    <input class="form-control-admin" type="text" name="fase" value="<?= htmlspecialchars($p['fase']) ?>">
                </div>
                <div>
                    <label class="form-label">Jornada</label>
                    <input class="form-control-admin" type="number" name="jornada" value="<?= $p['jornada'] !== null ? (int) $p['jornada'] : '' ?>" placeholder="—">
                </div>
                <div>
                    <label class="form-label">Grupo</label>
                    <input class="form-control-admin" type="text" name="grupo_letra" maxlength="1" value="<?= htmlspecialchars((string) ($p['grupo_letra'] ?? '')) ?>" placeholder="A">
                </div>
                <div>
                    <label class="form-label">Orden fase KO</label>
                    <input class="form-control-admin" type="number" name="orden_fase" value="<?= (int) $p['orden_fase'] ?>">
                </div>
                <div>
                    <label class="form-label">Fecha</label>
                    <input class="form-control-admin" type="date" name="fecha" value="<?= $p['fecha'] ? htmlspecialchars((string) $p['fecha']) : '' ?>">
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; margin-top:22px;">
                        <input type="checkbox" name="es_eliminatoria" value="1" <?= !empty($p['es_eliminatoria']) ? 'checked' : '' ?>>
                        <span>Eliminatoria</span>
                    </label>
                </div>
            </div>
            <div style="margin-top:12px;">
                <label class="form-label">Notas</label>
                <input class="form-control-admin" type="text" name="notas" value="<?= htmlspecialchars((string) ($p['notas'] ?? '')) ?>">
            </div>
            <div style="display:flex; gap:10px; margin-top:14px; flex-wrap:wrap;">
                <button type="submit" class="btn-admin" style="margin:0; border:none; cursor:pointer;">Guardar</button>
            </div>
        </form>
        <form method="post" onsubmit="return confirm('¿Eliminar este partido?');" style="margin:-8px 0 20px;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="partido_id" value="<?= (int) $p['id'] ?>">
            <button type="submit" class="btn-admin btn-admin--ghost" style="margin:0; color:#fca5a5; border-color:rgba(239,68,68,0.35);">Eliminar partido</button>
        </form>
    <?php endforeach; ?>

    <?php if (empty($partidos)): ?>
        <p style="color:var(--text-muted); margin-bottom:20px;">No hay partidos. Añade el primero abajo.</p>
    <?php endif; ?>

    <h2 class="section-title">Añadir partido</h2>
    <?php if (count($equiposSel) < 2): ?>
        <div class="message-panel">Necesitas al menos 2 equipos activos (no eliminados) inscritos.</div>
    <?php else: ?>
        <form method="post" class="admin-card" style="padding:20px;">
            <input type="hidden" name="action" value="add">
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:14px; align-items:end;">
                <div>
                    <label class="form-label">Local</label>
                    <select name="equipo_local_id" class="form-control-admin" required>
                        <option value="">—</option>
                        <?php foreach ($equiposSel as $e): ?>
                            <option value="<?= (int) $e['id'] ?>"><?= htmlspecialchars($e['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Visitante</label>
                    <select name="equipo_visitante_id" class="form-control-admin" required>
                        <option value="">—</option>
                        <?php foreach ($equiposSel as $e): ?>
                            <option value="<?= (int) $e['id'] ?>"><?= htmlspecialchars($e['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Goles L / V</label>
                    <div style="display:flex; gap:8px;">
                        <input class="form-control-admin" type="number" name="goles_local" min="0" max="99" style="width:80px;" placeholder="—">
                        <input class="form-control-admin" type="number" name="goles_visitante" min="0" max="99" style="width:80px;" placeholder="—">
                    </div>
                </div>
                <div>
                    <label class="form-label">Penales L / V</label>
                    <div style="display:flex; gap:8px;">
                        <input class="form-control-admin" type="number" name="penales_local" min="0" max="50" style="width:80px;">
                        <input class="form-control-admin" type="number" name="penales_visitante" min="0" max="50" style="width:80px;">
                    </div>
                </div>
                <div>
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-control-admin">
                        <option value="programado">Programado</option>
                        <option value="finalizado">Finalizado</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Fase</label>
                    <input class="form-control-admin" type="text" name="fase" value="<?= $tipoNombre === 'Liga' ? 'Liga' : 'Eliminatoria' ?>">
                </div>
                <div>
                    <label class="form-label">Jornada</label>
                    <input class="form-control-admin" type="number" name="jornada" placeholder="opcional">
                </div>
                <div>
                    <label class="form-label">Grupo (fase grupos)</label>
                    <input class="form-control-admin" type="text" name="grupo_letra" maxlength="1" placeholder="A">
                </div>
                <div>
                    <label class="form-label">Orden fase KO</label>
                    <input class="form-control-admin" type="number" name="orden_fase" value="0">
                </div>
                <div>
                    <label class="form-label">Fecha</label>
                    <input class="form-control-admin" type="date" name="fecha">
                </div>
                <div style="display:flex; align-items:center;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="es_eliminatoria" value="1" <?= $sugEl ? 'checked' : '' ?>>
                        Partido de eliminatoria
                    </label>
                </div>
            </div>
            <div style="margin-top:12px;">
                <label class="form-label">Notas</label>
                <input class="form-control-admin" type="text" name="notas">
            </div>
            <button type="submit" class="btn-admin" style="margin-top:16px; border:none; cursor:pointer;">Añadir</button>
        </form>
    <?php endif; ?>
</div>

<style>
.form-label { display:block; font-size:0.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.04em; margin-bottom:6px; }
</style>

<?php include '../includes/footer.php'; ?>
