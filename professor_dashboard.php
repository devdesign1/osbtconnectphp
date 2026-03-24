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

// === DONNÉES PROFESSEUR ===
$mentorat_todo = [];
$flashcards_todo = [];
$presences_todo = [];
$todo_list = [];
$heatmap_data = [];
$classes = [];
$etudiants = [];
$mes_decks = [];
$sessions_mentorat = [];
$stats_pedagogiques = [];
$student_progress = [];

$user_id = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'];
$user_prenom = $_SESSION['user_prenom'];

try {
    // Informations du professeur
    $stmt = $pdo->prepare("
        SELECT p.*, u.email, u.filiere as specialite_principale
        FROM professeurs p
        JOIN utilisateurs u ON p.id_utilisateur = u.id_utilisateur
        WHERE p.id_utilisateur = ?
    ");
    $stmt->execute([$user_id]);
    $professor_data = $stmt->fetch();
    
    // === TO-DO LIST (Canvas LMS) ===
    // Sessions mentorat à confirmer
    $stmt = $pdo->prepare("
        SELECT sm.*, 
               CONCAT(u.prenom, ' ', u.nom) as etudiant_nom,
               m.nom as matiere_nom,
               'mentorat' as type,
               TIMESTAMPDIFF(HOUR, NOW(), sm.date_session) as heures_restantes
        FROM sessions_mentorat sm
        JOIN utilisateurs u ON sm.etudiant_id = u.id_utilisateur
        JOIN matieres m ON sm.matiere_id = m.id_matiere
        WHERE sm.mentor_id = ?
        AND sm.statut = 'demande_envoyee'
        AND sm.date_session >= NOW()
        ORDER BY sm.date_session ASC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $mentorat_todo = $stmt->fetchAll();
    
    // Flashcards à valider (si le professeur modère les decks)
    $stmt = $pdo->prepare("
        SELECT d.*, 
               CONCAT(u.prenom, ' ', u.nom) as createur_nom,
               'flashcard' as type,
               DATEDIFF(NOW(), d.created_at) as jours_attente
        FROM decks d
        JOIN utilisateurs u ON d.createur_id = u.id_utilisateur
        WHERE d.est_valide = 0
        AND d.createur_id IN (
            SELECT id_utilisateur FROM utilisateurs 
            WHERE classe_id IN (
                SELECT id_classe FROM classes WHERE professeur_id = ?
            )
        )
        ORDER BY d.created_at ASC
        LIMIT 5
    ");
    $stmt->execute([$professor_data['id_professeur']]);
    $flashcards_todo = $stmt->fetchAll();
    
    // Présences à marquer pour les cours du jour
    $stmt = $pdo->prepare("
        SELECT pc.*, m.nom as matiere_nom,
               'presence' as type,
               COUNT(DISTINCT u.id_utilisateur) as etudiants_attendus
        FROM planning_cours pc
        JOIN matieres m ON pc.matiere_id = m.id_matiere
        JOIN classes c ON m.id_matiere = c.matiere_id AND c.professeur_id = ?
        JOIN utilisateurs u ON u.classe_id = c.id_classe AND u.role = 'etudiant'
        WHERE DATE(pc.date_seance) = CURDATE()
        AND pc.heure_debut <= NOW()
        AND pc.heure_fin >= NOW()
        GROUP BY pc.id_cours
        LIMIT 5
    ");
    $stmt->execute([$professor_data['id_professeur']]);
    $presences_todo = $stmt->fetchAll();
    
    // Assembler la to-do list
    $todo_list = array_merge($mentorat_todo ?? [], $flashcards_todo ?? [], $presences_todo ?? []);
    
    // === HEATMAP DATA (Khan Academy Style) ===
    $stmt = $pdo->prepare("
        SELECT 
            u.id_utilisateur,
            CONCAT(u.prenom, ' ', u.nom) as etudiant_nom,
            u.classe_id,
            c.nom as classe_nom,
            COUNT(DISTINCT d.id_deck) as nb_decks,
            COUNT(DISTINCT f.id_flashcard) as nb_flashcards,
            AVG(f.taux_reussite) as taux_reussite_moyen,
            COUNT(DISTINCT CASE WHEN f.prochaine_revision <= CURDATE() THEN f.id_flashcard END) as a_reviser,
            COUNT(DISTINCT sm.id_session) as sessions_mentorat,
            CASE 
                WHEN AVG(f.taux_reussite) >= 80 THEN 'excellent'
                WHEN AVG(f.taux_reussite) >= 60 THEN 'bon'
                WHEN AVG(f.taux_reussite) >= 40 THEN 'moyen'
                ELSE 'faible'
            END as niveau
        FROM utilisateurs u
        JOIN classes c ON u.classe_id = c.id_classe AND c.professeur_id = ?
        LEFT JOIN decks d ON u.id_utilisateur = d.createur_id
        LEFT JOIN flashcards f ON d.id_deck = f.deck_id
        LEFT JOIN sessions_mentorat sm ON u.id_utilisateur = sm.etudiant_id
        WHERE u.role = 'etudiant' 
        AND u.est_actif = 1
        GROUP BY u.id_utilisateur, u.classe_id, c.nom
        ORDER BY taux_reussite_moyen DESC
    ");
    $stmt->execute([$professor_data['id_professeur']]);
    $heatmap_data = $stmt->fetchAll();
    
    // === PROGRESSION PAR CLASSE ===
    $stmt = $pdo->prepare("
        SELECT 
            c.id_classe,
            c.nom as classe_nom,
            m.nom as matiere_nom,
            COUNT(DISTINCT u.id_utilisateur) as nb_etudiants,
            AVG(f.taux_reussite) as taux_reussite_moyen,
            COUNT(DISTINCT d.id_deck) as nb_decks_classe,
            COUNT(DISTINCT f.id_flashcard) as nb_flashcards_classe,
            SUM(CASE WHEN u.derniere_connexion >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as actifs_7j
        FROM classes c
        JOIN matieres m ON c.matiere_id = m.id_matiere
        LEFT JOIN utilisateurs u ON c.id_classe = u.classe_id AND u.role = 'etudiant' AND u.est_actif = 1
        LEFT JOIN decks d ON u.id_utilisateur = d.createur_id
        LEFT JOIN flashcards f ON d.id_deck = f.deck_id
        WHERE c.professeur_id = ? AND c.est_active = 1
        GROUP BY c.id_classe, c.nom, m.nom
        ORDER BY taux_reussite_moyen DESC
    ");
    $stmt->execute([$professor_data['id_professeur']]);
    $classes = $stmt->fetchAll();
    
    // === ÉTUDIANTS AVEC PROGRESSION DÉTAILLÉE ===
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            c.nom as classe_nom,
            m.nom as matiere_nom,
            c.promotion,
            (SELECT COUNT(*) FROM decks WHERE createur_id = u.id_utilisateur) as nb_decks,
            (SELECT AVG(taux_reussite) FROM flashcards WHERE deck_id IN (
                SELECT id_deck FROM decks WHERE createur_id = u.id_utilisateur
            )) as taux_reussite,
            (SELECT COUNT(*) FROM sessions_mentorat WHERE etudiant_id = u.id_utilisateur AND statut = 'session_terminee') as sessions_terminees,
            CASE 
                WHEN u.derniere_connexion >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'Aujourdhui'
                WHEN u.derniere_connexion >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'Cette semaine'
                ELSE 'Inactif'
            END as statut_activite
        FROM utilisateurs u
        JOIN classes c ON u.classe_id = c.id_classe
        JOIN matieres m ON c.matiere_id = m.id_matiere
        WHERE u.role = 'etudiant' 
        AND u.est_actif = 1
        AND c.professeur_id = ?
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute([$professor_data['id_professeur']]);
    $etudiants = $stmt->fetchAll();
    
    // === DECKS DU PROFESSEUR ===
    $stmt = $pdo->prepare("
        SELECT d.*, 
               COUNT(DISTINCT f.id_flashcard) as nb_flashcards,
               AVG(f.taux_reussite) as taux_reussite_moyen,
               COUNT(DISTINCT CASE WHEN f.prochaine_revision <= CURDATE() THEN f.id_flashcard END) as a_reviser,
               (SELECT COUNT(DISTINCT u2.id_utilisateur) 
                FROM utilisateurs u2 
                JOIN classes c2 ON u2.classe_id = c2.id_classe 
                WHERE c2.professeur_id = ?) as etudiants_cibles
        FROM decks d
        LEFT JOIN flashcards f ON d.id_deck = f.deck_id
        WHERE d.createur_id = ?
        GROUP BY d.id_deck
        ORDER BY d.created_at DESC
        LIMIT 8
    ");
    $stmt->execute([$professor_data['id_professeur'], $user_id]);
    $mes_decks = $stmt->fetchAll();
    
    // === SESSIONS MENTORAT ===
    $stmt = $pdo->prepare("
        SELECT sm.*, 
               CONCAT(u.prenom, ' ', u.nom) as etudiant_nom,
               m.nom as matiere_nom,
               u.classe_id,
               c.nom as classe_nom,
               CASE 
                   WHEN sm.date_session < NOW() THEN 'en_retard'
                   WHEN sm.date_session <= DATE_ADD(NOW(), INTERVAL 1 DAY) THEN 'urgent'
                   ELSE 'planifie'
               END as priorite
        FROM sessions_mentorat sm
        JOIN utilisateurs u ON sm.etudiant_id = u.id_utilisateur
        JOIN matieres m ON sm.matiere_id = m.id_matiere
        LEFT JOIN classes c ON u.classe_id = c.id_classe
        WHERE sm.mentor_id = ?
        AND sm.date_session >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY sm.date_session ASC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $sessions_mentorat = $stmt->fetchAll();
    
    // === STATISTIQUES PÉDAGOGIQUES ===
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT c.id_classe) as nb_classes,
            COUNT(DISTINCT u.id_utilisateur) as nb_etudiants_total,
            COUNT(DISTINCT d.id_deck) as nb_decks_crees,
            COUNT(DISTINCT f.id_flashcard) as nb_flashcards_crees,
            AVG(f.taux_reussite) as taux_reussite_global,
            COUNT(DISTINCT sm.id_session) as nb_sessions_mentorat,
            COUNT(DISTINCT CASE WHEN sm.statut = 'session_terminee' THEN sm.id_session END) as nb_sessions_terminees,
            COUNT(DISTINCT CASE WHEN u.derniere_connexion >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN u.id_utilisateur END) as etudiants_actifs_7j,
            SUM(CASE WHEN f.taux_reussite < 50 THEN 1 ELSE 0 END) as flashcards_en_difficulte
        FROM professeurs p
        LEFT JOIN classes c ON c.professeur_id = p.id_professeur AND c.est_active = 1
        LEFT JOIN utilisateurs u ON u.classe_id = c.id_classe AND u.role = 'etudiant' AND u.est_actif = 1
        LEFT JOIN decks d ON d.createur_id = p.id_utilisateur
        LEFT JOIN flashcards f ON f.deck_id = d.id_deck
        LEFT JOIN sessions_mentorat sm ON sm.mentor_id = p.id_utilisateur
        WHERE p.id_utilisateur = ?
    ");
    $stmt->execute([$user_id]);
    $stats_pedagogiques = $stmt->fetch();
    
    // === DONNÉES POUR GRAPHIQUES DE PROGRESSION ===
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(f.date_revision, '%Y-%m-%d') as date,
            AVG(f.taux_reussite) as taux_moyen,
            COUNT(DISTINCT f.id_flashcard) as nb_revisions
        FROM flashcards f
        JOIN decks d ON f.deck_id = d.id_deck
        WHERE d.createur_id = ?
        AND f.date_revision >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE_FORMAT(f.date_revision, '%Y-%m-%d')
        ORDER BY date ASC
    ");
    $stmt->execute([$user_id]);
    $progress_data = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur dashboard professeur: " . $e->getMessage());
    $professor_data = ['nom' => $_SESSION['user_nom'], 'prenom' => $_SESSION['user_prenom'], 'specialite' => 'Professeur'];
    $classes = [];
    $etudiants = [];
    $mes_decks = [];
    $sessions_mentorat = [];
    $stats_pedagogiques = [
        'nb_classes' => 0,
        'nb_etudiants_total' => 0,
        'nb_decks_crees' => 0,
        'nb_flashcards_crees' => 0,
        'taux_reussite_global' => 0,
        'nb_sessions_mentorat' => 0,
        'nb_sessions_terminees' => 0
    ];
    $todo_list = [];
    $heatmap_data = [];
}

// Fonctions utilitaires
function formatDateOSBT($date) {
    if (empty($date)) return 'N/A';
    $date_obj = new DateTime($date);
    $mois_fr = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return $date_obj->format('d') . ' ' . $mois_fr[$date_obj->format('n') - 1] . ' ' . $date_obj->format('Y');
}

function getPriorityBadge($priority) {
    $badges = [
        'urgent' => '<span class="priority-badge urgent" title="À faire dans les 24h">⚡ Urgent</span>',
        'en_retard' => '<span class="priority-badge late" title="En retard">⚠️ En retard</span>',
        'planifie' => '<span class="priority-badge planned" title="Planifié">📅 Planifié</span>'
    ];
    return $badges[$priority] ?? '<span class="priority-badge">' . $priority . '</span>';
}

function getTodoTypeIcon($type) {
    $icons = [
        'mentorat' => 'fa-handshake',
        'flashcard' => 'fa-brain',
        'presence' => 'fa-clipboard-check',
        'quiz' => 'fa-question-circle',
        'devoir' => 'fa-file-alt'
    ];
    return $icons[$type] ?? 'fa-tasks';
}

function getHeatmapColor($niveau) {
    $colors = [
        'excellent' => 'var(--heatmap-excellent)',
        'bon' => 'var(--heatmap-good)',
        'moyen' => 'var(--heatmap-medium)',
        'faible' => 'var(--heatmap-weak)'
    ];
    return $colors[$niveau] ?? 'var(--heatmap-default)';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratoire Pédagogique - OSBT Connect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/chart.js" rel="stylesheet">
    <style>
        /* ===== VARIABLES SCI-FI/LAB ===== */
        :root {
            /* ===== PALETTE INSPIRÉE DU STUDENT DASHBOARD (NOTION/VYGO) ===== */
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
            
            /* Espacements cohérents */
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;
        }

        /* ===== RESET & BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', 'Roboto', 'SF Pro Display', system-ui, sans-serif;
            background: var(--notion-gray-50);
            color: var(--notion-gray-900);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(0, 200, 83, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(66, 133, 244, 0.05) 0%, transparent 50%);
            z-index: -1;
        }

        /* ===== LAYOUT INSPIRÉ VYGO (SIDEBAR FINE) ===== */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* ===== SIDEBAR VYGO STYLE (INSPIRÉ STUDENT DASHBOARD) ===== */
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

        /* ===== MAIN CONTENT ===== */
        .main-content {
            flex: 1;
            margin-left: 72px;
            padding: var(--spacing-xl);
            position: relative;
        }

        .vygo-sidebar:hover ~ .main-content {
            margin-left: 240px;
        }

        /* Header Style Cohérent */
        .dashboard-header {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, 
                transparent, 
                var(--notion-green), 
                var(--notion-blue), 
                transparent
            );
            box-shadow: 0 0 10px rgba(0, 200, 83, 0.2);
        }

        .welcome-terminal {
            font-family: 'Courier New', monospace;
        }

        .terminal-line {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .terminal-prompt {
            color: var(--notion-green);
            font-weight: bold;
        }

        .terminal-text {
            color: var(--notion-gray-900);
            animation: typewriter 2s steps(40) 1s 1 normal both;
            overflow: hidden;
            white-space: nowrap;
        }

        @keyframes typewriter {
            from { width: 0; }
            to { width: 100%; }
        }

        .terminal-cursor {
            display: inline-block;
            width: 8px;
            height: 16px;
            background: var(--notion-green);
            animation: blink 1s infinite;
            margin-left: 2px;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }

        /* ===== TO-DO LIST STYLE COHÉRENT ===== */
        .todo-section {
            margin-bottom: var(--spacing-xl);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--notion-gray-200);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--notion-green);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .section-title i {
            color: var(--notion-green);
        }

        .section-subtitle {
            color: var(--notion-gray-600);
            font-size: 0.9rem;
        }

        .todo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: var(--spacing-lg);
        }

        .todo-card {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            position: relative;
            transition: all var(--transition-base);
            overflow: hidden;
        }

        .todo-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--notion-green);
        }

        .todo-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-green);
        }

        .todo-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }

        .todo-icon {
            width: 40px;
            height: 40px;
            background: rgba(0, 200, 83, 0.1);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--notion-green);
            font-size: 1.2rem;
        }

        .todo-title {
            flex: 1;
            font-weight: 600;
            color: var(--notion-gray-900);
        }

        .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .priority-badge.urgent {
            background: rgba(255, 82, 82, 0.1);
            color: var(--notion-red);
            border: 1px solid rgba(255, 82, 82, 0.2);
        }

        .priority-badge.late {
            background: rgba(255, 214, 0, 0.1);
            color: var(--notion-yellow);
            border: 1px solid rgba(255, 214, 0, 0.2);
        }

        .priority-badge.planned {
            background: rgba(66, 133, 244, 0.1);
            color: var(--notion-blue);
            border: 1px solid rgba(66, 133, 244, 0.2);
        }

        .todo-details {
            margin-bottom: var(--spacing-md);
        }

        .todo-meta {
            display: flex;
            gap: var(--spacing-lg);
            margin-bottom: 8px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.85rem;
            color: var(--notion-gray-600);
        }

        .meta-item i {
            color: var(--notion-blue);
        }

        .todo-description {
            color: var(--notion-gray-600);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .todo-actions {
            display: flex;
            gap: var(--spacing-sm);
            justify-content: flex-end;
        }

        /* ===== BUTTONS STYLE COHÉRENT ===== */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            transition: all var(--transition-base);
            text-decoration: none;
        }

        .btn-primary {
            background: var(--notion-green);
            color: white;
        }

        .btn-primary:hover {
            background: var(--notion-teal);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--notion-gray-200);
            color: var(--notion-gray-800);
        }

        .btn-secondary:hover {
            background: var(--notion-gray-300);
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        /* ===== HEATMAP STYLE COHÉRENT ===== */
        .heatmap-section {
            margin-bottom: var(--spacing-xl);
        }

        .heatmap-container {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
        }

        .heatmap-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: var(--spacing-md);
            margin-top: var(--spacing-lg);
        }

        .heatmap-card {
            background: var(--notion-gray-50);
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            transition: all var(--transition-fast);
        }

        .heatmap-card:hover {
            transform: translateY(-2px);
            border-color: var(--notion-green);
            box-shadow: var(--shadow-md);
        }

        .student-heatmap {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            background: var(--gradient-blue);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: white;
            font-weight: 600;
        }

        .student-info {
            flex: 1;
        }

        .student-name {
            font-weight: 600;
            margin-bottom: 2px;
            color: var(--notion-gray-900);
        }

        .student-class {
            font-size: 0.8rem;
            color: var(--notion-gray-600);
        }

        .heatmap-indicator {
            width: 60px;
            height: 10px;
            background: var(--notion-gray-200);
            border-radius: 5px;
            overflow: hidden;
            position: relative;
        }

        .heatmap-fill {
            height: 100%;
            width: var(--fill-width);
            background: var(--heatmap-color);
            transition: width 1s ease;
        }

        /* ===== PROGRESS TUBES (Éprouvettes) ===== */
        .progress-tubes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
        }

        .tube-card {
            background: linear-gradient(135deg, 
                rgba(26, 31, 46, 0.8) 0%,
                rgba(45, 52, 71, 0.8) 100%
            );
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--space-md);
            position: relative;
            overflow: hidden;
        }

        .tube-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, 
                transparent, 
                var(--neon-green), 
                transparent
            );
        }

        .tube-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-md);
        }

        .tube-title {
            font-weight: 600;
            color: var(--text-primary);
        }

        .tube-percentage {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--neon-green);
        }

        .tube-container {
            position: relative;
            height: 200px;
            margin: var(--space-md) 0;
        }

        .tube {
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 180px;
            background: rgba(26, 31, 46, 0.6);
            border: 2px solid var(--border-color);
            border-radius: 0 0 30px 30px;
            overflow: hidden;
        }

        .tube-liquid {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: var(--liquid-height);
            background: linear-gradient(to top, var(--neon-green), var(--neon-blue));
            transition: height 2s ease;
            border-radius: 0 0 28px 28px;
        }

        .tube-liquid::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(to bottom, 
                rgba(255, 255, 255, 0.3), 
                transparent
            );
            border-radius: 0 0 28px 28px;
        }

        .tube-bubbles {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .bubble {
            position: absolute;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            animation: bubbleRise 3s infinite ease-in;
        }

        @keyframes bubbleRise {
            0% { 
                transform: translateY(0) scale(0.5); 
                opacity: 0; 
            }
            10% { opacity: 1; }
            100% { 
                transform: translateY(-180px) scale(1.5); 
                opacity: 0; 
            }
        }

        .tube-metrics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-sm);
            text-align: center;
            margin-top: var(--space-md);
        }

        .tube-metric {
            padding: var(--space-xs);
            background: rgba(0, 255, 157, 0.05);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
        }

        .tube-value {
            display: block;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--neon-green);
            margin-bottom: 2px;
        }

        .tube-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        /* ===== CLASSES STYLE COHÉRENT ===== */
        .classes-section {
            margin-bottom: var(--spacing-xl);
        }

        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }

        .class-card {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            position: relative;
            overflow: hidden;
            transition: all var(--transition-base);
        }

        .class-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--notion-green);
        }

        .class-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient-green);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: var(--spacing-md);
            font-size: 1.5rem;
            color: white;
        }

        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-md);
        }

        .class-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--notion-gray-900);
            margin-bottom: 4px;
        }

        .class-subject {
            color: var(--notion-blue);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .class-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--spacing-sm);
            margin-top: var(--spacing-md);
        }

        .class-stat {
            text-align: center;
            padding: var(--spacing-sm);
            background: var(--notion-gray-50);
            border-radius: var(--radius-md);
            border: 1px solid var(--notion-gray-200);
        }

        .class-value {
            display: block;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--notion-green);
        }

        .class-label {
            font-size: 0.75rem;
            color: var(--notion-gray-600);
        }

        /* ===== QUICK ACTIONS STYLE COHÉRENT ===== */
        .quick-actions-section {
            margin-bottom: var(--spacing-xl);
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-lg);
        }

        .action-card {
            background: white;
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            text-align: center;
            cursor: pointer;
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--notion-green);
        }

        .action-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient-blue);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--spacing-md);
            font-size: 1.5rem;
            color: white;
        }

        .action-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--notion-gray-900);
            margin-bottom: var(--spacing-sm);
        }

        .action-description {
            color: var(--notion-gray-600);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        /* ===== UTILITY CLASSES ===== */
        .fade-in {
            animation: fadeIn 0.6s ease-in;
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        .delay-5 { animation-delay: 0.5s; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .d-flex { display: flex; }
        .d-none { display: none; }
        .align-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-1 { gap: var(--spacing-md); }
        .gap-2 { gap: var(--spacing-xl); }
        .w-100 { width: 100%; }
        .text-center { text-align: center; }
        .mb-1 { margin-bottom: var(--spacing-md); }
        .mb-2 { margin-bottom: var(--spacing-xl); }
        .mt-1 { margin-top: var(--spacing-md); }
        .mt-2 { margin-top: var(--spacing-xl); }
        .p-1 { padding: var(--spacing-md); }
        .p-2 { padding: var(--spacing-xl); }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar.collapsed {
                width: 80px;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .sidebar.collapsed ~ .main-content {
                margin-left: 80px;
            }
            
            .lab-title,
            .professor-info h3,
            .professor-info .specialty,
            .professor-stats,
            .nav-title,
            .nav-link span {
                display: none;
            }
            
            .lab-brand {
                justify-content: center;
            }
            
            .nav-link {
                justify-content: center;
                padding: var(--spacing-md);
            }
            
            .nav-icon {
                margin: 0;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform var(--transition-base);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: var(--spacing-lg);
            }
            
            .todo-grid,
            .heatmap-grid,
            .classes-grid,
            .actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- SIDEBAR VYGO STYLE -->
        <aside class="vygo-sidebar" id="sidebar">
            <!-- Logo -->
            <div class="sidebar-logo">
                <div class="logo-icon">OSBT</div>
                <span class="logo-text">Connect</span>
            </div>
            
            <!-- Navigation principale -->
            <nav class="vygo-nav">
                <div class="nav-item">
                    <a href="#" class="nav-link active">
                        <span class="nav-icon"><i class="fas fa-home"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="professor_classes.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-users"></i></span>
                        <span class="nav-text">Mes Classes</span>
                        <span class="nav-badge"><?= count($classes) ?></span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="professor_decks.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-brain"></i></span>
                        <span class="nav-text">Decks</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="professor_mentorat.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-handshake"></i></span>
                        <span class="nav-text">Mentorat</span>
                        <span class="nav-badge"><?= count($sessions_mentorat) ?></span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
                        <span class="nav-text">Analytics</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-plus"></i></span>
                        <span class="nav-text">Créer un Quiz</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-clipboard-check"></i></span>
                        <span class="nav-text">Faire l'appel</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-bell"></i></span>
                        <span class="nav-text">Notifications</span>
                        <span class="nav-badge">3</span>
                    </a>
                </div>
            </nav>
            
            <!-- Avatar utilisateur -->
            <div class="user-avatar-container">
                <div class="user-avatar" onclick="toggleUserMenu()">
                    <?= strtoupper(substr($_SESSION['user_prenom'], 0, 1)); ?>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <!-- HEADER -->
            <header class="dashboard-header">
                <div class="welcome-terminal">
                    <div class="terminal-line">
                        <span class="terminal-prompt">$</span>
                        <span class="terminal-text">Bienvenue dans votre laboratoire pédagogique</span>
                        <span class="terminal-cursor"></span>
                    </div>
                    <div class="terminal-line">
                        <span class="terminal-prompt">></span>
                        <span class="terminal-text">Professeur <?= htmlspecialchars($professor_data['prenom'] . ' ' . $professor_data['nom']) ?></span>
                    </div>
                </div>
            </header>

            <!-- CONTENU PRINCIPAL -->
            <!-- TO-DO LIST (Canvas LMS Inspired) -->
            <section class="todo-section fade-in delay-1">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-tasks"></i> To-Do List
                    </h2>
                    <span class="section-subtitle">Tâches à valider/corriger</span>
                </div>
                
                <div class="todo-grid">
                    <!-- Sessions de mentorat à confirmer -->
                    <?php foreach($mentorat_todo as $todo): ?>
                    <div class="todo-card">
                        <div class="todo-header">
                            <div class="todo-icon">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <div class="todo-title">Session mentorat</div>
                            <?= getPriorityBadge($todo['heures_restantes'] < 24 ? 'urgent' : 'planifie') ?>
                        </div>
                        <div class="todo-details">
                            <div class="todo-meta">
                                <div class="meta-item">
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($todo['etudiant_nom']) ?>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <?= date('H:i', strtotime($todo['date_session'])) ?>
                                </div>
                            </div>
                            <div class="todo-description">
                                <?= htmlspecialchars($todo['matiere_nom']) ?> - 
                                <?= date('d/m', strtotime($todo['date_session'])) ?>
                            </div>
                        </div>
                        <div class="todo-actions">
                            <button class="btn btn-primary btn-sm" onclick="confirmSession(<?= $todo['id_session'] ?>)">
                                <i class="fas fa-check"></i> Confirmer
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="rescheduleSession(<?= $todo['id_session'] ?>)">
                                <i class="fas fa-calendar"></i> Reporter
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Flashcards à valider -->
                    <?php foreach($flashcards_todo as $todo): ?>
                    <div class="todo-card">
                        <div class="todo-header">
                            <div class="todo-icon">
                                <i class="fas fa-brain"></i>
                            </div>
                            <div class="todo-title">Deck à valider</div>
                            <?= getPriorityBadge($todo['jours_attente'] > 3 ? 'late' : 'planned') ?>
                        </div>
                        <div class="todo-details">
                            <div class="todo-meta">
                                <div class="meta-item">
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($todo['createur_nom']) ?>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <?= $todo['jours_attente'] ?> jours
                                </div>
                            </div>
                            <div class="todo-description">
                                <?= htmlspecialchars($todo['titre']) ?>
                            </div>
                        </div>
                        <div class="todo-actions">
                            <button class="btn btn-primary btn-sm" onclick="validateDeck(<?= $todo['id_deck'] ?>)">
                                <i class="fas fa-check-circle"></i> Valider
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="reviewDeck(<?= $todo['id_deck'] ?>)">
                                <i class="fas fa-eye"></i> Voir
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Présences à marquer -->
                    <?php foreach($presences_todo as $todo): ?>
                    <div class="todo-card">
                        <div class="todo-header">
                            <div class="todo-icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div class="todo-title">Appel à faire</div>
                            <span class="priority-badge urgent">⚡ Maintenant</span>
                        </div>
                        <div class="todo-details">
                            <div class="todo-meta">
                                <div class="meta-item">
                                    <i class="fas fa-book"></i>
                                    <?= htmlspecialchars($todo['matiere_nom']) ?>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-users"></i>
                                    <?= $todo['etudiants_attendus'] ?> étudiants
                                </div>
                            </div>
                            <div class="todo-description">
                                Cours en cours - <?= date('H:i') ?>
                            </div>
                        </div>
                        <div class="todo-actions">
                            <button class="btn btn-primary btn-sm" onclick="markAttendanceNow()">
                                <i class="fas fa-clipboard-check"></i> Faire l'appel
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="viewAttendanceList()">
                                <i class="fas fa-list"></i> Liste
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- QUICK ACTIONS -->
            <section class="quick-actions-section fade-in delay-5">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-rocket"></i> Commandes du Laboratoire
                    </h2>
                    <span class="section-subtitle">Actions pédagogiques rapides</span>
                </div>
                
                <div class="actions-grid">
                    <!-- Lancer un Quiz -->
                    <div class="action-card" onclick="launchQuiz()">
                        <div class="action-icon">
                            <i class="fas fa-question-circle"></i>
                        </div>
                        <h3 class="action-title">Lancer un Quiz</h3>
                        <p class="action-description">Créer un quiz interactif pour vos étudiants</p>
                    </div>
                    
                    <!-- Ouvrir le Labo -->
                    <div class="action-card" onclick="openLab()">
                        <div class="action-icon">
                            <i class="fas fa-flask"></i>
                        </div>
                        <h3 class="action-title">Ouvrir le Labo</h3>
                        <p class="action-description">Accéder aux statistiques détaillées</p>
                    </div>
                    
                    <!-- Appel -->
                    <div class="action-card" onclick="takeAttendance()">
                        <div class="action-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <h3 class="action-title">Appel</h3>
                        <p class="action-description">Marquer les présences de la journée</p>
                    </div>
                    
                    <!-- Créer un Deck -->
                    <div class="action-card" onclick="createDeck()">
                        <div class="action-icon">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h3 class="action-title">Créer un Deck</h3>
                        <p class="action-description">Nouveau contenu pédagogique</p>
                    </div>
                </div>
            </section>

            <!-- MES CLASSES -->
            <section class="classes-section fade-in delay-4">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-graduation-cap"></i> Mes Classes
                    </h2>
                    <span class="section-subtitle">Laboratoires pédagogiques assignés</span>
                </div>
                
                <div class="classes-grid">
                    <?php foreach($classes as $classe): ?>
                    <div class="class-card">
                        <div class="class-icon">
                            <?php if(strtolower($classe['matiere_nom']) == 'technology'): ?>
                                <i class="fas fa-microchip"></i>
                            <?php else: ?>
                                <i class="fas fa-chart-line"></i>
                            <?php endif; ?>
                        </div>
                        <div class="class-header">
                            <div>
                                <div class="class-title"><?= htmlspecialchars($classe['classe_nom']) ?></div>
                                <div class="class-subject"><?= htmlspecialchars($classe['matiere_nom']) ?></div>
                            </div>
                            <div class="tube-percentage"><?= round($classe['taux_reussite_moyen'] ?? 0) ?>%</div>
                        </div>
                        <div class="class-stats">
                            <div class="class-stat">
                                <span class="class-value"><?= $classe['nb_etudiants'] ?></span>
                                <span class="class-label">Étudiants</span>
                            </div>
                            <div class="class-stat">
                                <span class="class-value"><?= $classe['nb_decks_classe'] ?></span>
                                <span class="class-label">Decks</span>
                            </div>
                            <div class="class-stat">
                                <span class="class-value"><?= $classe['actifs_7j'] ?></span>
                                <span class="class-label">Actifs (7j)</span>
                            </div>
                            <div class="class-stat">
                                <span class="class-value"><?= $classe['nb_flashcards_classe'] ?></span>
                                <span class="class-label">Flashcards</span>
                            </div>
                        </div>
                        <div class="todo-actions mt-2">
                            <button class="btn btn-primary btn-sm" onclick="viewClass(<?= $classe['id_classe'] ?>)">
                                <i class="fas fa-eye"></i> Voir la classe
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="analyzeClass(<?= $classe['id_classe'] ?>)">
                                <i class="fas fa-chart-bar"></i> Analytics
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // === INITIALISATION ===
        document.addEventListener('DOMContentLoaded', function() {
            // Animations au chargement
            setTimeout(() => {
                document.querySelectorAll('.terminal-text').forEach((el, index) => {
                    setTimeout(() => {
                        el.style.animation = `typewriter 2s steps(${el.textContent.length}) 1s 1 normal both`;
                    }, index * 500);
                });
            }, 1000);
            
            // Menu mobile
            const hamburger = document.getElementById('hamburgerMenu');
            const sidebar = document.getElementById('sidebar');
            
            hamburger?.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
            
            // Fermer le menu en cliquant à l'extérieur (mobile)
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 1200 && 
                    !sidebar.contains(e.target) && 
                    !hamburger.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            });
        });

        // === FONCTIONS DES ACTIONS ===
        function launchQuiz() {
            showNotification('Quiz en construction', 'La fonctionnalité Quiz sera bientôt disponible !', 'info');
        }

        function openLab() {
            showNotification('Analytics en construction', 'Le laboratoire d\'analytics sera bientôt disponible !', 'info');
        }

        function takeAttendance() {
            showNotification('Appel en construction', 'La fonctionnalité d\'appel sera bientôt disponible !', 'info');
        }

        function createDeck() {
            showNotification('Redirection', 'Redirection vers la page des decks...', 'info');
            setTimeout(() => {
                window.location.href = 'professor_decks.php';
            }, 1000);
        }

        function confirmSession(sessionId) {
            if (confirm('Confirmer cette session de mentorat ?')) {
                fetch(`api/confirm_session.php?id=${sessionId}`, { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        showNotification('Session confirmée', 'La session a été confirmée avec succès', 'success');
                        setTimeout(() => location.reload(), 1500);
                    })
                    .catch(error => {
                        showNotification('Erreur', 'Impossible de confirmer la session', 'danger');
                    });
            }
        }

        function validateDeck(deckId) {
            if (confirm('Valider ce deck de flashcards ?')) {
                fetch(`api/validate_deck.php?id=${deckId}`, { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        showNotification('Deck validé', 'Le deck a été validé et est maintenant public', 'success');
                        setTimeout(() => location.reload(), 1500);
                    })
                    .catch(error => {
                        showNotification('Erreur', 'Impossible de valider le deck', 'danger');
                    });
            }
        }

        function markAttendanceNow() {
            takeAttendance();
        }

        function viewClass(classId) {
            window.location.href = `professor_class.php?id=${classId}`;
        }

        function analyzeClass(classId) {
            window.location.href = `professor_analytics.php?class=${classId}`;
        }

        function rescheduleSession(sessionId) {
            showNotification('Reporter session', 'Fonctionnalité de report à implémenter', 'info');
        }

        function reviewDeck(deckId) {
            window.location.href = `professor_decks.php?review=${deckId}`;
        }

        function viewAttendanceList() {
            const today = new Date().toISOString().split('T')[0];
            window.location.href = `professor_attendance.php?date=${today}`;
        }

        // === SYSTÈME DE NOTIFICATION ===
        function showNotification(title, message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-icon">
                    <i class="fas fa-${getNotificationIcon(type)}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${title}</div>
                    <div class="notification-message">${message}</div>
                    <div class="notification-time">${new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}</div>
                </div>
                <button class="notification-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            Object.assign(notification.style, {
                position: 'fixed',
                top: '20px',
                right: '20px',
                background: 'white',
                border: '1px solid var(--notion-gray-200)',
                borderRadius: 'var(--radius-lg)',
                padding: 'var(--spacing-md)',
                width: '300px',
                zIndex: '9999',
                display: 'flex',
                alignItems: 'center',
                gap: 'var(--spacing-sm)',
                boxShadow: 'var(--shadow-lg)',
                transform: 'translateX(400px)',
                transition: 'transform 0.3s ease'
            });
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(400px)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }

        function getNotificationIcon(type) {
            const icons = {
                'success': 'check-circle',
                'info': 'info-circle',
                'warning': 'exclamation-triangle',
                'danger': 'exclamation-circle'
            };
            return icons[type] || 'bell';
        }

        // === FONCTIONS UTILITAIRES ===
        function toggleUserMenu() {
            // Ici, vous pourriez afficher un menu utilisateur
            window.location.href = 'profil.php';
        }
    </script>
</body>
</html>