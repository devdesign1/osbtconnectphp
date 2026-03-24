<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

// === RÉCUPÉRATION DES DONNÉES ===
$user_id = $_SESSION['user_id'];

// Récupérer les tâches de l'utilisateur
try {
    $stmt = $pdo->prepare("
        SELECT 
            id_tache,
            titre,
            description,
            date_limite,
            statut,
            priorite,
            matiere_id,
            m.matiere_nom,
            m.couleur_hex
        FROM taches
        LEFT JOIN matieres m ON taches.matiere_id = m.id_matiere
        WHERE user_id = ?
        ORDER BY date_limite ASC, priorite DESC
    ");
    $stmt->execute([$user_id]);
    $taches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $taches = [];
}

// Grouper par statut
$taches_par_statut = [
    'a_faire' => [],
    'en_cours' => [],
    'terminee' => []
];

$total_taches = 0;
$taches_urgentes = 0;

foreach ($taches as $tache) {
    $statut = $tache['statut'] ?? 'a_faire';
    if (isset($taches_par_statut[$statut])) {
        $taches_par_statut[$statut][] = $tache;
    }
    $total_taches++;
    
    if ($tache['priorite'] === 'haute') {
        $taches_urgentes++;
    }
}

// Calculer la progression
$progression = $total_taches > 0 ? round((count($taches_par_statut['terminee']) / $total_taches) * 100) : 0;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checklist - OSBT Connect</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --notion-blue: #4285f4;
            --notion-green: #00c853;
            --notion-purple: #8b5cf6;
            --notion-orange: #f97316;
            --notion-pink: #ec4899;
            --notion-red: #ef4444;
            
            --gradient-green: linear-gradient(135deg, #00c853, #00e676);
            --gradient-blue: linear-gradient(135deg, #4285f4, #1976d2);
            --gradient-purple: linear-gradient(135deg, #8b5cf6, #a78bfa);
            --gradient-orange: linear-gradient(135deg, #f97316, #fb923c);
            
            --notion-gray-50: #f8f9fa;
            --notion-gray-100: #e9ecef;
            --notion-gray-200: #dee2e6;
            --notion-gray-300: #ced4da;
            --notion-gray-400: #adb5bd;
            --notion-gray-500: #6c757d;
            --notion-gray-600: #495057;
            --notion-gray-700: #343a40;
            --notion-gray-800: #212529;
            --notion-gray-900: #000000;
            
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;
            
            --radius-sm: 0.25rem;
            --radius-md: 0.5rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-full: 9999px;
            
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            
            --transition-fast: 150ms ease;
            --transition-base: 250ms ease;
            --transition-slow: 350ms ease;
            
            --primary: var(--notion-green);
            --primary-dark: #007e33;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--notion-gray-50);
            color: var(--notion-gray-900);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .checklist-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }
        
        /* Header */
        .checklist-header {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-xl);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-2xl);
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .checklist-title {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }
        
        .checklist-title h1 {
            font-size: 2rem;
            background: var(--gradient-green);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
        
        .checklist-subtitle {
            color: var(--notion-gray-600);
            margin-top: var(--spacing-xs);
        }
        
        .back-link {
            color: var(--notion-gray-600);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--radius-md);
            transition: all var(--transition-base);
        }
        
        .back-link:hover {
            background: var(--notion-gray-100);
            color: var(--primary);
        }
        
        /* Stats Section */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-2xl);
        }
        
        .stat-card {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-base);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: var(--spacing-md);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-lg);
        }
        
        .stat-icon.total {
            background: rgba(66, 133, 244, 0.1);
            color: var(--notion-blue);
        }
        
        .stat-icon.urgent {
            background: rgba(239, 68, 68, 0.1);
            color: var(--notion-red);
        }
        
        .stat-icon.progress {
            background: rgba(0, 200, 83, 0.1);
            color: var(--notion-green);
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--notion-gray-900);
            margin-bottom: var(--spacing-xs);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--notion-gray-600);
        }
        
        /* Progress Bar */
        .progress-container {
            margin-top: var(--spacing-md);
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--notion-gray-200);
            border-radius: var(--radius-full);
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--gradient-green);
            border-radius: var(--radius-full);
            transition: width var(--transition-slow);
        }
        
        .progress-text {
            font-size: 0.75rem;
            color: var(--notion-gray-600);
            margin-top: var(--spacing-xs);
        }
        
        /* Filter Bar */
        .filter-bar {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-2xl);
            display: flex;
            gap: var(--spacing-md);
            flex-wrap: wrap;
            align-items: center;
            box-shadow: var(--shadow-sm);
        }
        
        .filter-btn {
            padding: var(--spacing-sm) var(--spacing-lg);
            border: 1px solid var(--notion-gray-300);
            background: white;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-base);
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .checklist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: var(--spacing-xl);
        }
        
        .statut-card {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-base);
        }
        
        .statut-card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .statut-header {
            padding: var(--spacing-lg);
            border-bottom: 2px solid var(--notion-gray-200);
            background: var(--notion-gray-50);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .statut-titre {
            font-size: 1.125rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .statut-count {
            background: var(--notion-gray-200);
            color: var(--notion-gray-700);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-size: 0.875rem;
            font-weight: 600;
            min-width: 28px;
            text-align: center;
        }
        
        .taches-list {
            padding: var(--spacing-lg);
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
            max-height: 700px;
            overflow-y: auto;
        }
        
        .taches-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .taches-list::-webkit-scrollbar-track {
            background: var(--notion-gray-100);
            border-radius: var(--radius-full);
        }
        
        .taches-list::-webkit-scrollbar-thumb {
            background: var(--notion-gray-300);
            border-radius: var(--radius-full);
        }
        
        .taches-list::-webkit-scrollbar-thumb:hover {
            background: var(--notion-gray-400);
        }
        
        .tache-item {
            padding: var(--spacing-md);
            background: var(--notion-gray-50);
            border-radius: var(--radius-lg);
            border-left: 4px solid;
            transition: all var(--transition-base);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .tache-item::before {
            content: '';
            position: absolute;
            top: 0;
            right: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: right var(--transition-base);
        }
        
        .tache-item:hover {
            background: white;
            box-shadow: var(--shadow-md);
            transform: translateX(4px);
        }
        
        .tache-item:hover::before {
            right: 100%;
        }
        
        .tache-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-xs);
        }
        
        .tache-titre {
            font-weight: 600;
            color: var(--notion-gray-900);
            flex: 1;
            word-break: break-word;
        }
        
        .tache-close {
            opacity: 0;
            transition: opacity var(--transition-base);
        }
        
        .tache-item:hover .tache-close {
            opacity: 1;
        }
        
        .tache-close-btn {
            background: none;
            border: none;
            color: var(--notion-gray-500);
            cursor: pointer;
            font-size: 1.25rem;
            transition: color var(--transition-base);
        }
        
        .tache-close-btn:hover {
            color: var(--notion-red);
        }
        
        .tache-description {
            font-size: 0.8125rem;
            color: var(--notion-gray-600);
            margin-bottom: var(--spacing-sm);
            line-height: 1.4;
        }
        
        .tache-meta {
            font-size: 0.875rem;
            color: var(--notion-gray-600);
            display: flex;
            gap: var(--spacing-md);
            flex-wrap: wrap;
        }
        
        .tache-meta-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }
        
        .matiere-badge {
            display: inline-block;
            font-size: 0.75rem;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            background: var(--notion-gray-200);
            color: var(--notion-gray-700);
            font-weight: 500;
        }
        
        .priorite-badge {
            font-size: 0.75rem;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .priorite-haute {
            background: rgba(239, 68, 68, 0.1);
            color: var(--notion-red);
        }
        
        .priorite-moyenne {
            background: rgba(249, 115, 22, 0.1);
            color: var(--notion-orange);
        }
        
        .priorite-basse {
            background: rgba(0, 200, 83, 0.1);
            color: var(--notion-green);
        }
        
        .empty-statut {
            text-align: center;
            padding: var(--spacing-2xl) var(--spacing-lg);
            color: var(--notion-gray-500);
        }
        
        .empty-statut i {
            font-size: 2.5rem;
            margin-bottom: var(--spacing-md);
            opacity: 0.5;
        }
        
        .empty-statut p {
            margin-bottom: var(--spacing-xs);
        }
        
        /* Overdue indicator */
        .overdue {
            border-left-color: var(--notion-red) !important;
            background: rgba(239, 68, 68, 0.05) !important;
        }
        
        .overdue .tache-titre::before {
            content: '⚠ ';
            color: var(--notion-red);
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .checklist-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .checklist-container {
                padding: var(--spacing-md);
            }
            
            .checklist-header {
                flex-direction: column;
                gap: var(--spacing-md);
                text-align: center;
            }
            
            .checklist-title h1 {
                font-size: 1.5rem;
            }
            
            .stats-section {
                grid-template-columns: 1fr;
            }
            
            .filter-bar {
                flex-direction: column;
            }
            
            .filter-btn {
                width: 100%;
            }
            
            .checklist-grid {
                grid-template-columns: 1fr;
            }
            
            .taches-list {
                max-height: 500px;
            }
        }
    </style>
</head>
<body>
    <div class="checklist-container">
        <!-- Header -->
        <div class="checklist-header">
            <div class="checklist-title">
                <i class="fas fa-tasks" style="font-size: 2rem; color: var(--primary);"></i>
                <div>
                    <h1>Checklist</h1>
                    <div class="checklist-subtitle">Gestion de vos tâches et projets</div>
                </div>
            </div>
            <a href="student_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Retour au dashboard
            </a>
        </div>
        
        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-list-check"></i>
                </div>
                <div class="stat-value"><?= $total_taches ?></div>
                <div class="stat-label">Tâches totales</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon urgent">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-value"><?= $taches_urgentes ?></div>
                <div class="stat-label">Tâches urgentes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon progress">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="stat-value"><?= $progression ?>%</div>
                <div class="stat-label">Progression</div>
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $progression ?>%;"></div>
                    </div>
                    <div class="progress-text"><?= count($taches_par_statut['terminee']) ?>/<?= $total_taches ?> complétées</div>
                </div>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <label style="font-weight: 600; color: var(--notion-gray-700);">
                <i class="fas fa-filter"></i> Filtrer par :
            </label>
            <button class="filter-btn active">
                <i class="fas fa-th"></i> Tous
            </button>
            <button class="filter-btn">
                <i class="fas fa-exclamation"></i> Urgent
            </button>
            <button class="filter-btn">
                <i class="fas fa-calendar-times"></i> En retard
            </button>
        </div>
        
        <!-- Checklist Grid -->
        <div class="checklist-grid">
            <!-- À faire -->
            <div class="statut-card">
                <div class="statut-header">
                    <div class="statut-titre">
                        <i class="fas fa-circle-notch" style="color: var(--notion-blue);"></i>
                        À faire
                    </div>
                    <span class="statut-count"><?= count($taches_par_statut['a_faire']) ?></span>
                </div>
                <div class="taches-list">
                    <?php if (empty($taches_par_statut['a_faire'])): ?>
                        <div class="empty-statut">
                            <i class="fas fa-inbox"></i>
                            <p>Aucune tâche</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($taches_par_statut['a_faire'] as $tache): ?>
                            <?php 
                                $is_overdue = strtotime($tache['date_limite']) < strtotime('today');
                                $overdue_class = $is_overdue ? 'overdue' : '';
                            ?>
                            <div class="tache-item <?= $overdue_class ?>" style="border-left-color: <?= $tache['couleur_hex'] ?? '#4285f4' ?>;">
                                <div class="tache-header">
                                    <div class="tache-titre"><?= htmlspecialchars($tache['titre']) ?></div>
                                    <div class="tache-close">
                                        <button class="tache-close-btn" title="Supprimer">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php if (!empty($tache['description'])): ?>
                                    <div class="tache-description"><?= htmlspecialchars(substr($tache['description'], 0, 80)) ?><?= strlen($tache['description']) > 80 ? '...' : '' ?></div>
                                <?php endif; ?>
                                <div class="tache-meta">
                                    <div class="tache-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d/m/Y', strtotime($tache['date_limite'])) ?>
                                    </div>
                                    <span class="priorite-badge priorite-<?= strtolower($tache['priorite'] ?? 'basse') ?>">
                                        <?= ucfirst($tache['priorite'] ?? 'basse') ?>
                                    </span>
                                    <?php if (!empty($tache['matiere_nom'])): ?>
                                        <span class="matiere-badge"><?= htmlspecialchars($tache['matiere_nom']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- En cours -->
            <div class="statut-card">
                <div class="statut-header">
                    <div class="statut-titre">
                        <i class="fas fa-spinner" style="color: var(--notion-orange);"></i>
                        En cours
                    </div>
                    <span class="statut-count"><?= count($taches_par_statut['en_cours']) ?></span>
                </div>
                <div class="taches-list">
                    <?php if (empty($taches_par_statut['en_cours'])): ?>
                        <div class="empty-statut">
                            <i class="fas fa-inbox"></i>
                            <p>Aucune tâche</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($taches_par_statut['en_cours'] as $tache): ?>
                            <div class="tache-item" style="border-left-color: <?= $tache['couleur_hex'] ?? '#f97316' ?>;">
                                <div class="tache-header">
                                    <div class="tache-titre"><?= htmlspecialchars($tache['titre']) ?></div>
                                    <div class="tache-close">
                                        <button class="tache-close-btn" title="Supprimer">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php if (!empty($tache['description'])): ?>
                                    <div class="tache-description"><?= htmlspecialchars(substr($tache['description'], 0, 80)) ?><?= strlen($tache['description']) > 80 ? '...' : '' ?></div>
                                <?php endif; ?>
                                <div class="tache-meta">
                                    <div class="tache-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d/m/Y', strtotime($tache['date_limite'])) ?>
                                    </div>
                                    <span class="priorite-badge priorite-<?= strtolower($tache['priorite'] ?? 'basse') ?>">
                                        <?= ucfirst($tache['priorite'] ?? 'basse') ?>
                                    </span>
                                    <?php if (!empty($tache['matiere_nom'])): ?>
                                        <span class="matiere-badge"><?= htmlspecialchars($tache['matiere_nom']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Terminée -->
            <div class="statut-card">
                <div class="statut-header">
                    <div class="statut-titre">
                        <i class="fas fa-check-circle" style="color: var(--notion-green);"></i>
                        Terminée
                    </div>
                    <span class="statut-count"><?= count($taches_par_statut['terminee']) ?></span>
                </div>
                <div class="taches-list">
                    <?php if (empty($taches_par_statut['terminee'])): ?>
                        <div class="empty-statut">
                            <i class="fas fa-inbox"></i>
                            <p>Aucune tâche</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($taches_par_statut['terminee'] as $tache): ?>
                            <div class="tache-item" style="border-left-color: <?= $tache['couleur_hex'] ?? '#00c853' ?>; opacity: 0.7;">
                                <div class="tache-header">
                                    <div class="tache-titre" style="text-decoration: line-through;"><?= htmlspecialchars($tache['titre']) ?></div>
                                    <div class="tache-close">
                                        <button class="tache-close-btn" title="Supprimer">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="tache-meta">
                                    <div class="tache-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d/m/Y', strtotime($tache['date_limite'])) ?>
                                    </div>
                                    <span class="priorite-badge priorite-<?= strtolower($tache['priorite'] ?? 'basse') ?>">
                                        <?= ucfirst($tache['priorite'] ?? 'basse') ?>
                                    </span>
                                    <?php if (!empty($tache['matiere_nom'])): ?>
                                        <span class="matiere-badge"><?= htmlspecialchars($tache['matiere_nom']) ?></span>
                                    <?php endif; ?>
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