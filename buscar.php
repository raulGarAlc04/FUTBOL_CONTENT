<?php
require_once 'db.php';
include 'includes/header.php';

$q = $_GET['q'] ?? '';
$q = trim($q);

$equipos = [];
$jugadores = [];
$competiciones = [];

if ($q !== '') {
    $searchTerm = '%' . $q . '%';

    // Buscar Equipos
    $stmt = $pdo->prepare("SELECT * FROM equipo WHERE nombre LIKE ? ORDER BY nombre ASC LIMIT 10");
    $stmt->execute([$searchTerm]);
    $equipos = $stmt->fetchAll();

    // Buscar Jugadores
    $stmt = $pdo->prepare("SELECT j.*, e.nombre as equipo_nombre FROM jugadores j LEFT JOIN equipo e ON j.equipo_actual_id = e.id WHERE j.nombre LIKE ? ORDER BY j.nombre ASC LIMIT 10");
    $stmt->execute([$searchTerm]);
    $jugadores = $stmt->fetchAll();

    // Buscar Competiciones
    $stmt = $pdo->prepare("SELECT * FROM competicion WHERE nombre LIKE ? ORDER BY nombre ASC LIMIT 10");
    $stmt->execute([$searchTerm]);
    $competiciones = $stmt->fetchAll();
}
?>

<div class="container" style="margin-top: 40px; margin-bottom: 60px;">
    <h1>Resultados de búsqueda: "
        <?= htmlspecialchars($q) ?>"
    </h1>

    <?php if ($q === ''): ?>
        <p style="color: var(--text-muted); margin-top: 20px;">Por favor, introduce un término de búsqueda en la página de
            inicio.</p>
    <?php elseif (empty($equipos) && empty($jugadores) && empty($competiciones)): ?>
        <p style="color: var(--text-muted); margin-top: 20px;">No se encontraron resultados para "
            <?= htmlspecialchars($q) ?>".
        </p>
    <?php else: ?>

        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 40px; margin-top: 40px;">

            <!-- Resultados Equipos -->
            <?php if (!empty($equipos)): ?>
                <div>
                    <h2 style="border-bottom: 2px solid var(--accent-color); padding-bottom: 10px; margin-bottom: 20px;">🛡️
                        Equipos</h2>
                    <div
                        style="background: var(--secondary-color); border-radius: 8px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05);">
                        <?php foreach ($equipos as $eq): ?>
                            <a href="equipo.php?id=<?= $eq['id'] ?>"
                                style="display: flex; align-items: center; gap: 15px; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); text-decoration: none; color: inherit; transition: background 0.2s;"
                                onmouseover="this.style.background='rgba(255,255,255,0.05)'"
                                onmouseout="this.style.background='transparent'">
                                <?php if (!empty($eq['escudo_url'])): ?>
                                    <img src="<?= htmlspecialchars($eq['escudo_url']) ?>"
                                        style="width: 30px; height: 30px; object-fit: contain;">
                                <?php else: ?>
                                    <div
                                        style="width: 30px; height: 30px; background: rgba(255,255,255,0.1); border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                        🛡️</div>
                                <?php endif; ?>
                                <div style="font-weight: bold;">
                                    <?= htmlspecialchars($eq['nombre']) ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Resultados Jugadores -->
            <?php if (!empty($jugadores)): ?>
                <div>
                    <h2 style="border-bottom: 2px solid var(--accent-color); padding-bottom: 10px; margin-bottom: 20px;">🏃
                        Jugadores</h2>
                    <div
                        style="background: var(--secondary-color); border-radius: 8px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05);">
                        <?php foreach ($jugadores as $jug): ?>
                            <div
                                style="display: flex; align-items: center; gap: 15px; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <?php if (!empty($jug['foto_url'])): ?>
                                    <img src="<?= htmlspecialchars($jug['foto_url']) ?>"
                                        style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <div
                                        style="width: 35px; height: 35px; border-radius: 50%; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center;">
                                        👤</div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight: bold;">
                                        <?= htmlspecialchars($jug['nombre']) ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">
                                        <?= htmlspecialchars($jug['equipo_nombre'] ?? 'Sin equipo') ?> •
                                        <?= htmlspecialchars($jug['posicion']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Resultados Competiciones -->
            <?php if (!empty($competiciones)): ?>
                <div>
                    <h2 style="border-bottom: 2px solid var(--accent-color); padding-bottom: 10px; margin-bottom: 20px;">🏆
                        Competiciones</h2>
                    <div
                        style="background: var(--secondary-color); border-radius: 8px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05);">
                        <?php foreach ($competiciones as $comp): ?>
                            <a href="competicion.php?id=<?= $comp['id'] ?>"
                                style="display: flex; align-items: center; gap: 15px; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); text-decoration: none; color: inherit; transition: background 0.2s;"
                                onmouseover="this.style.background='rgba(255,255,255,0.05)'"
                                onmouseout="this.style.background='transparent'">
                                <?php if (!empty($comp['logo_url'])): ?>
                                    <img src="<?= htmlspecialchars($comp['logo_url']) ?>"
                                        style="width: 30px; height: 30px; object-fit: contain;">
                                <?php else: ?>
                                    <div
                                        style="width: 30px; height: 30px; background: rgba(255,255,255,0.1); border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                        🏆</div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight: bold;">
                                        <?= htmlspecialchars($comp['nombre']) ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">Temporada
                                        <?= htmlspecialchars($comp['temporada_actual']) ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>