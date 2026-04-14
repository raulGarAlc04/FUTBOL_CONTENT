<?php
require_once 'db.php';
$page_title = 'Búsqueda';
include 'includes/header.php';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$equipos = [];
$jugadores = [];
$competiciones = [];

if ($q !== '') {
    $searchTerm = '%' . $q . '%';

    $stmt = $pdo->prepare("SELECT * FROM equipo WHERE nombre LIKE ? ORDER BY nombre ASC LIMIT 12");
    $stmt->execute([$searchTerm]);
    $equipos = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT j.*, e.nombre AS equipo_nombre
        FROM jugadores j
        LEFT JOIN equipo e ON j.equipo_actual_id = e.id
        WHERE j.nombre LIKE ?
        ORDER BY j.nombre ASC
        LIMIT 12
    ");
    $stmt->execute([$searchTerm]);
    $jugadores = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM competicion WHERE nombre LIKE ? ORDER BY nombre ASC LIMIT 12");
    $stmt->execute([$searchTerm]);
    $competiciones = $stmt->fetchAll();
}

$hasResults = $q !== '' && (!empty($equipos) || !empty($jugadores) || !empty($competiciones));
?>

<div class="container search-shell">
    <h1 class="search-title">Búsqueda<?php if ($q !== ''): ?>: <span class="search-query"><?= htmlspecialchars($q) ?></span><?php endif; ?></h1>

    <?php if ($q === ''): ?>
        <p class="search-lead">Escribe en el buscador del menú o en la página de inicio para ver equipos, jugadores y competiciones.</p>
    <?php elseif (!$hasResults): ?>
        <p class="search-lead">No hay resultados para «<?= htmlspecialchars($q) ?>». Prueba con otro nombre o revisa la ortografía.</p>
    <?php else: ?>
        <div class="search-columns">
            <?php if (!empty($equipos)): ?>
                <section>
                    <h2 class="search-col-title">Equipos</h2>
                    <div class="search-list">
                        <?php foreach ($equipos as $eq): ?>
                            <a class="search-row" href="equipo.php?id=<?= (int) $eq['id'] ?>">
                                <span class="search-row-thumb">
                                    <?php if (!empty($eq['escudo_url'])): ?>
                                        <img src="<?= htmlspecialchars($eq['escudo_url']) ?>" alt="">
                                    <?php else: ?>
                                        <span aria-hidden="true">🛡️</span>
                                    <?php endif; ?>
                                </span>
                                <div class="search-row-body">
                                    <strong><?= htmlspecialchars($eq['nombre']) ?></strong>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($jugadores)): ?>
                <section>
                    <h2 class="search-col-title">Jugadores</h2>
                    <div class="search-list">
                        <?php foreach ($jugadores as $jug): ?>
                            <?php $tid = isset($jug['equipo_actual_id']) ? (int) $jug['equipo_actual_id'] : 0; ?>
                            <?php if ($tid > 0): ?>
                                <a class="search-row" href="equipo.php?id=<?= $tid ?>">
                            <?php else: ?>
                                <div class="search-row search-row-static">
                            <?php endif; ?>
                                <span class="search-row-thumb round">
                                    <?php if (!empty($jug['foto_url'])): ?>
                                        <img src="<?= htmlspecialchars($jug['foto_url']) ?>" alt="">
                                    <?php else: ?>
                                        <span aria-hidden="true">👤</span>
                                    <?php endif; ?>
                                </span>
                                <div class="search-row-body">
                                    <strong><?= htmlspecialchars($jug['nombre']) ?></strong>
                                    <div class="search-row-sub">
                                        <?= htmlspecialchars($jug['equipo_nombre'] ?? 'Sin equipo') ?>
                                        <?php if (!empty($jug['posicion'])): ?>
                                            · <?= htmlspecialchars($jug['posicion']) ?>
                                        <?php endif; ?>
                                        <?php if ($tid > 0): ?>
                                            <span class="search-row-hint"> · Plantilla</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php if ($tid > 0): ?>
                                </a>
                            <?php else: ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($competiciones)): ?>
                <section>
                    <h2 class="search-col-title">Competiciones</h2>
                    <div class="search-list">
                        <?php foreach ($competiciones as $comp): ?>
                            <a class="search-row" href="competicion.php?id=<?= (int) $comp['id'] ?>">
                                <span class="search-row-thumb">
                                    <?php if (!empty($comp['logo_url'])): ?>
                                        <img src="<?= htmlspecialchars($comp['logo_url']) ?>" alt="">
                                    <?php else: ?>
                                        <span aria-hidden="true">🏆</span>
                                    <?php endif; ?>
                                </span>
                                <div class="search-row-body">
                                    <strong><?= htmlspecialchars($comp['nombre']) ?></strong>
                                    <div class="search-row-sub">Temporada <?= htmlspecialchars($comp['temporada_actual']) ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
