<?php
session_start();
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

// === TRAITEMENT DES ACTIONS ===
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create':
                    // Validation
                    $required_fields = ['nom', 'prenom', 'email', 'role', 'noma', 'mot_de_passe'];
                    foreach ($required_fields as $field) {
                        if (empty($_POST[$field])) {
                            throw new Exception("Champ $field requis");
                        }
                    }
                    
                    // Vérifier que le NOMA n'existe pas déjà
                    $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE noma = ?");
                    $stmt->execute([$_POST['noma']]);
                    if ($stmt->fetch()) {
                        throw new Exception('Ce NOMA existe déjà');
                    }
                    
                    // Vérifier que l'email n'existe pas déjà
                    $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE email = ?");
                    $stmt->execute([$_POST['email']]);
                    if ($stmt->fetch()) {
                        throw new Exception('Cet email existe déjà');
                    }
                    
                    // Hasher le mot de passe
                    $hashed_password = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);
                    
                    // Insérer l'utilisateur
                    $sql = "INSERT INTO utilisateurs (nom, prenom, email, role, noma, mot_de_passe, promotion, filiere, telephone, adresse, date_naissance, lieu_naissance, nationalite, sexe, est_actif, date_inscription) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
                    
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute([
                        $_POST['nom'],
                        $_POST['prenom'],
                        $_POST['email'],
                        $_POST['role'],
                        $_POST['noma'],
                        $hashed_password,
                        $_POST['promotion'] ?? null,
                        $_POST['filiere'] ?? null,
                        $_POST['telephone'] ?? null,
                        $_POST['adresse'] ?? null,
                        $_POST['date_naissance'] ?? null,
                        $_POST['lieu_naissance'] ?? null,
                        $_POST['nationalite'] ?? null,
                        $_POST['sexe'] ?? null
                    ]);
                    
                    if ($result) {
                        $message = 'Utilisateur créé avec succès';
                        $message_type = 'success';
                    } else {
                        throw new Exception('Erreur lors de la création');
                    }
                    break;
                    
                case 'update':
                    $user_id = $_POST['user_id'] ?? '';
                    if (empty($user_id)) {
                        throw new Exception('ID utilisateur requis');
                    }
                    
                    // Vérifier que l'utilisateur existe
                    $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE id_utilisateur = ?");
                    $stmt->execute([$user_id]);
                    if (!$stmt->fetch()) {
                        throw new Exception('Utilisateur non trouvé');
                    }
                    
                    // Construire la requête de mise à jour
                    $update_fields = [];
                    $params = [];
                    
                    $allowed_fields = ['nom', 'prenom', 'email', 'role', 'promotion', 'filiere', 'telephone', 'adresse', 'date_naissance', 'lieu_naissance', 'nationalite', 'sexe', 'est_actif'];
                    
                    foreach ($allowed_fields as $field) {
                        if (isset($_POST[$field])) {
                            $update_fields[] = "$field = ?";
                            $params[] = $_POST[$field];
                        }
                    }
                    
                    // Si le mot de passe est fourni, le mettre à jour
                    if (!empty($_POST['mot_de_passe'])) {
                        $update_fields[] = "mot_de_passe = ?";
                        $params[] = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);
                    }
                    
                    if (empty($update_fields)) {
                        throw new Exception('Aucun champ à mettre à jour');
                    }
                    
                    $params[] = $user_id; // Pour la clause WHERE
                    
                    $sql = "UPDATE utilisateurs SET " . implode(", ", $update_fields) . " WHERE id_utilisateur = ?";
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute($params);
                    
                    if ($result) {
                        $message = 'Utilisateur mis à jour avec succès';
                        $message_type = 'success';
                    } else {
                        throw new Exception('Erreur lors de la mise à jour');
                    }
                    break;
                    
                case 'delete':
                    $user_id = $_POST['user_id'] ?? '';
                    if (empty($user_id)) {
                        throw new Exception('ID utilisateur requis');
                    }
                    
                    // Empêcher la suppression de l'admin courant
                    if ($user_id == $_SESSION['user_id']) {
                        throw new Exception('Vous ne pouvez pas supprimer votre propre compte');
                    }
                    
                    // Désactiver l'utilisateur
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET est_actif = 0 WHERE id_utilisateur = ?");
                    $result = $stmt->execute([$user_id]);
                    
                    if ($result) {
                        $message = 'Utilisateur désactivé avec succès';
                        $message_type = 'success';
                    } else {
                        throw new Exception('Erreur lors de la désactivation');
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// === RÉCUPÉRATION DES UTILISATEURS ===
$users = [];
try {
    $stmt = $pdo->query("
        SELECT id_utilisateur, noma, nom, prenom, email, role, promotion, filiere, telephone, est_actif, date_inscription 
        FROM utilisateurs 
        ORDER BY date_inscription DESC
    ");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur récupération utilisateurs: " . $e->getMessage());
}

// Fonctions utilitaires
function getRoleIcon($role) {
    $icons = [
        'admin' => '👑',
        'tech' => '💻',
        'business' => '💼',
        'etudiant' => '🎓'
    ];
    return $icons[$role] ?? '👤';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Utilisateurs - OSBT Connect Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --osbt-green: #00C853;
            --osbt-dark-green: #2E7D32;
            --osbt-blue: #2196F3;
            --osbt-dark-blue: #1565C0;
            --osbt-gray: #757575;
            --osbt-light-gray: #f5f5f5;
            --osbt-white: #ffffff;
            --osbt-shadow: 0 2px 10px rgba(0,0,0,0.1);
            --osbt-radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #333;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--osbt-white);
            box-shadow: var(--osbt-shadow);
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 0 1.5rem 2rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--osbt-green);
        }

        .logo i {
            font-size: 2rem;
        }

        .admin-badge {
            background: var(--osbt-green);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-left: auto;
        }

        .nav-menu {
            padding: 1.5rem 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: var(--osbt-gray);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: var(--osbt-light-gray);
            color: var(--osbt-green);
            border-left-color: var(--osbt-green);
        }

        .nav-item.active {
            background: rgba(0, 200, 83, 0.1);
            color: var(--osbt-green);
            border-left-color: var(--osbt-green);
        }

        .nav-item i {
            width: 20px;
            margin-right: 1rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        .header {
            background: var(--osbt-white);
            padding: 1.5rem 2rem;
            border-radius: var(--osbt-radius);
            box-shadow: var(--osbt-shadow);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: var(--osbt-dark-green);
            font-size: 2rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
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
        }

        .btn-secondary {
            background: var(--osbt-gray);
            color: white;
        }

        .btn-danger {
            background: #F44336;
            color: white;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Table */
        .table-container {
            background: var(--osbt-white);
            border-radius: var(--osbt-radius);
            box-shadow: var(--osbt-shadow);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: var(--osbt-light-gray);
            font-weight: 600;
            color: #333;
        }

        tr:hover {
            background: var(--osbt-light-gray);
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .role-badge.admin {
            background: rgba(244, 67, 54, 0.1);
            color: #F44336;
        }

        .role-badge.tech {
            background: rgba(33, 150, 243, 0.1);
            color: var(--osbt-blue);
        }

        .role-badge.business {
            background: rgba(255, 152, 0, 0.1);
            color: #FF9800;
        }

        .role-badge.etudiant {
            background: rgba(0, 200, 83, 0.1);
            color: var(--osbt-green);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-badge.active {
            background: rgba(0, 200, 83, 0.1);
            color: var(--osbt-green);
        }

        .status-badge.inactive {
            background: rgba(244, 67, 54, 0.1);
            color: #F44336;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: var(--osbt-white);
            margin: 5% auto;
            padding: 2rem;
            border-radius: var(--osbt-radius);
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h2 {
            color: var(--osbt-dark-green);
        }

        .close {
            font-size: 2rem;
            cursor: pointer;
            color: var(--osbt-gray);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--osbt-green);
            box-shadow: 0 0 0 2px rgba(0, 200, 83, 0.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .table-container {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                    <span>OSBT Connect</span>
                    <span class="admin-badge">ADMIN</span>
                </div>
            </div>
            
            <nav class="nav-menu">
                <a href="admin_dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="admin_users.php" class="nav-item active">
                    <i class="fas fa-users"></i>
                    <span>Gestion utilisateurs</span>
                </a>
                <a href="admin_courses.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <span>Gestion cours</span>
                </a>
                <a href="admin_planning.php" class="nav-item">
                    <i class="fas fa-calendar"></i>
                    <span>Planning</span>
                </a>
                <a href="admin_announcements.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i>
                    <span>Annonces</span>
                </a>
                <a href="admin_mentorat.php" class="nav-item">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Mentorat</span>
                </a>
                <a href="admin_statistics.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Statistiques</span>
                </a>
                <a href="admin_settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Paramètres</span>
                </a>
                <a href="logout.php" class="nav-item" style="margin-top: auto; border-top: 1px solid #e0e0e0;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <div>
                    <h1>Gestion des Utilisateurs</h1>
                    <p style="color: var(--osbt-gray); margin-top: 0.5rem;">Ajouter, modifier et gérer les utilisateurs</p>
                </div>
                <button class="btn btn-primary" onclick="openModal()">
                    <i class="fas fa-plus"></i>
                    Nouvel utilisateur
                </button>
            </header>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $message_type ?>">
                    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="table-container">
                <div class="table-header">
                    <h3>Liste des utilisateurs</h3>
                    <div>
                        <input type="text" placeholder="Rechercher..." class="form-control" style="width: 200px; display: inline-block;" onkeyup="filterTable(this.value)">
                    </div>
                </div>
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th>NOMA</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Promotion</th>
                            <th>Filière</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['noma'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($user['nom']) ?></td>
                            <td><?= htmlspecialchars($user['prenom']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <span class="role-badge <?= $user['role'] ?>">
                                    <?= getRoleIcon($user['role']) ?> <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($user['promotion'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($user['filiere'] ?? 'N/A') ?></td>
                            <td>
                                <span class="status-badge <?= $user['est_actif'] ? 'active' : 'inactive' ?>">
                                    <?= $user['est_actif'] ? 'Actif' : 'Inactif' ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="editUser(<?= $user['id_utilisateur'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($user['id_utilisateur'] != $_SESSION['user_id']): ?>
                                <button class="btn btn-sm btn-danger" onclick="deleteUser(<?= $user['id_utilisateur'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nouvel utilisateur</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="userForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="user_id" id="userId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" class="form-control" id="nom" name="nom" required>
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prénom *</label>
                        <input type="text" class="form-control" id="prenom" name="prenom" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="noma">NOMA *</label>
                        <input type="text" class="form-control" id="noma" name="noma" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="role">Rôle *</label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="">Sélectionner...</option>
                            <option value="admin">Admin</option>
                            <option value="tech">Tech</option>
                            <option value="business">Business</option>
                            <option value="etudiant">Étudiant</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="mot_de_passe">Mot de passe *</label>
                        <input type="password" class="form-control" id="mot_de_passe" name="mot_de_passe" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="promotion">Promotion</label>
                        <input type="text" class="form-control" id="promotion" name="promotion">
                    </div>
                    <div class="form-group">
                        <label for="filiere">Filière</label>
                        <select class="form-control" id="filiere" name="filiere">
                            <option value="">Sélectionner...</option>
                            <option value="technology">Technology</option>
                            <option value="business">Business</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="telephone">Téléphone</label>
                    <input type="tel" class="form-control" id="telephone" name="telephone">
                </div>
                
                <div class="form-group">
                    <label for="adresse">Adresse</label>
                    <textarea class="form-control" id="adresse" name="adresse" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_naissance">Date de naissance</label>
                        <input type="date" class="form-control" id="date_naissance" name="date_naissance">
                    </div>
                    <div class="form-group">
                        <label for="sexe">Sexe</label>
                        <select class="form-control" id="sexe" name="sexe">
                            <option value="">Sélectionner...</option>
                            <option value="M">Masculin</option>
                            <option value="F">Féminin</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="lieu_naissance">Lieu de naissance</label>
                    <input type="text" class="form-control" id="lieu_naissance" name="lieu_naissance">
                </div>
                
                <div class="form-group">
                    <label for="nationalite">Nationalité</label>
                    <input type="text" class="form-control" id="nationalite" name="nationalite">
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('userModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Nouvel utilisateur';
            document.getElementById('formAction').value = 'create';
            document.getElementById('userForm').reset();
        }

        function closeModal() {
            document.getElementById('userModal').style.display = 'none';
        }

        function editUser(userId) {
            // Récupérer les données utilisateur (pourrait être fait via AJAX)
            // Pour l'instant, on ouvre le modal en mode édition
            document.getElementById('userModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Modifier utilisateur';
            document.getElementById('formAction').value = 'update';
            document.getElementById('userId').value = userId;
            
            // Ici vous devriez charger les données utilisateur dans le formulaire
            // Pour l'exemple, je laisse le formulaire vide
        }

        function deleteUser(userId) {
            if (confirm('Êtes-vous sûr de vouloir désactiver cet utilisateur ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function filterTable(searchTerm) {
            const table = document.getElementById('usersTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm.toLowerCase()) ? '' : 'none';
            }
        }

        // Fermer le modal en cliquant à l'extérieur
        window.onclick = function(event) {
            const modal = document.getElementById('userModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
