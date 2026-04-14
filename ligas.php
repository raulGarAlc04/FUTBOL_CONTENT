<?php
require_once 'db.php';
$page_title = 'Ligas y competiciones';
include 'includes/header.php';

$stmt = $pdo->query("
    SELECT c.*, tc.nombre AS tipo_nombre, p.bandera_url, p.nombre AS pais_nombre
    FROM competicion c
    JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id
    LEFT JOIN pais p ON c.pais_id = p.id
    ORDER BY tc.nombre ASC, c.nombre ASC
");
$competiciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

$porTipo = [];
foreach ($competiciones as $c) {
    $t = $c['tipo_nombre'] ?: 'Otros';
    if (!isset($porTipo[$t])) {
        $porTipo[$t] = [];
    }
    $porTipo[$t][] = $c;
}
?>

<div class="container page-shell">
    <header class="page-hero page-hero--tight">
        <div class="page-hero-main">
            <div class="page-hero-logo" aria-hidden="true"><span class="page-hero-icon">🏆</span></div>
            <div>
                <h1 class="page-hero-title">Ligas y competiciones</h1>
                <p class="page-hero-meta">Listado completo ordenado por tipo.</p>
            </div>
        </div>
    </header>

    <?php if (empty($competiciones)): ?>
        <div class="message-panel">No hay competiciones en la base de datos.</div>
    <?php else: ?>
        <?php foreach ($porTipo as $tipoNombre => $lista): ?>
            <section style="margin-top: 36px;">
                <h2 class="section-title"><?= htmlspecialchars($tipoNombre) ?></h2>
                <div class="competitions-grid">
                    <?php foreach ($lista as $comp): ?>
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
                                    <?php if (!empty($comp['bandera_url'])): ?>
                                        <img src="<?= htmlspecialchars($comp['bandera_url']) ?>" alt="" width="16" height="12" style="width: 16px; height: auto; border-radius: 2px;">
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($comp['pais_nombre'] ?? 'Internacional') ?></span>
                                </div>
                                <div class="comp-season"><?= htmlspecialchars($comp['temporada_actual']) ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
