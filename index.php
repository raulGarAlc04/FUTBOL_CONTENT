<?php
require_once 'db.php';
include 'includes/header.php';

$stmtTeams = $pdo->query("SELECT COUNT(*) FROM equipo");
$totalTeams = (int) $stmtTeams->fetchColumn();

$stmtPlayers = $pdo->query("SELECT COUNT(*) FROM jugadores");
$totalPlayers = (int) $stmtPlayers->fetchColumn();

$stmtComps = $pdo->query("SELECT COUNT(*) FROM competicion");
$totalComps = (int) $stmtComps->fetchColumn();

$stmtComps = $pdo->query("
    SELECT c.*, tc.nombre as tipo_nombre, p.bandera_url, p.nombre as pais_nombre 
    FROM competicion c 
    JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id 
    LEFT JOIN pais p ON c.pais_id = p.id 
    WHERE tc.nombre = 'Liga'
    ORDER BY c.id ASC 
    LIMIT 12
");
$competitions = $stmtComps->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="homepage-hero">
    <div class="hero-content">
        <h1>Futbol Data</h1>
        <p>Explora ligas, equipos y jugadores con datos ordenados y claros.</p>
        <div class="hero-stats">
            <div class="stat-item">
                <span class="stat-number"><?= $totalTeams ?></span>
                <span class="stat-label">Equipos</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?= $totalPlayers ?></span>
                <span class="stat-label">Jugadores</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?= $totalComps ?></span>
                <span class="stat-label">Competiciones</span>
            </div>
        </div>

        <form class="hero-search" action="<?= htmlspecialchars(fc_url('/buscar.php')) ?>" method="GET" role="search">
            <label class="visually-hidden" for="hero-q">Buscar</label>
            <input id="hero-q" type="search" name="q" placeholder="Equipo, jugador o competición…" autocomplete="off" maxlength="120">
            <button type="submit">Buscar</button>
        </form>
    </div>
</div>

<div class="container page-shell">
    <section>
        <div class="section-head">
            <h2 class="section-title">Ligas destacadas</h2>
            <a class="link-all" href="<?= htmlspecialchars(fc_url('/ligas.php')) ?>">Ver todas</a>
        </div>

        <?php if (empty($competitions)): ?>
            <div class="message-panel">No hay ligas registradas.</div>
        <?php else: ?>
            <div class="competitions-grid">
                <?php foreach ($competitions as $comp): ?>
                    <a href="competicion.php?id=<?= (int) $comp['id'] ?>" class="comp-card">
                        <div class="comp-card-header">
                            <?php if (!empty($comp['logo_url'])): ?>
                                <img src="<?= htmlspecialchars($comp['logo_url']) ?>" class="comp-logo" alt="">
                            <?php else: ?>
                                <div class="comp-logo-placeholder" aria-hidden="true">🏆</div>
                            <?php endif; ?>
                        </div>
                        <div class="comp-card-body">
                            <h3><?= htmlspecialchars($comp['nombre']) ?></h3>
                            <div class="comp-meta">
                                <?php if (!empty($comp['bandera_url'])): ?>
                                    <img src="<?= htmlspecialchars($comp['bandera_url']) ?>" alt="" width="16" height="12" class="comp-flag">
                                <?php endif; ?>
                                <span><?= htmlspecialchars($comp['pais_nombre'] ?? 'Internacional') ?></span>
                            </div>
                            <div class="comp-season"><?= htmlspecialchars($comp['temporada_actual']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
