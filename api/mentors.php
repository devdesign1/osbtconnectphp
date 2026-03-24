<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once '../config/database.php';

try {
    global $pdo;
    $query = "
        SELECT 
            u.id_utilisateur as id,
            u.nom,
            u.prenom,
            u.bio,
            u.filiere,
            m.nom as matiere,
            m.code as matiere_code,
            cm.niveau,
            cm.note_moyenne,
            cm.nombre_seances,
            COALESCE(
                (SELECT GROUP_CONCAT(DISTINCT CONCAT(dm.jour_semaine, ' ', dm.heure_debut, '-', dm.heure_fin) 
                 ORDER BY FIELD(dm.jour_semaine, 'lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche') SEPARATOR '; ')
                 FROM disponibilites_mentors dm
                 WHERE dm.mentor_id = u.id_utilisateur AND dm.est_active = 1),
                ''
            ) as disponibilites
        FROM utilisateurs u
        JOIN competences_mentors cm ON u.id_utilisateur = cm.mentor_id
        JOIN matieres m ON cm.matiere_id = m.id_matiere
        WHERE u.role = 'mentor' AND cm.statut = 'disponible'
        ORDER BY u.nom, u.prenom, m.nom
    ";
    $stmt = $pdo->query($query);
    $mentors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'mentors' => array_map(function($mentor) {
            return [
                'id' => (string)$mentor['id'],
                'prenom' => (string)$mentor['prenom'],
                'nom' => (string)$mentor['nom'],
                'matiere' => (string)$mentor['matiere'],
                'promotion' => '',
                'bio' => (string)($mentor['bio'] ?? ''),
                'note_moyenne' => (float)($mentor['note_moyenne'] ?? 0),
                'nombre_seances' => (int)($mentor['nombre_seances'] ?? 0),
                'competences' => [$mentor['matiere']],
                'disponibilites' => (string)($mentor['disponibilites'] ?? ''),
                'couleur' => '#2E5077',
                'niveau' => (string)($mentor['niveau'] ?? ''),
                'filiere' => (string)($mentor['filiere'] ?? '')
            ];
        }, $mentors)
    ]);
} catch (Exception $e) {
    error_log("Erreur mentors API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'error' => 'Impossible de récupérer les mentors'
    ]);
}
?>
