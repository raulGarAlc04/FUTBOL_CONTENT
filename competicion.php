<?php
require_once 'db.php';
require_once __DIR__ . '/includes/clasificacion_visual.php';
include 'includes/header.php';

if (!isset($_GET['id'])) {
    echo "<div class='container' style='margin-top: 40px;'><p>Competición no especificada.</p></div>";
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
    echo "<div class='container' style='margin-top: 40px;'><p>Competición no encontrada.</p></div>";
    include 'includes/footer.php';
    exit;
}

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
    WHERE ce.competicion_id = ?
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
    WHERE ce.competicion_id = ?
    ORDER BY e.nombre ASC
");
$stmtEquipos->execute([$comp_id]);
$equipos = $stmtEquipos->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container" style="margin-top: 40px;">
    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid var(--accent-color); padding-bottom: 20px; margin-bottom: 30px;">
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="width: 80px; height: 80px; background: rgba(255,255,255,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; padding: 10px;">
                <?php if (!empty($comp['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($comp['logo_url']) ?>" style="max-width: 100%; max-height: 100%;">
                <?php else: ?>
                    <span style="font-size: 2.5rem;">🏆</span>
                <?php endif; ?>
            </div>
            <div>
                <h1 style="margin: 0; line-height: 1;"><?= htmlspecialchars($comp['nombre']) ?></h1>
                <div style="margin-top: 5px; color: var(--text-muted);">
                    <?= htmlspecialchars($comp['tipo_nombre']) ?> • <?= htmlspecialchars($comp['temporada_actual']) ?>
                </div>
            </div>
        </div>
        <button onclick="history.back()" style="background-color: var(--secondary-color); color: #fff; border: 1px solid rgba(255,255,255,0.2); padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold;">
            ⬅ Volver
        </button>
    </div>

    <section style="margin-bottom: 40px;">
        <h2 class="section-title">Clasificación</h2>
        <?php if (empty($clasificacion)): ?>
            <div class="admin-card" style="padding: 25px; color: var(--text-muted);">No hay equipos inscritos.</div>
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
                                                <img src="<?= htmlspecialchars($row['escudo_url']) ?>" style="width: 28px; height: 28px; object-fit: contain;">
                                            <?php else: ?>
                                                <div style="width: 28px; height: 28px; background: rgba(255,255,255,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem;">🛡️</div>
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
                    <div style="font-weight: 800; margin-bottom: 10px;">Leyenda</div>
                    <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                        <?php foreach ($marcasVisuales as $m): ?>
                            <div style="display: inline-flex; align-items: center; gap: 10px; padding: 8px 10px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px;">
                                <span style="width: 12px; height: 12px; border-radius: 4px; background: <?= htmlspecialchars($m['color']) ?>;"></span>
                                <span style="font-weight: 700;"><?= htmlspecialchars($m['nombre']) ?></span>
                                <span style="color: var(--text-muted); font-size: 0.95rem;">(<?= htmlspecialchars($m['posiciones']) ?>)</span>
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
            <div class="admin-card" style="padding: 25px; color: var(--text-muted);">No hay equipos inscritos.</div>
        <?php else: ?>
            <div class="competitions-grid" style="grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));">
                <?php foreach ($equipos as $eq): ?>
                    <a href="equipo.php?id=<?= (int) $eq['id'] ?>" class="comp-card">
                        <div class="comp-card-header" style="justify-content: flex-start; gap: 12px;">
                            <?php if (!empty($eq['escudo_url'])): ?>
                                <img src="<?= htmlspecialchars($eq['escudo_url']) ?>" class="comp-logo" style="width: 44px; height: 44px; object-fit: contain;">
                            <?php else: ?>
                                <div class="comp-logo-placeholder" style="width: 44px; height: 44px;">🛡️</div>
                            <?php endif; ?>
                            <div>
                                <h3 style="margin: 0; color: #fff;"><?= htmlspecialchars($eq['nombre']) ?></h3>
                                <div class="comp-meta">
                                    <?php if (!empty($eq['bandera_url'])): ?>
                                        <img src="<?= htmlspecialchars($eq['bandera_url']) ?>" style="width: 16px;">
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
