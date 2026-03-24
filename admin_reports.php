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
    // === RAPPORTS DISPONIBLES ===
    $reports = [
        [
            'id' => 'users_report',
            'title' => 'Rapport Utilisateurs',
            'description' => 'Liste complète des utilisateurs avec leurs statistiques',
            'icon' => 'fa-users',
            'color' => 'var(--osbt-blue)',
            'last_generated' => date('d/m/Y H:i'),
            'records' => $pdo->query("SELECT COUNT(*) as count FROM utilisateurs")->fetch()['count']
        ],
        [
            'id' => 'courses_report',
            'title' => 'Rapport Cours',
            'description' => 'Statistiques des cours et planning',
            'icon' => 'fa-book',
            'color' => 'var(--osbt-primary)',
            'last_generated' => date('d/m/Y H:i', strtotime('-1 day')),
            'records' => $pdo->query("SELECT COUNT(*) as count FROM planning_cours")->fetch()['count']
        ],
        [
            'id' => 'mentorat_report',
            'title' => 'Rapport Mentorat',
            'description' => 'Sessions de mentorat et taux de participation',
            'icon' => 'fa-handshake',
            'color' => 'var(--osbt-primary-dark)',
            'last_generated' => date('d/m/Y H:i', strtotime('-2 hours')),
            'records' => $pdo->query("SELECT COUNT(*) as count FROM sessions_mentorat")->fetch()['count']
        ],
        [
            'id' => 'flashcards_report',
            'title' => 'Rapport Flashcards',
            'description' => 'Performance et utilisation des flashcards',
            'icon' => 'fa-brain',
            'color' => 'var(--osbt-blue)',
            'last_generated' => date('d/m/Y H:i', strtotime('-3 hours')),
            'records' => $pdo->query("SELECT COUNT(*) as count FROM flashcards")->fetch()['count']
        ],
        [
            'id' => 'activity_report',
            'title' => 'Rapport d\'Activité',
            'description' => 'Activité globale de la plateforme',
            'icon' => 'fa-chart-line',
            'color' => 'var(--osbt-gray)',
            'last_generated' => date('d/m/Y H:i', strtotime('-1 week')),
            'records' => $pdo->query("SELECT COUNT(*) as count FROM utilisateurs WHERE derniere_connexion >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch()['count']
        ]
    ];

    // === STATISTIQUES RAPPORTS ===
    $total_reports = $pdo->query("SELECT COUNT(*) as count FROM annonces")->fetch()['count'];
    $active_reports = $pdo->query("SELECT COUNT(*) as count FROM annonces WHERE est_active = 1")->fetch()['count'];
    $reports_today = $pdo->query("SELECT COUNT(*) as count FROM annonces WHERE DATE(date_creation) = CURDATE()")->fetch()['count'];
    $total_views = $pdo->query("SELECT COUNT(*) as count FROM annonces_vues")->fetch()['count'];

} catch (PDOException $e) {
    $reports = [];
    $total_reports = 0;
    $active_reports = 0;
    $reports_today = 0;
    $total_views = 0;
    error_log("Erreur rapports admin: " . $e->getMessage());
}

// Définir les valeurs par défaut si les requêtes échouent
if (!isset($total_reports)) $total_reports = 0;
if (!isset($active_reports)) $active_reports = 0;
if (!isset($reports_today)) $reports_today = 0;
if (!isset($total_views)) $total_views = 0;

$stats = [
    'total_reports' => $total_reports,
    'active_reports' => $active_reports,
    'reports_today' => $reports_today,
    'total_views' => $total_views,
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - OSBT Connect</title>
    
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
        
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: var(--spacing-lg);
        }
        
        .report-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .report-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--osbt-primary);
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-md);
        }
        
        .report-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--white);
        }
        
        .report-info {
            flex: 1;
            margin-left: var(--spacing-md);
        }
        
        .report-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-xs);
        }
        
        .report-description {
            font-size: 0.875rem;
            color: var(--gray-600);
            line-height: 1.4;
        }
        
        .report-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: var(--spacing-md);
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--gray-200);
        }
        
        .report-stats {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .stat-value {
            font-weight: 600;
            color: var(--osbt-primary);
        }
        
        .report-actions {
            display: flex;
            gap: var(--spacing-sm);
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
        
        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-secondary:hover {
            background: var(--gray-300);
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
            
            .reports-grid {
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
            
            <h1 class="header-title">Rapports</h1>
            <p style="color: var(--gray-600);">Générez et consultez les rapports de la plateforme</p>
        </div>
        
        <!-- Reports Grid -->
        <?php if (empty($reports)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>Aucun rapport disponible</h3>
                <p>Aucun rapport n'est actuellement disponible pour la génération.</p>
            </div>
        <?php else: ?>
            <div class="reports-grid">
                <?php foreach ($reports as $report): ?>
                    <div class="report-card">
                        <div class="report-header">
                            <div class="report-icon" style="background: <?php echo $report['color']; ?>;">
                                <i class="fas <?php echo $report['icon']; ?>"></i>
                            </div>
                            <div class="report-info">
                                <div class="report-title"><?php echo htmlspecialchars($report['title']); ?></div>
                                <div class="report-description"><?php echo htmlspecialchars($report['description']); ?></div>
                            </div>
                        </div>
                        
                        <div class="report-meta">
                            <div class="report-stats">
                                <div class="stat-item">
                                    <i class="fas fa-database"></i>
                                    <span class="stat-value"><?php echo number_format($report['records']); ?></span>
                                    enregistrements
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-clock"></i>
                                    <?php echo htmlspecialchars($report['last_generated']); ?>
                                </div>
                            </div>
                            
                            <div class="report-actions">
                                <button class="btn btn-primary" onclick="generateReport('<?php echo $report['id']; ?>')">
                                    <i class="fas fa-download"></i>
                                    Générer
                                </button>
                                <button class="btn btn-secondary" onclick="viewReport('<?php echo $report['id']; ?>')">
                                    <i class="fas fa-eye"></i>
                                    Voir
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function generateReport(reportId) {
            alert('Génération du rapport ' + reportId + ' en cours...\n\nCette fonctionnalité sera bientôt disponible !');
        }
        
        function viewReport(reportId) {
            alert('Visualisation du rapport ' + reportId + ' en cours...\n\nCette fonctionnalité sera bientôt disponible !');
        }
    </script>
</body>
</html>
