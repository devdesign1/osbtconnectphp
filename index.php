<?php
// ============================================
// CONFIGURATION SÉCURITÉ INTERNE OSBT
// ============================================

// Mode strict pour PHP 8+
declare(strict_types=1);

// Configuration d'environnement interne
define('OSBT_ENVIRONMENT', 'internal'); // internal | testing | production
define('OSBT_VERSION', '1.0.0');
define('OSBT_ACCESS_DOMAIN', '@osbt.education');

// Headers de sécurité pour environnement interne
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Configuration de la session sécurisée
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Vérifier si l'utilisateur est connecté via l'infrastructure OSBT
$isAuthenticated = isset($_SESSION['osbt_user_id']);
$userRole = $_SESSION['osbt_user_role'] ?? 'guest';

// ============================================
// FONCTIONS SPÉCIFIQUES OSBT
// ============================================

require_once __DIR__ . '/config/database.php';

/**
 * Fonction pour obtenir les statistiques OSBT (version sécurisée interne)
 */
function getOSBTStatistics(PDO $pdo): array {
    // Valeurs par défaut pour l'environnement OSBT
    $defaultStats = [
        'matieres' => 12,    // Nombre de matières BBA/MSc
        'mentors' => 8,      // Anciens élèves actifs
        'flashcards' => 50, // Flashcards certifiées
        'sessions' => 35,    // Sessions mentorat ce mois
        'etudiants' => 140   // Étudiants actifs OSBT
    ];
    
    try {
        // Requêtes spécifiques à la structure OSBT
        $queries = [
            'matieres' => 'SELECT COUNT(*) FROM matieres WHERE est_active = 1 AND programme IN ("Management", "MSc", "MBA")',
            'mentors' => 'SELECT COUNT(*) FROM utilisateurs WHERE role = "mentor" AND est_actif = 1 AND annee_promotion >= YEAR(CURDATE()) - 5',
            'flashcards' => 'SELECT COUNT(*) FROM flashcards WHERE est_verifie = 1 AND programme = (SELECT programme FROM utilisateurs WHERE id = :user_id)',
            'sessions' => 'SELECT COUNT(*) FROM sessions_mentorat WHERE statut = "terminee" AND DATE(date_session) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
            'etudiants' => 'SELECT COUNT(*) FROM utilisateurs WHERE role = "etudiant" AND est_actif = 1 AND annee_inscription >= YEAR(CURDATE()) - 3'
        ];
        
        $stats = [];
        foreach ($queries as $key => $query) {
            if ($key === 'flashcards' && isset($_SESSION['osbt_user_id'])) {
                $stmt = $pdo->prepare($query);
                $stmt->bindValue(':user_id', $_SESSION['osbt_user_id'], PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $pdo->query($query);
            }
            
            $result = $stmt->fetchColumn();
            $stats[$key] = $result ? (int)$result : $defaultStats[$key];
        }
        
        return $stats;
        
    } catch (PDOException $e) {
        // Log interne OSBT sans exposer d'informations
        error_log("[OSBT STATS ERROR] " . date('Y-m-d H:i:s') . " - " . $e->getMessage());
        return $defaultStats;
    }
}

/**
 * Récupère les annonces spécifiques à l'utilisateur OSBT
 */
function getOSBTAnnouncements(PDO $pdo, int $limit = 5): array {
    if (!$pdo) return [];
    
    try {
        $query = 'SELECT a.*, u.prenom, u.nom, u.programme, u.annee_promotion 
                 FROM annonces a 
                 JOIN utilisateurs u ON a.auteur_id = u.id_utilisateur 
                 WHERE a.est_active = 1 
                 AND (a.date_fin IS NULL OR a.date_fin >= NOW())
                 AND (
                    a.cible = "tous" 
                    OR a.cible = :programme 
                    OR (a.cible = "promo_specifique" AND FIND_IN_SET(:annee_promotion, a.promos_ciblees))
                 )
                 ORDER BY 
                     CASE a.importance 
                         WHEN "urgent" THEN 1 
                         WHEN "important" THEN 2 
                         ELSE 3 
                     END,
                     a.date_debut DESC 
                 LIMIT :limit';
        
        $stmt = $pdo->prepare($query);
        
        // Récupération du profil OSBT de l'utilisateur
        $userProgramme = $_SESSION['osbt_programme'] ?? 'BBA';
        $userAnnee = $_SESSION['osbt_annee'] ?? '2024';
        
        $stmt->bindValue(':programme', $userProgramme, PDO::PARAM_STR);
        $stmt->bindValue(':annee_promotion', $userAnnee, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("[OSBT ANNONCES ERROR] " . $e->getMessage());
        return [];
    }
}

/**
 * Fonction d'échappement spécifique OSBT
 */
function osbt_escape(string $input, int $maxLength = 200): string {
    $input = trim($input);
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    if (mb_strlen($input) > $maxLength) {
        $input = mb_substr($input, 0, $maxLength) . '...';
    }
    
    return $input;
}

/**
 * Formate la date selon les standards OSBT
 */
function osbt_format_date(string $date): string {
    $dateTime = new DateTime($date);
    $now = new DateTime();
    $diff = $now->diff($dateTime);
    
    if ($diff->days === 0) {
        return 'Aujourd\'hui à ' . $dateTime->format('H:i');
    } elseif ($diff->days === 1) {
        return 'Hier à ' . $dateTime->format('H:i');
    } elseif ($diff->days < 7) {
        return 'Il y a ' . $diff->days . ' jours';
    } else {
        return $dateTime->format('d/m/Y');
    }
}

/**
 * Détermine la classe d'importance selon les standards OSBT
 */
function getOSBTImportanceClass(string $importance): string {
    $importanceClasses = [
        'urgent' => 'annonce-urgent',
        'important' => 'annonce-important',
        'info' => 'annonce-info',
        'pedagogique' => 'annonce-pedagogique',
        'administratif' => 'annonce-administratif'
    ];
    
    return $importanceClasses[strtolower($importance)] ?? 'annonce-info';
}

// ============================================
// RÉCUPÉRATION DES DONNÉES OSBT
// ============================================

try {
    $stats = getOSBTStatistics($pdo);
    $annonces = getOSBTAnnouncements($pdo);
    $currentYear = date('Y');
    
    // Données spécifiques OSBT
    $programmes = ['Management', 'Informatique', 'Marketing', 'Infograplie'];
    $promotions = ['2023', '2024', '2025'];
    
} catch (Exception $e) {
    // Mode dégradé pour l'environnement interne
    error_log("[OSBT CONNECT ERROR] " . $e->getMessage());
    $stats = [
        'matieres' => 12,
        'mentors' => 8,
        'flashcards' => 150,
        'sessions' => 35,
        'etudiants' => 240
    ];
    $annonces = [];
    $currentYear = date('Y');
    $programmes = ['BBA', 'MSc Finance', 'MSc Marketing', 'MBA'];
    $promotions = ['2023', '2024', '2025'];
}

// ============================================
// DÉBUT DU HTML - PLATEFORME INTERNE OSBT
// ============================================
?>
<!DOCTYPE html>
<html lang="fr" data-environment="<?= OSBT_ENVIRONMENT ?>" data-version="<?= OSBT_VERSION ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>OSBT Connect | Plateforme Interne Étudiants</title>
    
    <!-- Métadonnées spécifiques OSBT -->
    <meta name="description" content="Plateforme interne OSBT - Accès réservé aux étudiants, anciens élèves et personnel de l'Omnia School of Business & Technology">
    <meta name="author" content="Omnia School of Business & Technology">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Favicon OSBT -->
    <link rel="icon" href="assets/favicon-osbt.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon-osbt.png">
    
    <!-- Fonts & Icons (chargement optimisé) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@500;700&display=swap" 
          rel="stylesheet" 
          media="print" 
          onload="this.media='all'">
    
    <link rel="stylesheet" 
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
          integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
          crossorigin="anonymous"
          referrerpolicy="no-referrer">
    
    <!-- CSS d'animation (chargement différé) -->
    <link rel="preload" href="https://unpkg.com/aos@2.3.1/dist/aos.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css"></noscript>
    
    <style>
        /* ========== VARIABLES OSBT ========== */
        :root {
            /* Palette institutionnelle OSBT */
            --osbt-green: #00C853;
            --osbt-green-dark: #2E7D32;
            --osbt-blue: #2196F3;
            --osbt-orange: #FF9800;
            --osbt-purple: #9C27B0;
            
            /* Couleurs de statut */
            --success: #4CAF50;
            --warning: #FF9800;
            --danger: #F44336;
            --info: #2196F3;
            
            /* Couleurs neutres */
            --dark: #0f172a;
            --light: #f8fafc;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            
            /* Gradients OSBT */
            --gradient-osbt: linear-gradient(135deg, var(--osbt-green) 0%, var(--osbt-green-dark) 100%);
            --gradient-osbt-blue: linear-gradient(135deg, var(--osbt-green) 0%, var(--osbt-blue) 100%);
            --gradient-osbt-horizontal: linear-gradient(90deg, var(--osbt-green) 0%, var(--osbt-blue) 100%);
            
            /* Design System */
            --border-radius: 16px;
            --border-radius-lg: 24px;
            --border-radius-sm: 8px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow-sm: 0 2px 8px rgba(0, 200, 83, 0.1);
            --shadow-md: 0 8px 30px rgba(0, 200, 83, 0.15);
            --shadow-lg: 0 20px 60px rgba(0, 200, 83, 0.2);
            --max-width: 1440px;
        }

        /* Menu mobile */
        .mobile-menu {
            position: fixed;
            top: 0;
            right: -100%;
            width: 320px;
            height: 100vh;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: -10px 0 40px rgba(0, 0, 0, 0.1);
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            padding: 80px 2rem 2rem;
            overflow-y: auto;
        }

        .mobile-menu.active {
            right: 0;
        }

        .mobile-menu-links {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .mobile-menu-link {
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .mobile-menu-link:hover {
            background: rgba(26, 115, 232, 0.1);
            color: var(--osbt-blue);
            transform: translateX(5px);
        }

        .mobile-menu-link i {
            width: 20px;
            text-align: center;
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* ========== RESET & BASE ========== */
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            -webkit-text-size-adjust: 100%;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.6;
            overflow-x: hidden;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ========== LOADER OSBT ========== */
        .osbt-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient-osbt);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .osbt-loader.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .osbt-loader-logo {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 2rem;
            letter-spacing: 1px;
        }

        .osbt-spinner {
            width: 60px;
            height: 60px;
            border: 3px solid rgba(255, 255, 255, 0.2);
            border-top-color: white;
            border-radius: 50%;
            animation: osbt-spin 1s ease-in-out infinite;
        }

        @keyframes osbt-spin {
            to { transform: rotate(360deg); }
        }

        /* ========== NAVIGATION OSBT ========== */
        .osbt-nav {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 200, 83, 0.08);
            z-index: 1000;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .osbt-nav.scrolled {
            box-shadow: var(--shadow-md);
        }

        .osbt-nav-container {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .osbt-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--dark);
        }

        .osbt-logo-icon {
            width: 48px;
            height: 48px;
            background: var(--gradient-osbt);
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 900;
            font-size: 1.2rem;
            transition: var(--transition);
            overflow: hidden;
            position: relative;
        }

        .osbt-logo-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: var(--border-radius-sm);
        }

        .osbt-logo:hover .osbt-logo-icon {
            transform: rotate(-5deg) scale(1.05);
        }

        .osbt-logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .osbt-logo-main {
            background: var(--gradient-osbt);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 900;
        }

        .osbt-logo-sub {
            font-size: 0.75rem;
            color: var(--gray);
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        /* Menu navigation */
        .osbt-nav-menu {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .osbt-nav-link {
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius-sm);
            transition: var(--transition);
            position: relative;
        }

        .osbt-nav-link:hover {
            background: rgba(0, 200, 83, 0.05);
            color: var(--osbt-green);
        }

        .osbt-nav-link.active {
            background: rgba(0, 200, 83, 0.1);
            color: var(--osbt-green);
        }

        .osbt-nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 1rem;
            right: 1rem;
            height: 2px;
            background: var(--gradient-osbt);
            border-radius: 1px;
        }

        /* Bouton connexion */
        .osbt-btn-login {
            background: var(--gradient-osbt);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .osbt-btn-login:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* ========== HERO SECTION OSBT ========== */
        .osbt-hero {
            margin-top: 80px;
            min-height: 90vh;
            background: var(--gradient-osbt);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
        }

        .osbt-hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.1;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
        }

        .osbt-hero-container {
            max-width: var(--max-width);
            margin: 0 auto;
            padding: 4rem 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .osbt-hero-content h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 800;
            color: white;
            line-height: 1.1;
            margin-bottom: 1.5rem;
        }

        .osbt-hero-highlight {
            background: linear-gradient(135deg, #ffffff 0%, #B2FF59 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: block;
        }

        .osbt-hero-subtitle {
            font-size: clamp(1rem, 2vw, 1.25rem);
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2.5rem;
            max-width: 600px;
            line-height: 1.7;
        }

        /* Boutons hero */
        .osbt-hero-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .osbt-btn-primary {
            background: white;
            color: var(--osbt-green-dark);
            padding: 1rem 2.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            transition: var(--transition);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .osbt-btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .osbt-btn-secondary {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            transition: var(--transition);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .osbt-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-3px);
        }

        /* Stats hero */
        .osbt-hero-stats {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius-lg);
            padding: 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            max-width: 500px;
            width: 100%;
        }

        .osbt-hero-stats-title {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
        }

        .osbt-stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .osbt-stat-card {
            background: rgba(255, 255, 255, 0.08);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: var(--transition);
            text-align: center;
        }

        .osbt-stat-card:hover {
            background: rgba(255, 255, 255, 0.12);
            transform: translateY(-5px);
        }

        .osbt-stat-number {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .osbt-stat-label {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ========== SECTION ANNONCES ========== */
        .osbt-annonces-section {
            padding: 6rem 2rem;
            background: var(--light);
            position: relative;
        }

        .osbt-section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .osbt-section-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 800;
            margin-bottom: 1rem;
            background: var(--gradient-osbt);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .osbt-section-subtitle {
            color: var(--gray);
            font-size: 1.25rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .osbt-annonces-container {
            max-width: var(--max-width);
            margin: 0 auto;
        }

        .osbt-annonces-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .osbt-annonce-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            border: 1px solid rgba(0, 200, 83, 0.1);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .osbt-annonce-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }

        .osbt-annonce-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--gradient-osbt);
            transition: var(--transition);
        }

        .osbt-annonce-card:hover::before {
            width: 8px;
        }

        /* Badges d'importance */
        .osbt-annonce-badge {
            display: inline-block;
            padding: 0.35rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
            align-self: flex-start;
        }

        .annonce-urgent {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger);
            border: 1px solid rgba(244, 67, 54, 0.2);
        }

        .annonce-important {
            background: rgba(255, 152, 0, 0.1);
            color: var(--warning);
            border: 1px solid rgba(255, 152, 0, 0.2);
        }

        .annonce-info {
            background: rgba(33, 150, 243, 0.1);
            color: var(--info);
            border: 1px solid rgba(33, 150, 243, 0.2);
        }

        .annonce-pedagogique {
            background: rgba(156, 39, 176, 0.1);
            color: var(--osbt-purple);
            border: 1px solid rgba(156, 39, 176, 0.2);
        }

        .annonce-administratif {
            background: rgba(0, 200, 83, 0.1);
            color: var(--osbt-green);
            border: 1px solid rgba(0, 200, 83, 0.2);
        }

        .osbt-annonce-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
            line-height: 1.3;
        }

        .osbt-annonce-content {
            color: var(--gray);
            line-height: 1.7;
            margin-bottom: 1.5rem;
            flex-grow: 1;
        }

        .osbt-annonce-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            font-size: 0.9rem;
        }

        .osbt-annonce-author {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray);
        }

        .osbt-annonce-cible {
            padding: 0.25rem 0.75rem;
            background: rgba(0, 200, 83, 0.08);
            color: var(--osbt-green);
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            font-size: 0.8rem;
        }

        .osbt-annonce-date {
            color: var(--gray);
            font-size: 0.85rem;
        }

        /* État vide */
        .osbt-empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--border-radius);
            border: 2px dashed rgba(0, 200, 83, 0.2);
        }

        .osbt-empty-state-icon {
            font-size: 3rem;
            color: var(--osbt-green);
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* ========== SECTION AXES STRATÉGIQUES ========== */
        .osbt-axes-section {
            padding: 6rem 2rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }

        .osbt-axes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            max-width: var(--max-width);
            margin: 0 auto;
        }

        .osbt-axis-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 2.5rem;
            border: 1px solid rgba(0, 200, 83, 0.1);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .osbt-axis-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }

        .osbt-axis-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-osbt);
            transform: scaleX(0);
            transition: var(--transition);
            transform-origin: left;
        }

        .osbt-axis-card:hover::before {
            transform: scaleX(1);
        }

        .osbt-axis-icon {
            width: 64px;
            height: 64px;
            background: var(--gradient-osbt);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.75rem;
            margin-bottom: 1.5rem;
        }

        .osbt-axis-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
        }

        .osbt-axis-content {
            color: var(--gray);
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }

        .osbt-axis-features {
            list-style: none;
            margin-top: 1.5rem;
        }

        .osbt-axis-feature {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            color: var(--gray);
        }

        .osbt-axis-feature i {
            color: var(--osbt-green);
            font-size: 0.9rem;
        }

        /* ========== SECTION ACCÈS ========== */
        .osbt-access-section {
            padding: 6rem 2rem;
            background: var(--gradient-osbt);
            position: relative;
            overflow: hidden;
        }

        .osbt-access-container {
            max-width: var(--max-width);
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .osbt-access-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 800;
            color: white;
            margin-bottom: 1rem;
        }

        .osbt-access-subtitle {
            font-size: 1.25rem;
            color: rgba(255, 255, 255, 0.9);
            max-width: 600px;
            margin: 0 auto 2.5rem;
        }

        /* ========== FOOTER OSBT ========== */
        .osbt-footer {
            background: var(--dark);
            color: white;
            padding: 4rem 2rem 2rem;
            margin-top: auto;
        }

        .osbt-footer-container {
            max-width: var(--max-width);
            margin: 0 auto;
        }

        .osbt-footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .osbt-footer-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
        }

        .osbt-footer-logo-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient-osbt);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 1rem;
        }

        .osbt-footer-logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .osbt-footer-logo-main {
            color: white;
            font-weight: 800;
            font-size: 1.25rem;
        }

        .osbt-footer-logo-sub {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .osbt-footer-description {
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }

        .osbt-footer-title {
            color: white;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .osbt-footer-links {
            list-style: none;
        }

        .osbt-footer-link {
            margin-bottom: 0.75rem;
        }

        .osbt-footer-link a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .osbt-footer-link a:hover {
            color: white;
            padding-left: 5px;
        }

        .osbt-footer-contact {
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.7;
        }

        .osbt-footer-contact-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .osbt-footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 2rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.9rem;
        }

        /* ========== MENU BURGER STYLISÉ ========== */
        .osbt-menu-toggle {
            display: none;
            flex-direction: column;
            justify-content: space-between;
            width: 35px;
            height: 28px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            position: relative;
            z-index: 1001;
            transition: var(--transition);
        }

        .osbt-menu-toggle:hover {
            transform: scale(1.05);
        }

        .osbt-menu-toggle-line {
            width: 100%;
            height: 3px;
            background: var(--osbt-green);
            border-radius: 3px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: absolute;
            left: 0;
            box-shadow: 0 2px 4px rgba(0, 200, 83, 0.2);
        }

        .osbt-menu-toggle-line:nth-child(1) { 
            top: 0; 
            transform-origin: left center;
        }
        
        .osbt-menu-toggle-line:nth-child(2) { 
            top: 50%; 
            transform: translateY(-50%);
            transform-origin: center center;
        }
        
        .osbt-menu-toggle-line:nth-child(3) { 
            bottom: 0; 
            transform-origin: left center;
        }

        /* Animation burger en croix */
        .osbt-menu-toggle.active .osbt-menu-toggle-line:nth-child(1) {
            transform: rotate(45deg) translateY(6px);
            background: var(--osbt-blue);
            box-shadow: 0 2px 4px rgba(33, 150, 243, 0.3);
        }

        .osbt-menu-toggle.active .osbt-menu-toggle-line:nth-child(2) {
            opacity: 0;
            transform: translateX(-20px) scale(0);
        }

        .osbt-menu-toggle.active .osbt-menu-toggle-line:nth-child(3) {
            transform: rotate(-45deg) translateY(-6px);
            background: var(--osbt-blue);
            box-shadow: 0 2px 4px rgba(33, 150, 243, 0.3);
        }
        @media (max-width: 1024px) {
            .osbt-hero-container {
                grid-template-columns: 1fr;
                gap: 3rem;
                text-align: center;
            }
            
            .osbt-hero-stats {
                margin: 0 auto;
            }
            
            .osbt-nav-menu {
                gap: 1rem;
            }
        }

        @media (max-width: 768px) {
            .osbt-nav-container {
                padding: 1rem;
            }
            
            .osbt-hero {
                margin-top: 60px;
            }
            
            .osbt-hero-container {
                padding: 2rem 1rem;
            }
            
            .osbt-hero-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .osbt-btn-primary,
            .osbt-btn-secondary {
                width: 100%;
                justify-content: center;
            }
            
            .osbt-stats-grid {
                grid-template-columns: 1fr;
            }
            
            .osbt-annonces-grid,
            .osbt-axes-grid {
                grid-template-columns: 1fr;
            }
            
            .osbt-annonce-card,
            .osbt-axis-card {
                padding: 1.5rem;
            }
            
            .osbt-section-header {
                margin-bottom: 2rem;
            }
            
            /* Afficher le menu burger en mobile */
            .osbt-menu-toggle {
                display: flex !important;
            }
        }

        @media (max-width: 480px) {
            .osbt-hero-content h1 {
                font-size: 2rem;
            }
            
            .osbt-section-title {
                font-size: 1.75rem;
            }
            
            .osbt-nav-menu {
                display: none; /* Géré par le menu mobile */
            }
        }

        /* ========== UTILITAIRES ========== */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .text-gradient {
            background: var(--gradient-osbt);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body data-aos-easing="ease" data-aos-duration="1000" data-aos-delay="0">
    <!-- Loader OSBT -->
    <div class="osbt-loader" id="osbtLoader" role="status" aria-label="Chargement de la plateforme OSBT">
        <div class="osbt-loader-logo">OSBT CONNECT</div>
        <div class="osbt-spinner" aria-hidden="true"></div>
        <span class="sr-only">Chargement en cours...</span>
    </div>

    <!-- Navigation OSBT -->
    <nav class="osbt-nav" id="osbtNav" role="navigation" aria-label="Navigation principale">
        <div class="osbt-nav-container">
            <a href="#" class="osbt-logo" aria-label="OSBT Connect - Retour à l'accueil">
                <div class="osbt-logo-icon" aria-hidden="true">
                    <img src="assets/img/logo-osbt.png" alt="OSBT Logo" onerror="this.style.display='none'; this.parentElement.innerHTML='OSBT';">
                </div>
                <div class="osbt-logo-text">
                    <span class="osbt-logo-main">Connect</span>
                    <span class="osbt-logo-sub">Plateforme Interne</span>
                </div>
            </a>
            
            <div class="osbt-nav-menu" id="navMenu">
                <a href="#axes" class="osbt-nav-link" data-scroll="axes">Nos Axes</a>
                <a href="#annonces" class="osbt-nav-link" data-scroll="annonces">Annonces</a>
                <a href="#programmes" class="osbt-nav-link" data-scroll="programmes">Programmes</a>
                <a href="#contact" class="osbt-nav-link" data-scroll="contact">Contact</a>
                
                <?php if ($isAuthenticated): ?>
                    <a href="dashboard.php" class="osbt-btn-login" aria-label="Accéder au tableau de bord">
                        <i class="fas fa-tachometer-alt"></i>
                        Tableau de bord
                    </a>
                <?php else: ?>
                    <a href="login.php" class="osbt-btn-login" aria-label="Se connecter à la plateforme">
                        <i class="fas fa-sign-in-alt"></i>
                        Connexion OSBT
                    </a>
                <?php endif; ?>
            </div>
            
            <button class="osbt-menu-toggle" id="menuToggle" aria-label="Menu mobile" aria-expanded="false" aria-controls="navMenu">
                <span class="osbt-menu-toggle-line"></span>
                <span class="osbt-menu-toggle-line"></span>
                <span class="osbt-menu-toggle-line"></span>
            </button>
        </div>
        
        <!-- Menu Mobile -->
        <div class="mobile-menu" id="mobileMenu">
            <div class="mobile-menu-links">
                <a href="#community" class="mobile-menu-link">
                    <i class="fas fa-users"></i>
                    Community Hub
                </a>
                <a href="#learning" class="mobile-menu-link">
                    <i class="fas fa-brain"></i>
                    Learning Center
                </a>
                <a href="#dashboard" class="mobile-menu-link">
                    <i class="fas fa-th-large"></i>
                    Mon OSBT
                </a>
                <a href="#about" class="mobile-menu-link">
                    <i class="fas fa-info-circle"></i>
                    À propos
                </a>
            </div>
            <a href="login.php" class="btn-login" style="width: 100%; justify-content: center;">
                <i class="fas fa-sign-in-alt"></i>
                Se connecter
            </a>
        </div>

        <!-- Overlay -->
        <div class="overlay" id="overlay"></div>
    </nav>

    <!-- Hero Section -->
    <section class="osbt-hero" id="hero" role="banner">
        <div class="osbt-hero-bg" aria-hidden="true"></div>
        
        <div class="osbt-hero-container">
            <div class="osbt-hero-content" data-aos="fade-right">
                <h1>
                    Votre Écosystème<br>
                    <span class="osbt-hero-highlight">Business & Technology</span>
                </h1>
                
                <p class="osbt-hero-subtitle">
                    Plateforme interne exclusive OSBT. Accédez à vos cours, collaborez avec vos pairs, 
                    et bénéficiez du mentorat des anciens élèves. Un environnement sécurisé et pensé 
                    pour votre réussite académique.
                </p>
                
                <div class="osbt-hero-actions">
                    <?php if ($isAuthenticated): ?>
                        <a href="dashboard.php" class="osbt-btn-primary" aria-label="Accéder à mon espace">
                            <i class="fas fa-rocket"></i>
                            Espace Étudiant
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="osbt-btn-primary" aria-label="Se connecter à la plateforme">
                            <i class="fas fa-lock"></i>
                            Accès Sécurisé
                        </a>
                    <?php endif; ?>
                    
                    <a href="#axes" class="osbt-btn-secondary" aria-label="Découvrir les fonctionnalités">
                        <i class="fas fa-compass"></i>
                        Découvrir
                    </a>
                </div>
                
                <?php if (!$isAuthenticated): ?>
                <p class="osbt-access-notice" style="margin-top: 2rem; color: rgba(255, 255, 255, 0.8); font-size: 0.9rem;">
                    <i class="fas fa-info-circle"></i>
                    Accès réservé aux étudiants et anciens élèves OSBT (email @osbt.education)
                </p>
                <?php endif; ?>
            </div>
            
            <div class="osbt-hero-stats" data-aos="fade-left" data-aos-delay="200">
                <h3 class="osbt-hero-stats-title">La Communauté OSBT en Chiffres</h3>
                
                <div class="osbt-stats-grid">
                    <?php 
                    $statLabels = [
                        'etudiants' => 'Étudiants Actifs',
                        'matieres' => 'Matières',
                        'mentors' => 'Mentors Certifiés',
                        'flashcards' => 'Flashcards'
                    ];
                    
                    foreach ($statLabels as $key => $label): 
                        if (isset($stats[$key])): 
                    ?>
                        <div class="osbt-stat-card">
                            <div class="osbt-stat-number" data-stat="<?= $key ?>" data-count="<?= $stats[$key] ?>">
                                0
                            </div>
                            <div class="osbt-stat-label"><?= $label ?></div>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Annonces -->
    <section class="osbt-annonces-section" id="annonces" aria-labelledby="annonces-title">
        <div class="osbt-annonces-container">
            <div class="osbt-section-header" data-aos="fade-up">
                <h2 class="osbt-section-title" id="annonces-title">Actualités OSBT</h2>
                <p class="osbt-section-subtitle">
                    Les dernières annonces de l'administration, des professeurs et de la communauté
                </p>
            </div>
            
            <div class="osbt-annonces-grid">
                <?php if(empty($annonces)): ?>
                    <div class="osbt-empty-state" data-aos="fade-up">
                        <div class="osbt-empty-state-icon" aria-hidden="true">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <h3 style="color: var(--dark); margin-bottom: 1rem;">Aucune annonce pour le moment</h3>
                        <p style="color: var(--gray); max-width: 400px; margin: 0 auto;">
                            Revenez plus tard pour consulter les dernières actualités de l'école.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach($annonces as $index => $annonce): ?>
                    <article class="osbt-annonce-card" 
                             data-aos="fade-up" 
                             data-aos-delay="<?= $index * 100 ?>"
                             aria-labelledby="annonce-title-<?= $index ?>">
                        <span class="osbt-annonce-badge <?= getOSBTImportanceClass($annonce['importance']) ?>" aria-label="Importance : <?= $annonce['importance'] ?>">
                            <?= strtoupper($annonce['importance']) ?>
                        </span>
                        
                        <h3 class="osbt-annonce-title" id="annonce-title-<?= $index ?>">
                            <?= osbt_escape($annonce['titre']) ?>
                        </h3>
                        
                        <div class="osbt-annonce-content">
                            <?= osbt_escape(strip_tags($annonce['contenu']), 150) ?>
                        </div>
                        
                        <footer class="osbt-annonce-meta">
                            <div class="osbt-annonce-author">
                                <i class="fas fa-user-circle" aria-hidden="true"></i>
                                <span>
                                    <?= osbt_escape($annonce['prenom'] . ' ' . $annonce['nom']) ?>
                                    <?php if (!empty($annonce['programme'])): ?>
                                        <span style="color: var(--gray); font-size: 0.85em;">
                                            (<?= $annonce['programme'] ?>)
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <div class="osbt-annonce-info">
                                <?php if (!empty($annonce['cible'])): ?>
                                <span class="osbt-annonce-cible" aria-label="Destinataires : <?= $annonce['cible'] ?>">
                                    <?= ucfirst($annonce['cible']) ?>
                                </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($annonce['date_debut'])): ?>
                                <div class="osbt-annonce-date">
                                    <i class="far fa-clock" aria-hidden="true"></i>
                                    <?= osbt_format_date($annonce['date_debut']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </footer>
                    </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Section Axes Stratégiques -->
    <section class="osbt-axes-section" id="axes" aria-labelledby="axes-title">
        <div class="osbt-section-header" data-aos="fade-up">
            <h2 class="osbt-section-title" id="axes-title">Nos 3 Axes Stratégiques</h2>
            <p class="osbt-section-subtitle">
                Une approche intégrée pour votre parcours académique et professionnel
            </p>
        </div>
        
        <div class="osbt-axes-grid">
            <article class="osbt-axis-card" data-aos="fade-up" data-aos-delay="100">
                <div class="osbt-axis-icon" aria-hidden="true">
                    <i class="fas fa-university"></i>
                </div>
                <h3 class="osbt-axis-title">Hub Scolaire</h3>
                <p class="osbt-axis-content">
                    Accès centralisé à votre vie étudiante : planning, notes, ressources pédagogiques, 
                    et communication directe avec l'administration.
                </p>
                <ul class="osbt-axis-features">
                    <li class="osbt-axis-feature">
                        <i class="fas fa-check-circle"></i>
                        Planning académique dynamique
                    </li>
                    <li class="osbt-axis-feature">
                        <i class="fas fa-check-circle"></i>
                        Espace de dépôt des travaux
                    </li>
                    <li class="osbt-axis-feature">
                        <i class="fas fa-check-circle"></i>
                        Communication institutionnelle
                    </li>
                </ul>
            </article>
            
            <article class="osbt-axis-card" data-aos="fade-up" data-aos-delay="200">
                <div class="osbt-axis-icon" aria-hidden="true">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <h3 class="osbt-axis-title">Mentorat & Communauté</h3>
                <p class="osbt-axis-content">
                    Connectez-vous avec les anciens élèves OSBT et bénéficiez d'un accompagnement 
                    personnalisé tout au long de votre parcours.
                </p>
                <ul class="osbt-axis-features">
                    <li class="osbt-axis-feature">
                        <i class="fas fa-check-circle"></i>
                        Matching par compétences
                    </li>
                    <li class="osbt-axis-feature">
                        <i class="fas fa-check-circle"></i>
                        Sessions de mentorat réservables
                    </li>
                    <li class="osbt-axis-feature">
                        <i class="fas fa-check-circle"></i>
                        Forum communautaire
                    </li>
                </ul>
            </article>
            
            <article class="osbt-axis-card" data-aos="fade-up" data-aos-delay="300">
                <div class="osbt-axis-icon" aria-hidden="true">
                    <i class="fas fa-brain"></i>
                </div>
                <h3 class="osbt-axis-title">Learning Center</h3>
                <p class="osbt-axis-content">
                    Outils d'apprentissage innovants adaptés aux programmes OSBT avec des méthodes 
                    basées sur la science cognitive.
                </p>
                <ul class="osbt-axis-features">
                    <li class="osbt-axis-feature">
                        <i class="fas fa-check-circle"></i>
                        Flashcards collaboratives
                    </li>
                    <li class="osbt-axis-feature">
                        <i class="fas fa-check-circle"></i>
                        Ressources par matière
                    </li>
                    <li class="osbt-axis-feature">
                        <i class="fas fa-check-circle"></i>
                        Suivi de progression
                    </li>
                </ul>
            </article>
        </div>
    </section>

    <!-- Section Programmes -->
    <section class="osbt-access-section" id="programmes" aria-labelledby="programmes-title">
        <div class="osbt-access-container" data-aos="fade-up">
            <h2 class="osbt-access-title" id="programmes-title">Programmes OSBT</h2>
            <p class="osbt-access-subtitle">
                Notre plateforme est optimisée pour tous les programmes de l'école, 
                avec des fonctionnalités spécifiques à chaque cursus.
            </p>
            
            <div style="display: flex; flex-wrap: wrap; gap: 1rem; justify-content: center; margin-top: 3rem;">
                <?php foreach($programmes as $programme): ?>
                <span style="background: rgba(255, 255, 255, 0.15); color: white; padding: 0.75rem 1.5rem; 
                     border-radius: 50px; border: 1px solid rgba(255, 255, 255, 0.3); font-weight: 600;">
                    <?= $programme ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer OSBT -->
    <footer class="osbt-footer" role="contentinfo">
        <div class="osbt-footer-container">
            <div class="osbt-footer-grid">
                <div>
                    <div class="osbt-footer-logo">
                        <div class="osbt-footer-logo-icon" aria-hidden="true">OSBT</div>
                        <div class="osbt-footer-logo-text">
                            <span class="osbt-footer-logo-main">Connect</span>
                            <span class="osbt-footer-logo-sub">Plateforme Interne</span>
                        </div>
                    </div>
                    <p class="osbt-footer-description">
                        L'excellence académique par la technologie. Une plateforme exclusive 
                        pour la communauté OSBT, pensée pour votre réussite.
                    </p>
                </div>
                
                <div>
                    <h4 class="osbt-footer-title">Accès Rapide</h4>
                    <ul class="osbt-footer-links">
                        <li class="osbt-footer-link">
                            <a href="#axes">
                                <i class="fas fa-chevron-right"></i>
                                Nos Axes
                            </a>
                        </li>
                        <li class="osbt-footer-link">
                            <a href="#annonces">
                                <i class="fas fa-chevron-right"></i>
                                Annonces
                            </a>
                        </li>
                        <li class="osbt-footer-link">
                            <a href="#programmes">
                                <i class="fas fa-chevron-right"></i>
                                Programmes
                            </a>
                        </li>
                        <li class="osbt-footer-link">
                            <a href="login.php">
                                <i class="fas fa-chevron-right"></i>
                                Connexion
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="osbt-footer-title">Contact OSBT</h4>
                    <div class="osbt-footer-contact">
                        <div class="osbt-footer-contact-item">
                            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                            <span>Omnia School of Business & Technology<br>Casablanca, Maroc</span>
                        </div>
                        <div class="osbt-footer-contact-item">
                            <i class="fas fa-envelope" aria-hidden="true"></i>
                            <span>contact@osbt.education</span>
                        </div>
                        <div class="osbt-footer-contact-item">
                            <i class="fas fa-phone" aria-hidden="true"></i>
                            <span>+212 983 348 334</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="osbt-footer-bottom">
                <p>&copy; <?= $currentYear ?> OSBT Connect - Plateforme Interne. Accès réservé.</p>
                <p style="margin-top: 0.5rem; font-size: 0.85rem;">
                    Version <?= OSBT_VERSION ?> | Environnement <?= OSBT_ENVIRONMENT ?>
                </p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // ============================================
        // INITIALISATION OSBT
        // ============================================
        
        // Configuration AOS
        AOS.init({
            duration: 800,
            once: true,
            offset: 100,
            disable: window.matchMedia('(prefers-reduced-motion: reduce)').matches
        });

        // ============================================
        // GESTION DU CHARGEMENT
        // ============================================
        
        window.addEventListener('load', () => {
            // Masquer le loader
            setTimeout(() => {
                const loader = document.getElementById('osbtLoader');
                loader.classList.add('hidden');
                
                // Supprimer après animation
                setTimeout(() => loader.remove(), 500);
            }, 800);
            
            // Initialiser les fonctionnalités
            initOSBTSmoothScroll();
            initOSBTCounters();
            initOSBTNavScroll();
            initOSBTMobileMenu();
            initOSBTAnnouncementFilters();
        });

        // ============================================
        // NAVIGATION SMOOTH SCROLL
        // ============================================
        
        function initOSBTSmoothScroll() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href === '#' || href === '') return;
                    
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (!target) return;
                    
                    const headerHeight = document.querySelector('.osbt-nav').offsetHeight;
                    const targetPosition = target.getBoundingClientRect().top + window.pageYOffset;
                    
                    window.scrollTo({
                        top: targetPosition - headerHeight,
                        behavior: 'smooth'
                    });
                    
                    // Mettre à jour l'URL sans recharger
                    history.pushState(null, null, href);
                    
                    // Mettre à jour la navigation active
                    updateActiveNavLink(href);
                });
            });
        }

        // ============================================
        // COMPTEURS ANIMÉS
        // ============================================
        
        function initOSBTCounters() {
            const counters = document.querySelectorAll('[data-count]');
            const speed = 200; // Plus bas = plus rapide
            
            counters.forEach(counter => {
                const updateCounter = () => {
                    const target = +counter.getAttribute('data-count');
                    const count = +counter.innerText.replace(/\D/g, '');
                    const increment = target / speed;
                    
                    if (count < target) {
                        counter.innerText = Math.ceil(count + increment).toLocaleString();
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.innerText = target.toLocaleString();
                    }
                };
                
                // Observer pour déclencher quand visible
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            updateCounter();
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.5 });
                
                observer.observe(counter);
            });
        }

        // ============================================
        // NAVIGATION SCROLL EFFECT
        // ============================================
        
        function initOSBTNavScroll() {
            const nav = document.getElementById('osbtNav');
            let lastScroll = 0;
            
            window.addEventListener('scroll', () => {
                const currentScroll = window.pageYOffset;
                
                // Effet d'ombre au scroll
                nav.classList.toggle('scrolled', currentScroll > 50);
                
                // Gestion des liens actifs
                updateActiveNavOnScroll();
                
                lastScroll = currentScroll;
            });
        }

        function updateActiveNavOnScroll() {
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.osbt-nav-link');
            const scrollPosition = window.scrollY + 100;
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                const sectionId = section.getAttribute('id');
                
                if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                    navLinks.forEach(link => {
                        link.classList.remove('active');
                        if (link.getAttribute('href') === `#${sectionId}`) {
                            link.classList.add('active');
                        }
                    });
                }
            });
        }

        function updateActiveNavLink(targetId) {
            document.querySelectorAll('.osbt-nav-link').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === targetId) {
                    link.classList.add('active');
                }
            });
        }

        // ============================================
        // MENU MOBILE OSBT
        // ============================================
        
        function initOSBTMobileMenu() {
            const menuToggle = document.getElementById('menuToggle');
            const navMenu = document.getElementById('navMenu');
            
            if (!menuToggle || !navMenu) return;
            
            menuToggle.addEventListener('click', () => {
                const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
                
                // Basculer l'état
                menuToggle.setAttribute('aria-expanded', !isExpanded);
                navMenu.style.display = isExpanded ? 'none' : 'flex';
                
                // Animation
                if (!isExpanded) {
                    navMenu.style.flexDirection = 'column';
                    navMenu.style.position = 'absolute';
                    navMenu.style.top = '100%';
                    navMenu.style.left = '0';
                    navMenu.style.right = '0';
                    navMenu.style.background = 'rgba(255, 255, 255, 0.98)';
                    navMenu.style.backdropFilter = 'blur(20px)';
                    navMenu.style.padding = '2rem';
                    navMenu.style.boxShadow = '0 10px 30px rgba(0, 0, 0, 0.1)';
                    navMenu.style.borderRadius = '0 0 var(--border-radius) var(--border-radius)';
                    navMenu.style.zIndex = '1000';
                }
            });
            
            // Fermer le menu en cliquant à l'extérieur
            document.addEventListener('click', (e) => {
                if (!menuToggle.contains(e.target) && !navMenu.contains(e.target)) {
                    menuToggle.setAttribute('aria-expanded', 'false');
                    navMenu.style.display = 'none';
                }
            });
            
            // Fermer le menu en redimensionnant
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    menuToggle.setAttribute('aria-expanded', 'false');
                    navMenu.style.display = 'flex';
                    navMenu.style.position = 'static';
                    navMenu.style.background = 'transparent';
                    navMenu.style.boxShadow = 'none';
                    navMenu.style.padding = '0';
                }
            });
        }

        // ============================================
        // FILTRES ANNONCES (Fonctionnalité future)
        // ============================================
        
        function initOSBTAnnouncementFilters() {
            // À implémenter lorsque les filtres seront ajoutés
            console.log('Fonctionnalité de filtrage des annonces prête à être implémentée');
        }

        // ============================================
        // GESTION DES ERREURS
        // ============================================
        
        window.addEventListener('error', (e) => {
            console.warn('Erreur détectée:', e.message);
            // Vous pouvez envoyer ces erreurs à un service de monitoring
        });

        // ============================================
        // OFFLINE DETECTION
        // ============================================
        
        window.addEventListener('online', () => {
            console.log('Connexion rétablie');
            // Vous pouvez ajouter un message à l'utilisateur
        });

        window.addEventListener('offline', () => {
            console.warn('Connexion perdue');
            // Vous pouvez afficher un message d'avertissement
        });

        // ============================================
        // PWA SUPPORT (Fonctionnalité future)
        // ============================================
        
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(error => {
                    console.log('Service Worker registration failed:', error);
                });
            });
        }
    </script>
</body>
</html>
