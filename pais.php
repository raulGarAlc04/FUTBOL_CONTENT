<?php
require_once 'db.php';
include 'includes/header.php';

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container' style='margin-top: 40px;'><p>País no especificado.</p></div>";
    include 'includes/footer.php';
    exit;
}

$pais_id = $_GET['id'];

// Obtener info del país
$stmt = $pdo->prepare("SELECT * FROM pais WHERE id = ?");
$stmt->execute([$pais_id]);
$pais = $stmt->fetch();

if (!$pais) {
    echo "<div class='container' style='margin-top: 40px;'><p>País no encontrado.</p></div>";
    include 'includes/footer.php';
    exit;
}

// Obtener competiciones del país con su tipo
$sql = "SELECT c.*, tc.nombre as tipo_nombre 
        FROM competicion c 
        JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id 
        WHERE c.pais_id = ? 
        ORDER BY c.nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$pais_id]);
$competiciones = $stmt->fetchAll();

$stmtEquipos = $pdo->prepare("
    SELECT e.id, e.nombre, e.escudo_url
    FROM equipo e
    WHERE e.pais_id = ?
    ORDER BY e.nombre ASC
");
$stmtEquipos->execute([$pais_id]);
$equipos = $stmtEquipos->fetchAll(PDO::FETCH_ASSOC);

$posicionesDisponibles = ['Portero', 'Defensa', 'Centrocampista', 'Delantero', 'Entrenador'];
$filtroEquipo = isset($_GET['equipo_id']) ? (int) $_GET['equipo_id'] : -1;
$filtroPosicion = isset($_GET['posicion']) ? trim((string) $_GET['posicion']) : '';
$ordenJugadores = isset($_GET['orden_jugadores']) ? trim((string) $_GET['orden_jugadores']) : 'posicion';
if (!in_array($filtroPosicion, $posicionesDisponibles, true)) {
    $filtroPosicion = '';
}
if (!in_array($ordenJugadores, ['equipo', 'dorsal', 'posicion'], true)) {
    $ordenJugadores = 'posicion';
}

$stmtEquiposJugadores = $pdo->prepare("
    SELECT DISTINCT e.id, e.nombre
    FROM jugadores j
    JOIN equipo e ON e.id = j.equipo_actual_id
    WHERE j.pais_id = ?
    ORDER BY e.nombre ASC
");
$stmtEquiposJugadores->execute([$pais_id]);
$equiposJugadores = $stmtEquiposJugadores->fetchAll(PDO::FETCH_ASSOC);

$stmtSinEquipo = $pdo->prepare("SELECT COUNT(*) FROM jugadores WHERE pais_id = ? AND equipo_actual_id IS NULL");
$stmtSinEquipo->execute([$pais_id]);
$haySinEquipo = ((int) $stmtSinEquipo->fetchColumn()) > 0;

$where = "WHERE j.pais_id = ?";
$params = [$pais_id];
if ($filtroEquipo === 0) {
    $where .= " AND j.equipo_actual_id IS NULL";
} elseif ($filtroEquipo > 0) {
    $where .= " AND j.equipo_actual_id = ?";
    $params[] = $filtroEquipo;
}
if ($filtroPosicion !== '') {
    $where .= " AND j.posicion = ?";
    $params[] = $filtroPosicion;
}

$orderBy = "FIELD(j.posicion, 'Portero', 'Defensa', 'Centrocampista', 'Delantero', 'Entrenador'), (j.dorsal IS NULL OR j.dorsal = 0), j.dorsal ASC, j.nombre ASC";
if ($ordenJugadores === 'dorsal') {
    $orderBy = "(j.dorsal IS NULL OR j.dorsal = 0), j.dorsal ASC, FIELD(j.posicion, 'Portero', 'Defensa', 'Centrocampista', 'Delantero', 'Entrenador'), j.nombre ASC";
} elseif ($ordenJugadores === 'equipo') {
    $orderBy = "(e.nombre IS NULL), e.nombre ASC, FIELD(j.posicion, 'Portero', 'Defensa', 'Centrocampista', 'Delantero', 'Entrenador'), (j.dorsal IS NULL OR j.dorsal = 0), j.dorsal ASC, j.nombre ASC";
}

$stmtJugadores = $pdo->prepare("
    SELECT 
        j.id,
        j.nombre,
        j.posicion,
        j.dorsal,
        e.id as equipo_id,
        e.nombre as equipo_nombre,
        e.escudo_url as equipo_escudo_url
    FROM jugadores j
    LEFT JOIN equipo e ON e.id = j.equipo_actual_id
    $where
    ORDER BY $orderBy
");
$stmtJugadores->execute($params);
$jugadoresPais = $stmtJugadores->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container" style="margin-top: 40px;">

    <!-- Encabezado del País -->
    <div class="admin-card" style="padding: 22px; margin-bottom: 18px;">
        <div style="display:flex; align-items:center; justify-content: space-between; gap: 18px; flex-wrap: wrap;">
            <div style="display:flex; align-items:center; gap: 16px; min-width: 0;">
                <?php if (!empty($pais['bandera_url'])): ?>
                    <img src="<?= htmlspecialchars($pais['bandera_url']) ?>" alt="Bandera"
                        style="width: 62px; height: auto; border-radius: 10px; border: 1px solid rgba(255,255,255,0.12); box-shadow: 0 10px 20px rgba(0,0,0,0.35);">
                <?php else: ?>
                    <div style="width: 62px; height: 42px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.04); display:flex; align-items:center; justify-content:center;">🏳️</div>
                <?php endif; ?>
                <div style="min-width: 0;">
                    <h1 style="margin: 0; font-size: 2.2rem; letter-spacing: -0.3px;"><?= htmlspecialchars($pais['nombre']) ?></h1>
                    <div style="margin-top: 6px; color: var(--text-muted); display:flex; gap: 10px; flex-wrap: wrap;">
                        <span>Competiciones: <strong style="color: var(--text-color);"><?= count($competiciones) ?></strong></span>
                        <span>Equipos: <strong style="color: var(--text-color);"><?= count($equipos) ?></strong></span>
                        <span>Jugadores: <strong style="color: var(--text-color);"><?= count($jugadoresPais) ?></strong></span>
                    </div>
                </div>
            </div>
            <div style="display:flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end;">
                <button type="button" class="btn-admin" onclick="history.back()" style="margin:0; background: rgba(255,255,255,0.05); color: var(--text-color); border: 1px solid rgba(255,255,255,0.10);">⬅ Volver</button>
                <a href="#competiciones" class="btn-admin" style="margin:0; background: rgba(255,255,255,0.05); color: var(--text-color); border: 1px solid rgba(255,255,255,0.10);">Competiciones</a>
                <a href="#equipos" class="btn-admin" style="margin:0; background: rgba(255,255,255,0.05); color: var(--text-color); border: 1px solid rgba(255,255,255,0.10);">Equipos</a>
                <a href="#jugadores" class="btn-admin" style="margin:0; background: rgba(255,255,255,0.05); color: var(--text-color); border: 1px solid rgba(255,255,255,0.10);">Jugadores</a>
            </div>
        </div>
    </div>

    <section id="competiciones" style="margin-bottom: 28px;">
        <h2 class="section-title">Competiciones</h2>
        <?php if (empty($competiciones)): ?>
            <div class="admin-card" style="padding: 22px; color: var(--text-muted);">No hay competiciones registradas en este país.</div>
        <?php else: ?>
            <div class="competitions-grid">
                <?php foreach ($competiciones as $comp): ?>
                    <a href="competicion.php?id=<?= (int) $comp['id'] ?>" class="comp-card">
                        <div class="comp-card-header">
                            <?php if (!empty($comp['logo_url'])): ?>
                                <img src="<?= htmlspecialchars($comp['logo_url']) ?>" class="comp-logo" alt="">
                            <?php else: ?>
                                <div class="comp-logo-placeholder">🏆</div>
                            <?php endif; ?>
                        </div>
                        <div class="comp-card-body">
                            <h3><?= htmlspecialchars($comp['nombre']) ?></h3>
                            <div class="comp-meta">
                                <span class="badge badge-info"><?= htmlspecialchars($comp['tipo_nombre']) ?></span>
                                <span><?= htmlspecialchars($comp['temporada_actual']) ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section id="equipos" style="margin-bottom: 28px;">
        <h2 class="section-title">Equipos</h2>
        <?php if (empty($equipos)): ?>
            <div class="admin-card" style="padding: 22px; color: var(--text-muted);">No hay equipos registrados en este país.</div>
        <?php else: ?>
            <div class="competitions-grid" style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));">
                <?php foreach ($equipos as $eq): ?>
                    <a href="equipo.php?id=<?= (int) $eq['id'] ?>" class="comp-card">
                        <div class="comp-card-header" style="height: 96px;">
                            <?php if (!empty($eq['escudo_url'])): ?>
                                <img src="<?= htmlspecialchars($eq['escudo_url']) ?>" class="comp-logo" alt="">
                            <?php else: ?>
                                <div class="comp-logo-placeholder">🛡️</div>
                            <?php endif; ?>
                        </div>
                        <div class="comp-card-body">
                            <h3><?= htmlspecialchars($eq['nombre']) ?></h3>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section id="jugadores" style="margin-bottom: 40px;">
        <h2 class="section-title">Jugadores</h2>
        <?php if (empty($jugadoresPais)): ?>
            <div class="admin-card" style="padding: 22px; color: var(--text-muted);">No hay jugadores con nacionalidad de este país.</div>
        <?php else: ?>
            <div class="admin-card" style="padding: 18px 20px;">
                <form method="GET" style="display:flex; gap: 12px; flex-wrap: wrap; align-items: end;">
                    <input type="hidden" name="id" value="<?= (int) $pais_id ?>">
                    <div style="min-width: 240px; flex: 1;">
                        <label style="display:block; color: var(--text-muted); margin-bottom: 6px;">Equipo</label>
                        <select name="equipo_id" class="form-control-admin">
                            <option value="-1" <?= $filtroEquipo === -1 ? 'selected' : '' ?>>Todos</option>
                            <?php if ($haySinEquipo): ?>
                                <option value="0" <?= $filtroEquipo === 0 ? 'selected' : '' ?>>Sin equipo</option>
                            <?php endif; ?>
                            <?php foreach ($equiposJugadores as $eqOpt): ?>
                                <option value="<?= (int) $eqOpt['id'] ?>" <?= $filtroEquipo === (int) $eqOpt['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $eqOpt['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="min-width: 220px;">
                        <label style="display:block; color: var(--text-muted); margin-bottom: 6px;">Posición</label>
                        <select name="posicion" class="form-control-admin">
                            <option value="" <?= $filtroPosicion === '' ? 'selected' : '' ?>>Todas</option>
                            <?php foreach ($posicionesDisponibles as $p): ?>
                                <option value="<?= htmlspecialchars($p) ?>" <?= $filtroPosicion === $p ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="min-width: 220px;">
                        <label style="display:block; color: var(--text-muted); margin-bottom: 6px;">Ordenar por</label>
                        <select name="orden_jugadores" class="form-control-admin">
                            <option value="equipo" <?= $ordenJugadores === 'equipo' ? 'selected' : '' ?>>Equipo</option>
                            <option value="dorsal" <?= $ordenJugadores === 'dorsal' ? 'selected' : '' ?>>Dorsal</option>
                            <option value="posicion" <?= $ordenJugadores === 'posicion' ? 'selected' : '' ?>>Posición</option>
                        </select>
                    </div>
                    <div style="display:flex; gap: 10px;">
                        <button type="submit" class="btn-admin" style="margin:0; padding: 10px 18px;">Aplicar</button>
                        <a href="pais.php?id=<?= (int) $pais_id ?>#jugadores" class="btn-admin" style="margin:0; background: rgba(255,255,255,0.05); color: var(--text-color); border: 1px solid rgba(255,255,255,0.10); padding: 10px 18px;">Limpiar</a>
                    </div>
                </form>
            </div>
            <div class="admin-card" style="padding: 0; overflow: hidden;">
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 80px; text-align: center;">Dorsal</th>
                                <th>Jugador</th>
                                <th style="width: 180px;">Posición</th>
                                <th>Equipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jugadoresPais as $j): ?>
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
                                        <?php if (!empty($j['equipo_id'])): ?>
                                            <div style="display:flex; align-items:center; gap: 10px;">
                                                <?php if (!empty($j['equipo_escudo_url'])): ?>
                                                    <img src="<?= htmlspecialchars((string) $j['equipo_escudo_url']) ?>" alt="" style="width: 22px; height: 22px; object-fit: contain;">
                                                <?php else: ?>
                                                    <div style="width: 22px; height: 22px; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; font-size: 0.75rem;">🛡️</div>
                                                <?php endif; ?>
                                                <a href="equipo.php?id=<?= (int) $j['equipo_id'] ?>" style="text-decoration: none; color: #fff; font-weight: 700;">
                                                    <?= htmlspecialchars((string) ($j['equipo_nombre'] ?? '')) ?>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">Sin equipo</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
