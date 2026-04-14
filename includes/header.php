<?php
// Incluir conexión a base de datos si no está incluida
require_once __DIR__ . '/../db.php';

// Obtener continentes para el menú
try {
    $stmt = $pdo->query("SELECT * FROM continente ORDER BY nombre ASC");
    $continentes_menu = $stmt->fetchAll();
} catch (PDOException $e) {
    $continentes_menu = [];
    // En producción loguearíamos el error
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Futbol Data 2026</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Main CSS -->
    <link rel="stylesheet" href="/php/FUTBOL_CONTENT/css/style.css">
</head>

<body>

    <header>
        <div class="header-container">
            <div class="logo-area">
                <div class="logo-placeholder">⚽</div> <!-- Placeholder temporal -->
                <span>Futbol Data</span>
            </div>

            <nav>
                <ul style="display: flex; align-items: center; gap: 20px;">
                    <?php foreach ($continentes_menu as $cont): ?>
                        <li>
                            <a href="/php/FUTBOL_CONTENT/continente.php?id=<?= $cont['id'] ?>">
                                <?= htmlspecialchars($cont['nombre']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    
                    <!-- Search Mini Form -->
                    <li style="margin-left: 10px;">
                        <form action="/php/FUTBOL_CONTENT/buscar.php" method="GET" style="display: flex; align-items: center; background: rgba(255,255,255,0.05); border-radius: 20px; padding: 5px 15px; border: 1px solid rgba(255,255,255,0.1);">
                            <input type="text" name="q" placeholder="Buscar..." style="background: none; border: none; color: white; outline: none; font-size: 0.85rem; width: 120px;">
                            <button type="submit" style="background: none; border: none; cursor: pointer; color: var(--accent-color); font-size: 0.9rem;">🔍</button>
                        </form>
                    </li>
                </ul>
            </nav>

            <?php
            // Detectar si estamos en el panel de administración
            $isAdminObj = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
            if ($isAdminObj):
                ?>
                <a href="/php/FUTBOL_CONTENT/index.php" class="btn-admin"
                    style="background-color: var(--secondary-color); border: 1px solid var(--text-muted);">Volver a la
                    Web</a>
            <?php else: ?>
                <a href="/php/FUTBOL_CONTENT/admin/index.php" class="btn-admin">Panel Admin</a>
            <?php endif; ?>
        </div>
    </header>

    <?php $mainFullWidth = isset($page_full_width) && $page_full_width; ?>
    <main class="<?= $mainFullWidth ? 'main-full' : '' ?>">
