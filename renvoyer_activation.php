<?php
session_start();
require_once 'config/database.php';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $_SESSION['error'] = 'Veuillez entrer votre email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Format d\'email invalide';
    } else {
        try {
            // Chercher l'utilisateur non activé
            $stmt = $pdo->prepare("
                SELECT id_utilisateur, noma, nom, prenom 
                FROM utilisateurs 
                WHERE email = ? AND est_actif = 0
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $_SESSION['error'] = 'Aucun compte inactif trouvé avec cet email';
            } else {
                // Appeler l'API pour envoyer l'email
                $api_url = 'http://' . $_SERVER['HTTP_HOST'] . '/api/send_activation.php';
                $data = json_encode(['user_id' => $user['id_utilisateur']]);
                
                $ch = curl_init($api_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data)
                ]);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($http_code == 200) {
                    $_SESSION['success'] = 'Un nouvel email d\'activation a été envoyé à ' . $email;
                } else {
                    $_SESSION['error'] = 'Erreur lors de l\'envoi de l\'email';
                }
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Erreur : ' . $e->getMessage();
        }
        
        header('Location: renvoyer_activation.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renvoyer l'activation - OSBT Connect</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-green: #00C853;
            --secondary-green: #2E7D32;
            --accent-blue: #2196F3;
            --danger-color: #f72585;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
            --osbt-light-green: #e8f5e9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--osbt-light-green) 0%, #c8e6c9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .resend-container {
            max-width: 450px;
            width: 100%;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .resend-header {
            background: linear-gradient(to right, var(--primary-green), var(--secondary-green));
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        .resend-header i {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .resend-header h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .resend-header p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .resend-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-color);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-color);
            font-size: 1rem;
        }

        .input-with-icon input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e1e5ee;
            border-radius: var(--border-radius);
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .input-with-icon input:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(0, 200, 83, 0.1);
        }

        .btn-resend {
            width: 100%;
            padding: 16px;
            background: linear-gradient(to right, var(--primary-green), var(--secondary-green));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-resend:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 200, 83, 0.3);
        }

        .btn-resend:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background-color: #d1e7dd;
            color: #0f5132;
            border-left: 4px solid #0f5132;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #842029;
            border-left: 4px solid #842029;
        }

        .back-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e1e5ee;
        }

        .back-link a {
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-link a:hover {
            color: var(--secondary-green);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="resend-container">
        <div class="resend-header">
            <i class="fas fa-envelope"></i>
            <h2>Renvoyer l'activation</h2>
            <p>Vous n'avez pas reçu l'email d'activation ?</p>
        </div>

        <div class="resend-body">
            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            </div>
            <?php endif; ?>

            <form method="POST" action="renvoyer_activation.php">
                <div class="form-group">
                    <label for="email">Email de votre compte</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" required 
                               placeholder="votre.email@osbt.be"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>

                <button type="submit" class="btn-resend">
                    <i class="fas fa-paper-plane"></i>
                    Renvoyer l'email d'activation
                </button>
            </form>

            <div class="back-link">
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i>
                    Retour à la connexion
                </a>
            </div>
        </div>
    </div>
</body>
</html>
