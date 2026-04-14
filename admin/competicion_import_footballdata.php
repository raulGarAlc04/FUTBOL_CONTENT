<?php
/**
 * Importación de partidos desde football-data.org (requiere token gratuito).
 * Documentación: https://www.football-data.org/documentation/quickstart
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/partidos_service.php';

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
$log = []; // avisos de importación (emparejamientos)

function partidos_fd_http_get(string $url, string $token): ?array
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "X-Auth-Token: {$token}\r\nAccept: application/json\r\n",
            'timeout' => 45,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    $log = [];
    $token = trim((string) ($_POST['token'] ?? ''));
    if ($token === '' && defined('FOOTBALL_DATA_TOKEN')) {
        $token = (string) FOOTBALL_DATA_TOKEN;
    }
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['code'] ?? '')));
    $season = (int) ($_POST['season'] ?? 0);
    if ($token === '') {
        $err = 'Indica el token X-Auth-Token o define FOOTBALL_DATA_TOKEN en db.php.';
    } elseif ($code === '' || $season < 1990 || $season > 2100) {
        $err = 'Código de competición (ej. PD, CL) y temporada (año inicio, ej. 2024) obligatorios.';
    } else {
        $url = 'https://api.football-data.org/v4/competitions/' . rawurlencode($code) . '/matches?season=' . $season;
        $json = partidos_fd_http_get($url, $token);
        if ($json === null) {
            $err = 'No se pudo conectar con la API.';
        } elseif (!isset($json['matches']) || !is_array($json['matches'])) {
            $err = 'Respuesta inesperada de la API (¿código o temporada incorrectos?).';
        } else {
            try {
                $pdo->prepare('UPDATE competicion SET football_data_code = ?, football_data_season = ? WHERE id = ?')->execute([$code, $season, $competicion_id]);
            } catch (PDOException $e) {
                // columnas opcionales no migradas
            }

            $inserted = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($json['matches'] as $m) {
                $apiId = isset($m['id']) ? (string) $m['id'] : '';
                if ($apiId === '') {
                    $skipped++;
                    continue;
                }
                $homeName = (string) ($m['homeTeam']['name'] ?? '');
                $awayName = (string) ($m['awayTeam']['name'] ?? '');
                $hid = partidos_api_buscar_equipo($pdo, $competicion_id, $homeName);
                $aid = partidos_api_buscar_equipo($pdo, $competicion_id, $awayName);
                if ($hid === null || $aid === null) {
                    $log[] = 'Sin emparejar: ' . $homeName . ' vs ' . $awayName;
                    $skipped++;
                    continue;
                }

                $status = (string) ($m['status'] ?? '');
                $ft = $m['score']['fullTime'] ?? null;
                $gl = is_array($ft) && array_key_exists('home', $ft) && $ft['home'] !== null ? (int) $ft['home'] : null;
                $gv = is_array($ft) && array_key_exists('away', $ft) && $ft['away'] !== null ? (int) $ft['away'] : null;
                $estado = ($status === 'FINISHED' || $status === 'AWARDED') ? 'finalizado' : 'programado';
                if ($estado !== 'finalizado') {
                    $gl = null;
                    $gv = null;
                }

                $matchday = isset($m['matchday']) && $m['matchday'] !== null ? (int) $m['matchday'] : null;
                $fase = 'Liga';
                $esEl = 0;
                $orden = 0;
                $stage = (string) ($m['stage'] ?? '');
                if ($stage !== '' && strtoupper($stage) !== 'REGULAR_SEASON') {
                    $fase = $stage;
                    $esEl = 1;
                    $orden = $matchday ?? 0;
                }

                $fecha = null;
                if (!empty($m['utcDate'])) {
                    $fecha = substr((string) $m['utcDate'], 0, 10);
                }

                $chk = $pdo->prepare('SELECT id FROM partido WHERE competicion_id = ? AND api_match_id = ?');
                $chk->execute([$competicion_id, $apiId]);
                $existing = $chk->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $pdo->prepare(
                        'UPDATE partido SET equipo_local_id=?, equipo_visitante_id=?, fase=?, jornada=?, es_eliminatoria=?, orden_fase=?, goles_local=?, goles_visitante=?, fecha=?, estado=?, api_fuente=?
                         WHERE id=? AND competicion_id=?'
                    )->execute([$hid, $aid, $fase, $matchday, $esEl, $orden, $gl, $gv, $fecha, $estado, 'football-data', (int) $existing['id'], $competicion_id]);
                    $updated++;
                } else {
                    $pdo->prepare(
                        'INSERT INTO partido (competicion_id, fase, jornada, grupo_letra, es_eliminatoria, orden_fase, equipo_local_id, equipo_visitante_id, goles_local, goles_visitante, fecha, estado, api_match_id, api_fuente)
                         VALUES (?,?,NULL,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute([$competicion_id, $fase, $matchday, $esEl, $orden, $hid, $aid, $gl, $gv, $fecha, $estado, $apiId, 'football-data']);
                    $inserted++;
                }
            }

            partidos_despues_de_cambio($pdo, $competicion_id);
            $msg = "Importación lista: {$inserted} nuevos, {$updated} actualizados, {$skipped} omitidos.";
        }
    }
}

$page_full_width = true;
include '../includes/header.php';

$defCode = $comp['football_data_code'] ?? '';
$defSeason = $comp['football_data_season'] ?? '';
?>

<div class="container page-shell" style="max-width: 720px;">
    <h1 style="margin-bottom:8px;">Importar partidos (API)</h1>
    <p style="color:var(--text-muted); margin-bottom:22px;">
        Usa tu token de <a href="https://www.football-data.org/client/register" target="_blank" rel="noopener">football-data.org</a>.
        Códigos de competición: <strong>PD</strong> La Liga, <strong>PL</strong> Premier, <strong>BL1</strong> Bundesliga, <strong>SA</strong> Serie A, <strong>FL1</strong> Ligue 1, <strong>CL</strong> Champions…
    </p>

    <?php if ($msg): ?>
        <div class="admin-card" style="padding:14px 18px; margin-bottom:16px; border-color:rgba(34,197,94,0.35);"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="admin-card" style="padding:14px 18px; margin-bottom:16px; border-color:rgba(239,68,68,0.4);"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="post" class="admin-card" style="padding:22px;">
        <input type="hidden" name="action" value="import">
        <div style="margin-bottom:14px;">
            <label class="form-label">Token (no se guarda en servidor si lo pegas aquí)</label>
            <input class="form-control-admin" type="password" name="token" autocomplete="off" placeholder="Pegar X-Auth-Token">
        </div>
        <div style="margin-bottom:14px;">
            <label class="form-label">Código competición</label>
            <input class="form-control-admin" type="text" name="code" value="<?= htmlspecialchars((string) $defCode) ?>" placeholder="PD" maxlength="16">
        </div>
        <div style="margin-bottom:14px;">
            <label class="form-label">Temporada (año de inicio)</label>
            <input class="form-control-admin" type="number" name="season" value="<?= $defSeason !== '' && $defSeason !== null ? (int) $defSeason : 2024 ?>" min="1990" max="2100">
        </div>
        <p style="font-size:0.88rem; color:var(--text-muted); margin-bottom:16px;">
            Los equipos se emparejan por nombre con los inscritos en esta competición. Si no coinciden, revisa nombres en la base o importa a mano desde <a href="competicion_partidos.php?id=<?= $competicion_id ?>">Resultados</a>.
        </p>
        <button type="submit" class="btn-admin" style="margin:0; border:none; cursor:pointer;">Importar / actualizar</button>
        <a href="competicion_partidos.php?id=<?= $competicion_id ?>" class="btn-admin btn-admin--ghost" style="margin-left:10px;">Volver a partidos</a>
    </form>

    <?php if ($log): ?>
        <div class="admin-card" style="padding:16px; margin-top:20px;">
            <strong>Avisos</strong>
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
