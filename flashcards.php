<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Connexion à la base de données
require_once 'config/database.php';

$user_id = $_SESSION['user_id'];
$deck_id = $_GET['deck_id'] ?? 0;

// Récupérer les informations du deck
$stmt = $pdo->prepare("SELECT d.*, m.nom as matiere_nom, m.couleur_hex 
                       FROM decks d 
                       LEFT JOIN matieres m ON d.matiere_id = m.id_matiere 
                       WHERE d.id_deck = ? AND (d.createur_id = ? OR d.est_public = 1)");
$stmt->execute([$deck_id, $user_id]);
$deck = $stmt->fetch();

if (!$deck) {
    header('Location: learning-center.php');
    exit();
}

// Traitement de l'évaluation d'une flashcard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'evaluate') {
        $flashcard_id = (int)$_POST['flashcard_id'];
        $difficulty = $_POST['difficulty']; // 'again', 'hard', 'good', 'easy'
        
        // Récupérer la flashcard
        $stmt = $pdo->prepare("SELECT * FROM flashcards WHERE id_flashcard = ?");
        $stmt->execute([$flashcard_id]);
        $flashcard = $stmt->fetch();
        
        if ($flashcard) {
            // Algorithme SM-2 simplifié
            $interval = (int)$flashcard['intervalle_revision'];
            $ease_factor = $flashcard['ease_factor'] ?? 2.5;
            $repetitions = $flashcard['nombre_revisions'] ?? 0;
            
            switch ($difficulty) {
                case 'again': // Oublié
                    $interval = 1;
                    $repetitions = 0;
                    $ease_factor = max(1.3, $ease_factor - 0.2);
                    break;
                    
                case 'hard': // Difficile
                    $interval = max(1, round($interval * 1.2));
                    $ease_factor = max(1.3, $ease_factor - 0.15);
                    break;
                    
                case 'good': // Moyen
                    $interval = round($interval * $ease_factor);
                    // Pas de changement à l'ease factor
                    break;
                    
                case 'easy': // Facile
                    $interval = round($interval * $ease_factor * 1.5);
                    $ease_factor = min(2.5, $ease_factor + 0.1);
                    break;
            }
            
            $repetitions++;
            
            // Calculer la prochaine révision
            $next_review = date('Y-m-d', strtotime("+$interval days"));
            
            // Mettre à jour la flashcard
            $stmt = $pdo->prepare("UPDATE flashcards SET 
                                  intervalle_revision = ?,
                                  ease_factor = ?,
                                  nombre_revisions = ?,
                                  prochaine_revision = ?,
                                  date_derniere_revision = NOW()
                                  WHERE id_flashcard = ?");
            $stmt->execute([$interval, $ease_factor, $repetitions, $next_review, $flashcard_id]);
            
            // Enregistrer la révision dans l'historique
            $stmt = $pdo->prepare("INSERT INTO revisions_srs 
                                  (flashcard_id, utilisateur_id, difficulte, intervalle_jours, ease_factor_avant, ease_factor_apres) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $flashcard_id,
                $user_id,
                $difficulty,
                $interval,
                $flashcard['ease_factor'] ?? 2.5,
                $ease_factor
            ]);
            
            // Mettre à jour le compteur du deck
            $stmt = $pdo->prepare("UPDATE decks SET nombre_revisions = nombre_revisions + 1 WHERE id_deck = ?");
            $stmt->execute([$deck_id]);
            
            header("Location: flashcards.php?deck_id=$deck_id");
            exit();
        }
    }
}

// Récupérer les flashcards du deck à réviser aujourd'hui
$stmt = $pdo->prepare("SELECT * FROM flashcards 
                       WHERE deck_id = ? 
                       AND (prochaine_revision IS NULL OR prochaine_revision <= CURDATE())
                       ORDER BY 
                         CASE 
                           WHEN prochaine_revision IS NULL THEN 0
                           ELSE 1 
                         END,
                         prochaine_revision ASC
                       LIMIT 1");
$stmt->execute([$deck_id]);
$current_card = $stmt->fetch();

// Si pas de carte à réviser aujourd'hui, récupérer la prochaine
if (!$current_card) {
    $stmt = $pdo->prepare("SELECT * FROM flashcards 
                           WHERE deck_id = ? 
                           ORDER BY prochaine_revision ASC 
                           LIMIT 1");
    $stmt->execute([$deck_id]);
    $current_card = $stmt->fetch();
}

// Statistiques
$stmt = $pdo->prepare("SELECT 
                       COUNT(*) as total,
                       SUM(CASE WHEN prochaine_revision <= CURDATE() OR prochaine_revision IS NULL THEN 1 ELSE 0 END) as a_reviser,
                       SUM(CASE WHEN prochaine_revision > CURDATE() THEN 1 ELSE 0 END) as revues
                       FROM flashcards 
                       WHERE deck_id = ?");
$stmt->execute([$deck_id]);
$stats = $stmt->fetch();

// Récupérer toutes les flashcards du deck pour la liste
$stmt = $pdo->prepare("SELECT * FROM flashcards WHERE deck_id = ? ORDER BY difficulte, recto");
$stmt->execute([$deck_id]);
$all_cards = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flashcards - <?= htmlspecialchars($deck['titre']) ?> | OSBT Connect</title>
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
            --primary: <?= $deck['couleur_hex'] ?: '#00c853' ?>;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }
        
        /* Sidebar */
        .sidebar {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            height: fit-content;
            box-shadow: var(--shadow-sm);
        }
        
        .deck-info {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }
        
        .deck-icon {
            width: 80px;
            height: 80px;
            background: var(--primary);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--spacing-md);
            font-size: 2rem;
            color: white;
        }
        
        .deck-title {
            font-size: 1.375rem;
            font-weight: 600;
            margin-bottom: var(--spacing-xs);
            color: var(--notion-gray-900);
        }
        
        .deck-meta {
            color: var(--notion-gray-600);
            font-size: 0.875rem;
            margin-bottom: var(--spacing-lg);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-lg);
        }
        
        .stat-card {
            background: var(--notion-gray-50);
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-md);
            text-align: center;
            transition: all var(--transition-base);
        }
        
        .stat-card:hover {
            background: white;
            box-shadow: var(--shadow-md);
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: var(--spacing-xs);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--notion-gray-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .card-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .card-item {
            background: var(--notion-gray-50);
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-sm) var(--spacing-md);
            margin-bottom: var(--spacing-sm);
            cursor: pointer;
            transition: all var(--transition-base);
            border-left: 4px solid transparent;
        }
        
        .card-item:hover {
            background: white;
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }
        
        .card-item.active {
            border-left-color: var(--primary);
            background: white;
            box-shadow: var(--shadow-md);
        }
        
        .card-preview {
            font-size: 0.875rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            color: var(--notion-gray-700);
        }
        
        .card-difficulty {
            display: inline-block;
            font-size: 0.688rem;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            margin-top: var(--spacing-xs);
            font-weight: 500;
        }
        
        .difficulty-easy { background: #2ecc71; color: white; }
        .difficulty-medium { background: #f39c12; color: white; }
        .difficulty-hard { background: #e74c3c; color: white; }
        
        /* Main Content */
        .main-content {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-sm);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-xl);
        }
        
        .nav-links a {
            color: var(--notion-gray-600);
            text-decoration: none;
            margin-left: var(--spacing-lg);
            font-size: 0.95rem;
            transition: color var(--transition-base);
        }
        
        .nav-links a:hover {
            color: var(--primary);
        }
        
        .review-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 500px;
        }
        
        .review-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }
        
        .review-title {
            font-size: 1.75rem;
            margin-bottom: var(--spacing-sm);
            color: var(--notion-gray-900);
            font-weight: 700;
        }
        
        .review-subtitle {
            color: var(--notion-gray-600);
            font-size: 1rem;
        }
        
        /* Flashcard Styling */
        .flashcard {
            width: 100%;
            max-width: 600px;
            height: 400px;
            perspective: 1000px;
            margin-bottom: var(--spacing-xl);
        }
        
        .card-inner {
            position: relative;
            width: 100%;
            height: 100%;
            text-align: center;
            transition: transform 0.8s;
            transform-style: preserve-3d;
            cursor: pointer;
        }
        
        .card-inner.flipped {
            transform: rotateY(180deg);
        }
        
        .card-front, .card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            background: white;
            border: 2px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: var(--shadow-sm);
        }
        
        .card-back {
            transform: rotateY(180deg);
            background: rgba(0, 200, 83, 0.1);
        }
        
        .card-content {
            font-size: 24px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .card-tags {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .tag {
            background: rgba(255, 255, 255, 0.1);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        
        .hint {
            color: #aaa;
            font-size: 14px;
            margin-top: 20px;
            text-align: center;
        }
        
        /* Evaluation Buttons */
        .evaluation-buttons {
            display: none;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .evaluation-buttons.show {
            display: flex;
            animation: fadeIn 0.5s;
        }
        
        .eval-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 140px;
            justify-content: center;
        }
        
        .eval-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .eval-btn:active {
            transform: translateY(-1px);
        }
        
        .btn-again {
            background: #e74c3c;
            color: white;
        }
        
        .btn-hard {
            background: #e67e22;
            color: white;
        }
        
        .btn-good {
            background: #3498db;
            color: white;
        }
        
        .btn-easy {
            background: #2ecc71;
            color: white;
        }
        
        /* No Cards Message */
        .no-cards {
            text-align: center;
            padding: 60px 20px;
        }
        
        .no-cards-icon {
            font-size: 60px;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary);
            width: <?= $stats['total'] > 0 ? (($stats['total'] - $stats['a_reviser']) / $stats['total'] * 100) : 0 ?>%;
            transition: width 0.5s ease;
        }
        
        .progress-text {
            text-align: right;
            font-size: 14px;
            color: #aaa;
            margin-bottom: 10px;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                order: 2;
            }
            
            .main-content {
                order: 1;
            }
        }
        
        @media (max-width: 768px) {
            .flashcard {
                height: 300px;
            }
            
            .card-content {
                font-size: 20px;
            }
            
            .eval-btn {
                min-width: 120px;
                padding: 10px 20px;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s;
        }
        
        /* Flashcard Animation */
        .card-front, .card-back {
            transition: all 0.3s;
        }
        
        .card-front:hover, .card-back:hover {
            border-color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="deck-info">
                <div class="deck-icon">
                    <i class="fas fa-layer-group"></i>
                </div>
                <h2 class="deck-title"><?= htmlspecialchars($deck['titre']) ?></h2>
                <div class="deck-meta">
                    <?php if ($deck['matiere_nom']): ?>
                        <span><?= htmlspecialchars($deck['matiere_nom']) ?></span> •
                    <?php endif; ?>
                    <span><?= $stats['total'] ?> cartes</span>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['a_reviser'] ?? 0 ?></div>
                    <div class="stat-label">À réviser</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['revues'] ?? 0 ?></div>
                    <div class="stat-label">Revues</div>
                </div>
            </div>
            
            <div class="progress-text">
                Progression: <?= $stats['total'] > 0 ? round(($stats['total'] - $stats['a_reviser']) / $stats['total'] * 100) : 0 ?>%
            </div>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            
            <h3 style="margin-bottom: 15px; font-size: 16px;">Cartes du deck</h3>
            <div class="card-list">
                <?php foreach ($all_cards as $index => $card): ?>
                    <div class="card-item <?= $current_card && $card['id_flashcard'] == $current_card['id_flashcard'] ? 'active' : '' ?>" 
                         onclick="loadCard(<?= $card['id_flashcard'] ?>)">
                        <div class="card-preview">
                            <strong>#<?= $index + 1 ?></strong> - <?= htmlspecialchars(substr($card['recto'], 0, 50)) . (strlen($card['recto']) > 50 ? '...' : '') ?>
                        </div>
                        <div class="card-difficulty difficulty-<?= $card['difficulte'] ?>">
                            <?= $card['difficulte'] ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div>
                    <h1 style="font-size: 24px;">Learning Center</h1>
                    <p style="color: #aaa; font-size: 14px;">Révision des flashcards</p>
                </div>
                <div class="nav-links">
                    <a href="learning-center.php"><i class="fas fa-home"></i> Centre d'apprentissage</a>
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                </div>
            </div>
            
            <div class="review-container">
                <?php if (!$current_card): ?>
                    <div class="no-cards">
                        <div class="no-cards-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <h2 style="margin-bottom: 15px;">Félicitations !</h2>
                        <p style="color: #aaa; margin-bottom: 30px; max-width: 500px;">
                            Vous avez révisé toutes les cartes de ce deck pour aujourd'hui.
                            La prochaine révision est programmée pour le 
                            <?php if ($all_cards): 
                                $next_date = min(array_column($all_cards, 'prochaine_revision'));
                                echo date('d/m/Y', strtotime($next_date));
                            endif; ?>
                        </p>
                        <a href="learning-center.php" style="
                            display: inline-block;
                            background: var(--primary);
                            color: white;
                            padding: 12px 30px;
                            border-radius: 12px;
                            text-decoration: none;
                            font-weight: 600;
                        ">
                            <i class="fas fa-arrow-left"></i> Retour au Learning Center
                        </a>
                    </div>
                <?php else: ?>
                    <div class="review-header">
                        <h2 class="review-title">Carte à réviser</h2>
                        <p class="review-subtitle">
                            <?php if ($current_card['prochaine_revision']): ?>
                                Prochaine révision prévue: <?= date('d/m/Y', strtotime($current_card['prochaine_revision'])) ?>
                            <?php else: ?>
                                Nouvelle carte
                            <?php endif; ?>
                            • Révisions: <?= $current_card['nombre_revisions'] ?>
                        </p>
                    </div>
                    
                    <div class="flashcard" onclick="flipCard()">
                        <div class="card-inner" id="cardInner">
                            <div class="card-front">
                                <div class="card-content" id="cardFront">
                                    <?= nl2br(htmlspecialchars($current_card['recto'])) ?>
                                </div>
                                <div class="hint">
                                    <i class="fas fa-hand-point-up"></i> Cliquez pour retourner la carte
                                </div>
                                <?php if ($current_card['tags']): ?>
                                    <div class="card-tags">
                                        <?php 
                                        $tags = explode(',', $current_card['tags']);
                                        foreach ($tags as $tag):
                                        ?>
                                            <span class="tag"><?= trim($tag) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-back">
                                <div class="card-content" id="cardBack">
                                    <?= nl2br(htmlspecialchars($current_card['verso'])) ?>
                                </div>
                                <?php if ($current_card['explication']): ?>
                                    <div style="
                                        margin-top: 20px;
                                        padding: 15px;
                                        background: rgba(255,255,255,0.05);
                                        border-radius: 10px;
                                        font-size: 16px;
                                        color: #ddd;
                                        text-align: left;
                                        width: 100%;
                                    ">
                                        <strong>Explication:</strong> <?= nl2br(htmlspecialchars($current_card['explication'])) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="hint">
                                    <i class="fas fa-star"></i> Évaluez votre connaissance
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="evaluation-buttons" id="evaluationButtons">
                        <form method="post" action="" id="evaluationForm">
                            <input type="hidden" name="action" value="evaluate">
                            <input type="hidden" name="flashcard_id" value="<?= $current_card['id_flashcard'] ?>">
                            <input type="hidden" name="difficulty" id="difficultyInput">
                            
                            <button type="button" class="eval-btn btn-again" onclick="submitEvaluation('again')">
                                <i class="fas fa-times-circle"></i> Oublié
                            </button>
                            
                            <button type="button" class="eval-btn btn-hard" onclick="submitEvaluation('hard')">
                                <i class="fas fa-hourglass-half"></i> Difficile
                            </button>
                            
                            <button type="button" class="eval-btn btn-good" onclick="submitEvaluation('good')">
                                <i class="fas fa-check-circle"></i> Moyen
                            </button>
                            
                            <button type="button" class="eval-btn btn-easy" onclick="submitEvaluation('easy')">
                                <i class="fas fa-bolt"></i> Facile
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        let isFlipped = false;
        
        function flipCard() {
            const cardInner = document.getElementById('cardInner');
            const evalButtons = document.getElementById('evaluationButtons');
            
            if (!isFlipped) {
                cardInner.classList.add('flipped');
                setTimeout(() => {
                    evalButtons.classList.add('show');
                }, 400);
                isFlipped = true;
            }
        }
        
        function submitEvaluation(difficulty) {
            document.getElementById('difficultyInput').value = difficulty;
            document.getElementById('evaluationForm').submit();
        }
        
        function loadCard(cardId) {
            // Recharger la page avec la nouvelle carte
            window.location.href = `flashcards.php?deck_id=<?= $deck_id ?>&card_id=${cardId}`;
        }
        
        // Animation de la carte au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const cardInner = document.getElementById('cardInner');
            if (cardInner) {
                cardInner.style.opacity = '0';
                cardInner.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    cardInner.style.transition = 'opacity 0.5s, transform 0.5s';
                    cardInner.style.opacity = '1';
                    cardInner.style.transform = 'translateY(0)';
                }, 100);
            }
        });
        
        // Raccourcis clavier
        document.addEventListener('keydown', function(e) {
            if (!isFlipped) {
                if (e.key === ' ' || e.key === 'Enter') {
                    flipCard();
                    e.preventDefault();
                }
            } else {
                switch(e.key) {
                    case '1':
                        submitEvaluation('again');
                        break;
                    case '2':
                        submitEvaluation('hard');
                        break;
                    case '3':
                        submitEvaluation('good');
                        break;
                    case '4':
                        submitEvaluation('easy');
                        break;
                }
            }
        });
    </script>
</body>
</html>