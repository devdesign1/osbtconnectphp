<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gérer les requêtes OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Inclure la configuration de la base de données
require_once '../config/database.php';

// Vérifier l'authentification admin
function verifyAdminAuth() {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token manquant']);
        exit;
    }
    
    // Retirer "Bearer " si présent
    $token = str_replace('Bearer ', '', $token);
    
    $token_data = json_decode(base64_decode($token), true);
    
    if (!$token_data || !isset($token_data['user_id']) || $token_data['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
        exit;
    }
    
    if ($token_data['exp'] < time()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token expiré']);
        exit;
    }
    
    return $token_data;
}

try {
    global $pdo;
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Vérifier l'authentification pour toutes les requêtes
    $admin_data = verifyAdminAuth();
    
    switch ($method) {
        case 'GET':
            // Lister tous les utilisateurs
            if (isset($_GET['id'])) {
                // Récupérer un utilisateur spécifique
                $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ?");
                $stmt->execute([$_GET['id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    echo json_encode(['success' => true, 'user' => $user]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
                }
            } else {
                // Lister tous les utilisateurs avec pagination
                $page = max(1, intval($_GET['page'] ?? 1));
                $limit = max(1, min(100, intval($_GET['limit'] ?? 10)));
                $offset = ($page - 1) * $limit;
                
                // Filtres
                $role = $_GET['role'] ?? '';
                $search = $_GET['search'] ?? '';
                $promotion = $_GET['promotion'] ?? '';
                $filiere = $_GET['filiere'] ?? '';
                
                $where_conditions = ["est_actif = 1"];
                $params = [];
                
                if (!empty($role)) {
                    $where_conditions[] = "role = ?";
                    $params[] = $role;
                }
                
                if (!empty($search)) {
                    $where_conditions[] = "(nom LIKE ? OR prenom LIKE ? OR noma LIKE ?)";
                    $search_param = "%$search%";
                    $params[] = $search_param;
                    $params[] = $search_param;
                    $params[] = $search_param;
                }
                
                if (!empty($promotion)) {
                    $where_conditions[] = "promotion = ?";
                    $params[] = $promotion;
                }
                
                if (!empty($filiere)) {
                    $where_conditions[] = "filiere = ?";
                    $params[] = $filiere;
                }
                
                $where_clause = "WHERE " . implode(" AND ", $where_conditions);
                
                // Compter le total
                $count_sql = "SELECT COUNT(*) as total FROM utilisateurs $where_clause";
                $stmt = $pdo->prepare($count_sql);
                $stmt->execute($params);
                $total = $stmt->fetch()['total'];
                
                // Récupérer les utilisateurs
                $sql = "SELECT * FROM utilisateurs $where_clause ORDER BY date_inscription DESC LIMIT ? OFFSET ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge($params, [$limit, $offset]));
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'users' => $users,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'pages' => ceil($total / $limit)
                    ]
                ]);
            }
            break;
            
        case 'POST':
            // Créer un nouvel utilisateur
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                break;
            }
            
            // Validation des champs requis
            $required_fields = ['nom', 'prenom', 'email', 'role', 'noma', 'mot_de_passe'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "Champ $field requis"]);
                    break;
                }
            }
            
            // Vérifier que le NOMA n'existe pas déjà
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE noma = ?");
            $stmt->execute([$data['noma']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ce NOMA existe déjà']);
                break;
            }
            
            // Vérifier que l'email n'existe pas déjà
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Cet email existe déjà']);
                break;
            }
            
            // Hasher le mot de passe
            $hashed_password = password_hash($data['mot_de_passe'], PASSWORD_DEFAULT);
            
            // Insérer l'utilisateur
            $sql = "INSERT INTO utilisateurs (nom, prenom, email, role, noma, mot_de_passe, promotion, filiere, telephone, adresse, date_naissance, lieu_naissance, nationalite, sexe, est_actif, date_inscription) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $data['nom'],
                $data['prenom'],
                $data['email'],
                $data['role'],
                $data['noma'],
                $hashed_password,
                $data['promotion'] ?? null,
                $data['filiere'] ?? null,
                $data['telephone'] ?? null,
                $data['adresse'] ?? null,
                $data['date_naissance'] ?? null,
                $data['lieu_naissance'] ?? null,
                $data['nationalite'] ?? null,
                $data['sexe'] ?? null
            ]);
            
            if ($result) {
                $user_id = $pdo->lastInsertId();
                echo json_encode([
                    'success' => true,
                    'message' => 'Utilisateur créé avec succès',
                    'user_id' => $user_id
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la création']);
            }
            break;
            
        case 'PUT':
            // Mettre à jour un utilisateur
            $user_id = $_GET['id'] ?? '';
            if (empty($user_id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID utilisateur requis']);
                break;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Données invalides']);
                break;
            }
            
            // Vérifier que l'utilisateur existe
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE id_utilisateur = ?");
            $stmt->execute([$user_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
                break;
            }
            
            // Construire la requête de mise à jour
            $update_fields = [];
            $params = [];
            
            $allowed_fields = ['nom', 'prenom', 'email', 'role', 'promotion', 'filiere', 'telephone', 'adresse', 'date_naissance', 'lieu_naissance', 'nationalite', 'sexe', 'est_actif'];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $update_fields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            // Si le mot de passe est fourni, le mettre à jour
            if (!empty($data['mot_de_passe'])) {
                $update_fields[] = "mot_de_passe = ?";
                $params[] = password_hash($data['mot_de_passe'], PASSWORD_DEFAULT);
            }
            
            if (empty($update_fields)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Aucun champ à mettre à jour']);
                break;
            }
            
            $params[] = $user_id; // Pour la clause WHERE
            
            $sql = "UPDATE utilisateurs SET " . implode(", ", $update_fields) . " WHERE id_utilisateur = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Utilisateur mis à jour avec succès']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
            }
            break;
            
        case 'DELETE':
            // Désactiver un utilisateur (suppression douce)
            $user_id = $_GET['id'] ?? '';
            if (empty($user_id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID utilisateur requis']);
                break;
            }
            
            // Vérifier que l'utilisateur existe
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE id_utilisateur = ?");
            $stmt->execute([$user_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
                break;
            }
            
            // Empêcher la suppression de l'admin courant
            if ($user_id == $admin_data['user_id']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas supprimer votre propre compte']);
                break;
            }
            
            // Désactiver l'utilisateur
            $stmt = $pdo->prepare("UPDATE utilisateurs SET est_actif = 0 WHERE id_utilisateur = ?");
            $result = $stmt->execute([$user_id]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Utilisateur désactivé avec succès']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la désactivation']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Erreur API admin users: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
} catch (Exception $e) {
    error_log("Erreur API admin users: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
