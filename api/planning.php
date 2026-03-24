<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once '../config/database.php';

// Récupérer le planning de la semaine
$stmt = $pdo->query("
    SELECT * FROM planning 
    WHERE date_debut >= CURDATE() 
    AND date_debut < DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY date_debut
    LIMIT 10
");

$planning = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'planning' => $planning,
    'count' => count($planning)
]);
?>
