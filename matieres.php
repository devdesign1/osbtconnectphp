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

// Récupérer les matières de la promotion
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.id_matiere,
            m.matiere_nom,
            m.code as matiere_code,
            m.couleur_hex,
            m.description,
            COUNT(DISTINCT pc.id_cours) as nombre_cours,
            COUNT(DISTINCT t.id_tache) as nombre_taches
        FROM matieres m
        LEFT JOIN planning_cours pc ON m.id_matiere = pc.matiere_id AND pc.promotion = ?
        LEFT JOIN taches t ON m.id_matiere = t.matiere_id AND t.user_id = ?
        GROUP BY m.id_matiere
        ORDER BY m.matiere_nom
    ");
    $stmt->execute([$user_promotion, $user_id]);
    $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $matieres = [];
}

// Récupérer les stats générales
$total_matieres = count($matieres);
$total_cours = array_sum(array_column($matieres, 'nombre_cours'));
$total_taches = array_sum(array_column($matieres, 'nombre_taches'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matières - OSBT Connect</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --notion-blue: #00c853;
            --notion-green: #00c853;
            --notion-purple: #00c853;
            --notion-orange: #f97316;
            --notion-pink: #ec4899;
            --notion-red: #ef4444;
            
            --gradient-green: linear-gradient(135deg, #00c853, #00e676);
            --gradient-blue: linear-gradient(135deg, #00c853, #1976d2);
            --gradient-purple: linear-gradient(135deg, #00c853, #a78bfa);
            
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
        
        .matieres-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }
        
        /* Header */
        .matieres-header {
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
        
        .matieres-title {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }
        
        .matieres-title h1 {
            font-size: 2rem;
            background: var(--gradient-purple);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
        
        .matieres-subtitle {
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
        
        .stat-icon.matieres {
            background: rgba(139, 92, 246, 0.1);
            color: var(--notion-purple);
        }
        
        .stat-icon.cours {
            background: rgba(66, 133, 244, 0.1);
            color: var(--notion-blue);
        }
        
        .stat-icon.taches {
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
        
        /* Search Bar */
        .search-bar {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-2xl);
            display: flex;
            gap: var(--spacing-md);
            align-items: center;
            box-shadow: var(--shadow-sm);
        }
        
        .search-input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 1rem;
            padding: var(--spacing-sm);
        }
        
        .search-input::placeholder {
            color: var(--notion-gray-400);
        }
        
        .search-icon {
            color: var(--notion-gray-400);
            font-size: 1.25rem;
        }
        
        /* Matieres Grid */
        .matieres-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--spacing-xl);
        }
        
        .matiere-card {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-base);
            cursor: pointer;
            position: relative;
        }
        
        .matiere-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: transparent;
        }
        
        .matiere-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--matiere-color);
            transition: height var(--transition-base);
        }
        
        .matiere-card:hover::before {
            height: 6px;
        }
        
        .matiere-header {
            padding: var(--spacing-lg);
            background: linear-gradient(135deg, var(--matiere-color) 0%, rgba(var(--matiere-rgb), 0.8) 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .matiere-header::before {
            content: '';
            position: absolute;
            bottom: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translate(-100%, -100%); }
            100% { transform: translate(100%, 100%); }
        }
        
        .matiere-code {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-md);
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            text-transform: uppercase;
        }
        
        .matiere-nom {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: var(--spacing-xs);
            position: relative;
            z-index: 1;
        }
        
        .matiere-body {
            padding: var(--spacing-lg);
        }
        
        .matiere-description {
            font-size: 0.875rem;
            color: var(--notion-gray-600);
            margin-bottom: var(--spacing-lg);
            line-height: 1.5;
            min-height: 40px;
        }
        
        .matiere-stats {
            display: flex;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-lg);
            border-bottom: 1px solid var(--notion-gray-200);
        }
        
        .matiere-stat {
            flex: 1;
            text-align: center;
        }
        
        .matiere-stat-value {
            display: block;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--matiere-color);
            margin-bottom: var(--spacing-xs);
        }
        
        .matiere-stat-label {
            display: block;
            font-size: 0.75rem;
            color: var(--notion-gray-500);
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .matiere-actions {
            display: flex;
            gap: var(--spacing-sm);
        }
        
        .action-btn {
            flex: 1;
            padding: var(--spacing-sm) var(--spacing-md);
            border: 1px solid var(--notion-gray-200);
            background: white;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-base);
            color: var(--notion-gray-600);
        }
        
        .action-btn:hover {
            background: var(--matiere-color);
            color: white;
            border-color: var(--matiere-color);
        }
        
        .action-btn i {
            margin-right: var(--spacing-xs);
        }
        
        /* Empty state */
        .empty-matieres {
            text-align: center;
            padding: var(--spacing-2xl);
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-xl);
        }
        
        .empty-matieres i {
            font-size: 3rem;
            color: var(--notion-gray-300);
            margin-bottom: var(--spacing-md);
        }
        
        .empty-matieres h3 {
            color: var(--notion-gray-600);
            margin-bottom: var(--spacing-sm);
        }
        
        .empty-matieres p {
            color: var(--notion-gray-500);
        }
        
        /* Color variants */
        .color-blue { --matiere-color: #4285f4; --matiere-rgb: 66, 133, 244; }
        .color-green { --matiere-color: #00c853; --matiere-rgb: 0, 200, 83; }
        .color-purple { --matiere-color: #8b5cf6; --matiere-rgb: 139, 92, 246; }
        .color-orange { --matiere-color: #f97316; --matiere-rgb: 249, 115, 22; }
        .color-pink { --matiere-color: #ec4899; --matiere-rgb: 236, 72, 153; }
        .color-red { --matiere-color: #ef4444; --matiere-rgb: 239, 68, 68; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .matieres-container {
                padding: var(--spacing-md);
            }
            
            .matieres-header {
                flex-direction: column;
                gap: var(--spacing-md);
                text-align: center;
            }
            
            .matieres-title h1 {
                font-size: 1.5rem;
            }
            
            .matieres-grid {
                grid-template-columns: 1fr;
            }
            
            .matiere-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="matieres-container">
        <!-- Header -->
        <div class="matieres-header">
            <div class="matieres-title">
                <i class="fas fa-book" style="font-size: 2rem; color: var(--notion-purple);"></i>
                <div>
                    <h1>Matières</h1>
                    <div class="matieres-subtitle">Catégories de vos cours et tâches</div>
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
                <div class="stat-icon matieres">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="stat-value"><?= $total_matieres ?></div>
                <div class="stat-label">Matières</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon cours">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-value"><?= $total_cours ?></div>
                <div class="stat-label">Cours cette semaine</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon taches">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-value"><?= $total_taches ?></div>
                <div class="stat-label">Tâches associées</div>
            </div>
        </div>
        
        <!-- Search Bar -->
        <div class="search-bar">
            <i class="fas fa-search search-icon"></i>
            <input 
                type="text" 
                class="search-input" 
                placeholder="Rechercher une matière..."
                id="searchInput"
            >
        </div>
        
        <!-- Matieres Grid -->
        <div class="matieres-grid" id="matieresGrid">
            <?php if (empty($matieres)): ?>
                <div class="empty-matieres" style="grid-column: 1 / -1;">
                    <i class="fas fa-book"></i>
                    <h3>Aucune matière trouvée</h3>
                    <p>Les matières de votre promotion s'afficheront ici</p>
                </div>
            <?php else: ?>
                <?php foreach ($matieres as $matiere): ?>
                    <?php
                        // Déterminer la couleur basée sur le code couleur hex
                        $color = $matiere['couleur_hex'] ?? '#8b5cf6';
                        $color_class = 'color-purple'; // Par défaut
                    ?>
                    <div class="matiere-card" style="--matiere-color: <?= $color ?>; --matiere-rgb: <?= implode(',', sscanf($color, '#%02x%02x%02x')) ?>;">
                        <div class="matiere-header">
                            <span class="matiere-code"><?= htmlspecialchars($matiere['matiere_code']) ?></span>
                            <h3 class="matiere-nom"><?= htmlspecialchars($matiere['matiere_nom']) ?></h3>
                        </div>
                        <div class="matiere-body">
                            <div class="matiere-description">
                                <?= $matiere['description'] ? htmlspecialchars($matiere['description']) : 'Pas de description disponible' ?>
                            </div>
                            
                            <div class="matiere-stats">
                                <div class="matiere-stat">
                                    <span class="matiere-stat-value"><?= $matiere['nombre_cours'] ?></span>
                                    <span class="matiere-stat-label">Cours</span>
                                </div>
                                <div class="matiere-stat">
                                    <span class="matiere-stat-value"><?= $matiere['nombre_taches'] ?></span>
                                    <span class="matiere-stat-label">Tâches</span>
                                </div>
                            </div>
                            
                            <div class="matiere-actions">
                                <button class="action-btn" title="Voir les cours">
                                    <i class="fas fa-calendar"></i>
                                    <span class="hide-mobile">Cours</span>
                                </button>
                                <button class="action-btn" title="Voir les tâches">
                                    <i class="fas fa-tasks"></i>
                                    <span class="hide-mobile">Tâches</span>
                                </button>
                                <button class="action-btn" title="Détails">
                                    <i class="fas fa-arrow-right"></i>
                                    <span class="hide-mobile">Détail</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.matiere-card');
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>