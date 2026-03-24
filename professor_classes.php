<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Vérifier que l'utilisateur est professeur
if ($_SESSION['user_role'] !== 'professeur') {
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
    // === CLASSES DU PROFESSEUR ===
    $stmt = $pdo->prepare("
        SELECT 
            c.id_classe,
            c.nom as classe_nom,
            m.nom as matiere_nom,
            m.couleur_hex,
            COUNT(DISTINCT u.id_utilisateur) as nb_etudiants,
            c.promotion,
            c.annee_scolaire,
            c.est_active,
            AVG(f.taux_reussite) as taux_reussite_moyen,
            COUNT(DISTINCT d.id_deck) as nb_decks_classe,
            COUNT(DISTINCT f.id_flashcard) as nb_flashcards_classe
        FROM classes c
        LEFT JOIN matieres m ON c.matiere_id = m.id_matiere
        LEFT JOIN utilisateurs u ON u.classe_id = c.id_classe AND u.role = 'etudiant' AND u.est_actif = 1
        LEFT JOIN decks d ON u.id_utilisateur = d.createur_id
        LEFT JOIN flashcards f ON d.id_deck = f.deck_id
        WHERE c.professeur_id = ?
        GROUP BY c.id_classe, c.nom, m.nom, m.couleur_hex, c.promotion, c.annee_scolaire, c.est_active
        ORDER BY c.nom, m.nom
    ");
    $stmt->execute([$user_id]);
    $classes = $stmt->fetchAll();
    
    // === ÉTUDIANTS PAR CLASSE ===
    $stmt = $pdo->prepare("
        SELECT 
            u.id_utilisateur,
            CONCAT(u.prenom, ' ', u.nom) as etudiant_nom,
            u.classe_id,
            c.nom as classe_nom,
            u.promotion,
            u.derniere_connexion,
            CASE 
                WHEN u.derniere_connexion >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'Actif'
                WHEN u.derniere_connexion >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'Cette semaine'
                ELSE 'Inactif'
            END as statut_activite,
            (SELECT COUNT(*) FROM decks WHERE createur_id = u.id_utilisateur) as nb_decks,
            (SELECT AVG(taux_reussite) FROM flashcards WHERE deck_id IN (
                SELECT id_deck FROM decks WHERE createur_id = u.id_utilisateur
            )) as taux_reussite
        FROM utilisateurs u
        JOIN classes c ON u.classe_id = c.id_classe
        WHERE c.professeur_id = ?
        AND u.role = 'etudiant' 
        AND u.est_actif = 1
        ORDER BY u.nom, u.prenom
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $etudiants = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $classes = [];
    $etudiants = [];
    error_log("Erreur classes professeur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Classes - OSBT Connect</title>
    
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
        
        .header-subtitle {
            color: var(--gray-600);
            font-size: 1rem;
        }
        
        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .class-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .class-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--osbt-primary);
        }
        
        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .class-info {
            flex: 1;
        }
        
        .class-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-sm);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .class-subject {
            font-size: 0.875rem;
            color: var(--gray-600);
            background: var(--gray-100);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-weight: 500;
        }
        
        .class-badge {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .badge-active {
            background: var(--osbt-primary);
            color: var(--white);
        }
        
        .badge-inactive {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .class-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--spacing-md);
            margin-top: var(--spacing-md);
        }
        
        .stat-item {
            text-align: center;
            padding: var(--spacing-sm);
            background: var(--gray-50);
            border-radius: var(--radius-md);
        }
        
        .stat-value {
            display: block;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--osbt-primary);
            margin-bottom: var(--spacing-xs);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .students-section {
            margin-top: var(--spacing-lg);
        }
        
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: var(--spacing-md);
        }
        
        .student-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            transition: all 0.3s ease;
        }
        
        .student-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--osbt-primary);
        }
        
        .student-name {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-sm);
        }
        
        .student-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--spacing-md);
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .status-badge {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-active {
            background: var(--osbt-primary);
            color: var(--white);
        }
        
        .status-inactive {
            background: var(--gray-200);
            color: var(--gray-700);
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
            
            .classes-grid {
                grid-template-columns: 1fr;
            }
            
            .students-grid {
                grid-template-columns: 1fr;
            }
            
            .class-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <a href="professor_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Retour au dashboard
            </a>
            
            <h1 class="header-title">Mes Classes</h1>
            <p class="header-subtitle">Gérez vos classes et suivez la progression de vos étudiants</p>
        </div>
        
        <!-- Classes Grid -->
        <?php if (empty($classes)): ?>
            <div class="empty-state">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3>Aucune classe assignée</h3>
                <p>Vous n'avez pas encore de classes assignées. Contactez l'administration.</p>
            </div>
        <?php else: ?>
            <div class="classes-grid">
                <?php foreach ($classes as $classe): ?>
                    <div class="class-card">
                        <div class="class-header">
                            <div class="class-info">
                                <div class="class-title">
                                    <?php echo htmlspecialchars($classe['classe_nom']); ?>
                                    <span class="class-subject"><?php echo htmlspecialchars($classe['matiere_nom']); ?></span>
                                </div>
                                
                                <div class="class-badge <?php echo $classe['est_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?php echo $classe['est_active'] ? 'Active' : 'Inactive'; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="class-badge <?php echo $classe['est_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                            Promotion <?php echo htmlspecialchars($classe['promotion']); ?>
                        </div>
                    </div>
                    
                    <div class="class-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $classe['nb_etudiants']; ?></span>
                            <span class="stat-label">Étudiants</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo round($classe['taux_reussite_moyen'] ?? 0); ?>%</span>
                            <span class="stat-label">Réussite</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $classe['nb_decks_classe']; ?></span>
                            <span class="stat-label">Decks</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $classe['nb_flashcards_classe']; ?></span>
                            <span class="stat-label">Flashcards</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Students Section -->
            <div class="students-section">
                <h2 style="margin-bottom: var(--spacing-md); color: var(--gray-900);">
                    <i class="fas fa-users" style="margin-right: var(--spacing-sm);"></i>
                    Étudiants récents
                </h2>
                
                <?php if (empty($etudiants)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h3>Aucun étudiant trouvé</h3>
                        <p>Aucun étudiant n'est encore inscrit dans vos classes.</p>
                    </div>
                <?php else: ?>
                    <div class="students-grid">
                        <?php foreach ($etudiants as $etudiant): ?>
                            <div class="student-card">
                                <div class="student-name"><?php echo htmlspecialchars($etudiant['etudiant_nom']); ?></div>
                                
                                <div class="student-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-graduation-cap"></i>
                                        <?php echo htmlspecialchars($etudiant['classe_nom']); ?>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-layer-group"></i>
                                        <?php echo $etudiant['nb_decks']; ?> decks
                                    </div>
                                    <div class="meta-item">
                                        <span class="status-badge <?php echo $etudiant['statut_activite'] === 'Actif' ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $etudiant['statut_activite']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
