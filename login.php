<?php
session_start();

// === SI DÉJÀ CONNECTÉ, REDIRIGER ===
if (isset($_SESSION['user_id'])) {
    $path = ($_SESSION['user_role'] === 'admin') ? 'admin_dashboard.php' : 
            (($_SESSION['user_role'] === 'professeur') ? 'professor_dashboard.php' : 'student_dashboard.php');
    header("Location: $path");
    exit();
}

// === TRAITEMENT DU FORMULAIRE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant = trim($_POST['identifiant'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    
    if (empty($identifiant) || empty($mot_de_passe)) {
        $_SESSION['error'] = "Veuillez remplir tous les champs";
    } else {
        $payload = json_encode([
            'username' => $identifiant,
            'password' => $mot_de_passe
        ]);
        $ch = curl_init('http://localhost/osbtconnect/api/auth.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || $httpcode >= 400) {
            $_SESSION['error'] = "Authentification échouée";
        } else {
            $data = json_decode($response, true);
            if (!$data || empty($data['success'])) {
                $_SESSION['error'] = $data['message'] ?? "Identifiant ou mot de passe incorrect";
            } else {
                $user = $data['user'] ?? [];
                $_SESSION['user_id'] = $user['id_utilisateur'] ?? ($user['id'] ?? null);
                $_SESSION['user_prenom'] = $user['prenom'] ?? '';
                $_SESSION['user_nom'] = $user['nom'] ?? '';
                $_SESSION['user_role'] = $user['role'] ?? 'etudiant';
                $_SESSION['user_promotion'] = $user['promotion'] ?? 1;
                $_SESSION['user_filiere'] = $user['filiere'] ?? '';
                
                $path = ($_SESSION['user_role'] === 'admin') ? 'admin_dashboard.php' : 
                        (($_SESSION['user_role'] === 'professeur') ? 'professor_dashboard.php' : 'student_dashboard.php');
                header("Location: $path");
                exit();
            }
        }
    }
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - OSBT Connect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --osbt-primary: #00C853;
            --osbt-primary-dark: #2E7D32;
            --osbt-blue: #2196F3;
            --osbt-light: #f8fafc;
            --osbt-dark: #0f172a;
            --osbt-gray: #64748b;
            --osbt-gray-light: #e2e8f0;
            --gradient-osbt: linear-gradient(135deg, var(--osbt-primary) 0%, var(--osbt-primary-dark) 100%);
            --radius: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--osbt-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Animation de fond */
        .bg-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(0, 200, 83, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(33, 150, 243, 0.05) 0%, transparent 50%);
            z-index: -1;
        }

        /* Carte de connexion */
        .login-container {
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 2;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 40px;
            border-radius: var(--radius);
            box-shadow: 
                0 20px 60px rgba(0, 200, 83, 0.1),
                0 0 0 1px rgba(0, 200, 83, 0.05);
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }

        .login-card:hover {
            box-shadow: 
                0 25px 80px rgba(0, 200, 83, 0.15),
                0 0 0 1px rgba(0, 200, 83, 0.1);
        }

        /* Logo OSBT */
        .logo-container {
            margin-bottom: 30px;
            position: relative;
        }

        .logo-box {
            width: 80px;
            height: 80px;
            background: var(--gradient-osbt);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            position: relative;
            transition: var(--transition);
        }

        .logo-box:hover {
            transform: rotate(-5deg) scale(1.05);
            box-shadow: 0 15px 40px rgba(0, 200, 83, 0.3);
        }

        .logo-box::after {
            content: '';
            position: absolute;
            top: -5px;
            left: -5px;
            right: -5px;
            bottom: -5px;
            background: var(--gradient-osbt);
            border-radius: 25px;
            z-index: -1;
            opacity: 0.3;
            filter: blur(10px);
        }

        .logo-text {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 2.5rem;
            font-weight: 900;
            color: white;
            letter-spacing: -1px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .logo-subtitle {
            font-size: 0.85rem;
            color: var(--osbt-gray);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 5px;
        }

        /* Titres */
        .login-header {
            margin-bottom: 30px;
        }

        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: var(--gradient-osbt);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-header p {
            color: var(--osbt-gray);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Formulaire */
        .form-group {
            margin-bottom: 24px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
            color: var(--osbt-dark);
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--osbt-gray);
            font-size: 1.1rem;
            transition: var(--transition);
        }

        input {
            width: 100%;
            padding: 16px 16px 16px 52px;
            border-radius: 14px;
            border: 2px solid var(--osbt-gray-light);
            font-family: inherit;
            font-size: 1rem;
            transition: var(--transition);
            background: white;
            color: var(--osbt-dark);
        }

        input:focus {
            outline: none;
            border-color: var(--osbt-primary);
            box-shadow: 0 0 0 4px rgba(0, 200, 83, 0.1);
        }

        input:focus + i {
            color: var(--osbt-primary);
        }

        /* Bouton */
        .btn-submit {
            width: 100%;
            padding: 18px;
            background: var(--gradient-osbt);
            color: white;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 10px;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.5px;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0, 200, 83, 0.3);
        }

        .btn-submit:active {
            transform: translateY(-1px);
        }

        .btn-submit::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        .btn-submit:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }

        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(50, 50);
                opacity: 0;
            }
        }

        /* Message d'erreur */
        .error-msg {
            background: linear-gradient(135deg, rgba(244, 67, 54, 0.1) 0%, rgba(244, 67, 54, 0.05) 100%);
            color: #D93025;
            padding: 16px;
            border-radius: 14px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid rgba(244, 67, 54, 0.2);
            animation: slideIn 0.3s ease-out;
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

        .error-msg i {
            font-size: 1.2rem;
        }

        /* Section démo */
        .demo-section {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--osbt-gray-light);
        }

        .demo-section p {
            font-size: 0.85rem;
            color: var(--osbt-gray);
            margin-bottom: 15px;
            font-weight: 600;
        }

        .demo-chips {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .demo-chip {
            padding: 10px 18px;
            background: rgba(0, 200, 83, 0.08);
            color: var(--osbt-primary);
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid rgba(0, 200, 83, 0.2);
        }

        .demo-chip:hover {
            background: rgba(0, 200, 83, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 200, 83, 0.1);
        }

        .demo-chip:active {
            transform: translateY(0);
        }

        /* Footer */
        .login-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--osbt-gray-light);
            font-size: 0.8rem;
            color: var(--osbt-gray);
        }

        .login-footer a {
            color: var(--osbt-primary);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .login-footer a:hover {
            color: var(--osbt-primary-dark);
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .login-card {
                padding: 30px 25px;
            }
            
            .logo-box {
                width: 70px;
                height: 70px;
            }
            
            .logo-text {
                font-size: 2rem;
            }
            
            .login-header h1 {
                font-size: 1.6rem;
            }
            
            input {
                padding: 14px 14px 14px 48px;
            }
            
            .demo-chips {
                flex-direction: column;
                align-items: stretch;
            }
            
            .demo-chip {
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 25px 20px;
            }
            
            .logo-box {
                width: 60px;
                height: 60px;
            }
            
            .logo-text {
                font-size: 1.8rem;
            }
            
            .login-header h1 {
                font-size: 1.4rem;
            }
        }

        /* Animation de particules */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            background: var(--gradient-osbt);
            border-radius: 50%;
            animation: float 20s infinite linear;
            opacity: 0.1;
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.1;
            }
            90% {
                opacity: 0.1;
            }
            100% {
                transform: translateY(-100vh) rotate(720deg);
                opacity: 0;
            }
        }

        /* Accessibilité */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
    </style>
</head>
<body>

    <div class="bg-pattern"></div>
    
    <!-- Particules d'animation -->
    <div class="particles" id="particles"></div>

    <div class="login-container">
        <div class="login-card">
            <!-- Logo OSBT -->
            <div class="logo-container">
                <div class="logo-box">
                    <span class="logo-text">OSBT</span>
                </div>
                <div class="logo-subtitle">Connect Platform</div>
            </div>

            <!-- En-tête -->
            <div class="login-header">
                <h1>Content de vous revoir !</h1>
                <p>Connectez-vous à votre espace OSBT Connect pour accéder à vos cours, ressources et communauté.</p>
            </div>

            <!-- Messages d'erreur -->
            <?php if(isset($_SESSION['error'])): ?>
                <div class="error-msg">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <!-- Formulaire -->
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="identifiant">NOMA Étudiant / Identifiant</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user-graduate"></i>
                        <input type="text" name="identifiant" id="identifiant" 
                               placeholder="Ex: TECH2023-001 ou ADMIN001" required 
                               autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="mot_de_passe" id="password" 
                               placeholder="Votre mot de passe" required 
                               autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </form>

            <!-- Section démo -->
            <div class="demo-section">
                <p>Accès rapide (Démo) :</p>
                <div class="demo-chips">
                    <div class="demo-chip" onclick="fillCredentials('ADMIN001', 'Administrateur')">
                        <i class="fas fa-user-shield"></i> Administrateur
                    </div>
                    <div class="demo-chip" onclick="fillCredentials('TECH2023-001', 'Étudiant Tech')">
                        <i class="fas fa-laptop-code"></i> Étudiant Tech
                    </div>
                    <div class="demo-chip" onclick="fillCredentials('BUS2023-001', 'Étudiant Business')">
                        <i class="fas fa-chart-line"></i> Étudiant Business
                    </div>
                    <div class="demo-chip" onclick="fillCredentials('PROF001', 'Professeur')">
                        <i class="fas fa-chalkboard-teacher"></i> Professeur
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="login-footer">
                <p><i class="fas fa-info-circle"></i> Plateforme interne OSBT - Accès réservé aux étudiants et personnel.</p>
                <p>Problème de connexion ? <a href="mailto:support@osbt.education">Contactez le support</a></p>
            </div>
        </div>
    </div>

    <script>
        // Animation des particules
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 20;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.width = Math.random() * 10 + 5 + 'px';
                particle.style.height = particle.style.width;
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = Math.random() * 20 + 20 + 's';
                particlesContainer.appendChild(particle);
            }
        }

        // Remplissage automatique des identifiants
        function fillCredentials(username, role) {
            const identifiantInput = document.getElementById('identifiant');
            const passwordInput = document.getElementById('password');
            
            identifiantInput.value = username;
            passwordInput.value = 'Osbt2024!';
            
            // Animation de feedback
            identifiantInput.focus();
            identifiantInput.style.borderColor = 'var(--osbt-primary)';
            passwordInput.style.borderColor = 'var(--osbt-primary)';
            
            // Reset après 2 secondes
            setTimeout(() => {
                identifiantInput.style.borderColor = '';
                passwordInput.style.borderColor = '';
            }, 2000);
            
            // Message d'information
            showNotification(`Identifiants ${role} remplis automatiquement.`);
        }

        // Notification
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--gradient-osbt);
                color: white;
                padding: 12px 20px;
                border-radius: 10px;
                font-weight: 600;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                z-index: 1000;
                animation: slideInRight 0.3s ease-out;
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            
            notification.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Validation du formulaire
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const identifiant = document.getElementById('identifiant').value.trim();
            const password = document.getElementById('password').value;
            
            if (!identifiant || !password) {
                e.preventDefault();
                showNotification('Veuillez remplir tous les champs');
                return false;
            }
            
            // Animation de chargement
            const submitBtn = this.querySelector('.btn-submit');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connexion...';
            submitBtn.disabled = true;
        });

        // Effet visuel sur les inputs
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.querySelector('i').style.color = 'var(--osbt-primary)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.querySelector('i').style.color = 'var(--osbt-gray)';
            });
        });

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            
            // Mettre le focus sur le premier champ
            document.getElementById('identifiant').focus();
            
            // Ajouter les styles d'animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        });

        // Toggle password visibility (optionnel)
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
        }
    </script>
</body>
</html>
