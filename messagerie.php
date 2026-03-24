<?php
session_start();
require_once 'config/database.php';

// === CONFIGURATION DE DÉVELOPPEMENT ===
$dev_mode = true;
$debug_mode = true;

if ($debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// === GESTION DES SESSIONS ===
if ($dev_mode && !isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ?");
        $stmt->execute([2]);
        $test_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($test_user) {
            $_SESSION['user_id'] = $test_user['id_utilisateur'];
            $_SESSION['user_prenom'] = $test_user['prenom'];
            $_SESSION['user_nom'] = $test_user['nom'];
            $_SESSION['user_role'] = $test_user['role'];
            $_SESSION['user_email'] = $test_user['email'];
            $_SESSION['identifiant_osbt'] = $test_user['identifiant_osbt'];
            $_SESSION['promotion'] = $test_user['promotion'] ?? '';
        }
    } catch (PDOException $e) {
        error_log("Erreur simulation DEV: " . $e->getMessage());
    }
}

// === VÉRIFICATION DE CONNEXION ===
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// === VARIABLES ===
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$promotion = $_SESSION['promotion'] ?? '';
$user_data = $_SESSION;

// === TRAITEMENT DU FORMULAIRE ===
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_message'])) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO messages (id_expediteur, id_destinataire, sujet, contenu, id_cours, type_message)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user_id,
                $_POST['destinataire'],
                $_POST['sujet'],
                $_POST['contenu'],
                $_POST['id_cours'] ?? null,
                $_POST['type_message'] ?? 'prive'
            ]);
            
            $success_message = "Message envoyé avec succès !";
            
        } catch (PDOException $e) {
            $error_message = "Erreur lors de l'envoi : " . $e->getMessage();
        }
    }
}

// === RÉCUPÉRATION DES DONNÉES ===
try {
    // Conversations récentes
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            CASE 
                WHEN m.id_expediteur = ? THEN m.id_destinataire 
                ELSE m.id_expediteur 
            END as other_user_id,
            u.prenom, u.nom, u.role,
            MAX(m.date_envoi) as last_message_date,
            COUNT(CASE WHEN m.id_destinataire = ? AND m.lu = 0 THEN 1 END) as unread_count
        FROM messages m
        JOIN utilisateurs u ON (
            CASE 
                WHEN m.id_expediteur = ? THEN m.id_destinataire = u.id_utilisateur
                ELSE m.id_expediteur = u.id_utilisateur
            END
        )
        WHERE (m.id_expediteur = ? OR m.id_destinataire = ?)
        GROUP BY other_user_id, u.prenom, u.nom, u.role
        ORDER BY last_message_date DESC
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Messages avec une personne spécifique
    $selected_user = $_GET['user'] ?? null;
    $messages = [];
    
    if ($selected_user) {
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   e.prenom as expediteur_prenom, e.nom as expediteur_nom,
                   d.prenom as destinataire_prenom, d.nom as destinataire_nom,
                   c.nom_cours
            FROM messages m
            JOIN utilisateurs e ON m.id_expediteur = e.id_utilisateur
            JOIN utilisateurs d ON m.id_destinataire = d.id_utilisateur
            LEFT JOIN cours c ON m.id_cours = c.id_cours
            WHERE (m.id_expediteur = ? AND m.id_destinataire = ?)
               OR (m.id_expediteur = ? AND m.id_destinataire = ?)
            ORDER BY m.date_envoi ASC
        ");
        $stmt->execute([$user_id, $selected_user, $selected_user, $user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Marquer comme lus
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET lu = 1, date_lecture = NOW() 
            WHERE id_expediteur = ? AND id_destinataire = ? AND lu = 0
        ");
        $stmt->execute([$selected_user, $user_id]);
    }
    
    // Annonces officielles
    $stmt = $pdo->prepare("
        SELECT a.*, u.prenom as auteur_prenom, u.nom as auteur_nom
        FROM annonces a
        JOIN utilisateurs u ON a.id_auteur = u.id_utilisateur
        WHERE a.destinataire_role = ? OR a.destinataire_role IS NULL OR a.destinataire_role = ''
        ORDER BY a.date_publication DESC
        LIMIT 10
    ");
    $stmt->execute([$user_role]);
    $annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Utilisateurs pour nouveau message
    $stmt = $pdo->prepare("
        SELECT id_utilisateur, prenom, nom, role, promotion
        FROM utilisateurs 
        WHERE id_utilisateur != ?
        ORDER BY role, nom, prenom
    ");
    $stmt->execute([$user_id]);
    $utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Cours pour les messages de cours
    $stmt = $pdo->prepare("
        SELECT id_cours, nom_cours
        FROM cours
        WHERE promotion = ?
        ORDER BY nom_cours
    ");
    $stmt->execute([$promotion]);
    $cours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    <title>Messagerie - OSBT Connect</title>
    
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
        
        .messaging-container {
            min-height: 100vh;
            padding: 30px;
        }
        
        /* Cards */
        .message-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }
        
        .message-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        /* Conversations List */
        .conversation-item {
            padding: 15px;
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid transparent;
            position: relative;
        }
        
        .conversation-item:hover {
            background: rgba(0, 200, 83, 0.1);
            border-color: var(--primary-green);
        }
        
        .conversation-item.active {
            background: var(--primary-green);
            color: white;
        }
        
        .unread-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        /* Messages */
        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            margin-bottom: 10px;
            position: relative;
        }
        
        .message-sent {
            background: var(--primary-green);
            color: white;
            margin-left: auto;
        }
        
        .message-received {
            background: var(--light-color);
            color: var(--dark-color);
        }
        
        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 4px;
        }
        
        /* Chat Area */
        .chat-area {
            height: 500px;
            overflow-y: auto;
            padding: 20px;
            background: var(--glass-bg);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
        }
        
        .message-input-area {
            padding: 20px;
            background: var(--glass-bg);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
            margin-top: 10px;
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
        
        /* Annonces */
        .annonce-item {
            padding: 15px;
            border-left: 4px solid var(--primary-green);
            background: rgba(0, 200, 83, 0.05);
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .annonce-urgent {
            border-left-color: var(--danger-color);
            background: rgba(247, 37, 133, 0.05);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .messaging-container {
                padding: 15px;
            }
            
            .message-bubble {
                max-width: 85%;
            }
            
            .chat-area {
                height: 400px;
            }
        }
    </style>
</head>
<body>
    <div class="messaging-container">
        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-3 col-md-4">
                    <div class="message-card">
                        <h4><i class="fas fa-envelope me-2"></i> Navigation</h4>
                        <ul class="nav-links">
                            <li><a href="dashboard.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
                            <li><a href="cours_devoirs.php"><i class="fas fa-book"></i> Cours & Devoirs</a></li>
                            <li><a href="notes.php"><i class="fas fa-chart-bar"></i> Mes notes</a></li>
                            <li><a href="emploi_du_temps.php"><i class="fas fa-calendar-alt"></i> Emploi du temps</a></li>
                            <li><a href="messagerie.php" class="active"><i class="fas fa-envelope"></i> Messages</a></li>
                            <li><a href="profil.php"><i class="fas fa-user"></i> Mon profil</a></li>
                            <?php if($user_data['user_role'] === 'Admin'): ?>
                                <li><a href="admin.php"><i class="fas fa-cog"></i> Administration</a></li>
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
                    
                    <div class="row">
                        <!-- Conversations List -->
                        <div class="col-lg-4">
                            <div class="message-card" data-aos="fade-right">
                                <h5><i class="fas fa-comments me-2"></i> Conversations</h5>
                                
                                <?php if(!empty($conversations)): ?>
                                    <?php foreach($conversations as $conv): ?>
                                        <div class="conversation-item <?php echo ($selected_user == $conv['other_user_id']) ? 'active' : ''; ?>"
                                             onclick="location.href='?user=<?php echo $conv['other_user_id']; ?>'">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($conv['prenom'] . ' ' . $conv['nom']); ?></strong>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($conv['role']); ?></div>
                                                    <div class="small text-muted">
                                                        <?php echo date('d/m H:i', strtotime($conv['last_message_date'])); ?>
                                                    </div>
                                                </div>
                                                <?php if($conv['unread_count'] > 0): ?>
                                                    <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">
                                        <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                        Aucune conversation
                                    </p>
                                <?php endif; ?>
                                
                                <button class="btn btn-primary w-100 mt-3" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                                    <i class="fas fa-plus me-2"></i> Nouveau message
                                </button>
                            </div>
                        </div>
                        
                        <!-- Chat Area -->
                        <div class="col-lg-8">
                            <?php if($selected_user): ?>
                                <!-- Messages -->
                                <div class="chat-area" id="chatArea">
                                    <?php if(!empty($messages)): ?>
                                        <?php foreach($messages as $msg): ?>
                                            <div class="message-bubble <?php echo ($msg['id_expediteur'] == $user_id) ? 'message-sent' : 'message-received'; ?>">
                                                <div><?php echo htmlspecialchars($msg['contenu']); ?></div>
                                                <div class="message-time">
                                                    <?php echo date('H:i', strtotime($msg['date_envoi'])); ?>
                                                    <?php if($msg['id_expediteur'] != $user_id && $msg['date_lecture']): ?>
                                                        <i class="fas fa-check-double text-primary ms-1"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center text-muted py-5">
                                            <i class="fas fa-comments fa-3x mb-3"></i>
                                            <p>Aucun message dans cette conversation</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Message Input -->
                                <div class="message-input-area">
                                    <form method="POST" id="messageForm">
                                        <div class="input-group">
                                            <input type="hidden" name="destinataire" value="<?php echo $selected_user; ?>">
                                            <input type="hidden" name="sujet" value="Message">
                                            <input type="hidden" name="type_message" value="prive">
                                            <textarea class="form-control" name="contenu" placeholder="Tapez votre message..." rows="2" required></textarea>
                                            <button class="btn btn-primary" type="submit" name="send_message">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php else: ?>
                                <!-- Welcome Screen -->
                                <div class="message-card text-center py-5">
                                    <i class="fas fa-comments fa-4x mb-3 text-primary"></i>
                                    <h4>Bienvenue dans la messagerie</h4>
                                    <p class="text-muted">Sélectionnez une conversation pour commencer à discuter</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                                        <i class="fas fa-plus me-2"></i> Nouveau message
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Annonces Officielles -->
                    <div class="message-card mt-4" data-aos="fade-up">
                        <h5><i class="fas fa-bullhorn me-2"></i> Annonces officielles</h5>
                        
                        <?php if(!empty($annonces)): ?>
                            <?php foreach($annonces as $annonce): ?>
                                <div class="annonce-item <?php echo $annonce['est_urgent'] ? 'annonce-urgent' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($annonce['titre']); ?>
                                                <?php if($annonce['est_urgent']): ?>
                                                    <span class="badge bg-danger ms-2">Urgent</span>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="mb-1"><?php echo htmlspecialchars($annonce['contenu']); ?></p>
                                            <small class="text-muted">
                                                Par <?php echo htmlspecialchars($annonce['auteur_prenom'] . ' ' . $annonce['auteur_nom']); ?>
                                                • <?php echo date('d/m/Y H:i', strtotime($annonce['date_publication'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">Aucune annonce officielle</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-envelope me-2"></i> Nouveau message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="destinataire" class="form-label">Destinataire</label>
                            <select class="form-select" id="destinataire" name="destinataire" required>
                                <option value="">Choisir un destinataire</option>
                                <?php foreach($utilisateurs as $user): ?>
                                    <option value="<?php echo $user['id_utilisateur']; ?>">
                                        <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?> 
                                        (<?php echo htmlspecialchars($user['role']); ?>)
                                        <?php if(!empty($user['promotion'])): ?>
                                            - Promo <?php echo htmlspecialchars($user['promotion']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="sujet" class="form-label">Sujet</label>
                            <input type="text" class="form-control" id="sujet" name="sujet" required>
                        </div>
                        <div class="mb-3">
                            <label for="id_cours" class="form-label">Cours (optionnel)</label>
                            <select class="form-select" id="id_cours" name="id_cours">
                                <option value="">Message général</option>
                                <?php foreach($cours as $cour): ?>
                                    <option value="<?php echo $cour['id_cours']; ?>">
                                        <?php echo htmlspecialchars($cour['nom_cours']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="contenu" class="form-label">Message</label>
                            <textarea class="form-control" id="contenu" name="contenu" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="send_message" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i> Envoyer
                        </button>
                    </div>
                </form>
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
        
        // Auto-scroll chat to bottom
        const chatArea = document.getElementById('chatArea');
        if (chatArea) {
            chatArea.scrollTop = chatArea.scrollHeight;
        }
        
        // Auto-refresh for new messages
        setInterval(() => {
            if (window.location.search.includes('user=')) {
                // Optionnel: recharger les messages toutes les 30 secondes
                // location.reload();
            }
        }, 30000);
        
        // Character counter for message
        const messageTextarea = document.querySelector('textarea[name="contenu"]');
        if (messageTextarea) {
            messageTextarea.addEventListener('input', function() {
                const maxLength = 500;
                if (this.value.length > maxLength) {
                    this.value = this.value.substring(0, maxLength);
                }
            });
        }
        
        // Interactive elements
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.addEventListener('click', function() {
                // Animation au clic
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 100);
            });
        });
    </script>
</body>
</html>
