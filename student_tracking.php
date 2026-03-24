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
$etudiants = [];
$suivis = [];
$stats_progression = [];

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
        
        // Étudiants du professeur
        $stmt = $pdo->prepare("
            SELECT u.*, c.nom as classe_nom, m.nom as matiere_nom, c.promotion
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
        
        // Suivis récents
        $stmt = $pdo->prepare("
            SELECT se.*, u.nom as etudiant_nom, u.prenom as etudiant_prenom
            FROM suivis_etudiants se
            JOIN utilisateurs u ON se.etudiant_id = u.id_utilisateur
            WHERE se.professeur_id = ?
            ORDER BY se.date_suivi DESC
            LIMIT 10
        ");
        $stmt->execute([$professor_id]);
        $suivis = $stmt->fetchAll();
        
        // Statistiques de progression
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT u.id_utilisateur) as total_etudiants,
                COUNT(DISTINCT CASE WHEN se.date_suivi >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN se.id_suivi END) as suivis_semaine,
                COUNT(DISTINCT CASE WHEN se.statut = 'atteint' THEN se.id_suivi END) as objectifs_atteints,
                COUNT(DISTINCT CASE WHEN se.statut = 'en_retard' THEN se.id_suivi END) as objectifs_en_retard
            FROM utilisateurs u
            LEFT JOIN suivis_etudiants se ON u.id_utilisateur = se.etudiant_id AND se.professeur_id = ?
            WHERE u.role = 'etudiant' 
            AND u.est_actif = 1
            AND u.classe_id IN (SELECT id_classe FROM classes WHERE professeur_id = ? AND est_active = 1)
        ");
        $stmt->execute([$professor_id, $professor_id]);
        $stats_progression = $stmt->fetch();
    } else {
        $etudiants = [];
        $suivis = [];
        $stats_progression = [
            'total_etudiants' => 0,
            'suivis_semaine' => 0,
            'objectifs_atteints' => 0,
            'objectifs_en_retard' => 0
        ];
    }
    
} catch (PDOException $e) {
    error_log("Erreur suivi étudiants: " . $e->getMessage());
    $etudiants = [];
    $suivis = [];
    $stats_progression = [
        'total_etudiants' => 0,
        'suivis_semaine' => 0,
        'objectifs_atteints' => 0,
        'objectifs_en_retard' => 0
    ];
}

// Traitement du formulaire de suivi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $etudiant_id = $_POST['etudiant_id'] ?? '';
    $type_suivi = $_POST['type_suivi'] ?? 'progression';
    $observations = trim($_POST['observations'] ?? '');
    $actions_recommandes = trim($_POST['actions_recommandes'] ?? '');
    $objectif_suivant = trim($_POST['objectif_suivant'] ?? '');
    $statut = $_POST['statut'] ?? 'en_cours';
    $date_suivi = $_POST['date_suivi'] ?? date('Y-m-d');
    
    if (!empty($etudiant_id) && !empty($observations)) {
        try {
            // Récupérer l'ID du professeur
            $stmt = $pdo->prepare("
                SELECT id_professeur FROM professeurs WHERE id_utilisateur = ?
            ");
            $stmt->execute([$user_id]);
            $professor_info = $stmt->fetch();
            
            if ($professor_info) {
                $professor_id = $professor_info['id_professeur'];
                
                $stmt = $pdo->prepare("
                    INSERT INTO suivis_etudiants 
                    (professeur_id, etudiant_id, date_suivi, type_suivi, observations, actions_recommandees, objectif_suivant, statut)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$professor_id, $etudiant_id, $date_suivi, $type_suivi, $observations, $actions_recommandes, $objectif_suivant, $statut]);
                
                $success = "Suivi enregistré avec succès !";
            } else {
                $error = "Profil professeur non trouvé";
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de l'enregistrement du suivi: " . $e->getMessage();
        }
    } else {
        $error = "Veuillez sélectionner un étudiant et remplir les observations";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi des Étudiants - OSBT Connect</title>
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

        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
        }

        .student-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .student-item {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s ease;
        }

        .student-item:hover {
            background: #f8f9fa;
        }

        .student-info h4 {
            margin-bottom: 0.25rem;
            color: #333;
        }

        .student-info p {
            color: var(--osbt-gray);
            font-size: 0.9rem;
        }

        .student-actions {
            display: flex;
            gap: 0.5rem;
        }

        .suivi-item {
            padding: 1rem;
            border-left: 4px solid var(--osbt-green);
            margin-bottom: 1rem;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
        }

        .suivi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .suivi-student {
            font-weight: bold;
            color: #333;
        }

        .suivi-date {
            color: var(--osbt-gray);
            font-size: 0.9rem;
        }

        .suivi-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .suivi-type.progression {
            background: #e3f2fd;
            color: #1976d2;
        }

        .suivi-type.difficulte {
            background: #ffebee;
            color: #c62828;
        }

        .suivi-type.objectif {
            background: #e8f5e8;
            color: var(--osbt-green);
        }

        .suivi-type.comportement {
            background: #fff3e0;
            color: #f57c00;
        }

        .suivi-content {
            margin-bottom: 0.5rem;
        }

        .suivi-observations {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .suivi-actions {
            color: var(--osbt-gray);
            font-style: italic;
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
            margin: 5% auto;
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            border-radius: 12px;
            max-height: 80vh;
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
                    <span>Suivi Étudiants</span>
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
                    <a href="student_tracking.php" class="nav-link active">
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
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-handshake"></i>
                        <span>Mentorat</span>
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
                <h1><i class="fas fa-chart-line"></i> Suivi des Étudiants</h1>
                <p>Suivez la progression et le développement de vos étudiants</p>
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
                    <div class="stat-value"><?= $stats_progression['total_etudiants'] ?? 0 ?></div>
                    <div class="stat-label">Étudiants suivis</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats_progression['suivis_semaine'] ?? 0 ?></div>
                    <div class="stat-label">Suivis cette semaine</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats_progression['objectifs_atteints'] ?? 0 ?></div>
                    <div class="stat-label">Objectifs atteints</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats_progression['objectifs_en_retard'] ?? 0 ?></div>
                    <div class="stat-label">Objectifs en retard</div>
                </div>
            </div>
            
            <!-- Contenu principal -->
            <div class="content-grid">
                <!-- Liste des étudiants -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-users"></i> Mes Étudiants
                        </h3>
                        <button class="btn btn-primary" onclick="openModal()">
                            <i class="fas fa-plus"></i> Nouveau suivi
                        </button>
                    </div>
                    
                    <div class="student-list">
                        <?php if(!empty($etudiants)): ?>
                            <?php foreach($etudiants as $etudiant): ?>
                            <div class="student-item">
                                <div class="student-info">
                                    <h4><?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?></h4>
                                    <p><?= htmlspecialchars($etudiant['noma']) ?> • <?= htmlspecialchars($etudiant['classe_nom']) ?></p>
                                </div>
                                <div class="student-actions">
                                    <button class="btn btn-sm btn-primary" onclick="openModal(<?= $etudiant['id_utilisateur'] ?>, '<?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?>')">
                                        <i class="fas fa-plus"></i> Suivre
                                    </button>
                                    <button class="btn btn-sm btn-success" onclick="viewDetails(<?= $etudiant['id_utilisateur'] ?>)">
                                        <i class="fas fa-eye"></i> Détails
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem; color: var(--osbt-gray);">
                                <i class="fas fa-users fa-3x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>Aucun étudiant assigné</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Suivis récents -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i> Suivis récents
                        </h3>
                    </div>
                    
                    <div>
                        <?php if(!empty($suivis)): ?>
                            <?php foreach($suivis as $suivi): ?>
                            <div class="suivi-item">
                                <div class="suivi-header">
                                    <div class="suivi-student"><?= htmlspecialchars($suivi['etudiant_prenom'] . ' ' . $suivi['etudiant_nom']) ?></div>
                                    <div class="suivi-date"><?= date('d/m/Y', strtotime($suivi['date_suivi'])) ?></div>
                                </div>
                                <div class="suivi-type <?= $suivi['type_suivi'] ?>"><?= ucfirst($suivi['type_suivi']) ?></div>
                                <div class="suivi-content">
                                    <div class="suivi-observations"><?= htmlspecialchars($suivi['observations']) ?></div>
                                    <?php if($suivi['actions_recommandes']): ?>
                                    <div class="suivi-actions">
                                        <strong>Actions:</strong> <?= htmlspecialchars($suivi['actions_recommandes']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem; color: var(--osbt-gray);">
                                <i class="fas fa-history fa-3x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>Aucun suivi récent</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de suivi -->
    <div id="suiviModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Nouveau Suivi</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" id="etudiant_id" name="etudiant_id">
                
                <div class="form-group">
                    <label for="type_suivi">Type de suivi</label>
                    <select id="type_suivi" name="type_suivi" class="form-control">
                        <option value="progression">Progression</option>
                        <option value="difficulte">Difficulté</option>
                        <option value="objectif">Objectif</option>
                        <option value="comportement">Comportement</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_suivi">Date du suivi</label>
                    <input type="date" id="date_suivi" name="date_suivi" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-group">
                    <label for="observations">Observations *</label>
                    <textarea id="observations" name="observations" class="form-control" rows="4" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="actions_recommandes">Actions recommandées</label>
                    <textarea id="actions_recommandes" name="actions_recommandes" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="objectif_suivant">Objectif suivant</label>
                    <input type="text" id="objectif_suivant" name="objectif_suivant" class="form-control" placeholder="Objectif à atteindre...">
                </div>
                
                <div class="form-group">
                    <label for="statut">Statut</label>
                    <select id="statut" name="statut" class="form-control">
                        <option value="en_cours">En cours</option>
                        <option value="atteint">Atteint</option>
                        <option value="en_retard">En retard</option>
                        <option value="abandonne">Abandonné</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(etudiantId = null, etudiantNom = '') {
            document.getElementById('suiviModal').style.display = 'block';
            if (etudiantId) {
                document.getElementById('etudiant_id').value = etudiantId;
                document.querySelector('.modal-title').textContent = `Suivi - ${etudiantNom}`;
            }
        }
        
        function closeModal() {
            document.getElementById('suiviModal').style.display = 'none';
            document.getElementById('etudiant_id').value = '';
            document.getElementById('observations').value = '';
            document.getElementById('actions_recommandes').value = '';
            document.getElementById('objectif_suivant').value = '';
            document.getElementById('type_suivi').value = 'progression';
            document.getElementById('statut').value = 'en_cours';
        }
        
        function viewDetails(etudiantId) {
            // Rediriger vers une page de détails (à implémenter)
            alert(`Détails de l'étudiant ${etudiantId} à implémenter`);
        }
        
        // Fermer la modal en cliquant à l'extérieur
        window.onclick = function(event) {
            const modal = document.getElementById('suiviModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
