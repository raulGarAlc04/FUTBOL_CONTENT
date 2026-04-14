<?php
require_once 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $page_title = 'Continente';
    include 'includes/header.php';
    echo '<div class="container page-shell"><div class="message-panel">Continente no especificado.</div></div>';
    include 'includes/footer.php';
    exit;
}

$continente_id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM continente WHERE id = ?");
$stmt->execute([$continente_id]);
$continente = $stmt->fetch();

if (!$continente) {
    $page_title = 'Continente';
    include 'includes/header.php';
    echo '<div class="container page-shell"><div class="message-panel">Continente no encontrado.</div></div>';
    include 'includes/footer.php';
    exit;
}

$page_title = $continente['nombre'];
include 'includes/header.php';

$stmt = $pdo->prepare("SELECT * FROM pais WHERE continente_id = ? ORDER BY nombre ASC");
$stmt->execute([$continente_id]);
$paises = $stmt->fetchAll();
?>

<div class="container page-shell">
    <header class="page-hero page-hero--tight">
        <div class="page-hero-main">
            <div class="page-hero-logo" aria-hidden="true"><span class="page-hero-icon">🌍</span></div>
            <div>
                <h1 class="page-hero-title"><?= htmlspecialchars($continente['nombre']) ?></h1>
                <p class="page-hero-meta">Países y selecciones asociadas a este continente.</p>
            </div>
        </div>
    </header>

    <?php if (empty($paises)): ?>
        <div class="message-panel">No hay países registrados en este continente todavía.</div>
    <?php else: ?>
        <div class="country-grid">
            <?php foreach ($paises as $pais): ?>
                <a href="pais.php?id=<?= (int) $pais['id'] ?>" class="country-card">
                    <?php if (!empty($pais['bandera_url'])): ?>
                        <img class="country-card-flag" src="<?= htmlspecialchars($pais['bandera_url']) ?>" alt="" width="64" height="40">
                    <?php endif; ?>
                    <span class="country-card-name"><?= htmlspecialchars($pais['nombre']) ?></span>
                    <?php if (!empty($pais['codigo_iso'])): ?>
                        <span class="country-card-iso"><?= htmlspecialchars($pais['codigo_iso']) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
