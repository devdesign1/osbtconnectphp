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

require_once '../config/database.php';

// Vérifier que l'utilisateur est connecté
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Non autorisé',
        'error' => 'Vous devez être connecté pour réserver une séance'
    ]);
    exit;
}

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

// Récupérer les données du formulaire
$mentor_id = $_POST['mentor_id'] ?? '';
$matiere = $_POST['matiere'] ?? '';
$date_session = $_POST['date_session'] ?? '';
$duree = $_POST['duree'] ?? 60;
$mode = $_POST['mode'] ?? 'visio';
$description_besoin = $_POST['description_besoin'] ?? '';

// Validation des données
$errors = [];

if (empty($mentor_id)) {
    $errors[] = 'Mentor ID requis';
}

if (empty($matiere)) {
    $errors[] = 'Matière requise';
}

if (empty($date_session)) {
    $errors[] = 'Date de session requise';
} else {
    // Vérifier que la date est dans le futur
    $session_time = strtotime($date_session);
    if ($session_time <= time()) {
        $errors[] = 'La date de session doit être dans le futur';
    }
}

if (empty($description_besoin)) {
    $errors[] = 'Description du besoin requise';
}

if (!in_array($mode, ['visio', 'presentiel'])) {
    $errors[] = 'Mode de session invalide';
}

$duree = intval($duree);
if ($duree < 30 || $duree > 120) {
    $errors[] = 'La durée doit être entre 30 et 120 minutes';
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
    // Vérifier que le mentor existe et est disponible
    $stmt = $pdo->prepare("
        SELECT u.id_utilisateur, u.nom, u.prenom 
        FROM utilisateurs u 
        WHERE u.id_utilisateur = ? AND u.role = 'mentor' AND u.est_actif = 1
    ");
    $stmt->execute([$mentor_id]);
    $mentor = $stmt->fetch();

    if (!$mentor) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Mentor non trouvé',
            'error' => 'Ce mentor n\'existe pas ou n\'est pas disponible'
        ]);
        exit;
    }

    // Vérifier que l'étudiant n'a pas déjà une session avec ce mentor à la même date
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM sessions_mentorat 
        WHERE id_etudiant = ? AND id_mentor = ? AND date_session = ? AND statut != 'annulee'
    ");
    $stmt->execute([$_SESSION['user_id'], $mentor_id, $date_session]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Conflit de session',
            'error' => 'Vous avez déjà une session avec ce mentor à cette date'
        ]);
        exit;
    }

    // Récupérer l'ID de la matière
    $stmt = $pdo->prepare("SELECT id_matiere FROM matieres WHERE nom = ? OR code = ?");
    $stmt->execute([$matiere, $matiere]);
    $matiere_data = $stmt->fetch();

    if (!$matiere_data) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Matière non trouvée',
            'error' => 'Cette matière n\'existe pas'
        ]);
        exit;
    }

    $matiere_id = $matiere_data['id_matiere'];

    // Créer la session de mentorat
    $stmt = $pdo->prepare("
        INSERT INTO sessions_mentorat 
        (id_etudiant, id_mentor, id_matiere, date_session, duree, mode, sujet_discussion, statut, date_demande) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'demandee', NOW())
    ");

    $success = $stmt->execute([
        $_SESSION['user_id'],
        $mentor_id,
        $matiere_id,
        $date_session,
        $duree,
        $mode,
        $description_besoin
    ]);

    if ($success) {
        $session_id = $pdo->lastInsertId();

        // Journaliser la réservation
        error_log("Nouvelle réservation mentorat: Session ID $session_id, Étudiant {$_SESSION['user_id']}, Mentor $mentor_id");

        echo json_encode([
            'success' => true,
            'message' => 'Demande de réservation envoyée avec succès',
            'session_id' => $session_id,
            'mentor' => [
                'nom' => $mentor['nom'],
                'prenom' => $mentor['prenom']
            ],
            'details' => [
                'matiere' => $matiere,
                'date_session' => $date_session,
                'duree' => $duree,
                'mode' => $mode
            ]
        ]);
    } else {
        throw new Exception('Erreur lors de la création de la session');
    }

} catch (PDOException $e) {
    error_log("Erreur DB réservation mentorat: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'error' => 'Impossible de créer la réservation'
    ]);
} catch (Exception $e) {
    error_log("Erreur réservation mentorat: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'error' => $e->getMessage()
    ]);
}
?>
