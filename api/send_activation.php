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

// Lire les données JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['user_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Données invalides',
        'error' => 'User ID requis'
    ]);
    exit;
}

$user_id = $data['user_id'];

try {
    // Récupérer les informations de l'utilisateur
    $stmt = $pdo->prepare("
        SELECT noma, email, nom, prenom 
        FROM utilisateurs 
        WHERE id_utilisateur = ? AND est_actif = 0
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Utilisateur non trouvé ou déjà activé'
        ]);
        exit;
    }
    
    // Générer un nouveau token d'activation
    $activation_token = bin2hex(random_bytes(32));
    $activation_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Créer la table tokens_activation si elle n'existe pas
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
    
    // Supprimer les anciens tokens pour cet utilisateur
    $stmt = $pdo->prepare("DELETE FROM tokens_activation WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Insérer le nouveau token
    $stmt = $pdo->prepare("
        INSERT INTO tokens_activation (user_id, token, expiry) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user_id, $activation_token, $activation_expiry]);
    
    // Préparer l'email d'activation
    $activation_link = "https://connect.osbt.be/activation.php?token=" . $activation_token;
    
    $subject = "Activation de votre compte OSBT Connect";
    $message = "
    <html>
    <head>
        <title>Activation de votre compte OSBT Connect</title>
    </head>
    <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: white; border-radius: 10px; overflow: hidden; box-shadow: 0 0 20px rgba(0,0,0,0.1);'>
            <div style='background: linear-gradient(135deg, #00C853, #2E7D32); padding: 30px; text-align: center; color: white;'>
                <h1 style='margin: 0; font-size: 2rem;'>OSBT Connect</h1>
                <p style='margin: 10px 0 0 0; opacity: 0.9;'>Plateforme Éducative All-in-One</p>
            </div>
            
            <div style='padding: 40px 30px;'>
                <h2 style='color: #2E7D32; margin-bottom: 20px;'>Bienvenue {$user['prenom']} {$user['nom']} !</h2>
                
                <p style='color: #333; line-height: 1.6; margin-bottom: 25px;'>
                    Merci de vous être inscrit sur OSBT Connect. Votre compte a été créé avec le NOMA <strong>{$user['noma']}</strong>.
                </p>
                
                <p style='color: #333; line-height: 1.6; margin-bottom: 25px;'>
                    Pour activer votre compte et commencer à utiliser la plateforme, veuillez cliquer sur le bouton ci-dessous :
                </p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$activation_link}' style='background: linear-gradient(135deg, #00C853, #2E7D32); color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: 600; display: inline-block;'>
                        Activer mon compte
                    </a>
                </div>
                
                <p style='color: #666; font-size: 0.9rem; line-height: 1.5; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                    <strong>Important :</strong> Ce lien d'activation expirera dans 24 heures. Si vous n'activez pas votre compte dans ce délai, vous devrez demander un nouvel email d'activation.
                </p>
                
                <p style='color: #666; font-size: 0.9rem; line-height: 1.5; margin-top: 15px;'>
                    Si le bouton ci-dessus ne fonctionne pas, copiez et collez ce lien dans votre navigateur :<br>
                    <span style='word-break: break-all; color: #2E7D32;'>{$activation_link}</span>
                </p>
                
                <p style='color: #666; font-size: 0.8rem; margin-top: 30px;'>
                    Cordialement,<br>
                    L'équipe OSBT Connect
                </p>
            </div>
            
            <div style='background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 0.8rem;'>
                <p style='margin: 0;'>© 2024 OMNIA SCHOOL OF BUSINESS AND TECHNOLOGY</p>
                <p style='margin: 5px 0 0 0;'>Connect.osbt.be</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // En-têtes pour l'email HTML
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: OSBT Connect <noreply@osbt.be>',
        'Reply-To: support@osbt.be',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Envoyer l'email (en développement, on simule l'envoi)
    $email_sent = true; // mail($user['email'], $subject, $message, implode("\r\n", $headers));
    
    if ($email_sent) {
        // Journaliser l'envoi
        error_log("Email d'activation envoyé à {$user['email']} (User ID: $user_id)");
        
        echo json_encode([
            'success' => true,
            'message' => 'Email d\'activation envoyé avec succès',
            'email' => $user['email'],
            'activation_link' => $activation_link, // En développement seulement
            'debug' => [
                'token' => $activation_token,
                'expiry' => $activation_expiry,
                'user_info' => $user
            ]
        ]);
    } else {
        throw new Exception('Erreur lors de l\'envoi de l\'email');
    }
    
} catch (PDOException $e) {
    error_log("Erreur DB envoi activation: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'error' => 'Impossible d\'envoyer l\'email d\'activation'
    ]);
} catch (Exception $e) {
    error_log("Erreur envoi activation: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'error' => $e->getMessage()
    ]);
}
?>
