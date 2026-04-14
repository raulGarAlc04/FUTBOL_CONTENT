<?php
require_once 'db.php';

try {
    $stmt = $pdo->prepare("SELECT id FROM tipo_competicion WHERE nombre = ?");
    $stmt->execute(['Fase clasificatoria']);
    if (!$stmt->fetch()) {
        $pdo->prepare("INSERT INTO tipo_competicion (nombre) VALUES (?)")->execute(['Fase clasificatoria']);
        echo "Added 'Fase clasificatoria'\n";
    } else {
        echo "'Fase clasificatoria' already exists\n";
    }

    $stmt2 = $pdo->prepare("SELECT id FROM tipo_competicion WHERE nombre = ?");
    $stmt2->execute(['Eliminatoria']);
    if (!$stmt2->fetch()) {
        $pdo->prepare("INSERT INTO tipo_competicion (nombre) VALUES (?)")->execute(['Eliminatoria']);
        echo "Added 'Eliminatoria'\n";
    }

    $stmt3 = $pdo->prepare("SELECT id FROM tipo_competicion WHERE nombre = ?");
    $stmt3->execute(['Fase de grupos y eliminatoria']);
    if (!$stmt3->fetch()) {
        $pdo->prepare("INSERT INTO tipo_competicion (nombre) VALUES (?)")->execute(['Fase de grupos y eliminatoria']);
        echo "Added 'Fase de grupos y eliminatoria'\n";
    }

    echo "Done.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>