<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';

$user_id = $_SESSION['user_id'];

// Sécurisation : récupérer le rôle depuis la BDD si pas en session
if (!isset($_SESSION['role'])) {
    $stmt = $pdo->prepare("SELECT role FROM utilisateurs WHERE id_utilisateur = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
    $_SESSION['role'] = $user_data['role'];
}
$role = $_SESSION['role'];

// Récupérer les infos de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// --- Données pour un ÉTUDIANT ---
if ($role == 'etudiant') {
    // Sessions à venir
    $stmt = $pdo->prepare("
        SELECT s.*, 
               u.prenom as mentor_prenom, u.nom as mentor_nom,
               m.nom as matiere_nom
        FROM sessions_mentorat s
        JOIN utilisateurs u ON s.mentor_id = u.id_utilisateur
        JOIN matieres m ON s.matiere_id = m.id_matiere
        WHERE s.etudiant_id = ? AND s.statut IN ('demande_acceptee', 'session_confirmee')
        ORDER BY s.date_session ASC
    ");
    $stmt->execute([$user_id]);
    $prochaines_sessions = $stmt->fetchAll();

    // Historique
    $stmt = $pdo->prepare("
        SELECT s.*, 
               u.prenom as mentor_prenom, u.nom as mentor_nom,
               m.nom as matiere_nom
        FROM sessions_mentorat s
        JOIN utilisateurs u ON s.mentor_id = u.id_utilisateur
        JOIN matieres m ON s.matiere_id = m.id_matiere
        WHERE s.etudiant_id = ? AND s.statut IN ('session_terminee', 'session_annulee')
        ORDER BY s.date_session DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $historique = $stmt->fetchAll();
}

// --- Données pour un MENTOR ---
if ($role == 'mentor') {
    // Demandes en attente
    $stmt = $pdo->prepare("
        SELECT s.*, 
               u.prenom as etudiant_prenom, u.nom as etudiant_nom,
               m.nom as matiere_nom
        FROM sessions_mentorat s
        JOIN utilisateurs u ON s.etudiant_id = u.id_utilisateur
        JOIN matieres m ON s.matiere_id = m.id_matiere
        WHERE s.mentor_id = ? AND s.statut = 'demande_envoyee'
        ORDER BY s.date_session ASC
    ");
    $stmt->execute([$user_id]);
    $demandes_attente = $stmt->fetchAll();

    // Sessions à venir
    $stmt = $pdo->prepare("
        SELECT s.*, 
               u.prenom as etudiant_prenom, u.nom as etudiant_nom,
               m.nom as matiere_nom
        FROM sessions_mentorat s
        JOIN utilisateurs u ON s.etudiant_id = u.id_utilisateur
        JOIN matieres m ON s.matiere_id = m.id_matiere
        WHERE s.mentor_id = ? AND s.statut IN ('demande_acceptee', 'session_confirmee')
        ORDER BY s.date_session ASC
    ");
    $stmt->execute([$user_id]);
    $sessions_mentor = $stmt->fetchAll();

    // Statistiques du mentor
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sessions,
            AVG(note_etudiant) as note_moyenne,
            SUM(CASE WHEN statut = 'session_terminee' THEN 1 ELSE 0 END) as sessions_terminees
        FROM sessions_mentorat
        WHERE mentor_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats_mentor = $stmt->fetch();
}

// --- Suggestions de mentors (pour tous) ---
$stmt = $pdo->prepare("
    SELECT 
        u.id_utilisateur, u.prenom, u.nom, u.bio, u.photo_profil,
        MAX(cm.note_moyenne) as note_moyenne, 
        SUM(cm.nombre_seances) as nombre_seances,
        GROUP_CONCAT(DISTINCT m.nom SEPARATOR ', ') as matieres
    FROM vue_mentors_disponibles v
    JOIN utilisateurs u ON v.id_utilisateur = u.id_utilisateur
    JOIN competences_mentors cm ON u.id_utilisateur = cm.mentor_id
    JOIN matieres m ON cm.matiere_id = m.id_matiere
    GROUP BY u.id_utilisateur
    ORDER BY note_moyenne DESC, nombre_seances DESC
    LIMIT 3
");
$stmt->execute();
$suggestions_mentors = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentorat - OSBT Connect</title>
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
            min-height: 100vh;
            padding: var(--spacing-xl);
            line-height: 1.6;
        }
        
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
        }
        
        .header {
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            background: white; 
            border: 1px solid var(--notion-gray-200); 
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl); 
            margin-bottom: var(--spacing-2xl);
            box-shadow: var(--shadow-sm);
        }
        
        .header h1 {
            font-size: 2rem;
            background: linear-gradient(90deg, var(--notion-green), #00e676);
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
        
        .nav-links a {
            color: var(--notion-gray-600); 
            text-decoration: none; 
            margin-left: var(--spacing-xl);
            transition: color var(--transition-base);
        }
        
        .nav-links a:hover { 
            color: var(--primary);
        }
        
        .grid-2 {
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: var(--spacing-xl);
        }
        
        .card {
            background: white; 
            border: 1px solid var(--notion-gray-200); 
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl); 
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-base);
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        
        .card-title {
            font-size: 1.25rem; 
            margin-bottom: var(--spacing-lg);
            display: flex; 
            align-items: center; 
            gap: var(--spacing-sm);
            color: var(--notion-gray-900);
            font-weight: 600;
        }
        
        .btn {
            display: inline-block; 
            padding: var(--spacing-sm) var(--spacing-lg);
            background: var(--primary); 
            color: white; 
            text-decoration: none;
            border-radius: var(--radius-lg); 
            font-weight: 600; 
            transition: all var(--transition-base);
            border: none;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
        }
        
        .btn:hover { 
            background: var(--primary-dark); 
            transform: translateY(-2px); 
            box-shadow: var(--shadow-md);
        }
        
        .btn-outline {
            background: transparent; 
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover { 
            background: var(--notion-gray-50);
        }
        
        .session-item {
            background: var(--notion-gray-50); 
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg); 
            margin-bottom: var(--spacing-sm);
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            transition: all var(--transition-base);
        }
        
        .session-item:hover {
            background: white;
            border-color: var(--primary);
            box-shadow: var(--shadow-sm);
        }
        
        .mentor-card {
            display: flex; 
            align-items: center; 
            gap: var(--spacing-md);
            background: var(--notion-gray-50); 
            border: 1px solid var(--notion-gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg); 
            margin-bottom: var(--spacing-sm);
            transition: all var(--transition-base);
        }
        
        .mentor-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            background: white;
        }
        
        .mentor-avatar {
            width: 50px; 
            height: 50px; 
            border-radius: var(--radius-full);
            background: var(--gradient-green); 
            display: flex;
            align-items: center; 
            justify-content: center;
            font-size: 1.5rem; 
            font-weight: bold;
            color: white;
        }
        
        .badge {
            display: inline-block; 
            padding: var(--spacing-xs) var(--spacing-sm); 
            border-radius: var(--radius-full);
            font-size: 0.75rem; 
            background: var(--notion-gray-100); 
            color: var(--notion-gray-700);
            border: 1px solid var(--notion-gray-200);
        }
        
        .statut-demo { 
            color: #FFA726; 
            border-color: #FFA726;
        }
        .statut-confirme { 
            color: #66BB6A; 
            border-color: #66BB6A;
        }
        .statut-termine { 
            color: #42A5F5; 
            border-color: #42A5F5;
        }
        
        /* Alert styles */
        .alert-construction {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: var(--radius-lg);
            padding: var(--spacing-md);
            margin-top: var(--spacing-md);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            color: #ffc107;
            font-weight: 600;
        }
        
        @media (max-width: 992px) {
            .grid-2 { 
                grid-template-columns: 1fr; 
            }
            .header { 
                flex-direction: column; 
                text-align: center; 
                gap: var(--spacing-lg); 
            }
            .nav-links a { 
                margin: 0 var(--spacing-sm); 
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: var(--spacing-lg);
            }
            
            .header {
                padding: var(--spacing-lg);
            }
            
            .card {
                padding: var(--spacing-lg);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1><i class="fas fa-users"></i> Mentorat</h1>
                <p style="color: var(--notion-gray-600);">Entraide entre étudiants</p>
            </div>
            <div class="nav-links">
                <a href="student_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="learning-center.php"><i class="fas fa-graduation-cap"></i> Learning Center</a>
                <a href="profil.php"><i class="fas fa-user"></i> Mon profil</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
            </div>
        </div>

        <!-- Messages d'alerte / bienvenue -->
        <div style="margin-bottom: var(--spacing-2xl);">
            <p>👋 Bienvenue, <strong><?= htmlspecialchars($user['prenom']) ?></strong> !</p>
        </div>

        <!-- Actions rapides -->
        <div style="display: flex; gap: var(--spacing-md); flex-wrap: wrap; margin-bottom: var(--spacing-2xl);">
            <a href="recherche-mentors.php" class="btn">
                <i class="fas fa-search"></i> Trouver un mentor
            </a>
            <?php if ($role == 'mentor'): ?>
                <a href="disponibilites.php" class="btn btn-outline" onclick="showConstructionAlert(event)">
                    <i class="fas fa-calendar-alt"></i> Gérer mes disponibilités
                </a>
                <a href="mes-sessions.php" class="btn btn-outline" onclick="showConstructionAlert(event)">
                    <i class="fas fa-clock"></i> Mes sessions
                </a>
            <?php else: ?>
                <a href="mes-sessions.php" class="btn btn-outline" onclick="showConstructionAlert(event)">
                    <i class="fas fa-history"></i> Mes demandes
                </a>
            <?php endif; ?>
        </div>

        <!-- Contenu principal : 2 colonnes -->
        <div class="grid-2">
            <!-- Colonne gauche : Sessions / Demandes -->
            <div>
                <?php if ($role == 'mentor'): ?>
                    <!-- Demandes en attente (mentor) -->
                    <div class="card">
                        <div class="card-title">
                            <i class="fas fa-inbox"></i> Demandes en attente
                            <?php if (count($demandes_attente) > 0): ?>
                                <span class="badge"><?= count($demandes_attente) ?> nouvelle(s)</span>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($demandes_attente)): ?>
                            <p style="color: #aaa;">Aucune demande en attente.</p>
                        <?php else: ?>
                            <?php foreach ($demandes_attente as $d): ?>
                                <div class="session-item">
                                    <div>
                                        <strong><?= htmlspecialchars($d['etudiant_prenom'] . ' ' . $d['etudiant_nom']) ?></strong><br>
                                        <small style="color: #aaa;"><?= htmlspecialchars($d['matiere_nom']) ?></small><br>
                                        <small><?= date('d/m/Y H:i', strtotime($d['date_session'])) ?></small>
                                    </div>
                                    <div>
                                        <a href="traiter-demande.php?id=<?= $d['id_session'] ?>&action=accepter" class="btn" style="padding: 5px 15px; margin-right: 5px;">✓</a>
                                        <a href="traiter-demande.php?id=<?= $d['id_session'] ?>&action=refuser" class="btn" style="background: #e74c3c; padding: 5px 15px;" onclick="showConstructionAlert(event)">✗</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Prochaines sessions (mentor) -->
                    <div class="card">
                        <div class="card-title">
                            <i class="fas fa-calendar-check"></i> Mes prochaines sessions
                        </div>
                        <?php if (empty($sessions_mentor)): ?>
                            <p style="color: #aaa;">Aucune session à venir.</p>
                        <?php else: ?>
                            <?php foreach ($sessions_mentor as $s): ?>
                                <div class="session-item">
                                    <div>
                                        <strong><?= htmlspecialchars($s['etudiant_prenom'] . ' ' . $s['etudiant_nom']) ?></strong><br>
                                        <small style="color: #aaa;"><?= htmlspecialchars($s['matiere_nom']) ?></small><br>
                                        <small><?= date('d/m/Y H:i', strtotime($s['date_session'])) ?></small>
                                    </div>
                                    <span class="badge statut-confirme">Confirmé</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Étudiant : sessions à venir -->
                    <div class="card">
                        <div class="card-title">
                            <i class="fas fa-calendar-check"></i> Mes prochaines sessions
                        </div>
                        <?php if (empty($prochaines_sessions)): ?>
                            <p style="color: #aaa;">Vous n'avez aucune session programmée.</p>
                        <?php else: ?>
                            <?php foreach ($prochaines_sessions as $s): ?>
                                <div class="session-item">
                                    <div>
                                        <strong><?= htmlspecialchars($s['mentor_prenom'] . ' ' . $s['mentor_nom']) ?></strong><br>
                                        <small style="color: #aaa;"><?= htmlspecialchars($s['matiere_nom']) ?></small><br>
                                        <small><?= date('d/m/Y H:i', strtotime($s['date_session'])) ?></small>
                                    </div>
                                    <span class="badge statut-confirme">Confirmé</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Historique -->
                    <div class="card">
                        <div class="card-title">
                            <i class="fas fa-history"></i> Dernières sessions
                        </div>
                        <?php if (empty($historique)): ?>
                            <p style="color: #aaa;">Aucun historique.</p>
                        <?php else: ?>
                            <?php foreach ($historique as $h): ?>
                                <div class="session-item">
                                    <div>
                                        <strong><?= htmlspecialchars($h['mentor_prenom'] . ' ' . $h['mentor_nom']) ?></strong><br>
                                        <small style="color: #aaa;"><?= htmlspecialchars($h['matiere_nom']) ?></small><br>
                                        <small><?= date('d/m/Y', strtotime($h['date_session'])) ?></small>
                                    </div>
                                    <?php if ($h['note_etudiant']): ?>
                                        <span class="badge">Note: <?= $h['note_etudiant'] ?>/5</span>
                                    <?php else: ?>
                                        <a href="noter-session.php?id=<?= $h['id_session'] ?>" style="color: var(--primary);" onclick="showConstructionAlert(event)">Noter</a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Colonne droite : Suggestions & Stats -->
            <div>
                <!-- Statistiques Mentor (si mentor) -->
                <?php if ($role == 'mentor'): ?>
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-chart-line"></i> Mes statistiques
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 15px; text-align: center;">
                            <div style="font-size: 32px; font-weight: 700; color: var(--primary);"><?= $stats_mentor['sessions_terminees'] ?? 0 ?></div>
                            <div style="color: #aaa;">Sessions terminées</div>
                        </div>
                        <div style="background: rgba(255,255,255,0.05); border-radius: 12px; padding: 15px; text-align: center;">
                            <div style="font-size: 32px; font-weight: 700; color: var(--primary);"><?= round($stats_mentor['note_moyenne'] ?? 0, 1) ?>/5</div>
                            <div style="color: #aaa;">Note moyenne</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Suggestions de mentors -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-star"></i> Mentors recommandés
                    </div>
                    <?php if (empty($suggestions_mentors)): ?>
                        <p style="color: var(--notion-gray-600);">Aucun mentor disponible pour le moment.</p>
                    <?php else: ?>
                        <?php foreach ($suggestions_mentors as $m): ?>
                            <div class="mentor-card">
                                <div class="mentor-avatar">
                                    <?= strtoupper(substr($m['prenom'], 0, 1) . substr($m['nom'], 0, 1)) ?>
                                </div>
                                <div style="flex: 1;">
                                    <strong><?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></strong><br>
                                    <small style="color: var(--notion-gray-600);"><?= htmlspecialchars(substr($m['matieres'], 0, 30)) ?>…</small><br>
                                    <span style="font-size: 13px;">
                                        <i class="fas fa-star" style="color: #FFD700;"></i> <?= round($m['note_moyenne'], 1) ?>
                                        • <?= $m['nombre_seances'] ?> sessions
                                    </span>
                                </div>
                                <a href="profil-mentor.php?id=<?= $m['id_utilisateur'] ?>" class="btn" style="padding: 5px 15px;" onclick="showConstructionAlert(event)">Voir</a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div style="margin-top: var(--spacing-lg); text-align: center;">
                        <a href="recherche-mentors.php" style="color: var(--primary); text-decoration: none;">
                            Voir tous les mentors <i class="fas fa-arrow-right"></i>
                        </a>
                      
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function showConstructionAlert(event) {
        event.preventDefault();
        alert('🚧 Cette fonctionnalité est en construction et sera bientôt disponible !');
    }
    </script>
</body>
</html>