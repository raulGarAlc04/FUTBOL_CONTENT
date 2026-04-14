<?php
require_once __DIR__ . '/../db.php';
include '../includes/header.php';

if (!isset($_GET['id']))
    die("ID no especificado.");
$competicion_id = $_GET['id'];

// Info Competición
$stmt = $pdo->prepare("SELECT c.*, tc.nombre as tipo_nombre FROM competicion c JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id WHERE c.id = ?");
$stmt->execute([$competicion_id]);
$comp = $stmt->fetch();

// Añadir Equipo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['equipo_id'])) {
    $equipo_id = $_POST['equipo_id'];
    $error = null;

    // Verificar restricciones
    $stmtCheck = $pdo->prepare("SELECT e.pais_id as equipo_pais, p.continente_id as equipo_continente
                                FROM equipo e
                                JOIN pais p ON e.pais_id = p.id
                                WHERE e.id = ?");
    $stmtCheck->execute([$equipo_id]);
    $equipoData = $stmtCheck->fetch();

    // Validaciones de ámbito
    if ($comp['pais_id'] && $equipoData['equipo_pais'] != $comp['pais_id']) {
        $error = "Este equipo no pertenece al país de la competición.";
    } elseif ($comp['continente_id'] && $equipoData['equipo_continente'] != $comp['continente_id']) {
        $error = "Este equipo no pertenece al continente de la competición.";
    }

    if (!$error && $comp['tipo_nombre'] === 'Liga') {
        // Restricción: Solo 1 Liga (Solo aplica si es ambito nacional, aunque tecnicamente una liga continental podria existir, asumimos logica estandar)
        // Buscar si ya está en otra competición tipo 'Liga'
        $sqlLigaCheck = "SELECT c.nombre FROM competicion_equipo ce 
                         JOIN competicion c ON ce.competicion_id = c.id 
                         JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id 
                         WHERE ce.equipo_id = ? AND tc.nombre = 'Liga'";
        $stmtLigaCheck = $pdo->prepare($sqlLigaCheck);
        $stmtLigaCheck->execute([$equipo_id]);
        $existingLiga = $stmtLigaCheck->fetch();

        if ($existingLiga) {
            $error = "Este equipo ya juega en otra Liga: " . $existingLiga['nombre'];
        }
    }

    if (!$error) {
        try {
            $stmt = $pdo->prepare("INSERT INTO competicion_equipo (competicion_id, equipo_id) VALUES (?, ?)");
            $stmt->execute([$competicion_id, $equipo_id]);
            $stmtClas = $pdo->prepare("INSERT INTO clasificacion (competicion_id, equipo_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE equipo_id = equipo_id");
            $stmtClas->execute([$competicion_id, $equipo_id]);
        } catch (PDOException $e) {
            $error = "El equipo ya está inscrito en esta competición.";
        }
    }
}

// Eliminar Equipo
if (isset($_GET['remove'])) {
    $remove_id = $_GET['remove'];
    $stmt = $pdo->prepare("DELETE FROM competicion_equipo WHERE competicion_id = ? AND equipo_id = ?");
    $stmt->execute([$competicion_id, $remove_id]);
    $pdo->prepare("DELETE FROM clasificacion WHERE competicion_id = ? AND equipo_id = ?")->execute([$competicion_id, $remove_id]);
    // Redirección limpia
    echo "<script>window.location.href='competicion_participantes.php?id=$competicion_id';</script>";
}

// Obtener Participantes Actuales
$sqlParticipantes = "SELECT e.* FROM equipo e 
                     JOIN competicion_equipo ce ON e.id = ce.equipo_id 
                     WHERE ce.competicion_id = ?";
$participantes = $pdo->prepare($sqlParticipantes);
$participantes->execute([$competicion_id]);
$lista_participantes = $participantes->fetchAll();

// Obtener Todos los Equipos Disponibles
// Lógica de filtrado según ámbito y tipo
$sqlDisponibles = "";
$params = [];

// Base Query: Equipos NO en esta competición
$baseQuery = "SELECT e.* FROM equipo e JOIN pais p ON e.pais_id = p.id WHERE e.id NOT IN (SELECT equipo_id FROM competicion_equipo WHERE competicion_id = ?)";
$params[] = $competicion_id;

// Filtro 1: Ámbito
if ($comp['pais_id']) {
    // Nacional
    $baseQuery .= " AND e.pais_id = ?";
    $params[] = $comp['pais_id'];
} elseif ($comp['continente_id']) {
    // Continental
    $baseQuery .= " AND p.continente_id = ?";
    $params[] = $comp['continente_id'];
}
// Internacional: No extra filter

// Filtro 2: Tipo 'Liga' (Solo si es Nacional, generalmente)
if ($comp['tipo_nombre'] === 'Liga') {
    $baseQuery .= " AND e.id NOT IN (
                        SELECT ce.equipo_id 
                        FROM competicion_equipo ce
                        JOIN competicion c ON ce.competicion_id = c.id
                        JOIN tipo_competicion tc ON c.tipo_competicion_id = tc.id
                        WHERE tc.nombre = 'Liga'
                    )";
}

$baseQuery .= " ORDER BY e.nombre ASC";

$disponibles = $pdo->prepare($baseQuery);
$disponibles->execute($params);
$lista_disponibles = $disponibles->fetchAll();
?>

<div class="container" style="margin-top: 40px; margin-bottom: 60px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div style="display: flex; align-items: center; gap: 20px;">
            <?php if($comp['logo_url']): ?>
                <img src="<?= $comp['logo_url'] ?>" style="width: 50px; height: 50px; object-fit: contain;">
            <?php endif; ?>
            <div>
                <h1 style="margin: 0; font-size: 2rem;"><?= htmlspecialchars($comp['nombre']) ?></h1>
                <p style="color: var(--text-muted); margin: 0;">
                    <?= htmlspecialchars($comp['tipo_nombre']) ?> • <?= htmlspecialchars($comp['temporada_actual']) ?>
                </p>
            </div>
        </div>
        <a href="competiciones.php" class="btn-admin" style="background: var(--secondary-color);">Volver a Competiciones</a>
    </div>

    <!-- TABS NAVEGACIÓN -->
    <div style="display: flex; gap: 10px; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
        <a href="#" class="btn-admin" style="background: var(--accent-color); color: white; margin-left: 0;">Equipos</a>
        <a href="competicion_clasificacion.php?id=<?= $competicion_id ?>" class="btn-admin" style="background: rgba(255,255,255,0.05); color: var(--text-muted); margin-left: 0;">Clasificación</a>
    </div>

    <?php if (isset($error)): ?>
        <div style="background: #ef4444; color: white; padding: 15px; border-radius: 8px; margin-bottom: 30px; border-left: 5px solid rgba(0,0,0,0.2);">
            ⚠️ <?= $error ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">

        <!-- LISTA -->
        <div>
            <div class="admin-card" style="padding: 25px; margin-bottom: 20px;">
                <h2 style="font-size: 1.5rem; color: #fff; margin-bottom: 10px;">Equipos Inscritos (<?= count($lista_participantes) ?>)</h2>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">
                <?php foreach ($lista_participantes as $eq): ?>
                    <div class="admin-card" style="margin-bottom: 0; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid rgba(255,255,255,0.05); transition: border-color 0.2s;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <?php if (!empty($eq['escudo_url'])): ?>
                                <img src="<?= htmlspecialchars($eq['escudo_url']) ?>" style="width: 28px; height: 28px; object-fit: contain;">
                            <?php else: ?>
                                <div style="width: 28px; height: 28px; background: rgba(255,255,255,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem;">🛡️</div>
                            <?php endif; ?>
                            <span style="font-weight: 600; color: #fff;"><?= htmlspecialchars($eq['nombre']) ?></span>
                        </div>
                        <a href="competicion_participantes.php?id=<?= $competicion_id ?>&remove=<?= $eq['id'] ?>" 
                           onclick="return confirm('¿Retirar a <?= addslashes($eq['nombre']) ?> de esta competición?')"
                           style="color: #ef4444; text-decoration: none; font-size: 0.85rem; font-weight: bold; background: rgba(239, 68, 68, 0.1); padding: 5px 12px; border-radius: 20px; transition: background 0.2s;">
                           Retirar
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($lista_participantes)): ?>
                <div class="admin-card" style="text-align: center; padding: 50px; color: var(--text-muted);">
                    <div style="font-size: 3rem; margin-bottom: 10px;">🏟️</div>
                    <p>No hay equipos inscritos en esta competición.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- FORMULARIO AÑADIR -->
        <div>
            <div class="admin-card" style="position: sticky; top: 100px;">
                <h3 style="color: #fff; margin-bottom: 20px;">Añadir Equipo</h3>
                <form method="POST">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-muted);">Seleccionar Equipo Disponible</label>
                        <select name="equipo_id" class="form-control-admin" required style="width: 100%;">
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($lista_disponibles as $d): ?>
                                <option value="<?= $d['id'] ?>">
                                    <?= htmlspecialchars($d['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-admin" style="width: 100%; margin: 0; padding: 12px; font-weight: bold; border: none; cursor: pointer;">
                        Inscribir Equipo
                    </button>
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 15px; text-align: center;">
                        Solo se muestran equipos que cumplen los requisitos de ámbito (País/Continente) de la competición.
                    </p>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
