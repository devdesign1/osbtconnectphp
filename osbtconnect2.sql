-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : mar. 24 mars 2026 à 13:41
-- Version du serveur : 8.4.3
-- Version de PHP : 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `osbtconnect2`
--

-- --------------------------------------------------------

--
-- Structure de la table `annonces`
--

CREATE TABLE `annonces` (
  `id_annonce` int NOT NULL,
  `titre` varchar(200) NOT NULL,
  `contenu` text NOT NULL,
  `auteur_id` int NOT NULL,
  `cible` enum('tous','promo1','promo2','business','technology') DEFAULT 'tous',
  `importance` enum('info','important','urgent') DEFAULT 'info',
  `date_debut` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_fin` datetime DEFAULT NULL,
  `est_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `annonces`
--

INSERT INTO `annonces` (`id_annonce`, `titre`, `contenu`, `auteur_id`, `cible`, `importance`, `date_debut`, `date_fin`, `est_active`, `created_at`) VALUES
(1, 'Bienvenue sur OSBT-Connect!', 'La plateforme officielle d\'entraide entre étudiants est maintenant en ligne. Profitez du système de mentorat et des flashcards collaboratives!', 1, 'tous', 'important', '2026-01-31 20:54:21', NULL, 1, '2026-01-31 19:54:21'),
(2, 'Inscriptions ouvertes pour les mentors', 'Les étudiants de 2ème année peuvent s\'inscrire comme mentors jusqu\'au 30 novembre. Aidez vos camarades et gagnez en expérience!', 1, 'promo2', 'info', '2026-01-31 20:54:21', NULL, 1, '2026-01-31 19:54:21'),
(3, 'Atelier \"Préparation aux entretiens\"', 'Un atelier gratuit pour préparer vos entretiens d\'embauche aura lieu le 15 décembre. Inscriptions via la plateforme.', 1, 'tous', 'important', '2026-01-31 20:54:21', NULL, 1, '2026-01-31 19:54:21');

-- --------------------------------------------------------

--
-- Structure de la table `annonces_professeur`
--

CREATE TABLE `annonces_professeur` (
  `id_annonce` int NOT NULL,
  `professeur_id` int NOT NULL,
  `titre` varchar(255) NOT NULL,
  `contenu` text NOT NULL,
  `type_annonce` enum('information','devoir','examen','evenement','rappel') DEFAULT 'information',
  `cible_type` enum('tous','classe','matiere','personnalise') DEFAULT 'tous',
  `cible_ids` varchar(500) DEFAULT NULL,
  `date_publication` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_expiration` datetime DEFAULT NULL,
  `priorite` enum('basse','normale','haute','urgente') DEFAULT 'normale',
  `est_publie` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `annonces_professeur`
--

INSERT INTO `annonces_professeur` (`id_annonce`, `professeur_id`, `titre`, `contenu`, `type_annonce`, `cible_type`, `cible_ids`, `date_publication`, `date_expiration`, `priorite`, `est_publie`, `created_at`, `updated_at`) VALUES
(1, 1, 'Devoir JavaScript à rendre', 'Le devoir sur les closures doit être rendu avant le 20 février sur la plateforme.', 'devoir', 'classe', '1', '2026-02-10 11:02:43', '2026-02-20 23:59:59', 'haute', 1, '2026-02-10 10:02:43', '2026-02-10 10:02:43');

-- --------------------------------------------------------

--
-- Structure de la table `checklist_items`
--

CREATE TABLE `checklist_items` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `item_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `completed` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `classes`
--

CREATE TABLE `classes` (
  `id_classe` int NOT NULL,
  `nom` varchar(255) NOT NULL,
  `description` text,
  `professeur_id` int DEFAULT NULL,
  `matiere_id` int DEFAULT NULL,
  `promotion` int DEFAULT NULL,
  `salle` varchar(100) DEFAULT NULL,
  `emploi_du_temps` text,
  `est_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `classes`
--

INSERT INTO `classes` (`id_classe`, `nom`, `description`, `professeur_id`, `matiere_id`, `promotion`, `salle`, `emploi_du_temps`, `est_active`, `created_at`, `updated_at`) VALUES
(1, 'Développement Web Frontend 2023', 'HTML, CSS, JavaScript avancé pour la promotion 2023', 1, 1, 2023, 'Labo A101', NULL, 1, '2026-02-10 10:02:42', '2026-02-10 10:02:42'),
(2, 'Bases de Données 2023', 'MySQL, modélisation et requêtes SQL pour débutants', 1, 2, 2023, 'Labo B205', NULL, 1, '2026-02-10 10:02:42', '2026-02-10 10:02:42'),
(3, 'PHP Avancé 2024', 'POO, MVC, APIs REST pour la promotion 2024', 1, 4, 2024, 'Labo C301', NULL, 1, '2026-02-10 10:02:42', '2026-02-10 10:02:42');

-- --------------------------------------------------------

--
-- Structure de la table `competences_mentors`
--

CREATE TABLE `competences_mentors` (
  `id_competence` int NOT NULL,
  `mentor_id` int NOT NULL,
  `matiere_id` int NOT NULL,
  `niveau` enum('intermediaire','avance','expert') DEFAULT 'intermediaire',
  `description_experience` text,
  `statut` enum('disponible','limite','indisponible') DEFAULT 'disponible',
  `note_moyenne` decimal(3,2) DEFAULT '5.00',
  `nombre_seances` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `competences_mentors`
--

INSERT INTO `competences_mentors` (`id_competence`, `mentor_id`, `matiere_id`, `niveau`, `description_experience`, `statut`, `note_moyenne`, `nombre_seances`, `created_at`, `updated_at`) VALUES
(1, 6, 1, 'expert', '3 ans d\'expérience en développement web, projets freelance', 'disponible', 5.00, 0, '2026-01-31 19:54:21', '2026-01-31 19:54:21'),
(2, 6, 2, 'avance', 'Projet de fin d\'études sur l\'optimisation de bases de données', 'disponible', 5.00, 0, '2026-01-31 19:54:21', '2026-01-31 19:54:21'),
(3, 7, 2, 'expert', 'Administrateur de bases de données pendant 1 an en alternance', 'disponible', 5.00, 0, '2026-01-31 19:54:21', '2026-01-31 19:54:21'),
(4, 7, 4, 'avance', 'Développement d\'APIs REST pour applications mobiles', 'disponible', 5.00, 0, '2026-01-31 19:54:21', '2026-01-31 19:54:21'),
(5, 8, 7, 'expert', 'Stage chez une agence de marketing digital, gestion de campagnes', 'disponible', 5.00, 0, '2026-01-31 19:54:21', '2026-01-31 19:54:21'),
(6, 9, 8, 'expert', 'Alternance en département financier, analyse de données', 'disponible', 5.00, 0, '2026-01-31 19:54:21', '2026-01-31 19:54:21');

-- --------------------------------------------------------

--
-- Structure de la table `contenus_pedagogiques`
--

CREATE TABLE `contenus_pedagogiques` (
  `id_contenu` int NOT NULL,
  `professeur_id` int NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text,
  `type_contenu` enum('deck','document','video','exercice','quiz') DEFAULT 'document',
  `fichier_url` varchar(500) DEFAULT NULL,
  `classe_id` int DEFAULT NULL,
  `matiere_id` int DEFAULT NULL,
  `difficulte` enum('debutant','intermediaire','avance') DEFAULT 'debutant',
  `tags` varchar(500) DEFAULT NULL,
  `est_public` tinyint(1) DEFAULT '0',
  `est_actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `contenus_pedagogiques`
--

INSERT INTO `contenus_pedagogiques` (`id_contenu`, `professeur_id`, `titre`, `description`, `type_contenu`, `fichier_url`, `classe_id`, `matiere_id`, `difficulte`, `tags`, `est_public`, `est_actif`, `created_at`, `updated_at`) VALUES
(1, 1, 'Introduction à JavaScript Moderne', 'Support de cours sur les nouvelles fonctionnalités d\'ES6+', 'document', '/documents/js-es6.pdf', 1, 1, 'debutant', 'javascript,es6,frontend', 1, 1, '2026-02-10 10:02:42', '2026-02-10 10:02:42');

-- --------------------------------------------------------

--
-- Structure de la table `decks`
--

CREATE TABLE `decks` (
  `id_deck` int NOT NULL,
  `createur_id` int NOT NULL,
  `matiere_id` int DEFAULT NULL,
  `titre` varchar(200) NOT NULL,
  `description` text,
  `niveau` enum('debutant','intermediaire','avance') DEFAULT 'intermediaire',
  `est_public` tinyint(1) DEFAULT '0',
  `est_certifie` tinyint(1) DEFAULT '0',
  `nombre_cartes` int DEFAULT '0',
  `nombre_revisions` int DEFAULT '0',
  `popularite` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `decks`
--

INSERT INTO `decks` (`id_deck`, `createur_id`, `matiere_id`, `titre`, `description`, `niveau`, `est_public`, `est_certifie`, `nombre_cartes`, `nombre_revisions`, `popularite`, `created_at`, `updated_at`) VALUES
(1, 6, 1, 'JavaScript - Les concepts avancés', 'Tout ce qu\'il faut savoir sur JavaScript ES6+', 'avance', 1, 0, 15, 0, 0, '2026-01-31 19:54:21', '2026-01-31 19:54:21'),
(2, 7, 2, 'SQL - Requêtes essentielles', 'Les requêtes SQL les plus utilisées en entreprise', 'intermediaire', 1, 0, 12, 0, 0, '2026-01-31 19:54:21', '2026-01-31 19:54:21'),
(3, 8, 7, 'Marketing Digital - Vocabulaire', 'Les termes essentiels du marketing digital', 'debutant', 1, 0, 20, 0, 0, '2026-01-31 19:54:21', '2026-01-31 19:54:21'),
(4, 2, 1, 'Mes révisions perso JavaScript', 'Mes notes personnelles pour l\'examen', 'intermediaire', 0, 0, 8, 0, 0, '2026-01-31 19:54:21', '2026-01-31 19:54:21');

-- --------------------------------------------------------

--
-- Structure de la table `disponibilites_mentors`
--

CREATE TABLE `disponibilites_mentors` (
  `id_disponibilite` int NOT NULL,
  `mentor_id` int NOT NULL,
  `jour_semaine` enum('lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche') DEFAULT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `mode` enum('presentiel','visio','les_deux') DEFAULT 'visio',
  `lieu_presentiel` varchar(100) DEFAULT NULL,
  `lien_visio` varchar(255) DEFAULT NULL,
  `est_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `disponibilites_mentors`
--

INSERT INTO `disponibilites_mentors` (`id_disponibilite`, `mentor_id`, `jour_semaine`, `heure_debut`, `heure_fin`, `mode`, `lieu_presentiel`, `lien_visio`, `est_active`, `created_at`) VALUES
(1, 6, 'lundi', '18:00:00', '20:00:00', 'visio', NULL, NULL, 1, '2026-01-31 19:54:21'),
(2, 6, 'mercredi', '19:00:00', '21:00:00', 'visio', NULL, NULL, 1, '2026-01-31 19:54:21'),
(3, 6, 'samedi', '10:00:00', '12:00:00', 'presentiel', NULL, NULL, 1, '2026-01-31 19:54:21'),
(4, 7, 'mardi', '16:00:00', '18:00:00', 'visio', NULL, NULL, 1, '2026-01-31 19:54:21'),
(5, 7, 'jeudi', '17:00:00', '19:00:00', 'les_deux', NULL, NULL, 1, '2026-01-31 19:54:21'),
(6, 8, 'lundi', '14:00:00', '16:00:00', 'visio', NULL, NULL, 1, '2026-01-31 19:54:21'),
(7, 8, 'vendredi', '15:00:00', '17:00:00', 'presentiel', NULL, NULL, 1, '2026-01-31 19:54:21'),
(8, 9, 'mardi', '18:00:00', '20:00:00', 'visio', NULL, NULL, 1, '2026-01-31 19:54:21'),
(9, 9, 'jeudi', '18:00:00', '20:00:00', 'visio', NULL, NULL, 1, '2026-01-31 19:54:21');

-- --------------------------------------------------------

--
-- Structure de la table `evaluations`
--

CREATE TABLE `evaluations` (
  `id_evaluation` int NOT NULL,
  `professeur_id` int NOT NULL,
  `etudiant_id` int NOT NULL,
  `classe_id` int DEFAULT NULL,
  `matiere_id` int DEFAULT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text,
  `type_evaluation` enum('quiz','devoir','projet','examen','participation') DEFAULT 'devoir',
  `note_sur` decimal(5,2) DEFAULT '20.00',
  `note_obtenue` decimal(5,2) DEFAULT NULL,
  `appreciation` text,
  `date_evaluation` date DEFAULT NULL,
  `date_limite` date DEFAULT NULL,
  `est_publie` tinyint(1) DEFAULT '0',
  `est_soumis` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `evaluations`
--

INSERT INTO `evaluations` (`id_evaluation`, `professeur_id`, `etudiant_id`, `classe_id`, `matiere_id`, `titre`, `description`, `type_evaluation`, `note_sur`, `note_obtenue`, `appreciation`, `date_evaluation`, `date_limite`, `est_publie`, `est_soumis`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 1, 1, 'Devoir JavaScript #1', 'Exercices sur les closures et les promesses', 'devoir', 20.00, 16.50, NULL, '2026-02-15', '2026-02-20', 1, 0, '2026-02-10 10:02:43', '2026-02-10 10:02:43');

-- --------------------------------------------------------

--
-- Structure de la table `flashcards`
--

CREATE TABLE `flashcards` (
  `id_flashcard` int NOT NULL,
  `deck_id` int NOT NULL,
  `recto` text NOT NULL,
  `verso` text NOT NULL,
  `explication` text,
  `difficulte` enum('facile','moyen','difficile') DEFAULT 'moyen',
  `tags` varchar(255) DEFAULT NULL,
  `nombre_revisions` int DEFAULT '0',
  `taux_reussite` decimal(5,2) DEFAULT '0.00',
  `prochaine_revision` date DEFAULT NULL,
  `intervalle_revision` int DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `date_derniere_revision` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `flashcards`
--

INSERT INTO `flashcards` (`id_flashcard`, `deck_id`, `recto`, `verso`, `explication`, `difficulte`, `tags`, `nombre_revisions`, `taux_reussite`, `prochaine_revision`, `intervalle_revision`, `created_at`, `updated_at`, `date_derniere_revision`) VALUES
(1, 1, 'Qu\'est-ce qu\'une closure en JavaScript?', 'Une fonction qui a accès à son scope parent même après l\'exécution de la fonction parente.', 'Les closures permettent de créer des variables privées et de l\'encapsulation.', 'moyen', 'javascript, closure, scope', 0, 0.00, NULL, 1, '2026-01-31 19:54:21', '2026-01-31 19:54:21', NULL),
(2, 1, 'Différence entre let, const et var', 'let: scope bloc, réassignable. const: scope bloc, non réassignable. var: scope fonction, hoisted.', 'Utilisez const par défaut, let quand besoin de réassigner, évitez var.', 'facile', 'javascript, variables, hoisting', 0, 0.00, NULL, 1, '2026-01-31 19:54:21', '2026-01-31 19:54:21', NULL),
(3, 2, 'Comment sélectionner toutes les colonnes d\'une table?', 'SELECT * FROM nom_table;', 'L\'astérisque (*) sélectionne toutes les colonnes.', 'facile', 'sql, select, requetes', 0, 0.00, NULL, 1, '2026-01-31 19:54:21', '2026-01-31 19:54:21', NULL),
(4, 2, 'Quelle est la différence entre INNER JOIN et LEFT JOIN?', 'INNER JOIN: seulement les correspondances. LEFT JOIN: toutes les lignes de gauche, même sans correspondance.', 'Utilisez LEFT JOIN quand vous voulez garder toutes les lignes de la table de gauche.', 'difficile', 'sql, join, relations', 0, 0.00, NULL, 1, '2026-01-31 19:54:21', '2026-01-31 19:54:21', NULL),
(5, 3, 'Qu\'est-ce que le SEO?', 'Search Engine Optimization - Techniques pour améliorer le classement dans les moteurs de recherche.', 'Le SEO est crucial pour la visibilité organique d\'un site web.', 'facile', 'marketing, seo, referencement', 0, 0.00, NULL, 1, '2026-01-31 19:54:21', '2026-01-31 19:54:21', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `matieres`
--

CREATE TABLE `matieres` (
  `id_matiere` int NOT NULL,
  `code` varchar(20) NOT NULL,
  `nom` varchar(150) NOT NULL,
  `description` text,
  `filiere` enum('business','technology','commun') DEFAULT 'commun',
  `couleur_hex` varchar(7) DEFAULT '#3498db',
  `est_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `matieres`
--

INSERT INTO `matieres` (`id_matiere`, `code`, `nom`, `description`, `filiere`, `couleur_hex`, `est_active`) VALUES
(1, 'DEV1', 'Développement Web', 'HTML, CSS, JavaScript, Responsive Design', 'technology', '#3498db', 1),
(2, 'DEV2', 'Base de Données', 'MySQL, Modélisation, Requêtes SQL, PDO', 'technology', '#3498db', 1),
(3, 'DEV3', 'Algorithmique', 'Structures de données, Complexité, Algorithmes de tri', 'technology', '#3498db', 1),
(4, 'DEV4', 'PHP Avancé', 'POO, MVC, APIs REST, Sécurité', 'technology', '#3498db', 1),
(5, 'DEV5', 'Flutter Mobile', 'Dart, Widgets, State Management, APIs', 'technology', '#3498db', 1),
(6, 'MGT1', 'Management', 'Gestion d\'équipe, Leadership, Stratégie', 'business', '#3498db', 1),
(7, 'MKT1', 'Marketing Digital', 'SEO, Réseaux sociaux, Content Marketing', 'business', '#3498db', 1),
(8, 'FIN1', 'Finance', 'Comptabilité, Analyse financière, Budget', 'business', '#3498db', 1),
(9, 'COM1', 'Communication', 'Prise de parole, Négociation, Présentation', 'business', '#3498db', 1),
(10, 'ANG1', 'Anglais Business', 'Vocabulaire professionnel, Rédaction d\'emails', 'commun', '#3498db', 1),
(11, 'PRO1', 'Projet Professionnel', 'CV, Lettre de motivation, Entretien', 'commun', '#3498db', 1);

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id_notification` int NOT NULL,
  `utilisateur_id` int NOT NULL,
  `titre` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type_notification` enum('annonce','mentorat_demande','mentorat_accepte','mentorat_refuse','session_rappel','flashcard_rappel','systeme') DEFAULT 'systeme',
  `lien_action` varchar(255) DEFAULT NULL,
  `est_lue` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id_notification`, `utilisateur_id`, `titre`, `message`, `type_notification`, `lien_action`, `est_lue`, `created_at`) VALUES
(1, 2, 'Nouveau devoir disponible', 'Un devoir en Développement Web a été ajouté.', 'annonce', '/devoirs/1', 0, '2026-01-31 19:54:21'),
(2, 2, 'Demande de mentorat acceptée', 'Emma a accepté votre demande de session sur JavaScript.', 'mentorat_accepte', '/mentorat/sessions/1', 0, '2026-01-31 19:54:21'),
(3, 6, 'Nouvelle demande de mentorat', 'Bruno a demandé de l\'aide en Base de Données.', 'mentorat_demande', '/mentorat/demandes/3', 0, '2026-01-31 19:54:21');

-- --------------------------------------------------------

--
-- Structure de la table `planning_cours`
--

CREATE TABLE `planning_cours` (
  `id_seance` int NOT NULL,
  `matiere_id` int NOT NULL,
  `titre_seance` varchar(200) NOT NULL,
  `description` text,
  `promotion` int NOT NULL,
  `date_seance` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `salle` varchar(50) DEFAULT NULL,
  `type_seance` enum('cours','td','tp','examen') DEFAULT 'cours',
  `professeur_nom` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `planning_cours`
--

INSERT INTO `planning_cours` (`id_seance`, `matiere_id`, `titre_seance`, `description`, `promotion`, `date_seance`, `heure_debut`, `heure_fin`, `salle`, `type_seance`, `professeur_nom`, `created_at`) VALUES
(1, 1, 'Introduction à JavaScript', NULL, 1, '2026-02-01', '09:00:00', '11:00:00', 'A101', 'cours', 'Prof. Martin', '2026-01-31 19:54:21'),
(2, 2, 'Requêtes SQL avancées', NULL, 1, '2026-02-02', '14:00:00', '16:00:00', 'B205', 'td', 'Prof. Dubois', '2026-01-31 19:54:21'),
(3, 4, 'Architecture MVC en PHP', NULL, 2, '2026-02-01', '13:00:00', '15:00:00', 'C301', 'cours', 'Prof. Laurent', '2026-01-31 19:54:21'),
(4, 7, 'Stratégies de marketing digital', NULL, 1, '2026-02-03', '10:00:00', '12:00:00', 'D402', 'cours', 'Prof. Simon', '2026-01-31 19:54:21');

-- --------------------------------------------------------

--
-- Structure de la table `professeurs`
--

CREATE TABLE `professeurs` (
  `id_professeur` int NOT NULL,
  `id_utilisateur` int DEFAULT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `filiere` enum('technology','business','commun') DEFAULT 'commun',
  `specialite` varchar(255) DEFAULT NULL,
  `biographie` text,
  `date_embauche` date DEFAULT NULL,
  `est_actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `professeurs`
--

INSERT INTO `professeurs` (`id_professeur`, `id_utilisateur`, `nom`, `prenom`, `email`, `filiere`, `specialite`, `biographie`, `date_embauche`, `est_actif`, `created_at`, `updated_at`) VALUES
(1, 10, 'Martin', 'Sophie', 'sophie.martin@osbt.com', 'technology', 'Développement Web et Mobile', 'Professeur expérimenté avec 10 ans dans l\'enseignement et 5 ans en entreprise', '2019-09-01', 1, '2026-02-10 10:02:42', '2026-02-10 10:02:42');

-- --------------------------------------------------------

--
-- Structure de la table `sessions_mentorat`
--

CREATE TABLE `sessions_mentorat` (
  `id_session` int NOT NULL,
  `etudiant_id` int NOT NULL,
  `mentor_id` int NOT NULL,
  `matiere_id` int NOT NULL,
  `titre_session` varchar(200) NOT NULL,
  `description_besoin` text NOT NULL,
  `date_session` datetime NOT NULL,
  `duree_minutes` int DEFAULT '60',
  `mode` enum('presentiel','visio') DEFAULT 'visio',
  `lieu` varchar(255) DEFAULT NULL,
  `statut` enum('demande_envoyee','demande_acceptee','demande_refusee','session_confirmee','session_terminee','session_annulee') DEFAULT 'demande_envoyee',
  `note_etudiant` int DEFAULT NULL,
  `feedback_etudiant` text,
  `feedback_mentor` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Déchargement des données de la table `sessions_mentorat`
--

INSERT INTO `sessions_mentorat` (`id_session`, `etudiant_id`, `mentor_id`, `matiere_id`, `titre_session`, `description_besoin`, `date_session`, `duree_minutes`, `mode`, `lieu`, `statut`, `note_etudiant`, `feedback_etudiant`, `feedback_mentor`, `created_at`, `updated_at`) VALUES
(1, 2, 6, 1, 'Aide sur les closures JavaScript', 'Je ne comprends pas le concept de closure et son utilisation pratique dans les projets.', '2026-02-02 20:54:21', 60, 'visio', NULL, 'session_confirmee', NULL, NULL, NULL, '2026-01-31 19:54:21', '2026-01-31 19:54:21'),
(2, 3, 8, 7, 'Préparation présentation marketing', 'J\'ai une présentation à faire sur les tendances du marketing digital et je cherche des conseils.', '2026-02-03 20:54:21', 60, 'visio', NULL, 'session_confirmee', NULL, NULL, NULL, '2026-01-31 19:54:21', '2026-01-31 19:54:21'),
(3, 4, 9, 8, 'Compréhension des ratios financiers', 'Besoin d\'aide pour comprendre les différents ratios financiers et leur interprétation.', '2026-02-04 20:54:21', 60, 'visio', NULL, 'demande_envoyee', NULL, NULL, NULL, '2026-01-31 19:54:21', '2026-01-31 19:54:21');

-- --------------------------------------------------------

--
-- Structure de la table `suivis_etudiants`
--

CREATE TABLE `suivis_etudiants` (
  `id_suivi` int NOT NULL,
  `professeur_id` int NOT NULL,
  `etudiant_id` int NOT NULL,
  `date_suivi` date DEFAULT NULL,
  `type_suivi` enum('progression','difficulte','objectif','comportement') DEFAULT 'progression',
  `observations` text,
  `actions_recommandees` text,
  `objectif_suivant` text,
  `statut` enum('en_cours','atteint','en_retard','abandonne') DEFAULT 'en_cours',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `suivis_etudiants`
--

INSERT INTO `suivis_etudiants` (`id_suivi`, `professeur_id`, `etudiant_id`, `date_suivi`, `type_suivi`, `observations`, `actions_recommandees`, `objectif_suivant`, `statut`, `created_at`, `updated_at`) VALUES
(1, 1, 2, '2026-02-01', 'progression', 'Bon progrès en JavaScript, besoin de travailler sur les concepts asynchrones', 'Réviser les promesses et async/await', 'Maîtriser les requêtes AJAX avec fetch API', 'en_cours', '2026-02-10 10:02:43', '2026-02-10 10:02:43');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('student','professor','admin') COLLATE utf8mb4_unicode_ci DEFAULT 'student',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id_utilisateur` int NOT NULL,
  `noma` varchar(20) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('etudiant','mentor','admin','professeur') DEFAULT 'etudiant',
  `promotion` int NOT NULL,
  `filiere` enum('business','technology') NOT NULL,
  `bio` text,
  `photo_profil` varchar(255) DEFAULT NULL,
  `date_inscription` datetime DEFAULT CURRENT_TIMESTAMP,
  `est_actif` tinyint(1) DEFAULT '1',
  `classe_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id_utilisateur`, `noma`, `nom`, `prenom`, `email`, `mot_de_passe`, `role`, `promotion`, `filiere`, `bio`, `photo_profil`, `date_inscription`, `est_actif`, `classe_id`) VALUES
(1, 'ADMIN001', 'Admin', 'OSBT', 'admin@osbt.be', '$2y$10$HoM2XrolBRHv6DhEjF4.mu11NAIR6LoU7B.zOfmoORCZaT7TV6OSi', 'admin', 2, 'technology', 'Administrateur de la plateforme OSBT-Connect', NULL, '2026-01-31 20:54:21', 1, NULL),
(2, 'TECH2023-001', 'Dupont', 'Alice', 'alice.dupont@student.osbt.be', '$2y$10$...', 'etudiant', 1, 'technology', 'Passionnée par le développement web et les nouvelles technologies.', NULL, '2026-01-31 20:54:21', 1, NULL),
(3, 'TECH2023-002', 'Martin', 'Bruno', 'bruno.martin@student.osbt.be', '$2y$10$...', 'etudiant', 1, 'technology', 'Intéressé par l\'IA et la data science.', NULL, '2026-01-31 20:54:21', 1, NULL),
(4, 'BUS2023-001', 'Dubois', 'Clara', 'clara.dubois@student.osbt.be', '$2y$10$...', 'etudiant', 1, 'business', 'Future entrepreneure dans le e-commerce.', NULL, '2026-01-31 20:54:21', 1, NULL),
(5, 'BUS2023-002', 'Laurent', 'David', 'david.laurent@student.osbt.be', '$2y$10$...', 'etudiant', 1, 'business', 'Spécialisé en marketing digital et réseaux sociaux.', NULL, '2026-01-31 20:54:21', 1, NULL),
(6, 'TECH2022-001', 'Leclerc', 'Emma', 'emma.leclerc@student.osbt.be', '$2y$10$...', 'mentor', 2, 'technology', 'Expert en JavaScript et React. Disponible le soir et le weekend.', NULL, '2026-01-31 20:54:21', 1, NULL),
(7, 'TECH2022-002', 'Moreau', 'Fabrice', 'fabrice.moreau@student.osbt.be', '$2y$10$...', 'mentor', 2, 'technology', 'Spécialiste des bases de données et PHP. Passionné par la pédagogie.', NULL, '2026-01-31 20:54:21', 1, NULL),
(8, 'BUS2022-001', 'Simon', 'Gabrielle', 'gabrielle.simon@student.osbt.be', '$2y$10$...', 'mentor', 2, 'business', 'Experte en marketing digital et stratégie de marque.', NULL, '2026-01-31 20:54:21', 1, NULL),
(9, 'BUS2022-002', 'Lefevre', 'Hugo', 'hugo.lefevre@student.osbt.be', '$2y$10$...', 'mentor', 2, 'business', 'Spécialiste en finance et analyse de marché.', NULL, '2026-01-31 20:54:21', 1, NULL),
(10, 'PROF2024-001', 'Martin', 'Sophie', 'sophie.martin@osbt.com', '$2y$10$YourHashHere', 'professeur', 2024, 'technology', 'Professeur de développement web et mobile, 10 ans d\'expérience', NULL, '2026-02-10 11:02:42', 1, NULL);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_dashboard_etudiant`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_dashboard_etudiant` (
`decks_personnels` bigint
,`filiere` enum('business','technology')
,`flashcards_personnelles` bigint
,`id_utilisateur` int
,`nom_complet` varchar(201)
,`noma` varchar(20)
,`notifications_non_lues` bigint
,`promotion` int
,`sessions_en_cours` bigint
,`sessions_terminees` bigint
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_mentors_disponibles`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_mentors_disponibles` (
`bio` text
,`disponibilites` text
,`filiere` enum('business','technology')
,`id_utilisateur` int
,`matiere_code` varchar(20)
,`matiere_nom` varchar(150)
,`niveau` enum('intermediaire','avance','expert')
,`nom_complet` varchar(201)
,`nombre_seances` int
,`note_moyenne` decimal(3,2)
,`promotion` int
);

-- --------------------------------------------------------

--
-- Structure de la vue `vue_dashboard_etudiant`
--
DROP TABLE IF EXISTS `vue_dashboard_etudiant`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_dashboard_etudiant`  AS SELECT `u`.`id_utilisateur` AS `id_utilisateur`, `u`.`noma` AS `noma`, concat(`u`.`prenom`,' ',`u`.`nom`) AS `nom_complet`, `u`.`promotion` AS `promotion`, `u`.`filiere` AS `filiere`, (select count(0) from `sessions_mentorat` where ((`sessions_mentorat`.`etudiant_id` = `u`.`id_utilisateur`) and (`sessions_mentorat`.`statut` = 'session_terminee'))) AS `sessions_terminees`, (select count(0) from `sessions_mentorat` where ((`sessions_mentorat`.`etudiant_id` = `u`.`id_utilisateur`) and (`sessions_mentorat`.`statut` in ('demande_envoyee','session_confirmee')))) AS `sessions_en_cours`, (select count(0) from `decks` where (`decks`.`createur_id` = `u`.`id_utilisateur`)) AS `decks_personnels`, (select count(0) from (`flashcards` `f` join `decks` `d` on((`f`.`deck_id` = `d`.`id_deck`))) where (`d`.`createur_id` = `u`.`id_utilisateur`)) AS `flashcards_personnelles`, (select count(0) from `notifications` where ((`notifications`.`utilisateur_id` = `u`.`id_utilisateur`) and (`notifications`.`est_lue` = false))) AS `notifications_non_lues` FROM `utilisateurs` AS `u` WHERE (`u`.`role` = 'etudiant') ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_mentors_disponibles`
--
DROP TABLE IF EXISTS `vue_mentors_disponibles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_mentors_disponibles`  AS SELECT `u`.`id_utilisateur` AS `id_utilisateur`, concat(`u`.`prenom`,' ',`u`.`nom`) AS `nom_complet`, `u`.`promotion` AS `promotion`, `u`.`filiere` AS `filiere`, `u`.`bio` AS `bio`, `m`.`nom` AS `matiere_nom`, `m`.`code` AS `matiere_code`, `cm`.`niveau` AS `niveau`, `cm`.`note_moyenne` AS `note_moyenne`, `cm`.`nombre_seances` AS `nombre_seances`, group_concat(distinct concat(`dm`.`jour_semaine`,' ',`dm`.`heure_debut`,'-',`dm`.`heure_fin`) order by field(`dm`.`jour_semaine`,'lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche') ASC separator '; ') AS `disponibilites` FROM (((`utilisateurs` `u` join `competences_mentors` `cm` on((`u`.`id_utilisateur` = `cm`.`mentor_id`))) join `matieres` `m` on((`cm`.`matiere_id` = `m`.`id_matiere`))) left join `disponibilites_mentors` `dm` on(((`u`.`id_utilisateur` = `dm`.`mentor_id`) and (`dm`.`est_active` = true)))) WHERE ((`u`.`role` = 'mentor') AND (`cm`.`statut` = 'disponible')) GROUP BY `u`.`id_utilisateur`, `m`.`id_matiere` ORDER BY `u`.`nom` ASC, `u`.`prenom` ASC, `m`.`nom` ASC ;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `annonces`
--
ALTER TABLE `annonces`
  ADD PRIMARY KEY (`id_annonce`),
  ADD KEY `auteur_id` (`auteur_id`),
  ADD KEY `idx_date_debut` (`date_debut`),
  ADD KEY `idx_cible` (`cible`);

--
-- Index pour la table `annonces_professeur`
--
ALTER TABLE `annonces_professeur`
  ADD PRIMARY KEY (`id_annonce`),
  ADD KEY `idx_annonce_professeur` (`professeur_id`),
  ADD KEY `idx_annonce_type` (`type_annonce`),
  ADD KEY `idx_annonce_priorite` (`priorite`),
  ADD KEY `idx_annonce_dates` (`date_publication`,`date_expiration`);

--
-- Index pour la table `checklist_items`
--
ALTER TABLE `checklist_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_completed` (`completed`);

--
-- Index pour la table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id_classe`),
  ADD KEY `idx_classe_professeur` (`professeur_id`),
  ADD KEY `idx_classe_matiere` (`matiere_id`),
  ADD KEY `idx_classe_promotion` (`promotion`);

--
-- Index pour la table `competences_mentors`
--
ALTER TABLE `competences_mentors`
  ADD PRIMARY KEY (`id_competence`),
  ADD UNIQUE KEY `unique_mentor_matiere` (`mentor_id`,`matiere_id`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_matiere_niveau` (`matiere_id`,`niveau`);

--
-- Index pour la table `contenus_pedagogiques`
--
ALTER TABLE `contenus_pedagogiques`
  ADD PRIMARY KEY (`id_contenu`),
  ADD KEY `idx_contenu_professeur` (`professeur_id`),
  ADD KEY `idx_contenu_classe` (`classe_id`),
  ADD KEY `idx_contenu_matiere` (`matiere_id`),
  ADD KEY `idx_contenu_type` (`type_contenu`);

--
-- Index pour la table `decks`
--
ALTER TABLE `decks`
  ADD PRIMARY KEY (`id_deck`),
  ADD KEY `idx_matiere_niveau` (`matiere_id`,`niveau`),
  ADD KEY `idx_createur` (`createur_id`),
  ADD KEY `idx_popularite` (`popularite` DESC);

--
-- Index pour la table `disponibilites_mentors`
--
ALTER TABLE `disponibilites_mentors`
  ADD PRIMARY KEY (`id_disponibilite`),
  ADD KEY `idx_mentor_jour` (`mentor_id`,`jour_semaine`),
  ADD KEY `idx_disponibilites_actives` (`est_active`,`jour_semaine`,`heure_debut`);

--
-- Index pour la table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id_evaluation`),
  ADD KEY `idx_eval_professeur` (`professeur_id`),
  ADD KEY `idx_eval_etudiant` (`etudiant_id`),
  ADD KEY `idx_eval_classe` (`classe_id`),
  ADD KEY `idx_eval_matiere` (`matiere_id`),
  ADD KEY `idx_eval_date` (`date_evaluation`);

--
-- Index pour la table `flashcards`
--
ALTER TABLE `flashcards`
  ADD PRIMARY KEY (`id_flashcard`),
  ADD KEY `idx_deck` (`deck_id`),
  ADD KEY `idx_difficulte` (`difficulte`),
  ADD KEY `idx_prochaine_revision` (`prochaine_revision`);
ALTER TABLE `flashcards` ADD FULLTEXT KEY `idx_recherche` (`recto`,`verso`,`tags`);

--
-- Index pour la table `matieres`
--
ALTER TABLE `matieres`
  ADD PRIMARY KEY (`id_matiere`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_filiere` (`filiere`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id_notification`),
  ADD KEY `idx_utilisateur_lue` (`utilisateur_id`,`est_lue`),
  ADD KEY `idx_created_at` (`created_at` DESC);

--
-- Index pour la table `planning_cours`
--
ALTER TABLE `planning_cours`
  ADD PRIMARY KEY (`id_seance`),
  ADD KEY `idx_promotion_date` (`promotion`,`date_seance`),
  ADD KEY `idx_matiere` (`matiere_id`);

--
-- Index pour la table `professeurs`
--
ALTER TABLE `professeurs`
  ADD PRIMARY KEY (`id_professeur`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `id_utilisateur` (`id_utilisateur`);

--
-- Index pour la table `sessions_mentorat`
--
ALTER TABLE `sessions_mentorat`
  ADD PRIMARY KEY (`id_session`),
  ADD KEY `idx_etudiant_statut` (`etudiant_id`,`statut`),
  ADD KEY `idx_mentor_statut` (`mentor_id`,`statut`),
  ADD KEY `idx_date_session` (`date_session`),
  ADD KEY `idx_matiere` (`matiere_id`);

--
-- Index pour la table `suivis_etudiants`
--
ALTER TABLE `suivis_etudiants`
  ADD PRIMARY KEY (`id_suivi`),
  ADD UNIQUE KEY `unique_suivi_date` (`professeur_id`,`etudiant_id`,`date_suivi`),
  ADD KEY `idx_suivi_professeur` (`professeur_id`),
  ADD KEY `idx_suivi_etudiant` (`etudiant_id`),
  ADD KEY `idx_suivi_date` (`date_suivi`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id_utilisateur`),
  ADD UNIQUE KEY `noma` (`noma`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_noma` (`noma`),
  ADD KEY `idx_role_promo` (`role`,`promotion`),
  ADD KEY `idx_filiere` (`filiere`),
  ADD KEY `fk_utilisateurs_classe` (`classe_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `annonces`
--
ALTER TABLE `annonces`
  MODIFY `id_annonce` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `annonces_professeur`
--
ALTER TABLE `annonces_professeur`
  MODIFY `id_annonce` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `checklist_items`
--
ALTER TABLE `checklist_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `classes`
--
ALTER TABLE `classes`
  MODIFY `id_classe` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `competences_mentors`
--
ALTER TABLE `competences_mentors`
  MODIFY `id_competence` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `contenus_pedagogiques`
--
ALTER TABLE `contenus_pedagogiques`
  MODIFY `id_contenu` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `decks`
--
ALTER TABLE `decks`
  MODIFY `id_deck` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `disponibilites_mentors`
--
ALTER TABLE `disponibilites_mentors`
  MODIFY `id_disponibilite` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id_evaluation` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `flashcards`
--
ALTER TABLE `flashcards`
  MODIFY `id_flashcard` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `matieres`
--
ALTER TABLE `matieres`
  MODIFY `id_matiere` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id_notification` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `planning_cours`
--
ALTER TABLE `planning_cours`
  MODIFY `id_seance` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `professeurs`
--
ALTER TABLE `professeurs`
  MODIFY `id_professeur` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `sessions_mentorat`
--
ALTER TABLE `sessions_mentorat`
  MODIFY `id_session` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `suivis_etudiants`
--
ALTER TABLE `suivis_etudiants`
  MODIFY `id_suivi` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id_utilisateur` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `annonces`
--
ALTER TABLE `annonces`
  ADD CONSTRAINT `annonces_ibfk_1` FOREIGN KEY (`auteur_id`) REFERENCES `utilisateurs` (`id_utilisateur`);

--
-- Contraintes pour la table `annonces_professeur`
--
ALTER TABLE `annonces_professeur`
  ADD CONSTRAINT `annonces_professeur_ibfk_1` FOREIGN KEY (`professeur_id`) REFERENCES `professeurs` (`id_professeur`);

--
-- Contraintes pour la table `checklist_items`
--
ALTER TABLE `checklist_items`
  ADD CONSTRAINT `checklist_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`professeur_id`) REFERENCES `professeurs` (`id_professeur`),
  ADD CONSTRAINT `classes_ibfk_2` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id_matiere`);

--
-- Contraintes pour la table `competences_mentors`
--
ALTER TABLE `competences_mentors`
  ADD CONSTRAINT `competences_mentors_ibfk_1` FOREIGN KEY (`mentor_id`) REFERENCES `utilisateurs` (`id_utilisateur`),
  ADD CONSTRAINT `competences_mentors_ibfk_2` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id_matiere`);

--
-- Contraintes pour la table `contenus_pedagogiques`
--
ALTER TABLE `contenus_pedagogiques`
  ADD CONSTRAINT `contenus_pedagogiques_ibfk_1` FOREIGN KEY (`professeur_id`) REFERENCES `professeurs` (`id_professeur`),
  ADD CONSTRAINT `contenus_pedagogiques_ibfk_2` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id_classe`),
  ADD CONSTRAINT `contenus_pedagogiques_ibfk_3` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id_matiere`);

--
-- Contraintes pour la table `decks`
--
ALTER TABLE `decks`
  ADD CONSTRAINT `decks_ibfk_1` FOREIGN KEY (`createur_id`) REFERENCES `utilisateurs` (`id_utilisateur`),
  ADD CONSTRAINT `decks_ibfk_2` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id_matiere`);

--
-- Contraintes pour la table `disponibilites_mentors`
--
ALTER TABLE `disponibilites_mentors`
  ADD CONSTRAINT `disponibilites_mentors_ibfk_1` FOREIGN KEY (`mentor_id`) REFERENCES `utilisateurs` (`id_utilisateur`);

--
-- Contraintes pour la table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`professeur_id`) REFERENCES `professeurs` (`id_professeur`),
  ADD CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`etudiant_id`) REFERENCES `utilisateurs` (`id_utilisateur`),
  ADD CONSTRAINT `evaluations_ibfk_3` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id_classe`),
  ADD CONSTRAINT `evaluations_ibfk_4` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id_matiere`);

--
-- Contraintes pour la table `flashcards`
--
ALTER TABLE `flashcards`
  ADD CONSTRAINT `flashcards_ibfk_1` FOREIGN KEY (`deck_id`) REFERENCES `decks` (`id_deck`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id_utilisateur`);

--
-- Contraintes pour la table `planning_cours`
--
ALTER TABLE `planning_cours`
  ADD CONSTRAINT `planning_cours_ibfk_1` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id_matiere`);

--
-- Contraintes pour la table `professeurs`
--
ALTER TABLE `professeurs`
  ADD CONSTRAINT `professeurs_ibfk_1` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs` (`id_utilisateur`);

--
-- Contraintes pour la table `sessions_mentorat`
--
ALTER TABLE `sessions_mentorat`
  ADD CONSTRAINT `sessions_mentorat_ibfk_1` FOREIGN KEY (`etudiant_id`) REFERENCES `utilisateurs` (`id_utilisateur`),
  ADD CONSTRAINT `sessions_mentorat_ibfk_2` FOREIGN KEY (`mentor_id`) REFERENCES `utilisateurs` (`id_utilisateur`),
  ADD CONSTRAINT `sessions_mentorat_ibfk_3` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id_matiere`);

--
-- Contraintes pour la table `suivis_etudiants`
--
ALTER TABLE `suivis_etudiants`
  ADD CONSTRAINT `suivis_etudiants_ibfk_1` FOREIGN KEY (`professeur_id`) REFERENCES `professeurs` (`id_professeur`),
  ADD CONSTRAINT `suivis_etudiants_ibfk_2` FOREIGN KEY (`etudiant_id`) REFERENCES `utilisateurs` (`id_utilisateur`);

--
-- Contraintes pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD CONSTRAINT `fk_utilisateurs_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id_classe`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
