<?php
require_once 'config/database.php';

// Récupérer le token depuis l'URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('Token d\'activation manquant');
}

try {
    // Vérifier le token
    $stmt = $pdo->prepare("
        SELECT ta.user_id, ta.expiry, u.noma, u.email, u.nom, u.prenom
        FROM tokens_activation ta
        JOIN utilisateurs u ON ta.user_id = u.id_utilisateur
        WHERE ta.token = ? AND ta.used = FALSE
    ");
    $stmt->execute([$token]);
    $token_data = $stmt->fetch();
    
    if (!$token_data) {
        die('Token d\'activation invalide ou déjà utilisé');
    }
    
    // Vérifier que le token n'a pas expiré
    if (strtotime($token_data['expiry']) < time()) {
        die('Token d\'activation expiré');
    }
    
    // Activer le compte
    $stmt = $pdo->prepare("UPDATE utilisateurs SET est_actif = 1 WHERE id_utilisateur = ?");
    $stmt->execute([$token_data['user_id']]);
    
    // Marquer le token comme utilisé
    $stmt = $pdo->prepare("UPDATE tokens_activation SET used = 1 WHERE token = ?");
    $stmt->execute([$token]);
    
    // Message de succès
    $success_message = "Votre compte a été activé avec succès ! Vous pouvez maintenant vous connecter.";
    
    // Redirection vers la page de connexion avec un message de succès
    session_start();
    $_SESSION['success'] = $success_message;
    header('Location: login.php');
    exit();
    
} catch (PDOException $e) {
    die('Erreur lors de l\'activation: ' . $e->getMessage());
}
?>
