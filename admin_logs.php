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
    // === LOGS SYSTÈME ===
    $stmt = $pdo->prepare("
        SELECT 
            l.*,
            CONCAT(u.prenom, ' ', u.nom) as utilisateur_nom,
            u.role as utilisateur_role
        FROM logs_systeme l
        LEFT JOIN utilisateurs u ON l.utilisateur_id = u.id_utilisateur
        ORDER BY l.date_creation DESC
        LIMIT 50
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
    // === STATISTIQUES LOGS ===
    $total_logs_query = $pdo->query("SELECT COUNT(*) as count FROM logs_systeme");
    $total_logs = $total_logs_query ? $total_logs_query->fetch()['count'] : 0;
    
    $logs_today_query = $pdo->query("SELECT COUNT(*) as count FROM logs_systeme WHERE DATE(date_creation) = CURDATE()");
    $logs_today = $logs_today_query ? $logs_today_query->fetch()['count'] : 0;
    
    $logs_errors_query = $pdo->query("SELECT COUNT(*) as count FROM logs_systeme WHERE niveau = 'ERROR'");
    $logs_errors = $logs_errors_query ? $logs_errors_query->fetch()['count'] : 0;
    
    $logs_warnings_query = $pdo->query("SELECT COUNT(*) as count FROM logs_systeme WHERE niveau = 'WARNING'");
    $logs_warnings = $logs_warnings_query ? $logs_warnings_query->fetch()['count'] : 0;
    
} catch (PDOException $e) {
    $logs = [];
    $total_logs = 0;
    $logs_today = 0;
    $logs_errors = 0;
    $logs_warnings = 0;
    error_log("Erreur logs admin: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs Système - OSBT Connect</title>
    
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
        
        .logs-section {
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
        
        .filter-buttons {
            display: flex;
            gap: var(--spacing-sm);
        }
        
        .filter-btn {
            padding: var(--spacing-sm) var(--spacing-md);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            background: var(--white);
            color: var(--gray-700);
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover {
            background: var(--gray-100);
        }
        
        .filter-btn.active {
            background: var(--osbt-primary);
            color: var(--white);
            border-color: var(--osbt-primary);
        }
        
        .logs-list {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
            max-height: 600px;
            overflow-y: auto;
        }
        
        .log-item {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .log-item:hover {
            background: var(--gray-100);
            border-color: var(--osbt-primary);
        }
        
        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-sm);
        }
        
        .log-info {
            flex: 1;
        }
        
        .log-title {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-xs);
        }
        
        .log-meta {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .log-content {
            color: var(--gray-700);
            line-height: 1.6;
            margin-bottom: var(--spacing-sm);
        }
        
        .level-badge {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .level-info {
            background: var(--osbt-blue);
            color: var(--white);
        }
        
        .level-warning {
            background: var(--osbt-gray);
            color: var(--white);
        }
        
        .level-error {
            background: var(--osbt-primary-dark);
            color: var(--white);
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
            
            <h1 class="header-title">Logs Système</h1>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_logs; ?></div>
                <div class="stat-label">Total logs</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $logs_today; ?></div>
                <div class="stat-label">Aujourd'hui</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $logs_errors; ?></div>
                <div class="stat-label">Erreurs</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $logs_warnings; ?></div>
                <div class="stat-label">Avertissements</div>
            </div>
        </div>
        
        <!-- Logs Section -->
        <div class="logs-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-history" style="margin-right: var(--spacing-sm); color: var(--osbt-primary);"></i>
                    Logs récents
                </h2>
                <div class="filter-buttons">
                    <button class="filter-btn active" onclick="filterLogs('all')">Tous</button>
                    <button class="filter-btn" onclick="filterLogs('errors')">Erreurs</button>
                    <button class="filter-btn" onclick="filterLogs('warnings')">Avertissements</button>
                </div>
            </div>
            
            <div class="logs-list" id="logsList">
                <?php if (empty($logs)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>Aucun log</h3>
                        <p>Aucun log système n'est disponible pour le moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <div class="log-item" data-level="<?php echo strtolower($log['niveau'] ?? 'info'); ?>">
                            <div class="log-header">
                                <div class="log-info">
                                    <div class="log-title">
                                        <?php echo htmlspecialchars($log['action'] ?? 'Action système'); ?>
                                        <span class="level-badge level-<?php echo strtolower($log['niveau'] ?? 'info'); ?>">
                                            <?php echo strtoupper($log['niveau'] ?? 'INFO'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="log-meta">
                                <span>
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($log['utilisateur_nom'] ?? 'Système'); ?>
                                    (<?php echo ucfirst($log['utilisateur_role'] ?? 'system'); ?>)
                                </span>
                                <span>
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('d/m/Y H:i:s', strtotime($log['date_creation'])); ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($log['description'])): ?>
                                <div class="log-content">
                                    <?php echo nl2br(htmlspecialchars($log['description'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function filterLogs(level) {
            // Mettre à jour les boutons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Filtrer les logs
            const logItems = document.querySelectorAll('.log-item');
            logItems.forEach(item => {
                if (level === 'all' || item.dataset.level === level) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
