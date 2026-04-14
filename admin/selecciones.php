<?php
require_once __DIR__ . '/../db.php';
include '../includes/header.php';

// Obtener Continentes
$stmtContinentes = $pdo->query("SELECT * FROM continente ORDER BY nombre ASC");
$continentes = $stmtContinentes->fetchAll();

$stmtLiga = $pdo->prepare("SELECT id FROM tipo_competicion WHERE nombre = 'Liga' LIMIT 1");
$stmtLiga->execute();
$ligaTipoId = (int) $stmtLiga->fetchColumn();
if (!$ligaTipoId) {
    die("No existe el tipo de competición 'Liga' en la base de datos.");
}

// ACTION: Create Competition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_comp') {
    $nombre = $_POST['nombre'];
    $scope = $_POST['scope']; // continent_X or international
    $paises_seleccionados = $_POST['paises'] ?? [];

    // Validar
    if (empty($nombre) || empty($paises_seleccionados)) {
        echo "<script>alert('Por favor complete todos los campos y seleccione al menos un país.');</script>";
        exit; // Should render page again properly, but simple alert for now
    }

    // 1. Crear Competición
    $pais_id = null;
    $continente_id = null;
    if (strpos($scope, 'continent_') === 0) {
        $continente_id = (int) str_replace('continent_', '', $scope);
    }
    $sqlComp = "INSERT INTO competicion (nombre, pais_id, continente_id, tipo_competicion_id, temporada_actual, logo_url) VALUES (?, ?, ?, ?, '2026', '')";
    $stmtComp = $pdo->prepare($sqlComp);

    // Si es scope continental, podríamos guardar el continente_id si existiera columna, o inferir
    // Por ahora simplemente creamos la competición.

    $stmtComp->execute([$nombre, $pais_id, $continente_id, $ligaTipoId]);
    $comp_id = $pdo->lastInsertId();

    // 2. Procesar Países
    foreach ($paises_seleccionados as $pais_id) {
        // Obtener datos del país
        $stmtPais = $pdo->prepare("SELECT * FROM pais WHERE id = ?");
        $stmtPais->execute([$pais_id]);
        $pais = $stmtPais->fetch();

        if ($pais) {
            // Check si existe equipo "Selección" para este país
            // Asumimos nombre = nombre del país
            $stmtEq = $pdo->prepare("SELECT id FROM equipo WHERE nombre = ? AND pais_id = ?");
            $stmtEq->execute([$pais['nombre'], $pais_id]);
            $equipo = $stmtEq->fetch();

            if (!$equipo) {
                // Crear equipo
                $stmtNewEq = $pdo->prepare("INSERT INTO equipo (nombre, pais_id, escudo_url) VALUES (?, ?, ?)");
                // Usamos bandera como escudo inicial
                $stmtNewEq->execute([$pais['nombre'], $pais_id, $pais['bandera_url']]);
                $equipo_id = $pdo->lastInsertId();
            } else {
                $equipo_id = $equipo['id'];
            }

            // 3. Añadir a Competición
            $stmtCompEq = $pdo->prepare("INSERT INTO competicion_equipo (competicion_id, equipo_id) VALUES (?, ?)");
            $stmtCompEq->execute([$comp_id, $equipo_id]);
            $pdo->prepare("INSERT INTO clasificacion (competicion_id, equipo_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE equipo_id = equipo_id")
                ->execute([$comp_id, $equipo_id]);
        }
    }

    // Redirect
    echo "<script>window.location.href='competicion_clasificacion.php?id=$comp_id';</script>";
    exit;

}

// Obtener Países por Continente para el Formulario
$paisesByContinent = [];
foreach ($continentes as $c) {
    $stmtP = $pdo->prepare("SELECT * FROM pais WHERE continente_id = ? ORDER BY nombre ASC");
    $stmtP->execute([$c['id']]);
    $paisesByContinent[$c['id']] = $stmtP->fetchAll();
}
?>

<div class="container" style="margin-top: 40px; margin-bottom: 60px; max-width: 1000px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 2.5rem; margin-bottom: 5px;">Gestor de Selecciones</h1>
            <p style="color: var(--text-muted);">Crea ligas de selecciones y gestiona la clasificación manual.</p>
        </div>
        <a href="index.php" class="btn-admin" style="background: var(--secondary-color);">Volver al Panel</a>
    </div>

    <div class="admin-card" style="padding: 40px;">
        <form method="POST" id="formSelecciones">
            <input type="hidden" name="action" value="create_comp">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                <!-- 1. Nombre -->
                <div style="grid-column: span 2;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; color: #fff;">Nombre de la Competición</label>
                    <input type="text" name="nombre" class="form-control-admin" placeholder="Ej: Mundial 2026, Eurocopa, Copa América..." required style="padding: 12px; font-size: 1.1rem;">
                </div>

                <!-- 3. Ámbito Geographic -->
                <div>
                    <label style="display:block; margin-bottom:12px; font-weight:600; color: #fff;">Ámbito Geográfico</label>
                    <div style="margin-bottom: 15px;">
                        <select id="scopeSelect" name="scope" class="form-control-admin" onchange="updateCountryList()" style="padding: 12px;">
                            <option value="international">🌎 Internacional (Todos los países)</option>
                            <?php foreach ($continentes as $c): ?>
                                <option value="continent_<?= $c['id'] ?>">🌍 Continental - <?= htmlspecialchars($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="background: rgba(59, 130, 246, 0.1); padding: 15px; border-radius: 8px; border: 1px solid rgba(59, 130, 246, 0.2);">
                        <p style="margin: 0; font-size: 0.85rem; color: #93c5fd; line-height: 1.5;">
                            💡 <strong>Tip:</strong> El sistema creará automáticamente los equipos nacionales si aún no existen para los países seleccionados.
                        </p>
                    </div>
                </div>
            </div>

            <!-- 4. Selección de Países -->
            <div style="margin-bottom: 40px;">
                <label style="display:block; margin-bottom:15px; font-weight:600; color: #fff; display: flex; justify-content: space-between; align-items: center;">
                    Seleccionar Países Participantes
                    <span id="counterDisplay" style="font-size: 0.8rem; background: var(--accent-color); color: var(--primary-color); padding: 2px 10px; border-radius: 20px;">0 seleccionados</span>
                </label>

                <div id="countriesContainer" style="max-height: 500px; overflow-y: auto; background: rgba(0,0,0,0.15); padding: 25px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                    <!-- Generado por JS -->
                </div>
            </div>

            <div style="text-align: center; padding-top: 30px; border-top: 1px solid rgba(255,255,255,0.05);">
                <button type="submit" class="btn-admin" style="font-size: 1.2rem; padding: 15px 60px; font-weight: 800; background: var(--accent-color); box-shadow: 0 10px 20px rgba(0,0,0,0.2);">
                    Crear Torneo de Selecciones
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .country-item {
        transition: all 0.2s;
        border: 1px solid transparent;
        padding: 8px;
        border-radius: 8px;
    }
    .country-item:hover {
        background: rgba(255,255,255,0.05);
        border-color: rgba(255,255,255,0.1);
    }
    .country-item input[type="checkbox"]:checked + span {
        color: var(--accent-color);
        font-weight: bold;
    }
</style>

<script>
    // Data from PHP
    const continentData = <?php echo json_encode($paisesByContinent); ?>;
    const continentsList = <?php echo json_encode($continentes); ?>;

    function updateCountryList() {
        const scope = document.getElementById('scopeSelect').value;
        const container = document.getElementById('countriesContainer');
        container.innerHTML = '';

        if (scope === 'international') {
            // Mostrar TODOS agrupados por continente
            continentsList.forEach(cont => {
                if (continentData[cont.id]) {
                    renderContinentGroup(cont.id, cont.nombre, continentData[cont.id]);
                }
            });
        } else if (scope.startsWith('continent_')) {
            const contId = scope.split('_')[1];
            const contObj = continentsList.find(c => c.id == contId);
            if (contObj && continentData[contId]) {
                renderContinentGroup(contId, contObj.nombre, continentData[contId]);
            }
        }
    }

    function renderContinentGroup(id, name, countries) {
        if (!countries || countries.length === 0) return;

        const container = document.getElementById('countriesContainer');

        // Title
        const title = document.createElement('h4');
        title.textContent = name;
        title.style.color = 'var(--accent-color)';
        title.style.marginTop = '10px';
        title.style.marginBottom = '10px';
        title.style.borderBottom = '1px solid rgba(255,255,255,0.1)';
        container.appendChild(title);

        // Grid
        const grid = document.createElement('div');
        grid.style.display = 'grid';
        grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(180px, 1fr))';
        grid.style.gap = '10px';
        grid.style.marginBottom = '20px';

        countries.forEach(p => {
            const label = document.createElement('label');
            label.style.display = 'flex';
            label.style.alignItems = 'center';
            label.style.gap = '8px';
            label.style.cursor = 'pointer';
            label.className = 'country-item';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'paises[]';
            checkbox.value = p.id;

            const img = document.createElement('img');
            img.src = p.bandera_url || '';
            img.style.width = '20px';
            img.style.height = '15px';
            img.style.objectFit = 'contain';

            const span = document.createElement('span');
            span.textContent = p.nombre;

            label.appendChild(checkbox);
            if (p.bandera_url) label.appendChild(img);
            label.appendChild(span);

            grid.appendChild(label);
        });

        container.appendChild(grid);
    }

    // Init
    updateCountryList();
</script>

<?php include '../includes/footer.php'; ?>
