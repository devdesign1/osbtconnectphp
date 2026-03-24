<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gérer les requêtes OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Inclure la configuration de la base de données
require_once '../config/database.php';

// Vérifier le token (simple pour le debug)
$token = $_GET['token'] ?? $_POST['token'] ?? '';
if (empty($token)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Token manquant',
        'error' => 'Veuillez fournir un token d\'authentification'
    ]);
    exit;
}

try {
    // Décoder le token
    $token_data = json_decode(base64_decode($token), true);
    
    if (!$token_data || !isset($token_data['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Token invalide',
            'error' => 'Le token n\'est pas valide'
        ]);
        exit;
    }
    
    // Vérifier l'expiration
    if ($token_data['exp'] < time()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Token expiré',
            'error' => 'Veuillez vous reconnecter'
        ]);
        exit;
    }
    
    global $pdo;
    
    // Récupérer les données utilisateur complètes
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ? AND est_actif = 1");
    $stmt->execute([$token_data['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Utilisateur non trouvé',
            'error' => 'L\'utilisateur n\'existe pas ou est inactif'
        ]);
        exit;
    }
    
    // Retourner les données utilisateur (tout en string pour éviter les erreurs de type)
    echo json_encode([
        'success' => true,
        'message' => 'Données utilisateur récupérées',
        'user' => [
            'id' => (string)$user['id_utilisateur'],
            'nom' => (string)$user['nom'],
            'prenom' => (string)$user['prenom'],
            'role' => (string)$user['role'],
            'noma' => (string)$user['noma'],
            'email' => (string)($user['email'] ?? ''),
            'promotion' => (string)($user['promotion'] ?? '1'),
            'filiere' => (string)($user['filiere'] ?? ''),
            'telephone' => (string)($user['telephone'] ?? ''),
            'adresse' => (string)($user['adresse'] ?? ''),
            'date_naissance' => (string)($user['date_naissance'] ?? ''),
            'lieu_naissance' => (string)($user['lieu_naissance'] ?? ''),
            'nationalite' => (string)($user['nationalite'] ?? ''),
            'sexe' => (string)($user['sexe'] ?? ''),
            'date_inscription' => (string)($user['date_inscription'] ?? ''),
            'derniere_connexion' => (string)($user['derniere_connexion'] ?? ''),
            'est_actif' => (string)$user['est_actif']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erreur profile: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'error' => 'Une erreur est survenue'
    ]);
}
?>
