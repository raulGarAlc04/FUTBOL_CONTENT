<?php
require_once __DIR__ . '/../db.php';
include '../includes/header.php';

$equipo = null;
$error = null;

// Obtener países
$paises = $pdo->query("SELECT * FROM pais ORDER BY nombre ASC")->fetchAll();

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM equipo WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $equipo = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    // $nombre_corto removed
    $estadio = trim($_POST['estadio']);
    $fundacion = !empty($_POST['fundacion']) ? $_POST['fundacion'] : null;
    $pais_id = !empty($_POST['pais_id']) ? $_POST['pais_id'] : null;
    $escudo_url = trim($_POST['escudo_url']);
    $id = $_POST['id'] ?? null;

    if (empty($nombre)) {
        $error = "El nombre del equipo es obligatorio.";
    } else {
        try {
            if ($id) {
                // Actualizar
                $stmt = $pdo->prepare("UPDATE equipo SET nombre = ?, estadio = ?, fundacion = ?, pais_id = ?, escudo_url = ? WHERE id = ?");
                $stmt->execute([$nombre, $estadio, $fundacion, $pais_id, $escudo_url, $id]);
            } else {
                // Insertar
                $stmt = $pdo->prepare("INSERT INTO equipo (nombre, estadio, fundacion, pais_id, escudo_url) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $estadio, $fundacion, $pais_id, $escudo_url]);
            }
            echo "<script>window.location.href='equipos.php';</script>";
            exit;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="container" style="margin-top: 40px; margin-bottom: 60px; max-width: 800px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 2.5rem; margin-bottom: 5px;">
                <?= $equipo ? 'Editar Equipo' : 'Nuevo Equipo' ?>
            </h1>
            <p style="color: var(--text-muted);">Completa la información básica del club o selección.</p>
        </div>
        <a href="equipos.php" class="btn-admin" style="background: var(--secondary-color);">Volver a la Lista</a>
    </div>

    <?php if ($error): ?>
        <div style="background: #ef4444; color: white; padding: 15px; border-radius: 8px; margin-bottom: 30px; border-left: 5px solid rgba(0,0,0,0.2);">
            ⚠️ <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="admin-card" style="padding: 40px;">
        <form method="POST">
            <?php if ($equipo): ?>
                <input type="hidden" name="id" value="<?= $equipo['id'] ?>">
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- Información Principal -->
                <div style="grid-column: span 2;">
                    <label style="display: block; margin-bottom: 8px; font-size: 0.9rem; font-weight: 600; color: #fff;">Nombre Completo</label>
                    <input type="text" name="nombre" value="<?= $equipo ? htmlspecialchars($equipo['nombre']) : '' ?>" required placeholder="Ej: Real Madrid C.F." class="form-control-admin" style="font-size: 1.1rem; padding: 12px;">
                </div>

                <div>
                    <label style="display: block; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-muted);">País de Origen</label>
                    <select name="pais_id" class="form-control-admin">
                        <option value="">-- Seleccionar País --</option>
                        <?php foreach ($paises as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($equipo && $equipo['pais_id'] == $p['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-muted);">Año de Fundación</label>
                    <input type="number" name="fundacion" value="<?= $equipo ? htmlspecialchars($equipo['fundacion']) : '' ?>" placeholder="Ej: 1902" class="form-control-admin">
                </div>

                <div style="grid-column: span 2;">
                    <label style="display: block; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-muted);">Estadio / Campo</label>
                    <input type="text" name="estadio" value="<?= $equipo ? htmlspecialchars($equipo['estadio']) : '' ?>" placeholder="Ej: Santiago Bernabéu" class="form-control-admin">
                </div>

                <!-- Imagen / Escudo -->
                <div style="grid-column: span 2; display: flex; gap: 20px; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-muted);">URL del Escudo (PNG preferiblemente)</label>
                        <input type="text" id="escudo_url_input" name="escudo_url" value="<?= $equipo ? htmlspecialchars($equipo['escudo_url']) : '' ?>" placeholder="https://..." class="form-control-admin">
                    </div>
                    <?php if ($equipo && $equipo['escudo_url']): ?>
                        <div style="width: 80px; height: 80px; background: rgba(255,255,255,0.05); border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1);">
                            <img src="<?= htmlspecialchars($equipo['escudo_url']) ?>" style="max-width: 60px; max-height: 60px; object-fit: contain;">
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-top: 40px; padding-top: 30px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; gap: 15px;">
                <button type="submit" class="btn-admin" style="margin: 0; padding: 15px 40px; font-size: 1rem; font-weight: bold; background: var(--accent-color);">
                    <?= $equipo ? 'Guardar Cambios' : 'Crear Equipo' ?>
                </button>
                <a href="equipos.php" class="btn-admin" style="margin: 0; padding: 15px 30px; background: rgba(255,255,255,0.05); color: var(--text-muted);">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>