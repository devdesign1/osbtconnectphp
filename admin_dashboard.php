<?php
session_start();

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Vérifier que l'utilisateur est admin
if ($_SESSION['user_role'] !== 'admin') {
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

// === DONNÉES ADMIN ===
$stats = [];
$users = [];
$courses = [];
$announcements = [];
$kpi_strategiques = [];
$monitoring_temps_reel = [];
$alertes_automatiques = [];
$trends = [];
$analytics_avances = [];

$user_id = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'];
$user_prenom = $_SESSION['user_prenom'];

try {
    // === STATISTIQUES GLOBALES ===
    $total_users = $pdo->query("SELECT COUNT(*) as count FROM utilisateurs WHERE est_actif = 1")->fetch()['count'];
    $total_students = $pdo->query("SELECT COUNT(*) as count FROM utilisateurs WHERE est_actif = 1 AND role = 'etudiant'")->fetch()['count'];
    $total_mentors = $pdo->query("SELECT COUNT(*) as count FROM utilisateurs WHERE est_actif = 1 AND role IN ('tech', 'business')")->fetch()['count'];
    $total_courses = $pdo->query("SELECT COUNT(*) as count FROM planning_cours")->fetch()['count'];
    $total_announcements = $pdo->query("SELECT COUNT(*) as count FROM annonces WHERE est_active = 1")->fetch()['count'];
    
    // === CALCUL DES TRENDS ===
    $trends['users'] = calculateTrend($pdo, "utilisateurs", "date_inscription", 7);
    $trends['students'] = calculateTrend($pdo, "utilisateurs", "date_inscription", 7, "role = 'etudiant'");
    $trends['mentors'] = calculateTrend($pdo, "utilisateurs", "date_inscription", 7, "role IN ('tech', 'business')");
    $trends['courses'] = calculateTrend($pdo, "planning_cours", "created_at", 7);
    
    $stats = [
        'total_users' => $total_users,
        'total_students' => $total_students,
        'total_mentors' => $total_mentors,
        'total_courses' => $total_courses,
        'total_announcements' => $total_announcements,
        'trends' => $trends
    ];
    
    // === KPIs STRATÉGIQUES ===
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT u.id_utilisateur) as utilisateurs_actifs_jour,
            COUNT(DISTINCT CASE WHEN u.derniere_connexion >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN u.id_utilisateur END) as utilisateurs_actifs_semaine,
            COUNT(DISTINCT CASE WHEN u.derniere_connexion >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN u.id_utilisateur END) as utilisateurs_actifs_mois,
            AVG(CASE WHEN u.derniere_connexion >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN TIMESTAMPDIFF(HOUR, u.derniere_connexion, NOW()) ELSE NULL END) as temps_moyen_entre_connexions
        FROM utilisateurs u
        WHERE u.est_actif = 1
    ");
    $kpi_engagement = $stmt->fetch();
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT d.id_deck) as total_decks,
            COUNT(DISTINCT f.id_flashcard) as total_flashcards,
            AVG(f.taux_reussite) as taux_moyen_reussite,
            COUNT(DISTINCT CASE WHEN f.prochaine_revision <= CURDATE() THEN f.id_flashcard END) as flashcards_a_reviser_aujourdhui
        FROM decks d
        LEFT JOIN flashcards f ON d.id_deck = f.deck_id
        WHERE d.createur_id IN (SELECT id_utilisateur FROM utilisateurs WHERE est_actif = 1)
    ");
    $kpi_flashcards = $stmt->fetch();
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT sm.id_session) as total_sessions_mentorat,
            COUNT(DISTINCT CASE WHEN sm.statut = 'session_terminee' THEN sm.id_session END) as sessions_terminees,
            AVG(CASE WHEN sm.statut = 'session_terminee' THEN TIMESTAMPDIFF(MINUTE, sm.date_session, sm.date_fin) ELSE NULL END) as duree_moyenne_sessions
        FROM sessions_mentorat sm
        WHERE sm.date_session >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $kpi_mentorat = $stmt->fetch();
    
    $kpi_strategiques = [
        'engagement' => $kpi_engagement,
        'flashcards' => $kpi_flashcards,
        'mentorat' => $kpi_mentorat
    ];
    
    // === MONITORING TEMPS RÉEL ===
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT id_utilisateur) as connexions_en_cours,
            COUNT(DISTINCT CASE WHEN derniere_connexion >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN id_utilisateur END) as utilisateurs_actifs_5min,
            COUNT(DISTINCT CASE WHEN derniere_connexion >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN id_utilisateur END) as utilisateurs_actifs_1h
        FROM utilisateurs 
        WHERE est_actif = 1
    ");
    $monitoring_connexions = $stmt->fetch();
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as cours_en_cours,
            COUNT(DISTINCT matiere_id) as matieres_actives
        FROM planning_cours 
        WHERE date_seance <= NOW() 
        AND DATE_ADD(heure_debut, INTERVAL duree MINUTE) >= NOW()
    ");
    $monitoring_cours = $stmt->fetch();
    
    $monitoring_temps_reel = [
        'connexions' => $monitoring_connexions,
        'cours' => $monitoring_cours,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // === ALERTES AUTOMATIQUES ===
    $alertes_automatiques = [];
    
    // Alerte: Utilisateurs inactifs
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM utilisateurs 
        WHERE est_actif = 1 
        AND (derniere_connexion IS NULL OR derniere_connexion < DATE_SUB(NOW(), INTERVAL 7 DAY))
    ");
    $inactifs_7j = $stmt->fetch()['count'];
    if ($inactifs_7j > 0) {
        $alertes_automatiques[] = [
            'type' => 'warning',
            'icone' => 'fa-user-clock',
            'titre' => 'Utilisateurs inactifs',
            'message' => "$inactifs_7j utilisateurs n'ont pas de connexion depuis 7 jours",
            'action' => 'Voir la liste',
            'link' => 'admin_users.php?filter=inactifs'
        ];
    }
    
    // Alerte: Taux de réussite faible
    if ($kpi_flashcards['taux_moyen_reussite'] < 60) {
        $alertes_automatiques[] = [
            'type' => 'danger',
            'icone' => 'fa-chart-line',
            'titre' => 'Performance faible',
            'message' => 'Le taux de réussite moyen des flashcards est inférieur à 60%',
            'action' => 'Analyser',
            'link' => 'admin_statistics.php#flashcards'
        ];
    }
    
    // Alerte: Sessions mentorat non terminées
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM sessions_mentorat 
        WHERE date_session < NOW() 
        AND statut NOT IN ('session_terminee', 'annulee')
    ");
    $sessions_en_retard = $stmt->fetch()['count'];
    if ($sessions_en_retard > 0) {
        $alertes_automatiques[] = [
            'type' => 'info',
            'icone' => 'fa-calendar-check',
            'titre' => 'Sessions en retard',
            'message' => "$sessions_en_retard sessions mentorat nécessitent une finalisation",
            'action' => 'Gérer',
            'link' => 'admin_mentorat.php?filter=retard'
        ];
    }
    
    // Alerte: Nouveaux utilisateurs à vérifier
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM utilisateurs 
        WHERE est_actif = 1 
        AND date_inscription >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        AND role = 'etudiant'
    ");
    $nouveaux_24h = $stmt->fetch()['count'];
    if ($nouveaux_24h > 0) {
        $alertes_automatiques[] = [
            'type' => 'success',
            'icone' => 'fa-user-plus',
            'titre' => 'Nouveaux étudiants',
            'message' => "$nouveaux_24h nouveaux étudiants inscrits aujourd'hui",
            'action' => 'Vérifier',
            'link' => 'admin_users.php?filter=new'
        ];
    }
    
    // === ANALYTICS AVANCÉS ===
    // Données pour graphiques
    $stmt = $pdo->query("
        SELECT 
            DATE(date_inscription) as date,
            COUNT(*) as count
        FROM utilisateurs 
        WHERE date_inscription >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND est_actif = 1
        GROUP BY DATE(date_inscription)
        ORDER BY date ASC
    ");
    $inscriptions_30j = $stmt->fetchAll();
    
    // Distribution par rôle
    $stmt = $pdo->query("
        SELECT 
            role,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM utilisateurs WHERE est_actif = 1), 1) as percentage
        FROM utilisateurs 
        WHERE est_actif = 1
        GROUP BY role
        ORDER BY count DESC
    ");
    $distribution_roles = $stmt->fetchAll();
    
    // Performance par filière
    $stmt = $pdo->query("
        SELECT 
            u.filiere,
            COUNT(DISTINCT u.id_utilisateur) as nb_etudiants,
            AVG(f.taux_reussite) as taux_reussite_moyen,
            COUNT(DISTINCT d.id_deck) as nb_decks,
            COUNT(DISTINCT sm.id_session) as nb_sessions_mentorat
        FROM utilisateurs u
        LEFT JOIN decks d ON u.id_utilisateur = d.createur_id
        LEFT JOIN flashcards f ON d.id_deck = f.deck_id
        LEFT JOIN sessions_mentorat sm ON u.id_utilisateur = sm.etudiant_id
        WHERE u.est_actif = 1 AND u.role = 'etudiant'
        GROUP BY u.filiere
        ORDER BY nb_etudiants DESC
    ");
    $performance_filieres = $stmt->fetchAll();
    
    $analytics_avances = [
        'inscriptions_30j' => $inscriptions_30j,
        'distribution_roles' => $distribution_roles,
        'performance_filieres' => $performance_filieres
    ];
    
    // === DONNÉES POUR TABLES AVEC FILTRES ===
    // Derniers utilisateurs inscrits
    $stmt = $pdo->query("
        SELECT id_utilisateur, noma, nom, prenom, role, promotion, filiere, date_inscription, derniere_connexion,
               CASE 
                   WHEN derniere_connexion >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'actif'
                   WHEN derniere_connexion >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'recent'
                   ELSE 'inactif'
               END as statut_activite
        FROM utilisateurs 
        WHERE est_actif = 1 
        ORDER BY date_inscription DESC 
        LIMIT 20
    ");
    $users = $stmt->fetchAll();
    
    // Cours du jour
    $stmt = $pdo->query("
        SELECT pc.*, m.nom as matiere_nom, m.filiere, p.nom as promotion_nom,
               CASE 
                   WHEN pc.date_seance < CURDATE() THEN 'passé'
                   WHEN pc.date_seance = CURDATE() THEN 'aujourdhui'
                   ELSE 'futur'
               END as statut_cours
        FROM planning_cours pc
        JOIN matieres m ON pc.matiere_id = m.id_matiere
        LEFT JOIN promotions p ON pc.promotion = p.id_promotion
        WHERE DATE(pc.date_seance) BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY pc.date_seance ASC, pc.heure_debut ASC
        LIMIT 15
    ");
    $courses = $stmt->fetchAll();
    
    // Dernières annonces
    $stmt = $pdo->query("
        SELECT a.*, CONCAT(u.prenom, ' ', u.nom) as auteur_nom,
               CASE 
                   WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'nouveau'
                   ELSE 'ancien'
               END as statut_annonce
        FROM annonces a
        JOIN utilisateurs u ON a.auteur_id = u.id_utilisateur
        WHERE a.est_active = 1
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $announcements = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur dashboard admin: " . $e->getMessage());
}

// Fonction pour calculer les trends
function calculateTrend($pdo, $table, $date_field, $days = 7, $where = '1=1') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN $date_field >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 END) as recent,
                COUNT(CASE WHEN $date_field BETWEEN DATE_SUB(NOW(), INTERVAL ? DAY) AND DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 END) as previous
            FROM $table
            WHERE $where
        ");
        $stmt->execute([$days, $days * 2, $days]);
        $data = $stmt->fetch();
        
        if ($data['previous'] > 0) {
            $trend = round((($data['recent'] - $data['previous']) / $data['previous']) * 100, 1);
            $direction = $trend >= 0 ? 'up' : 'down';
        } else {
            $trend = $data['recent'] > 0 ? 100 : 0;
            $direction = $data['recent'] > 0 ? 'up' : 'neutral';
        }
        
        return [
            'value' => abs($trend),
            'direction' => $direction,
            'recent' => $data['recent'],
            'previous' => $data['previous']
        ];
    } catch (PDOException $e) {
        return ['value' => 0, 'direction' => 'neutral', 'recent' => 0, 'previous' => 0];
    }
}

// Fonctions utilitaires
function getRoleIcon($role) {
    $icons = [
        'admin' => '👑',
        'tech' => '💻',
        'business' => '💼',
        'etudiant' => '🎓',
        'professeur' => '📚'
    ];
    return $icons[$role] ?? '👤';
}

function formatDateOSBT($date) {
    if (empty($date)) return 'N/A';
    $date_obj = new DateTime($date);
    $mois_fr = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return $date_obj->format('d') . ' ' . $mois_fr[$date_obj->format('n') - 1] . ' ' . $date_obj->format('Y');
}

function getStatusBadge($status) {
    $badges = [
        'actif' => '<span class="status-badge active">Actif</span>',
        'recent' => '<span class="status-badge recent">Récent</span>',
        'inactif' => '<span class="status-badge inactive">Inactif</span>',
        'nouveau' => '<span class="status-badge new">Nouveau</span>',
        'ancien' => '<span class="status-badge old">Ancien</span>',
        'aujourdhui' => '<span class="status-badge today">Aujourd\'hui</span>',
        'passé' => '<span class="status-badge past">Passé</span>',
        'futur' => '<span class="status-badge future">Futur</span>'
    ];
    return $badges[$status] ?? '<span class="status-badge">' . $status . '</span>';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - OSBT Connect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js" rel="stylesheet">
    <style>
        :root {
        /* ===== PALETTE HARMONISÉE AVEC STUDENT DASHBOARD ===== */
            /* Palette de couleurs douces */
            --notion-blue: #00bfa5;
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--notion-gray-50);
            color: var(--notion-gray-900);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
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

        .dashboard-wrapper {
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

        /* Header */
        .main-header {
            background: white;
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            color: var(--notion-green);
            font-size: 1.75rem;
            margin-bottom: 0.25rem;
        }

        .header-left p {
            color: var(--notion-gray-600);
            font-size: 0.9rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: var(--spacing-lg);
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--notion-gray-500);
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--notion-gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all var(--transition-base);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--notion-green);
            box-shadow: 0 0 0 3px rgba(0, 200, 83, 0.1);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            background: var(--gradient-blue);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            position: relative;
        }

        .status-dot {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 12px;
            height: 12px;
            background: var(--notion-green);
            border: 2px solid white;
            border-radius: var(--radius-full);
        }

        .user-info h4 {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.125rem;
        }

        .user-info p {
            font-size: 0.8rem;
            color: var(--notion-gray-500);
        }

        .notification-btn {
            position: relative;
            background: none;
            border: none;
            color: var(--notion-gray-600);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius-md);
            transition: all var(--transition-base);
        }

        .notification-btn:hover {
            background: var(--notion-gray-100);
            color: var(--notion-green);
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            width: 8px;
            height: 8px;
            background: var(--notion-red);
            border-radius: var(--radius-full);
        }

        /* ===== STATS CARDS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }

        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-green);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            background: var(--gradient-green);
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-weight: 600;
        }

        .trend-up {
            background: rgba(0, 200, 83, 0.1);
            color: var(--notion-green);
        }

        .trend-down {
            background: rgba(255, 82, 82, 0.1);
            color: var(--notion-red);
        }

        .trend-neutral {
            background: rgba(158, 158, 158, 0.1);
            color: var(--notion-gray-500);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--notion-green);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--notion-gray-600);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .stat-period {
            font-size: 0.75rem;
            color: var(--notion-gray-500);
        }

        /* ===== FILTRES RAPIDES ===== */
        .filters-toolbar {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-md);
        }

        .filter-group {
            display: flex;
            gap: var(--spacing-sm);
            flex-wrap: wrap;
            margin-bottom: var(--spacing-md);
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--notion-gray-300);
            background: white;
            border-radius: var(--radius-full);
            cursor: pointer;
            font-size: 0.85rem;
            color: var(--notion-gray-600);
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .filter-btn:hover {
            border-color: var(--notion-green);
            color: var(--notion-green);
        }

        .filter-btn.active {
            background: var(--notion-green);
            border-color: var(--notion-green);
            color: white;
        }

        .filter-stats {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            font-size: 0.85rem;
            color: var(--notion-gray-600);
        }

        /* ===== KPIs STRATÉGIQUES ===== */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }

        .kpi-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
        }

        .kpi-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--notion-gray-200);
        }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .kpi-icon.engagement {
            background: var(--gradient-blue);
        }

        .kpi-icon.flashcards {
            background: var(--gradient-purple);
        }

        .kpi-icon.mentorat {
            background: var(--gradient-yellow);
        }

        .kpi-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--notion-green);
        }

        .kpi-metrics {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }

        .metric {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: var(--notion-gray-100);
            border-radius: var(--radius-md);
        }

        .metric-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--notion-green);
        }

        .metric-label {
            font-size: 0.85rem;
            color: var(--notion-gray-600);
        }

        /* ===== TABLES AVEC FILTRES ===== */
        .table-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--spacing-xl);
            overflow: hidden;
        }

        .table-header {
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--notion-gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--spacing-md);
        }

        .table-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--notion-green);
        }

        .table-actions {
            display: flex;
            gap: var(--spacing-sm);
            flex-wrap: wrap;
        }

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

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--notion-gray-200);
        }

        th {
            background: var(--notion-gray-100);
            font-weight: 600;
            color: var(--notion-gray-800);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background: var(--notion-gray-100);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-badge.active {
            background: rgba(0, 200, 83, 0.1);
            color: var(--notion-green);
        }

        .status-badge.recent {
            background: rgba(66, 133, 244, 0.1);
            color: var(--notion-blue);
        }

        .status-badge.inactive {
            background: rgba(158, 158, 158, 0.1);
            color: var(--notion-gray-600);
        }

        .status-badge.new {
            background: rgba(123, 31, 162, 0.1);
            color: var(--notion-purple);
        }

        .status-badge.today {
            background: rgba(255, 214, 0, 0.1);
            color: var(--notion-yellow);
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .role-badge.admin {
            background: rgba(255, 82, 82, 0.1);
            color: var(--notion-red);
        }

        .role-badge.tech {
            background: rgba(66, 133, 244, 0.1);
            color: var(--notion-blue);
        }

        .role-badge.business {
            background: rgba(255, 214, 0, 0.1);
            color: var(--notion-yellow);
        }

        .role-badge.etudiant {
            background: rgba(0, 200, 83, 0.1);
            color: var(--notion-green);
        }

        /* ===== ALERTES ===== */
        .alertes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
        }

        .alerte-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            border-left: 4px solid;
            transition: all var(--transition-base);
        }

        .alerte-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .alerte-card.warning {
            border-left-color: var(--notion-yellow);
        }

        .alerte-card.danger {
            border-left-color: var(--notion-red);
        }

        .alerte-card.info {
            border-left-color: var(--notion-blue);
        }

        .alerte-card.success {
            border-left-color: var(--notion-green);
        }

        .alerte-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
        }

        .alerte-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }

        .alerte-card.warning .alerte-icon {
            background: var(--notion-yellow);
        }

        .alerte-card.danger .alerte-icon {
            background: var(--notion-red);
        }

        .alerte-card.info .alerte-icon {
            background: var(--notion-blue);
        }

        .alerte-card.success .alerte-icon {
            background: var(--notion-green);
        }

        .alerte-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--notion-gray-800);
        }

        .alerte-message {
            color: var(--notion-gray-600);
            margin-bottom: var(--spacing-md);
            line-height: 1.5;
        }

        /* ===== GRAPHIQUES ===== */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }

        .chart-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
        }

        .chart-header {
            margin-bottom: var(--spacing-lg);
        }

        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--notion-green);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .chart-container {
            height: 250px;
            position: relative;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-toggle.mobile {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .main-header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
            
            .search-box {
                width: 100%;
            }
            
            .stats-grid,
            .kpi-grid,
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .table-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        @keyframes slideIn {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease;
        }

        .slide-in {
            animation: slideIn 0.3s ease;
        }

        /* ===== NOTIFICATIONS ===== */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
            max-width: 350px;
        }

        .notification {
            background: white;
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: flex-start;
            gap: var(--spacing-md);
            animation: slideIn var(--transition-base);
            border-left: 4px solid var(--notion-green);
        }

        .notification-icon {
            color: var(--notion-green);
            font-size: 1.25rem;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--notion-gray-800);
        }

        .notification-message {
            font-size: 0.85rem;
            color: var(--notion-gray-600);
            margin-bottom: 0.5rem;
        }

        .notification-time {
            font-size: 0.75rem;
            color: var(--notion-gray-500);
        }

        /* ===== UTILITY CLASSES ===== */
        .text-success { color: var(--notion-green); }
        .text-warning { color: var(--notion-yellow); }
        .text-danger { color: var(--notion-red); }
        .text-info { color: var(--notion-blue); }
        .text-primary { color: var(--notion-green); }
        
        .bg-success { background: var(--notion-green); }
        .bg-warning { background: var(--notion-yellow); }
        .bg-danger { background: var(--notion-red); }
        .bg-info { background: var(--notion-blue); }
        .bg-primary { background: var(--notion-green); }
        
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
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
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
                    <a href="admin_dashboard.php" class="nav-link active">
                        <span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="admin_analytics.php" class="nav-link" onclick="showPageNotAvailable(event, 'La page Analytics n\'est pas encore disponible.')">
                        <span class="nav-icon"><i class="fas fa-chart-line"></i></span>
                        <span class="nav-text">Analytics</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="admin_reports.php" class="nav-link" onclick="showPageNotAvailable(event, 'La page Rapports n\'est pas encore disponible.')">
                        <span class="nav-icon"><i class="fas fa-file-alt"></i></span>
                        <span class="nav-text">Rapports</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="admin_users.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-users"></i></span>
                        <span class="nav-text">Utilisateurs</span>
                        <span class="nav-badge"><?= $total_users ?></span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="admin_courses.php" class="nav-link" onclick="showPageNotAvailable(event, 'La page Gestion des Cours n\'est pas encore disponible.')">
                        <span class="nav-icon"><i class="fas fa-book"></i></span>
                        <span class="nav-text">Cours</span>
                        <span class="nav-badge"><?= $total_courses ?></span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="admin_announcements.php" class="nav-link" onclick="showPageNotAvailable(event, 'La page Gestion des Annonces n\'est pas encore disponible.')">
                        <span class="nav-icon"><i class="fas fa-bullhorn"></i></span>
                        <span class="nav-text">Annonces</span>
                        <span class="nav-badge"><?= $total_announcements ?></span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="admin_mentorat.php" class="nav-link" onclick="showPageNotAvailable(event, 'La page Gestion du Mentorat n\'est pas encore disponible.')">
                        <span class="nav-icon"><i class="fas fa-handshake"></i></span>
                        <span class="nav-text">Mentorat</span>
                        <span class="nav-badge"><?= $kpi_mentorat['total_sessions_mentorat'] ?? 0 ?></span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="admin_settings.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-cog"></i></span>
                        <span class="nav-text">Paramètres</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="admin_backup.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-database"></i></span>
                        <span class="nav-text">Backup</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="admin_logs.php" class="nav-link">
                        <span class="nav-icon"><i class="fas fa-history"></i></span>
                        <span class="nav-text">Logs</span>
                        <span class="nav-badge alert">5</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link" onclick="logout()">
                        <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                        <span class="nav-text">Déconnexion</span>
                    </a>
                </div>
            </nav>
            
            <!-- Avatar utilisateur en bas -->
            <div class="user-avatar-container">
                <div class="user-avatar" onclick="toggleUserMenu()">
                    <?= strtoupper(substr($_SESSION['user_prenom'], 0, 1)); ?>
                </div>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <!-- Header -->
            <header class="main-header fade-in">
                <div class="header-left">
                    <h1>Dashboard Admin</h1>
                    <p>Bienvenue, <?= htmlspecialchars($user_prenom . ' ' . $user_nom) ?> • Dernière mise à jour: <?= date('H:i') ?></p>
                </div>
                <div class="header-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="search-input" placeholder="Rechercher utilisateurs, cours...">
                    </div>
                    <button class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge"></span>
                    </button>
                    <div class="user-menu">
                        <div class="user-avatar">
                            <?= strtoupper(substr($user_prenom, 0, 1) . substr($user_nom, 0, 1)) ?>
                            <span class="status-dot"></span>
                        </div>
                        <div class="user-info">
                            <h4><?= htmlspecialchars($user_prenom . ' ' . $user_nom) ?></h4>
                            <p>Administrateur</p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Stats Cards -->
            <div class="stats-grid fade-in">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-trend trend-<?= $trends['users']['direction'] ?>">
                            <i class="fas fa-arrow-<?= $trends['users']['direction'] == 'up' ? 'up' : 'down' ?>"></i>
                            <?= $trends['users']['value'] ?>%
                        </div>
                    </div>
                    <div class="stat-value"><?= $stats['total_users'] ?></div>
                    <div class="stat-label">Total utilisateurs</div>
                    <div class="stat-period">+<?= $trends['users']['recent'] ?> nouveaux (7j)</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--notion-green), #66BB6A);">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-trend trend-<?= $trends['students']['direction'] ?>">
                            <i class="fas fa-arrow-<?= $trends['students']['direction'] == 'up' ? 'up' : 'down' ?>"></i>
                            <?= $trends['students']['value'] ?>%
                        </div>
                    </div>
                    <div class="stat-value"><?= $stats['total_students'] ?></div>
                    <div class="stat-label">Étudiants</div>
                    <div class="stat-period">+<?= $trends['students']['recent'] ?> nouveaux (7j)</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--notion-yellow), #FFA726);">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-trend trend-<?= $trends['mentors']['direction'] ?>">
                            <i class="fas fa-arrow-<?= $trends['mentors']['direction'] == 'up' ? 'up' : 'down' ?>"></i>
                            <?= $trends['mentors']['value'] ?>%
                        </div>
                    </div>
                    <div class="stat-value"><?= $stats['total_mentors'] ?></div>
                    <div class="stat-label">Mentors</div>
                    <div class="stat-period">+<?= $trends['mentors']['recent'] ?> nouveaux (7j)</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--notion-purple), #BA68C8);">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-trend trend-<?= $trends['courses']['direction'] ?>">
                            <i class="fas fa-arrow-<?= $trends['courses']['direction'] == 'up' ? 'up' : 'down' ?>"></i>
                            <?= $trends['courses']['value'] ?>%
                        </div>
                    </div>
                    <div class="stat-value"><?= $stats['total_courses'] ?></div>
                    <div class="stat-label">Cours totaux</div>
                    <div class="stat-period">+<?= $trends['courses']['recent'] ?> nouveaux (7j)</div>
                </div>
            </div>

            <!-- Alertes Automatiques -->
            <?php if(!empty($alertes_automatiques)): ?>
            <div class="fade-in mb-2">
                <h2 class="table-title mb-1"><i class="fas fa-exclamation-triangle"></i> Alertes Système</h2>
                <div class="alertes-grid">
                    <?php foreach($alertes_automatiques as $alerte): ?>
                    <div class="alerte-card <?= $alerte['type'] ?>">
                        <div class="alerte-header">
                            <div class="alerte-icon">
                                <i class="fas <?= $alerte['icone'] ?>"></i>
                            </div>
                            <div class="alerte-title"><?= $alerte['titre'] ?></div>
                        </div>
                        <div class="alerte-message"><?= $alerte['message'] ?></div>
                        <a href="<?= $alerte['link'] ?? '#' ?>" class="btn btn-sm btn-<?= $alerte['type'] ?>">
                            <?= $alerte['action'] ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- KPIs Stratégiques -->
            <div class="fade-in mb-2">
                <h2 class="table-title mb-1"><i class="fas fa-chart-line"></i> KPIs Stratégiques</h2>
                <div class="kpi-grid">
                    <!-- Engagement -->
                    <div class="kpi-card">
                        <div class="kpi-header">
                            <div class="kpi-icon engagement">
                                <i class="fas fa-users-cog"></i>
                            </div>
                            <div class="kpi-title">Engagement</div>
                        </div>
                        <div class="kpi-metrics">
                            <div class="metric">
                                <span class="metric-label">Actifs aujourd'hui</span>
                                <span class="metric-value"><?= $kpi_engagement['utilisateurs_actifs_jour'] ?? 0 ?></span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Actifs cette semaine</span>
                                <span class="metric-value"><?= $kpi_engagement['utilisateurs_actifs_semaine'] ?? 0 ?></span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Actifs ce mois</span>
                                <span class="metric-value"><?= $kpi_engagement['utilisateurs_actifs_mois'] ?? 0 ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Flashcards -->
                    <div class="kpi-card">
                        <div class="kpi-header">
                            <div class="kpi-icon flashcards">
                                <i class="fas fa-brain"></i>
                            </div>
                            <div class="kpi-title">Flashcards</div>
                        </div>
                        <div class="kpi-metrics">
                            <div class="metric">
                                <span class="metric-label">Decks créés</span>
                                <span class="metric-value"><?= $kpi_flashcards['total_decks'] ?? 0 ?></span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Flashcards totales</span>
                                <span class="metric-value"><?= $kpi_flashcards['total_flashcards'] ?? 0 ?></span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Taux réussite moyen</span>
                                <span class="metric-value"><?= round($kpi_flashcards['taux_moyen_reussite'] ?? 0) ?>%</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mentorat -->
                    <div class="kpi-card">
                        <div class="kpi-header">
                            <div class="kpi-icon mentorat">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <div class="kpi-title">Mentorat</div>
                        </div>
                        <div class="kpi-metrics">
                            <div class="metric">
                                <span class="metric-label">Sessions totales</span>
                                <span class="metric-value"><?= $kpi_mentorat['total_sessions_mentorat'] ?? 0 ?></span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Sessions terminées</span>
                                <span class="metric-value"><?= $kpi_mentorat['sessions_terminees'] ?? 0 ?></span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Durée moyenne</span>
                                <span class="metric-value"><?= round($kpi_mentorat['duree_moyenne_sessions'] ?? 0) ?>min</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monitoring Temps Réel -->
            <div class="table-container fade-in mb-2">
                <div class="table-header">
                    <h3 class="table-title"><i class="fas fa-satellite-dish"></i> Monitoring Temps Réel</h3>
                    <div class="d-flex align-center gap-1">
                        <span class="text-danger"><i class="fas fa-circle"></i> LIVE</span>
                        <span class="text-muted"><?= date('H:i:s') ?></span>
                    </div>
                </div>
                <div class="p-2">
                    <div class="d-flex justify-between gap-2 flex-wrap">
                        <div class="text-center">
                            <div class="stat-value text-primary"><?= $monitoring_connexions['utilisateurs_actifs_5min'] ?? 0 ?></div>
                            <div class="stat-label">En ligne maintenant</div>
                        </div>
                        <div class="text-center">
                            <div class="stat-value"><?= $monitoring_connexions['utilisateurs_actifs_1h'] ?? 0 ?></div>
                            <div class="stat-label">Dernière heure</div>
                        </div>
                        <div class="text-center">
                            <div class="stat-value"><?= $monitoring_cours['cours_en_cours'] ?? 0 ?></div>
                            <div class="stat-label">Cours en cours</div>
                        </div>
                        <div class="text-center">
                            <div class="stat-value"><?= $monitoring_cours['matieres_actives'] ?? 0 ?></div>
                            <div class="stat-label">Matières actives</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Graphiques -->
            <div class="charts-grid fade-in mb-2">
                <!-- Inscriptions 30 jours -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title"><i class="fas fa-chart-line"></i> Inscriptions (30 jours)</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="inscriptionsChart"></canvas>
                    </div>
                </div>
                
                <!-- Distribution par rôle -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title"><i class="fas fa-chart-pie"></i> Distribution par rôle</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="rolesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Derniers Utilisateurs avec Filtres -->
            <div class="table-container fade-in mb-2">
                <div class="table-header">
                    <h3 class="table-title"><i class="fas fa-users"></i> Derniers utilisateurs</h3>
                    <div class="table-actions">
                        <div class="filter-group">
                            <button class="filter-btn active" data-filter="all">Tous</button>
                            <button class="filter-btn" data-filter="etudiant">Étudiants</button>
                            <button class="filter-btn" data-filter="mentor">Mentors</button>
                            <button class="filter-btn" data-filter="admin">Admins</button>
                            <button class="filter-btn" data-filter="actif">Actifs</button>
                            <button class="filter-btn" data-filter="new">Nouveaux</button>
                        </div>
                        <a href="#" class="btn btn-primary" onclick="showPageNotAvailable(event, 'La page Gestion des Utilisateurs n\'est pas encore disponible.')">
                            <i class="fas fa-plus"></i> Gérer
                        </a>
                    </div>
                </div>
                <div class="filters-toolbar">
                    <div class="filter-stats">
                        <span><?= count($users) ?> utilisateurs</span>
                        <span><?= $total_students ?> étudiants</span>
                        <span><?= $total_mentors ?> mentors</span>
                    </div>
                </div>
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th>NOMA</th>
                            <th>Nom</th>
                            <th>Rôle</th>
                            <th>Promotion</th>
                            <th>Activité</th>
                            <th>Inscription</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr data-role="<?= $user['role'] ?>" data-status="<?= $user['statut_activite'] ?>" data-new="<?= date('Y-m-d', strtotime($user['date_inscription'])) == date('Y-m-d') ? 'yes' : 'no' ?>">
                            <td><?= htmlspecialchars($user['noma'] ?? 'N/A') ?></td>
                            <td>
                                <div><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
                                <small class="text-muted"><?= $user['email'] ?? '' ?></small>
                            </td>
                            <td>
                                <span class="role-badge <?= $user['role'] ?>">
                                    <?= getRoleIcon($user['role']) ?> <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($user['promotion'] ?? 'N/A') ?></td>
                            <td><?= getStatusBadge($user['statut_activite']) ?></td>
                            <td><?= formatDateOSBT($user['date_inscription']) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-primary" title="Éditer">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-secondary" title="Voir profil">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Cours avec Filtres -->
            <div class="table-container fade-in mb-2">
                <div class="table-header">
                    <h3 class="table-title"><i class="fas fa-calendar"></i> Cours à venir</h3>
                    <div class="table-actions">
                        <div class="filter-group">
                            <button class="filter-btn active" data-filter="all">Tous</button>
                            <button class="filter-btn" data-filter="today">Aujourd'hui</button>
                            <button class="filter-btn" data-filter="tech">Tech</button>
                            <button class="filter-btn" data-filter="business">Business</button>
                        </div>
                        <a href="#" class="btn btn-primary" onclick="showPageNotAvailable(event, 'La page Planification n\'est pas encore disponible.')">
                            <i class="fas fa-calendar-plus"></i> Planifier
                        </a>
                    </div>
                </div>
                <table id="coursesTable">
                    <thead>
                        <tr>
                            <th>Matière</th>
                            <th>Filière</th>
                            <th>Promotion</th>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Salle</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($courses as $course): ?>
                        <tr data-filiere="<?= $course['filiere'] ?>" data-date="<?= $course['date_seance'] ?>" data-status="<?= $course['statut_cours'] ?>">
                            <td><?= htmlspecialchars($course['matiere_nom']) ?></td>
                            <td><?= htmlspecialchars($course['filiere']) ?></td>
                            <td><?= htmlspecialchars($course['promotion_nom'] ?? $course['promotion']) ?></td>
                            <td><?= date('d/m/Y', strtotime($course['date_seance'])) ?></td>
                            <td><?= $course['heure_debut'] ?> - <?= $course['heure_fin'] ?></td>
                            <td><?= htmlspecialchars($course['salle'] ?? 'N/A') ?></td>
                            <td><?= getStatusBadge($course['statut_cours']) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Dernières Annonces -->
            <div class="table-container fade-in">
                <div class="table-header">
                    <h3 class="table-title"><i class="fas fa-bullhorn"></i> Dernières annonces</h3>
                    <div class="table-actions">
                        <div class="filter-group">
                            <button class="filter-btn active" data-filter="all">Toutes</button>
                            <button class="filter-btn" data-filter="new">Nouvelles</button>
                            <button class="filter-btn" data-filter="urgent">Urgentes</button>
                        </div>
                        <a href="#" class="btn btn-primary" onclick="showPageNotAvailable(event, 'La page Gestion des Annonces n\'est pas encore disponible.')">
                            <i class="fas fa-plus"></i> Nouvelle
                        </a>
                    </div>
                </div>
                <table id="announcementsTable">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Auteur</th>
                            <th>Cible</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($announcements as $announcement): ?>
                        <tr data-status="<?= $announcement['statut_annonce'] ?>" data-urgent="<?= $announcement['priorite'] == 'urgent' ? 'yes' : 'no' ?>">
                            <td><?= htmlspecialchars($announcement['titre']) ?></td>
                            <td><?= htmlspecialchars($announcement['auteur_nom']) ?></td>
                            <td><?= htmlspecialchars($announcement['cible']) ?></td>
                            <td><?= formatDateOSBT($announcement['created_at']) ?></td>
                            <td><?= getStatusBadge($announcement['statut_annonce']) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Notifications Container -->
    <div class="notification-container" id="notificationContainer"></div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // === SIDEBAR TOGGLE ===
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        }

        // === FILTRES DYNAMIQUES ===
        document.addEventListener('DOMContentLoaded', function() {
            // Filtrage des utilisateurs
            document.querySelectorAll('#usersTable .filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    // Mettre à jour les boutons actifs
                    document.querySelectorAll('#usersTable .filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const filter = this.dataset.filter;
                    const rows = document.querySelectorAll('#usersTable tbody tr');
                    
                    rows.forEach(row => {
                        if (filter === 'all') {
                            row.style.display = '';
                        } else if (filter === 'etudiant') {
                            row.style.display = row.dataset.role === 'etudiant' ? '' : 'none';
                        } else if (filter === 'mentor') {
                            row.style.display = ['tech', 'business'].includes(row.dataset.role) ? '' : 'none';
                        } else if (filter === 'admin') {
                            row.style.display = row.dataset.role === 'admin' ? '' : 'none';
                        } else if (filter === 'actif') {
                            row.style.display = row.dataset.status === 'actif' ? '' : 'none';
                        } else if (filter === 'new') {
                            row.style.display = row.dataset.new === 'yes' ? '' : 'none';
                        }
                    });
                });
            });

            // Filtrage des cours
            document.querySelectorAll('#coursesTable .filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('#coursesTable .filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const filter = this.dataset.filter;
                    const rows = document.querySelectorAll('#coursesTable tbody tr');
                    const today = new Date().toISOString().split('T')[0];
                    
                    rows.forEach(row => {
                        if (filter === 'all') {
                            row.style.display = '';
                        } else if (filter === 'today') {
                            row.style.display = row.dataset.date === today ? '' : 'none';
                        } else if (filter === 'tech') {
                            row.style.display = row.dataset.filiere === 'tech' ? '' : 'none';
                        } else if (filter === 'business') {
                            row.style.display = row.dataset.filiere === 'business' ? '' : 'none';
                        }
                    });
                });
            });

            // Filtrage des annonces
            document.querySelectorAll('#announcementsTable .filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('#announcementsTable .filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const filter = this.dataset.filter;
                    const rows = document.querySelectorAll('#announcementsTable tbody tr');
                    
                    rows.forEach(row => {
                        if (filter === 'all') {
                            row.style.display = '';
                        } else if (filter === 'new') {
                            row.style.display = row.dataset.status === 'nouveau' ? '' : 'none';
                        } else if (filter === 'urgent') {
                            row.style.display = row.dataset.urgent === 'yes' ? '' : 'none';
                        }
                    });
                });
            });

            // Recherche en temps réel
            const searchInput = document.querySelector('.search-input');
            searchInput?.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                
                // Rechercher dans toutes les tables
                document.querySelectorAll('tbody tr').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });

            // === GRAPHIQUES ===
            // Graphique des inscriptions
            const inscriptionsCtx = document.getElementById('inscriptionsChart');
            if (inscriptionsCtx) {
                const labels = <?= json_encode(array_column($inscriptions_30j, 'date')) ?>;
                const data = <?= json_encode(array_column($inscriptions_30j, 'count')) ?>;
                
                new Chart(inscriptionsCtx, {
                    type: 'line',
                    data: {
                        labels: labels.map(date => new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })),
                        datasets: [{
                            label: 'Inscriptions',
                            data: data,
                            borderColor: '#00C853',
                            backgroundColor: 'rgba(0, 200, 83, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }

            // Graphique des rôles
            const rolesCtx = document.getElementById('rolesChart');
            if (rolesCtx) {
                const labels = <?= json_encode(array_column($distribution_roles, 'role')) ?>;
                const data = <?= json_encode(array_column($distribution_roles, 'percentage')) ?>;
                const colors = {
                    'etudiant': '#4CAF50',
                    'tech': '#2196F3',
                    'business': '#FF9800',
                    'admin': '#F44336',
                    'professeur': '#9C27B0'
                };
                
                new Chart(rolesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: labels.map(role => role.charAt(0).toUpperCase() + role.slice(1)),
                        datasets: [{
                            data: data,
                            backgroundColor: labels.map(role => colors[role] || '#607D8B'),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }

            // === NOTIFICATIONS ===
            class NotificationSystem {
                constructor() {
                    this.container = document.getElementById('notificationContainer');
                    this.notifications = [];
                    this.init();
                }
                
                init() {
                    // Simulation de notifications temps réel
                    setInterval(() => {
                        if (Math.random() > 0.8) {
                            this.show('Nouvel utilisateur', 'Un nouvel utilisateur s\'est inscrit', 'success');
                        }
                        if (Math.random() > 0.9) {
                            this.show('Alerte système', 'Taux de réussite en baisse', 'warning');
                        }
                    }, 30000);
                }
                
                show(title, message, type = 'info') {
                    const notification = document.createElement('div');
                    notification.className = `notification notification-${type}`;
                    notification.innerHTML = `
                        <div class="notification-icon">
                            <i class="fas fa-${this.getIcon(type)}"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">${title}</div>
                            <div class="notification-message">${message}</div>
                            <div class="notification-time">${new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}</div>
                        </div>
                        <button class="btn btn-sm btn-secondary" onclick="this.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    
                    this.container.appendChild(notification);
                    
                    // Auto-remove après 5 secondes
                    setTimeout(() => {
                        notification.style.opacity = '0';
                        notification.style.transform = 'translateX(100%)';
                        setTimeout(() => notification.remove(), 300);
                    }, 5000);
                    
                    // Mise à jour du badge
                    this.updateBadge();
                }
                
                getIcon(type) {
                    const icons = {
                        'success': 'check-circle',
                        'warning': 'exclamation-triangle',
                        'danger': 'exclamation-circle',
                        'info': 'info-circle'
                    };
                    return icons[type] || 'bell';
                }
                
                updateBadge() {
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        badge.style.display = 'block';
                    }
                }
            }

            // Initialiser le système de notifications
            new NotificationSystem();

            // === EXPORT DE DONNÉES ===
            window.exportData = function(type) {
                let csvContent = '';
                
                switch(type) {
                    case 'users':
                        csvContent = "NOMA,Nom,Prénom,Rôle,Promotion,Date d'inscription\n";
                        <?php foreach($users as $user): ?>
                        csvContent += "<?= $user['noma'] ?>,<?= $user['nom'] ?>,<?= $user['prenom'] ?>,<?= $user['role'] ?>,<?= $user['promotion'] ?>,<?= $user['date_inscription'] ?>\n";
                        <?php endforeach; ?>
                        break;
                        
                    case 'courses':
                        csvContent = "Matière,Filière,Promotion,Date,Heure,Salle\n";
                        <?php foreach($courses as $course): ?>
                        csvContent += "<?= $course['matiere_nom'] ?>,<?= $course['filiere'] ?>,<?= $course['promotion_nom'] ?? $course['promotion'] ?>,<?= $course['date_seance'] ?>,<?= $course['heure_debut'] ?>,<?= $course['salle'] ?>\n";
                        <?php endforeach; ?>
                        break;
                }
                
                const blob = new Blob([csvContent], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `export_${type}_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                
                // Notification
                new NotificationSystem().show('Export réussi', `Les données ${type} ont été exportées`, 'success');
            };

            // === ACTIONS RAPIDES ===
            window.createAnnouncement = function() {
                window.location.href = 'admin_announcements.php?action=create';
            };

            window.addUser = function() {
                window.location.href = 'admin_users.php?action=create';
            };

            window.runBackup = function() {
                if (confirm('Voulez-vous exécuter une sauvegarde de la base de données ?')) {
                    fetch('admin_backup.php', { method: 'POST' })
                        .then(response => response.json())
                        .then(data => {
                            new NotificationSystem().show('Backup réussi', data.message, 'success');
                        })
                        .catch(error => {
                            new NotificationSystem().show('Erreur', 'Échec du backup', 'danger');
                        });
                }
            };

            // === ANIMATIONS AU CHARGEMENT ===
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // === REFRESH AUTOMATIQUE ===
            let refreshInterval = setInterval(() => {
                // Actualiser les données toutes les minutes
                fetch('admin_dashboard.php?refresh=1')
                    .then(response => response.text())
                    .then(html => {
                        // Mettre à jour les éléments dynamiques
                        document.querySelector('.stat-card .stat-value').innerHTML = '<?= $total_users ?>';
                        // ... autres mises à jour
                    })
                    .catch(error => console.error('Erreur de rafraîchissement:', error));
            }, 60000); // 60 secondes

            // Nettoyer l'intervalle à la déconnexion
            window.addEventListener('beforeunload', () => {
                clearInterval(refreshInterval);
            });
        });

        // === RECHERCHE AVANCÉE ===
        function searchAdvanced() {
            const searchTerm = document.querySelector('.search-input').value.toLowerCase();
            const tables = ['usersTable', 'coursesTable', 'announcementsTable'];
            
            tables.forEach(tableId => {
                const table = document.getElementById(tableId);
                if (table) {
                    const rows = table.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        const rowText = row.textContent.toLowerCase();
                        if (searchTerm === '' || rowText.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                }
            });
        }

        // === MODE DARK/LIGHT ===
        function toggleTheme() {
            const html = document.documentElement;
            if (html.getAttribute('data-theme') === 'dark') {
                html.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
            } else {
                html.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
            }
        }

        // Charger le thème sauvegardé
        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        });

        // Fonction pour les pages non disponibles
        function showPageNotAvailable(event, message = 'Cette page n\'est pas encore disponible.') {
            event.preventDefault();
            alert(message);
            return false;
        }
        
        // Fonction de déconnexion
        function logout() {
            if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
                // Détruire la session
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>