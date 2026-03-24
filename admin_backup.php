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
    // === INFORMATIONS DE BACKUP ===
    $backup_info = [
        'last_backup' => null,
        'next_scheduled' => null,
        'total_backups' => 0,
        'backup_size' => 0,
        'db_size' => 0,
    ];
    
    // Simuler les informations de backup
    $backup_info['last_backup'] = [
        'date' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'size' => '15.2 MB',
        'type' => 'complet',
        'status' => 'success'
    ];
    
    $backup_info['next_scheduled'] = [
        'date' => date('Y-m-d H:i:s', strtotime('tomorrow 02:00')),
        'type' => 'automatique',
        'status' => 'programmé'
    ];
    
    $backup_info['total_backups'] = 15;
    $backup_info['backup_size'] = 228.5; // MB
    $backup_info['db_size'] = 45.2; // MB
    
} catch (PDOException $e) {
    $backup_info = [];
    error_log("Erreur backup admin: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sauvegarde - OSBT Connect</title>
    
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
        
        .backup-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--spacing-xl);
        }
        
        .backup-section {
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
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
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
        
        .backup-list {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
            max-height: 400px;
            overflow-y: auto;
        }
        
        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-md);
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            transition: all 0.3s ease;
        }
        
        .backup-item:hover {
            background: var(--gray-100);
            border-color: var(--osbt-primary);
        }
        
        .backup-info {
            flex: 1;
        }
        
        .backup-name {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-xs);
        }
        
        .backup-meta {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
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
        
        .status-success {
            background: var(--osbt-primary);
            color: var(--white);
        }
        
        .status-pending {
            background: var(--osbt-gray);
            color: var(--white);
        }
        
        .status-failed {
            background: var(--osbt-primary-dark);
            color: var(--white);
        }
        
        .action-buttons {
            display: flex;
            gap: var(--spacing-md);
            justify-content: center;
            margin-top: var(--spacing-xl);
        }
        
        .btn {
            padding: var(--spacing-md) var(--spacing-xl);
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            font-size: 1rem;
        }
        
        .btn-primary {
            background: var(--osbt-primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: var(--osbt-primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-secondary:hover {
            background: var(--gray-300);
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--gray-200);
            border-radius: var(--radius-full);
            overflow: hidden;
            margin-top: var(--spacing-sm);
        }
        
        .progress-fill {
            height: 100%;
            background: var(--osbt-primary);
            transition: width 2s ease;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: var(--spacing-md);
            }
            
            .backup-grid {
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
            
            <h1 class="header-title">Sauvegarde Système</h1>
        </div>
        
        <!-- Backup Grid -->
        <div class="backup-grid">
            <!-- Stats Section -->
            <div class="backup-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-chart-bar"></i>
                        Statistiques
                    </h2>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $backup_info['total_backups']; ?></div>
                        <div class="stat-label">Total sauvegardes</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($backup_info['backup_size'], 1); ?> MB</div>
                        <div class="stat-label">Taille totale</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($backup_info['db_size'], 1); ?> MB</div>
                        <div class="stat-label">Base de données</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value">7 jours</div>
                        <div class="stat-label">Rétention</div>
                    </div>
                </div>
            </div>
            
            <!-- Last Backup Section -->
            <div class="backup-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-history"></i>
                        Dernière sauvegarde
                    </h2>
                    <button class="btn btn-secondary" onclick="downloadBackup()">
                        <i class="fas fa-download"></i>
                        Télécharger
                    </button>
                </div>
                
                <div class="backup-item">
                    <div class="backup-info">
                        <div class="backup-name">Sauvegarde complète</div>
                        <div class="backup-meta">
                            <span>
                                <i class="fas fa-calendar"></i>
                                <?php echo $backup_info['last_backup']['date']; ?>
                            </span>
                            <span>
                                <i class="fas fa-database"></i>
                                <?php echo $backup_info['last_backup']['size']; ?>
                            </span>
                            <span class="status-badge status-<?php echo $backup_info['last_backup']['status']; ?>">
                                <?php echo ucfirst($backup_info['last_backup']['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Next Backup Section -->
            <div class="backup-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-clock"></i>
                        Prochaine sauvegarde
                    </h2>
                    <button class="btn btn-secondary" onclick="scheduleBackup()">
                        <i class="fas fa-calendar-plus"></i>
                        Programmer
                    </button>
                </div>
                
                <div class="backup-item">
                    <div class="backup-info">
                        <div class="backup-name">Sauvegarde automatique</div>
                        <div class="backup-meta">
                            <span>
                                <i class="fas fa-calendar"></i>
                                <?php echo $backup_info['next_scheduled']['date']; ?>
                            </span>
                            <span>
                                <i class="fas fa-robot"></i>
                                <?php echo ucfirst($backup_info['next_scheduled']['type']); ?>
                            </span>
                            <span class="status-badge status-<?php echo $backup_info['next_scheduled']['status']; ?>">
                                <?php echo ucfirst($backup_info['next_scheduled']['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 75%;"></div>
                </div>
            </div>
            
            <!-- Backup History Section -->
            <div class="backup-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-list"></i>
                        Historique récent
                    </h2>
                    <button class="btn btn-secondary" onclick="showAllBackups()">
                        <i class="fas fa-eye"></i>
                        Voir tout
                    </button>
                </div>
                
                <div class="backup-list">
                    <!-- Simuler quelques backups récents -->
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name">Sauvegarde automatique</div>
                            <div class="backup-meta">
                                <span>
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('d/m/Y H:i', strtotime('-5 days')); ?>
                                </span>
                                <span>
                                    <i class="fas fa-database"></i>
                                    14.8 MB
                                </span>
                                <span class="status-badge status-success">
                                    Succès
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name">Sauvegarde manuelle</div>
                            <div class="backup-meta">
                                <span>
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('d/m/Y H:i', strtotime('-7 days')); ?>
                                </span>
                                <span>
                                    <i class="fas fa-database"></i>
                                    16.2 MB
                                </span>
                                <span class="status-badge status-success">
                                    Succès
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name">Sauvegarde automatique</div>
                            <div class="backup-meta">
                                <span>
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('d/m/Y H:i', strtotime('-12 days')); ?>
                                </span>
                                <span>
                                    <i class="fas fa-database"></i>
                                    15.5 MB
                                </span>
                                <span class="status-badge status-failed">
                                    Échec
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="createBackupNow()">
                <i class="fas fa-play"></i>
                Lancer une sauvegarde maintenant
            </button>
            <button class="btn btn-secondary" onclick="configureBackup()">
                <i class="fas fa-cog"></i>
                Configurer les sauvegardes
            </button>
        </div>
    </div>
    
    <script>
        function createBackupNow() {
            showNotification('Sauvegarde en cours', 'La sauvegarde de la base de données est en cours...', 'info');
            
            // Simuler une sauvegarde
            setTimeout(() => {
                showNotification('Sauvegarde terminée', 'La sauvegarde a été effectuée avec succès', 'success');
            }, 3000);
        }
        
        function downloadBackup() {
            showNotification('Téléchargement', 'Le téléchargement de la sauvegarde commencera...', 'info');
            // Simuler un téléchargement
            setTimeout(() => {
                window.open('data:application/sql;base64,' + btoa('CREATE TABLE...'), '_blank');
            }, 1000);
        }
        
        function scheduleBackup() {
            showNotification('Programmation', 'L\'interface de programmation des sauvegardes sera bientôt disponible', 'info');
        }
        
        function configureBackup() {
            showNotification('Configuration', 'L\'interface de configuration des sauvegardes sera bientôt disponible', 'info');
        }
        
        function showAllBackups() {
            showNotification('Historique complet', 'L\'historique complet des sauvegardes sera bientôt disponible', 'info');
        }
        
        function showNotification(title, message, type) {
            // Créer une notification simple
            alert(title + ': ' + message);
        }
    </script>
</body>
</html>
