<?php
require_once 'db.php';

try {
    echo "<h2>Competicion Table Schema</h2>";
    $stmt = $pdo->query("DESCRIBE competicion");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";

    echo "<h2>Equipo Table Schema</h2>";
    $stmt = $pdo->query("DESCRIBE equipo");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";

    echo "<h2>Continente Table Schema</h2>";
    $stmt = $pdo->query("DESCRIBE continente");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";

    echo "<h2>Pais Table Schema</h2>";
    $stmt = $pdo->query("DESCRIBE pais");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>