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

// === DONNÉES ===
$sessions = [];
$etudiants = [];
$stats_mentorat = [];

$user_id = $_SESSION['user_id'];

try {
    // D'abord récupérer l'ID du professeur
    $stmt = $pdo->prepare("
        SELECT id_professeur FROM professeurs WHERE id_utilisateur = ?
    ");
    $stmt->execute([$user_id]);
    $professor_info = $stmt->fetch();
    
    if ($professor_info) {
        $professor_id = $professor_info['id_professeur'];
        
        // Sessions de mentorat du professeur
        $stmt = $pdo->prepare("
            SELECT sm.*, 
                   CONCAT(u.prenom, ' ', u.nom) as etudiant_nom,
                   u.noma,
                   m.nom as matiere_nom,
                   c.nom as classe_nom
            FROM sessions_mentorat sm
            JOIN utilisateurs u ON sm.etudiant_id = u.id_utilisateur
            JOIN matieres m ON sm.matiere_id = m.id_matiere
            LEFT JOIN classes c ON u.classe_id = c.id_classe
            WHERE sm.mentor_id = ?
            ORDER BY sm.date_session DESC
        ");
        $stmt->execute([$user_id]);
        $sessions = $stmt->fetchAll();
        
        // Étudiants disponibles pour le mentorat
        $stmt = $pdo->prepare("
            SELECT u.*, c.nom as classe_nom, m.nom as matiere_nom
            FROM utilisateurs u
            LEFT JOIN classes c ON u.classe_id = c.id_classe
            LEFT JOIN matieres m ON c.matiere_id = m.id_matiere
            WHERE u.role = 'etudiant' 
            AND u.est_actif = 1
            AND u.classe_id IN (SELECT id_classe FROM classes WHERE professeur_id = ? AND est_active = 1)
            ORDER BY u.nom, u.prenom
        ");
        $stmt->execute([$professor_id]);
        $etudiants = $stmt->fetchAll();
        
        // Statistiques de mentorat
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT sm.id_session) as total_sessions,
                COUNT(DISTINCT CASE WHEN sm.statut = 'session_terminee' THEN sm.id_session END) as sessions_terminees,
                COUNT(DISTINCT CASE WHEN sm.date_session >= NOW() THEN sm.id_session END) as sessions_aujourdhui,
                COUNT(DISTINCT CASE WHEN sm.date_session >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN sm.id_session END) as sessions_semaine,
                AVG(
                    CASE 
                        WHEN sm.statut = 'session_terminee' THEN 
                            TIMESTAMPDIFF(MINUTE, sm.date_session, sm.date_fin)
                        ELSE NULL 
                    END
                ) as duree_moyenne
            FROM sessions_mentorat sm
            WHERE sm.mentor_id = ?
        ");
        $stmt->execute([$user_id]);
        $stats_mentorat = $stmt->fetch();
    } else {
        $sessions = [];
        $etudiants = [];
        $stats_mentorat = [
            'total_sessions' => 0,
            'sessions_terminees' => 0,
            'sessions_aujourdhui' => 0,
            'sessions_semaine' => 0,
            'duree_moyenne' => 0
        ];
    }
    
} catch (PDOException $e) {
    error_log("Erreur mentorat sessions: " . $e->getMessage());
    $sessions = [];
    $etudiants = [];
    $stats_mentorat = [
        'total_sessions' => 0,
        'sessions_terminees' => 0,
        'sessions_aujourdhui' => 0,
        'sessions_semaine' => 0,
        'duree_moyenne' => 0
    ];
}

// Traitement du formulaire de création/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_session') {
        $etudiant_id = $_POST['etudiant_id'] ?? '';
        $matiere_id = $_POST['matiere_id'] ?? '';
        $date_session = $_POST['date_session'] ?? '';
        $heure_debut = $_POST['heure_debut'] ?? '';
        $duree = $_POST['duree'] ?? 60;
        $type_session = $_POST['type_session'] ?? 'individuelle';
        $salle = $_POST['salle'] ?? '';
        $description = trim($_POST['description'] ?? '');
        
        if (!empty($etudiant_id) && !empty($matiere_id) && !empty($date_session) && !empty($heure_debut)) {
            try {
                $datetime_session = $date_session . ' ' . $heure_debut;
                $datetime_fin = date('Y-m-d H:i', strtotime($datetime_session . ' +' . $duree . ' minutes'));
                
                $stmt = $pdo->prepare("
                    INSERT INTO sessions_mentorat 
                    (etudiant_id, mentor_id, matiere_id, date_session, date_fin, duree, type_session, salle, description, statut, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'demande_envoyee', NOW())
                ");
                $stmt->execute([$etudiant_id, $user_id, $matiere_id, $datetime_session, $datetime_fin, $duree, $type_session, $salle, $description]);
                
                $success = "Session de mentorat créée avec succès !";
            } catch (PDOException $e) {
                $error = "Erreur lors de la création de la session: " . $e->getMessage();
            }
        } else {
            $error = "Veuillez remplir tous les champs obligatoires";
        }
    }
}

// Récupérer les matières
try {
    $stmt = $pdo->query("SELECT id_matiere, nom FROM matieres ORDER BY nom");
    $matieres = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erreur de chargement des matières";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sessions de Mentorat - OSBT Connect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --osbt-green: #00C853;
            --osbt-dark-green: #2E7D32;
            --osbt-blue: #2196F3;
            --osbt-gray: #6c757d;
            --osbt-white: #ffffff;
            --osbt-shadow: 0 2px 4px rgba(0,0,0,0.1);
            --osbt-radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--osbt-green) 0%, var(--osbt-blue) 100%);
            min-height: 100vh;
            color: #333;
        }

        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            flex: 0 0 280px;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            background: #f8f9fa;
            min-height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 2rem;
        }

        .logo-text h2 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }

        .logo-text span {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .nav-menu {
            padding: 0 1rem;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            color: white;
            text-decoration: none;
            border-radius: var(--osbt-radius);
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        .header {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--osbt-shadow);
        }

        .header h1 {
            color: var(--osbt-green);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: var(--osbt-gray);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--osbt-shadow);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--osbt-green);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--osbt-gray);
            font-size: 1rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--osbt-shadow);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title i {
            color: var(--osbt-green);
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--osbt-radius);
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--osbt-green);
            color: white;
        }

        .btn-primary:hover {
            background: var(--osbt-dark-green);
            transform: translateY(-2px);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
        }

        .session-list {
            max-height: 500px;
            overflow-y: auto;
        }

        .session-item {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 1rem;
            border-radius: var(--osbt-radius);
            transition: all 0.3s ease;
        }

        .session-item:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }

        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .session-student {
            font-weight: bold;
            color: #333;
        }

        .session-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .session-status.demande_envoyee {
            background: #e3f2fd;
            color: #1976d2;
        }

        .session-status.demande_acceptee {
            background: #fff3e0;
            color: #f57c00;
        }

        .session-status.session_confirmee {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .session-status.session_terminee {
            background: #f3e5f5;
            color: #9c27b0;
        }

        .session-status.annulee {
            background: #ffebee;
            color: #c62828;
        }

        .session-info {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.5rem;
            flex-wrap: wrap;
        }

        .session-info span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.9rem;
            color: var(--osbt-gray);
        }

        .session-description {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .session-actions {
            display: flex;
            gap: 0.5rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            position: relative;
            background: white;
            margin: 2% auto;
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            border-radius: 12px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }

        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--osbt-gray);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: var(--osbt-radius);
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--osbt-green);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--osbt-radius);
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--osbt-green);
            text-decoration: none;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .calendar-view {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .calendar-day {
            background: white;
            border-radius: var(--osbt-radius);
            padding: 1rem;
            border: 1px solid #e0e0e0;
            text-align: center;
        }

        .calendar-day.has-sessions {
            border-color: var(--osbt-green);
            background: #f0fff0;
        }

        .calendar-day .has-sessions {
            font-size: 0.9rem;
            color: var(--osbt-green);
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-text">
                    <h2>OSBT</h2>
                    <span>Sessions Mentorat</span>
                </div>
            </div>
            
            <nav class="nav-menu">
                <div class="nav-item">
                    <a href="professor_dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="mentorat_sessions.php" class="nav-link active">
                        <i class="fas fa-handshake"></i>
                        <span>Mentorat</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="student_tracking.php" class="nav-link">
                        <i class="fas fa-chart-line"></i>
                        <span>Suivi Étudiants</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="create_deck.php" class="nav-link">
                        <i class="fas fa-brain"></i>
                        <span>Créer Deck</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Contenu principal -->
        <main class="main-content">
            <a href="professor_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Retour au dashboard
            </a>
            
            <div class="header">
                <h1><i class="fas fa-handshake"></i> Sessions de Mentorat</h1>
                <p>Gérez et planifiez vos sessions de mentorat personnalisées</p>
            </div>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats_mentorat['total_sessions'] ?? 0 ?></div>
                    <div class="stat-label">Total sessions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats_mentorat['sessions_terminees'] ?? 0 ?></div>
                    <div class="stat-label">Sessions terminées</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats_mentorat['sessions_aujourdhui'] ?? 0 ?></div>
                    <div class="stat-label">Aujourd'hui</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= round($stats_mentorat['duree_moyenne'] ?? 0) ?>min</div>
                    <div class="stat-label">Durée moyenne</div>
                </div>
            </div>
            
            <!-- Contenu principal -->
            <div class="content-grid">
                <!-- Liste des sessions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-calendar"></i> Mes Sessions
                        </h3>
                        <button class="btn btn-primary" onclick="openModal()">
                            <i class="fas fa-plus"></i> Nouvelle session
                        </button>
                    </div>
                    
                    <div class="session-list">
                        <?php if(!empty($sessions)): ?>
                            <?php foreach($sessions as $session): ?>
                            <div class="session-item">
                                <div class="session-header">
                                    <div class="session-student"><?= htmlspecialchars($session['etudiant_nom']) ?></div>
                                    <div class="session-status <?= str_replace('_', '-', $session['statut']) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $session['statut'])) ?>
                                    </div>
                                </div>
                                <div class="session-info">
                                    <span><i class="fas fa-book"></i> <?= htmlspecialchars($session['matiere_nom']) ?></span>
                                    <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($session['date_session'])) ?></span>
                                    <span><i class="fas fa-clock"></i> <?= date('H:i', strtotime($session['date_session'])) ?></span>
                                    <?php if($session['salle']): ?>
                                    <span><i class="fas fa-door-open"></i> <?= htmlspecialchars($session['salle']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if($session['description']): ?>
                                <div class="session-description">
                                    <?= htmlspecialchars($session['description']) ?>
                                </div>
                                <?php endif; ?>
                                <div class="session-actions">
                                    <?php if($session['statut'] === 'demande_envoyee'): ?>
                                        <button class="btn btn-sm btn-success" onclick="acceptSession(<?= $session['id_session'] ?>)">
                                            <i class="fas fa-check"></i> Accepter
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="cancelSession(<?= $session['id_session'] ?>)">
                                            <i class="fas fa-times"></i> Annuler
                                        </button>
                                    <?php elseif($session['statut'] === 'session_confirmee'): ?>
                                        <button class="btn btn-sm btn-primary" onclick="startSession(<?= $session['id_session'] ?>)">
                                            <i class="fas fa-play"></i> Démarrer
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="completeSession(<?= $session['id_session'] ?>)">
                                            <i class="fas fa-check"></i> Terminer
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem; color: var(--osbt-gray);">
                                <i class="fas fa-handshake fa-3x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>Aucune session de mentorat programmée</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Vue calendrier -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-alt"></i> Calendrier
                        </h3>
                    </div>
                    
                    <div class="calendar-view">
                        <?php
                        // Afficher les 7 prochains jours
                        for($i = 0; $i < 7; $i++):
                            $date = date('Y-m-d', strtotime("+$i days"));
                            $day_name = date('l', strtotime($date));
                            $day_number = date('d', strtotime($date));
                            
                            // Compter les sessions pour ce jour
                            $sessions_day = array_filter($sessions, function($session) use ($date) {
                                return date('Y-m-d', strtotime($session['date_session'])) === $date;
                            });
                            
                            $has_sessions = !empty($sessions_day);
                        ?>
                        <div class="calendar-day <?= $has_sessions ? 'has-sessions' : '' ?>">
                            <div style="font-weight: bold; color: var(--osbt-primary); margin-bottom: 0.5rem;">
                                <?= $day_name ?>
                            </div>
                            <div style="font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem;">
                                <?= $day_number ?>
                            </div>
                            <?php if($has_sessions): ?>
                                <div style="font-size: 0.9rem; color: var(--osbt-primary); font-weight: 600;">
                                    <?= count($sessions_day) ?> session(s)
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de création de session -->
    <div id="sessionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Nouvelle Session de Mentorat</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_session">
                
                <div class="form-group">
                    <label for="etudiant_id">Étudiant *</label>
                    <select id="etudiant_id" name="etudiant_id" class="form-control" required>
                        <option value="">Sélectionner un étudiant</option>
                        <?php foreach($etudiants as $etudiant): ?>
                        <option value="<?= $etudiant['id_utilisateur'] ?>">
                            <?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?> - <?= htmlspecialchars($etudiant['noma']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="matiere_id">Matière *</label>
                        <select id="matiere_id" name="matiere_id" class="form-control" required>
                            <option value="">Sélectionner une matière</option>
                            <?php foreach($matieres as $matiere): ?>
                            <option value="<?= $matiere['id_matiere'] ?>">
                                <?= htmlspecialchars($matiere['nom']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="type_session">Type de session</label>
                        <select id="type_session" name="type_session" class="form-control">
                            <option value="individuelle">Individuelle</option>
                            <option value="groupe">En groupe</option>
                            <option value="atelier">Atelier</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_session">Date *</label>
                        <input type="date" id="date_session" name="date_session" class="form-control" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="heure_debut">Heure de début *</label>
                        <input type="time" id="heure_debut" name="heure_debut" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="duree">Durée (minutes)</label>
                        <input type="number" id="duree" name="duree" class="form-control" value="60" min="15" max="180">
                    </div>
                    
                    <div class="form-group">
                        <label for="salle">Salle</label>
                        <input type="text" id="salle" name="salle" class="form-control" placeholder="Ex: Labo A">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="Objectifs de la session, points à aborder..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Créer la session
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('sessionModal').style.display = 'block';
            // Définir la date par défaut à demain
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('date_session').value = tomorrow.toISOString().split('T')[0];
        }
        
        function closeModal() {
            document.getElementById('sessionModal').style.display = 'none';
            // Réinitialiser le formulaire
            document.getElementById('etudiant_id').value = '';
            document.getElementById('matiere_id').value = '';
            document.getElementById('date_session').value = '';
            document.getElementById('heure_debut').value = '';
            document.getElementById('duree').value = '60';
            document.getElementById('salle').value = '';
            document.getElementById('description').value = '';
            document.getElementById('type_session').value = 'individuelle';
        }
        
        function acceptSession(sessionId) {
            if (confirm('Êtes-vous sûr de vouloir accepter cette session ?')) {
                // Implémenter l'acceptation
                alert('Fonctionnalité d\'acceptation à implémenter pour la session ' + sessionId);
            }
        }
        
        function cancelSession(sessionId) {
            if (confirm('Êtes-vous sûr de vouloir annuler cette session ?')) {
                // Implémenter l'annulation
                alert('Fonctionnalité d\'annulation à implémenter pour la session ' + sessionId);
            }
        }
        
        function startSession(sessionId) {
            if (confirm('Démarrer la session maintenant ?')) {
                // Implémenter le démarrage
                alert('Fonctionnalité de démarrage à implémenter pour la session ' + sessionId);
            }
        }
        
        function completeSession(sessionId) {
            if (confirm('Terminer cette session ?')) {
                // Implémenter la terminaison
                alert('Fonctionnalité de terminaison à implémenter pour la session ' + sessionId);
            }
        }
        
        // Fermer la modal en cliquant à l'extérieur
        window.onclick = function(event) {
            const modal = document.getElementById('sessionModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
