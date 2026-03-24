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
    // === COURS ET PLANNING ===
    $stmt = $pdo->prepare("
        SELECT 
            pc.*,
            m.nom as matiere_nom,
            m.couleur_hex,
            p.nom as professeur_nom,
            p.prenom as professeur_prenom,
            COUNT(DISTINCT u.id_utilisateur) as nb_etudiants_inscrits
        FROM planning_cours pc
        LEFT JOIN matieres m ON pc.matiere_id = m.id_matiere
        LEFT JOIN professeurs p ON pc.professeur_id = p.id_professeur
        LEFT JOIN utilisateurs u ON u.classe_id IN (
            SELECT id_classe FROM classes WHERE matiere_id = pc.matiere_id
        ) AND u.role = 'etudiant' AND u.est_actif = 1
        WHERE pc.date_seance >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY pc.id_cours
        ORDER BY pc.date_seance DESC, pc.heure_debut DESC
        LIMIT 20
    ");
    $stmt->execute();
    $courses = $stmt->fetchAll();
    
    // === STATISTIQUES COURS ===
    $total_courses = $pdo->query("SELECT COUNT(*) as count FROM planning_cours")->fetch()['count'];
    $courses_today = $pdo->query("SELECT COUNT(*) as count FROM planning_cours WHERE DATE(date_seance) = CURDATE()")->fetch()['count'];
    $courses_week = $pdo->query("SELECT COUNT(*) as count FROM planning_cours WHERE date_seance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetch()['count'];
    $total_matieres = $pdo->query("SELECT COUNT(*) as count FROM matieres")->fetch()['count'];
    
    $stats = [
        'total_courses' => $total_courses,
        'courses_today' => $courses_today,
        'courses_week' => $courses_week,
        'total_matieres' => $total_matieres,
    ];
    
    // === MATIÈRES ===
    $stmt = $pdo->query("
        SELECT 
            m.*,
            COUNT(DISTINCT pc.id_cours) as nb_cours,
            COUNT(DISTINCT p.id_professeur) as nb_professeurs
        FROM matieres m
        LEFT JOIN planning_cours pc ON m.id_matiere = pc.matiere_id
        LEFT JOIN professeurs p ON p.matiere_id = m.id_matiere
        GROUP BY m.id_matiere
        ORDER BY m.nom
    ");
    $matieres = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $courses = [];
    $matieres = [];
    $total_courses = 0;
    $courses_today = 0;
    $courses_week = 0;
    $total_matieres = 0;
    error_log("Erreur cours admin: " . $e->getMessage());
}

// Définir les valeurs par défaut si les requêtes échouent
if (!isset($total_courses)) $total_courses = 0;
if (!isset($courses_today)) $courses_today = 0;
if (!isset($courses_week)) $courses_week = 0;
if (!isset($total_matieres)) $total_matieres = 0;

$stats = [
    'total_courses' => $total_courses,
    'courses_today' => $courses_today,
    'courses_week' => $courses_week,
    'total_matieres' => $total_matieres,
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Cours - OSBT Connect</title>
    
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
        
        .courses-list {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }
        
        .course-item {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .course-item:hover {
            background: var(--gray-100);
            border-color: var(--osbt-primary);
        }
        
        .course-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--osbt-primary);
        }
        
        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-sm);
        }
        
        .course-info {
            flex: 1;
        }
        
        .course-title {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-xs);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .course-subject {
            font-size: 0.875rem;
            color: var(--gray-600);
            background: var(--gray-200);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-weight: 500;
        }
        
        .course-meta {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .matieres-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-md);
        }
        
        .matiere-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            transition: all 0.3s ease;
        }
        
        .matiere-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--osbt-primary);
        }
        
        .matiere-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-sm);
        }
        
        .matiere-color {
            width: 20px;
            height: 20px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-300);
        }
        
        .matiere-name {
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .matiere-stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
            color: var(--gray-600);
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
            
            <h1 class="header-title">Gestion des Cours</h1>
            <button class="btn btn-primary" onclick="showPageNotAvailable(event, 'La création de cours sera bientôt disponible !')">
                <i class="fas fa-plus"></i>
                Nouveau cours
            </button>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_courses']; ?></div>
                <div class="stat-label">Total cours</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['courses_today']; ?></div>
                <div class="stat-label">Cours aujourd'hui</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['courses_week']; ?></div>
                <div class="stat-label">Cette semaine</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_matieres']; ?></div>
                <div class="stat-label">Matières</div>
            </div>
        </div>
        
        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Courses Section -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-calendar-alt" style="margin-right: var(--spacing-sm); color: var(--osbt-primary);"></i>
                        Cours récents
                    </h2>
                </div>
                
                <div class="courses-list">
                    <?php if (empty($courses)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3>Aucun cours trouvé</h3>
                            <p>Aucun cours n'est programmé pour les 30 prochains jours.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($courses as $course): ?>
                            <div class="course-item">
                                <div class="course-header">
                                    <div class="course-info">
                                        <div class="course-title">
                                            <?php echo htmlspecialchars($course['matiere_nom']); ?>
                                            <span class="course-subject">
                                                <?php echo date('d/m', strtotime($course['date_seance'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="course-meta">
                                    <div>
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('H:i', strtotime($course['heure_debut'])); ?> - <?php echo date('H:i', strtotime($course['heure_fin'])); ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($course['salle']); ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-user-tie"></i>
                                        <?php echo htmlspecialchars($course['professeur_prenom'] . ' ' . $course['professeur_nom']); ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-users"></i>
                                        <?php echo $course['nb_etudiants_inscrits']; ?> étudiants
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Matières Section -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-book" style="margin-right: var(--spacing-sm); color: var(--osbt-blue);"></i>
                        Matières
                    </h2>
                </div>
                
                <div class="matieres-grid">
                    <?php if (empty($matieres)): ?>
                        <div class="empty-state">
                            <i class="fas fa-book"></i>
                            <h3>Aucune matière</h3>
                            <p>Aucune matière n'est enregistrée dans le système.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($matieres as $matiere): ?>
                            <div class="matiere-card">
                                <div class="matiere-header">
                                    <div class="matiere-color" style="background: <?php echo $matiere['couleur_hex'] ?? 'var(--osbt-primary)'; ?>;"></div>
                                    <div class="matiere-name"><?php echo htmlspecialchars($matiere['nom']); ?></div>
                                </div>
                                
                                <div class="matiere-stats">
                                    <span><?php echo $matiere['nb_cours']; ?> cours</span>
                                    <span><?php echo $matiere['nb_professeurs']; ?> profs</span>
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
