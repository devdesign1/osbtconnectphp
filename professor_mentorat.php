<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Vérifier que l'utilisateur est professeur
if ($_SESSION['user_role'] !== 'professeur') {
    header('Location: student_dashboard.php');
    exit();
}

// === CONNEXION DB ===
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=osbtconnect2;charset=utf8mb4",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'];
$user_prenom = $_SESSION['user_prenom'];

try {
    // === SESSIONS MENTORAT EN ATTENTE ===
    $stmt = $pdo->prepare("
        SELECT 
            sm.*,
            CONCAT(u.prenom, ' ', u.nom) as etudiant_nom,
            m.nom as matiere_nom,
            u.classe_id,
            c.nom as classe_nom,
            sm.date_session,
            sm.heure_debut,
            sm.heure_fin,
            sm.lieu,
            sm.notes,
            sm.statut,
            TIMESTAMPDIFF(HOUR, NOW(), sm.date_session) as heures_restantes,
            CASE 
                WHEN sm.date_session < NOW() THEN 'en_retard'
                WHEN sm.date_session <= DATE_ADD(NOW(), INTERVAL 1 DAY) THEN 'urgent'
                ELSE 'planifie'
            END as priorite
        FROM sessions_mentorat sm
        JOIN utilisateurs u ON sm.etudiant_id = u.id_utilisateur
        JOIN matieres m ON sm.matiere_id = m.id_matiere
        LEFT JOIN classes c ON u.classe_id = c.id_classe
        WHERE sm.mentor_id = ?
        AND sm.date_session >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY sm.date_session ASC, sm.heure_debut ASC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $sessions_en_attente = $stmt->fetchAll();
    
    // === SESSIONS À VENIR ===
    $stmt = $pdo->prepare("
        SELECT 
            sm.*,
            CONCAT(u.prenom, ' ', u.nom) as etudiant_nom,
            m.nom as matiere_nom,
            u.classe_id,
            c.nom as classe_nom,
            sm.date_session,
            sm.heure_debut,
            sm.heure_fin,
            sm.lieu,
            sm.statut
        FROM sessions_mentorat sm
        JOIN utilisateurs u ON sm.etudiant_id = u.id_utilisateur
        JOIN matieres m ON sm.matiere_id = m.id_matiere
        LEFT JOIN classes c ON u.classe_id = c.id_classe
        WHERE sm.mentor_id = ?
        AND sm.date_session > NOW()
        AND sm.statut IN ('demande_envoyee', 'demande_acceptee')
        ORDER BY sm.date_session ASC, sm.heure_debut ASC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $sessions_a_venir = $stmt->fetchAll();
    
    // === SESSIONS PASSÉES ===
    $stmt = $pdo->prepare("
        SELECT 
            sm.*,
            CONCAT(u.prenom, ' ', u.nom) as etudiant_nom,
            m.nom as matiere_nom,
            u.classe_id,
            c.nom as classe_nom,
            sm.date_session,
            sm.heure_debut,
            sm.heure_fin,
            sm.lieu,
            sm.notes,
            sm.statut
        FROM sessions_mentorat sm
        JOIN utilisateurs u ON sm.etudiant_id = u.id_utilisateur
        JOIN matieres m ON sm.matiere_id = m.id_matiere
        LEFT JOIN classes c ON u.classe_id = c.id_classe
        WHERE sm.mentor_id = ?
        AND sm.statut = 'session_terminee'
        ORDER BY sm.date_session DESC, sm.heure_debut DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $sessions_passees = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $sessions_en_attente = [];
    $sessions_a_venir = [];
    $sessions_passees = [];
    error_log("Erreur mentorat professeur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentorat - OSBT Connect</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Palette OSBT */
            --osbt-primary: #00C853;
            --osbt-primary-dark: #2E7D32;
            --osbt-blue: #2196F3;
            --osbt-light: #f8fafc;
            --osbt-dark: #0f172a;
            --osbt-gray: #64748b;
            --osbt-gray-light: #e2e8f0;
            
            /* Neutrals */
            --white: #FFFFFF;
            --gray-50: #f8f9fa;
            --gray-100: #e9ecef;
            --gray-200: #dee2e6;
            --gray-300: #ced4da;
            --gray-400: #adb5bd;
            --gray-500: #6c757d;
            --gray-600: #495057;
            --gray-700: #343a40;
            --gray-800: #212529;
            --gray-900: #111827;
            
            /* Spacing */
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;
            
            /* Border radius */
            --radius-sm: 0.25rem;
            --radius-md: 0.5rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-full: 9999px;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }
        
        .header {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-md);
        }
        
        .header-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--osbt-primary);
            margin-bottom: var(--spacing-md);
        }
        
        .header-subtitle {
            color: var(--gray-600);
            font-size: 1rem;
        }
        
        .tabs {
            display: flex;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-lg);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .tab {
            padding: var(--spacing-sm) var(--spacing-md);
            background: none;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--gray-600);
        }
        
        .tab.active {
            background: var(--osbt-primary);
            color: var(--white);
        }
        
        .tab:hover {
            background: var(--gray-100);
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: var(--spacing-xl);
        }
        
        .section-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .session-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: var(--spacing-md);
        }
        
        .session-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .session-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--osbt-primary);
        }
        
        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-md);
        }
        
        .session-info {
            flex: 1;
        }
        
        .session-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-sm);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .session-subject {
            font-size: 0.875rem;
            color: var(--gray-600);
            background: var(--gray-100);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-weight: 500;
        }
        
        .session-meta {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .priority-badge {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .priority-urgent {
            background: var(--osbt-primary);
            color: var(--white);
        }
        
        .priority-late {
            background: var(--gray-700);
            color: var(--white);
        }
        
        .priority-planned {
            background: var(--osbt-blue);
            color: var(--white);
        }
        
        .status-badge {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-confirmed {
            background: var(--osbt-primary);
            color: var(--white);
        }
        
        .status-pending {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            color: var(--osbt-primary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            color: var(--osbt-primary-dark);
        }
        
        .empty-state {
            text-align: center;
            padding: var(--spacing-2xl);
            color: var(--gray-500);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: var(--spacing-md);
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: var(--spacing-md);
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .session-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <a href="professor_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Retour au dashboard
            </a>
            
            <h1 class="header-title">Sessions Mentorat</h1>
            <p class="header-subtitle">Gérez les sessions de mentorat avec vos étudiants</p>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('en_attente')">En attente (<?php echo count($sessions_en_attente); ?>)</button>
            <button class="tab" onclick="showTab('a_venir')">À venir (<?php echo count($sessions_a_venir); ?>)</button>
            <button class="tab" onclick="showTab('passees')">Passées (<?php echo count($sessions_passees); ?>)</button>
        </div>
        
        <div class="content-grid">
            <!-- Sessions en attente -->
            <div class="section-card" id="en_attente" style="display: block;">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-clock" style="margin-right: var(--spacing-sm); color: var(--osbt-primary);"></i>
                        Sessions en attente
                    </h2>
                    <span style="color: var(--gray-600); font-size: 0.875rem;">
                            <?php echo count($sessions_en_attente); ?> sessions
                    </span>
                </div>
            </div>
                
                <?php if (empty($sessions_en_attente)): ?>
                    <div class="empty-state">
                        <i class="fas fa-handshake"></i>
                        <h3>Aucune session en attente</h3>
                        <p>Vous n'avez pas de sessions de mentorat en attente de validation.</p>
                    </div>
                <?php else: ?>
                    <div class="session-grid">
                        <?php foreach ($sessions_en_attente as $session): ?>
                            <div class="session-card">
                                <div class="session-header">
                                    <div class="session-info">
                                        <div class="session-title">
                                            <?php echo htmlspecialchars($session['etudiant_nom']); ?>
                                            <span class="session-subject"><?php echo htmlspecialchars($session['matiere_nom']); ?></span>
                                        </div>
                                        
                                        <div class="priority-badge <?php echo $session['priorite']; ?>">
                                            <?php echo ucfirst($session['priorite']); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="session-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('d/m/Y', strtotime($session['date_session'])); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('H:i', strtotime($session['heure_debut'])); ?> - <?php echo date('H:i', strtotime($session['heure_fin'])); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($session['lieu']); ?>
                                    </div>
                                </div>
                                
                                <div class="session-meta">
                                    <div class="meta-item">
                                        <span class="status-badge <?php echo $session['statut']; ?>">
                                            <?php echo ucfirst($session['statut']); ?>
                                        </span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-hourglass-half"></i>
                                        <?php echo $session['heures_restantes']; ?>h
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Sessions à venir -->
            <div class="section-card" id="a_venir" style="display: none;">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-calendar-plus" style="margin-right: var(--spacing-sm); color: var(--osbt-blue);"></i>
                        Sessions à venir
                    </h2>
                    <span style="color: var(--gray-600); font-size: 0.875rem;">
                            <?php echo count($sessions_a_venir); ?> sessions
                    </span>
                </div>
            </div>
                
                <?php if (empty($sessions_a_venir)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <h3>Aucune session à venir</h3>
                        <p>Vous n'avez pas de sessions de mentorat planifiées.</p>
                    </div>
                <?php else: ?>
                    <div class="session-grid">
                        <?php foreach ($sessions_a_venir as $session): ?>
                            <div class="session-card">
                                <div class="session-header">
                                    <div class="session-info">
                                        <div class="session-title">
                                            <?php echo htmlspecialchars($session['etudiant_nom']); ?>
                                            <span class="session-subject"><?php echo htmlspecialchars($session['matiere_nom']); ?></span>
                                        </div>
                                        
                                        <div class="priority-badge priority-planned">
                                            Planifié
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="session-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('d/m/Y', strtotime($session['date_session'])); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('H:i', strtotime($session['heure_debut'])); ?> - <?php echo date('H:i', strtotime($session['heure_fin'])); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($session['lieu']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Sessions passées -->
            <div class="section-card" id="passees" style="display: none;">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-history" style="margin-right: var(--spacing-sm); color: var(--gray-600);"></i>
                        Sessions passées
                    </h2>
                    <span style="color: var(--gray-600); font-size: 0.875rem;">
                            <?php echo count($sessions_passees); ?> sessions
                    </span>
                </div>
            </div>
                
                <?php if (empty($sessions_passees)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>Aucune session passée</h3>
                        <p>Vous n'avez pas encore de sessions de mentorat terminées.</p>
                    </div>
                <?php else: ?>
                    <div class="session-grid">
                        <?php foreach ($sessions_passees as $session): ?>
                            <div class="session-card">
                                <div class="session-header">
                                    <div class="session-info">
                                        <div class="session-title">
                                            <?php echo htmlspecialchars($session['etudiant_nom']); ?>
                                            <span class="session-subject"><?php echo htmlspecialchars($session['matiere_nom']); ?></span>
                                        </div>
                                        
                                        <div class="status-badge status-confirmed">
                                            Terminée
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="session-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('d/m/Y', strtotime($session['date_session'])); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('H:i', strtotime($session['heure_debut'])); ?> - <?php echo date('H:i', strtotime($session['heure_fin'])); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($session['lieu']); ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($session['notes'])): ?>
                                    <div class="session-meta" style="margin-top: var(--spacing-md);">
                                        <div class="meta-item" style="flex: 1;">
                                            <i class="fas fa-sticky-note"></i>
                                            <span>Notes: <?php echo htmlspecialchars($session['notes']); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabId) {
            // Cacher tous les contenus
            document.querySelectorAll('.section-card').forEach(card => {
                card.style.display = 'none';
            });
            
            // Désactiver tous les onglets
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Afficher le contenu sélectionné
            document.getElementById(tabId).style.display = 'block';
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
