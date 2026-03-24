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
    // === STATISTIQUES ANALYTICS ===
    $total_users_query = $pdo->query("SELECT COUNT(*) as count FROM utilisateurs WHERE est_actif = 1");
    $total_users = $total_users_query ? $total_users_query->fetch()['count'] : 0;
    
    $total_students_query = $pdo->query("SELECT COUNT(*) as count FROM utilisateurs WHERE est_actif = 1 AND role = 'etudiant'");
    $total_students = $total_students_query ? $total_students_query->fetch()['count'] : 0;
    
    $total_mentors_query = $pdo->query("SELECT COUNT(*) as count FROM utilisateurs WHERE est_actif = 1 AND role IN ('tech', 'business')");
    $total_mentors = $total_mentors_query ? $total_mentors_query->fetch()['count'] : 0;
    
    $total_professors_query = $pdo->query("SELECT COUNT(*) as count FROM utilisateurs WHERE est_actif = 1 AND role = 'professeur'");
    $total_professors = $total_professors_query ? $total_professors_query->fetch()['count'] : 0;
    
    $total_courses_query = $pdo->query("SELECT COUNT(*) as count FROM planning_cours");
    $total_courses = $total_courses_query ? $total_courses_query->fetch()['count'] : 0;
    
    $total_decks_query = $pdo->query("SELECT COUNT(*) as count FROM decks WHERE est_valide = 1");
    $total_decks = $total_decks_query ? $total_decks_query->fetch()['count'] : 0;
    
    $total_flashcards_query = $pdo->query("SELECT COUNT(*) as count FROM flashcards");
    $total_flashcards = $total_flashcards_query ? $total_flashcards_query->fetch()['count'] : 0;
    
    $total_sessions_query = $pdo->query("SELECT COUNT(*) as count FROM sessions_mentorat");
    $total_sessions = $total_sessions_query ? $total_sessions_query->fetch()['count'] : 0;
    
    $stats = [
        'total_users' => $total_users,
        'total_students' => $total_students,
        'total_mentors' => $total_mentors,
        'total_professors' => $total_professors,
        'total_courses' => $total_courses,
        'total_decks' => $total_decks,
        'total_flashcards' => $total_flashcards,
        'total_sessions' => $total_sessions,
    ];
    
    // === ACTIVITÉ RÉCENTE ===
    $stmt = $pdo->prepare("
        SELECT 
            u.prenom,
            u.nom,
            u.role,
            u.derniere_connexion,
            CASE 
                WHEN u.derniere_connexion >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'Aujourd\'hui'
                WHEN u.derniere_connexion >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'Cette semaine'
                ELSE 'Ancien'
            END as statut
        FROM utilisateurs u
        WHERE u.est_actif = 1
        ORDER BY u.derniere_connexion DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_activity = $stmt->fetchAll();
    
    // === UTILISATION PAR RÔLE ===
    $role_stats = $pdo->query("
        SELECT 
            role,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM utilisateurs WHERE est_actif = 1), 2) as percentage
        FROM utilisateurs 
        WHERE est_actif = 1
        GROUP BY role
        ORDER BY count DESC
    ")->fetchAll();
    
} catch (PDOException $e) {
    $recent_activity = [];
    $role_stats = [];
    error_log("Erreur analytics admin: " . $e->getMessage());
}

// Définir les valeurs par défaut si les requêtes échouent
if (!isset($total_users)) $total_users = 0;
if (!isset($total_students)) $total_students = 0;
if (!isset($total_mentors)) $total_mentors = 0;
if (!isset($total_professors)) $total_professors = 0;
if (!isset($total_courses)) $total_courses = 0;
if (!isset($total_decks)) $total_decks = 0;
if (!isset($total_flashcards)) $total_flashcards = 0;
if (!isset($total_sessions)) $total_sessions = 0;

$stats = [
    'total_users' => $total_users,
    'total_students' => $total_students,
    'total_mentors' => $total_mentors,
    'total_professors' => $total_professors,
    'total_courses' => $total_courses,
    'total_decks' => $total_decks,
    'total_flashcards' => $total_flashcards,
    'total_sessions' => $total_sessions,
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - OSBT Connect</title>
    
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: var(--osbt-primary);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--spacing-md);
            font-size: 1.5rem;
            color: var(--white);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--osbt-primary);
            margin-bottom: var(--spacing-sm);
        }
        
        .stat-label {
            font-size: 1rem;
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
        
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-md);
            background: var(--gray-50);
            border-radius: var(--radius-md);
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            background: var(--gray-100);
        }
        
        .activity-avatar {
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
        
        .activity-info {
            flex: 1;
        }
        
        .activity-name {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-xs);
        }
        
        .activity-meta {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .role-chart {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }
        
        .role-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-sm);
            background: var(--gray-50);
            border-radius: var(--radius-md);
        }
        
        .role-label {
            flex: 1;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .role-bar {
            width: 100px;
            height: 20px;
            background: var(--gray-200);
            border-radius: var(--radius-full);
            overflow: hidden;
        }
        
        .role-fill {
            height: 100%;
            background: var(--osbt-primary);
            transition: width 1s ease;
        }
        
        .role-percentage {
            font-weight: 600;
            color: var(--osbt-primary);
            min-width: 50px;
            text-align: right;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: var(--spacing-md);
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content-grid {
                grid-template-columns: 1fr;
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
            
            <h1 class="header-title">Analytics</h1>
            <p style="color: var(--gray-600);">Statistiques détaillées de la plateforme</p>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Utilisateurs totaux</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                <div class="stat-label">Étudiants</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_professors']; ?></div>
                <div class="stat-label">Professeurs</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-handshake"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_mentors']; ?></div>
                <div class="stat-label">Mentors</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_courses']; ?></div>
                <div class="stat-label">Cours</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_decks']; ?></div>
                <div class="stat-label">Decks</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-brain"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_flashcards']; ?></div>
                <div class="stat-label">Flashcards</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_sessions']; ?></div>
                <div class="stat-label">Sessions mentorat</div>
            </div>
        </div>
        
        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Activity Section -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-clock" style="margin-right: var(--spacing-sm); color: var(--osbt-primary);"></i>
                        Activité récente
                    </h2>
                </div>
                
                <div class="activity-list">
                    <?php if (empty($recent_activity)): ?>
                        <div style="text-align: center; padding: var(--spacing-xl); color: var(--gray-500);">
                            <i class="fas fa-user-clock" style="font-size: 2rem; margin-bottom: var(--spacing-md); opacity: 0.5;"></i>
                            <h3>Aucune activité récente</h3>
                            <p>Aucune connexion utilisateur récente enregistrée.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-avatar">
                                    <?php echo strtoupper(substr($activity['prenom'], 0, 1)); ?>
                                </div>
                                <div class="activity-info">
                                    <div class="activity-name">
                                        <?php echo htmlspecialchars($activity['prenom'] . ' ' . $activity['nom']); ?>
                                        <span style="margin-left: var(--spacing-sm); font-size: 0.875rem; color: var(--gray-600);">
                                            (<?php echo ucfirst($activity['role']); ?>)
                                        </span>
                                    </div>
                                    <div class="activity-meta">
                                        <?php echo $activity['statut']; ?> • 
                                        <?php echo date('d/m/Y H:i', strtotime($activity['derniere_connexion'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Role Distribution -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-chart-pie" style="margin-right: var(--spacing-sm); color: var(--osbt-blue);"></i>
                        Distribution par rôle
                    </h2>
                </div>
                
                <div class="role-chart">
                    <?php if (empty($role_stats)): ?>
                        <div style="text-align: center; padding: var(--spacing-xl); color: var(--gray-500);">
                            <i class="fas fa-chart-pie" style="font-size: 2rem; margin-bottom: var(--spacing-md); opacity: 0.5;"></i>
                            <h3>Aucune donnée</h3>
                            <p>Aucune statistique de rôle disponible.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($role_stats as $role): ?>
                            <div class="role-item">
                                <div class="role-label">
                                    <?php echo ucfirst($role['role']); ?>
                                </div>
                                <div class="role-bar">
                                    <div class="role-fill" style="width: <?php echo $role['percentage']; ?>%;"></div>
                                </div>
                                <div class="role-percentage">
                                    <?php echo $role['percentage']; ?>%
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
