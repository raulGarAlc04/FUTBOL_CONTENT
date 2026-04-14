<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/paths.php';

try {
    $stmt = $pdo->query("SELECT * FROM continente ORDER BY nombre ASC");
    $continentes_menu = $stmt->fetchAll();
} catch (PDOException $e) {
    $continentes_menu = [];
}

$isAdminObj = strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' · ' : '' ?>Futbol Data</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(fc_url('/css/style.css')) ?>">
</head>

<body>

    <header class="site-header">
        <div class="header-container">
            <a href="<?= htmlspecialchars(fc_url('/index.php')) ?>" class="logo-area" aria-label="Inicio Futbol Data">
                <span class="logo-mark" aria-hidden="true"></span>
                <span class="logo-text">Futbol Data</span>
            </a>

            <button type="button" class="nav-toggle" data-nav-toggle aria-expanded="false" aria-controls="site-nav-drawer">
                <span class="nav-toggle-bars" aria-hidden="true"></span>
                <span class="visually-hidden">Menú</span>
            </button>

            <div class="nav-overlay" data-nav-panel aria-hidden="true"></div>
            <nav class="site-nav" id="site-nav-drawer" data-nav-panel aria-label="Principal">
                <ul class="nav-list">
                    <li><a href="<?= htmlspecialchars(fc_url('/index.php')) ?>">Inicio</a></li>
                    <li><a href="<?= htmlspecialchars(fc_url('/ligas.php')) ?>">Ligas</a></li>
                    <?php foreach ($continentes_menu as $cont): ?>
                        <li>
                            <a href="<?= htmlspecialchars(fc_url('/continente.php?id=' . (int) $cont['id'])) ?>">
                                <?= htmlspecialchars($cont['nombre']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <form class="nav-search" action="<?= htmlspecialchars(fc_url('/buscar.php')) ?>" method="GET" role="search">
                    <label class="visually-hidden" for="nav-search-q">Buscar</label>
                    <input id="nav-search-q" type="search" name="q" placeholder="Buscar…" autocomplete="off" maxlength="120">
                    <button type="submit" class="nav-search-btn" aria-label="Buscar">⌕</button>
                </form>
            </nav>

            <?php if ($isAdminObj): ?>
                <a href="<?= htmlspecialchars(fc_url('/index.php')) ?>" class="btn-admin btn-admin--ghost">Web</a>
            <?php else: ?>
                <a href="<?= htmlspecialchars(fc_url('/admin/index.php')) ?>" class="btn-admin">Admin</a>
            <?php endif; ?>
        </div>
    </header>

    <?php $mainFullWidth = isset($page_full_width) && $page_full_width; ?>
    <main class="<?= $mainFullWidth ? 'main-full' : '' ?>">
