<?php
session_start();
require_once 'config/database.php';

// === CONFIGURATION DE DÉVELOPPEMENT ===
$dev_mode = true;
$debug_mode = true;

if ($debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// === GESTION DES SESSIONS ===
if ($dev_mode && !isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ?");
        $stmt->execute([2]);
        $test_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($test_user) {
            $_SESSION['user_id'] = $test_user['id_utilisateur'];
            $_SESSION['user_prenom'] = $test_user['prenom'];
            $_SESSION['user_nom'] = $test_user['nom'];
            $_SESSION['user_role'] = $test_user['role'];
            $_SESSION['user_email'] = $test_user['email'];
            $_SESSION['identifiant_osbt'] = $test_user['identifiant_osbt'];
            $_SESSION['promotion'] = $test_user['promotion'] ?? '';
        }
    } catch (PDOException $e) {
        error_log("Erreur simulation DEV: " . $e->getMessage());
    }
}

// === VÉRIFICATION DE CONNEXION ===
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// === VARIABLES ===
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$promotion = $_SESSION['promotion'] ?? '';
$user_data = $_SESSION; // Ajout de cette ligne

// === RÉCUPÉRATION DES DONNÉES ===
$notes = [];
$matieres = [];
$stats = [
    'moyenne_generale' => 0,
    'note_max' => 0,
    'note_min' => 0,
    'total_notes' => 0,
    'total_matieres' => 0,
    'credits_total' => 0
];

try {
    // Récupérer les notes de l'étudiant
    $stmt = $pdo->prepare("
        SELECT n.*, c.nom_cours, c.couleur, u.prenom as prof_prenom, u.nom as prof_nom
        FROM notes n
        LEFT JOIN cours c ON n.id_cours = c.id_cours
        LEFT JOIN utilisateurs u ON c.id_professeur = u.id_utilisateur
        WHERE n.id_utilisateur = ?
        ORDER BY n.date_evaluation DESC, c.nom_cours ASC
    ");
    $stmt->execute([$user_id]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Regrouper par matière
    $matieres_notes = [];
    foreach ($notes as $note) {
        $matiere = $note['nom_cours'] ?? 'Matière inconnue';
        if (!isset($matieres_notes[$matiere])) {
            $matieres_notes[$matiere] = [
                'notes' => [],
                'credits' => 3, // Valeur par défaut
                'couleur' => $note['couleur'] ?? '#4facfe',
                'professeur' => ($note['prof_prenom'] ?? '') . ' ' . ($note['prof_nom'] ?? 'Non spécifié')
            ];
        }
        $matieres_notes[$matiere]['notes'][] = $note['note'];
    }
    
    // Calculer les moyennes par matière
    foreach ($matieres_notes as $matiere => $data) {
        $moyenne = array_sum($data['notes']) / count($data['notes']);
        $matieres[] = [
            'nom' => $matiere,
            'moyenne' => round($moyenne, 2),
            'notes' => $data['notes'],
            'credits' => $data['credits'],
            'couleur' => $data['couleur'],
            'professeur' => $data['professeur'],
            'nb_notes' => count($data['notes'])
        ];
    }
    
    // Calculer la moyenne générale
    $total_points = 0;
    $total_credits = 0;
    foreach ($matieres as $matiere) {
        $total_points += $matiere['moyenne'] * $matiere['credits'];
        $total_credits += $matiere['credits'];
    }
    $moyenne_generale = $total_credits > 0 ? round($total_points / $total_credits, 2) : 0;
    
    // Statistiques
    $all_notes = array_column($notes, 'note');
    $stats = [
        'moyenne_generale' => !empty($all_notes) ? round(array_sum($all_notes) / count($all_notes), 2) : 0,
        'note_max' => !empty($all_notes) ? max($all_notes) : 0,
        'note_min' => !empty($all_notes) ? min($all_notes) : 0,
        'total_notes' => count($notes),
        'total_matieres' => count($matieres),
        'credits_total' => $total_credits
    ];
    
} catch (PDOException $e) {
    error_log("Erreur récupération notes: " . $e->getMessage());
    $error_message = "Erreur lors de la récupération des notes";
    // Stats restent à leurs valeurs par défaut (0)
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes & Évaluations - OSBT Connect</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-green: #00C853;      /* Vert vif OSBT */
            --secondary-green: #2E7D32;    /* Vert foncé OSBT */
            --accent-blue: #2196F3;        /* Bleu d'accent */
            --dark-blue: #1565C0;
            --success-color: #4CAF50;
            --danger-color: #f72585;
            --warning-color: #FF9800;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --border-radius: 16px;
            --box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --osbt-light-green: #e8f5e9;
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.2);
            
            /* Pour compatibilité avec le dashboard existant */
            --primary-gradient: linear-gradient(135deg, var(--primary-green) 0%, var(--secondary-green) 100%);
            --secondary-gradient: linear-gradient(135deg, var(--dark-blue) 0%, var(--accent-blue) 100%);
            --accent-gradient: linear-gradient(135deg, var(--accent-blue) 0%, var(--primary-green) 100%);
            --success-gradient: linear-gradient(135deg, var(--success-color) 0%, var(--primary-green) 100%);
            --warning-gradient: linear-gradient(135deg, var(--danger-color) 0%, #FF6B6B 100%);
            --shadow-glass: 0 8px 32px 0 rgba(0, 200, 83, 0.15);
            --shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--osbt-light-green) !important;
            min-height: 100vh;
            overflow-x: hidden;
            color: var(--dark-color) !important;
        }
        
        /* Text colors for better visibility */
        .text-white {
            color: var(--dark-color) !important;
        }
        
        .sidebar h4,
        .sidebar p,
        .nav-links a,
        .header-card h1,
        .header-card p,
        .stat-card h4,
        .stat-card p,
        .content-card h3,
        .matiere-card h5,
        .matiere-card p,
        .table {
            color: var(--dark-color) !important;
        }
        
        .nav-links a:hover,
        .nav-links a.active {
            color: var(--primary-green) !important;
        }
        
        .dashboard-container {
            min-height: 100vh;
            padding: 30px;
        }
        
        .sidebar {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
            padding: 30px;
            height: fit-content;
            box-shadow: var(--box-shadow);
            position: sticky;
            top: 30px;
            transition: var(--transition);
        }
        
        .sidebar:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: white;
            position: relative;
            animation: pulse 2s infinite;
            box-shadow: 0 10px 25px rgba(0, 200, 83, 0.3);
        }
        
        .nav-links {
            list-style: none;
            padding: 0;
        }
        
        .nav-links a {
            display: flex;
            align-items: center;
            padding: 15px;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            transition: var(--transition);
            margin-bottom: 10px;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background: var(--glass-bg);
            transform: translateX(5px);
        }
        
        .main-content {
            padding-left: 30px;
        }
        
        .header-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            color: var(--dark-color);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }
        
        .header-card:hover {
            transform: translateY(-5px) scale(1.01);
            box-shadow: var(--shadow-hover);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
            padding: 25px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            color: var(--dark-color);
        }
        
        .stat-card h4 {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .content-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            border: 1px solid var(--glass-border);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-glass);
        }
        
        .matiere-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid var(--accent-gradient);
            transition: var(--transition);
        }
        
        .matiere-card:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .note-badge {
            background: var(--success-gradient);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .note-badge.low {
            background: var(--warning-gradient);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        
        .btn {
            border-radius: 12px;
            font-weight: 500;
            transition: var(--transition);
            border: none;
        }
        
        .btn-success {
            background: var(--success-gradient);
            color: white;
        }
        
        .btn-outline-primary {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            color: white;
        }
        
        .btn-outline-primary:hover {
            background: var(--accent-gradient);
            border-color: transparent;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .sidebar {
                position: static;
                margin-bottom: 20px;
            }
            
            .main-content {
                padding-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-3 col-md-4">
                    <div class="sidebar">
                        <div class="text-center mb-4">
                            <div class="profile-avatar">📊</div>
                            <h5><?php echo htmlspecialchars($user_data['user_prenom'] . ' ' . $user_data['user_nom']); ?></h5>
                            <span class="badge bg-success mb-2"><?php echo htmlspecialchars($user_data['user_role']); ?></span>
                            <p class="small opacity-75">Promotion <?php echo htmlspecialchars($promotion); ?></p>
                        </div>
                        
                        <ul class="nav-links">
                            <li><a href="dashboard.php"><i class="fas fa-home me-2"></i> Tableau de bord</a></li>
                            <li><a href="cours_devoirs.php"><i class="fas fa-book me-2"></i> Cours & Devoirs</a></li>
                            <li><a href="notes.php" class="active"><i class="fas fa-chart-bar me-2"></i> Mes notes</a></li>
                            <li><a href="emploi_du_temps.php"><i class="fas fa-calendar-alt me-2"></i> Emploi du temps</a></li>
                            <li><a href="messagerie.php"><i class="fas fa-envelope me-2"></i> Messages</a></li>
                            <li><a href="profil.php"><i class="fas fa-user me-2"></i> Mon profil</a></li>
                            <?php if($user_data['user_role'] === 'Admin'): ?>
                                <li><a href="admin.php"><i class="fas fa-cog me-2"></i> Administration</a></li>
                            <?php endif; ?>
                        </ul>
                        
                        <div class="mt-4 pt-3 border-top border-white-20">
                            <a href="logout.php" class="btn btn-outline-danger w-100">
                                <i class="fas fa-sign-out-alt"></i> Déconnexion
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="col-lg-9 col-md-8">
                    <!-- Header -->
                    <div class="header-card">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1 class="display-5 fw-bold mb-3">
                                    <i class="fas fa-chart-line me-3"></i> Notes & Évaluations
                                </h1>
                                <p class="lead">Suivez votre progression académique</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn btn-success mb-2 w-100" onclick="exportPDF()">
                                    <i class="fas fa-file-pdf me-2"></i> Bulletin PDF
                                </button>
                                <button class="btn btn-outline-primary w-100" onclick="window.print()">
                                    <i class="fas fa-print me-2"></i> Imprimer
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stats -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-graduation-cap fa-2x mb-3"></i>
                            <h4><?php echo $stats['moyenne_generale']; ?>/20</h4>
                            <p>Moyenne générale</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-trophy fa-2x mb-3"></i>
                            <h4><?php echo $stats['note_max']; ?>/20</h4>
                            <p>Meilleure note</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-chart-line fa-2x mb-3"></i>
                            <h4><?php echo $stats['note_min']; ?>/20</h4>
                            <p>Note la plus basse</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-book fa-2x mb-3"></i>
                            <h4><?php echo $stats['total_matieres']; ?></h4>
                            <p>Matières suivies</p>
                        </div>
                    </div>
                    
                    <!-- Charts -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="content-card">
                                <h5><i class="fas fa-chart-bar me-2"></i> Progression par matière</h5>
                                <div class="chart-container">
                                    <canvas id="matieresChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="content-card">
                                <h5><i class="fas fa-chart-pie me-2"></i> Répartition des notes</h5>
                                <div class="chart-container">
                                    <canvas id="repartitionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Matières -->
                    <div class="content-card">
                        <h5><i class="fas fa-book-open me-2"></i> Détail par matière</h5>
                        
                        <?php if(!empty($matieres)): ?>
                            <?php foreach($matieres as $matiere): ?>
                                <div class="matiere-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h6>
                                                <span class="badge me-2" style="background: <?php echo $matiere['couleur']; ?>;">
                                                    <?php echo htmlspecialchars($matiere['nom']); ?>
                                                </span>
                                            </h6>
                                            <p class="small opacity-75 mb-1">
                                                <i class="fas fa-user-tie me-1"></i> <?php echo htmlspecialchars($matiere['professeur']); ?>
                                                <span class="ms-3"><i class="fas fa-credit-card me-1"></i> <?php echo $matiere['credits']; ?> crédits</span>
                                            </p>
                                            <p class="small opacity-75 mb-0">
                                                <i class="fas fa-list-ol me-1"></i> <?php echo $matiere['nb_notes']; ?> évaluation(s)
                                            </p>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <div class="note-badge <?php echo $matiere['moyenne'] < 10 ? 'low' : ''; ?>">
                                                <?php echo $matiere['moyenne']; ?>/20
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <button class="btn btn-outline-primary btn-sm me-2" onclick="showDetails('<?php echo htmlspecialchars($matiere['nom']); ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-primary btn-sm" onclick="showProgression('<?php echo htmlspecialchars($matiere['nom']); ?>')">
                                                <i class="fas fa-chart-line"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="progress mt-3" style="height: 6px;">
                                        <div class="progress-bar" style="width: <?php echo ($matiere['moyenne'] / 20) * 100; ?>%; background: <?php echo $matiere['couleur']; ?>;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chart-bar fa-3x opacity-50 mb-3"></i>
                                <h5 class="opacity-75">Aucune note disponible</h5>
                                <p class="opacity-50">Vos notes apparaîtront ici une fois les évaluations enregistrées</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Historique -->
                    <div class="content-card">
                        <h5><i class="fas fa-history me-2"></i> Historique des évaluations</h5>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Matière</th>
                                        <th>Type</th>
                                        <th>Note</th>
                                        <th>Coefficient</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(!empty($notes)): ?>
                                        <?php foreach($notes as $note): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($note['date_evaluation'])); ?></td>
                                                <td>
                                                    <span class="badge" style="background: <?php echo $note['couleur']; ?>;">
                                                        <?php echo htmlspecialchars($note['nom_cours']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($note['type_evaluation'] ?? 'Examen'); ?></td>
                                                <td>
                                                    <span class="note-badge <?php echo $note['note'] < 10 ? 'low' : ''; ?>">
                                                        <?php echo $note['note']; ?>/20
                                                    </span>
                                                </td>
                                                <td><?php echo $note['coefficient'] ?? 1; ?></td>
                                                <td>
                                                    <button class="btn btn-outline-primary btn-sm" onclick="showNoteDetails(<?php echo $note['id_note']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center opacity-50">Aucune évaluation enregistrée</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Chart.js configurations
        const matieresData = <?php echo json_encode(array_map(function($m) { return ['name' => $m['nom'], 'moyenne' => $m['moyenne']]; }, $matieres)); ?>;
        const notesData = <?php echo json_encode(array_column($notes, 'note')); ?>;
        
        // Matieres Chart
        const matieresCtx = document.getElementById('matieresChart').getContext('2d');
        new Chart(matieresCtx, {
            type: 'bar',
            data: {
                labels: matieresData.map(m => m.name),
                datasets: [{
                    label: 'Moyenne',
                    data: matieresData.map(m => m.moyenne),
                    backgroundColor: 'rgba(79, 172, 254, 0.6)',
                    borderColor: 'rgba(79, 172, 254, 1)',
                    borderWidth: 2,
                    borderRadius: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 20,
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { color: 'rgba(255, 255, 255, 0.7)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: 'rgba(255, 255, 255, 0.7)' }
                    }
                }
            }
        });
        
        // Repartition Chart
        const repartitionCtx = document.getElementById('repartitionChart').getContext('2d');
        const noteRanges = {
            '0-8': notesData.filter(n => n <= 8).length,
            '9-12': notesData.filter(n => n > 8 && n <= 12).length,
            '13-16': notesData.filter(n => n > 12 && n <= 16).length,
            '17-20': notesData.filter(n => n > 16).length
        };
        
        new Chart(repartitionCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(noteRanges),
                datasets: [{
                    data: Object.values(noteRanges),
                    backgroundColor: [
                        'rgba(250, 112, 154, 0.8)',
                        'rgba(254, 225, 64, 0.8)',
                        'rgba(79, 172, 254, 0.8)',
                        'rgba(67, 233, 123, 0.8)'
                    ]
                }]
            }
        });
        
        // Functions
        function showDetails(matiere) {
            alert(`Détails pour: ${matiere}\n\nFonctionnalité à implémenter`);
        }
        
        function showProgression(matiere) {
            alert(`Progression pour: ${matiere}\n\nFonctionnalité à implémenter`);
        }
        
        function showNoteDetails(noteId) {
            alert(`Détails note #${noteId}\n\nFonctionnalité à implémenter`);
        }
        
        function exportPDF() {
            alert(`Export PDF bulletin\n\nFonctionnalité à implémenter`);
        }
    </script>
</body>
</html>
