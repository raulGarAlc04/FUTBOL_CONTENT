<?php
require_once __DIR__ . '/../db.php';
include '../includes/header.php';

$pais = null;
$error = null;

// Obtener continentes para el select
$continentes = $pdo->query("SELECT * FROM continente ORDER BY nombre ASC")->fetchAll();

// Si viene un ID, estamos editando
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM pais WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $pais = $stmt->fetch();
}

// Procesar Formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $codigo_iso = trim($_POST['codigo_iso']);
    $bandera_url = trim($_POST['bandera_url']);
    $continente_id = !empty($_POST['continente_id']) ? $_POST['continente_id'] : null;
    $id = $_POST['id'] ?? null;

    if (empty($nombre)) {
        $error = "El nombre es obligatorio.";
    } else {
        try {
            if ($id) {
                // Actualizar
                $stmt = $pdo->prepare("UPDATE pais SET nombre = ?, codigo_iso = ?, bandera_url = ?, continente_id = ? WHERE id = ?");
                $stmt->execute([$nombre, $codigo_iso, $bandera_url, $continente_id, $id]);
            } else {
                // Insertar
                $stmt = $pdo->prepare("INSERT INTO pais (nombre, codigo_iso, bandera_url, continente_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nombre, $codigo_iso, $bandera_url, $continente_id]);
            }
            // Redirigir a la lista
            echo "<script>window.location.href='paises.php';</script>";
            exit;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="container" style="margin-top: 40px; max-width: 600px;">
    <h1>
        <?= $pais ? 'Editar País' : 'Añadir Nuevo País' ?>
    </h1>

    <?php if ($error): ?>
        <div style="background: #ef4444; color: white; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST"
        style="background: var(--secondary-color); padding: 30px; border-radius: 8px; margin-top: 20px;">
        <?php if ($pais): ?>
            <input type="hidden" name="id" value="<?= $pais['id'] ?>">
        <?php endif; ?>

        <div style="margin-bottom: 20px;">
            <label for="nombre" style="display: block; margin-bottom: 8px; font-weight: 500;">Nombre del País</label>
            <input type="text" id="nombre" name="nombre" value="<?= $pais ? htmlspecialchars($pais['nombre']) : '' ?>"
                required
                style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: white;">
        </div>

        <div style="margin-bottom: 20px;">
            <label for="codigo_iso" style="display: block; margin-bottom: 8px; font-weight: 500;">Código ISO (3
                letras)</label>
            <input type="text" id="codigo_iso" name="codigo_iso"
                value="<?= $pais ? htmlspecialchars($pais['codigo_iso']) : '' ?>" maxlength="3" placeholder="Ej: ESP"
                style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: white;">
        </div>

        <div style="margin-bottom: 20px;">
            <label for="bandera_url" style="display: block; margin-bottom: 8px; font-weight: 500;">URL de la
                Bandera</label>
            <input type="text" id="bandera_url" name="bandera_url"
                value="<?= $pais ? htmlspecialchars($pais['bandera_url'] ?? '') : '' ?>"
                placeholder="https://ejemplo.com/bandera.png"
                style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: white;">
        </div>

        <div style="margin-bottom: 20px;">
            <label for="continente_id" style="display: block; margin-bottom: 8px; font-weight: 500;">Continente</label>
            <select id="continente_id" name="continente_id"
                style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: white;">
                <option value="">-- Seleccionar Continente --</option>
                <?php foreach ($continentes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($pais && $pais['continente_id'] == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn-admin" style="margin-left: 0; cursor: pointer; border: none;">
                <?= $pais ? 'Actualizar' : 'Guardar' ?>
            </button>
            <a href="paises.php"
                style="padding: 0.5rem 1rem; color: var(--text-muted); text-decoration: none; display: flex; align-items: center;">Cancelar</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>