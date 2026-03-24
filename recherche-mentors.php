<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';

$user_id = $_SESSION['user_id'];

// Récupérer la liste des matières pour le filtre
$stmt = $pdo->query("SELECT id_matiere, nom, code, couleur_hex FROM matieres WHERE est_active = 1 ORDER BY nom");
$matieres = $stmt->fetchAll();

// Construire la requête avec filtres
$sql = "
    SELECT 
        u.id_utilisateur,
        u.prenom,
        u.nom,
        u.bio,
        u.photo_profil,
        u.promotion,
        cm.note_moyenne,
        cm.nombre_seances,
        cm.niveau,
        m.nom as matiere_nom,
        m.couleur_hex,
        GROUP_CONCAT(DISTINCT CONCAT(dm.jour_semaine, ' ', dm.heure_debut, '-', dm.heure_fin) SEPARATOR '; ') as disponibilites
    FROM utilisateurs u
    JOIN competences_mentors cm ON u.id_utilisateur = cm.mentor_id
    JOIN matieres m ON cm.matiere_id = m.id_matiere
    LEFT JOIN disponibilites_mentors dm ON u.id_utilisateur = dm.mentor_id AND dm.est_active = 1
    WHERE u.role = 'mentor' 
      AND cm.statut = 'disponible'
";

$params = [];

// Filtre par matière
if (!empty($_GET['matiere'])) {
    $sql .= " AND cm.matiere_id = ?";
    $params[] = $_GET['matiere'];
}

// Filtre par niveau
if (!empty($_GET['niveau'])) {
    $sql .= " AND cm.niveau = ?";
    $params[] = $_GET['niveau'];
}

// Filtre par disponibilité (jour)
if (!empty($_GET['jour'])) {
    $sql .= " AND dm.jour_semaine = ?";
    $params[] = $_GET['jour'];
}

$sql .= " GROUP BY u.id_utilisateur, m.id_matiere";
$sql .= " ORDER BY cm.note_moyenne DESC, cm.nombre_seances DESC";

// Pagination (10 par page)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(DISTINCT t.id_utilisateur) as total FROM ($sql) as t";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_mentors = $count_stmt->fetch()['total'];
$total_pages = ceil($total_mentors / $limit);

$sql .= " LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mentors = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rechercher un mentor - OSBT Connect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Notion/Vygo inspired colors - blanc/vert theme */
            --notion-blue: #4285f4;
            --notion-green: #00c853;
            --notion-purple: #8b5cf6;
            --notion-orange: #f97316;
            --notion-pink: #ec4899;
            --notion-red: #ef4444;
            
            /* Gradients */
            --gradient-green: linear-gradient(135deg, #00c853, #00e676);
            --gradient-blue: linear-gradient(135deg, #4285f4, #1976d2);
            --gradient-purple: linear-gradient(135deg, #8b5cf6, #7c3aed);
            
            /* Neutrals */
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
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            
            /* Transitions */
            --transition-fast: 150ms ease;
            --transition-base: 250ms ease;
            --transition-slow: 350ms ease;
            
            /* Primary colors for consistency */
            --primary: var(--notion-green);
            --primary-dark: #007e33;
            --tech-blue: var(--notion-blue);
            --business-green: var(--notion-green);
        }
        
        * { margin:0; padding:0; box-sizing:border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--notion-gray-50);
            color: var(--notion-gray-900);
            min-height: 100vh;
            padding: var(--spacing-xl);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-xl);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-2xl);
            box-shadow: var(--shadow-sm);
        }
        
        .header h1 {
            font-size: 2rem;
            background: linear-gradient(90deg, var(--notion-green), #00e676);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
        
        .nav-links a {
            color: var(--notion-gray-600);
            text-decoration: none;
            margin-left: var(--spacing-xl);
            transition: color var(--transition-base);
        }
        
        .nav-links a:hover {
            color: var(--primary);
        }
        
        /* Layout principal */
        .search-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }
        
        /* Sidebar filtres */
        .filters {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-xl);
            padding: var(--spacing-xl);
            height: fit-content;
            box-shadow: var(--shadow-sm);
        }
        
        .filters h3 {
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group {
            margin-bottom: 25px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: var(--spacing-sm);
            color: var(--notion-gray-600);
            font-size: 0.875rem;
        }
        
        .filter-select {
            width: 100%;
            padding: var(--spacing-sm) var(--spacing-md);
            background: var(--notion-gray-50);
            border: 1px solid var(--notion-gray-300);
            border-radius: var(--radius-md);
            color: var(--notion-gray-900);
            font-size: 0.9375rem;
            cursor: pointer;
            transition: border-color var(--transition-base);
        }
        
        .filter-select:hover {
            border-color: var(--notion-gray-400);
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 200, 83, 0.1);
        }
        
        .filter-select option {
            background: white;
            color: var(--notion-gray-900);
        }
        
        .btn {
            display: inline-block;
            padding: var(--spacing-sm) var(--spacing-xl);
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            transition: all var(--transition-base);
            border: none;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
            margin-top: var(--spacing-sm);
        }
        
        .btn-outline:hover {
            background: rgba(0, 200, 83, 0.1);
            box-shadow: var(--shadow-sm);
        }
        
        /* Liste des mentors */
        .mentors-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .mentor-card {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-xl);
            padding: var(--spacing-xl);
            display: flex;
            gap: var(--spacing-xl);
            transition: all var(--transition-base);
            box-shadow: var(--shadow-sm);
        }
        
        .mentor-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .mentor-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--notion-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            color: white;
            flex-shrink: 0;
        }
        
        .mentor-info {
            flex: 1;
        }
        
        .mentor-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .mentor-name {
            font-size: 20px;
            font-weight: 600;
        }
        
        .mentor-promo {
            background: var(--notion-gray-100);
            padding: var(--spacing-xs) var(--spacing-md);
            border-radius: var(--radius-full);
            font-size: 0.8125rem;
            color: var(--notion-gray-600);
        }
        
        .mentor-bio {
            color: var(--notion-gray-600);
            margin-bottom: var(--spacing-md);
            line-height: 1.5;
        }
        
        .mentor-tags {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .tag {
            background: rgba(66, 133, 244, 0.1);
            color: var(--notion-blue);
            padding: var(--spacing-xs) var(--spacing-md);
            border-radius: var(--radius-full);
            font-size: 0.8125rem;
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }
        
        .tag i {
            font-size: 12px;
        }
        
        .mentor-stats {
            display: flex;
            gap: var(--spacing-xl);
            color: var(--notion-gray-500);
            font-size: 0.875rem;
            margin-bottom: var(--spacing-md);
        }
        
        .mentor-rating {
            color: #FFD700;
        }
        
        .mentor-disponibilites {
            background: rgba(0, 200, 83, 0.1);
            border-left: 3px solid var(--primary);
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            color: var(--notion-gray-700);
            margin-top: var(--spacing-sm);
        }
        
        .mentor-actions {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        
        .btn-small {
            padding: 8px 20px;
            font-size: 14px;
            width: auto;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 40px;
        }
        
        .pagination a, .pagination span {
            background: white;
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--radius-md);
            color: var(--notion-gray-700);
            text-decoration: none;
            border: 1px solid var(--notion-gray-300);
            transition: all var(--transition-base);
        }
        
        .pagination a:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .pagination .active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: var(--spacing-2xl) var(--spacing-xl);
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
        }
        
        .empty-state i {
            font-size: 3.75rem;
            color: var(--notion-gray-400);
            margin-bottom: var(--spacing-xl);
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .search-layout {
                grid-template-columns: 1fr;
            }
            
            .mentor-card {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .mentor-header {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            
            .mentor-stats {
                justify-content: center;
            }
            
            .mentor-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1><i class="fas fa-users"></i> Mentorat</h1>
                <p style="color: var(--notion-gray-600);">Trouvez le mentor idéal pour vous accompagner</p>
            </div>
            <div class="nav-links">
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="learning-center.php"><i class="fas fa-graduation-cap"></i> Learning Center</a>
                <a href="mentorat.php"><i class="fas fa-arrow-left"></i> Retour</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>
        
        <!-- Section recherche -->
        <div class="search-layout">
            <!-- Filtres -->
            <div class="filters">
                <h3><i class="fas fa-sliders-h" style="color: var(--notion-green);"></i> Filtres</h3>
                <form method="GET" action="">
                    <div class="filter-group">
                        <label><i class="fas fa-book"></i> Matière</label>
                        <select name="matiere" class="filter-select">
                            <option value="">Toutes les matières</option>
                            <?php foreach ($matieres as $m): ?>
                                <option value="<?= $m['id_matiere'] ?>" <?= (isset($_GET['matiere']) && $_GET['matiere'] == $m['id_matiere']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-chart-line"></i> Niveau</label>
                        <select name="niveau" class="filter-select">
                            <option value="">Tous niveaux</option>
                            <option value="intermediaire" <?= (isset($_GET['niveau']) && $_GET['niveau'] == 'intermediaire') ? 'selected' : '' ?>>Intermédiaire</option>
                            <option value="avance" <?= (isset($_GET['niveau']) && $_GET['niveau'] == 'avance') ? 'selected' : '' ?>>Avancé</option>
                            <option value="expert" <?= (isset($_GET['niveau']) && $_GET['niveau'] == 'expert') ? 'selected' : '' ?>>Expert</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-calendar-alt"></i> Disponibilité</label>
                        <select name="jour" class="filter-select">
                            <option value="">Tous les jours</option>
                            <option value="lundi" <?= (isset($_GET['jour']) && $_GET['jour'] == 'lundi') ? 'selected' : '' ?>>Lundi</option>
                            <option value="mardi" <?= (isset($_GET['jour']) && $_GET['jour'] == 'mardi') ? 'selected' : '' ?>>Mardi</option>
                            <option value="mercredi" <?= (isset($_GET['jour']) && $_GET['jour'] == 'mercredi') ? 'selected' : '' ?>>Mercredi</option>
                            <option value="jeudi" <?= (isset($_GET['jour']) && $_GET['jour'] == 'jeudi') ? 'selected' : '' ?>>Jeudi</option>
                            <option value="vendredi" <?= (isset($_GET['jour']) && $_GET['jour'] == 'vendredi') ? 'selected' : '' ?>>Vendredi</option>
                            <option value="samedi" <?= (isset($_GET['jour']) && $_GET['jour'] == 'samedi') ? 'selected' : '' ?>>Samedi</option>
                            <option value="dimanche" <?= (isset($_GET['jour']) && $_GET['jour'] == 'dimanche') ? 'selected' : '' ?>>Dimanche</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Rechercher
                    </button>
                    
                    <a href="recherche-mentors.php" class="btn btn-outline">
                        <i class="fas fa-undo"></i> Réinitialiser
                    </a>
                </form>
            </div>
            
            <!-- Résultats -->
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="font-size: 22px;">
                        <?= $total_mentors ?> mentor<?= $total_mentors > 1 ? 's' : '' ?> trouvé<?= $total_mentors > 1 ? 's' : '' ?>
                    </h2>
                    <span style="color: var(--notion-gray-500);">
                        Page <?= $page ?> sur <?= $total_pages ?>
                    </span>
                </div>
                
                <?php if (empty($mentors)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <h3 style="margin-bottom: 10px;">Aucun mentor trouvé</h3>
                        <p style="color: var(--notion-gray-600); margin-bottom: var(--spacing-xl);">
                            Essayez de modifier vos filtres ou revenez plus tard.
                        </p>
                        <a href="recherche-mentors.php" class="btn" style="width: auto; padding: var(--spacing-sm) var(--spacing-xl);">
                            <i class="fas fa-undo"></i> Réinitialiser les filtres
                        </a>
                    </div>
                <?php else: ?>
                    <div class="mentors-list">
                        <?php foreach ($mentors as $mentor): ?>
                            <div class="mentor-card">
                                <div class="mentor-avatar" style="background: <?= $mentor['couleur_hex'] ?>;">
                                    <?= strtoupper(substr($mentor['prenom'], 0, 1) . substr($mentor['nom'], 0, 1)) ?>
                                </div>
                                <div class="mentor-info">
                                    <div class="mentor-header">
                                        <div>
                                            <span class="mentor-name">
                                                <?= htmlspecialchars($mentor['prenom'] . ' ' . $mentor['nom']) ?>
                                            </span>
                                            <span class="mentor-promo">
                                                Promo <?= $mentor['promotion'] ?>
                                            </span>
                                        </div>
                                        <span class="tag" style="background: <?= $mentor['couleur_hex'] ?>20; color: <?= $mentor['couleur_hex'] ?>;">
                                            <i class="fas fa-graduation-cap"></i>
                                            <?= $mentor['matiere_nom'] ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mentor-bio">
                                        <?= htmlspecialchars($mentor['bio'] ?? 'Pas de biographie disponible.') ?>
                                    </div>
                                    
                                    <div class="mentor-tags">
                                        <span class="tag">
                                            <i class="fas fa-level-up-alt"></i>
                                            <?= ucfirst($mentor['niveau']) ?>
                                        </span>
                                        <span class="tag">
                                            <i class="fas fa-star" style="color: #FFD700;"></i>
                                            <?= round($mentor['note_moyenne'], 1) ?> (<?= $mentor['nombre_seances'] ?> sessions)
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($mentor['disponibilites'])): ?>
                                        <div class="mentor-disponibilites">
                                            <i class="fas fa-calendar-check" style="color: var(--primary); margin-right: var(--spacing-sm);"></i>
                                            <?= htmlspecialchars(substr($mentor['disponibilites'], 0, 80)) ?>…
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mentor-actions">
                                        <a href="profil-mentor.php?id=<?= $mentor['id_utilisateur'] ?>" class="btn btn-small">
                                            <i class="fas fa-user"></i> Voir le profil
                                        </a>
                                        <a href="demande-session.php?mentor=<?= $mentor['id_utilisateur'] ?>&matiere=<?= $mentor['matiere_nom'] ?>" class="btn btn-small" style="background: var(--notion-blue);">
                                            <i class="fas fa-calendar-plus"></i> Demander une session
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>