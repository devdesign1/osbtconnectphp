<?php
require_once 'config/database.php';

echo "<h2>Vérification de la structure de la table flashcards</h2>";

try {
    $stmt = $pdo->query("DESCRIBE flashcards");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Colonnes trouvées dans la table flashcards :</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
    echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    
    $date_columns = [];
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "</tr>";
        
        if (strpos($column['Field'], 'date') !== false) {
            $date_columns[] = $column['Field'];
        }
    }
    echo "</table>";
    
    // Vérifier si la colonne date_derniere_revision existe
    $has_date_derniere_revision = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'date_derniere_revision') {
            $has_date_derniere_revision = true;
            break;
        }
    }
    
    if (!$has_date_derniere_revision) {
        echo "<h3 style='color: red;'>⚠️ La colonne 'date_derniere_revision' n'existe pas dans la table flashcards.</h3>";
        echo "<h4>Colonnes avec 'date' trouvées :</h4>";
        echo "<ul>";
        foreach ($date_columns as $col) {
            echo "<li><strong>" . htmlspecialchars($col) . "</strong></li>";
        }
        echo "</ul>";
        
        echo "<h4>Suggestion de correction pour learning-center.php :</h4>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
        echo "// Remplacer la ligne problématique :\n";
        echo "MAX(CASE WHEN f.date_derniere_revision IS NOT NULL THEN f.date_derniere_revision ELSE NULL END) as last_reviewed\n\n";
        echo "// Par une de ces options (selon les colonnes disponibles) :\n";
        foreach ($date_columns as $col) {
            echo "MAX(f." . $col . ") as last_reviewed  // si " . $col . " est la bonne colonne\n";
        }
        echo "// Ou si aucune colonne de date n'est appropriée :\n";
        echo "MAX(f.updated_at) as last_reviewed  // utiliser updated_at si elle existe\n";
        echo "NULL as last_reviewed  // ou simplement NULL pour désactiver le tri";
        echo "</pre>";
    } else {
        echo "<h3 style='color: green;'>✅ La colonne 'date_derniere_revision' existe bien.</h3>";
    }
    
    // Afficher quelques données pour test
    echo "<h3>Exemple de données dans flashcards (5 premières lignes) :</h3>";
    $stmt = $pdo->query("SELECT * FROM flashcards LIMIT 5");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($data)) {
        echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
        echo "<tr>";
        foreach (array_keys($data[0]) as $key) {
            echo "<th>" . htmlspecialchars($key) . "</th>";
        }
        echo "</tr>";
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Aucune donnée dans la table flashcards.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
