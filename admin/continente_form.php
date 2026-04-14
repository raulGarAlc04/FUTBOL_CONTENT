<?php
require_once __DIR__ . '/../db.php';
include '../includes/header.php';

$continente = null;
$error = null;

// Si viene un ID, estamos editando
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM continente WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $continente = $stmt->fetch();
}

// Procesar Formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $id = $_POST['id'] ?? null;

    if (empty($nombre)) {
        $error = "El nombre es obligatorio.";
    } else {
        try {
            if ($id) {
                // Actualizar
                $stmt = $pdo->prepare("UPDATE continente SET nombre = ? WHERE id = ?");
                $stmt->execute([$nombre, $id]);
            } else {
                // Insertar
                $stmt = $pdo->prepare("INSERT INTO continente (nombre) VALUES (?)");
                $stmt->execute([$nombre]);
            }
            // Redirigir a la lista
            echo "<script>window.location.href='continentes.php';</script>";
            exit;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="container" style="margin-top: 40px; max-width: 600px;">
    <h1>
        <?= $continente ? 'Editar Continente' : 'Añadir Nuevo Continente' ?>
    </h1>

    <?php if ($error): ?>
        <div style="background: #ef4444; color: white; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST"
        style="background: var(--secondary-color); padding: 30px; border-radius: 8px; margin-top: 20px;">
        <?php if ($continente): ?>
            <input type="hidden" name="id" value="<?= $continente['id'] ?>">
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <label for="nombre" style="display: block; margin-bottom: 8px; font-weight: 500;">Nombre del
                Continente</label>
            <input type="text" id="nombre" name="nombre"
                value="<?= $continente ? htmlspecialchars($continente['nombre']) : '' ?>" required
                style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: white;">
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn-admin" style="margin-left: 0; cursor: pointer; border: none;">
                <?= $continente ? 'Actualizar' : 'Guardar' ?>
            </button>
            <a href="continentes.php"
                style="padding: 0.5rem 1rem; color: var(--text-muted); text-decoration: none; display: flex; align-items: center;">Cancelar</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>