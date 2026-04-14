<?php
require_once __DIR__ . '/../db.php';
include '../includes/header.php';

// Eliminar país
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM pais WHERE id = ?");
        $stmt->execute([$id]);
        echo "<script>window.location.href='paises.php';</script>";
    } catch (PDOException $e) {
        $error = "Error al eliminar: " . $e->getMessage();
    }
}

// --- LÓGICA DE OBTENCIÓN DE DATOS ---

// Obtener continentes para el filtro
$continentes = $pdo->query("SELECT * FROM continente ORDER BY nombre ASC")->fetchAll();

// Filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$continente_filter = isset($_GET['continente_id']) ? $_GET['continente_id'] : '';

// --- LÓGICA DE PAGINACIÓN ---
$limit = 20; // Países por página
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// Construir consulta base
$sqlBase = "FROM pais p LEFT JOIN continente c ON p.continente_id = c.id";
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "p.nombre LIKE ?";
    $params[] = "%$search%";
}

if (!empty($continente_filter)) {
    $whereClauses[] = "p.continente_id = ?";
    $params[] = $continente_filter;
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = " WHERE " . implode(" AND ", $whereClauses);
}

// Contar total de países (con filtros)
$stmtCount = $pdo->prepare("SELECT COUNT(*) $sqlBase $whereSql");
$stmtCount->execute($params);
$totalCountries = $stmtCount->fetchColumn();
$totalPages = ceil($totalCountries / $limit);

// Obtener países paginados
$sql = "SELECT p.*, c.nombre as continente_nombre 
        $sqlBase $whereSql 
        ORDER BY p.id DESC 
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$paises = $stmt->fetchAll();
?>

<div class="container" style="margin-top: 40px; margin-bottom: 60px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1 style="font-size: 2.5rem; margin-bottom: 5px;">Gestión de Países</h1>
            <p style="color: var(--text-muted);">Administra las naciones y sus banderas registradas en el sistema.</p>
        </div>
        <a href="pais_form.php" class="btn-admin" style="padding: 12px 25px; font-weight: bold; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
            ➕ Añadir Nuevo País
        </a>
    </div>

    <!-- Filtros y Buscador -->
    <div class="admin-card" style="padding: 20px; margin-bottom: 30px;">
        <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <label style="display: block; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-muted);">Buscar por Nombre</label>
                <input type="text" name="search" placeholder="Ej: España, Argentina..." value="<?= htmlspecialchars($search) ?>" class="form-control-admin">
            </div>

            <div style="flex: 1; min-width: 250px;">
                <label style="display: block; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-muted);">Filtrar por Continente</label>
                <select name="continente_id" onchange="this.form.submit()" class="form-control-admin">
                    <option value="">-- Todos los Continentes --</option>
                    <?php foreach ($continentes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $continente_filter == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn-admin" style="margin: 0; padding: 10px 20px;">Buscar</button>
                <?php if (!empty($search) || !empty($continente_filter)): ?>
                    <a href="paises.php" class="btn-admin" style="margin: 0; padding: 10px 20px; background: rgba(255,255,255,0.05); color: var(--text-muted);">Limpiar</a>
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
                    <th style="width: 60px;">Bandera</th>
                    <th>Nombre del País</th>
                    <th>ISO</th>
                    <th>Continente</th>
                    <th style="text-align: right; padding-right: 25px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paises as $p): ?>
                    <tr>
                        <td style="padding-left: 25px; font-weight: bold; color: var(--text-muted);">#<?= $p['id'] ?></td>
                        <td>
                            <?php if (!empty($p['bandera_url'])): ?>
                                <img src="<?= htmlspecialchars($p['bandera_url']) ?>" alt="Bandera"
                                    style="width: 32px; height: 20px; object-fit: cover; border-radius: 3px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                            <?php else: ?>
                                <div style="width: 32px; height: 20px; background: rgba(255,255,255,0.05); border-radius: 3px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem;">🏳️</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight: 700; font-size: 1.1rem; color: #fff;">
                                <?= htmlspecialchars($p['nombre']) ?>
                            </div>
                        </td>
                        <td>
                            <span style="font-family: monospace; font-weight: bold; color: var(--text-muted);">
                                <?= htmlspecialchars($p['codigo_iso'] ?? '--') ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-continent">
                                <?= htmlspecialchars($p['continente_nombre']) ?>
                            </span>
                        </td>
                        <td style="text-align: right; padding-right: 25px;">
                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 12px;">
                                <a href="pais_form.php?id=<?= $p['id'] ?>" class="btn-admin" style="margin: 0; padding: 6px 15px; font-size: 0.85rem; background: rgba(255,255,255,0.05); color: var(--text-muted);">Editar</a>
                                <a href="paises.php?delete=<?= $p['id'] ?>"
                                    onclick="return confirm('¿Seguro que quieres eliminar este país?')"
                                    style="color: #ef4444; text-decoration: none; font-size: 1.2rem; line-height: 1; padding: 5px;" title="Eliminar">&times;</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($paises)): ?>
                    <tr>
                        <td colspan="6" style="padding: 50px; text-align: center; color: var(--text-muted);">
                            <div style="font-size: 3rem; margin-bottom: 10px;">🏳️</div>
                            No se encontraron países que coincidan con la búsqueda.
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
                if (!empty($search)) $queryParams['search'] = $search;
                if (!empty($continente_filter)) $queryParams['continente_id'] = $continente_filter;
                $queryString = http_build_query($queryParams);
                $prefix = !empty($queryString) ? "&" . $queryString : "";
                ?>

                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $prefix ?>" class="page-link">&larr; Anterior</a>
                <?php endif; ?>

                <span style="padding: 8px 15px; color: var(--text-muted);">
                    Página <strong><?= $page ?></strong> de <?= $totalPages ?>
                </span>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $prefix ?>" class="page-link">Siguiente &rarr;</a>
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



<?php include '../includes/footer.php'; ?>
