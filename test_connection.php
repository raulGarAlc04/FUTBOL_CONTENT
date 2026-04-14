<?php
require 'db.php';

try {
    if ($pdo) {
        echo "CONEXION_EXITOSA: Conectado a la base de datos 'futbol_data' correctamente.";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>