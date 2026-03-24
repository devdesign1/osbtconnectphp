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
$user_promotion = $_SESSION['user_promotion'] ?? 1;

// Récupérer les annonces
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.id_annonce,
            a.titre,
            a.contenu,
            a.date_creation,
            a.importance,
            a.categorie,
            a.statut,
            u.nom as auteur_nom,
            u.prenom as auteur_prenom,
            COUNT(DISTINCT ua.id_annonce) as nombre_lectures
        FROM annonces a
        LEFT JOIN utilisateurs u ON a.user_id = u.id_user
        LEFT JOIN utilisateur_annonces ua ON a.id_annonce = ua.id_annonce AND ua.is_read = 1
        WHERE a.promotion = ?
        GROUP BY a.id_annonce
        ORDER BY a.importance DESC, a.date_creation DESC
    ");
    $stmt->execute([$user_promotion]);
    $annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $annonces = [];
}

// Récupérer les annonces lues par l'utilisateur
try {
    $stmt = $pdo->prepare("
        SELECT id_annonce FROM utilisateur_annonces 
        WHERE user_id = ? AND is_read = 1
    ");
    $stmt->execute([$user_id]);
    $lues = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id_annonce');
} catch (PDOException $e) {
    $lues = [];
}

// Grouper par catégorie
$annonces_par_categorie = [
    'generale' => [],
    'academique' => [],
    'vie_etudiante' => [],
    'maintenance' => []
];

foreach ($annonces as $annonce) {
    $categorie = $annonce['categorie'] ?? 'generale';
    if (isset($annonces_par_categorie[$categorie])) {
        $annonces_par_categorie[$categorie][] = $annonce;
    }
}

// Statistiques
$total_annonces = count($annonces);
$annonces_non_lues = 0;
$annonces_importantes = 0;

foreach ($annonces as $annonce) {
    if (!in_array($annonce['id_annonce'], $lues)) {
        $annonces_non_lues++;
    }
    if ($annonce['importance'] === 'haute') {
        $annonces_importantes++;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annonces - OSBT Connect</title>
    
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
            --notion-yellow: #fbbf24;
            
            --gradient-green: linear-gradient(135deg, #00c853, #00e676);
            --gradient-blue: linear-gradient(135deg, #4285f4, #1976d2);
            --gradient-red: linear-gradient(135deg, #ef4444, #dc2626);
            
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
        
        .annonces-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }
        
        /* Header */
        .annonces-header {
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
        
        .annonces-title {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }
        
        .annonces-title h1 {
            font-size: 2rem;
            background: var(--gradient-blue);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
        
        .annonces-subtitle {
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
        
        .stat-icon.non-lues {
            background: rgba(239, 68, 68, 0.1);
            color: var(--notion-red);
        }
        
        .stat-icon.importantes {
            background: rgba(251, 191, 36, 0.1);
            color: var(--notion-yellow);
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
            color: var(--notion-gray-600);
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--notion-gray-50);
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Annonces List */
        .annonces-list {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-lg);
        }
        
        .annonce-card {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-base);
            border-left: 4px solid var(--notion-gray-300);
            position: relative;
        }
        
        .annonce-card:hover {
            transform: translateX(4px);
            box-shadow: var(--shadow-md);
        }
        
        .annonce-card.non-lue {
            background: linear-gradient(135deg, rgba(66, 133, 244, 0.02), rgba(0, 200, 83, 0.02));
            border-left-color: var(--notion-blue);
        }
        
        .annonce-card.importance-haute {
            border-left-color: var(--notion-red);
        }
        
        .annonce-card.importance-moyenne {
            border-left-color: var(--notion-yellow);
        }
        
        .annonce-card.importance-basse {
            border-left-color: var(--notion-green);
        }
        
        .annonce-indicator {
            position: absolute;
            top: var(--spacing-lg);
            right: var(--spacing-lg);
            width: 12px;
            height: 12px;
            border-radius: var(--radius-full);
            background: var(--notion-blue);
        }
        
        .annonce-indicator.non-lu {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .annonce-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }
        
        .annonce-title-section {
            flex: 1;
        }
        
        .annonce-titre {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--notion-gray-900);
            margin-bottom: var(--spacing-xs);
        }
        
        .annonce-meta {
            display: flex;
            gap: var(--spacing-md);
            flex-wrap: wrap;
            font-size: 0.875rem;
            color: var(--notion-gray-600);
        }
        
        .annonce-meta-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }
        
        .annonce-badges {
            display: flex;
            gap: var(--spacing-sm);
            flex-wrap: wrap;
        }
        
        .badge {
            display: inline-block;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-categorie {
            background: rgba(66, 133, 244, 0.1);
            color: var(--notion-blue);
        }
        
        .badge-categorie.academique {
            background: rgba(0, 200, 83, 0.1);
            color: var(--notion-green);
        }
        
        .badge-categorie.vie_etudiante {
            background: rgba(139, 92, 246, 0.1);
            color: var(--notion-purple);
        }
        
        .badge-categorie.maintenance {
            background: rgba(249, 115, 22, 0.1);
            color: var(--notion-orange);
        }
        
        .badge-importance {
            background: rgba(239, 68, 68, 0.1);
            color: var(--notion-red);
        }
        
        .badge-importance.moyenne {
            background: rgba(251, 191, 36, 0.1);
            color: var(--notion-yellow);
        }
        
        .badge-importance.basse {
            background: rgba(0, 200, 83, 0.1);
            color: var(--notion-green);
        }
        
        .annonce-contenu {
            color: var(--notion-gray-700);
            margin-bottom: var(--spacing-lg);
            line-height: 1.6;
        }
        
        .annonce-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--notion-gray-200);
            font-size: 0.875rem;
            color: var(--notion-gray-600);
        }
        
        .annonce-auteur {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .annonce-auteur-avatar {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-full);
            background: var(--notion-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.75rem;
        }
        
        .annonce-actions {
            display: flex;
            gap: var(--spacing-sm);
        }
        
        .action-btn {
            background: none;
            border: none;
            color: var(--notion-gray-500);
            cursor: pointer;
            transition: color var(--transition-base);
            font-size: 1rem;
        }
        
        .action-btn:hover {
            color: var(--primary);
        }
        
        /* Empty state */
        .empty-annonces {
            text-align: center;
            padding: var(--spacing-2xl);
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-xl);
        }
        
        .empty-annonces i {
            font-size: 3rem;
            color: var(--notion-gray-300);
            margin-bottom: var(--spacing-md);
        }
        
        .empty-annonces h3 {
            color: var(--notion-gray-600);
            margin-bottom: var(--spacing-sm);
        }
        
        .empty-annonces p {
            color: var(--notion-gray-500);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .annonces-container {
                padding: var(--spacing-md);
            }
            
            .annonces-header {
                flex-direction: column;
                gap: var(--spacing-md);
                text-align: center;
            }
            
            .annonces-title h1 {
                font-size: 1.5rem;
            }
            
            .filter-bar {
                flex-direction: column;
            }
            
            .filter-btn {
                width: 100%;
            }
            
            .annonce-header {
                flex-direction: column;
            }
            
            .annonce-indicator {
                top: var(--spacing-md);
                right: var(--spacing-md);
            }
            
            .annonce-footer {
                flex-direction: column;
                gap: var(--spacing-sm);
            }
        }
    </style>
</head>
<body>
    <div class="annonces-container">
        <!-- Header -->
        <div class="annonces-header">
            <div class="annonces-title">
                <i class="fas fa-bell" style="font-size: 2rem; color: var(--notion-blue);"></i>
                <div>
                    <h1>Annonces</h1>
                    <div class="annonces-subtitle">Toutes les actualités de votre promotion</div>
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
                    <i class="fas fa-newspaper"></i>
                </div>
                <div class="stat-value"><?= $total_annonces ?></div>
                <div class="stat-label">Annonces totales</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon non-lues">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-value"><?= $annonces_non_lues ?></div>
                <div class="stat-label">Non lues</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon importantes">
                    <i class="fas fa-exclamation"></i>
                </div>
                <div class="stat-value"><?= $annonces_importantes ?></div>
                <div class="stat-label">Importantes</div>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <label style="font-weight: 600; color: var(--notion-gray-700);">
                <i class="fas fa-filter"></i> Filtrer :
            </label>
            <button class="filter-btn active" data-filter="tous">
                Tous
            </button>
            <button class="filter-btn" data-filter="non-lu">
                <i class="fas fa-envelope"></i> Non lus
            </button>
            <button class="filter-btn" data-filter="important">
                <i class="fas fa-star"></i> Importants
            </button>
            <button class="filter-btn" data-filter="academique">
                <i class="fas fa-graduation-cap"></i> Académique
            </button>
            <button class="filter-btn" data-filter="vie">
                <i class="fas fa-users"></i> Vie étudiante
            </button>
        </div>
        
        <!-- Annonces List -->
        <div class="annonces-list" id="annonces-list">
            <?php if (empty($annonces)): ?>
                <div class="empty-annonces">
                    <i class="fas fa-inbox"></i>
                    <h3>Aucune annonce</h3>
                    <p>Il n'y a actuellement aucune annonce</p>
                </div>
            <?php else: ?>
                <?php foreach ($annonces as $annonce): 
                    $is_lue = in_array($annonce['id_annonce'], $lues);
                    $importance_class = 'importance-' . ($annonce['importance'] ?? 'basse');
                    $categorie_label = [
                        'generale' => 'Général',
                        'academique' => 'Académique',
                        'vie_etudiante' => 'Vie étudiante',
                        'maintenance' => 'Maintenance'
                    ][$annonce['categorie'] ?? 'generale'];
                ?>
                    <div class="annonce-card <?= $importance_class ?> <?= !$is_lue ? 'non-lue' : '' ?>" data-filter="<?= $annonce['categorie'] ?>" data-importance="<?= $annonce['importance'] ?>" data-read="<?= $is_lue ? 'oui' : 'non' ?>">
                        <?php if (!$is_lue): ?>
                            <div class="annonce-indicator non-lu"></div>
                        <?php endif; ?>
                        
                        <div class="annonce-header">
                            <div class="annonce-title-section">
                                <h3 class="annonce-titre"><?= htmlspecialchars($annonce['titre']) ?></h3>
                                <div class="annonce-meta">
                                    <div class="annonce-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d/m/Y à H:i', strtotime($annonce['date_creation'])) ?>
                                    </div>
                                    <div class="annonce-meta-item">
                                        <i class="fas fa-eye"></i>
                                        <?= $annonce['nombre_lectures'] ?> lecteur<?= $annonce['nombre_lectures'] > 1 ? 's' : '' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="annonce-badges">
                                <span class="badge badge-categorie <?= $annonce['categorie'] ?>">
                                    <?= $categorie_label ?>
                                </span>
                                <span class="badge badge-importance <?= $annonce['importance'] ?? 'basse' ?>">
                                    <?= ucfirst($annonce['importance'] ?? 'basse') ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="annonce-contenu">
                            <?= htmlspecialchars(substr($annonce['contenu'], 0, 300)) ?>
                            <?= strlen($annonce['contenu']) > 300 ? '...' : '' ?>
                        </div>
                        
                        <div class="annonce-footer">
                            <div class="annonce-auteur">
                                <div class="annonce-auteur-avatar">
                                    <?= strtoupper(substr($annonce['auteur_nom'] ?? 'A', 0, 1)) ?>
                                </div>
                                <span><?= htmlspecialchars(($annonce['auteur_prenom'] ?? 'Auteur') . ' ' . ($annonce['auteur_nom'] ?? '')) ?></span>
                            </div>
                            <div class="annonce-actions">
                                <button class="action-btn mark-read" title="<?= $is_lue ? 'Marquer comme non-lu' : 'Marquer comme lu' ?>">
                                    <i class="fas fa-<?= $is_lue ? 'check' : 'circle' ?>"></i>
                                </button>
                                <button class="action-btn" title="Ajouter aux favoris">
                                    <i class="fas fa-bookmark"></i>
                                </button>
                                <button class="action-btn" title="Signaler">
                                    <i class="fas fa-flag"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const filter = this.dataset.filter;
                
                // Update active button
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Filter annonces
                document.querySelectorAll('.annonce-card').forEach(card => {
                    let show = false;
                    
                    if (filter === 'tous') {
                        show = true;
                    } else if (filter === 'non-lu') {
                        show = card.dataset.read === 'non';
                    } else if (filter === 'important') {
                        show = card.dataset.importance === 'haute';
                    } else if (filter === 'academique') {
                        show = card.dataset.filter === 'academique';
                    } else if (filter === 'vie') {
                        show = card.dataset.filter === 'vie_etudiante';
                    }
                    
                    card.style.display = show ? '' : 'none';
                });
            });
        });
        
        // Mark as read functionality
        document.querySelectorAll('.mark-read').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const card = this.closest('.annonce-card');
                card.classList.toggle('non-lue');
                this.classList.toggle('fas-check', 'fas-circle');
            });
        });
    </script>
</body>
</html>