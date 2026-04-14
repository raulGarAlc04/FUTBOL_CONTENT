<?php

function clasificacion_visual_storage_path()
{
    return __DIR__ . '/clasificacion_visual.json';
}

function clasificacion_visual_normalize_color($color)
{
    $c = trim((string) $color);
    if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $c)) {
        return strtolower($c);
    }
    return '#ffffff';
}

function clasificacion_visual_normalize_posiciones($spec)
{
    $s = str_replace([';', '|'], ',', (string) $spec);
    $s = preg_replace('/[^0-9,\-\s]/', '', $s);
    $s = preg_replace('/\s+/', '', $s);
    $s = preg_replace('/,+/', ',', $s);
    $s = trim($s, ',');
    return $s;
}

function clasificacion_visual_posicion_en_spec($pos, $spec)
{
    $pos = (int) $pos;
    if ($pos <= 0) return false;
    $s = clasificacion_visual_normalize_posiciones($spec);
    if ($s === '') return false;
    $parts = explode(',', $s);
    foreach ($parts as $p) {
        if ($p === '') continue;
        $dash = strpos($p, '-');
        if ($dash !== false) {
            $a = substr($p, 0, $dash);
            $b = substr($p, $dash + 1);
            if ($a === '' || $b === '') continue;
            $ai = (int) $a;
            $bi = (int) $b;
            if ($ai <= 0 || $bi <= 0) continue;
            $min = min($ai, $bi);
            $max = max($ai, $bi);
            if ($pos >= $min && $pos <= $max) return true;
            continue;
        }
        if ((int) $p === $pos) return true;
    }
    return false;
}

function clasificacion_visual_hex_to_rgb($hex)
{
    $h = ltrim((string) $hex, '#');
    if (strlen($h) === 3) {
        $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
    }
    if (strlen($h) !== 6) return null;
    return [
        hexdec(substr($h, 0, 2)),
        hexdec(substr($h, 2, 2)),
        hexdec(substr($h, 4, 2)),
    ];
}

function clasificacion_visual_load_all()
{
    $path = clasificacion_visual_storage_path();
    if (!file_exists($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }
    return $data;
}

function clasificacion_visual_save_all($data)
{
    if (!is_array($data)) $data = [];

    $path = clasificacion_visual_storage_path();
    $fp = @fopen($path, 'c+');
    if (!$fp) {
        return false;
    }
    $locked = flock($fp, LOCK_EX);
    if (!$locked) {
        fclose($fp);
        return false;
    }

    ftruncate($fp, 0);
    rewind($fp);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }
    $ok = fwrite($fp, $json) !== false;
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $ok;
}

function clasificacion_visual_get_marks($competicionId)
{
    $cid = (int) $competicionId;
    if ($cid <= 0) return [];
    $all = clasificacion_visual_load_all();
    $key = (string) $cid;
    $items = $all[$key] ?? [];
    if (!is_array($items)) return [];

    $out = [];
    foreach ($items as $m) {
        if (!is_array($m)) continue;
        $id = isset($m['id']) ? (string) $m['id'] : '';
        if ($id === '') continue;
        $nombre = trim((string) ($m['nombre'] ?? ''));
        $posiciones = clasificacion_visual_normalize_posiciones($m['posiciones'] ?? '');
        if ($nombre === '' || $posiciones === '') continue;
        $out[] = [
            'id' => $id,
            'nombre' => $nombre,
            'color' => clasificacion_visual_normalize_color($m['color'] ?? ''),
            'posiciones' => $posiciones,
            'orden' => (int) ($m['orden'] ?? 0),
        ];
    }

    usort($out, function ($a, $b) {
        if ((int) $a['orden'] !== (int) $b['orden']) {
            return (int) $a['orden'] - (int) $b['orden'];
        }
        return strcmp((string) $a['id'], (string) $b['id']);
    });

    return $out;
}

function clasificacion_visual_set_marks($competicionId, $marks)
{
    $cid = (int) $competicionId;
    if ($cid <= 0) return false;
    if (!is_array($marks)) $marks = [];

    $clean = [];
    foreach ($marks as $m) {
        if (!is_array($m)) continue;
        $id = isset($m['id']) ? (string) $m['id'] : '';
        if ($id === '') continue;
        $nombre = trim((string) ($m['nombre'] ?? ''));
        $posiciones = clasificacion_visual_normalize_posiciones($m['posiciones'] ?? '');
        if ($nombre === '' || $posiciones === '') continue;
        $clean[] = [
            'id' => $id,
            'nombre' => $nombre,
            'color' => clasificacion_visual_normalize_color($m['color'] ?? ''),
            'posiciones' => $posiciones,
            'orden' => (int) ($m['orden'] ?? 0),
        ];
    }

    $all = clasificacion_visual_load_all();
    $all[(string) $cid] = $clean;
    return clasificacion_visual_save_all($all);
}

function clasificacion_visual_new_id()
{
    try {
        return bin2hex(random_bytes(8));
    } catch (Exception $e) {
        return str_replace('.', '', uniqid('', true));
    }
}
