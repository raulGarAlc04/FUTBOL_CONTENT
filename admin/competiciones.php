<?php
require_once __DIR__ . '/../db.php';
include '../includes/header.php';

// Eliminar competicion
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM competicion WHERE id = ?");
        $stmt->execute([$id]);
        echo "<script>window.location.href='competiciones.php';</script>";
    } catch (PDOException $e) {
        $error = "Error al eliminar: " . $e->getMessage();
    }
}

// Obtener competiciones con JOINs para mostrar nombres
$sql = "SELECT c.*, p.nombre as pais_nombre, p.bandera_url, cont.nombre as continente_nombre, tc.nombre as tipo_nombre
        FROM competicion c 
        LEFT JOIN pais p ON c.pais_id = p.id 
        LEFT JOIN continente cont ON c.continente_id = cont.id
        JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id
        WHERE tc.nombre = 'Liga'
        ORDER BY c.id ASC";
$stmt = $pdo->query($sql);
$competiciones = $stmt->fetchAll();
?>

<div class="container" style="margin-top: 40px; margin-bottom: 60px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 2.5rem; margin-bottom: 5px;">Gestión de Competiciones</h1>
            <p style="color: var(--text-muted);">Administra ligas del sistema y su clasificación.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="competicion_form.php" class="btn-admin" style="padding: 12px 25px; font-weight: bold; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                ➕ Añadir Nueva Competición
            </a>
        </div>
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
                    <th style="width: 60px; padding-left: 25px;">ID</th>
                    <th style="width: 60px;">Logo</th>
                    <th>Nombre de la Competición</th>
                    <th>Tipo</th>
                    <th>Ámbito / Región</th>
                    <th style="text-align: center;">Temporada</th>
                    <th style="text-align: right; padding-right: 25px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($competiciones as $comp): ?>
                    <tr>
                        <td style="padding-left: 25px; font-weight: bold; color: var(--text-muted);">
                            #<?= $comp['id'] ?>
                        </td>
                        <td>
                            <?php if (!empty($comp['logo_url'])): ?>
                                <img src="<?= htmlspecialchars($comp['logo_url']) ?>" alt="Logo"
                                    style="width: 32px; height: 32px; object-fit: contain;">
                            <?php else: ?>
                                <div style="width: 32px; height: 32px; background: rgba(255,255,255,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">🏆</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight: 700; font-size: 1.1rem; color: #fff;">
                                <?= htmlspecialchars($comp['nombre']) ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-info">
                                <?= htmlspecialchars($comp['tipo_nombre']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($comp['pais_id']): ?>
                                <!-- Nacional -->
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <?php if (!empty($comp['bandera_url'])): ?>
                                        <img src="<?= htmlspecialchars($comp['bandera_url']) ?>" alt="Bandera"
                                            style="width: 20px; border-radius: 2px;">
                                    <?php endif; ?>
                                    <span style="font-size: 0.95rem;"><?= htmlspecialchars($comp['pais_nombre']) ?></span>
                                </div>
                            <?php elseif ($comp['continente_id']): ?>
                                <!-- Continental -->
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 1.2rem;">🌍</span>
                                    <span class="badge badge-continent">
                                        <?= htmlspecialchars($comp['continente_nombre']) ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <!-- Internacional -->
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 1.2rem;">🌎</span>
                                    <span style="color: #34d399; font-size: 0.95rem; font-weight: 600;">Internacional</span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center; font-weight: 500;">
                            <?= htmlspecialchars($comp['temporada_actual']) ?>
                        </td>
                        <td style="text-align: right; padding-right: 25px;">
                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                                <a href="competicion_partidos.php?id=<?= $comp['id'] ?>" class="btn-admin" style="margin: 0; padding: 6px 12px; font-size: 0.85rem; background: rgba(59,130,246,0.35);">Resultados</a>
                                <a href="competicion_clasificacion.php?id=<?= $comp['id'] ?>" class="btn-admin" style="margin: 0; padding: 6px 15px; font-size: 0.85rem; background: var(--accent-color);">Clasificación</a>
                                <a href="competicion_form.php?id=<?= $comp['id'] ?>" class="btn-admin" style="margin: 0; padding: 6px 12px; font-size: 0.85rem; background: rgba(255,255,255,0.05); color: var(--text-muted);">Editar</a>
                                <a href="competiciones.php?delete=<?= $comp['id'] ?>"
                                    onclick="return confirm('¿Seguro que quieres eliminar esta competición?')"
                                    style="color: #ef4444; text-decoration: none; font-size: 1.2rem; line-height: 1; padding: 5px;" title="Eliminar">&times;</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($competiciones)): ?>
                    <tr>
                        <td colspan="7" style="padding: 50px; text-align: center; color: var(--text-muted);">
                            <div style="font-size: 3rem; margin-bottom: 10px;">🏆</div>
                            No hay competiciones registradas en el sistema.
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
