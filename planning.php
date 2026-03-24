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

// Récupérer les cours de la semaine
try {
    $stmt = $pdo->prepare("
        SELECT 
            pc.id_cours,
            pc.matiere_nom,
            pc.nom_professeur,
            pc.salle,
            pc.date_seance,
            pc.heure_debut,
            pc.heure_fin,
            pc.type_cours,
            m.couleur_hex,
            m.code as matiere_code
        FROM planning_cours pc
        LEFT JOIN matieres m ON pc.matiere_id = m.id_matiere
        WHERE pc.promotion = ?
        AND pc.date_seance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY pc.date_seance, pc.heure_debut
    ");
    $stmt->execute([$user_promotion]);
    $cours_semaine = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cours_semaine = [];
}

// Grouper par jour
$cours_par_jour = [];
$jours_semaine = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

foreach ($cours_semaine as $cours) {
    $jour_numero = date('N', strtotime($cours['date_seance']));
    $nom_jour = $jours_semaine[$jour_numero - 1];
    $date_formatee = date('d/m', strtotime($cours['date_seance']));
    
    if (!isset($cours_par_jour[$nom_jour])) {
        $cours_par_jour[$nom_jour] = [
            'date' => $date_formatee,
            'cours' => []
        ];
    }
    
    $cours_par_jour[$nom_jour]['cours'][] = $cours;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning - OSBT Connect</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
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
        
        .planning-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }
        
        /* Header */
        .planning-header {
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
        
        .planning-title {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }
        
        .planning-title h1 {
            font-size: 2rem;
            background: var(--gradient-green);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
        
        .planning-subtitle {
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
        
        /* Planning Grid */
        .planning-grid {
            display: grid;
            gap: var(--spacing-xl);
        }
        
        .jour-card {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-xl);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-base);
        }
        
        .jour-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .jour-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--notion-gray-200);
        }
        
        .jour-nom {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--notion-gray-900);
        }
        
        .jour-date {
            font-size: 0.875rem;
            color: var(--notion-gray-500);
            background: var(--notion-gray-100);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
        }
        
        .cours-list {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }
        
        .cours-item {
            display: flex;
            gap: var(--spacing-md);
            padding: var(--spacing-md);
            background: var(--notion-gray-50);
            border-radius: var(--radius-lg);
            border-left: 4px solid;
            transition: all var(--transition-base);
        }
        
        .cours-item:hover {
            background: white;
            box-shadow: var(--shadow-sm);
            transform: translateX(4px);
        }
        
        .cours-time {
            min-width: 80px;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--notion-gray-700);
        }
        
        .cours-info {
            flex: 1;
        }
        
        .cours-matiere {
            font-weight: 600;
            color: var(--notion-gray-900);
            margin-bottom: var(--spacing-xs);
        }
        
        .cours-details {
            font-size: 0.875rem;
            color: var(--notion-gray-600);
            display: flex;
            gap: var(--spacing-md);
            flex-wrap: wrap;
        }
        
        .cours-detail {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }
        
        .type-badge {
            font-size: 0.75rem;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .type-cm {
            background: rgba(66, 133, 244, 0.1);
            color: var(--notion-blue);
        }
        
        .type-td {
            background: rgba(0, 200, 83, 0.1);
            color: var(--notion-green);
        }
        
        .type-tp {
            background: rgba(139, 92, 246, 0.1);
            color: var(--notion-purple);
        }
        
        /* Empty state */
        .empty-jour {
            text-align: center;
            padding: var(--spacing-2xl);
            color: var(--notion-gray-500);
        }
        
        .empty-jour i {
            font-size: 2rem;
            margin-bottom: var(--spacing-md);
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .planning-container {
                padding: var(--spacing-md);
            }
            
            .planning-header {
                flex-direction: column;
                gap: var(--spacing-md);
                text-align: center;
            }
            
            .cours-item {
                flex-direction: column;
                gap: var(--spacing-sm);
            }
            
            .cours-time {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="planning-container">
        <!-- Header -->
        <div class="planning-header">
            <div class="planning-title">
                <i class="fas fa-calendar-alt" style="font-size: 2rem; color: var(--primary);"></i>
                <div>
                    <h1>Planning</h1>
                    <div class="planning-subtitle">Emploi du temps de la semaine</div>
                </div>
            </div>
            <a href="student_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Retour au dashboard
            </a>
        </div>
        
        <!-- Planning Grid -->
        <div class="planning-grid">
            <?php if (empty($cours_par_jour)): ?>
                <div class="jour-card">
                    <div class="empty-jour">
                        <i class="fas fa-calendar-times"></i>
                        <h3>Aucun cours cette semaine</h3>
                        <p>Profitez de votre temps libre pour réviser !</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($jours_semaine as $jour): ?>
                    <?php if (isset($cours_par_jour[$jour])): ?>
                        <div class="jour-card">
                            <div class="jour-header">
                                <div class="jour-nom"><?= $jour ?></div>
                                <div class="jour-date"><?= $cours_par_jour[$jour]['date'] ?></div>
                            </div>
                            <div class="cours-list">
                                <?php foreach ($cours_par_jour[$jour]['cours'] as $cours): ?>
                                    <div class="cours-item" style="border-left-color: <?= $cours['couleur_hex'] ?>;">
                                        <div class="cours-time">
                                            <?= date('H:i', strtotime($cours['heure_debut'])) ?>
                                            - 
                                            <?= date('H:i', strtotime($cours['heure_fin'])) ?>
                                        </div>
                                        <div class="cours-info">
                                            <div class="cours-matiere"><?= htmlspecialchars($cours['matiere_nom']) ?></div>
                                            <div class="cours-details">
                                                <div class="cours-detail">
                                                    <i class="fas fa-user-tie"></i>
                                                    <?= htmlspecialchars($cours['nom_professeur']) ?>
                                                </div>
                                                <div class="cours-detail">
                                                    <i class="fas fa-door-open"></i>
                                                    <?= htmlspecialchars($cours['salle']) ?>
                                                </div>
                                                <div class="type-badge type-<?= strtolower($cours['type_cours']) ?>">
                                                    <?= $cours['type_cours'] ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
