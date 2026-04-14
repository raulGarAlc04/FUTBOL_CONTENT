<?php
require_once __DIR__ . '/../db.php';
include '../includes/header.php';

// Eliminar continente si se solicita
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM continente WHERE id = ?");
        $stmt->execute([$id]);
        echo "<script>window.location.href='continentes.php';</script>";
    } catch (PDOException $e) {
        $error = "Error al eliminar: " . $e->getMessage();
    }
}

// Obtener todos los continentes
$stmt = $pdo->query("SELECT * FROM continente ORDER BY id ASC");
$continentes = $stmt->fetchAll();
?>

<div class="container" style="margin-top: 40px; margin-bottom: 60px; max-width: 800px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 2.5rem; margin-bottom: 5px;">Gestión de Continentes</h1>
            <p style="color: var(--text-muted);">Administra las regiones geográficas globales.</p>
        </div>
        <a href="continente_form.php" class="btn-admin" style="padding: 12px 25px; font-weight: bold; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
            ➕ Añadir Continente
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div style="background: #ef4444; color: white; padding: 15px; border-radius: 8px; margin-bottom: 30px; border-left: 5px solid rgba(0,0,0,0.2);">
            ⚠️ <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="admin-card" style="padding: 0; overflow: hidden;">
        <div class="table-wrap">
            <table class="admin-table">
            <thead>
                <tr>
                    <th style="width: 80px; padding-left: 25px;">ID</th>
                    <th>Nombre del Continente</th>
                    <th style="text-align: right; padding-right: 25px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($continentes as $c): ?>
                    <tr>
                        <td style="padding-left: 25px; font-weight: bold; color: var(--text-muted);">#<?= $c['id'] ?></td>
                        <td>
                            <div style="font-weight: 700; font-size: 1.1rem; color: #fff;">
                                <?= htmlspecialchars($c['nombre']) ?>
                            </div>
                        </td>
                        <td style="text-align: right; padding-right: 25px;">
                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 12px;">
                                <a href="continente_form.php?id=<?= $c['id'] ?>" class="btn-admin" style="margin: 0; padding: 6px 15px; font-size: 0.85rem; background: rgba(255,255,255,0.05); color: var(--text-muted);">Editar</a>
                                <a href="continentes.php?delete=<?= $c['id'] ?>" 
                                   onclick="return confirm('¿Seguro que quieres eliminar este continente?')"
                                   style="color: #ef4444; text-decoration: none; font-size: 1.2rem; line-height: 1; padding: 5px;" title="Eliminar">&times;</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($continentes)): ?>
                    <tr>
                        <td colspan="3" style="padding: 50px; text-align: center; color: var(--text-muted);">
                            <div style="font-size: 3rem; margin-bottom: 10px;">🌍</div>
                            No hay continentes registrados.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            </table>
        </div>
    </div>

    <div style="margin-top: 30px;">
        <a href="index.php" style="color: var(--text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            &larr; Volver al Panel de Control
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
