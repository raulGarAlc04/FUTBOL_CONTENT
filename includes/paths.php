<?php
declare(strict_types=1);

if (defined('FC_BASE')) {
    return;
}

$fcRoot = dirname(__DIR__);
$fcPath = str_replace('\\', '/', realpath($fcRoot) ?: $fcRoot);
$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$docPath = str_replace('\\', '/', realpath($docRoot) ?: '');

$base = '';
if ($docPath !== '' && $fcPath !== '' && strpos($fcPath, rtrim($docPath, '/')) === 0) {
    $rel = substr($fcPath, strlen(rtrim($docPath, '/')));
    if ($rel !== '') {
        $base = $rel[0] === '/' ? $rel : '/' . $rel;
    }
}

if ($base === '') {
    $base = '/php/FUTBOL_CONTENT';
}

define('FC_BASE', rtrim($base, '/'));

/**
 * URL absoluta desde la raíz web de la app (p. ej. /css/style.css).
 */
function fc_url(string $path = ''): string
{
    $path = str_replace('\\', '/', $path);
    if ($path !== '' && $path[0] !== '/') {
        $path = '/' . $path;
    }
    return FC_BASE . $path;
}
