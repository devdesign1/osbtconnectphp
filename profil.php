<?php
session_start();
require_once 'config/database.php';

// === VÉRIFICATION DE CONNEXION ===
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// === VARIABLES ===
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$promotion = $_SESSION['user_promotion'] ?? '';
$user_data = $_SESSION;

// === TRAITEMENT DU FORMULAIRE ===
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        try {
            $stmt = $pdo->prepare("
                UPDATE utilisateurs 
                SET prenom = ?, nom = ?, email = ?, telephone = ?, 
                    date_naissance = ?, adresse = ?, bio = ?
                WHERE id_utilisateur = ?
            ");
            
            $stmt->execute([
                $_POST['prenom'],
                $_POST['nom'],
                $_POST['email'],
                $_POST['telephone'] ?? '',
                $_POST['date_naissance'] ?? '',
                $_POST['adresse'] ?? '',
                $_POST['bio'] ?? '',
                $user_id
            ]);
            
            // Mettre à jour la session
            $_SESSION['user_prenom'] = $_POST['prenom'];
            $_SESSION['user_nom'] = $_POST['nom'];
            $_SESSION['user_email'] = $_POST['email'];
            
            $success_message = "Profil mis à jour avec succès !";
            
        } catch (PDOException $e) {
            $error_message = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
    
    if (isset($_POST['change_password'])) {
        try {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Vérifier le mot de passe actuel
            $stmt = $pdo->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id_utilisateur = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($current_password, $user['mot_de_passe'])) {
                if ($new_password === $confirm_password) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id_utilisateur = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    
                    $success_message = "Mot de passe changé avec succès !";
                } else {
                    $error_message = "Les nouveaux mots de passe ne correspondent pas";
                }
            } else {
                $error_message = "Mot de passe actuel incorrect";
            }
        } catch (PDOException $e) {
            $error_message = "Erreur lors du changement de mot de passe : " . $e->getMessage();
        }
    }
}

// === RÉCUPÉRATION DES DONNÉES UTILISATEUR ===
try {
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Statistiques académiques
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM matieres WHERE filiere = ?) as nb_cours,
            0 as nb_notes,
            0 as moyenne_generale,
            0 as devoirs_rendus,
            0 as devoirs_en_cours
    ");
    $stmt->execute([$_SESSION['user_filiere'] ?? 'technology']);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Erreur récupération données: " . $e->getMessage());
    $error_message = "Erreur lors de la récupération des données";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - OSBT Connect</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-green: #00C853;
            --secondary-green: #2E7D32;
            --accent-blue: #2196F3;
            --dark-blue: #1565C0;
            --success-color: #4CAF50;
            --danger-color: #f72585;
            --warning-color: #FF9800;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --border-radius: 16px;
            --box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --osbt-light-green: #e8f5e9;
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.2);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--osbt-light-green) !important;
            min-height: 100vh;
            overflow-x: hidden;
            color: var(--dark-color) !important;
        }
        
        .profile-container {
            min-height: 100vh;
            padding: 30px;
        }
        
        /* Profile Header */
        .profile-header {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 3rem;
            color: white;
            position: relative;
            box-shadow: 0 10px 25px rgba(0, 200, 83, 0.3);
            transition: var(--transition);
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
        }
        
        .profile-avatar i {
            font-size: 3rem;
        }
        
        /* Cards */
        .profile-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }
        
        .profile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .profile-card h4 {
            color: var(--primary-green);
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        /* Stats */
        .stat-item {
            text-align: center;
            padding: 20px;
            background: rgba(0, 200, 83, 0.1);
            border-radius: 12px;
            transition: var(--transition);
        }
        
        .stat-item:hover {
            background: rgba(0, 200, 83, 0.2);
            transform: translateY(-3px);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-green);
            display: block;
        }
        
        .stat-label {
            color: var(--gray-color);
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        /* Forms */
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid var(--glass-border);
            background: var(--glass-bg);
            transition: var(--transition);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(0, 200, 83, 0.25);
        }
        
        .btn {
            border-radius: 12px;
            font-weight: 500;
            transition: var(--transition);
            border: none;
        }
        
        .btn-primary {
            background: var(--primary-green);
            border-color: var(--primary-green);
        }
        
        .btn-primary:hover {
            background: var(--secondary-green);
            transform: translateY(-2px);
        }
        
        .btn-outline-primary {
            border-color: var(--primary-green);
            color: var(--primary-green);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-green);
            border-color: var(--primary-green);
        }
        
        /* Navigation */
        .nav-links {
            list-style: none;
            padding: 0;
        }
        
        .nav-links li {
            margin-bottom: 12px;
        }
        
        .nav-links a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: var(--dark-color);
            text-decoration: none;
            border-radius: 12px;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background: var(--primary-green);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-links i {
            margin-right: 12px;
            width: 20px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .profile-container {
                padding: 15px;
            }
            
            .profile-header {
                padding: 25px;
            }
            
            .profile-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-3 col-md-4">
                    <div class="profile-card">
                        <h4><i class="fas fa-user me-2"></i> Navigation</h4>
                        <ul class="nav-links">
                            <li><a href="student_dashboard.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
                            <li><a href="cours_devoirs.php" onclick="showConstructionAlert(event)"><i class="fas fa-book"></i> Cours & Devoirs</a></li>
                            <li><a href="notes.php" onclick="showConstructionAlert(event)"><i class="fas fa-chart-bar"></i> Mes notes</a></li>
                            <li><a href="emploi_du_temps.php" onclick="showConstructionAlert(event)"><i class="fas fa-calendar-alt"></i> Emploi du temps</a></li>
                            <li><a href="messagerie.php" onclick="showConstructionAlert(event)"><i class="fas fa-envelope"></i> Messages</a></li>
                            <li><a href="profil.php" class="active"><i class="fas fa-user"></i> Mon profil</a></li>
                            <?php if($user_data['user_role'] === 'Admin'): ?>
                                <li><a href="admin.php" onclick="showConstructionAlert(event)"><i class="fas fa-cog"></i> Administration</a></li>
                            <?php endif; ?>
                        </ul>
                        
                        <div class="mt-4 pt-3 border-top">
                            <a href="logout.php" class="btn btn-outline-danger w-100">
                                <i class="fas fa-sign-out-alt"></i> Déconnexion
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="col-lg-9 col-md-8">
                    <!-- Profile Header -->
                    <div class="profile-header" data-aos="fade-up">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <h2><?php echo htmlspecialchars($user_data['user_prenom'] . ' ' . $user_data['user_nom']); ?></h2>
                        <p class="text-muted"><?php echo htmlspecialchars($user_data['user_role']); ?></p>
                        <p class="text-muted"><?php echo htmlspecialchars($user_data['user_noma'] ?? ''); ?></p>
                        <?php if(!empty($promotion)): ?>
                            <span class="badge bg-success">Promotion <?php echo htmlspecialchars($promotion); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Stats -->
                    <div class="row mb-4">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-item" data-aos="fade-up" data-aos-delay="100">
                                <span class="stat-number"><?php echo $stats['nb_cours'] ?? 0; ?></span>
                                <div class="stat-label">Cours suivis</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-item" data-aos="fade-up" data-aos-delay="200">
                                <span class="stat-number"><?php echo $stats['nb_notes'] ?? 0; ?></span>
                                <div class="stat-label">Notes obtenues</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-item" data-aos="fade-up" data-aos-delay="300">
                                <span class="stat-number"><?php echo round($stats['moyenne_generale'] ?? 0, 1); ?></span>
                                <div class="stat-label">Moyenne</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-item" data-aos="fade-up" data-aos-delay="400">
                                <span class="stat-number"><?php echo $stats['devoirs_rendus'] ?? 0; ?></span>
                                <div class="stat-label">Devoirs rendus</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Messages -->
                    <?php if($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Profile Information -->
                    <div class="profile-card" data-aos="fade-up" data-aos-delay="500">
                        <h4><i class="fas fa-user-edit me-2"></i> Informations personnelles</h4>
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="prenom" class="form-label">Prénom</label>
                                    <input type="text" class="form-control" id="prenom" name="prenom" 
                                           value="<?php echo htmlspecialchars($user_info['prenom'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="nom" class="form-label">Nom</label>
                                    <input type="text" class="form-control" id="nom" name="nom" 
                                           value="<?php echo htmlspecialchars($user_info['nom'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="telephone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="telephone" name="telephone" 
                                           value="<?php echo htmlspecialchars($user_info['telephone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="date_naissance" class="form-label">Date de naissance</label>
                                    <input type="date" class="form-control" id="date_naissance" name="date_naissance" 
                                           value="<?php echo htmlspecialchars($user_info['date_naissance'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="promotion" class="form-label">Promotion</label>
                                    <input type="text" class="form-control" id="promotion" name="promotion" 
                                           value="<?php echo htmlspecialchars($user_info['promotion'] ?? ''); ?>" readonly>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="adresse" class="form-label">Adresse</label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="2"><?php echo htmlspecialchars($user_info['adresse'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="bio" class="form-label">Biographie</label>
                                <textarea class="form-control" id="bio" name="bio" rows="3" placeholder="Parlez-nous de vous..."><?php echo htmlspecialchars($user_info['bio'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Mettre à jour
                            </button>
                        </form>
                    </div>
                    
                    <!-- Change Password -->
                    <div class="profile-card" data-aos="fade-up" data-aos-delay="600">
                        <h4><i class="fas fa-lock me-2"></i> Sécurité</h4>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Mot de passe actuel</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-outline-primary">
                                <i class="fas fa-key me-2"></i> Changer le mot de passe
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS Animation -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });
        
        // Password confirmation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Les mots de passe ne correspondent pas');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Interactive stats
        document.querySelectorAll('.stat-item').forEach(item => {
            item.addEventListener('click', function() {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 200);
            });
        });
        
        function showConstructionAlert(event) {
            event.preventDefault();
            alert('🚧 Cette fonctionnalité est en construction et sera bientôt disponible !');
        }
    </script>
</body>
</html>
