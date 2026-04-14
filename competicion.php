<?php
require_once 'db.php';
require_once __DIR__ . '/includes/clasificacion_visual.php';

if (!isset($_GET['id'])) {
    $page_title = 'Competición';
    include 'includes/header.php';
    echo '<div class="container page-shell"><div class="message-panel">Competición no especificada.</div></div>';
    include 'includes/footer.php';
    exit;
}

$comp_id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT c.*, tc.nombre as tipo_nombre 
                       FROM competicion c 
                       JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id 
                       WHERE c.id = ?");
$stmt->execute([$comp_id]);
$comp = $stmt->fetch();

if (!$comp) {
    $page_title = 'Competición';
    include 'includes/header.php';
    echo '<div class="container page-shell"><div class="message-panel">Competición no encontrada.</div></div>';
    include 'includes/footer.php';
    exit;
}

$page_title = $comp['nombre'];
include 'includes/header.php';

$stmtClas = $pdo->prepare("
    SELECT 
        e.id as equipo_id,
        e.nombre,
        e.escudo_url,
        COALESCE(c.pj, 0) as pj,
        COALESCE(c.pg, 0) as pg,
        COALESCE(c.pe, 0) as pe,
        COALESCE(c.pp, 0) as pp,
        COALESCE(c.gf, 0) as gf,
        COALESCE(c.gc, 0) as gc,
        COALESCE(c.puntos, 0) as puntos
    FROM competicion_equipo ce
    JOIN equipo e ON ce.equipo_id = e.id
    LEFT JOIN clasificacion c ON c.competicion_id = ce.competicion_id AND c.equipo_id = e.id
    WHERE ce.competicion_id = ? AND COALESCE(ce.eliminado, 0) = 0
");
$stmtClas->execute([$comp_id]);
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

$marcasVisuales = clasificacion_visual_get_marks($comp_id);

$stmtEquipos = $pdo->prepare("
    SELECT e.*, p.nombre as pais_nombre, p.bandera_url
    FROM equipo e
    JOIN competicion_equipo ce ON e.id = ce.equipo_id
    LEFT JOIN pais p ON e.pais_id = p.id
    WHERE ce.competicion_id = ? AND COALESCE(ce.eliminado, 0) = 0
    ORDER BY e.nombre ASC
");
$stmtEquipos->execute([$comp_id]);
$equipos = $stmtEquipos->fetchAll(PDO::FETCH_ASSOC);

$partidosLista = [];
try {
    $stmtP = $pdo->prepare(
        "SELECT p.*, el.nombre AS nl, ev.nombre AS nv
         FROM partido p
         JOIN equipo el ON el.id = p.equipo_local_id
         JOIN equipo ev ON ev.id = p.equipo_visitante_id
         WHERE p.competicion_id = ?
         ORDER BY p.es_eliminatoria ASC, p.orden_fase ASC, p.jornada ASC, p.fecha ASC, p.id ASC"
    );
    $stmtP->execute([$comp_id]);
    $partidosLista = $stmtP->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $partidosLista = [];
}
?>

<div class="container page-shell">
    <header class="page-hero">
        <div class="page-hero-main">
            <div class="page-hero-logo">
                <?php if (!empty($comp['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($comp['logo_url']) ?>" alt="">
                <?php else: ?>
                    <span class="page-hero-icon" aria-hidden="true">🏆</span>
                <?php endif; ?>
            </div>
            <div>
                <h1 class="page-hero-title"><?= htmlspecialchars($comp['nombre']) ?></h1>
                <p class="page-hero-meta">
                    <?= htmlspecialchars($comp['tipo_nombre']) ?> · <?= htmlspecialchars($comp['temporada_actual']) ?>
                </p>
            </div>
        </div>
        <button type="button" class="btn-back" onclick="history.back()">← Volver</button>
    </header>

    <?php if (!empty($partidosLista)): ?>
        <section style="margin-bottom: 40px;">
            <h2 class="section-title">Partidos</h2>
            <div class="admin-card" style="padding: 0; overflow: hidden;">
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Fase</th>
                                <th>J.</th>
                                <th>Local</th>
                                <th style="text-align:center;">Resultado</th>
                                <th>Visitante</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partidosLista as $pt): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pt['fase']) ?><?= !empty($pt['es_eliminatoria']) ? ' · KO' : '' ?></td>
                                    <td><?= $pt['jornada'] !== null ? (int) $pt['jornada'] : '—' ?></td>
                                    <td><?= htmlspecialchars($pt['nl']) ?></td>
                                    <td style="text-align:center; font-weight:800;">
                                        <?php if ($pt['estado'] === 'finalizado' && $pt['goles_local'] !== null && $pt['goles_visitante'] !== null): ?>
                                            <?= (int) $pt['goles_local'] ?> – <?= (int) $pt['goles_visitante'] ?>
                                            <?php if ($pt['penales_local'] !== null && $pt['penales_visitante'] !== null): ?>
                                                <span style="font-size:0.78rem; color:var(--text-muted); font-weight:600;"> (p. <?= (int) $pt['penales_local'] ?>–<?= (int) $pt['penales_visitante'] ?>)</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);">vs</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($pt['nv']) ?></td>
                                    <td style="color:var(--text-muted); font-size:0.9rem;"><?= $pt['fecha'] ? htmlspecialchars((string) $pt['fecha']) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section style="margin-bottom: 40px;">
        <h2 class="section-title">Clasificación</h2>
        <?php if (empty($clasificacion)): ?>
            <div class="message-panel">No hay equipos inscritos.</div>
        <?php else: ?>
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
                                <th style="text-align: center;">PTS</th>
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
                                                <img src="<?= htmlspecialchars($row['escudo_url']) ?>" alt="" style="width: 28px; height: 28px; object-fit: contain;">
                                            <?php else: ?>
                                                <div style="width: 28px; height: 28px; background: rgba(255,255,255,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem;" aria-hidden="true">🛡️</div>
                                            <?php endif; ?>
                                            <a href="equipo.php?id=<?= (int) $row['equipo_id'] ?>" style="text-decoration: none; color: #fff; font-weight: 700;">
                                                <?= htmlspecialchars($row['nombre']) ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td style="text-align: center;"><?= (int) $row['pj'] ?></td>
                                    <td style="text-align: center;"><?= (int) $row['pg'] ?></td>
                                    <td style="text-align: center;"><?= (int) $row['pe'] ?></td>
                                    <td style="text-align: center;"><?= (int) $row['pp'] ?></td>
                                    <td style="text-align: center;"><?= (int) $row['gf'] ?></td>
                                    <td style="text-align: center;"><?= (int) $row['gc'] ?></td>
                                    <td style="text-align: center;"><?= $dg > 0 ? '+' . $dg : $dg ?></td>
                                    <td style="text-align: center; font-weight: 900; color: var(--accent-color);"><?= (int) $row['puntos'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if (!empty($marcasVisuales)): ?>
                <div class="admin-card" style="padding: 18px 20px; margin-top: 14px;">
                    <div style="font-weight: 800; margin-bottom: 12px;">Leyenda</div>
                    <div class="legend-strip">
                        <?php foreach ($marcasVisuales as $m): ?>
                            <div class="legend-item">
                                <span class="legend-swatch" style="background: <?= htmlspecialchars($m['color']) ?>;"></span>
                                <span class="legend-name"><?= htmlspecialchars($m['nombre']) ?></span>
                                <span class="legend-pos"><?= htmlspecialchars($m['posiciones']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section style="margin-bottom: 60px;">
        <h2 class="section-title">Equipos</h2>
        <?php if (empty($equipos)): ?>
            <div class="message-panel">No hay equipos inscritos.</div>
        <?php else: ?>
            <div class="competitions-grid" style="grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));">
                <?php foreach ($equipos as $eq): ?>
                    <a href="equipo.php?id=<?= (int) $eq['id'] ?>" class="comp-card">
                        <div class="comp-card-header" style="justify-content: flex-start; gap: 12px;">
                            <?php if (!empty($eq['escudo_url'])): ?>
                                <img src="<?= htmlspecialchars($eq['escudo_url']) ?>" class="comp-logo" style="width: 44px; height: 44px; object-fit: contain;" alt="">
                            <?php else: ?>
                                <div class="comp-logo-placeholder" style="width: 44px; height: 44px;">🛡️</div>
                            <?php endif; ?>
                            <div>
                                <h3 style="margin: 0; color: #fff;"><?= htmlspecialchars($eq['nombre']) ?></h3>
                                <div class="comp-meta">
                                    <?php if (!empty($eq['bandera_url'])): ?>
                                        <img src="<?= htmlspecialchars($eq['bandera_url']) ?>" alt="" class="comp-flag">
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($eq['pais_nombre'] ?? '') ?></span>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
