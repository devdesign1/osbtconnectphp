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

// === TRAITEMENT DU FORMULAIRE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $classe_id = $_POST['classe_id'] ?? null;
    $matiere_id = $_POST['matiere_id'] ?? null;
    $difficulte = $_POST['difficulte'] ?? 'debutant';
    $est_public = isset($_POST['est_public']) ? 1 : 0;
    $tags = $_POST['tags'] ?? [];
    
    // Validation
    if (empty($titre)) {
        $error = "Le titre du deck est obligatoire";
    } else {
        try {
            // Créer le deck
            $stmt = $pdo->prepare("
                INSERT INTO decks (titre, description, createur_id, classe_id, matiere_id, difficulte, est_public, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$titre, $description, $_SESSION['user_id'], $classe_id, $matiere_id, $difficulte, $est_public]);
            $deck_id = $pdo->lastInsertId();
            
            // Ajouter les tags si fournis
            if (!empty($tags)) {
                $tags_json = json_encode($tags);
                $stmt = $pdo->prepare("
                    UPDATE decks SET tags = ? WHERE id_deck = ?
                ");
                $stmt->execute([$tags_json, $deck_id]);
            }
            
            $success = "Deck créé avec succès !";
            
            // Rediriger vers le dashboard professeur
            header('Location: professor_dashboard.php');
            exit();
            
        } catch (PDOException $e) {
            $error = "Erreur lors de la création du deck: " . $e->getMessage();
        }
    }
}

// Récupérer les classes et matières du professeur
try {
    // D'abord récupérer l'ID du professeur
    $stmt = $pdo->prepare("
        SELECT id_professeur FROM professeurs WHERE id_utilisateur = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $professor_info = $stmt->fetch();
    
    if ($professor_info) {
        $professor_id = $professor_info['id_professeur'];
        
        // Récupérer les classes du professeur
        $stmt = $pdo->prepare("
            SELECT c.id_classe, c.nom, m.nom as matiere_nom, m.id_matiere
            FROM classes c
            LEFT JOIN matieres m ON c.matiere_id = m.id_matiere
            WHERE c.professeur_id = ? AND c.est_active = 1
            ORDER BY c.nom
        ");
        $stmt->execute([$professor_id]);
        $classes = $stmt->fetchAll();
    } else {
        $classes = [];
    }
    
    // Récupérer toutes les matières pour les options
    $stmt = $pdo->query("SELECT id_matiere, nom FROM matieres ORDER BY nom");
    $matieres = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur de chargement des données";
    $classes = [];
    $matieres = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Deck - OSBT Connect</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            padding: 2rem;
            width: 100%;
            max-width: 600px;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            color: var(--osbt-green);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .header p {
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
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--osbt-green);
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-check input {
            width: 20px;
            height: 20px;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--osbt-radius);
            font-size: 1rem;
            cursor: pointer;
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

        .btn-secondary {
            background: var(--osbt-gray);
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--osbt-radius);
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--osbt-green);
            text-decoration: none;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .tags-input {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding: 0.5rem;
            border: 2px solid #e0e0e0;
            border-radius: var(--osbt-radius);
            min-height: 50px;
        }

        .tag {
            background: var(--osbt-green);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tag .remove {
            cursor: pointer;
            font-weight: bold;
        }

        .tag-input {
            border: none;
            outline: none;
            flex: 1;
            min-width: 100px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="professor_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Retour au dashboard
        </a>
        
        <div class="header">
            <h1><i class="fas fa-brain"></i> Créer un Deck</h1>
            <p>Créez du contenu pédagogique pour vos étudiants</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="titre">Titre du deck *</label>
                <input type="text" id="titre" name="titre" class="form-control" 
                       value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" 
                          placeholder="Décrivez le contenu de ce deck..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="classe_id">Classe concernée</label>
                <select id="classe_id" name="classe_id" class="form-control">
                    <option value="">Sélectionner une classe (optionnel)</option>
                    <?php foreach($classes as $classe): ?>
                    <option value="<?= $classe['id_classe'] ?>" 
                            <?= (isset($_POST['classe_id']) && $_POST['classe_id'] == $classe['id_classe']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($classe['nom']) ?> - <?= htmlspecialchars($classe['matiere_nom']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="matiere_id">Matière</label>
                <select id="matiere_id" name="matiere_id" class="form-control">
                    <option value="">Sélectionner une matière (optionnel)</option>
                    <?php foreach($matieres as $matiere): ?>
                    <option value="<?= $matiere['id_matiere'] ?>" 
                            <?= (isset($_POST['matiere_id']) && $_POST['matiere_id'] == $matiere['id_matiere']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($matiere['nom']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="difficulte">Difficulté</label>
                <select id="difficulte" name="difficulte" class="form-control">
                    <option value="debutant" <?= (isset($_POST['difficulte']) && $_POST['difficulte'] === 'debutant') ? 'selected' : '' ?>>Débutant</option>
                    <option value="intermediaire" <?= (isset($_POST['difficulte']) && $_POST['difficulte'] === 'intermediaire') ? 'selected' : '' ?>>Intermédiaire</option>
                    <option value="avance" <?= (isset($_POST['difficulte']) && $_POST['difficulte'] === 'avance') ? 'selected' : '' ?>>Avancé</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="tags">Tags</label>
                <div class="tags-input" id="tagsContainer">
                    <input type="text" class="tag-input" placeholder="Ajouter un tag et appuyer sur Entrée...">
                </div>
                <small>Ajoutez des tags pour organiser votre contenu (ex: javascript, html, css)</small>
            </div>
            
            <div class="form-group">
                <div class="form-check">
                    <input type="checkbox" id="est_public" name="est_public" 
                           <?= (isset($_POST['est_public']) && $_POST['est_public']) ? 'checked' : '' ?>>
                    <label for="est_public">Rendre ce deck public pour tous les professeurs</label>
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Créer le deck
                </button>
                <a href="professor_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Annuler
                </a>
            </div>
        </form>
    </div>

    <script>
        // Gestion des tags
        const tagsContainer = document.getElementById('tagsContainer');
        const tagInput = tagsContainer.querySelector('.tag-input');
        const tags = [];
        
        tagInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const tag = this.value.trim().toLowerCase();
                if (tag && !tags.includes(tag)) {
                    tags.push(tag);
                    addTag(tag);
                    this.value = '';
                }
            }
        });
        
        function addTag(tag) {
            const tagElement = document.createElement('span');
            tagElement.className = 'tag';
            tagElement.innerHTML = `
                ${tag}
                <span class="remove" onclick="removeTag('${tag}')">&times;</span>
            `;
            tagsContainer.insertBefore(tagElement, tagInput);
        }
        
        function removeTag(tag) {
            const index = tags.indexOf(tag);
            if (index > -1) {
                tags.splice(index, 1);
                updateTagsDisplay();
            }
        }
        
        function updateTagsDisplay() {
            const existingTags = tagsContainer.querySelectorAll('.tag');
            existingTags.forEach(tag => tag.remove());
            tags.forEach(tag => addTag(tag));
        }
        
        // Ajouter les tags au formulaire avant soumission
        document.querySelector('form').addEventListener('submit', function(e) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'tags';
            hiddenInput.value = JSON.stringify(tags);
            this.appendChild(hiddenInput);
        });
    </script>
</body>
</html>
