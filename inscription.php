<?php
session_start();
require_once 'config/database.php';

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Récupération et nettoyage des données
    $noma = trim($_POST['noma'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $confirm_mot_de_passe = $_POST['confirm_mot_de_passe'] ?? '';
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    
    // Validation des données
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
    if (empty($email)) {
        $errors[] = 'Veuillez entrer votre email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
    if (empty($mot_de_passe)) {
        $errors[] = 'Veuillez entrer un mot de passe';
    } elseif (strlen($mot_de_passe) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères';
    } elseif (!preg_match('/[A-Z]/', $mot_de_passe)) {
        $errors[] = 'Le mot de passe doit contenir au moins une majuscule';
    } elseif (!preg_match('/[0-9]/', $mot_de_passe)) {
        $errors[] = 'Le mot de passe doit contenir au moins un chiffre';
    }
    
    // Validation de la confirmation du mot de passe
    if ($mot_de_passe !== $confirm_mot_de_passe) {
        $errors[] = 'Les mots de passe ne correspondent pas';
    }
    
    // Validation du nom et prénom
    if (empty($nom)) {
        $errors[] = 'Veuillez entrer votre nom';
    }
    
    if (empty($prenom)) {
        $errors[] = 'Veuillez entrer votre prénom';
    }
    
    // Si pas d'erreurs, créer le compte
    if (empty($errors)) {
        try {
            // Hashage du mot de passe
            $hashed_password = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            
            // Création du compte
            $stmt = $pdo->prepare("
                INSERT INTO utilisateurs (noma, email, mot_de_passe, nom, prenom, promotion, filiere, role, est_actif) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'etudiant', 0)
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
                
                // Enregistrer le token d'activation (vous pouvez créer une table tokens_activation)
                $_SESSION['activation_token'] = $activation_token;
                $_SESSION['pending_user_id'] = $user_id;
                
                // Message de succès (simulation d'email)
                $_SESSION['success'] = "Compte créé avec succès ! Un email d'activation a été envoyé à $email";
                
                // Redirection vers la page de connexion
                header('Location: login.php');
                exit();
            } else {
                $errors[] = 'Erreur lors de la création du compte';
            }
            
        } catch (PDOException $e) {
            $errors[] = 'Erreur de base de données : ' . $e->getMessage();
        }
    }
    
    // Si erreurs, les stocker en session
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['old_data'] = $_POST;
        header('Location: inscription.php');
        exit();
    }
}

// Générer un token CSRF si nécessaire
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - OSBT Connect</title>
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
            --warning-color: #FF9800;
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

        .register-container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        /* En-tête */
        .register-header {
            background: linear-gradient(to right, var(--primary-green), var(--secondary-green));
            padding: 40px 30px;
            text-align: center;
            color: white;
            position: relative;
        }

        .register-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .register-header::after {
            content: '';
            position: absolute;
            bottom: -30px;
            left: -30px;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }

        .osbt-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .osbt-logo img {
            height: 60px;
            width: auto;
            background: white;
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .osbt-text {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .osbt-text h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }

        .osbt-subtitle {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-top: 5px;
            font-weight: 300;
        }

        .register-header h2 {
            font-size: 1.5rem;
            margin-top: 10px;
            position: relative;
            z-index: 1;
        }

        /* Corps du formulaire */
        .register-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-color);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-group .required {
            color: var(--danger-color);
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

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-color);
            cursor: pointer;
            font-size: 1rem;
            padding: 5px;
        }

        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e1e5ee;
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            transition: var(--transition);
            border-radius: 2px;
        }

        .strength-weak { background: var(--danger-color); width: 33%; }
        .strength-medium { background: var(--warning-color); width: 66%; }
        .strength-strong { background: var(--primary-green); width: 100%; }

        /* Aide NOMA */
        .noma-help {
            background: var(--osbt-light-green);
            padding: 15px;
            border-radius: 8px;
            margin-top: 8px;
            font-size: 0.85rem;
            color: var(--secondary-green);
            border-left: 3px solid var(--primary-green);
        }

        .noma-examples {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .noma-example {
            background: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-family: monospace;
            font-size: 0.8rem;
            border: 1px solid var(--primary-green);
            cursor: pointer;
            transition: var(--transition);
        }

        .noma-example:hover {
            background: var(--primary-green);
            color: white;
        }

        /* Bouton d'inscription */
        .btn-register {
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
            margin-top: 20px;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 200, 83, 0.3);
        }

        .btn-register:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Messages d'alerte */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
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

        .alert i {
            font-size: 1.1rem;
        }

        /* Lien de connexion */
        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e1e5ee;
            font-size: 0.9rem;
            color: var(--gray-color);
        }

        .login-link a {
            color: var(--primary-green);
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            color: var(--secondary-green);
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .register-container {
                max-width: 100%;
            }
            
            .register-header {
                padding: 30px 20px;
            }
            
            .register-body {
                padding: 30px 20px;
            }
            
            .osbt-logo {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .osbt-text {
                text-align: center;
            }
            
            .osbt-logo img {
                height: 50px;
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- En-tête -->
        <div class="register-header">
            <div class="osbt-logo">
                <img src="im/téléchargement.png" alt="Logo OSBT">
                <div class="osbt-text">
                    <h1>OSBT Connect</h1>
                    <div class="osbt-subtitle">OMNIA SCHOOL OF BUSINESS AND TECHNOLOGY</div>
                </div>
            </div>
            <h2>Créer Votre Compte</h2>
        </div>

        <!-- Corps du formulaire -->
        <div class="register-body">
            <!-- Messages d'alerte -->
            <?php if(isset($_SESSION['errors'])): ?>
            <div class="alert alert-error" id="errorAlert">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <?php 
                    echo implode("<br>", $_SESSION['errors']);
                    unset($_SESSION['errors']);
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle"></i>
                <div><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            </div>
            <?php endif; ?>

            <!-- Formulaire d'inscription -->
            <form id="registerForm" method="POST" action="inscription.php">
                <!-- Token CSRF -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="form-group">
                    <label for="noma">
                        NOMA OSBT <span class="required">*</span>
                    </label>
                    <div class="input-with-icon">
                        <i class="fas fa-id-card"></i>
                        <input type="text" id="noma" name="noma" required 
                               placeholder="ex: TECH2023-001"
                               value="<?php echo isset($_SESSION['old_data']['noma']) ? htmlspecialchars($_SESSION['old_data']['noma']) : ''; ?>"
                               pattern="^(TECH|BUS)(20\d{2})-(\d{3})$"
                               title="Format: TECH2023-001 ou BUS2023-015">
                    </div>
                    <div class="noma-help">
                        <strong>Format du NOMA :</strong> FILIÈREANNÉE-NUMÉRO
                        <div class="noma-examples">
                            <span class="noma-example" onclick="fillNoma('TECH2023-001')">TECH2023-001</span>
                            <span class="noma-example" onclick="fillNoma('BUS2023-001')">BUS2023-001</span>
                            <span class="noma-example" onclick="fillNoma('TECH2022-015')">TECH2022-015</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="nom">
                        Nom <span class="required">*</span>
                    </label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="nom" name="nom" required 
                               placeholder="Votre nom de famille"
                               value="<?php echo isset($_SESSION['old_data']['nom']) ? htmlspecialchars($_SESSION['old_data']['nom']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="prenom">
                        Prénom <span class="required">*</span>
                    </label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="prenom" name="prenom" required 
                               placeholder="Votre prénom"
                               value="<?php echo isset($_SESSION['old_data']['prenom']) ? htmlspecialchars($_SESSION['old_data']['prenom']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">
                        Email <span class="required">*</span>
                    </label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" required 
                               placeholder="votre.email@osbt.be"
                               value="<?php echo isset($_SESSION['old_data']['email']) ? htmlspecialchars($_SESSION['old_data']['email']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="mot_de_passe">
                        Mot de passe <span class="required">*</span>
                    </label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="mot_de_passe" name="mot_de_passe" required 
                               placeholder="Min 8 caractères, 1 majuscule, 1 chiffre">
                        <button type="button" class="password-toggle" onclick="togglePassword('mot_de_passe')">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="password-strength-bar" id="passwordStrength"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_mot_de_passe">
                        Confirmer le mot de passe <span class="required">*</span>
                    </label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_mot_de_passe" name="confirm_mot_de_passe" required 
                               placeholder="Confirmez votre mot de passe">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_mot_de_passe')">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-register" id="registerBtn">
                    <span id="registerText">Créer mon compte</span>
                    <div class="loading" id="registerLoading" style="display: none; width: 18px; height: 18px; border: 2px solid rgba(255, 255, 255, 0.3); border-radius: 50%; border-top-color: white; animation: spin 1s linear infinite;"></div>
                </button>
            </form>

            <div class="login-link">
                <p>Déjà inscrit ? <a href="login.php">Se connecter</a></p>
            </div>
        </div>
    </div>

    <script>
        // Basculer la visibilité du mot de passe
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggle = field.parentNode.querySelector('.password-toggle i');
            
            if (field.type === 'password') {
                field.type = 'text';
                toggle.classList.remove('fa-eye');
                toggle.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                toggle.classList.remove('fa-eye-slash');
                toggle.classList.add('fa-eye');
            }
        }

        // Remplir automatiquement le NOMA
        function fillNoma(noma) {
            document.getElementById('noma').value = noma;
            document.getElementById('noma').focus();
        }

        // Vérifier la force du mot de passe
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 3) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        }

        // Validation en temps réel
        document.getElementById('mot_de_passe').addEventListener('input', function() {
            checkPasswordStrength(this.value);
        });

        // Validation du formulaire
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('mot_de_passe').value;
            const confirmPassword = document.getElementById('confirm_mot_de_passe').value;
            const noma = document.getElementById('noma').value;
            const email = document.getElementById('email').value;
            
            // Validation côté client
            if (!noma.match(/^(TECH|BUS)(20\d{2})-(\d{3})$/)) {
                alert('Format NOMA invalide. Ex: TECH2023-001');
                e.preventDefault();
                return;
            }
            
            if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                alert('Format d\'email invalide');
                e.preventDefault();
                return;
            }
            
            if (password.length < 8) {
                alert('Le mot de passe doit contenir au moins 8 caractères');
                e.preventDefault();
                return;
            }
            
            if (!password.match(/[A-Z]/)) {
                alert('Le mot de passe doit contenir au moins une majuscule');
                e.preventDefault();
                return;
            }
            
            if (!password.match(/[0-9]/)) {
                alert('Le mot de passe doit contenir au moins un chiffre');
                e.preventDefault();
                return;
            }
            
            if (password !== confirmPassword) {
                alert('Les mots de passe ne correspondent pas');
                e.preventDefault();
                return;
            }
            
            // Afficher l'animation de chargement
            const registerBtn = document.getElementById('registerBtn');
            const registerText = document.getElementById('registerText');
            const loading = document.getElementById('registerLoading');
            
            registerBtn.disabled = true;
            registerText.style.display = 'none';
            loading.style.display = 'block';
        });

        // Focus automatique
        document.addEventListener('DOMContentLoaded', function() {
            const nomaField = document.getElementById('noma');
            if (nomaField && !nomaField.value) {
                nomaField.focus();
            }
            
            // Masquer les alertes après 5 secondes
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 300);
                });
            }, 5000);
        });
    </script>
</body>
</html>
