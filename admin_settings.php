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

$user_id = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'];
$user_prenom = $_SESSION['user_prenom'];

try {
    // === PARAMÈTRES SYSTÈME ===
    $stmt = $pdo->query("SELECT * FROM parametres_systeme ORDER BY categorie, nom");
    $settings = $stmt->fetchAll();
    
    // === ORGANISER LES PARAMÈTRES PAR CATÉGORIE ===
    $settings_by_category = [];
    foreach ($settings as $setting) {
        $settings_by_category[$setting['categorie']][] = $setting;
    }
    
} catch (PDOException $e) {
    $settings = [];
    $settings_by_category = [];
    error_log("Erreur settings admin: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - OSBT Connect</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Palette OSBT */
            --osbt-primary: #00C853;
            --osbt-primary-dark: #2E7D32;
            --osbt-blue: #2196F3;
            --osbt-light: #f8fafc;
            --osbt-dark: #0f172a;
            --osbt-gray: #64748b;
            --osbt-gray-light: #e2e8f0;
            
            /* Neutrals */
            --white: #FFFFFF;
            --gray-50: #f8f9fa;
            --gray-100: #e9ecef;
            --gray-200: #dee2e6;
            --gray-300: #ced4da;
            --gray-400: #adb5bd;
            --gray-500: #6c757d;
            --gray-600: #495057;
            --gray-700: #343a40;
            --gray-800: #212529;
            --gray-900: #111827;
            
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
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }
        
        .header {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--osbt-primary);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            color: var(--osbt-primary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            color: var(--osbt-primary-dark);
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: var(--spacing-xl);
        }
        
        .settings-section {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .settings-list {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }
        
        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-md);
            background: var(--gray-50);
            border-radius: var(--radius-md);
            transition: all 0.3s ease;
        }
        
        .setting-item:hover {
            background: var(--gray-100);
        }
        
        .setting-info {
            flex: 1;
        }
        
        .setting-name {
            font-weight: 500;
            color: var(--gray-900);
            margin-bottom: var(--spacing-xs);
        }
        
        .setting-description {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .setting-control {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }
        
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 24px;
            background: var(--gray-300);
            border-radius: var(--radius-full);
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .toggle-switch.active {
            background: var(--osbt-primary);
        }
        
        .toggle-slider {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background: var(--white);
            border-radius: var(--radius-full);
            transition: transform 0.3s ease;
        }
        
        .toggle-switch.active .toggle-slider {
            transform: translateX(26px);
        }
        
        .text-input {
            padding: var(--spacing-sm) var(--spacing-md);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            width: 200px;
            transition: border-color 0.3s ease;
        }
        
        .text-input:focus {
            outline: none;
            border-color: var(--osbt-primary);
            box-shadow: 0 0 0 2px rgba(0, 200, 83, 0.2);
        }
        
        .number-input {
            padding: var(--spacing-sm) var(--spacing-md);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            width: 100px;
            transition: border-color 0.3s ease;
        }
        
        .number-input:focus {
            outline: none;
            border-color: var(--osbt-primary);
            box-shadow: 0 0 0 2px rgba(0, 200, 83, 0.2);
        }
        
        .btn {
            padding: var(--spacing-sm) var(--spacing-md);
            border: none;
            border-radius: var(--radius-md);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: var(--osbt-primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: var(--osbt-primary-dark);
        }
        
        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-secondary:hover {
            background: var(--gray-300);
        }
        
        .empty-state {
            text-align: center;
            padding: var(--spacing-2xl);
            color: var(--gray-500);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: var(--spacing-md);
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: var(--spacing-md);
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <a href="admin_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Retour au dashboard
            </a>
            
            <h1 class="header-title">Paramètres Système</h1>
        </div>
        
        <!-- Settings Grid -->
        <div class="settings-grid">
            <?php if (empty($settings_by_category)): ?>
                <div class="empty-state">
                    <i class="fas fa-cog"></i>
                    <h3>Aucun paramètre</h3>
                    <p>Aucun paramètre système n'est configuré pour le moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($settings_by_category as $category => $category_settings): ?>
                    <div class="settings-section">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i class="fas fa-cog"></i>
                                <?php echo ucfirst($category); ?>
                            </h2>
                            <button class="btn btn-primary" onclick="saveCategory('<?php echo $category; ?>')">
                                <i class="fas fa-save"></i>
                                Sauvegarder
                            </button>
                        </div>
                        
                        <div class="settings-list">
                            <?php foreach ($category_settings as $setting): ?>
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <div class="setting-name"><?php echo htmlspecialchars($setting['nom']); ?></div>
                                        <div class="setting-description"><?php echo htmlspecialchars($setting['description'] ?? ''); ?></div>
                                    </div>
                                    
                                    <div class="setting-control">
                                        <?php if ($setting['type'] === 'boolean'): ?>
                                            <div class="toggle-switch <?php echo $setting['valeur'] ? 'active' : ''; ?>" 
                                                 onclick="toggleSetting(this, '<?php echo $setting['id_parametre']; ?>')">
                                                <div class="toggle-slider"></div>
                                            </div>
                                        <?php elseif ($setting['type'] === 'text'): ?>
                                            <input type="text" 
                                                   class="text-input" 
                                                   value="<?php echo htmlspecialchars($setting['valeur']); ?>"
                                                   id="setting_<?php echo $setting['id_parametre']; ?>"
                                                   onchange="updateSetting('<?php echo $setting['id_parametre']; ?>', this.value)">
                                        <?php elseif ($setting['type'] === 'number'): ?>
                                            <input type="number" 
                                                   class="number-input" 
                                                   value="<?php echo htmlspecialchars($setting['valeur']); ?>"
                                                   id="setting_<?php echo $setting['id_parametre']; ?>"
                                                   onchange="updateSetting('<?php echo $setting['id_parametre']; ?>', this.value)">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function toggleSwitch(element, settingId) {
            element.classList.toggle('active');
            const isActive = element.classList.contains('active');
            
            // Envoyer la mise à jour au serveur
            fetch('admin_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=toggle&setting_id=' + settingId + '&value=' + (isActive ? '1' : '0')
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Paramètre mis à jour', 'Le paramètre a été sauvegardé avec succès', 'success');
                } else {
                    showNotification('Erreur', data.message || 'Une erreur est survenue', 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur', 'Une erreur réseau est survenue', 'error');
            });
        }
        
        function updateSetting(settingId, value) {
            // Envoyer la mise à jour au serveur
            fetch('admin_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=update&setting_id=' + settingId + '&value=' + encodeURIComponent(value)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Paramètre mis à jour', 'Le paramètre a été sauvegardé avec succès', 'success');
                } else {
                    showNotification('Erreur', data.message || 'Une erreur est survenue', 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur', 'Une erreur réseau est survenue', 'error');
            });
        }
        
        function saveCategory(category) {
            showNotification('Sauvegarde', 'Les paramètres de la catégorie "' + category + '" ont été sauvegardés', 'info');
        }
        
        function showNotification(title, message, type) {
            // Créer une notification simple (pour l'instant)
            alert(title + ': ' + message);
        }
        
        // Gérer les actions POST
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            $settingId = $_POST['setting_id'] ?? '';
            $value = $_POST['value'] ?? '';
            
            if ($action === 'toggle' && !empty($settingId)) {
                $newValue = ($value === '1') ? 1 : 0;
                $stmt = $pdo->prepare("UPDATE parametres_systeme SET valeur = ? WHERE id_parametre = ?");
                $stmt->execute([$newValue, $settingId]);
                
                echo json_encode(['success' => true]);
                exit;
            } elseif ($action === 'update' && !empty($settingId)) {
                $stmt = $pdo->prepare("UPDATE parametres_systeme SET valeur = ? WHERE id_parametre = ?");
                $stmt->execute([$value, $settingId]);
                
                echo json_encode(['success' => true]);
                exit;
            }
        }
        ?>
    </script>
</body>
</html>
