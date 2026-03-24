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
    // === DECKS DU PROFESSEUR ===
    $stmt = $pdo->prepare("
        SELECT 
            d.*,
            m.nom as matiere_nom,
            m.couleur_hex,
            COUNT(DISTINCT f.id_flashcard) as nb_flashcards,
            AVG(f.taux_reussite) as taux_reussite_moyen,
            COUNT(DISTINCT CASE WHEN f.prochaine_revision <= CURDATE() THEN f.id_flashcard END) as a_reviser,
            (SELECT COUNT(DISTINCT u2.id_utilisateur) 
                FROM utilisateurs u2 
                JOIN classes c2 ON u2.classe_id = c2.id_classe 
                WHERE c2.professeur_id = ?) as etudiants_cibles
        FROM decks d
        LEFT JOIN matieres m ON d.matiere_id = m.id_matiere
        LEFT JOIN flashcards f ON d.id_deck = f.deck_id
        WHERE d.createur_id = ?
        GROUP BY d.id_deck
        ORDER BY d.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id, $user_id]);
    $decks = $stmt->fetchAll();
    
    // === FLASHCARDS RÉCENTES ===
    $stmt = $pdo->prepare("
        SELECT 
            f.*,
            d.titre as deck_titre,
            m.nom as matiere_nom,
            u.prenom as createur_prenom,
            u.nom as createur_nom,
            f.prochaine_revision,
            f.taux_reussite
        FROM flashcards f
        JOIN decks d ON f.deck_id = d.id_deck
        JOIN matieres m ON d.matiere_id = m.id_matiere
        JOIN utilisateurs u ON d.createur_id = u.id_utilisateur
        WHERE d.createur_id IN (
            SELECT id_utilisateur FROM utilisateurs 
            WHERE classe_id IN (
                SELECT id_classe FROM classes WHERE professeur_id = ?
            )
        )
        ORDER BY f.prochaine_revision ASC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $flashcards_recentes = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $decks = [];
    $flashcards_recentes = [];
    error_log("Erreur decks professeur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Decks - OSBT Connect</title>
    
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
        
        .header-actions {
            display: flex;
            gap: var(--spacing-md);
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
        
        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-secondary:hover {
            background: var(--gray-300);
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
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
        
        .deck-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-md);
        }
        
        .deck-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .deck-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--osbt-primary);
        }
        
        .deck-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-md);
        }
        
        .deck-info {
            flex: 1;
        }
        
        .deck-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: var(--spacing-sm);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .deck-subject {
            font-size: 0.875rem;
            color: var(--gray-600);
            background: var(--gray-100);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-weight: 500;
        }
        
        .deck-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--spacing-sm);
            margin-top: var(--spacing-md);
        }
        
        .deck-stat {
            text-align: center;
            padding: var(--spacing-sm);
            background: var(--gray-50);
            border-radius: var(--radius-md);
        }
        
        .stat-value {
            display: block;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--osbt-primary);
            margin-bottom: var(--spacing-xs);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        .flashcard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-md);
        }
        
        .flashcard-item {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            transition: all 0.3s ease;
        }
        
        .flashcard-item:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--osbt-blue);
        }
        
        .flashcard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-sm);
        }
        
        .flashcard-title {
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .flashcard-meta {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .revision-badge {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .revision-urgent {
            background: var(--osbt-primary);
            color: var(--white);
        }
        
        .revision-soon {
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
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .deck-grid,
            .flashcard-grid {
                grid-template-columns: 1fr;
            }
            
            .deck-stats {
                grid-template-columns: repeat(2, 1fr);
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
            
            <h1 class="header-title">Mes Decks</h1>
            <div class="header-actions">
                <a href="professor_decks.php?action=create" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Créer un deck
                </a>
                <a href="professor_decks.php?action=import" class="btn btn-secondary">
                    <i class="fas fa-upload"></i>
                    Importer
                </a>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Decks Section -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-layer-group" style="margin-right: var(--spacing-sm);"></i>
                        Mes Decks
                    </h2>
                    <span style="color: var(--gray-600); font-size: 0.875rem;">
                            <?php echo count($decks); ?> decks
                    </span>
                </div>
            </div>
                
                <?php if (empty($decks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-layer-group"></i>
                        <h3>Aucun deck créé</h3>
                        <p>Commencez par créer votre premier deck de flashcards.</p>
                    </div>
                <?php else: ?>
                    <div class="deck-grid">
                        <?php foreach ($decks as $deck): ?>
                            <div class="deck-card">
                                <div class="deck-header">
                                    <div class="deck-info">
                                        <div class="deck-title">
                                            <?php echo htmlspecialchars($deck['titre']); ?>
                                            <span class="deck-subject"><?php echo htmlspecialchars($deck['matiere_nom']); ?></span>
                                        </div>
                                        
                                        <div class="deck-stats">
                                            <div class="deck-stat">
                                                <span class="stat-value"><?php echo $deck['nb_flashcards']; ?></span>
                                                <span class="stat-label">Flashcards</span>
                                            </div>
                                            <div class="deck-stat">
                                                <span class="stat-value"><?php echo round($deck['taux_reussite_moyen'] ?? 0); ?>%</span>
                                                <span class="stat-label">Réussite</span>
                                            </div>
                                            <div class="deck-stat">
                                                <span class="stat-value"><?php echo $deck['etudiants_cibles']; ?></span>
                                                <span class="stat-label">Étudiants</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Flashcards Récentes Section -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-brain" style="margin-right: var(--spacing-sm);"></i>
                        Flashcards à réviser
                    </h2>
                    <span style="color: var(--gray-600); font-size: 0.875rem;">
                            <?php echo count($flashcards_recentes); ?> cartes
                    </span>
                </div>
            </div>
                
                <?php if (empty($flashcards_recentes)): ?>
                    <div class="empty-state">
                        <i class="fas fa-brain"></i>
                        <h3>Aucune flashcard à réviser</h3>
                        <p>Toutes les flashcards sont à jour.</p>
                    </div>
                <?php else: ?>
                    <div class="flashcard-grid">
                        <?php foreach ($flashcards_recentes as $flashcard): ?>
                            <div class="flashcard-item">
                                <div class="flashcard-header">
                                    <div class="flashcard-title"><?php echo htmlspecialchars($flashcard['question']); ?></div>
                                    <div class="flashcard-meta">
                                        <span><?php echo htmlspecialchars($flashcard['deck_titre']); ?></span>
                                        <span class="revision-badge <?php echo (strtotime($flashcard['prochaine_revision']) <= strtotime('today')) ? 'revision-urgent' : 'revision-soon'; ?>">
                                            <?php echo (strtotime($flashcard['prochaine_revision']) <= strtotime('today')) ? 'À réviser' : 'Bientôt'; ?>
                                        </span>
                                        <span>• <?php echo round($flashcard['taux_reussite']); ?>%</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
