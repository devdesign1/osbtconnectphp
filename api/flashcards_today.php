<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once '../config/database.php';

// Auth via token (base64 JSON)
$token = $_GET['token'] ?? '';
if (empty($token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token manquant']);
    exit;
}

try {
    $token_data = json_decode(base64_decode($token), true);
    if (!$token_data || !isset($token_data['user_id']) || ($token_data['exp'] ?? 0) < time()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token invalide ou expiré']);
        exit;
    }

    global $pdo;
    $user_id = $token_data['user_id'];

    $stmt = $pdo->prepare("
        SELECT 
            f.id_flashcard AS id,
            f.recto AS question,
            f.verso AS reponse,
            f.difficulte,
            m.nom AS matiere
        FROM flashcards f
        LEFT JOIN decks d ON f.deck_id = d.id_deck
        LEFT JOIN matieres m ON d.matiere_id = m.id_matiere
        WHERE (f.prochaine_revision IS NULL OR f.prochaine_revision <= CURDATE())
          AND (d.createur_id = ? OR d.est_public = 1)
        ORDER BY 
          CASE 
            WHEN f.prochaine_revision IS NULL THEN 0
            ELSE 1 
          END,
          f.prochaine_revision ASC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'flashcards' => array_map(function($c) {
            return [
                'id' => (int)$c['id'],
                'question' => (string)$c['question'],
                'reponse' => (string)$c['reponse'],
                'matiere' => (string)($c['matiere'] ?? ''),
                'difficulte' => (string)($c['difficulte'] ?? 'moyen'),
            ];
        }, $cards)
    ]);
} catch (Exception $e) {
    error_log("Erreur flashcards_today: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
