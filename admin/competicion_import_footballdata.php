<?php
/**
 * Redirección: la importación unificada está en competicion_import_api.php
 */
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    header('Location: competicion_import_api.php?id=' . $id, true, 302);
    exit;
}
http_response_code(400);
die('ID no válido.');
