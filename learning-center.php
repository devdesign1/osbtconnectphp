<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
$user_id = $_SESSION['user_id'];

// Statistiques globales
$stats_sql = "
SELECT 
    COUNT(DISTINCT d.id_deck) as total_decks,
    COUNT(DISTINCT f.id_flashcard) as total_cards,
    SUM(CASE WHEN f.prochaine_revision <= CURDATE() OR f.prochaine_revision IS NULL THEN 1 ELSE 0 END) as cards_to_review,
    SUM(f.nombre_revisions) as total_reviews
FROM utilisateurs u
LEFT JOIN decks d ON d.createur_id = u.id_utilisateur
LEFT JOIN flashcards f ON f.deck_id = d.id_deck
WHERE u.id_utilisateur = ?
";
$stmt = $pdo->prepare($stats_sql);
$stmt->execute([$user_id]);
$global_stats = $stmt->fetch();

// Matières avec progression
$matieres_sql = "
SELECT 
    m.*,
    COUNT(DISTINCT d.id_deck) as nb_decks,
    COUNT(DISTINCT f.id_flashcard) as nb_cards,
    SUM(CASE WHEN f.prochaine_revision <= CURDATE() OR f.prochaine_revision IS NULL THEN 1 ELSE 0 END) as cards_to_review
FROM matieres m
LEFT JOIN decks d ON d.matiere_id = m.id_matiere AND (d.createur_id = ? OR d.est_public = 1)
LEFT JOIN flashcards f ON f.deck_id = d.id_deck
WHERE m.est_active = 1
GROUP BY m.id_matiere
ORDER BY m.filiere, m.nom
";
$stmt = $pdo->prepare($matieres_sql);
$stmt->execute([$user_id]);
$matieres = $stmt->fetchAll();

// Derniers decks utilisés
$recent_decks_sql = "
SELECT d.*, m.nom as matiere_nom, m.couleur_hex,
       MAX(f.updated_at) as last_reviewed
FROM decks d
LEFT JOIN matieres m ON d.matiere_id = m.id_matiere
LEFT JOIN flashcards f ON f.deck_id = d.id_deck
WHERE d.createur_id = ? OR d.est_public = 1
GROUP BY d.id_deck
ORDER BY last_reviewed DESC, d.updated_at DESC
LIMIT 5
";
$stmt = $pdo->prepare($recent_decks_sql);
$stmt->execute([$user_id]);
$recent_decks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Center | OSBT Connect</title>
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--notion-gray-50);
            color: var(--notion-gray-900);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-2xl);
            padding: var(--spacing-lg);
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--notion-gray-900);
            margin-bottom: 0.25rem;
        }
        
        .header-subtitle {
            color: var(--notion-gray-600);
            font-size: 0.95rem;
        }
        
        .nav-links a {
            color: var(--notion-gray-600);
            text-decoration: none;
            margin-left: var(--spacing-xl);
            font-size: 0.95rem;
            transition: color var(--transition-base);
        }
        
        .nav-links a:hover {
            color: var(--primary);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-2xl);
        }
        
        .stat-card {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            transition: all var(--transition-base);
            box-shadow: var(--shadow-sm);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: var(--spacing-sm);
            color: var(--primary);
        }
        
        .stat-number {
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: var(--spacing-xs);
            color: var(--notion-gray-900);
        }
        
        .stat-label {
            color: var(--notion-gray-600);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* Matières Grid */
        .matieres-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-2xl);
        }
        
        .matiere-card {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            transition: all var(--transition-base);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .matiere-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        
        .matiere-color {
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        
        .matiere-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-sm);
        }
        
        .matiere-code {
            font-family: monospace;
            background: var(--notion-gray-100);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            color: var(--notion-gray-700);
        }
        
        .matiere-filiere {
            font-size: 0.75rem;
            color: var(--notion-gray-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .matiere-title {
            font-size: 1.25rem;
            margin-bottom: var(--spacing-sm);
            color: var(--notion-gray-900);
        }
        
        .matiere-stats {
            display: flex;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-sm);
            font-size: 0.875rem;
            color: var(--notion-gray-600);
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-2xl);
        }
        
        .action-btn {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            color: var(--notion-gray-900);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            transition: all var(--transition-base);
            box-shadow: var(--shadow-sm);
        }
        
        .action-btn:hover {
            background: var(--notion-gray-50);
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .action-btn i {
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        .action-btn strong {
            color: var(--notion-gray-900);
        }
        
        /* Recent Decks */
        .recent-decks {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-2xl);
            box-shadow: var(--shadow-sm);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: var(--spacing-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--notion-gray-900);
        }
        
        .decks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: var(--spacing-lg);
        }
        
        .deck-card {
            background: var(--notion-gray-50);
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            transition: all var(--transition-base);
            cursor: pointer;
        }
        
        .deck-card:hover {
            background: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        
        .deck-matiere {
            display: inline-block;
            font-size: 0.75rem;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            margin-bottom: var(--spacing-sm);
            color: white;
            font-weight: 500;
        }
        
        .deck-title {
            font-size: 1.125rem;
            margin-bottom: var(--spacing-sm);
            color: var(--notion-gray-900);
            font-weight: 600;
        }
        
        .deck-meta {
            font-size: 0.875rem;
            color: var(--notion-gray-600);
            display: flex;
            justify-content: space-between;
            margin-top: var(--spacing-sm);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .nav-links a {
                margin: 0 10px;
            }
            
            .matieres-grid {
                grid-template-columns: 1fr;
            }
            
            .action-btn {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1><i class="fas fa-graduation-cap"></i> Learning Center</h1>
                <p style="color: #aaa; margin-top: 5px;">Apprentissage intelligent avec flashcards SRS</p>
            </div>
            <div class="nav-links">
                <a href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="profil.php"><i class="fas fa-user"></i> Mon profil</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>
        
        <!-- Statistiques globales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-number"><?= $global_stats['total_decks'] ?? 0 ?></div>
                <div class="stat-label">Decks créés</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-cards"></i>
                </div>
                <div class="stat-number"><?= $global_stats['total_cards'] ?? 0 ?></div>
                <div class="stat-label">Cartes totales</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?= $global_stats['cards_to_review'] ?? 0 ?></div>
                <div class="stat-label">À réviser</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-repeat"></i>
                </div>
                <div class="stat-number"><?= $global_stats['total_reviews'] ?? 0 ?></div>
                <div class="stat-label">Révisions totales</div>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="quick-actions">
            <a href="create-deck.php" class="action-btn" onclick="showConstructionAlert(event)">
                <i class="fas fa-plus-circle"></i>
                <div>
                    <strong>Créer un deck</strong>
                    <div style="font-size: 14px; color: #aaa;">Nouveau deck de flashcards</div>
                </div>
            </a>
            
            <a href="flashcards.php?mode=review" class="action-btn" onclick="showConstructionAlert(event)">
                <i class="fas fa-play-circle"></i>
                <div>
                    <strong>Réviser</strong>
                    <div style="font-size: 14px; color: #aaa;">Lancer une session de révision</div>
                </div>
            </a>
            
            <a href="import-flashcards.php" class="action-btn" onclick="showConstructionAlert(event)">
                <i class="fas fa-file-import"></i>
                <div>
                    <strong>Importer</strong>
                    <div style="font-size: 14px; color: #aaa;">Importer depuis Anki/Quizlet</div>
                </div>
            </a>
            
            <a href="resources.php" class="action-btn" onclick="showConstructionAlert(event)">
                <i class="fas fa-book-open"></i>
                <div>
                    <strong>Ressources</strong>
                    <div style="font-size: 14px; color: #aaa;">Accéder aux ressources partagées</div>
                </div>
            </a>
        </div>
        
        <!-- Derniers decks utilisés -->
        <div class="recent-decks">
            <div class="section-title">
                <span><i class="fas fa-history"></i> Derniers decks utilisés</span>
                <a href="my-decks.php" style="color: var(--primary); text-decoration: none; font-size: 14px;">
                </a>
            </div>
            
            <div class="decks-grid">
                <?php foreach ($recent_decks as $deck): ?>
                    <a href="flashcards.php?deck_id=<?= $deck['id_deck'] ?>" style="text-decoration: none; color: inherit;">
                        <div class="deck-card">
                            <div class="deck-matiere" style="background: <?= $deck['couleur_hex'] ?>;">
                                <?= $deck['matiere_nom'] ?>
                            </div>
                            <div class="deck-title"><?= htmlspecialchars($deck['titre']) ?></div>
                            <div style="color: #aaa; font-size: 14px;">
                                <?= htmlspecialchars($deck['description'] ?? 'Pas de description') ?>
                            </div>
                            <div class="deck-meta">
                                <span><i class="fas fa-cards"></i> <?= $deck['nombre_cartes'] ?> cartes</span>
                                <span><i class="fas fa-repeat"></i> <?= $deck['nombre_revisions'] ?> révisions</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
                
                <?php if (empty($recent_decks)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #aaa;">
                        <i class="fas fa-layer-group" style="font-size: 50px; margin-bottom: 20px;"></i>
                        <p>Aucun deck récent. Créez votre premier deck !</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Toutes les matières -->
       <div class="recent-decks">
    <div class="section-title">
        <span><i class="fas fa-book"></i> Parcourir par matière</span>
    </div>
    
    <div class="matieres-grid">
        <?php foreach ($matieres as $matiere): 
            $couleur = $matiere['couleur_hex'];
            $filiere_color = $matiere['filiere'] == 'technology' ? 'var(--tech-blue)' : 
                           ($matiere['filiere'] == 'business' ? 'var(--business-green)' : '#8E44AD');
        ?>
            <a href="#" 
               class="action-btn"
               onclick="showConstructionAlert(event)"
               style="text-decoration: none; color: inherit;">
               
                <div class="matiere-card">
                    <div class="matiere-color" style="background: <?= $couleur ?>;"></div>
                    
                    <div class="matiere-header">
                        <span class="matiere-code"><?= $matiere['code'] ?></span>
                        <span class="matiere-filiere" style="color: <?= $filiere_color ?>;">
                            <?= $matiere['filiere'] ?>
                        </span>
                    </div>
                    
                    <h3 class="matiere-title"><?= htmlspecialchars($matiere['nom']) ?></h3>
                    
                    <p style="color: #aaa; font-size: 14px; margin-bottom: 15px;">
                        <?= htmlspecialchars($matiere['description']) ?>
                    </p>
                    
                    <div class="matiere-stats">
                        <span><i class="fas fa-layer-group"></i> <?= $matiere['nb_decks'] ?> decks</span>
                        <span><i class="fas fa-cards"></i> <?= $matiere['nb_cards'] ?> cartes</span>
                        
                        <?php if ($matiere['cards_to_review'] > 0): ?>
                            <span style="color: var(--primary);">
                                <i class="fas fa-clock"></i> <?= $matiere['cards_to_review'] ?> à réviser
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
            </a>
        <?php endforeach; ?>
    </div>
</div>

    </div>
    
    <script>
        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .matiere-card, .deck-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s, transform 0.5s';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
        
        // Raccourci clavier pour lancer une révision (touche R)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'r' || e.key === 'R') {
                window.location.href = 'flashcards.php?mode=review';
            }
        });
        
        function showConstructionAlert(event) {
            event.preventDefault();
            alert('🚧 Cette fonctionnalité est en construction et sera bientôt disponible !');
        }
    </script>
</body>
</html>
