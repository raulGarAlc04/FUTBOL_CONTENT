<?php
require_once 'db.php';
include 'includes/header.php';

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='container' style='margin-top: 40px;'><p>Continente no especificado.</p></div>";
    include 'includes/footer.php';
    exit;
}

$continente_id = $_GET['id'];

// Obtener info del continente
$stmt = $pdo->prepare("SELECT * FROM continente WHERE id = ?");
$stmt->execute([$continente_id]);
$continente = $stmt->fetch();

if (!$continente) {
    echo "<div class='container' style='margin-top: 40px;'><p>Continente no encontrado.</p></div>";
    include 'includes/footer.php';
    exit;
}

// Obtener países del continente
$stmt = $pdo->prepare("SELECT * FROM pais WHERE continente_id = ? ORDER BY nombre ASC");
$stmt->execute([$continente_id]);
$paises = $stmt->fetchAll();
?>

<div class="container" style="margin-top: 40px;">
    <h1 style="border-bottom: 2px solid var(--accent-color); padding-bottom: 10px; margin-bottom: 30px;">
        <?= htmlspecialchars($continente['nombre']) ?>
    </h1>

    <?php if (empty($paises)): ?>
        <p style="color: var(--text-muted);">No hay países registrados en este continente todavía.</p>
    <?php else: ?>
        <div class="grid-paises"
            style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px;">
            <?php foreach ($paises as $pais): ?>
                <a href="pais.php?id=<?= $pais['id'] ?>" class="card-pais"
                    style="display: block; background: var(--secondary-color); padding: 20px; border-radius: 8px; text-decoration: none; color: var(--text-color); border: 1px solid rgba(255,255,255,0.05); transition: transform 0.2s, background-color 0.2s; text-align: center;">
                    <?php if (!empty($pais['bandera_url'])): ?>
                        <img src="<?= htmlspecialchars($pais['bandera_url']) ?>" alt="<?= htmlspecialchars($pais['nombre']) ?>"
                            style="width: 60px; height: auto; border-radius: 4px; marginBottom: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                    <?php endif; ?>
                    <div style="font-weight: 600; font-size: 1.1rem; margin-bottom: 5px;">
                        <?= htmlspecialchars($pais['nombre']) ?>
                    </div>
                    <?php if (!empty($pais['codigo_iso'])): ?>
                        <span
                            style="background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; color: var(--text-muted);">
                            <?= htmlspecialchars($pais['codigo_iso']) ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .card-pais:hover {
        transform: translateY(-3px);
        background-color: #2d3b4e;
        border-color: var(--accent-color);
    }
</style>

<?php include 'includes/footer.php'; ?>