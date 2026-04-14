<?php
require_once 'db.php';
try {
    $stmt = $pdo->prepare("INSERT INTO tipo_competicion (nombre) VALUES (?)");
    $stmt->execute(['Clasificatoria']);
    echo "Clasificatoria added successfully.";
} catch (PDOException $e) {
    echo "Error (maybe exists): " . $e->getMessage();
}
