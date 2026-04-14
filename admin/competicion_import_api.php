<?php
/**
 * Importar partidos desde varias APIs:
 * - TheSportsDB: muchas ligas, clave pública de prueba "123".
 * - API-Football (api-sports.io): plan gratuito ~100 req/día, listado amplio de ligas.
 * - football-data.org: plan gratuito limitado a varias competiciones.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/import_apis_partidos.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID no válido.');
}
$competicion_id = (int) $_GET['id'];

$stmt = $pdo->prepare('SELECT c.*, tc.nombre AS tipo_nombre FROM competicion c JOIN tipo_competicion tc ON tc.id = c.tipo_competicion_id WHERE c.id = ?');
$stmt->execute([$competicion_id]);
$comp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$comp) {
    die('Competición no encontrada.');
}

$msg = '';
$err = '';
$log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'import_tsdb') {
        $log = [];
        $apiKey = trim((string) ($_POST['tsdb_key'] ?? ''));
        $leagueId = (int) ($_POST['tsdb_league_id'] ?? 0);
        $season = trim((string) ($_POST['tsdb_season'] ?? ''));
        if ($leagueId <= 0 || $season === '') {
            $err = 'Indica ID de liga TheSportsDB y temporada (ej. 2024-2025).';
        } else {
            $events = import_tsdb_fetch_events($apiKey, $leagueId, $season);
            if ($events === null) {
                $err = 'No se pudo leer TheSportsDB (¿ID liga o temporada incorrectos, o límite de peticiones?).';
            } else {
                try {
                    $pdo->prepare('UPDATE competicion SET thesportsdb_league_id = ?, thesportsdb_season = ? WHERE id = ?')->execute([
                        (string) $leagueId, $season, $competicion_id,
                    ]);
                } catch (PDOException $e) {
                }
                $r = import_apis_upsert_loop($pdo, $competicion_id, $events, static function ($ev) {
                    return import_tsdb_map_event($ev);
                });
                $log = $r['log'];
                partidos_despues_de_cambio($pdo, $competicion_id);
                $msg = 'TheSportsDB: ' . $r['inserted'] . ' nuevos, ' . $r['updated'] . ' actualizados, ' . $r['skipped'] . ' omitidos.';
            }
        }
    } elseif ($action === 'import_apifb') {
        $log = [];
        $apiKey = trim((string) ($_POST['apifb_key'] ?? ''));
        if ($apiKey === '' && defined('API_FOOTBALL_KEY')) {
            $apiKey = (string) API_FOOTBALL_KEY;
        }
        $leagueId = (int) ($_POST['apifb_league_id'] ?? 0);
        $season = (int) ($_POST['apifb_season'] ?? 0);
        if ($apiKey === '') {
            $err = 'Indica la clave x-apisports-key (dashboard de api-football.com) o define API_FOOTBALL_KEY en db.php.';
        } elseif ($leagueId <= 0 || $season < 1990 || $season > 2100) {
            $err = 'ID de liga API-Football (número) y temporada (año, ej. 2024) obligatorios.';
        } else {
            $fixtures = import_apifb_fetch_fixtures($apiKey, $leagueId, $season);
            if ($fixtures === null) {
                $err = 'API-Football no respondió o rechazó la petición (clave, límite diario o parámetros).';
            } else {
                try {
                    $pdo->prepare('UPDATE competicion SET api_football_league_id = ?, api_football_season = ? WHERE id = ?')->execute([$leagueId, $season, $competicion_id]);
                } catch (PDOException $e) {
                }
                $r = import_apis_upsert_loop($pdo, $competicion_id, $fixtures, static function ($fix) {
                    return import_apifb_map_fixture($fix);
                });
                $log = $r['log'];
                partidos_despues_de_cambio($pdo, $competicion_id);
                $msg = 'API-Football: ' . $r['inserted'] . ' nuevos, ' . $r['updated'] . ' actualizados, ' . $r['skipped'] . ' omitidos.';
            }
        }
    } elseif ($action === 'import_fd') {
        $log = [];
        $token = trim((string) ($_POST['fd_token'] ?? ''));
        if ($token === '' && defined('FOOTBALL_DATA_TOKEN')) {
            $token = (string) FOOTBALL_DATA_TOKEN;
        }
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['fd_code'] ?? '')));
        $season = (int) ($_POST['fd_season'] ?? 0);
        if ($token === '') {
            $err = 'Indica el token de football-data.org o define FOOTBALL_DATA_TOKEN en db.php.';
        } elseif ($code === '' || $season < 1990 || $season > 2100) {
            $err = 'Código competición y temporada (año inicio) obligatorios.';
        } else {
            $url = 'https://api.football-data.org/v4/competitions/' . rawurlencode($code) . '/matches?season=' . $season;
            $json = import_apis_fd_get($url, $token);
            if ($json === null) {
                $err = 'No se pudo conectar con football-data.org.';
            } elseif (!isset($json['matches']) || !is_array($json['matches'])) {
                $err = 'Respuesta no válida (¿competición no incluida en tu plan gratuito?).';
            } else {
                try {
                    $pdo->prepare('UPDATE competicion SET football_data_code = ?, football_data_season = ? WHERE id = ?')->execute([$code, $season, $competicion_id]);
                } catch (PDOException $e) {
                }
                $r = import_apis_upsert_loop($pdo, $competicion_id, $json['matches'], static function ($m) {
                    return import_fd_map_match($m);
                });
                $log = $r['log'];
                partidos_despues_de_cambio($pdo, $competicion_id);
                $msg = 'football-data.org: ' . $r['inserted'] . ' nuevos, ' . $r['updated'] . ' actualizados, ' . $r['skipped'] . ' omitidos.';
            }
        }
    }
}

$page_full_width = true;
include '../includes/header.php';

$defTsdbLeague = $comp['thesportsdb_league_id'] ?? '';
$defTsdbSeason = $comp['thesportsdb_season'] ?? '2024-2025';
$defAfbLeague = $comp['api_football_league_id'] ?? '';
$defAfbSeason = $comp['api_football_season'] ?? 2024;
$defFdCode = $comp['football_data_code'] ?? '';
$defFdSeason = $comp['football_data_season'] ?? 2024;
?>

<div class="container page-shell" style="max-width: 820px;">
    <h1 style="margin-bottom:8px;">Importar partidos (APIs)</h1>
    <p style="color:var(--text-muted); margin-bottom:20px;">
        Elige un proveedor. Los nombres de equipos deben coincidir con los inscritos en esta competición.
        <a href="competicion_partidos.php?id=<?= $competicion_id ?>">Volver a resultados</a>
    </p>

    <?php if ($msg): ?>
        <div class="admin-card" style="padding:14px 18px; margin-bottom:16px; border-color:rgba(34,197,94,0.35);"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="admin-card" style="padding:14px 18px; margin-bottom:16px; border-color:rgba(239,68,68,0.4);"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <div class="admin-card" style="padding:22px; margin-bottom:22px;">
        <h2 style="margin:0 0 12px; font-size:1.2rem;">1. TheSportsDB (recomendado: muchas ligas)</h2>
        <p style="color:var(--text-muted); font-size:0.92rem; margin-bottom:16px;">
            <a href="https://www.thesportsdb.com/api.php" target="_blank" rel="noopener">Documentación</a> ·
            Busca el <strong>idLeague</strong> en la ficha de la liga (URL o <a href="https://www.thesportsdb.com/leagues.php" target="_blank" rel="noopener">listado</a>).
            Temporada en formato <strong>2024-2025</strong>. Clave pública de prueba: <code>123</code> (o tu clave premium).
        </p>
        <form method="post">
            <input type="hidden" name="action" value="import_tsdb">
            <div style="margin-bottom:12px;">
                <label class="form-label">Clave API (vacío = 123)</label>
                <input class="form-control-admin" type="text" name="tsdb_key" autocomplete="off" placeholder="123">
            </div>
            <div style="margin-bottom:12px;">
                <label class="form-label">ID liga (número)</label>
                <input class="form-control-admin" type="number" name="tsdb_league_id" value="<?= htmlspecialchars((string) $defTsdbLeague) ?>" min="1" placeholder="4335 La Liga">
            </div>
            <div style="margin-bottom:16px;">
                <label class="form-label">Temporada</label>
                <input class="form-control-admin" type="text" name="tsdb_season" value="<?= htmlspecialchars((string) $defTsdbSeason) ?>" placeholder="2024-2025">
            </div>
            <button type="submit" class="btn-admin" style="margin:0; border:none; cursor:pointer;">Importar TheSportsDB</button>
        </form>
    </div>

    <div class="admin-card" style="padding:22px; margin-bottom:22px;">
        <h2 style="margin:0 0 12px; font-size:1.2rem;">2. API-Football (api-sports.io)</h2>
        <p style="color:var(--text-muted); font-size:0.92rem; margin-bottom:16px;">
            Registro en <a href="https://www.api-football.com/" target="_blank" rel="noopener">api-football.com</a> · plan gratuito (~100 peticiones/día) con
            <a href="https://www.api-football.com/coverage" target="_blank" rel="noopener">muchas ligas</a>.
            ID de liga (ej. La Liga <strong>140</strong>) y temporada como año único <strong>2024</strong>.
        </p>
        <form method="post">
            <input type="hidden" name="action" value="import_apifb">
            <div style="margin-bottom:12px;">
                <label class="form-label">x-apisports-key</label>
                <input class="form-control-admin" type="password" name="apifb_key" autocomplete="off" placeholder="Clave del panel">
            </div>
            <div style="margin-bottom:12px;">
                <label class="form-label">ID liga</label>
                <input class="form-control-admin" type="number" name="apifb_league_id" value="<?= $defAfbLeague !== '' && $defAfbLeague !== null ? (int) $defAfbLeague : '' ?>" min="1" placeholder="140">
            </div>
            <div style="margin-bottom:16px;">
                <label class="form-label">Temporada (año)</label>
                <input class="form-control-admin" type="number" name="apifb_season" value="<?= (int) $defAfbSeason ?>" min="1990" max="2100">
            </div>
            <button type="submit" class="btn-admin" style="margin:0; border:none; cursor:pointer;">Importar API-Football</button>
        </form>
    </div>

    <div class="admin-card" style="padding:22px; margin-bottom:22px;">
        <h2 style="margin:0 0 12px; font-size:1.2rem;">3. football-data.org</h2>
        <p style="color:var(--text-muted); font-size:0.92rem; margin-bottom:16px;">
            Plan gratuito limitado a un subconjunto de competiciones (≈12). Útil si ya tienes token.
        </p>
        <form method="post">
            <input type="hidden" name="action" value="import_fd">
            <div style="margin-bottom:12px;">
                <label class="form-label">Token</label>
                <input class="form-control-admin" type="password" name="fd_token" autocomplete="off">
            </div>
            <div style="margin-bottom:12px;">
                <label class="form-label">Código (PD, PL, CL…)</label>
                <input class="form-control-admin" type="text" name="fd_code" value="<?= htmlspecialchars((string) $defFdCode) ?>" maxlength="16">
            </div>
            <div style="margin-bottom:16px;">
                <label class="form-label">Temporada (año inicio)</label>
                <input class="form-control-admin" type="number" name="fd_season" value="<?= (int) $defFdSeason ?>" min="1990" max="2100">
            </div>
            <button type="submit" class="btn-admin" style="margin:0; border:none; cursor:pointer;">Importar football-data</button>
        </form>
    </div>

    <?php if ($log): ?>
        <div class="admin-card" style="padding:16px;">
            <strong>Avisos (emparejamiento)</strong>
            <ul style="margin:8px 0 0 18px; color:var(--text-muted); font-size:0.9rem;">
                <?php foreach ($log as $line): ?>
                    <li><?= htmlspecialchars($line) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<style>.form-label { display:block; font-size:0.78rem; font-weight:700; color:var(--text-muted); margin-bottom:6px; }</style>

<?php include '../includes/footer.php'; ?>
