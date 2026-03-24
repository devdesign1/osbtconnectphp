<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Rediriger les admins vers leur dashboard
if ($_SESSION['user_role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}
// Rediriger les professeurs vers leur dashboard
if ($_SESSION['user_role'] === 'professeur') {
    header('Location: professor_dashboard.php');
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

// === RÉCUPÉRATION DES DONNÉES UTILISATEUR ===
$user_id = $_SESSION['user_id'];
$user_promotion = $_SESSION['user_promotion'] ?? 1;
$user_filiere = $_SESSION['user_filiere'] ?? 'technology';

// Initialisation des tableaux
$stats = [];
$planning = [];
$annonces = [];
$sessions_mentorat = [];
$flashcards_a_reviser = [];
$checklist_items = []; // NOUVEAU: Checklist inspirée de Vygo
$circular_progress = []; // NOUVEAU: Progression circulaire

try {
    // 1. Statistiques
    // Cours aujourd'hui
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM planning_cours 
        WHERE DATE(date_seance) = CURDATE() 
        AND promotion = ?
    ");
    $stmt->execute([$user_promotion]);
    $cours_aujourdhui = $stmt->fetch()['count'] ?? 0;
    
    // Notifications non lues
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE utilisateur_id = ? AND est_lue = 0
    ");
    $stmt->execute([$user_id]);
    $notifications_count = $stmt->fetch()['count'] ?? 0;
    
    // Mes decks
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM decks WHERE createur_id = ?");
    $stmt->execute([$user_id]);
    $mes_decks = $stmt->fetch()['count'] ?? 0;
    
    // Sessions mentorat actives
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM sessions_mentorat 
        WHERE etudiant_id = ? 
        AND statut IN ('demande_envoyee', 'session_confirmee')
    ");
    $stmt->execute([$user_id]);
    $sessions_actives = $stmt->fetch()['count'] ?? 0;
    
    // Compilation des stats
    $stats = [
        'cours_aujourdhui' => $cours_aujourdhui,
        'notifications_count' => $notifications_count,
        'mes_decks' => $mes_decks,
        'sessions_actives' => $sessions_actives
    ];
    
    // 2. Planning du jour - Version améliorée MyStudyLife
    $stmt = $pdo->prepare("
        SELECT 
            pc.*, 
            m.nom as matiere_nom, 
            m.filiere,
            m.couleur_hex,
            u.nom as prof_nom,
            u.prenom as prof_prenom
        FROM planning_cours pc
        JOIN matieres m ON pc.matiere_id = m.id_matiere
        LEFT JOIN utilisateurs u ON pc.professeur_id = u.id_utilisateur
        WHERE DATE(pc.date_seance) = CURDATE() 
        AND pc.promotion = ?
        ORDER BY pc.heure_debut ASC
    ");
    $stmt->execute([$user_promotion]);
    $planning = $stmt->fetchAll();
    
    // 3. Annonces récentes (limit 5)
    $stmt = $pdo->prepare("
        SELECT a.*, CONCAT(u.prenom, ' ', u.nom) as auteur_nom
        FROM annonces a
        JOIN utilisateurs u ON a.auteur_id = u.id_utilisateur
        WHERE a.est_active = 1
        AND (a.cible = 'tous' OR a.cible LIKE ? OR a.cible = ?)
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute(["%$user_promotion%", $user_filiere]);
    $annonces = $stmt->fetchAll();
    
    // 4. Sessions de mentorat à venir
    $stmt = $pdo->prepare("
        SELECT sm.*, m.nom as matiere_nom, 
               CONCAT(u.prenom, ' ', u.nom) as mentor_nom
        FROM sessions_mentorat sm
        JOIN matieres m ON sm.matiere_id = m.id_matiere
        JOIN utilisateurs u ON sm.mentor_id = u.id_utilisateur
        WHERE sm.etudiant_id = ?
        AND sm.date_session >= NOW()
        AND sm.statut IN ('session_confirmee', 'demande_acceptee')
        ORDER BY sm.date_session ASC
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $sessions_mentorat = $stmt->fetchAll();
    
    // 5. Flashcards à réviser
    $stmt = $pdo->prepare("
        SELECT f.*, d.titre as deck_titre, m.nom as matiere_nom
        FROM flashcards f
        JOIN decks d ON f.deck_id = d.id_deck
        JOIN matieres m ON d.matiere_id = m.id_matiere
        WHERE d.createur_id = ?
        AND (f.prochaine_revision <= CURDATE() OR f.prochaine_revision IS NULL)
        ORDER BY f.taux_reussite ASC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $flashcards_a_reviser = $stmt->fetchAll();
    
    // 6. NOUVEAU: Checklist du jour inspirée de Vygo
    $checklist_items = [
        'cours' => [
            'title' => 'Cours à suivre',
            'items' => [],
            'completed' => 0,
            'total' => 0,
            'icon' => '📚'
        ],
        'flashcards' => [
            'title' => 'Flashcards à réviser',
            'items' => [],
            'completed' => 0,
            'total' => count($flashcards_a_reviser),
            'icon' => '🧠'
        ],
        'mentorat' => [
            'title' => 'Sessions mentorat',
            'items' => [],
            'completed' => 0,
            'total' => 0,
            'icon' => '🤝'
        ],
        'travaux' => [
            'title' => 'Travaux à rendre',
            'items' => [],
            'completed' => 0,
            'total' => 0,
            'icon' => '📝'
        ]
    ];
    
    // Remplir la checklist avec les cours du jour
    foreach ($planning as $cours) {
        $checklist_items['cours']['items'][] = [
            'title' => $cours['matiere_nom'],
            'time' => date('H:i', strtotime($cours['heure_debut'])),
            'completed' => false,
            'type' => 'cours'
        ];
        $checklist_items['cours']['total']++;
    }
    
    // Remplir la checklist avec les flashcards
    foreach ($flashcards_a_reviser as $card) {
        $checklist_items['flashcards']['items'][] = [
            'title' => $card['deck_titre'],
            'details' => substr($card['recto'], 0, 30) . '...',
            'completed' => false,
            'type' => 'flashcard'
        ];
    }
    
    // Remplir la checklist avec les sessions mentorat
    foreach ($sessions_mentorat as $session) {
        $checklist_items['mentorat']['items'][] = [
            'title' => $session['matiere_nom'],
            'time' => date('H:i', strtotime($session['date_session'])),
            'completed' => false,
            'type' => 'mentorat'
        ];
        $checklist_items['mentorat']['total']++;
    }
    
    // 7. NOUVEAU: Progression circulaire par matière (inspiré Dribbble)
    $stmt = $pdo->prepare("
        SELECT 
            m.nom as matiere_nom,
            m.filiere,
            m.couleur_hex,
            COUNT(DISTINCT pc.id_planning) as total_cours,
            COUNT(DISTINCT CASE WHEN pc.date_seance < NOW() THEN pc.id_planning END) as cours_realises,
            ROUND(
                COUNT(DISTINCT CASE WHEN pc.date_seance < NOW() THEN pc.id_planning END) * 100.0 / 
                NULLIF(COUNT(DISTINCT pc.id_planning), 0), 1
            ) as progression,
            COUNT(DISTINCT d.id_deck) as total_decks,
            AVG(f.taux_reussite) as moyenne_flashcards
        FROM matieres m
        LEFT JOIN planning_cours pc ON m.id_matiere = pc.matiere_id AND pc.promotion = ?
        LEFT JOIN decks d ON d.matiere_id = m.id_matiere AND d.createur_id = ?
        LEFT JOIN flashcards f ON f.deck_id = d.id_deck
        WHERE (m.filiere = ? OR m.filiere = 'commun')
        AND m.est_active = 1
        GROUP BY m.id_matiere, m.nom, m.filiere, m.couleur_hex
        ORDER BY progression DESC
        LIMIT 6
    ");
    $stmt->execute([$user_promotion, $user_id, $user_filiere]);
    $circular_progress = $stmt->fetchAll();
    
    // 8. NOUVEAU: Récupérer les travaux à rendre cette semaine
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            m.nom as matiere_nom,
            m.couleur_hex
        FROM travaux t
        JOIN matieres m ON t.matiere_id = m.id_matiere
        WHERE t.date_limite BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        AND t.promotion = ?
        ORDER BY t.date_limite ASC
        LIMIT 3
    ");
    $stmt->execute([$user_promotion]);
    $travaux_a_rendre = $stmt->fetchAll();
    
    // Ajouter les travaux à la checklist
    foreach ($travaux_a_rendre as $travail) {
        $checklist_items['travaux']['items'][] = [
            'title' => $travail['titre'],
            'details' => $travail['matiere_nom'],
            'due_date' => date('d/m', strtotime($travail['date_limite'])),
            'completed' => false,
            'type' => 'travail'
        ];
        $checklist_items['travaux']['total']++;
    }
    
} catch (PDOException $e) {
    // En cas d'erreur, les tableaux restent vides
    error_log("Erreur dashboard: " . $e->getMessage());
}

// Fonction pour générer une couleur de matière par défaut
function getDefaultColor($index) {
    $colors = [
        '#00C853', '#2196F3', '#FF9800', '#9C27B0', 
        '#F44336', '#00BCD4', '#8BC34A', '#FF5722'
    ];
    return $colors[$index % count($colors)];
}
?>

<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Étudiant - OSBT Connect</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ===== VARIABLES INSPIRÉES DE NOTION ===== */
        :root {
            /* Palette de couleurs douces */
            --notion-blue: #4285f4;
            --notion-teal: #00bfa5;
            --notion-green: #00c853;
            --notion-yellow: #ffd600;
            --notion-red: #ff5252;
            --notion-purple: #7b1fa2;
            --notion-pink: #e91e63;
            --notion-gray: #5f6368;
            
            /* Gradients doux (inspiré Notion) */
            --gradient-blue: linear-gradient(135deg, #4285f4 0%, #64b5f6 100%);
            --gradient-teal: linear-gradient(135deg, #00bfa5 0%, #4db6ac 100%);
            --gradient-green: linear-gradient(135deg, #00c853 0%, #4caf50 100%);
            --gradient-yellow: linear-gradient(135deg, #ffd600 0%, #ffeb3b 100%);
            --gradient-red: linear-gradient(135deg, #ff5252 0%, #ff8a80 100%);
            --gradient-purple: linear-gradient(135deg, #7b1fa2 0%, #9c27b0 100%);
            
            /* Niveaux de gris Notion */
            --notion-gray-50: #fafafa;
            --notion-gray-100: #f5f5f5;
            --notion-gray-200: #eeeeee;
            --notion-gray-300: #e0e0e0;
            --notion-gray-400: #bdbdbd;
            --notion-gray-500: #9e9e9e;
            --notion-gray-600: #757575;
            --notion-gray-700: #616161;
            --notion-gray-800: #424242;
            --notion-gray-900: #212121;
            
            /* Design System inspiré Vygo/Dribbble */
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-2xl: 24px;
            --radius-full: 9999px;
            
            --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07), 0 2px 4px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.08), 0 4px 6px rgba(0, 0, 0, 0.06);
            --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1), 0 10px 10px rgba(0, 0, 0, 0.04);
            
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* ===== RESET & BASE ===== */
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
        
        /* ===== LAYOUT INSPIRÉ VYGO (SIDEBAR FINE) ===== */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar fine Vygo */
        .vygo-sidebar {
            width: 72px;
            background: white;
            border-right: 1px solid var(--notion-gray-200);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.5rem 0;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 100;
            transition: width var(--transition-base);
        }
        
        .vygo-sidebar:hover {
            width: 240px;
        }
        
        .vygo-sidebar:hover .nav-text {
            opacity: 1;
            transform: translateX(0);
        }
        
        /* Logo OSBT Connect - Version compacte */
        .sidebar-logo {
            margin-bottom: 2.5rem;
            padding: 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient-green);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .logo-text {
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--notion-gray-900);
            white-space: nowrap;
            overflow: hidden;
            opacity: 0;
            transform: translateX(-10px);
            transition: all var(--transition-base);
        }
        
        .vygo-sidebar:hover .logo-text {
            opacity: 1;
            transform: translateX(0);
        }
        
        /* Navigation Vygo */
        .vygo-nav {
            flex: 1;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 0 1rem;
        }
        
        .nav-item {
            position: relative;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: var(--radius-md);
            color: var(--notion-gray-600);
            text-decoration: none;
            transition: all var(--transition-base);
            white-space: nowrap;
        }
        
        .nav-link:hover {
            background: var(--notion-gray-100);
            color: var(--notion-gray-900);
        }
        
        .nav-link.active {
            background: var(--gradient-green);
            color: white;
            box-shadow: var(--shadow-md);
        }
        
        .nav-link.active::after {
            content: '';
            position: absolute;
            right: -1rem;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 24px;
            background: var(--notion-green);
            border-radius: var(--radius-full);
        }
        
        .nav-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
            flex-shrink: 0;
        }
        
        .nav-text {
            font-size: 0.875rem;
            font-weight: 500;
            opacity: 0;
            transform: translateX(-10px);
            transition: all var(--transition-base);
        }
        
        /* Badge de notification */
        .nav-badge {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: var(--notion-red);
            color: white;
            font-size: 0.7rem;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 0.25rem;
        }
        
        /* Avatar utilisateur en bas */
        .user-avatar-container {
            margin-top: auto;
            padding: 1rem;
            position: relative;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--gradient-green);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: transform var(--transition-base);
        }
        
        .user-avatar:hover {
            transform: scale(1.05);
        }
        
        /* ===== CONTENU PRINCIPAL ===== */
        .main-content {
            flex: 1;
            margin-left: 72px;
            padding: 2rem;
            min-height: 100vh;
            background: var(--notion-gray-50);
        }
        
        /* ===== HEADER DASHBOARD ===== */
        .dashboard-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--notion-gray-900);
            margin-bottom: 0.25rem;
        }
        
        .header-subtitle {
            color: var(--notion-gray-600);
            font-size: 0.95rem;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        /* Date widget MyStudyLife style */
        .date-widget {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1rem 1.5rem;
            border: 1px solid var(--notion-gray-200);
            box-shadow: var(--shadow-sm);
        }
        
        .current-date {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--notion-gray-900);
            margin-bottom: 0.25rem;
        }
        
        .current-day {
            font-size: 0.85rem;
            color: var(--notion-gray-600);
        }
        
        /* ===== SECTION CHECKLIST VYGO ===== */
        .checklist-section {
            margin-bottom: 2.5rem;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .section-title h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--notion-gray-900);
        }
        
        .section-icon {
            width: 36px;
            height: 36px;
            background: var(--gradient-green);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .checklist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .checklist-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            border: 1px solid var(--notion-gray-200);
            box-shadow: var(--shadow-md);
            transition: var(--transition-base);
        }
        
        .checklist-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .checklist-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }
        
        .checklist-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .checklist-icon {
            font-size: 1.5rem;
        }
        
        .checklist-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--notion-gray-900);
        }
        
        .checklist-progress {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .progress-text {
            font-size: 0.875rem;
            color: var(--notion-gray-600);
            font-weight: 500;
        }
        
        .progress-bar-container {
            width: 100px;
            height: 6px;
            background: var(--notion-gray-200);
            border-radius: var(--radius-full);
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background: var(--gradient-green);
            border-radius: var(--radius-full);
            transition: width var(--transition-slow);
        }
        
        .checklist-items {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .checklist-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem;
            border-radius: var(--radius-md);
            background: var(--notion-gray-50);
            border: 1px solid var(--notion-gray-200);
            cursor: pointer;
            transition: all var(--transition-base);
        }
        
        .checklist-item:hover {
            background: white;
            border-color: var(--notion-gray-300);
            transform: translateX(4px);
        }
        
        .checklist-item.completed {
            background: rgba(0, 200, 83, 0.08);
            border-color: rgba(0, 200, 83, 0.2);
        }
        
        .checklist-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid var(--notion-gray-400);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all var(--transition-base);
        }
        
        .checklist-item.completed .checklist-checkbox {
            background: var(--notion-green);
            border-color: var(--notion-green);
        }
        
        .checklist-item.completed .checklist-checkbox i {
            color: white;
            font-size: 0.75rem;
        }
        
        .checklist-item-content {
            flex: 1;
            min-width: 0;
        }
        
        .checklist-item-title {
            font-weight: 500;
            color: var(--notion-gray-900);
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
        }
        
        .checklist-item.completed .checklist-item-title {
            text-decoration: line-through;
            color: var(--notion-gray-500);
        }
        
        .checklist-item-details {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.8rem;
            color: var(--notion-gray-600);
        }
        
        .checklist-time {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .checklist-due-date {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            color: var(--notion-red);
            font-weight: 500;
        }
        
        /* ===== SECTION CALENDRIER MYSTUDYLIFE ===== */
        .calendar-section {
            margin-bottom: 2.5rem;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .calendar-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .calendar-btn {
            padding: 0.5rem 1rem;
            background: white;
            border: 1px solid var(--notion-gray-300);
            border-radius: var(--radius-md);
            color: var(--notion-gray-700);
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-base);
        }
        
        .calendar-btn:hover {
            background: var(--notion-gray-100);
        }
        
        .calendar-btn.active {
            background: var(--gradient-green);
            color: white;
            border-color: var(--notion-green);
        }
        
        .calendar-grid {
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--notion-gray-200);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        
        .calendar-time-slots {
            display: flex;
            flex-direction: column;
        }
        
        .calendar-time-slot {
            display: flex;
            min-height: 80px;
            border-bottom: 1px solid var(--notion-gray-200);
        }
        
        .calendar-time-slot:last-child {
            border-bottom: none;
        }
        
        .time-label {
            width: 80px;
            padding: 1rem;
            border-right: 1px solid var(--notion-gray-200);
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            font-size: 0.9rem;
            color: var(--notion-gray-600);
            font-weight: 500;
            background: var(--notion-gray-50);
        }
        
        .time-content {
            flex: 1;
            padding: 1rem;
            position: relative;
        }
        
        .calendar-event {
            position: absolute;
            left: 0;
            right: 0;
            padding: 0.75rem;
            border-radius: var(--radius-md);
            margin: 0 0.5rem;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid;
            cursor: pointer;
            transition: all var(--transition-base);
        }
        
        .calendar-event:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .event-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            color: white;
        }
        
        .event-details {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.9);
        }
        
        /* ===== SECTION PROGRESSION CIRCCULAIRE DRIBBBLE ===== */
        .progress-section {
            margin-bottom: 2.5rem;
        }
        
        .progress-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
        }
        
        .progress-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            border: 1px solid var(--notion-gray-200);
            box-shadow: var(--shadow-md);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: var(--transition-base);
        }
        
        .progress-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Cercle de progression */
        .circular-progress {
            position: relative;
            width: 120px;
            height: 120px;
            margin-bottom: 1rem;
        }
        
        .circular-background {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: var(--notion-gray-200);
        }
        
        .circular-fill {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            clip-path: polygon(50% 50%, 50% 0, 100% 0, 100% 100%, 0 100%, 0 0, 50% 0);
            transform: rotate(calc(var(--progress) * 3.6deg));
            transition: transform 1s ease;
        }
        
        .circular-inner {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--notion-gray-900);
        }
        
        .progress-info {
            margin-top: 0.75rem;
        }
        
        .progress-matiere {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--notion-gray-900);
            margin-bottom: 0.25rem;
        }
        
        .progress-stats {
            font-size: 0.8rem;
            color: var(--notion-gray-600);
        }
        
        /* ===== SECTION ANNONCES & RESSOURCES NOTION ===== */
        .resources-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .resource-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            border: 1px solid var(--notion-gray-200);
            box-shadow: var(--shadow-md);
            transition: var(--transition-base);
        }
        
        .resource-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .resource-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }
        
        .resource-badge {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-urgent {
            background: rgba(255, 82, 82, 0.1);
            color: var(--notion-red);
        }
        
        .badge-info {
            background: rgba(66, 133, 244, 0.1);
            color: var(--notion-blue);
        }
        
        .resource-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            border-radius: var(--radius-md);
            background: var(--notion-gray-50);
            margin-bottom: 0.75rem;
            border: 1px solid var(--notion-gray-200);
            transition: all var(--transition-base);
        }
        
        .resource-item:hover {
            background: white;
            border-color: var(--notion-gray-300);
        }
        
        .resource-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: white;
            font-size: 1.125rem;
        }
        
        .resource-content {
            flex: 1;
            min-width: 0;
        }
        
        .resource-title {
            font-weight: 600;
            color: var(--notion-gray-900);
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
        }
        
        .resource-details {
            font-size: 0.85rem;
            color: var(--notion-gray-600);
            margin-bottom: 0.5rem;
        }
        
        .resource-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--notion-gray-500);
        }
        
        /* ===== RESSOURCES PAR MATIÈRE NOTION ===== */
        .matieres-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .matiere-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            border-top: 4px solid;
            box-shadow: var(--shadow-md);
            transition: var(--transition-base);
        }
        
        .matiere-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .matiere-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .matiere-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .matiere-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--notion-gray-900);
        }
        
        .matiere-ressources {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .ressource-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: var(--radius-md);
            background: var(--notion-gray-50);
            color: var(--notion-gray-700);
            text-decoration: none;
            transition: all var(--transition-base);
        }
        
        .ressource-link:hover {
            background: var(--notion-gray-100);
            transform: translateX(4px);
        }
        
        .ressource-type {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--notion-gray-200);
            color: var(--notion-gray-600);
        }
        
        .ressource-name {
            flex: 1;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .vygo-sidebar {
                width: 60px;
            }
            
            .main-content {
                margin-left: 60px;
                padding: 1.5rem;
            }
            
            .checklist-grid,
            .progress-grid,
            .resources-section,
            .matieres-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-right {
                width: 100%;
                justify-content: space-between;
            }
            
            .calendar-time-slot {
                flex-direction: column;
                min-height: auto;
            }
            
            .time-label {
                width: 100%;
                justify-content: flex-start;
                border-right: none;
                border-bottom: 1px solid var(--notion-gray-200);
            }
        }
        
        /* ===== ANIMATIONS ===== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        
        /* ===== UTILITIES ===== */
        .text-success { color: var(--notion-green); }
        .text-info { color: var(--notion-blue); }
        .text-warning { color: var(--notion-yellow); }
        .text-danger { color: var(--notion-red); }
        
        .mb-1 { margin-bottom: 0.5rem; }
        .mb-2 { margin-bottom: 1rem; }
        .mb-3 { margin-bottom: 1.5rem; }
        .mb-4 { margin-bottom: 2rem; }
        
        .mt-1 { margin-top: 0.5rem; }
        .mt-2 { margin-top: 1rem; }
        .mt-3 { margin-top: 1.5rem; }
        .mt-4 { margin-top: 2rem; }
        
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-2 { gap: 0.5rem; }
        .gap-3 { gap: 0.75rem; }
        .gap-4 { gap: 1rem; }
        
        .w-full { width: 100%; }
        
        /* ===== SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--notion-gray-100);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--notion-gray-300);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--notion-gray-400);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar fine Vygo -->
        <aside class="vygo-sidebar">
            <!-- Logo -->
            <div class="sidebar-logo">
                <div class="logo-icon">OSBT</div>
                <span class="logo-text">Connect</span>
            </div>
            
            <!-- Navigation principale -->
            <nav class="vygo-nav">
                <div class="nav-item">
                    <a href="dashboard.php" class="nav-link active">
                        <span class="nav-icon"><i class="fas fa-home"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="planning.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-calendar-alt"></i></span>
                        <span class="nav-text">Planning</span>
                        <?php if($stats['cours_aujourdhui'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['cours_aujourdhui']; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="checklist.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-check-square"></i></span>
                        <span class="nav-text">Checklist</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="flashcards.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-brain"></i></span>
                        <span class="nav-text">Flashcards</span>
                        <?php if(count($flashcards_a_reviser) > 0): ?>
                        <span class="nav-badge"><?php echo count($flashcards_a_reviser); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="mentorat.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-users"></i></span>
                        <span class="nav-text">Mentorat</span>
                        <?php if($stats['sessions_actives'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['sessions_actives']; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="matieres.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-book"></i></span>
                        <span class="nav-text">Matières</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="annonces.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-bullhorn"></i></span>
                        <span class="nav-text">Annonces</span>
                        <?php if($stats['notifications_count'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['notifications_count']; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="profil.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-user"></i></span>
                        <span class="nav-text">Profil</span>
                    </a>
                </div>
            </nav>
            
            <!-- Avatar utilisateur -->
            <div class="user-avatar-container">
                <div class="user-avatar" onclick="toggleUserMenu()">
                    <?php echo strtoupper(substr($_SESSION['user_prenom'], 0, 1)); ?>
                </div>
            </div>
        </aside>
        
        <!-- Contenu principal -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-left">
                    <h1>Bonjour, <?php echo htmlspecialchars($_SESSION['user_prenom']); ?> 👋</h1>
                    <p class="header-subtitle">Voici votre journée en un coup d'œil</p>
                </div>
                
                <div class="header-right">
                    <div class="date-widget">
                        <div class="current-date"><?php echo date('d F Y'); ?></div>
                        <div class="current-day"><?php echo date('l'); ?></div>
                    </div>
                </div>
            </header>
            
            <!-- Section Checklist Vygo -->
            <section class="checklist-section fade-in-up">
                <div class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-check-square"></i>
                    </div>
                    <h2>Checklist du jour</h2>
                </div>
                
                <div class="checklist-grid">
                    <?php foreach($checklist_items as $category => $data): ?>
                    <?php if($data['total'] > 0 || !empty($data['items'])): ?>
                    <div class="checklist-card">
                        <div class="checklist-header">
                            <div class="checklist-title">
                                <span class="checklist-icon"><?php echo $data['icon']; ?></span>
                                <span class="checklist-name"><?php echo $data['title']; ?></span>
                            </div>
                            
                            <div class="checklist-progress">
                                <span class="progress-text">
                                    <?php echo $data['completed']; ?>/<?php echo $data['total']; ?>
                                </span>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: <?php echo $data['total'] > 0 ? ($data['completed'] / $data['total'] * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="checklist-items">
                            <?php foreach(array_slice($data['items'], 0, 3) as $index => $item): ?>
                            <div class="checklist-item" data-type="<?php echo $item['type']; ?>" data-index="<?php echo $index; ?>">
                                <div class="checklist-checkbox">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="checklist-item-content">
                                    <div class="checklist-item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                    <div class="checklist-item-details">
                                        <?php if(isset($item['time'])): ?>
                                        <span class="checklist-time">
                                            <i class="far fa-clock"></i> <?php echo $item['time']; ?>
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if(isset($item['details'])): ?>
                                        <span class="checklist-details"><?php echo htmlspecialchars($item['details']); ?></span>
                                        <?php endif; ?>
                                        
                                        <?php if(isset($item['due_date'])): ?>
                                        <span class="checklist-due-date">
                                            <i class="fas fa-calendar-day"></i> <?php echo $item['due_date']; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if(count($data['items']) > 3): ?>
                            <div class="checklist-more">
                                <button class="calendar-btn w-full" onclick="showMoreItems('<?php echo $category; ?>')">
                                    +<?php echo count($data['items']) - 3; ?> plus
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
            
            <!-- Section Calendrier MyStudyLife -->
            <section class="calendar-section fade-in-up" style="animation-delay: 100ms">
                <div class="calendar-header">
                    <div class="section-title">
                        <div class="section-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <h2>Emploi du temps du jour</h2>
                    </div>
                    
                    <div class="calendar-actions">
                        <button class="calendar-btn active" onclick="changeView('day')">Jour</button>
                        <button class="calendar-btn" onclick="changeView('week')">Semaine</button>
                        <button class="calendar-btn" onclick="changeView('month')">Mois</button>
                    </div>
                </div>
                
                <div class="calendar-grid">
                    <div class="calendar-time-slots">
                        <?php
                        // Créer des créneaux horaires de 8h à 20h
                        $time_slots = [];
                        for ($hour = 8; $hour <= 20; $hour++) {
                            $time_slots[] = sprintf('%02d:00', $hour);
                        }
                        
                        foreach ($time_slots as $time_slot):
                        ?>
                        <div class="calendar-time-slot">
                            <div class="time-label"><?php echo $time_slot; ?></div>
                            <div class="time-content">
                                <?php
                                // Trouver les cours à cette heure
                                $current_time = strtotime($time_slot);
                                foreach ($planning as $cours):
                                    $cours_start = strtotime($cours['heure_debut']);
                                    $cours_end = strtotime($cours['heure_fin']);
                                    
                                    if ($current_time >= $cours_start && $current_time < $cours_end):
                                        $duration = ($cours_end - $cours_start) / 3600; // en heures
                                        $top_position = (($cours_start - strtotime('08:00')) / 3600) * 80;
                                        $height = $duration * 80;
                                        
                                        $color = !empty($cours['couleur_hex']) ? $cours['couleur_hex'] : getDefaultColor(array_search($cours['matiere_nom'], array_column($planning, 'matiere_nom')));
                                ?>
                                <div class="calendar-event" 
                                     style="top: <?php echo $top_position; ?>px; 
                                            height: <?php echo $height; ?>px; 
                                            background: <?php echo $color; ?>;
                                            border-left-color: <?php echo darkenColor($color, 30); ?>;"
                                     onclick="openCourseDetails(<?php echo $cours['id_planning']; ?>)">
                                    <div class="event-title"><?php echo htmlspecialchars($cours['matiere_nom']); ?></div>
                                    <div class="event-details">
                                        <span><i class="fas fa-clock"></i> <?php echo date('H:i', $cours_start); ?>-<?php echo date('H:i', $cours_end); ?></span>
                                        <?php if($cours['salle']): ?>
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo $cours['salle']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            
            <!-- Section Progression Circulaire Dribbble -->
            <section class="progress-section fade-in-up" style="animation-delay: 200ms">
                <div class="section-title">
                    <div class="section-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h2>Progression par matière</h2>
                </div>
                
                <div class="progress-grid">
                    <?php foreach($circular_progress as $index => $matiere): 
                        $progress = $matiere['progression'] ?? 0;
                        $color = !empty($matiere['couleur_hex']) ? $matiere['couleur_hex'] : getDefaultColor($index);
                    ?>
                    <div class="progress-card">
                        <div class="circular-progress">
                            <div class="circular-background"></div>
                            <div class="circular-fill" style="background: <?php echo $color; ?>; --progress: <?php echo $progress; ?>"></div>
                            <div class="circular-inner"><?php echo round($progress); ?>%</div>
                        </div>
                        
                        <div class="progress-info">
                            <div class="progress-matiere"><?php echo htmlspecialchars($matiere['matiere_nom']); ?></div>
                            <div class="progress-stats">
                                <?php echo $matiere['cours_realises']; ?>/<?php echo $matiere['total_cours']; ?> cours
                                <?php if($matiere['moyenne_flashcards']): ?>
                                • <?php echo round($matiere['moyenne_flashcards']); ?>% flashcards
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            
            <!-- Section Annonces & Ressources Notion -->
            <section class="fade-in-up" style="animation-delay: 300ms">
                <div class="section-title mb-3">
                    <div class="section-icon">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <h2>Annonces & Ressources</h2>
                </div>
                
                <div class="resources-section">
                    <!-- Annonces -->
                    <div class="resource-card">
                        <div class="resource-header">
                            <h3 style="font-size: 1.1rem; font-weight: 600;">Dernières annonces</h3>
                            <a href="annonces.php" style="font-size: 0.875rem; color: var(--notion-blue); text-decoration: none; font-weight: 500;">
                                Voir tout →
                            </a>
                        </div>
                        
                        <?php foreach(array_slice($annonces, 0, 3) as $annonce): ?>
                        <div class="resource-item">
                            <div class="resource-icon" style="background: var(--gradient-blue);">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="resource-content">
                                <div class="resource-title"><?php echo htmlspecialchars($annonce['titre']); ?></div>
                                <div class="resource-details">
                                    <?php echo nl2br(htmlspecialchars(substr($annonce['contenu'], 0, 60) . '...')); ?>
                                </div>
                                <div class="resource-meta">
                                    <span><i class="far fa-user"></i> <?php echo htmlspecialchars($annonce['auteur_nom']); ?></span>
                                    <span><i class="far fa-clock"></i> <?php echo date('d/m H:i', strtotime($annonce['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Sessions Mentorat -->
                    <div class="resource-card">
                        <div class="resource-header">
                            <h3 style="font-size: 1.1rem; font-weight: 600;">Sessions mentorat</h3>
                            <a href="mentorat.php" style="font-size: 0.875rem; color: var(--notion-blue); text-decoration: none; font-weight: 500;">
                                Voir tout →
                            </a>
                        </div>
                        
                        <?php foreach(array_slice($sessions_mentorat, 0, 3) as $session): ?>
                        <div class="resource-item">
                            <div class="resource-icon" style="background: var(--gradient-teal);">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="resource-content">
                                <div class="resource-title"><?php echo htmlspecialchars($session['matiere_nom']); ?></div>
                                <div class="resource-details">
                                    Avec <?php echo htmlspecialchars($session['mentor_nom']); ?>
                                </div>
                                <div class="resource-meta">
                                    <span><i class="far fa-calendar"></i> <?php echo date('d/m', strtotime($session['date_session'])); ?></span>
                                    <span><i class="far fa-clock"></i> <?php echo date('H:i', strtotime($session['date_session'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            
            <!-- Section Ressources par matière Notion -->
            <section class="fade-in-up mt-4" style="animation-delay: 400ms">
                <div class="section-title mb-3">
                    <div class="section-icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <h2>Ressources par matière</h2>
                </div>
                
                <div class="matieres-grid">
                    <?php foreach(array_slice($circular_progress, 0, 3) as $index => $matiere): 
                        $color = !empty($matiere['couleur_hex']) ? $matiere['couleur_hex'] : getDefaultColor($index);
                    ?>
                    <div class="matiere-card" style="border-top-color: <?php echo $color; ?>;">
                        <div class="matiere-header">
                            <div class="matiere-icon" style="background: <?php echo $color; ?>;">
                                <?php echo $matiere['filiere'] === 'technology' ? '💻' : '💼'; ?>
                            </div>
                            <div class="matiere-title"><?php echo htmlspecialchars($matiere['matiere_nom']); ?></div>
                        </div>
                        
                        <div class="matiere-ressources">
                            <a href="#" class="ressource-link">
                                <div class="ressource-type">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <span class="ressource-name">Cours du 15/11</span>
                            </a>
                            
                            <a href="#" class="ressource-link">
                                <div class="ressource-type">
                                    <i class="fas fa-play-circle"></i>
                                </div>
                                <span class="ressource-name">Vidéo TD</span>
                            </a>
                            
                            <a href="flashcards.php?matiere=<?php echo urlencode($matiere['matiere_nom']); ?>" class="ressource-link">
                                <div class="ressource-type">
                                    <i class="fas fa-brain"></i>
                                </div>
                                <span class="ressource-name">Flashcards</span>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>
    
    <script>
        // Fonction pour assombrir une couleur
        function darkenColor(color, percent) {
            let r = parseInt(color.substring(1, 3), 16);
            let g = parseInt(color.substring(3, 5), 16);
            let b = parseInt(color.substring(5, 7), 16);
            
            r = Math.floor(r * (100 - percent) / 100);
            g = Math.floor(g * (100 - percent) / 100);
            b = Math.floor(b * (100 - percent) / 100);
            
            return `rgb(${r}, ${g}, ${b})`;
        }
        
        // Checklist functionality (inspiré Vygo)
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion des cases à cocher
            document.querySelectorAll('.checklist-item').forEach(item => {
                item.addEventListener('click', function() {
                    this.classList.toggle('completed');
                    
                    // Mettre à jour le compteur de la catégorie
                    const category = this.closest('.checklist-card');
                    const progressText = category.querySelector('.progress-text');
                    const progressBar = category.querySelector('.progress-bar');
                    
                    if (progressText && progressBar) {
                        const [completed, total] = progressText.textContent.split('/').map(num => parseInt(num));
                        
                        if (this.classList.contains('completed')) {
                            progressText.textContent = `${completed + 1}/${total}`;
                            const newWidth = ((completed + 1) / total) * 100;
                            progressBar.style.width = `${newWidth}%`;
                            
                            // Animation de validation
                            this.style.animation = 'none';
                            setTimeout(() => {
                                this.style.animation = 'fadeInUp 0.3s ease-out';
                            }, 10);
                        } else {
                            progressText.textContent = `${completed - 1}/${total}`;
                            const newWidth = ((completed - 1) / total) * 100;
                            progressBar.style.width = `${newWidth}%`;
                        }
                        
                        // Sauvegarder dans localStorage
                        saveChecklistState();
                    }
                });
            });
            
            // Animation des cercles de progression
            animateProgressCircles();
            
            // Initialiser le calendrier
            initCalendar();
        });
        
        // Animer les cercles de progression (inspiré Dribbble)
        function animateProgressCircles() {
            const circles = document.querySelectorAll('.circular-fill');
            circles.forEach(circle => {
                const progress = parseFloat(circle.style.getPropertyValue('--progress') || 0);
                circle.style.transform = `rotate(${progress * 3.6}deg)`;
            });
        }
        
        // Initialiser le calendrier MyStudyLife style
        function initCalendar() {
            // Ajouter des événements de drag and drop
            const calendarEvents = document.querySelectorAll('.calendar-event');
            calendarEvents.forEach(event => {
                event.addEventListener('mouseenter', () => {
                    event.style.zIndex = '10';
                });
                
                event.addEventListener('mouseleave', () => {
                    event.style.zIndex = '1';
                });
            });
        }
        
        // Changer la vue du calendrier
        function changeView(view) {
            const buttons = document.querySelectorAll('.calendar-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Ici, vous pourriez charger une vue différente via AJAX
            console.log(`Changement vers la vue: ${view}`);
        }
        
        // Ouvrir les détails d'un cours
        function openCourseDetails(courseId) {
            // Ici, vous pourriez ouvrir un modal avec les détails du cours
            console.log(`Ouverture des détails du cours: ${courseId}`);
        }
        
        // Sauvegarder l'état de la checklist (simulation)
        function saveChecklistState() {
            const checklistState = {};
            document.querySelectorAll('.checklist-item').forEach(item => {
                const type = item.dataset.type;
                const index = item.dataset.index;
                if (!checklistState[type]) checklistState[type] = [];
                checklistState[type][index] = item.classList.contains('completed');
            });
            
            localStorage.setItem('checklistState', JSON.stringify(checklistState));
        }
        
        // Charger l'état de la checklist
        function loadChecklistState() {
            const savedState = localStorage.getItem('checklistState');
            if (savedState) {
                const checklistState = JSON.parse(savedState);
                
                Object.keys(checklistState).forEach(type => {
                    checklistState[type].forEach((isCompleted, index) => {
                        const item = document.querySelector(`.checklist-item[data-type="${type}"][data-index="${index}"]`);
                        if (item && isCompleted) {
                            item.classList.add('completed');
                        }
                    });
                });
            }
        }
        
        // Toggle menu utilisateur
        function toggleUserMenu() {
            // Ici, vous pourriez afficher un menu utilisateur
            window.location.href = 'profil.php';
        }
        
        // Afficher plus d'items dans une catégorie
        function showMoreItems(category) {
            // Ici, vous pourriez charger plus d'items via AJAX
            console.log(`Chargement de plus d'items pour: ${category}`);
        }
        
        // Charger l'état sauvegardé au démarrage
        document.addEventListener('DOMContentLoaded', loadChecklistState);
    </script>
</body>
</html>

<?php
// Fonction utilitaire pour assombrir une couleur
function darkenColor($color, $percent) {
    $color = str_replace('#', '', $color);
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    
    $r = floor($r * (100 - $percent) / 100);
    $g = floor($g * (100 - $percent) / 100);
    $b = floor($b * (100 - $percent) / 100);
    
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}
?>
