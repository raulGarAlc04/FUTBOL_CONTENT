<?php
require_once __DIR__ . '/../db.php';
include '../includes/header.php';

$competicion = null;
$error = null;

// Obtener datos para desplegables
$paises = $pdo->query("SELECT * FROM pais ORDER BY nombre ASC")->fetchAll();
$continentes = $pdo->query("SELECT * FROM continente ORDER BY nombre ASC")->fetchAll();
$tiposCompeticion = $pdo->query('SELECT id, nombre FROM tipo_competicion ORDER BY nombre ASC')->fetchAll(PDO::FETCH_ASSOC);
if (!$tiposCompeticion) {
    die('No hay tipos de competición en la base de datos.');
}
$ligaTipo = null;
foreach ($tiposCompeticion as $t) {
    if ($t['nombre'] === 'Liga') {
        $ligaTipo = $t;
        break;
    }
}
if (!$ligaTipo) {
    $ligaTipo = $tiposCompeticion[0];
}
$ligaTipoId = (int) $ligaTipo['id'];

// Si viene un ID, estamos editando
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM competicion WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $competicion = $stmt->fetch();
}

// Determinar el ámbito actual para prellenar el formulario
$ambito_actual = 'nacional'; // Default
if ($competicion) {
    if ($competicion['continente_id']) {
        $ambito_actual = 'continental';
    } elseif (is_null($competicion['pais_id']) && is_null($competicion['continente_id'])) {
        $ambito_actual = 'internacional';
    }
}

// Procesar Formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $tipo_competicion_id = (int) ($_POST['tipo_competicion_id'] ?? $ligaTipoId);
    $tipoOk = false;
    foreach ($tiposCompeticion as $t) {
        if ((int) $t['id'] === $tipo_competicion_id) {
            $tipoOk = true;
            break;
        }
    }
    if (!$tipoOk) {
        $tipo_competicion_id = $ligaTipoId;
    }
    $temporada_actual = trim($_POST['temporada_actual']);
    $logo_url = trim($_POST['logo_url']);
    $id = $_POST['id'] ?? null;
    $ambito = $_POST['ambito'];

    // Lógica de ámbito
    $pais_id = null;
    $continente_id = null;

    if ($ambito === 'nacional') {
        $pais_id = !empty($_POST['pais_id']) ? $_POST['pais_id'] : null;
    } elseif ($ambito === 'continental') {
        $continente_id = !empty($_POST['continente_id']) ? $_POST['continente_id'] : null;
    }
    // Si es internacional, ambos se quedan en null

    if (empty($nombre)) {
        $error = "El nombre es obligatorio.";
    } elseif ($ambito === 'nacional' && empty($pais_id)) {
        $error = "Debes seleccionar un País para competiciones nacionales.";
    } elseif ($ambito === 'continental' && empty($continente_id)) {
        $error = "Debes seleccionar un Continente para competiciones continentales.";
    } else {
        try {
            if ($id) {
                // Actualizar
                $stmt = $pdo->prepare("UPDATE competicion SET nombre = ?, pais_id = ?, continente_id = ?, tipo_competicion_id = ?, temporada_actual = ?, logo_url = ? WHERE id = ?");
                $stmt->execute([$nombre, $pais_id, $continente_id, $tipo_competicion_id, $temporada_actual, $logo_url, $id]);
            } else {
                // Insertar
                $stmt = $pdo->prepare("INSERT INTO competicion (nombre, pais_id, continente_id, tipo_competicion_id, temporada_actual, logo_url) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $pais_id, $continente_id, $tipo_competicion_id, $temporada_actual, $logo_url]);
            }
            // Redirigir
            echo "<script>window.location.href='competiciones.php';</script>";
            exit;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="container" style="margin-top: 40px; max-width: 800px;">
    <h1>
        <?= $competicion ? 'Editar Competición' : 'Añadir Nueva Competición' ?>
    </h1>

    <?php if ($error): ?>
        <div style="background: #ef4444; color: white; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST"
        style="background: var(--secondary-color); padding: 30px; border-radius: 8px; margin-top: 20px;">
        <?php if ($competicion): ?>
            <input type="hidden" name="id" value="<?= $competicion['id'] ?>">
        <?php endif; ?>

        <!-- Ámbito Selector -->
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Ámbito de la Competición</label>
            <div style="display: flex; gap: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="radio" name="ambito" value="nacional" <?= $ambito_actual === 'nacional' ? 'checked' : '' ?> onchange="toggleScope()">
                    Nacional
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="radio" name="ambito" value="continental" <?= $ambito_actual === 'continental' ? 'checked' : '' ?> onchange="toggleScope()">
                    Continental
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="radio" name="ambito" value="internacional" <?= $ambito_actual === 'internacional' ? 'checked' : '' ?> onchange="toggleScope()">
                    Internacional (Mundial)
                </label>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div style="margin-bottom: 20px; grid-column: span 2;">
                <label for="nombre" style="display: block; margin-bottom: 8px; font-weight: 500;">Nombre de la
                    Competición</label>
                <input type="text" id="nombre" name="nombre"
                    value="<?= $competicion ? htmlspecialchars($competicion['nombre']) : '' ?>" required
                    placeholder="Ej: La Liga, Champions League, Mundial de Clubes..."
                    style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: white;">
            </div>

            <div style="margin-bottom: 20px;">
                <label for="tipo_competicion_id"
                    style="display: block; margin-bottom: 8px; font-weight: 500;">Tipo de competición</label>
                <select id="tipo_competicion_id" name="tipo_competicion_id"
                    style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: white;">
                    <?php
                    $tipoSel = $competicion ? (int) $competicion['tipo_competicion_id'] : $ligaTipoId;
                    foreach ($tiposCompeticion as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === $tipoSel ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 8px;">
                    Eliminatoria y «Grupos + eliminatoria» usan la gestión de <strong>resultados</strong> para eliminar perdedores de la edición.
                </p>
            </div>

            <!-- Selector de País (Solo Nacional) -->
            <div id="field-pais" style="margin-bottom: 20px;">
                <label for="pais_id" style="display: block; margin-bottom: 8px; font-weight: 500;">País</label>
                <select id="pais_id" name="pais_id"
                    style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: white;">
                    <option value="">-- Seleccionar País --</option>
                    <?php foreach ($paises as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($competicion && $competicion['pais_id'] == $p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Selector de Continente (Solo Continental) -->
            <div id="field-continente" style="margin-bottom: 20px; display: none;">
                <label for="continente_id"
                    style="display: block; margin-bottom: 8px; font-weight: 500;">Continente</label>
                <select id="continente_id" name="continente_id"
                    style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: white;">
                    <option value="">-- Seleccionar Continente --</option>
                    <?php foreach ($continentes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($competicion && $competicion['continente_id'] == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="temporada_actual" style="display: block; margin-bottom: 8px; font-weight: 500;">Temporada
                    Actual</label>
                <input type="text" id="temporada_actual" name="temporada_actual"
                    value="<?= $competicion ? htmlspecialchars($competicion['temporada_actual']) : '2025/2026' ?>"
                    placeholder="Ej: 2025/2026"
                    style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: white;">
            </div>

            <div style="margin-bottom: 20px;">
                <label for="logo_url" style="display: block; margin-bottom: 8px; font-weight: 500;">URL del Logo</label>
                <input type="text" id="logo_url" name="logo_url"
                    value="<?= $competicion ? htmlspecialchars($competicion['logo_url']) : '' ?>"
                    placeholder="https://..."
                    style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: white;">
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 10px;">
            <button type="submit" class="btn-admin" style="margin-left: 0; cursor: pointer; border: none;">
                <?= $competicion ? 'Actualizar' : 'Guardar' ?>
            </button>
            <a href="competiciones.php"
                style="padding: 0.5rem 1rem; color: var(--text-muted); text-decoration: none; display: flex; align-items: center;">Cancelar</a>
        </div>
    </form>
</div>

<script>
    function toggleScope() {
        const scope = document.querySelector('input[name="ambito"]:checked').value;
        const countryField = document.getElementById('field-pais');
        const continentField = document.getElementById('field-continente');

        if (scope === 'nacional') {
            countryField.style.display = 'block';
            continentField.style.display = 'none';
            document.getElementById('pais_id').required = true;
            document.getElementById('continente_id').required = false;
        } else if (scope === 'continental') {
            countryField.style.display = 'none';
            continentField.style.display = 'block';
            document.getElementById('pais_id').required = false;
            document.getElementById('continente_id').required = true;
        } else {
            countryField.style.display = 'none';
            continentField.style.display = 'none';
            document.getElementById('pais_id').required = false;
            document.getElementById('continente_id').required = false;
        }
    }

    // Inicializar al cargar
    document.addEventListener('DOMContentLoaded', toggleScope);
</script>

<?php include '../includes/footer.php'; ?>
