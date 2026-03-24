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
    // === ANNONCES ===
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            CONCAT(u.prenom, ' ', u.nom) as auteur_nom,
            u.role as auteur_role,
            COUNT(DISTINCT v.id_vue) as nb_vues
        FROM annonces a
        LEFT JOIN utilisateurs u ON a.auteur_id = u.id_utilisateur
        LEFT JOIN annonces_vues v ON a.id_annonce = v.id_annonce
        GROUP BY a.id_annonce
        ORDER BY a.date_creation DESC
        LIMIT 20
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll();
    
    // === STATISTIQUES ANNONCES ===
    $total_announcements = $pdo->query("SELECT COUNT(*) as count FROM annonces")->fetch()['count'];
    $active_announcements = $pdo->query("SELECT COUNT(*) as count FROM annonces WHERE est_active = 1")->fetch()['count'];
    $announcements_today = $pdo->query("SELECT COUNT(*) as count FROM annonces WHERE DATE(date_creation) = CURDATE()")->fetch()['count'];
    $total_views = $pdo->query("SELECT COUNT(*) as count FROM annonces_vues")->fetch()['count'];

} catch (PDOException $e) {
    $announcements = [];
    $total_announcements = 0;
    $active_announcements = 0;
    $announcements_today = 0;
    $total_views = 0;
    error_log("Erreur annonces admin: " . $e->getMessage());
}

// Définir les valeurs par défaut si les requêtes échouent
if (!isset($total_announcements)) $total_announcements = 0;
if (!isset($active_announcements)) $active_announcements = 0;
if (!isset($announcements_today)) $announcements_today = 0;
if (!isset($total_views)) $total_views = 0;

$stats = [
    'total_announcements' => $total_announcements,
    'active_announcements' => $active_announcements,
    'announcements_today' => $announcements_today,
    'total_views' => $total_views,
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Annonces - OSBT Connect</title>
    
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
        
        .announcements-section {
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
        
        .announcements-list {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-lg);
        }
        
        .announcement-item {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .announcement-item:hover {
            background: var(--gray-100);
            border-color: var(--osbt-primary);
        }
        
        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-md);
        }
        
        .announcement-info {
            flex: 1;
        }
        
        .announcement-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-sm);
        }
        
        .announcement-meta {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .announcement-content {
            color: var(--gray-700);
            line-height: 1.6;
            margin-bottom: var(--spacing-md);
        }
        
        .announcement-actions {
            display: flex;
            gap: var(--spacing-sm);
            justify-content: flex-end;
        }
        
        .status-badge {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-active {
            background: var(--osbt-primary);
            color: var(--white);
        }
        
        .status-inactive {
            background: var(--gray-200);
            color: var(--gray-700);
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
        
        .btn-danger {
            background: var(--osbt-gray);
            color: var(--white);
        }
        
        .btn-danger:hover {
            background: var(--gray-700);
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
            
            <h1 class="header-title">Gestion des Annonces</h1>
            <button class="btn btn-primary" onclick="showPageNotAvailable(event, 'La création d\'annonces sera bientôt disponible !')">
                <i class="fas fa-plus"></i>
                Nouvelle annonce
            </button>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_announcements']; ?></div>
                <div class="stat-label">Total annonces</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['active_announcements']; ?></div>
                <div class="stat-label">Annonces actives</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['announcements_today']; ?></div>
                <div class="stat-label">Aujourd'hui</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_views']; ?></div>
                <div class="stat-label">Vues totales</div>
            </div>
        </div>
        
        <!-- Announcements Section -->
        <div class="announcements-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-bullhorn" style="margin-right: var(--spacing-sm); color: var(--osbt-primary);"></i>
                    Annonces récentes
                </h2>
            </div>
            
            <div class="announcements-list">
                <?php if (empty($announcements)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bullhorn"></i>
                        <h3>Aucune annonce</h3>
                        <p>Aucune annonce n'a été publiée pour le moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-item">
                            <div class="announcement-header">
                                <div class="announcement-info">
                                    <div class="announcement-title"><?php echo htmlspecialchars($announcement['titre']); ?></div>
                                    <div class="announcement-meta">
                                        <span>
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($announcement['auteur_nom']); ?>
                                            (<?php echo ucfirst($announcement['auteur_role']); ?>)
                                        </span>
                                        <span>
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($announcement['date_creation'])); ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-eye"></i>
                                            <?php echo $announcement['nb_vues']; ?> vues
                                        </span>
                                        <span class="status-badge <?php echo $announcement['est_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $announcement['est_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="announcement-content">
                                <?php echo nl2br(htmlspecialchars($announcement['contenu'])); ?>
                            </div>
                            
                            <div class="announcement-actions">
                                <button class="btn btn-primary" onclick="editAnnouncement(<?php echo $announcement['id_annonce']; ?>)">
                                    <i class="fas fa-edit"></i>
                                    Modifier
                                </button>
                                <button class="btn btn-secondary" onclick="viewAnnouncement(<?php echo $announcement['id_annonce']; ?>)">
                                    <i class="fas fa-eye"></i>
                                    Voir
                                </button>
                                <button class="btn btn-danger" onclick="deleteAnnouncement(<?php echo $announcement['id_annonce']; ?>)">
                                    <i class="fas fa-trash"></i>
                                    Supprimer
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function showPageNotAvailable(event, message) {
            event.preventDefault();
            alert(message);
        }
        
        function editAnnouncement(id) {
            alert('Modification de l\'annonce #' + id + ' en cours...\n\nCette fonctionnalité sera bientôt disponible !');
        }
        
        function viewAnnouncement(id) {
            alert('Visualisation de l\'annonce #' + id + ' en cours...\n\nCette fonctionnalité sera bientôt disponible !');
        }
        
        function deleteAnnouncement(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette annonce ?')) {
                alert('Suppression de l\'annonce #' + id + ' en cours...\n\nCette fonctionnalité sera bientôt disponible !');
            }
        }
    </script>
</body>
</html>
