<?php
require_once __DIR__ . '/../db.php';
include '../includes/header.php';

if (!isset($_GET['id'])) {
    die("ID de equipo no especificado.");
}
$equipo_id = $_GET['id'];

// Obtener info del equipo
$stmt = $pdo->prepare("SELECT * FROM equipo WHERE id = ?");
$stmt->execute([$equipo_id]);
$equipo = $stmt->fetch();

if (!$equipo)
    die("Equipo no encontrado.");

// Obtener países para la nacionalidad del jugador
$paises = $pdo->query("SELECT * FROM pais ORDER BY nombre ASC")->fetchAll();

// --- LOGICA DE EDICIÓN ---
$playerToEdit = null;
if (isset($_GET['edit_player'])) {
    $editId = $_GET['edit_player'];
    $stmtEdit = $pdo->prepare("SELECT j.*, p.nombre as pais_nombre FROM jugadores j LEFT JOIN pais p ON j.pais_id = p.id WHERE j.id = ? AND j.equipo_actual_id = ?");
    $stmtEdit->execute([$editId, $equipo_id]);
    $playerToEdit = $stmtEdit->fetch();
}

// Procesar formulario (AÑADIR o EDITAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        if ($_POST['action'] === 'add_player') {
            $nombre = trim($_POST['nombre']);
            $dorsal = !empty($_POST['dorsal']) ? $_POST['dorsal'] : null;
            $posicion = $_POST['posicion'];
            $pais_id = !empty($_POST['pais_id']) ? $_POST['pais_id'] : null;

            // AÑADIR JUGADOR INDIVIDUAL
            if (!empty($nombre)) {
                $sql = "INSERT INTO jugadores (nombre, equipo_actual_id, posicion, dorsal, pais_id) VALUES (?, ?, ?, ?, ?)";
                $stmtInsert = $pdo->prepare($sql);
                $stmtInsert->execute([$nombre, $equipo_id, $posicion, $dorsal, $pais_id]);
            }
        } elseif ($_POST['action'] === 'add_players_bulk') {
            // AÑADIR JUGADORES MASIVAMENTE
            if (isset($_POST['players']) && is_array($_POST['players'])) {
                $sql = "INSERT INTO jugadores (nombre, equipo_actual_id, posicion, dorsal, pais_id) VALUES (?, ?, ?, ?, ?)";
                $stmtInsert = $pdo->prepare($sql);

                foreach ($_POST['players'] as $player) {
                    $pNombre = trim($player['nombre']);
                    $pPosicion = $player['posicion'];
                    $pDorsal = !empty($player['dorsal']) ? $player['dorsal'] : null;
                    $pPaisId = !empty($player['pais_id']) ? $player['pais_id'] : null;

                    if (!empty($pNombre)) {
                        $stmtInsert->execute([$pNombre, $equipo_id, $pPosicion, $pDorsal, $pPaisId]);
                    }
                }
            }
        } elseif ($_POST['action'] === 'edit_player' && isset($_POST['player_id'])) {
            $nombre = trim($_POST['nombre']);
            $dorsal = !empty($_POST['dorsal']) ? $_POST['dorsal'] : null;
            $posicion = $_POST['posicion'];
            $pais_id = !empty($_POST['pais_id']) ? $_POST['pais_id'] : null;

            // EDITAR JUGADOR
            $playerId = $_POST['player_id'];
            if (!empty($nombre)) {
                $sql = "UPDATE jugadores SET nombre = ?, posicion = ?, dorsal = ?, pais_id = ? WHERE id = ? AND equipo_actual_id = ?";
                $stmtUpdate = $pdo->prepare($sql);
                $stmtUpdate->execute([$nombre, $posicion, $dorsal, $pais_id, $playerId, $equipo_id]);

                // Redirigir para limpiar la URL de edición
                echo "<script>window.location.href='equipo_jugadores.php?id=$equipo_id';</script>";
                exit;
            }
        }
    }
}

// Procesar ELIMINAR JUGADOR
if (isset($_GET['delete_player'])) {
    $player_id = $_GET['delete_player'];
    $stmtDel = $pdo->prepare("DELETE FROM jugadores WHERE id = ? AND equipo_actual_id = ?");
    $stmtDel->execute([$player_id, $equipo_id]);
    echo "<script>window.location.href='equipo_jugadores.php?id=$equipo_id';</script>";
    exit;
}

// Obtener jugadores del equipo
$stmtPlayers = $pdo->prepare("SELECT j.*, p.nombre as pais_nombre, p.bandera_url 
                              FROM jugadores j 
                              LEFT JOIN pais p ON j.pais_id = p.id 
                              WHERE j.equipo_actual_id = ? 
                              ORDER BY FIELD(j.posicion, 'Portero', 'Defensa', 'Centrocampista', 'Delantero', 'Entrenador'), j.dorsal ASC");
$stmtPlayers->execute([$equipo_id]);
$jugadores = $stmtPlayers->fetchAll();
?>

<div class="container" style="margin-top: 40px; margin-bottom: 60px;">

    <!-- Encabezado Equipo -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 25px;">
        <div style="display: flex; align-items: center; gap: 25px;">
            <?php if (!empty($equipo['escudo_url'])): ?>
                <div style="width: 80px; height: 80px; background: rgba(255,255,255,0.05); border-radius: 15px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1);">
                    <img src="<?= htmlspecialchars($equipo['escudo_url']) ?>" alt="Escudo" style="max-width: 60px; max-height: 60px; object-fit: contain;">
                </div>
            <?php endif; ?>
            <div>
                <h1 style="margin: 0; font-size: 2.5rem;"><?= htmlspecialchars($equipo['nombre']) ?></h1>
                <p style="color: var(--text-muted); margin: 0; font-size: 1.1rem;">Gestión de Plantilla Actual</p>
            </div>
        </div>
        <a href="equipos.php" class="btn-admin" style="background: var(--secondary-color);">Volver a Equipos</a>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 40px;">

        <!-- LISTA DE JUGADORES -->
        <div>
            <div class="admin-card" style="padding: 0; overflow: hidden;">
                <div style="padding: 20px 25px; background: rgba(0,0,0,0.1); border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="margin: 0; font-size: 1.25rem;">Jugadores Inscritos (<?= count($jugadores) ?>)</h2>
                </div>

                <div class="table-wrap" style="border-radius: 0; border-left: 0; border-right: 0;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center; padding-left: 25px;">#</th>
                                <th>Nombre del Jugador</th>
                                <th>Posición</th>
                                <th style="text-align: center;">Nac.</th>
                                <th style="text-align: right; padding-right: 25px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jugadores as $j): ?>
                                <tr style="<?= ($playerToEdit && $playerToEdit['id'] == $j['id']) ? 'background: rgba(59, 130, 246, 0.1);' : '' ?>">
                                    <td style="text-align: center; padding-left: 25px; font-weight: 800; color: var(--accent-color); font-size: 1.1rem;">
                                        <?= $j['dorsal'] ?? '-' ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: #fff; font-size: 1.05rem;">
                                            <?= htmlspecialchars($j['nombre']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $posClass = 'badge-info';
                                        if ($j['posicion'] == 'Portero') $posClass = 'badge-warning';
                                        if ($j['posicion'] == 'Defensa') $posClass = 'badge-success';
                                        if ($j['posicion'] == 'Delantero') $posClass = 'badge-striker';
                                        if ($j['posicion'] == 'Entrenador') $posClass = 'badge-danger';
                                        ?>
                                        <span class="badge <?= $posClass ?>">
                                            <?= htmlspecialchars($j['posicion']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if (!empty($j['bandera_url'])): ?>
                                            <img src="<?= htmlspecialchars($j['bandera_url']) ?>" title="<?= htmlspecialchars($j['pais_nombre']) ?>" style="width: 24px; border-radius: 2px;">
                                        <?php else: ?>
                                            <span style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($j['pais_nombre'] ?? '-') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; padding-right: 25px;">
                                        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 12px;">
                                            <a href="equipo_jugadores.php?id=<?= $equipo_id ?>&edit_player=<?= $j['id'] ?>"
                                                class="btn-admin" style="margin: 0; padding: 5px 10px; font-size: 0.8rem; background: rgba(59, 130, 246, 0.1); color: #3b82f6;">Editar</a>
                                            <a href="equipo_jugadores.php?id=<?= $equipo_id ?>&delete_player=<?= $j['id'] ?>"
                                                onclick="return confirm('¿Eliminar a <?= addslashes($j['nombre']) ?>?')"
                                                style="color: #ef4444; text-decoration: none; font-size: 1.2rem; line-height: 1; padding: 5px;" title="Eliminar">&times;</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($jugadores)): ?>
                                <tr>
                                    <td colspan="5" style="padding: 50px; text-align: center; color: var(--text-muted);">
                                        <div style="font-size: 3rem; margin-bottom: 10px;">🏃‍♂️</div>
                                        No hay jugadores registrados en la plantilla.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- FORMULARIO AÑADIR / EDITAR -->
        <div>
            <div class="admin-card" style="position: sticky; top: 100px; border-left: 4px solid var(--accent-color); padding: 30px;">
                <h3 style="margin-bottom: 25px; color: #fff; font-size: 1.25rem;">
                    <?= $playerToEdit ? 'Editar Jugador' : 'Añadir Nuevo Jugador' ?>
                </h3>

                <form method="POST" action="equipo_jugadores.php?id=<?= $equipo_id ?>" autocomplete="off">
                    <input type="hidden" name="action" value="<?= $playerToEdit ? 'edit_player' : 'add_player' ?>">
                    <?php if ($playerToEdit): ?>
                        <input type="hidden" name="player_id" value="<?= $playerToEdit['id'] ?>">
                    <?php endif; ?>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px;">Nombre Completo</label>
                        <input type="text" name="nombre" required value="<?= htmlspecialchars($playerToEdit['nombre'] ?? '') ?>" class="form-control-admin" placeholder="Ej: Lionel Messi">
                    </div>

                    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 15px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px;">Posición</label>
                            <select name="posicion" class="form-control-admin">
                                <?php
                                $positions = ['Portero', 'Defensa', 'Centrocampista', 'Delantero', 'Entrenador'];
                                foreach ($positions as $pos): ?>
                                    <option value="<?= $pos ?>" <?= ($playerToEdit && $playerToEdit['posicion'] == $pos) ? 'selected' : '' ?>>
                                        <?= $pos ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px;">Dorsal</label>
                            <input type="number" name="dorsal" value="<?= htmlspecialchars($playerToEdit['dorsal'] ?? '') ?>" class="form-control-admin" placeholder="Ej: 10">
                        </div>
                    </div>

                    <div style="margin-bottom: 25px;">
                        <label style="display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px;">Nacionalidad</label>
                        <input type="text" list="paisesList" value="<?= $playerToEdit ? htmlspecialchars($playerToEdit['pais_nombre'] ?? '') : '' ?>" oninput="updatePaisId(this, 'single_pais_id')" placeholder="Buscar país..." class="form-control-admin">
                        <input type="hidden" name="pais_id" id="single_pais_id" value="<?= $playerToEdit['pais_id'] ?? '' ?>">
                    </div>

                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn-admin" style="flex: 1; margin: 0; padding: 12px; font-weight: bold; background: var(--accent-color);">
                            <?= $playerToEdit ? 'Guardar Cambios' : 'Añadir Jugador' ?>
                        </button>
                        <?php if ($playerToEdit): ?>
                            <a href="equipo_jugadores.php?id=<?= $equipo_id ?>" class="btn-admin" style="margin: 0; padding: 12px; background: rgba(255,255,255,0.05); color: var(--text-muted);">
                                Cancelar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<div class="container" style="margin-top: 40px; margin-bottom: 100px;">
    <div class="admin-card" style="padding: 30px;">
        <h3 style="margin-bottom: 25px; color: #fff; font-size: 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 15px;">
            Carga Masiva de Plantilla
        </h3>

        <form method="POST" action="equipo_jugadores.php?id=<?= $equipo_id ?>" autocomplete="off">
            <input type="hidden" name="action" value="add_players_bulk">

            <?php
            // ID y Nombre del país del equipo para valor por defecto
            $stmtPais = $pdo->prepare("SELECT nombre FROM pais WHERE id = ?");
            if (!empty($equipo['pais_id'])) {
                $stmtPais->execute([$equipo['pais_id']]);
                $nombrePais = $stmtPais->fetchColumn();
                $defaultPaisId = $equipo['pais_id'];
                $defaultPaisNombre = $nombrePais ? $nombrePais : '';
            } else {
                $defaultPaisId = '';
                $defaultPaisNombre = '';
            }
            ?>

            <div style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 6px;">
                <!-- Bulk Generator Controls -->
                <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <h4 style="margin: 0 0 10px 0; color: var(--accent-color);">Generar Filas Rápidas</h4>
                    <div style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                        <?php
                        $positions = ['Portero', 'Defensa', 'Centrocampista', 'Delantero'];
                        foreach ($positions as $pos):
                        ?>
                            <div>
                                <label
                                    style="display: block; font-size: 0.8rem; margin-bottom: 3px; color: var(--text-muted);"><?= $pos ?>s</label>
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <input type="number" id="count-<?= $pos ?>" value="0" min="0"
                                        style="width: 50px; padding: 5px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 4px; text-align: center;">
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <button type="button" onclick="generateBulkRows()"
                            style="background: var(--accent-color); color: var(--primary-color); border: none; padding: 6px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; height: 32px;">
                            + Generar
                        </button>
                    </div>
                </div>

                <p style="color: var(--text-muted); margin-bottom: 10px; font-size: 0.9rem;">
                    💡 <strong>Tip:</strong> Usa <code>Enter</code> para saltar a la siguiente fila. Al final de la
                    lista, crea una nueva automáticamente. Usa las <code>Flechas</code> para moverte.
                </p>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;" id="bulkTable">
                    <thead>
                        <tr style="text-align: left; color: var(--text-muted); font-size: 0.9rem;">
                            <th style="padding: 5px;">Posición</th>
                            <th style="padding: 5px;">Nombre</th>
                            <th style="padding: 5px; width: 80px;">Dorsal</th>
                            <th style="padding: 5px;">Nacionalidad</th>
                            <th style="padding: 5px; width: 30px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Rows added by JS -->
                    </tbody>
                </table>

                <button type="button" onclick="addBulkRow()"
                    style="background: #475569; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 0.9rem;">
                    + Añadir Fila
                </button>
            </div>

            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                <button type="submit" class="btn-admin"
                    style="background: var(--accent-color); width: 100%; padding: 15px; font-size: 1.1rem;">
                    Guardar Todos los Jugadores
                </button>
            </div>
        </form>
    </div>
</div>

<!-- DATA LIST FOR COUNTRIES -->
<datalist id="paisesList">
    <?php foreach ($paises as $p): ?>
        <option value="<?= htmlspecialchars($p['nombre']) ?>"></option>
    <?php endforeach; ?>
</datalist>

<script>
    let playerCount = 0;
    const defaultCountryId = "<?= $defaultPaisId ?>";
    const defaultCountryName = "<?= htmlspecialchars($defaultPaisNombre) ?>";

    // Map names to IDs for lookup
    const paisesMap = {};
    <?php foreach ($paises as $p): ?>
        paisesMap["<?= addslashes($p['nombre']) ?>"] = "<?= $p['id'] ?>";
    <?php endforeach; ?>

    function updatePaisId(input, hiddenId) {
        const val = input.value;
        const hiddenInput = document.getElementById(hiddenId);

        if (paisesMap[val]) {
            hiddenInput.value = paisesMap[val];
            input.style.borderColor = "#22c55e"; // Green border if valid
            input.style.borderWidth = "2px";
        } else {
            hiddenInput.value = ""; // Clear ID if name not found
            if (val.length > 0) {
                input.style.borderColor = "#ef4444"; // Red border if invalid/not selected
                input.style.borderWidth = "1px";
            } else {
                input.style.borderColor = "rgba(255,255,255,0.1)"; // Reset
                input.style.borderWidth = "1px";
            }
        }
    }

    function generateBulkRows() {
        const positions = ['Portero', 'Defensa', 'Centrocampista', 'Delantero'];

        positions.forEach(pos => {
            const countInput = document.getElementById(`count-${pos}`);
            const count = parseInt(countInput.value) || 0;

            for (let i = 0; i < count; i++) {
                addBulkRow({
                    pos: pos,
                    paisName: defaultCountryName,
                    paisId: defaultCountryId
                });
            }

            // Reset input
            countInput.value = 0;
        });
    }

    function addBulkRow(previousData = null) {
        const tbody = document.querySelector(`#bulkTable tbody`);
        const tr = document.createElement('tr');
        tr.className = 'bulk-player-row';
        const currentRowId = playerCount;

        // Determinar valores por defecto (del anterior o globales)
        let defPos = 'Centrocampista';
        let defPaisId = defaultCountryId;
        let defPaisName = defaultCountryName;

        if (previousData) {
            defPos = previousData.pos;
            defPaisId = previousData.paisId;
            defPaisName = previousData.paisName;
        }

        const hiddenInputId = `pais_hidden_${currentRowId}`;

        tr.innerHTML = `
            <td style="padding: 5px;">
                <select name="players[${currentRowId}][posicion]" class="smart-input" data-col="0"
                    style="width: 100%; padding: 8px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 4px;">
                    <option value="Portero" ${defPos === 'Portero' ? 'selected' : ''}>Portero</option>
                    <option value="Defensa" ${defPos === 'Defensa' ? 'selected' : ''}>Defensa</option>
                    <option value="Centrocampista" ${defPos === 'Centrocampista' ? 'selected' : ''}>Medio</option>
                    <option value="Delantero" ${defPos === 'Delantero' ? 'selected' : ''}>Delantero</option>
                    <option value="Entrenador" ${defPos === 'Entrenador' ? 'selected' : ''}>Entrenador</option>
                </select>
            </td>
            <td style="padding: 5px;">
                <input type="text" name="players[${currentRowId}][nombre]" class="smart-input" data-col="1" required placeholder="Nombre"
                    style="width: 100%; padding: 8px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 4px;">
            </td>
            <td style="padding: 5px;">
                <input type="number" name="players[${currentRowId}][dorsal]" class="smart-input" data-col="2" placeholder="#"
                    style="width: 100%; padding: 8px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 4px;">
            </td>
            <td style="padding: 5px;">
                <input type="text" list="paisesList"
                    class="country-input smart-input" data-col="3"
                    oninput="updatePaisId(this, '${hiddenInputId}')"
                    value="${defPaisName}"
                    placeholder="Buscar país..."
                    style="width: 100%; padding: 8px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 4px;">
                <input type="hidden" name="players[${currentRowId}][pais_id]" id="${hiddenInputId}" value="${defPaisId}">
            </td>
            <td style="padding: 5px; text-align: center;">
                <button type="button" tabindex="-1" onclick="removeRow(this)" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.2rem;">&times;</button>
            </td>
        `;

        tbody.appendChild(tr);

        // Trigger validation visual for pre-filled country
        if (defPaisName && defPaisId) {
            const input = tr.querySelector('.country-input');
            if (input) {
                input.style.borderColor = "#22c55e";
                input.style.borderWidth = "2px";
            }
        }

        playerCount++;
        return tr;
    }

    function removeRow(btn) {
        const row = btn.closest('tr');
        row.remove();
    }

    // --- KEYBOARD NAVIGATION ---
    document.addEventListener('keydown', function(e) {
        if (!e.target.classList.contains('smart-input')) return;

        const currentInput = e.target;
        const currentRow = currentInput.closest('tr');
        const currentColIndex = parseInt(currentInput.getAttribute('data-col'));

        // ENTER key
        if (e.key === 'Enter') {
            e.preventDefault();
            const nextRow = currentRow.nextElementSibling;

            if (nextRow) {
                // Move to same column in next row (or name column if preference)
                // Let's move to Name column (index 1) of next row for speed, or same column
                const nextInput = nextRow.querySelector(`.smart-input[data-col="${currentColIndex}"]`) || nextRow.querySelector(`.smart-input[data-col="1"]`);
                if (nextInput) nextInput.focus();
            } else {
                // Create NEW ROW
                // Get data from current row to copy
                const posVal = currentRow.querySelector('select').value;
                const paisVal = currentRow.querySelector('.country-input').value;
                const paisIdVal = currentRow.querySelector('input[type="hidden"]').value;

                const newRow = addBulkRow({
                    pos: posVal,
                    paisName: paisVal,
                    paisId: paisIdVal
                });

                // Focus on Name (col 1) of new row
                setTimeout(() => {
                    const firstInput = newRow.querySelector(`.smart-input[data-col="1"]`);
                    if (firstInput) firstInput.focus();
                }, 10);
            }
        }

        // ARROW KEYS (Simple grid navigation)
        if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
            // Prevent default scroll only if we are moving focus

            let targetRow = currentRow;
            let targetCol = currentColIndex;

            if (e.key === 'ArrowUp') {
                targetRow = currentRow.previousElementSibling;
            } else if (e.key === 'ArrowDown') {
                targetRow = currentRow.nextElementSibling;
            } else if (e.key === 'ArrowLeft') {
                targetCol = currentColIndex - 1;
            } else if (e.key === 'ArrowRight') {
                targetCol = currentColIndex + 1;
            }

            if (targetRow && targetCol >= 0) {
                const targetInput = targetRow.querySelector(`.smart-input[data-col="${targetCol}"]`);
                if (targetInput) {
                    e.preventDefault();
                    targetInput.focus();
                    // Select text if it's text input
                    if (targetInput.type === 'text' || targetInput.type === 'number') {
                        targetInput.select();
                    }
                }
            }
        }
    });

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        // No initial rows, let user generate them or use the button
    });
</script>

<?php include '../includes/footer.php'; ?>
