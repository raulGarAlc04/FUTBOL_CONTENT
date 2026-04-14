<?php
require_once 'db.php';
$page_full_width = true;
include 'includes/header.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container' style='margin-top: 40px;'><p>Equipo no especificado.</p></div>";
    include 'includes/footer.php';
    exit;
}
$equipo_id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT e.*, p.nombre as pais_nombre, p.bandera_url 
                       FROM equipo e 
                       LEFT JOIN pais p ON e.pais_id = p.id 
                       WHERE e.id = ?");
$stmt->execute([$equipo_id]);
$equipo = $stmt->fetch();

if (!$equipo) {
    echo "<div class='container' style='margin-top: 40px;'><p>Equipo no encontrado.</p></div>";
    include 'includes/footer.php';
    exit;
}

$ordenJugadores = isset($_GET['orden_jugadores']) ? trim((string) $_GET['orden_jugadores']) : 'posicion';
if (!in_array($ordenJugadores, ['posicion', 'dorsal'], true)) {
    $ordenJugadores = 'posicion';
}

$orderByJug = "FIELD(j.posicion, 'Portero', 'Defensa', 'Centrocampista', 'Delantero', 'Entrenador'), (j.dorsal IS NULL OR j.dorsal = 0), j.dorsal ASC, j.nombre ASC";
if ($ordenJugadores === 'dorsal') {
    $orderByJug = "(j.dorsal IS NULL OR j.dorsal = 0), j.dorsal ASC, FIELD(j.posicion, 'Portero', 'Defensa', 'Centrocampista', 'Delantero', 'Entrenador'), j.nombre ASC";
}

$stmtJug = $pdo->prepare("SELECT j.*, p.nombre as pais_nombre, p.bandera_url 
                          FROM jugadores j 
                          LEFT JOIN pais p ON j.pais_id = p.id 
                          WHERE j.equipo_actual_id = ? 
                          ORDER BY $orderByJug");
$stmtJug->execute([$equipo_id]);
$jugadores = $stmtJug->fetchAll(PDO::FETCH_ASSOC);

$plantilla = [
    'Portero' => [],
    'Defensa' => [],
    'Centrocampista' => [],
    'Delantero' => [],
    'Entrenador' => []
];

foreach ($jugadores as $j) {
    $pos = $j['posicion'] ?: 'Centrocampista';
    if (!isset($plantilla[$pos])) {
        $pos = 'Centrocampista';
    }
    $plantilla[$pos][] = $j;
}

$stmtComps = $pdo->prepare("
    SELECT c.id, c.nombre, c.temporada_actual, c.logo_url, tc.nombre as tipo_nombre
    FROM competicion c
    JOIN competicion_equipo ce ON ce.competicion_id = c.id
    JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id
    WHERE ce.equipo_id = ?
    ORDER BY c.nombre ASC
");
$stmtComps->execute([$equipo_id]);
$competiciones = $stmtComps->fetchAll(PDO::FETCH_ASSOC);

$clasificacionesPorCompeticion = [];
if (!empty($competiciones)) {
    $stmtClas = $pdo->prepare("
        SELECT 
            e.id as equipo_id,
            e.nombre,
            e.escudo_url,
            COALESCE(cl.pj, 0) as pj,
            COALESCE(cl.pg, 0) as pg,
            COALESCE(cl.pe, 0) as pe,
            COALESCE(cl.pp, 0) as pp,
            COALESCE(cl.gf, 0) as gf,
            COALESCE(cl.gc, 0) as gc,
            COALESCE(cl.puntos, 0) as puntos
        FROM competicion_equipo ce
        JOIN equipo e ON e.id = ce.equipo_id
        LEFT JOIN clasificacion cl ON cl.competicion_id = ce.competicion_id AND cl.equipo_id = e.id
        WHERE ce.competicion_id = ?
    ");

    foreach ($competiciones as $comp) {
        $compId = (int) $comp['id'];
        $stmtClas->execute([$compId]);
        $rows = $stmtClas->fetchAll(PDO::FETCH_ASSOC);

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

        $pos = 1;
        foreach ($rows as &$r) {
            $r['pos'] = $pos++;
        }
        unset($r);

        $clasificacionesPorCompeticion[$compId] = $rows;
    }
}
?>

<div class="container team-page">
    <div class="team-hero">
        <div class="team-hero-left">
            <div class="team-crest">
                <?php if (!empty($equipo['escudo_url'])): ?>
                    <img src="<?= htmlspecialchars($equipo['escudo_url']) ?>" alt="">
                <?php else: ?>
                    <span class="team-crest-fallback">🛡️</span>
                <?php endif; ?>
            </div>

            <div class="team-hero-text">
                <h1 class="team-title"><?= htmlspecialchars($equipo['nombre']) ?></h1>
                <div class="team-meta">
                    <?php if (!empty($equipo['bandera_url']) || !empty($equipo['pais_nombre'])): ?>
                        <span class="team-chip">
                            <?php if (!empty($equipo['bandera_url'])): ?>
                                <img src="<?= htmlspecialchars($equipo['bandera_url']) ?>" alt="">
                            <?php endif; ?>
                            <span><?= htmlspecialchars($equipo['pais_nombre'] ?? '') ?></span>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($equipo['estadio'])): ?>
                        <span class="team-chip">
                            <span class="team-chip-icon">🏟️</span>
                            <span><?= htmlspecialchars($equipo['estadio']) ?></span>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($equipo['fundacion'])): ?>
                        <span class="team-chip">
                            <span class="team-chip-icon">📅</span>
                            <span><?= htmlspecialchars($equipo['fundacion']) ?></span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="team-hero-actions">
            <button onclick="history.back()" class="btn-admin team-back">Volver</button>
        </div>
    </div>

    <div class="team-layout">
        <div class="team-main">
            <section class="team-section">
                <div class="team-section-head">
                    <h2 class="team-section-title">Plantilla</h2>
                    <div class="team-section-subtitle"><?= count($jugadores) ?> jugadores</div>
                </div>

                <?php if (empty($jugadores)): ?>
                    <div class="team-empty">
                        <div class="team-empty-icon">👤</div>
                        <div>No hay jugadores registrados.</div>
                    </div>
                <?php else: ?>
                    <div style="display:flex; justify-content: flex-end; margin-bottom: 14px;">
                        <form method="GET" style="display:flex; gap: 10px; align-items:center;">
                            <input type="hidden" name="id" value="<?= (int) $equipo_id ?>">
                            <select name="orden_jugadores" class="form-control-admin" style="width: 220px;">
                                <option value="posicion" <?= $ordenJugadores === 'posicion' ? 'selected' : '' ?>>Ordenar por posición</option>
                                <option value="dorsal" <?= $ordenJugadores === 'dorsal' ? 'selected' : '' ?>>Ordenar por dorsal</option>
                            </select>
                            <button type="submit" class="btn-admin" style="margin:0; padding: 10px 16px;">Aplicar</button>
                        </form>
                    </div>

                    <?php if ($ordenJugadores === 'posicion'): ?>
                        <div class="roster-grid">
                            <?php foreach ($plantilla as $pos => $lista): ?>
                                <?php if (empty($lista)) continue; ?>
                                <div class="roster-card">
                                    <div class="roster-card-head">
                                        <div class="roster-title"><?= htmlspecialchars($pos) ?></div>
                                        <div class="roster-count"><?= count($lista) ?></div>
                                    </div>
                                    <div class="roster-list">
                                        <?php foreach ($lista as $j): ?>
                                            <div class="player-row">
                                                <div class="player-number"><?= !empty($j['dorsal']) ? (int) $j['dorsal'] : '-' ?></div>
                                                <div class="player-info">
                                                    <div class="player-name"><?= htmlspecialchars($j['nombre']) ?></div>
                                                </div>
                                                <div class="player-country">
                                                    <?php if (!empty($j['bandera_url'])): ?>
                                                        <img src="<?= htmlspecialchars($j['bandera_url']) ?>" alt="">
                                                    <?php endif; ?>
                                                    <span><?= htmlspecialchars($j['pais_nombre'] ?? '') ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="admin-card" style="padding: 0; overflow: hidden;">
                            <div class="table-wrap">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 90px; text-align: center;">Dorsal</th>
                                            <th>Jugador</th>
                                            <th style="width: 180px;">Posición</th>
                                            <th>Nacionalidad</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($jugadores as $j): ?>
                                            <?php
                                            $posClass = 'badge-info';
                                            if (($j['posicion'] ?? '') === 'Portero') $posClass = 'badge-warning';
                                            if (($j['posicion'] ?? '') === 'Defensa') $posClass = 'badge-success';
                                            if (($j['posicion'] ?? '') === 'Delantero') $posClass = 'badge-striker';
                                            if (($j['posicion'] ?? '') === 'Entrenador') $posClass = 'badge-danger';
                                            ?>
                                            <tr>
                                                <td style="text-align: center; font-weight: 900; color: var(--accent-color);">
                                                    <?= !empty($j['dorsal']) ? (int) $j['dorsal'] : '-' ?>
                                                </td>
                                                <td style="font-weight: 800; color: #fff;">
                                                    <?= htmlspecialchars((string) $j['nombre']) ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?= $posClass ?>"><?= htmlspecialchars((string) ($j['posicion'] ?? '')) ?></span>
                                                </td>
                                                <td>
                                                    <div style="display:flex; align-items:center; gap: 10px;">
                                                        <?php if (!empty($j['bandera_url'])): ?>
                                                            <img src="<?= htmlspecialchars((string) $j['bandera_url']) ?>" alt="" style="width: 22px; border-radius: 4px;">
                                                        <?php endif; ?>
                                                        <span style="color: var(--text-color); font-weight: 700;"><?= htmlspecialchars((string) ($j['pais_nombre'] ?? '')) ?></span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>

        <aside class="team-aside">
            <section class="team-section">
                <div class="team-section-head">
                    <h2 class="team-section-title">Competiciones</h2>
                    <div class="team-section-subtitle"><?= count($competiciones) ?></div>
                </div>

                <?php if (empty($competiciones)): ?>
                    <div class="team-empty team-empty-compact">
                        <div class="team-empty-icon">🏆</div>
                        <div>Este equipo no está inscrito en ninguna competición.</div>
                    </div>
                <?php else: ?>
                    <div class="team-comp-list">
                        <?php foreach ($competiciones as $c): ?>
                            <a href="competicion.php?id=<?= (int) $c['id'] ?>" class="team-comp-item">
                                <div class="team-comp-logo">
                                    <?php if (!empty($c['logo_url'])): ?>
                                        <img src="<?= htmlspecialchars($c['logo_url']) ?>" alt="">
                                    <?php else: ?>
                                        <span>🏆</span>
                                    <?php endif; ?>
                                </div>
                                <div class="team-comp-text">
                                    <div class="team-comp-name"><?= htmlspecialchars($c['nombre']) ?></div>
                                    <div class="team-comp-meta"><?= htmlspecialchars($c['temporada_actual']) ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if (!empty($competiciones)): ?>
                <section class="team-section team-section-standings">
                    <div class="team-section-head">
                        <h2 class="team-section-title">Clasificación</h2>
                        <div class="team-section-subtitle">Equipo marcado</div>
                    </div>

                    <div class="standings-list">
                        <?php foreach ($competiciones as $c): ?>
                            <?php $compId = (int) $c['id']; ?>
                            <?php $rows = $clasificacionesPorCompeticion[$compId] ?? []; ?>

                            <div class="standings-card">
                                <div class="standings-head">
                                    <div class="standings-name"><?= htmlspecialchars($c['nombre']) ?></div>
                                    <div class="standings-season"><?= htmlspecialchars($c['temporada_actual']) ?></div>
                                </div>

                                <?php if (empty($rows)): ?>
                                    <div class="team-empty team-empty-compact">
                                        <div class="team-empty-icon">📋</div>
                                        <div>Sin datos de clasificación.</div>
                                    </div>
                                <?php else: ?>
                                    <div class="standings-table-wrap">
                                        <table class="standings-table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th class="standings-team">Equipo</th>
                                                    <th>PTS</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rows as $r): ?>
                                                    <?php $isMe = (int) $r['equipo_id'] === $equipo_id; ?>
                                                    <tr class="<?= $isMe ? 'standings-row-me' : '' ?>">
                                                        <td class="standings-pos"><?= (int) $r['pos'] ?></td>
                                                        <td class="standings-team">
                                                            <span class="standings-team-name"><?= htmlspecialchars($r['nombre']) ?></span>
                                                        </td>
                                                        <td class="standings-pts"><?= (int) $r['puntos'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
