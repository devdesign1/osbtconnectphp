<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once '../config/database.php';

// Récupérer les dernières annonces
$stmt = $pdo->query("
    SELECT * FROM annonces 
    ORDER BY date_publication DESC 
    LIMIT 5
");

$annonces = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'annonces' => $annonces
]);
?>
