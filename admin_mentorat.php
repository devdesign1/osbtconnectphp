<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Vérifier que l'utilisateur est admin
if ($_SESSION['user_role'] !== 'admin') {
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
    // === STATISTIQUES MENTORAT ===
    $stats = [
        'total_sessions' => $pdo->query("SELECT COUNT(*) as count FROM sessions_mentorat")->fetch()['count'],
        'active_mentors' => $pdo->query("SELECT COUNT(DISTINCT mentor_id) as count FROM sessions_mentorat WHERE statut = 'session_terminee'")->fetch()['count'],
        'pending_sessions' => $pdo->query("SELECT COUNT(*) as count FROM sessions_mentorat WHERE statut = 'demande_envoyee'")->fetch()['count'],
        'completed_sessions' => $pdo->query("SELECT COUNT(*) as count FROM sessions_mentorat WHERE statut = 'session_terminee'")->fetch()['count'],
    ];
    
    // === SESSIONS RÉCENTES ===
    $stmt = $pdo->prepare("
        SELECT 
            sm.*,
            CONCAT(e.prenom, ' ', e.nom) as etudiant_nom,
            e.role as etudiant_role,
            e.classe_id,
            c.nom as classe_nom,
            CONCAT(m.prenom, ' ', m.nom) as mentor_nom,
            mat.nom as matiere_nom,
            sm.date_session,
            sm.heure_debut,
            sm.heure_fin,
            sm.lieu,
            sm.notes,
            sm.statut,
            TIMESTAMPDIFF(HOUR, NOW(), sm.date_session) as heures_restantes
        FROM sessions_mentorat sm
        JOIN utilisateurs e ON sm.etudiant_id = e.id_utilisateur
        JOIN utilisateurs m ON sm.mentor_id = m.id_utilisateur
        LEFT JOIN classes c ON e.classe_id = c.id_classe
        LEFT JOIN matieres mat ON sm.matiere_id = mat.id_matiere
        WHERE sm.date_session >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY sm.date_session DESC, sm.heure_debut DESC
        LIMIT 20
    ");
    $stmt->execute();
    $sessions = $stmt->fetchAll();
    
    // === MENTORS ACTIFS ===
    $stmt = $pdo->query("
        SELECT 
            u.id_utilisateur,
            CONCAT(u.prenom, ' ', u.nom) as mentor_nom,
            u.role,
            COUNT(DISTINCT sm.id_session) as nb_sessions,
            COUNT(DISTINCT CASE WHEN sm.statut = 'session_terminee' THEN sm.id_session END) as nb_sessions_terminees,
            AVG(CASE WHEN sm.statut = 'session_terminee' THEN 1 ELSE 0 END) * 100 as taux_completion
        FROM utilisateurs u
        LEFT JOIN sessions_mentorat sm ON u.id_utilisateur = sm.mentor_id
        WHERE u.role IN ('tech', 'business')
        AND u.est_actif = 1
        GROUP BY u.id_utilisateur
        ORDER BY nb_sessions DESC
        LIMIT 10
    ");
    $mentors = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $sessions = [];
    $mentors = [];
    $total_sessions = 0;
    $active_mentors = 0;
    $pending_sessions = 0;
    $completed_sessions = 0;
    error_log("Erreur mentorat admin: " . $e->getMessage());
}

// Définir les valeurs par défaut si les requêtes échouent
if (!isset($total_sessions)) $total_sessions = 0;
if (!isset($active_mentors)) $active_mentors = 0;
if (!isset($pending_sessions)) $pending_sessions = 0;
if (!isset($completed_sessions)) $completed_sessions = 0;

$stats = [
    'total_sessions' => $total_sessions,
    'active_mentors' => $active_mentors,
    'pending_sessions' => $pending_sessions,
    'completed_sessions' => $completed_sessions,
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du Mentorat - OSBT Connect</title>
    
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--osbt-primary);
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .stat-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--osbt-primary);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--osbt-primary);
            margin-bottom: var(--spacing-sm);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 500;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
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
        
        .sessions-list {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }
        
        .session-item {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .session-item:hover {
            background: var(--gray-100);
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
            background: var(--gray-200);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-weight: 500;
        }
        
        .session-meta {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .mentors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-md);
        }
        
        .mentor-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            transition: all 0.3s ease;
        }
        
        .mentor-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--osbt-primary);
        }
        
        .mentor-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-sm);
        }
        
        .mentor-avatar {
            width: 40px;
            height: 40px;
            background: var(--osbt-blue);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 600;
        }
        
        .mentor-name {
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .mentor-stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .status-badge {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: var(--osbt-gray);
            color: var(--white);
        }
        
        .status-confirmed {
            background: var(--osbt-blue);
            color: var(--white);
        }
        
        .status-completed {
            background: var(--osbt-primary);
            color: var(--white);
        }
        
        .btn {
            padding: var(--spacing-sm) var(--spacing-md);
            border: none;
            border-radius: var(--radius-md);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: var(--osbt-primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: var(--osbt-primary-dark);
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
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <a href="admin_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Retour au dashboard
            </a>
            
            <h1 class="header-title">Gestion du Mentorat</h1>
            <button class="btn btn-primary" onclick="showPageNotAvailable(event, 'La planification du mentorat sera bientôt disponible !')">
                <i class="fas fa-plus"></i>
                Nouvelle session
            </button>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_sessions']; ?></div>
                <div class="stat-label">Total sessions</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['active_mentors']; ?></div>
                <div class="stat-label">Mentors actifs</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['pending_sessions']; ?></div>
                <div class="stat-label">En attente</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['completed_sessions']; ?></div>
                <div class="stat-label">Terminées</div>
            </div>
        </div>
        
        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Sessions Section -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-calendar-check" style="margin-right: var(--spacing-sm); color: var(--osbt-primary);"></i>
                        Sessions récentes
                    </h2>
                </div>
                
                <div class="sessions-list">
                    <?php if (empty($sessions)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3>Aucune session</h3>
                            <p>Aucune session de mentorat n'est programmée pour les 30 prochains jours.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sessions as $session): ?>
                            <div class="session-item">
                                <div class="session-header">
                                    <div class="session-info">
                                        <div class="session-title">
                                            <?php echo htmlspecialchars($session['etudiant_nom']); ?>
                                            <span class="session-subject"><?php echo htmlspecialchars($session['matiere_nom']); ?></span>
                                        </div>
                                        
                                        <span class="status-badge <?php echo $session['statut']; ?>">
                                            <?php 
                                                $status_map = [
                                                    'demande_envoyee' => 'En attente',
                                                    'demande_acceptee' => 'Confirmée',
                                                    'session_terminee' => 'Terminée'
                                                ];
                                                echo $status_map[$session['statut']] ?? $session['statut'];
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="session-meta">
                                    <div>
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('d/m/Y', strtotime($session['date_session'])); ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('H:i', strtotime($session['heure_debut'])); ?> - <?php echo date('H:i', strtotime($session['heure_fin'])); ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($session['lieu']); ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-user-tie"></i>
                                        <?php echo htmlspecialchars($session['mentor_nom']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Mentors Section -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-handshake" style="margin-right: var(--spacing-sm); color: var(--osbt-blue);"></i>
                        Top Mentors
                    </h2>
                </div>
                
                <div class="mentors-grid">
                    <?php if (empty($mentors)): ?>
                        <div class="empty-state">
                            <i class="fas fa-user-tie"></i>
                            <h3>Aucun mentor</h3>
                            <p>Aucun mentor n'est actuellement actif sur la plateforme.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($mentors as $mentor): ?>
                            <div class="mentor-card">
                                <div class="mentor-header">
                                    <div class="mentor-avatar">
                                        <?php echo strtoupper(substr($mentor['mentor_nom'], 0, 1)); ?>
                                    </div>
                                    <div class="mentor-name"><?php echo htmlspecialchars($mentor['mentor_nom']); ?></div>
                                </div>
                                
                                <div class="mentor-stats">
                                    <span><?php echo $mentor['nb_sessions']; ?> sessions</span>
                                    <span><?php echo $mentor['nb_sessions_terminees']; ?> terminées</span>
                                    <span><?php echo round($mentor['taux_completion']); ?>% complétion</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showPageNotAvailable(event, message) {
            event.preventDefault();
            alert(message);
        }
    </script>
</body>
</html>
