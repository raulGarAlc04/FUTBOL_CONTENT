<?php
declare(strict_types=1);

/**
 * HTTP JSON y upsert de partidos para importadores (TheSportsDB, API-Football, football-data).
 */
require_once __DIR__ . '/partidos_service.php';

function import_apis_http_get_json(string $url, array $headers = []): ?array
{
    $lines = [];
    foreach ($headers as $k => $v) {
        $lines[] = $k . ': ' . $v;
    }
    $headerStr = $lines === [] ? '' : implode("\r\n", $lines) . "\r\n";
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => $headerStr . "Accept: application/json\r\n",
            'timeout' => 60,
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

function import_apis_fd_get(string $url, string $token): ?array
{
    return import_apis_http_get_json($url, ['X-Auth-Token' => $token]);
}

/**
 * @return array{inserted:int,updated:int,skipped:int,log:string[]}
 */
function import_apis_upsert_loop(
    PDO $pdo,
    int $competicion_id,
    iterable $rows,
    callable $mapRow
): array {
    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $log = [];

    foreach ($rows as $row) {
        $mapped = $mapRow($row);
        if ($mapped === null) {
            $skipped++;
            continue;
        }
        [
            'api_match_id' => $apiId,
            'home_name' => $homeName,
            'away_name' => $awayName,
            'goles_local' => $gl,
            'goles_visitante' => $gv,
            'estado' => $estado,
            'fase' => $fase,
            'jornada' => $jornada,
            'grupo_letra' => $grupo,
            'es_eliminatoria' => $esEl,
            'orden_fase' => $orden,
            'fecha' => $fecha,
            'fuente' => $fuente,
        ] = $mapped;

        if ($apiId === '') {
            $skipped++;
            continue;
        }

        $hid = partidos_api_buscar_equipo($pdo, $competicion_id, $homeName);
        $aid = partidos_api_buscar_equipo($pdo, $competicion_id, $awayName);
        if ($hid === null || $aid === null) {
            $log[] = 'Sin emparejar: ' . $homeName . ' vs ' . $awayName;
            $skipped++;
            continue;
        }

        $chk = $pdo->prepare('SELECT id FROM partido WHERE competicion_id = ? AND api_match_id = ?');
        $chk->execute([$competicion_id, $apiId]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $pdo->prepare(
                'UPDATE partido SET equipo_local_id=?, equipo_visitante_id=?, fase=?, jornada=?, grupo_letra=?, es_eliminatoria=?, orden_fase=?, goles_local=?, goles_visitante=?, fecha=?, estado=?, api_fuente=?
                 WHERE id=? AND competicion_id=?'
            )->execute([$hid, $aid, $fase, $jornada, $grupo, $esEl, $orden, $gl, $gv, $fecha, $estado, $fuente, (int) $existing['id'], $competicion_id]);
            $updated++;
        } else {
            $pdo->prepare(
                'INSERT INTO partido (competicion_id, fase, jornada, grupo_letra, es_eliminatoria, orden_fase, equipo_local_id, equipo_visitante_id, goles_local, goles_visitante, fecha, estado, api_match_id, api_fuente)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([$competicion_id, $fase, $jornada, $grupo, $esEl, $orden, $hid, $aid, $gl, $gv, $fecha, $estado, $apiId, $fuente]);
            $inserted++;
        }
    }

    return ['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped, 'log' => $log];
}

function import_tsdb_is_finished(array $ev): bool
{
    $s = strtolower((string) ($ev['strStatus'] ?? ''));
    return strpos($s, 'finished') !== false
        || strpos($s, 'after full time') !== false
        || strpos($s, 'awarded') !== false;
}

function import_tsdb_parse_round(?string $round): ?int
{
    if ($round === null || $round === '') {
        return null;
    }
    if (preg_match('/(\d+)\s*$/', $round, $m)) {
        return (int) $m[1];
    }
    return null;
}

/**
 * @return array<string, mixed>|null
 */
function import_tsdb_map_event(array $ev): ?array
{
    $idEvent = (string) ($ev['idEvent'] ?? '');
    if ($idEvent === '') {
        return null;
    }
    $homeName = (string) ($ev['strHomeTeam'] ?? '');
    $awayName = (string) ($ev['strAwayTeam'] ?? '');
    if ($homeName === '' || $awayName === '') {
        return null;
    }
    $finished = import_tsdb_is_finished($ev);
    $gl = null;
    $gv = null;
    if ($finished) {
        if (isset($ev['intHomeScore']) && $ev['intHomeScore'] !== '' && $ev['intHomeScore'] !== null) {
            $gl = (int) $ev['intHomeScore'];
        }
        if (isset($ev['intAwayScore']) && $ev['intAwayScore'] !== '' && $ev['intAwayScore'] !== null) {
            $gv = (int) $ev['intAwayScore'];
        }
    }
    $estado = $finished ? 'finalizado' : 'programado';
    $jornada = isset($ev['intRound']) && $ev['intRound'] !== '' && $ev['intRound'] !== null ? (int) $ev['intRound'] : null;
    $grupo = isset($ev['strGroup']) && $ev['strGroup'] !== '' && $ev['strGroup'] !== null
        ? strtoupper(substr(trim((string) $ev['strGroup']), 0, 1)) : null;
    $esEl = 0;
    $orden = 0;
    $fase = (string) ($ev['strLeague'] ?? 'Liga');
    $fecha = !empty($ev['dateEvent']) ? substr((string) $ev['dateEvent'], 0, 10) : null;

    return [
        'api_match_id' => 'tsdb-' . $idEvent,
        'home_name' => $homeName,
        'away_name' => $awayName,
        'goles_local' => $gl,
        'goles_visitante' => $gv,
        'estado' => $estado,
        'fase' => $fase !== '' ? $fase : 'Liga',
        'jornada' => $jornada,
        'grupo_letra' => $grupo,
        'es_eliminatoria' => $esEl,
        'orden_fase' => $orden,
        'fecha' => $fecha,
        'fuente' => 'thesportsdb',
    ];
}

function import_tsdb_fetch_events(string $apiKey, int $leagueId, string $season): ?array
{
    $key = trim($apiKey) !== '' ? trim($apiKey) : '123';
    $url = 'https://www.thesportsdb.com/api/v1/json/' . rawurlencode($key) . '/eventsseason.php?id=' . $leagueId . '&s=' . rawurlencode($season);
    $json = import_apis_http_get_json($url);
    if ($json === null) {
        return null;
    }
    $events = $json['events'] ?? null;
    if (!is_array($events)) {
        return [];
    }
    return $events;
}

function import_apifb_status_final(string $short): bool
{
    $s = strtoupper($short);
    return in_array($s, ['FT', 'AET', 'PEN', 'AWD', 'WO'], true);
}

/**
 * @return array<string, mixed>|null
 */
function import_apifb_map_fixture(array $fix): ?array
{
    $fid = $fix['fixture']['id'] ?? null;
    if ($fid === null) {
        return null;
    }
    $apiId = 'afb-' . (string) $fid;
    $homeName = (string) ($fix['teams']['home']['name'] ?? '');
    $awayName = (string) ($fix['teams']['away']['name'] ?? '');
    if ($homeName === '' || $awayName === '') {
        return null;
    }
    $short = (string) ($fix['fixture']['status']['short'] ?? '');
    $finished = import_apifb_status_final($short);
    $gl = null;
    $gv = null;
    if ($finished) {
        if (isset($fix['goals']['home']) && $fix['goals']['home'] !== null) {
            $gl = (int) $fix['goals']['home'];
        }
        if (isset($fix['goals']['away']) && $fix['goals']['away'] !== null) {
            $gv = (int) $fix['goals']['away'];
        }
    }
    $estado = $finished ? 'finalizado' : 'programado';
    $round = (string) ($fix['league']['round'] ?? '');
    $jornada = import_tsdb_parse_round($round);
    $grupo = null;
    if (preg_match('/Group\s+([A-Z])/i', $round, $m)) {
        $grupo = strtoupper($m[1]);
    }
    $esEl = (stripos($round, 'regular') === false && $round !== '' && $grupo === null) ? 1 : 0;
    if (stripos($round, 'regular') !== false) {
        $esEl = 0;
    }
    $orden = 0;
    $fase = (string) ($fix['league']['name'] ?? 'Liga');
    $fecha = !empty($fix['fixture']['date']) ? substr((string) $fix['fixture']['date'], 0, 10) : null;

    return [
        'api_match_id' => $apiId,
        'home_name' => $homeName,
        'away_name' => $awayName,
        'goles_local' => $gl,
        'goles_visitante' => $gv,
        'estado' => $estado,
        'fase' => $fase !== '' ? $fase : 'Liga',
        'jornada' => $jornada,
        'grupo_letra' => $grupo,
        'es_eliminatoria' => $esEl,
        'orden_fase' => $orden,
        'fecha' => $fecha,
        'fuente' => 'api-football',
    ];
}

function import_apifb_fetch_fixtures(string $apiKey, int $leagueId, int $seasonYear): ?array
{
    $url = 'https://v3.football.api-sports.io/fixtures?league=' . $leagueId . '&season=' . $seasonYear;
    $json = import_apis_http_get_json($url, ['x-apisports-key' => $apiKey]);
    if ($json === null) {
        return null;
    }
    if (!empty($json['errors'])) {
        return null;
    }
    if (!isset($json['response']) || !is_array($json['response'])) {
        return null;
    }
    return $json['response'];
}

/**
 * football-data.org (mismo esquema que antes: api_match_id = id numérico API).
 *
 * @return array<string, mixed>|null
 */
function import_fd_map_match(array $m): ?array
{
    $apiId = isset($m['id']) ? (string) $m['id'] : '';
    if ($apiId === '') {
        return null;
    }
    $homeName = (string) ($m['homeTeam']['name'] ?? '');
    $awayName = (string) ($m['awayTeam']['name'] ?? '');
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

    return [
        'api_match_id' => $apiId,
        'home_name' => $homeName,
        'away_name' => $awayName,
        'goles_local' => $gl,
        'goles_visitante' => $gv,
        'estado' => $estado,
        'fase' => $fase,
        'jornada' => $matchday,
        'grupo_letra' => null,
        'es_eliminatoria' => $esEl,
        'orden_fase' => $orden,
        'fecha' => $fecha,
        'fuente' => 'football-data',
    ];
}
