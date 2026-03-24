<?php
session_start();
require_once '../config/database.php'; // Connexion à la base de données

// Récupérer toutes les matières pour le filtre
$matieres = $pdo->query("SELECT * FROM matieres ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les mentors disponibles avec leurs compétences et disponibilités
$query = "
    SELECT 
        u.id_utilisateur,
        u.nom,
        u.prenom,
        u.bio,
        u.filiere,
        m.nom as matiere_nom,
        m.code as matiere_code,
        cm.niveau,
        cm.note_moyenne,
        cm.nombre_seances,
        GROUP_CONCAT(DISTINCT CONCAT(dm.jour_semaine, ' ', dm.heure_debut, '-', dm.heure_fin) ORDER BY FIELD(dm.jour_semaine, 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche') SEPARATOR '; ') as disponibilites
    FROM utilisateurs u
    JOIN competences_mentors cm ON u.id_utilisateur = cm.mentor_id
    JOIN matieres m ON cm.matiere_id = m.id_matiere
    LEFT JOIN disponibilites_mentors dm ON u.id_utilisateur = dm.mentor_id AND dm.est_active = 1
    WHERE u.role = 'mentor' AND cm.statut = 'disponible'
    GROUP BY u.id_utilisateur, m.id_matiere
    ORDER BY u.nom, u.prenom, m.nom
";

$mentors = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
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
            --primary-green: #00C853;
            --secondary-green: #2E7D32;
            --accent-blue: #2196F3;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            margin: 0;
            padding: 0;
            color: var(--dark-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--secondary-green);
            margin-bottom: 1rem;
            text-align: center;
        }

        .container > p {
            text-align: center;
            font-size: 1.1rem;
            color: var(--gray-color);
            margin-bottom: 2rem;
        }

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .filter-select {
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
            border: 2px solid #e1e5ee;
            background: white;
            min-width: 200px;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(0, 200, 83, 0.1);
        }

        .mentor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .mentor-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--box-shadow);
            border: 1px solid rgba(0, 200, 83, 0.1);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .mentor-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            transform: scaleX(0);
            transition: var(--transition);
        }

        .mentor-card:hover::before {
            transform: scaleX(1);
        }

        .mentor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 200, 83, 0.15);
        }

        .mentor-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .mentor-info {
            flex: 1;
        }

        .mentor-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .mentor-filiere {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filiere-technology {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        .filiere-business {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }

        .mentor-niveau {
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
        }

        .niveau-expert { 
            background: linear-gradient(135deg, #e74c3c, #c0392b); 
        }
        .niveau-avance { 
            background: linear-gradient(135deg, #f39c12, #e67e22); 
        }
        .niveau-intermediaire { 
            background: linear-gradient(135deg, #3498db, #2980b9); 
        }

        .mentor-matiere {
            background: var(--light-color);
            padding: 0.8rem;
            border-radius: 8px;
            margin: 1rem 0;
            border-left: 4px solid var(--primary-green);
        }

        .mentor-matiere strong {
            color: var(--secondary-green);
        }

        .mentor-dispo {
            margin: 1rem 0;
            padding: 0.8rem;
            background: rgba(0, 200, 83, 0.05);
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .mentor-dispo strong {
            color: var(--primary-green);
        }

        .mentor-bio {
            margin: 1rem 0;
            color: var(--gray-color);
            font-style: italic;
            line-height: 1.5;
        }

        .mentor-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1rem 0;
            padding: 0.8rem;
            background: var(--light-color);
            border-radius: 8px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-green);
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--gray-color);
            text-transform: uppercase;
        }

        .btn-reserver {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }

        .btn-reserver:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 200, 83, 0.3);
        }

        .btn-reserver:active {
            transform: translateY(0);
        }

        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            padding: 3rem;
            border-radius: var(--border-radius);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            animation: slideUp 0.4s ease;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray-color);
            cursor: pointer;
            transition: var(--transition);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close:hover {
            color: var(--danger-color);
            background: rgba(244, 67, 54, 0.1);
        }

        .modal h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--secondary-green);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5ee;
            border-radius: var(--border-radius);
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(0, 200, 83, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .mentor-grid {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-select {
                min-width: auto;
            }
            
            .modal-content {
                padding: 2rem;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav style="background: linear-gradient(135deg, #00C853, #2E7D32); padding: 1rem 2rem; color: white;">
        <div style="max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 1.5rem; font-weight: bold;">
                <i class="fas fa-graduation-cap"></i> OSBT Connect
            </div>
            <div>
                <a href="../index.php" style="color: white; text-decoration: none; margin-right: 1rem;">Accueil</a>
                <a href="../dashboard.php" style="color: white; text-decoration: none; margin-right: 1rem;">Dashboard</a>
                <a href="../login.php" style="color: white; text-decoration: none;">Connexion</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1>Trouver un mentor</h1>
        <p>Recherchez un mentor par matière et consultez ses disponibilités.</p>

        <div class="filters">
            <select id="filter-matiere" class="filter-select">
                <option value="">Toutes les matières</option>
                <?php foreach ($matieres as $matiere): ?>
                    <option value="<?php echo $matiere['code']; ?>"><?php echo $matiere['nom']; ?> (<?php echo $matiere['code']; ?>)</option>
                <?php endforeach; ?>
            </select>
            <select id="filter-filiere" class="filter-select">
                <option value="">Toutes les filières</option>
                <option value="technology">Technology</option>
                <option value="business">Business</option>
            </select>
            <select id="filter-niveau" class="filter-select">
                <option value="">Tous les niveaux</option>
                <option value="expert">Expert</option>
                <option value="avance">Avancé</option>
                <option value="intermediaire">Intermédiaire</option>
            </select>
        </div>

        <div class="mentor-grid" id="mentor-list">
            <?php foreach ($mentors as $mentor): ?>
                <div class="mentor-card" data-matiere="<?php echo $mentor['matiere_code']; ?>" data-filiere="<?php echo $mentor['filiere']; ?>" data-niveau="<?php echo $mentor['niveau']; ?>">
                    <div class="mentor-header">
                        <div>
                            <div class="mentor-name"><?php echo $mentor['prenom'] . ' ' . $mentor['nom']; ?></div>
                            <div class="mentor-filiere <?php echo 'filiere-' . $mentor['filiere']; ?>">
                                <?php echo $mentor['filiere']; ?>
                            </div>
                        </div>
                        <div class="mentor-niveau <?php echo 'niveau-' . $mentor['niveau']; ?>">
                            <?php echo $mentor['niveau']; ?>
                        </div>
                    </div>
                    <div class="mentor-matiere">
                        Matière : <?php echo $mentor['matiere_nom']; ?> (<?php echo $mentor['matiere_code']; ?>)
                    </div>
                    <div class="mentor-dispo">
                        <strong>Disponibilités :</strong> <?php echo $mentor['disponibilites']; ?>
                    </div>
                    <div class="mentor-bio">
                        <?php echo $mentor['bio']; ?>
                    </div>
                    <div class="mentor-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($mentor['note_moyenne'], 1); ?></div>
                            <div class="stat-label">Note</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $mentor['nombre_seances']; ?></div>
                            <div class="stat-label">Séances</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo ucfirst($mentor['niveau']); ?></div>
                            <div class="stat-label">Niveau</div>
                        </div>
                    </div>
                    <button class="btn-reserver" onclick="openReservationModal(<?php echo $mentor['id_utilisateur']; ?>, '<?php echo $mentor['matiere_nom']; ?>')">
                        <i class="fas fa-calendar-check"></i> Réserver une séance
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal de réservation -->
    <div id="reservationModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Réserver une séance</h2>
            <form id="reservationForm">
                <input type="hidden" id="mentor_id" name="mentor_id">
                <div class="form-group">
                    <label for="matiere">Matière :</label>
                    <input type="text" id="matiere" name="matiere" readonly>
                </div>
                <div class="form-group">
                    <label for="date_session">Date et heure :</label>
                    <input type="datetime-local" id="date_session" name="date_session" required>
                </div>
                <div class="form-group">
                    <label for="duree">Durée (minutes) :</label>
                    <input type="number" id="duree" name="duree" min="30" max="120" value="60" required>
                </div>
                <div class="form-group">
                    <label for="mode">Mode :</label>
                    <select id="mode" name="mode" required>
                        <option value="visio">Visio</option>
                        <option value="presentiel">Présentiel</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description_besoin">Description de votre besoin :</label>
                    <textarea id="description_besoin" name="description_besoin" rows="4" required></textarea>
                </div>
                <button type="submit" class="btn-reserver">Envoyer la demande</button>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer style="background: #2c3e50; color: white; padding: 2rem; text-align: center; margin-top: 3rem;">
        <p>&copy; 2024 OSBT Connect - OMNIA SCHOOL OF BUSINESS AND TECHNOLOGY</p>
    </footer>

    <script>
        // Filtrage des mentors
        document.getElementById('filter-matiere').addEventListener('change', filterMentors);
        document.getElementById('filter-filiere').addEventListener('change', filterMentors);
        document.getElementById('filter-niveau').addEventListener('change', filterMentors);

        function filterMentors() {
            const matiere = document.getElementById('filter-matiere').value.toLowerCase();
            const filiere = document.getElementById('filter-filiere').value.toLowerCase();
            const niveau = document.getElementById('filter-niveau').value.toLowerCase();

            const mentorCards = document.querySelectorAll('.mentor-card');

            mentorCards.forEach(card => {
                const cardMatiere = card.getAttribute('data-matiere').toLowerCase();
                const cardFiliere = card.getAttribute('data-filiere').toLowerCase();
                const cardNiveau = card.getAttribute('data-niveau').toLowerCase();

                const show = 
                    (matiere === '' || cardMatiere === matiere) &&
                    (filiere === '' || cardFiliere === filiere) &&
                    (niveau === '' || cardNiveau === niveau);

                card.style.display = show ? 'block' : 'none';
            });
        }

        // Modal de réservation
        const modal = document.getElementById('reservationModal');
        const closeModal = document.querySelector('.modal .close');

        function openReservationModal(mentorId, matiere) {
            document.getElementById('mentor_id').value = mentorId;
            document.getElementById('matiere').value = matiere;
            modal.style.display = 'block';
        }

        closeModal.addEventListener('click', () => {
            modal.style.display = 'none';
        });

        window.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Gestion de la soumission du formulaire de réservation
        document.getElementById('reservationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('reserver_mentorat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Demande de réservation envoyée !');
                    modal.style.display = 'none';
                    // Actualiser la page ou mettre à jour l'interface
                } else {
                    alert('Erreur : ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue.');
            });
        });
    </script>
</body>
</html>