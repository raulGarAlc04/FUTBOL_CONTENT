<?php
require_once __DIR__ . '/../db.php';
include '../includes/header.php';

// Eliminar equipo
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM equipo WHERE id = ?");
        $stmt->execute([$id]);
        echo "<script>window.location.href='equipos.php';</script>";
    } catch (PDOException $e) {
        $error = "Error al eliminar: " . $e->getMessage();
    }
}

// Obtener países para el filtro
$paises = $pdo->query("SELECT * FROM pais ORDER BY nombre ASC")->fetchAll();

// Filtro y Buscador
$pais_filter = isset($_GET['pais_id']) ? $_GET['pais_id'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- LÓGICA DE PAGINACIÓN ---
$limit = 20; // Equipos por página
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// 1. Contar total de equipos (respetando filtros)
$sqlCount = "SELECT COUNT(*) FROM equipo e";
$whereClauses = [];
$params = [];

if (!empty($pais_filter)) {
    $whereClauses[] = "e.pais_id = ?";
    $params[] = $pais_filter;
}

if (!empty($search)) {
    $whereClauses[] = "e.nombre LIKE ?";
    $params[] = "%$search%";
}

if (!empty($whereClauses)) {
    $sqlCount .= " WHERE " . implode(" AND ", $whereClauses);
}

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalTeams = $stmtCount->fetchColumn();
$totalPages = ceil($totalTeams / $limit);

// 2. Obtener equipos (con filtro y límites)
$sql = "SELECT e.*, p.nombre as pais_nombre, p.bandera_url 
        FROM equipo e 
        LEFT JOIN pais p ON e.pais_id = p.id";

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

$sql .= " ORDER BY e.id DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipos = $stmt->fetchAll();
?>

<div class="container" style="margin-top: 40px; margin-bottom: 60px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 2.5rem; margin-bottom: 5px;">Gestión de Equipos</h1>
            <p style="color: var(--text-muted);">Administra los clubes y selecciones de la base de datos.</p>
        </div>
        <a href="equipo_form.php" class="btn-admin" style="padding: 12px 25px; font-weight: bold; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
            ➕ Añadir Nuevo Equipo
        </a>
    </div>

    <!-- Filtro y Buscador -->
    <div class="admin-card" style="padding: 20px; margin-bottom: 30px;">
        <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <label style="display: block; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-muted);">Buscar por Nombre</label>
                <input type="text" name="search" placeholder="Ej: Real Madrid, Manchester..." value="<?= htmlspecialchars($search) ?>" class="form-control-admin">
            </div>

            <div style="flex: 1; min-width: 250px;">
                <label style="display: block; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-muted);">Filtrar por País</label>
                <select name="pais_id" onchange="this.form.submit()" class="form-control-admin">
                    <option value="">-- Todos los Países --</option>
                    <?php foreach ($paises as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $pais_filter == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn-admin" style="margin: 0; padding: 10px 20px;">Buscar</button>
                <?php if (!empty($pais_filter) || !empty($search)): ?>
                    <a href="equipos.php" class="btn-admin" style="margin: 0; padding: 10px 20px; background: rgba(255,255,255,0.05); color: var(--text-muted);">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
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
                    <th style="width: 60px;">Escudo</th>
                    <th>Nombre del Equipo</th>
                    <th>País / Región</th>
                    <th>Estadio Principal</th>
                    <th style="text-align: right; padding-right: 25px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equipos as $eq): ?>
                    <tr>
                        <td style="padding-left: 25px; font-weight: bold; color: var(--text-muted);">#<?= $eq['id'] ?></td>
                        <td>
                            <?php if (!empty($eq['escudo_url'])): ?>
                                <img src="<?= htmlspecialchars($eq['escudo_url']) ?>" alt="Escudo"
                                    style="width: 32px; height: 32px; object-fit: contain;">
                            <?php else: ?>
                                <div style="width: 32px; height: 32px; background: rgba(255,255,255,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">🛡️</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight: 700; font-size: 1.1rem; color: #fff;">
                                <?= htmlspecialchars($eq['nombre']) ?>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <?php if (!empty($eq['bandera_url'])): ?>
                                    <img src="<?= htmlspecialchars($eq['bandera_url']) ?>" alt="Bandera"
                                        style="width: 20px; border-radius: 2px;">
                                <?php endif; ?>
                                <span style="font-size: 0.95rem;"><?= htmlspecialchars($eq['pais_nombre'] ?? 'Sin País') ?></span>
                            </div>
                        </td>
                        <td>
                            <div style="color: var(--text-muted); font-size: 0.9rem;">
                                <?= htmlspecialchars($eq['estadio'] ?? 'No especificado') ?>
                            </div>
                        </td>
                        <td style="text-align: right; padding-right: 25px;">
                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
                                <a href="equipo_jugadores.php?id=<?= $eq['id'] ?>" class="btn-admin" style="margin: 0; padding: 6px 12px; font-size: 0.8rem; background: rgba(59, 130, 246, 0.1); color: #3b82f6;">Plantilla</a>
                                <a href="equipo_form.php?id=<?= $eq['id'] ?>" class="btn-admin" style="margin: 0; padding: 6px 12px; font-size: 0.8rem; background: rgba(255,255,255,0.05); color: var(--text-muted);">Editar</a>
                                <a href="equipos.php?delete=<?= $eq['id'] ?>" 
                                   onclick="return confirm('¿Seguro que quieres eliminar este equipo?')"
                                   style="color: #ef4444; text-decoration: none; font-size: 1.2rem; line-height: 1; padding: 5px;" title="Eliminar">&times;</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($equipos)): ?>
                    <tr>
                        <td colspan="6" style="padding: 50px; text-align: center; color: var(--text-muted);">
                            <div style="font-size: 3rem; margin-bottom: 10px;">🛡️</div>
                            No se encontraron equipos que coincidan con la búsqueda.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
            </table>
        </div>

        <!-- PAGINACIÓN UI -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination" style="padding: 20px; background: rgba(0,0,0,0.1); border-top: 1px solid rgba(255,255,255,0.05);">
                <?php
                $queryParams = [];
                if (!empty($pais_filter)) $queryParams['pais_id'] = $pais_filter;
                if (!empty($search)) $queryParams['search'] = $search;
                $queryString = http_build_query($queryParams);
                $filterParam = !empty($queryString) ? "&" . $queryString : "";
                ?>

                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $filterParam ?>" class="page-link">&larr; Anterior</a>
                <?php endif; ?>

                <span style="padding: 8px 15px; color: var(--text-muted);">
                    Página <strong><?= $page ?></strong> de <?= $totalPages ?>
                </span>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $filterParam ?>" class="page-link">Siguiente &rarr;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div style="margin-top: 30px;">
        <a href="index.php" style="color: var(--text-muted); text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            &larr; Volver al Panel de Control
        </a>
    </div>
</div>

    <div style="margin-top: 20px;">
        <a href="index.php" style="color: var(--text-muted); text-decoration: none;">&larr; Volver al Panel</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
