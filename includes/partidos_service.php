<?php
declare(strict_types=1);

/**
 * Resultados de partidos, recálculo de clasificación (liga/grupos) y eliminaciones en KO.
 */

function partidos_tipos_nombre(PDO $pdo, int $competicionId): ?string
{
    $s = $pdo->prepare('SELECT tc.nombre FROM competicion c JOIN tipo_competicion tc ON tc.id = c.tipo_competicion_id WHERE c.id = ?');
    $s->execute([$competicionId]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    return $r ? (string) $r['nombre'] : null;
}

function partidos_hay_eliminatoria_en_tipo(?string $tipo): bool
{
    return $tipo === 'Eliminatoria' || $tipo === 'Fase de grupos y eliminatoria';
}

function partidos_resolver_ganador(array $p): ?int
{
    if (($p['estado'] ?? '') !== 'finalizado') {
        return null;
    }
    if ($p['goles_local'] === null || $p['goles_local'] === '' || $p['goles_visitante'] === null || $p['goles_visitante'] === '') {
        return null;
    }
    $gl = (int) $p['goles_local'];
    $gv = (int) $p['goles_visitante'];
    if ($gl > $gv) {
        return (int) $p['equipo_local_id'];
    }
    if ($gv > $gl) {
        return (int) $p['equipo_visitante_id'];
    }
    $pl = $p['penales_local'] !== null && $p['penales_local'] !== '' ? (int) $p['penales_local'] : null;
    $pv = $p['penales_visitante'] !== null && $p['penales_visitante'] !== '' ? (int) $p['penales_visitante'] : null;
    if ($pl !== null && $pv !== null && $pl !== $pv) {
        return $pl > $pv ? (int) $p['equipo_local_id'] : (int) $p['equipo_visitante_id'];
    }
    return null;
}

function partidos_perdedor(array $p, int $ganadorId): ?int
{
    $el = (int) $p['equipo_local_id'];
    $ev = (int) $p['equipo_visitante_id'];
    if ($ganadorId === $el) {
        return $ev;
    }
    if ($ganadorId === $ev) {
        return $el;
    }
    return null;
}

function partidos_replay_eliminaciones_ko(PDO $pdo, int $competicionId): void
{
    $tipo = partidos_tipos_nombre($pdo, $competicionId);
    $pdo->prepare('UPDATE competicion_equipo SET eliminado = 0 WHERE competicion_id = ?')->execute([$competicionId]);

    if (!partidos_hay_eliminatoria_en_tipo($tipo)) {
        return;
    }

    $s = $pdo->prepare(
        "SELECT * FROM partido WHERE competicion_id = ? AND es_eliminatoria = 1 AND estado = 'finalizado'
         ORDER BY orden_fase ASC, jornada ASC, id ASC"
    );
    $s->execute([$competicionId]);
    while ($p = $s->fetch(PDO::FETCH_ASSOC)) {
        $gw = partidos_resolver_ganador($p);
        if ($gw === null) {
            continue;
        }
        $lose = partidos_perdedor($p, $gw);
        if ($lose !== null) {
            $u = $pdo->prepare('UPDATE competicion_equipo SET eliminado = 1 WHERE competicion_id = ? AND equipo_id = ?');
            $u->execute([$competicionId, $lose]);
        }
    }
}

function partidos_equipos_grupo(PDO $pdo, int $competicionId): array
{
    $s = $pdo->prepare('SELECT equipo_id, grupo_letra FROM competicion_equipo WHERE competicion_id = ?');
    $s->execute([$competicionId]);
    $map = [];
    while ($r = $s->fetch(PDO::FETCH_ASSOC)) {
        $g = $r['grupo_letra'];
        $map[(int) $r['equipo_id']] = $g ? strtoupper(substr(trim((string) $g), 0, 1)) : null;
    }
    return $map;
}

function partidos_grupo_coincide(?string $gPartido, ?string $gLocal, ?string $gVisit): bool
{
    if ($gPartido === null || $gPartido === '') {
        return true;
    }
    $gPartido = strtoupper(substr($gPartido, 0, 1));
    return $gLocal === $gPartido && $gVisit === $gPartido;
}

function partidos_recalcular_clasificacion_desde_partidos(PDO $pdo, int $competicionId): void
{
    $tipo = partidos_tipos_nombre($pdo, $competicionId);
    if ($tipo === 'Eliminatoria') {
        return;
    }

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM partido WHERE competicion_id = ? AND estado = 'finalizado' AND es_eliminatoria = 0");
    $cnt->execute([$competicionId]);
    if ((int) $cnt->fetchColumn() === 0) {
        return;
    }

    $gq = $pdo->prepare(
        'SELECT e.id FROM equipo e
         JOIN competicion_equipo ce ON ce.equipo_id = e.id
         WHERE ce.competicion_id = ? AND COALESCE(ce.eliminado,0) = 0'
    );
    $gq->execute([$competicionId]);
    $equiposIds = [];
    while ($r = $gq->fetch(PDO::FETCH_ASSOC)) {
        $equiposIds[] = (int) $r['id'];
    }
    $grupoPorEquipo = partidos_equipos_grupo($pdo, $competicionId);

    $ins = $pdo->prepare('INSERT INTO clasificacion (competicion_id, equipo_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE equipo_id = equipo_id');
    foreach ($equiposIds as $eid) {
        $ins->execute([$competicionId, $eid]);
    }

    $stats = [];
    foreach ($equiposIds as $eid) {
        $stats[$eid] = ['pj' => 0, 'pg' => 0, 'pe' => 0, 'pp' => 0, 'gf' => 0, 'gc' => 0, 'puntos' => 0];
    }

    $pq = $pdo->prepare(
        "SELECT * FROM partido WHERE competicion_id = ? AND estado = 'finalizado' AND es_eliminatoria = 0 ORDER BY jornada ASC, id ASC"
    );
    $pq->execute([$competicionId]);
    while ($row = $pq->fetch(PDO::FETCH_ASSOC)) {
        $el = (int) $row['equipo_local_id'];
        $ev = (int) $row['equipo_visitante_id'];
        if (!isset($stats[$el]) || !isset($stats[$ev])) {
            continue;
        }
        $gPartido = $row['grupo_letra'] ? strtoupper(substr(trim((string) $row['grupo_letra']), 0, 1)) : null;
        $gEl = $grupoPorEquipo[$el] ?? null;
        $gEv = $grupoPorEquipo[$ev] ?? null;
        if (!partidos_grupo_coincide($gPartido, $gEl, $gEv)) {
            continue;
        }

        $gl = (int) $row['goles_local'];
        $gv = (int) $row['goles_visitante'];

        $stats[$el]['pj']++;
        $stats[$ev]['pj']++;
        $stats[$el]['gf'] += $gl;
        $stats[$el]['gc'] += $gv;
        $stats[$ev]['gf'] += $gv;
        $stats[$ev]['gc'] += $gl;

        if ($gl > $gv) {
            $stats[$el]['pg']++;
            $stats[$el]['puntos'] += 3;
            $stats[$ev]['pp']++;
        } elseif ($gv > $gl) {
            $stats[$ev]['pg']++;
            $stats[$ev]['puntos'] += 3;
            $stats[$el]['pp']++;
        } else {
            $stats[$el]['pe']++;
            $stats[$ev]['pe']++;
            $stats[$el]['puntos']++;
            $stats[$ev]['puntos']++;
        }
    }

    $upd = $pdo->prepare(
        'UPDATE clasificacion SET pj = ?, pg = ?, pe = ?, pp = ?, gf = ?, gc = ?, puntos = ? WHERE competicion_id = ? AND equipo_id = ?'
    );
    foreach ($stats as $eid => $st) {
        $upd->execute([
            $st['pj'], $st['pg'], $st['pe'], $st['pp'], $st['gf'], $st['gc'], $st['puntos'],
            $competicionId, $eid,
        ]);
    }
}

function partidos_despues_de_cambio(PDO $pdo, int $competicionId): void
{
    partidos_replay_eliminaciones_ko($pdo, $competicionId);
    partidos_recalcular_clasificacion_desde_partidos($pdo, $competicionId);
}

function partidos_validar_finalizado(array $data): ?string
{
    if (($data['estado'] ?? '') !== 'finalizado') {
        return null;
    }
    if ($data['goles_local'] === '' || $data['goles_local'] === null || $data['goles_visitante'] === '' || $data['goles_visitante'] === null) {
        return 'Indica goles local y visitante para finalizar.';
    }
    if (!empty($data['es_eliminatoria'])) {
        $gl = (int) $data['goles_local'];
        $gv = (int) $data['goles_visitante'];
        if ($gl === $gv) {
            $pl = $data['penales_local'] ?? null;
            $pv = $data['penales_visitante'] ?? null;
            if ($pl === '' || $pl === null || $pv === '' || $pv === null) {
                return 'En eliminatoria, si hay empate debes indicar penales (marcador de la tanda).';
            }
            if ((int) $pl === (int) $pv) {
                return 'Los penales no pueden quedar en empate.';
            }
        }
    }
    return null;
}

function partidos_normalizar_nombre(string $s): string
{
    $s = trim($s);
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return mb_strtolower($s, 'UTF-8');
}

function partidos_api_buscar_equipo(PDO $pdo, int $competicionId, string $nombreApi): ?int
{
    $normApi = partidos_normalizar_nombre($nombreApi);
    $q = $pdo->prepare(
        'SELECT e.id, e.nombre, e.nombre_corto FROM equipo e
         JOIN competicion_equipo ce ON ce.equipo_id = e.id
         WHERE ce.competicion_id = ? AND COALESCE(ce.eliminado,0) = 0'
    );
    $q->execute([$competicionId]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    $exact = [];
    foreach ($rows as $r) {
        $n = partidos_normalizar_nombre((string) $r['nombre']);
        $c = isset($r['nombre_corto']) && $r['nombre_corto'] !== null && $r['nombre_corto'] !== ''
            ? partidos_normalizar_nombre((string) $r['nombre_corto']) : '';
        if ($n === $normApi || ($c !== '' && $c === $normApi)) {
            $exact[] = (int) $r['id'];
        }
    }
    if (count($exact) === 1) {
        return $exact[0];
    }
    if (count($exact) > 1) {
        return null;
    }
    $partial = [];
    foreach ($rows as $r) {
        $n = partidos_normalizar_nombre((string) $r['nombre']);
        if ($n === '' || $normApi === '') {
            continue;
        }
        if (mb_strpos($n, $normApi, 0, 'UTF-8') !== false || mb_strpos($normApi, $n, 0, 'UTF-8') !== false) {
            $partial[] = (int) $r['id'];
        }
    }
    if (count($partial) === 1) {
        return $partial[0];
    }
    return null;
}

function partidos_sugerir_es_eliminatoria(?string $tipoNombre): int
{
    if ($tipoNombre === 'Eliminatoria') {
        return 1;
    }
    if ($tipoNombre === 'Fase de grupos y eliminatoria') {
        return 0;
    }
    return 0;
}
