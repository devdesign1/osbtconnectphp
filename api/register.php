<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gérer les requêtes OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Inclure la configuration de la base de données
require_once '../config/database.php';

// Vérifier que la méthode est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée',
        'error' => 'Utilisez la méthode POST'
    ]);
    exit;
}

// Lire les données JSON envoyées
$input = file_get_contents('php://input');
if (empty($input)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Données manquantes',
        'error' => 'Aucune donnée reçue'
    ]);
    exit;
}

$data = json_decode($input, true);

// Validation des données
if (!$data) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Format invalide',
        'error' => 'Les données doivent être au format JSON'
    ]);
    exit;
}

// Champs requis
$required_fields = ['noma', 'email', 'password', 'confirm_password', 'nom', 'prenom'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty(trim($data[$field]))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Champ manquant',
            'error' => "Le champ '$field' est requis"
        ]);
        exit;
    }
}

// Nettoyage des données
$noma = trim($data['noma']);
$email = trim($data['email']);
$password = $data['password'];
$confirm_password = $data['confirm_password'];
$nom = trim($data['nom']);
$prenom = trim($data['prenom']);

// Validation des erreurs
$errors = [];

// Validation du NOMA
if (!preg_match('/^(TECH|BUS)(20\d{2})-(\d{3})$/', $noma, $matches)) {
    $errors[] = 'Format NOMA invalide. Exemples: TECH2023-001, BUS2023-015';
} else {
    $filiere = ($matches[1] == 'TECH') ? 'technology' : 'business';
    $annee = intval(substr($matches[2], 2, 2));
    $promotion = (date('y') - $annee) + 1;
    
    // Vérifier que l'utilisateur n'existe pas déjà
    $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE noma = ?");
    $stmt->execute([$noma]);
    
    if ($stmt->rowCount() > 0) {
        $errors[] = 'Ce NOMA est déjà inscrit';
    }
}

// Validation de l'email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Format d\'email invalide';
} else {
    // Vérifier que l'email n'est pas déjà utilisé
    $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        $errors[] = 'Cet email est déjà utilisé';
    }
}

// Validation du mot de passe
if (strlen($password) < 8) {
    $errors[] = 'Le mot de passe doit contenir au moins 8 caractères';
}

if (!preg_match('/[A-Z]/', $password)) {
    $errors[] = 'Le mot de passe doit contenir au moins une majuscule';
}

if (!preg_match('/[0-9]/', $password)) {
    $errors[] = 'Le mot de passe doit contenir au moins un chiffre';
}

if ($password !== $confirm_password) {
    $errors[] = 'Les mots de passe ne correspondent pas';
}

// Validation du nom et prénom
if (strlen($nom) < 2) {
    $errors[] = 'Le nom doit contenir au moins 2 caractères';
}

if (strlen($prenom) < 2) {
    $errors[] = 'Le prénom doit contenir au moins 2 caractères';
}

// Si erreurs, les retourner
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de validation',
        'errors' => $errors
    ]);
    exit;
}

try {
    // Hashage du mot de passe
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Création du compte (désactivé par défaut)
    $stmt = $pdo->prepare("
        INSERT INTO utilisateurs (noma, email, mot_de_passe, nom, prenom, promotion, filiere, role, est_actif, date_inscription) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'etudiant', 0, NOW())
    ");
    
    $success = $stmt->execute([
        $noma,
        $email,
        $hashed_password,
        $nom,
        $prenom,
        $promotion,
        $filiere
    ]);
    
    if ($success) {
        $user_id = $pdo->lastInsertId();
        
        // Générer un token d'activation
        $activation_token = bin2hex(random_bytes(32));
        $activation_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Créer une table tokens_activation si elle n'existe pas
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tokens_activation (
                id_token INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL,
                expiry DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                used BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (user_id) REFERENCES utilisateurs(id_utilisateur),
                INDEX idx_token (token),
                INDEX idx_user (user_id)
            )
        ");
        
        // Enregistrer le token d'activation
        $stmt = $pdo->prepare("
            INSERT INTO tokens_activation (user_id, token, expiry) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user_id, $activation_token, $activation_expiry]);
        
        // Simulation d'envoi d'email (dans un vrai projet, utiliser PHPMailer ou similar)
        $activation_link = "https://connect.osbt.be/activation.php?token=" . $activation_token;
        
        // Journaliser l'inscription
        error_log("Nouvelle inscription: $noma ($email) - User ID: $user_id");
        
        // Réponse de succès
        echo json_encode([
            'success' => true,
            'message' => 'Compte créé avec succès. Un email d\'activation a été envoyé.',
            'user' => [
                'id' => $user_id,
                'noma' => $noma,
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'promotion' => $promotion,
                'filiere' => $filiere,
                'role' => 'etudiant'
            ],
            'activation_link' => $activation_link, // En développement seulement
            'debug' => [
                'activation_token' => $activation_token,
                'activation_expiry' => $activation_expiry
            ]
        ]);
        
    } else {
        throw new Exception('Erreur lors de la création du compte');
    }
    
} catch (PDOException $e) {
    // Erreur de base de données
    error_log("Erreur DB inscription: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'error' => 'Impossible de créer le compte'
    ]);
} catch (Exception $e) {
    // Autre erreur
    error_log("Erreur inscription: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'error' => 'Une erreur est survenue'
    ]);
}
?>
