<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/clasificacion_visual.php';
include '../includes/header.php';

if (!isset($_GET['id'])) {
    die("ID no especificado.");
}
$competicion_id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT c.*, tc.nombre as tipo_nombre 
                       FROM competicion c 
                       JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id 
                       WHERE c.id = ?");
$stmt->execute([$competicion_id]);
$comp = $stmt->fetch();

if (!$comp) {
    die("Competición no encontrada.");
}

$marcasVisuales = clasificacion_visual_get_marks($competicion_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_visual_marca_id'])) {
    $deleteId = trim((string) $_POST['delete_visual_marca_id']);
    if ($deleteId !== '') {
        $next = [];
        foreach ($marcasVisuales as $m) {
            if ((string) ($m['id'] ?? '') === $deleteId) continue;
            $next[] = $m;
        }
        clasificacion_visual_set_marks($competicion_id, $next);
    }
    echo "<script>window.location.href='competicion_clasificacion.php?id=$competicion_id';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_visual_marca') {
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $color = clasificacion_visual_normalize_color($_POST['color'] ?? '');
    $posiciones = clasificacion_visual_normalize_posiciones($_POST['posiciones'] ?? '');
    $orden = asIntOrZero($_POST['orden'] ?? 0);

    if ($nombre !== '' && $posiciones !== '') {
        $marcasVisuales[] = [
            'id' => clasificacion_visual_new_id(),
            'nombre' => $nombre,
            'color' => $color,
            'posiciones' => $posiciones,
            'orden' => $orden,
        ];
        clasificacion_visual_set_marks($competicion_id, $marcasVisuales);
    }

    echo "<script>window.location.href='competicion_clasificacion.php?id=$competicion_id';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_visual_marcas') {
    $items = $_POST['marcas'] ?? [];
    if (!is_array($items)) $items = [];

    $next = [];
    foreach ($items as $id => $row) {
        if (!is_array($row)) continue;
        $mid = trim((string) $id);
        if ($mid === '') continue;
        $nombre = trim((string) ($row['nombre'] ?? ''));
        $color = clasificacion_visual_normalize_color($row['color'] ?? '');
        $posiciones = clasificacion_visual_normalize_posiciones($row['posiciones'] ?? '');
        $orden = asIntOrZero($row['orden'] ?? 0);
        if ($nombre === '' || $posiciones === '') continue;
        $next[] = [
            'id' => $mid,
            'nombre' => $nombre,
            'color' => $color,
            'posiciones' => $posiciones,
            'orden' => $orden,
        ];
    }
    clasificacion_visual_set_marks($competicion_id, $next);

    echo "<script>window.location.href='competicion_clasificacion.php?id=$competicion_id';</script>";
    exit;
}

function asIntOrZero($v)
{
    if ($v === '' || $v === null) {
        return 0;
    }
    if (is_numeric($v)) {
        return (int) $v;
    }
    return 0;
}

$stmtTeams = $pdo->prepare("
    SELECT e.id, e.nombre, e.escudo_url
    FROM competicion_equipo ce
    JOIN equipo e ON ce.equipo_id = e.id
    WHERE ce.competicion_id = ?
    ORDER BY e.nombre ASC
");
$stmtTeams->execute([$competicion_id]);
$equipos = $stmtTeams->fetchAll(PDO::FETCH_ASSOC);

$equiposById = [];
$equiposByName = [];
foreach ($equipos as $eq) {
    $eid = (int) $eq['id'];
    $equiposById[$eid] = $eq;
    $equiposByName[mb_strtolower(trim((string) $eq['nombre']), 'UTF-8')] = $eid;
}

$pdo->beginTransaction();
try {
    $stmtClean = $pdo->prepare("DELETE FROM clasificacion 
                                WHERE competicion_id = ? 
                                  AND equipo_id NOT IN (SELECT equipo_id FROM competicion_equipo WHERE competicion_id = ?)");
    $stmtClean->execute([$competicion_id, $competicion_id]);

    $stmtEnsure = $pdo->prepare("INSERT INTO clasificacion (competicion_id, equipo_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE equipo_id = equipo_id");
    foreach ($equipos as $eq) {
        $stmtEnsure->execute([$competicion_id, (int) $eq['id']]);
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmtExport = $pdo->prepare("
        SELECT c.*, e.id as equipo_id, e.nombre
        FROM clasificacion c
        JOIN equipo e ON c.equipo_id = e.id
        WHERE c.competicion_id = ?
    ");
    $stmtExport->execute([$competicion_id]);
    $rows = $stmtExport->fetchAll(PDO::FETCH_ASSOC);

    usort($rows, function ($a, $b) {
        if ((int) $a['puntos'] !== (int) $b['puntos']) {
            return (int) $b['puntos'] - (int) $a['puntos'];
        }
        $dgA = (int) $a['gf'] - (int) $a['gc'];
        $dgB = (int) $b['gf'] - (int) $b['gc'];
        if ($dgA !== $dgB) {
            return $dgB - $dgA;
        }
        if ((int) $a['gf'] !== (int) $b['gf']) {
            return (int) $b['gf'] - (int) $a['gf'];
        }
        return strcasecmp((string) $a['nombre'], (string) $b['nombre']);
    });

    $safeName = preg_replace('/[^a-zA-Z0-9_\- ]+/', '', (string) $comp['nombre']);
    $filename = 'clasificacion_' . trim(str_replace(' ', '_', $safeName)) . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, ['equipo_id', 'equipo', 'pj', 'pg', 'pe', 'pp', 'gf', 'gc', 'puntos']);
    foreach ($rows as $r) {
        fputcsv($out, [
            (int) $r['equipo_id'],
            (string) $r['nombre'],
            (int) $r['pj'],
            (int) $r['pg'],
            (int) $r['pe'],
            (int) $r['pp'],
            (int) $r['gf'],
            (int) $r['gc'],
            (int) $r['puntos'],
        ]);
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_csv') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo "<script>alert('No se pudo subir el CSV.'); window.location.href='competicion_clasificacion.php?id=$competicion_id';</script>";
        exit;
    }

    $tmp = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($tmp, 'r');
    if (!$handle) {
        echo "<script>alert('No se pudo leer el CSV.'); window.location.href='competicion_clasificacion.php?id=$competicion_id';</script>";
        exit;
    }

    $firstLine = fgets($handle);
    if ($firstLine === false) {
        fclose($handle);
        echo "<script>alert('El CSV está vacío.'); window.location.href='competicion_clasificacion.php?id=$competicion_id';</script>";
        exit;
    }
    $delimiter = ',';
    $candidates = [",", ";", "\t"];
    foreach ($candidates as $d) {
        if (substr_count($firstLine, $d) > substr_count($firstLine, $delimiter)) {
            $delimiter = $d;
        }
    }
    rewind($handle);

    $header = fgetcsv($handle, 0, $delimiter);
    if (!$header) {
        fclose($handle);
        echo "<script>alert('El CSV no tiene cabecera válida.'); window.location.href='competicion_clasificacion.php?id=$competicion_id';</script>";
        exit;
    }

    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
    $colIndex = [];
    foreach ($header as $i => $col) {
        $key = mb_strtolower(trim((string) $col), 'UTF-8');
        $colIndex[$key] = $i;
    }

    $getVal = function ($row, $name) use ($colIndex) {
        $k = mb_strtolower($name, 'UTF-8');
        if (!isset($colIndex[$k])) return null;
        $idx = $colIndex[$k];
        return isset($row[$idx]) ? $row[$idx] : null;
    };

    $stmtUpdate = $pdo->prepare("
        UPDATE clasificacion
        SET pj = ?, pg = ?, pe = ?, pp = ?, gf = ?, gc = ?, puntos = ?
        WHERE competicion_id = ? AND equipo_id = ?
    ");

    $updated = 0;
    $pdo->beginTransaction();
    try {
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $equipoIdRaw = $getVal($row, 'equipo_id');
            $equipoNameRaw = $getVal($row, 'equipo');

            $eid = null;
            if ($equipoIdRaw !== null && is_numeric($equipoIdRaw)) {
                $candidate = (int) $equipoIdRaw;
                if (isset($equiposById[$candidate])) {
                    $eid = $candidate;
                }
            }

            if ($eid === null && $equipoNameRaw !== null) {
                $key = mb_strtolower(trim((string) $equipoNameRaw), 'UTF-8');
                if (isset($equiposByName[$key])) {
                    $eid = (int) $equiposByName[$key];
                }
            }

            if ($eid === null) {
                continue;
            }

            $pj = asIntOrZero($getVal($row, 'pj'));
            $pg = asIntOrZero($getVal($row, 'pg'));
            $pe = asIntOrZero($getVal($row, 'pe'));
            $pp = asIntOrZero($getVal($row, 'pp'));
            $gf = asIntOrZero($getVal($row, 'gf'));
            $gc = asIntOrZero($getVal($row, 'gc'));
            $puntos = asIntOrZero($getVal($row, 'puntos'));

            if ($pj < 0) $pj = 0;
            if ($pg < 0) $pg = 0;
            if ($pe < 0) $pe = 0;
            if ($pp < 0) $pp = 0;
            if ($gf < 0) $gf = 0;
            if ($gc < 0) $gc = 0;

            $stmtUpdate->execute([$pj, $pg, $pe, $pp, $gf, $gc, $puntos, $competicion_id, $eid]);
            $updated++;
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        fclose($handle);
        echo "<script>alert('Error importando CSV: " . addslashes($e->getMessage()) . "'); window.location.href='competicion_clasificacion.php?id=$competicion_id';</script>";
        exit;
    }
    fclose($handle);

    echo "<script>alert('Importación completada. Filas actualizadas: $updated'); window.location.href='competicion_clasificacion.php?id=$competicion_id';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $data = $_POST['stats'] ?? [];
    if (!is_array($data)) {
        $data = [];
    }

    $stmtUpdate = $pdo->prepare("
        UPDATE clasificacion
        SET pj = ?, pg = ?, pe = ?, pp = ?, gf = ?, gc = ?, puntos = ?
        WHERE competicion_id = ? AND equipo_id = ?
    ");

    $pdo->beginTransaction();
    try {
        foreach ($equipos as $eq) {
            $eid = (int) $eq['id'];
            $row = $data[$eid] ?? [];

            $pj = asIntOrZero($row['pj'] ?? 0);
            $pg = asIntOrZero($row['pg'] ?? 0);
            $pe = asIntOrZero($row['pe'] ?? 0);
            $pp = asIntOrZero($row['pp'] ?? 0);
            $gf = asIntOrZero($row['gf'] ?? 0);
            $gc = asIntOrZero($row['gc'] ?? 0);
            $puntos = asIntOrZero($row['puntos'] ?? 0);

            if ($pj < 0) $pj = 0;
            if ($pg < 0) $pg = 0;
            if ($pe < 0) $pe = 0;
            if ($pp < 0) $pp = 0;
            if ($gf < 0) $gf = 0;
            if ($gc < 0) $gc = 0;

            $stmtUpdate->execute([$pj, $pg, $pe, $pp, $gf, $gc, $puntos, $competicion_id, $eid]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    echo "<script>window.location.href='competicion_clasificacion.php?id=$competicion_id';</script>";
    exit;
}

$stmtClas = $pdo->prepare("
    SELECT c.*, e.id as equipo_id, e.nombre, e.escudo_url
    FROM clasificacion c
    JOIN equipo e ON c.equipo_id = e.id
    WHERE c.competicion_id = ?
");
$stmtClas->execute([$competicion_id]);
$clasificacion = $stmtClas->fetchAll(PDO::FETCH_ASSOC);

usort($clasificacion, function ($a, $b) {
    if ((int) $a['puntos'] !== (int) $b['puntos']) {
        return (int) $b['puntos'] - (int) $a['puntos'];
    }
    $dgA = (int) $a['gf'] - (int) $a['gc'];
    $dgB = (int) $b['gf'] - (int) $b['gc'];
    if ($dgA !== $dgB) {
        return $dgB - $dgA;
    }
    if ((int) $a['gf'] !== (int) $b['gf']) {
        return (int) $b['gf'] - (int) $a['gf'];
    }
    return strcasecmp((string) $a['nombre'], (string) $b['nombre']);
});
?>

<div class="container" style="margin-top: 40px; margin-bottom: 60px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div style="display: flex; align-items: center; gap: 20px;">
            <?php if ($comp['logo_url']): ?>
                <img src="<?= $comp['logo_url'] ?>" style="width: 50px; height: 50px; object-fit: contain;">
            <?php endif; ?>
            <div>
                <h1 style="margin: 0; font-size: 2rem;"><?= htmlspecialchars($comp['nombre']) ?></h1>
                <p style="color: var(--text-muted); margin: 0;">Clasificación manual o recalculada al guardar partidos (liga / fase de grupos).</p>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap: 10px; flex-wrap: wrap; justify-content: flex-end;">
            <a href="competicion_clasificacion.php?id=<?= $competicion_id ?>&export=csv" class="btn-admin" style="margin:0; background: rgba(255,255,255,0.05); color: var(--text-muted);">Exportar CSV</a>
            <form method="POST" enctype="multipart/form-data" style="display:flex; gap: 10px; align-items:center; margin: 0;">
                <input type="hidden" name="action" value="import_csv">
                <input type="file" name="csv_file" accept=".csv,text/csv" class="form-control-admin" style="width: 260px;">
                <button type="submit" class="btn-admin" style="margin:0; background: rgba(255,255,255,0.05); color: var(--text-muted);">Importar</button>
            </form>
            <a href="competiciones.php" class="btn-admin" style="margin:0; background: var(--secondary-color);">Volver</a>
        </div>
    </div>

    <div style="display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
        <a href="competicion_participantes.php?id=<?= $competicion_id ?>" class="btn-admin" style="background: rgba(255,255,255,0.05); color: var(--text-muted); margin-left: 0;">Equipos</a>
        <a href="competicion_partidos.php?id=<?= $competicion_id ?>" class="btn-admin" style="background: rgba(255,255,255,0.05); color: var(--text-muted); margin-left: 0;">Resultados</a>
        <a href="#" class="btn-admin" style="background: var(--accent-color); color: white; margin-left: 0;">Clasificación</a>
    </div>

    <?php if (empty($equipos)): ?>
        <div class="admin-card" style="text-align: center; padding: 50px; color: var(--text-muted);">
            <div style="font-size: 2rem; margin-bottom: 10px;">📋</div>
            No hay equipos inscritos en la competición.
            <div style="margin-top: 15px;">
                <a href="competicion_participantes.php?id=<?= $competicion_id ?>" class="btn-admin" style="background: #3b82f6;">Gestionar Equipos</a>
            </div>
        </div>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <div class="admin-card" style="padding: 0; overflow: hidden;">
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center;">#</th>
                                <th>Equipo</th>
                                <th style="text-align: center;">PJ</th>
                                <th style="text-align: center;">PG</th>
                                <th style="text-align: center;">PE</th>
                                <th style="text-align: center;">PP</th>
                                <th style="text-align: center;">GF</th>
                                <th style="text-align: center;">GC</th>
                                <th style="text-align: center;">DG</th>
                                <th style="text-align: center; color: var(--accent-color);">PTS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $pos = 1; ?>
                            <?php foreach ($clasificacion as $row): ?>
                                <?php
                                $currentPos = $pos;
                                $marca = null;
                                foreach ($marcasVisuales as $m) {
                                    if (clasificacion_visual_posicion_en_spec($currentPos, $m['posiciones'])) {
                                        $marca = $m;
                                        break;
                                    }
                                }
                                $rowStyle = '';
                                if ($marca) {
                                    $rgb = clasificacion_visual_hex_to_rgb($marca['color']);
                                    $bg = $rgb ? ('rgba(' . $rgb[0] . ',' . $rgb[1] . ',' . $rgb[2] . ',0.12)') : 'rgba(255,255,255,0.03)';
                                    $rowStyle = 'box-shadow: inset 6px 0 0 ' . $marca['color'] . '; background: ' . $bg . ';';
                                }
                                ?>
                                <?php $dg = (int) $row['gf'] - (int) $row['gc']; ?>
                                <tr style="<?= htmlspecialchars($rowStyle) ?>">
                                    <td style="text-align: center; font-weight: bold; color: var(--text-muted);"><?= $pos++ ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <?php if (!empty($row['escudo_url'])): ?>
                                                <img src="<?= htmlspecialchars($row['escudo_url']) ?>" style="width: 28px; height: 28px; object-fit: contain;">
                                            <?php else: ?>
                                                <div style="width: 28px; height: 28px; background: rgba(255,255,255,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem;">🛡️</div>
                                            <?php endif; ?>
                                            <span style="font-weight: 600; font-size: 1.05rem; color: #fff;"><?= htmlspecialchars($row['nombre']) ?></span>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <input type="number" min="0" name="stats[<?= (int) $row['equipo_id'] ?>][pj]" value="<?= (int) $row['pj'] ?>" class="form-control-admin" style="width: 70px; margin: 0 auto; text-align: center;">
                                    </td>
                                    <td style="text-align: center;">
                                        <input type="number" min="0" name="stats[<?= (int) $row['equipo_id'] ?>][pg]" value="<?= (int) $row['pg'] ?>" class="form-control-admin" style="width: 70px; margin: 0 auto; text-align: center;">
                                    </td>
                                    <td style="text-align: center;">
                                        <input type="number" min="0" name="stats[<?= (int) $row['equipo_id'] ?>][pe]" value="<?= (int) $row['pe'] ?>" class="form-control-admin" style="width: 70px; margin: 0 auto; text-align: center;">
                                    </td>
                                    <td style="text-align: center;">
                                        <input type="number" min="0" name="stats[<?= (int) $row['equipo_id'] ?>][pp]" value="<?= (int) $row['pp'] ?>" class="form-control-admin" style="width: 70px; margin: 0 auto; text-align: center;">
                                    </td>
                                    <td style="text-align: center;">
                                        <input type="number" min="0" name="stats[<?= (int) $row['equipo_id'] ?>][gf]" value="<?= (int) $row['gf'] ?>" class="form-control-admin" style="width: 70px; margin: 0 auto; text-align: center;">
                                    </td>
                                    <td style="text-align: center;">
                                        <input type="number" min="0" name="stats[<?= (int) $row['equipo_id'] ?>][gc]" value="<?= (int) $row['gc'] ?>" class="form-control-admin" style="width: 70px; margin: 0 auto; text-align: center;">
                                    </td>
                                    <td style="text-align: center; font-weight: 700; color: var(--text-muted);">
                                        <?= $dg > 0 ? '+' . $dg : $dg ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <input type="number" name="stats[<?= (int) $row['equipo_id'] ?>][puntos]" value="<?= (int) $row['puntos'] ?>" class="form-control-admin" style="width: 80px; margin: 0 auto; text-align: center; font-weight: 800;">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="margin-top: 18px; display: flex; justify-content: flex-end; gap: 10px;">
                <a href="competicion_participantes.php?id=<?= $competicion_id ?>" class="btn-admin" style="background: rgba(255,255,255,0.05); color: var(--text-muted);">Gestionar Equipos</a>
                <button type="submit" class="btn-admin" style="background: #3b82f6; font-weight: 800;">Guardar Clasificación</button>
            </div>
        </form>

        <div class="admin-card" style="margin-top: 22px;">
            <h3 style="margin-top: 0;">Marcas visuales</h3>

            <form method="POST" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: end; margin-bottom: 18px;">
                <input type="hidden" name="action" value="add_visual_marca">
                <div style="flex: 1; min-width: 220px;">
                    <label style="display:block; color: var(--text-muted); margin-bottom: 6px;">Nombre</label>
                    <input type="text" name="nombre" class="form-control-admin" placeholder="Campeón / Champions / Descenso" required>
                </div>
                <div style="width: 110px;">
                    <label style="display:block; color: var(--text-muted); margin-bottom: 6px;">Color</label>
                    <input type="color" name="color" value="#d4af37" style="width: 100%; height: 46px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); padding: 4px;">
                </div>
                <div style="flex: 1; min-width: 220px;">
                    <label style="display:block; color: var(--text-muted); margin-bottom: 6px;">Posiciones</label>
                    <input type="text" name="posiciones" class="form-control-admin" placeholder="1 o 1-4 o 1,3,5-7" required>
                </div>
                <div style="width: 110px;">
                    <label style="display:block; color: var(--text-muted); margin-bottom: 6px;">Orden</label>
                    <input type="number" name="orden" class="form-control-admin" value="0" style="width: 100%;">
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-admin" style="margin: 0; background: var(--accent-color); font-weight: 800;">Añadir</button>
                </div>
            </form>

            <?php if (empty($marcasVisuales)): ?>
                <div style="color: var(--text-muted);">Todavía no hay marcas visuales configuradas.</div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="save_visual_marcas">
                    <div style="overflow: hidden; border: 1px solid rgba(255,255,255,0.06); border-radius: 12px;">
                        <table class="admin-table" style="margin: 0;">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th style="width: 120px;">Color</th>
                                    <th>Posiciones</th>
                                    <th style="width: 120px; text-align: center;">Orden</th>
                                    <th style="width: 110px; text-align: right;">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($marcasVisuales as $m): ?>
                                    <tr>
                                        <td>
                                            <input type="text" name="marcas[<?= htmlspecialchars((string) $m['id']) ?>][nombre]" value="<?= htmlspecialchars((string) $m['nombre']) ?>" class="form-control-admin">
                                        </td>
                                        <td>
                                            <input type="color" name="marcas[<?= htmlspecialchars((string) $m['id']) ?>][color]" value="<?= htmlspecialchars((string) $m['color']) ?>" style="width: 100%; height: 46px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); padding: 4px;">
                                        </td>
                                        <td>
                                            <input type="text" name="marcas[<?= htmlspecialchars((string) $m['id']) ?>][posiciones]" value="<?= htmlspecialchars((string) $m['posiciones']) ?>" class="form-control-admin">
                                        </td>
                                        <td style="text-align: center;">
                                            <input type="number" name="marcas[<?= htmlspecialchars((string) $m['id']) ?>][orden]" value="<?= (int) $m['orden'] ?>" class="form-control-admin" style="width: 100px; margin: 0 auto;">
                                        </td>
                                        <td style="text-align: right;">
                                            <button type="submit" name="delete_visual_marca_id" value="<?= htmlspecialchars((string) $m['id']) ?>" class="btn-admin" style="margin: 0; background: rgba(255,255,255,0.05); color: #ef4444;" onclick="return confirm('¿Eliminar esta marca?')">Eliminar</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 14px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn-admin" style="margin: 0; background: #3b82f6; font-weight: 800;">Guardar marcas</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>