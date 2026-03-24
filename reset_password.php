<?php
require_once 'config/database.php';

// Réinitialiser le mot de passe pour ADMIN001
$new_password = 'Osbt2024!';
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE noma = ?");
    $stmt->execute([$hashed_password, 'ADMIN001']);
    
    echo '<h3>✅ Mot de passe réinitialisé avec succès!</h3>';
    echo '<p><strong>NOMA:</strong> ADMIN001</p>';
    echo '<p><strong>Nouveau mot de passe:</strong> ' . $new_password . '</p>';
    echo '<p><strong>Hash généré:</strong> ' . $hashed_password . '</p>';
    
    // Vérifier le nouveau hash
    echo '<h3>🔍 Vérification:</h3>';
    $stmt = $pdo->prepare("SELECT mot_de_passe FROM utilisateurs WHERE noma = ?");
    $stmt->execute(['ADMIN001']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (password_verify($new_password, $user['mot_de_passe'])) {
        echo '<p style="color: green;">✅ Le mot de passe fonctionne correctement!</p>';
    } else {
        echo '<p style="color: red;">❌ Erreur lors de la vérification</p>';
    }
    
} catch (Exception $e) {
    echo '<p style="color: red;">Erreur: ' . $e->getMessage() . '</p>';
}
?>
