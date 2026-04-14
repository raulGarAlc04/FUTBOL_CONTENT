<?php
require_once 'db.php';
include 'includes/header.php';

$stmtTeams = $pdo->query("SELECT COUNT(*) FROM equipo");
$totalTeams = (int) $stmtTeams->fetchColumn();

$stmtPlayers = $pdo->query("SELECT COUNT(*) FROM jugadores");
$totalPlayers = (int) $stmtPlayers->fetchColumn();

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
        <p>Explora ligas, equipos y jugadores.</p>
        <div class="hero-stats">
            <div class="stat-item">
                <span class="stat-number"><?= $totalTeams ?></span>
                <span class="stat-label">Equipos</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?= $totalPlayers ?></span>
                <span class="stat-label">Jugadores</span>
            </div>
        </div>

        <form action="buscar.php" method="GET" style="max-width: 600px; margin: 40px auto 0 auto; display: flex; gap: 10px;">
            <input type="text" name="q" placeholder="Buscar equipo, jugador o competición..." style="flex: 1; padding: 15px 25px; border-radius: 30px; border: none; outline: none; font-size: 1.1rem; background: rgba(255,255,255,0.95); color: #111; box-shadow: 0 8px 15px rgba(0,0,0,0.2);">
            <button type="submit" style="padding: 15px 30px; border-radius: 30px; border: none; background: var(--accent-color); color: white; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: background 0.3s, transform 0.2s; box-shadow: 0 8px 15px rgba(0,0,0,0.2);">Buscar</button>
        </form>
    </div>
</div>

<div class="container" style="margin-top: 40px; margin-bottom: 60px;">
    <section style="margin-bottom: 50px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
            <h2 class="section-title">Ligas</h2>
        </div>

        <?php if (empty($competitions)): ?>
            <div class="admin-card" style="padding: 25px; color: var(--text-muted);">No hay ligas registradas.</div>
        <?php else: ?>
            <div class="competitions-grid">
                <?php foreach ($competitions as $comp): ?>
                    <a href="competicion.php?id=<?= (int) $comp['id'] ?>" class="comp-card">
                        <div class="comp-card-header">
                            <?php if (!empty($comp['logo_url'])): ?>
                                <img src="<?= htmlspecialchars($comp['logo_url']) ?>" class="comp-logo">
                            <?php else: ?>
                                <div class="comp-logo-placeholder">🏆</div>
                            <?php endif; ?>
                        </div>
                        <div class="comp-card-body">
                            <h3><?= htmlspecialchars($comp['nombre']) ?></h3>
                            <div class="comp-meta">
                                <?php if (!empty($comp['bandera_url'])): ?>
                                    <img src="<?= htmlspecialchars($comp['bandera_url']) ?>" style="width: 16px;">
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
