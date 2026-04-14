<?php
require_once 'db.php';
$stmt = $pdo->query("SELECT * FROM tipo_competicion");
$types = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($types);
