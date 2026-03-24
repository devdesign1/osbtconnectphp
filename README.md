# 🎓 OSBT Connect - Plateforme Éducative "All-in-One"

## 📋 Description

**OSBT Connect** est une plateforme éducative complète conçue pour les étudiants et enseignants de l'OSBT. Elle offre une solution intégrée pour la gestion académique, la communication et le suivi des performances.

### 🎯 MVP Actuel (Version 2.0)

**Fonctionnalités principales :**
- 🏠 **Dashboards** spécialisés par rôle (Étudiant, Professeur, Admin)
- 📚 **Learning Center** avec flashcards et decks
- 🧠 **Système de flashcards** avec algorithme de répétition espacée (SM-2)
- 👥 **Mentorat** personnalisé entre étudiants et professeurs
- 📊 **Analytics** avancés avec graphiques interactifs
- 💬 **Messagerie interne** avec notifications
- 🔐 **Authentification sécurisée** avec rôles
- 📱 **Design responsive** avec thème Notion-inspired

---

## 🏗️ Architecture Technique

### **Technologies**
- **Backend :** PHP 8.3+ avec PDO
- **Base de données :** MySQL 8.0+
- **Frontend :** HTML5/CSS3/JavaScript moderne
- **Animations :** CSS3 animations et transitions
- **Graphiques :** Chart.js
- **Design :** Notion/Vygo inspired avec glass morphism

### **Structure du projet**
```
osbtconnect/
├── 📁 api/                    # API endpoints
│   ├── auth.php              # API authentification
│   ├── dashboard_api.php      # API dashboard
│   └── [autres endpoints]    # API diverses
├── 📁 config/                # Configuration
│   └── database.php          # Connexion BDD
├── 📁 sql/                   # Scripts SQL
│   ├── create_tables_dashboard.sql
│   ├── create_tables_learning_center.sql
│   ├── create_tables_mentorat.sql
│   └── utilisateurs_test.sql
├── 📁 assets/                # Resources statiques
│   ├── css/                 # Feuilles de style
│   ├── js/                  # Scripts JavaScript
│   └── images/              # Images et icônes
├── 📄 Pages principales
│   ├── index.php             # Landing page
│   ├── login.php             # Login principal
│   ├── student_dashboard.php   # Dashboard étudiant
│   ├── professor_dashboard.php # Dashboard professeur
│   ├── admin_dashboard.php    # Dashboard admin
│   ├── learning-center.php    # Centre d'apprentissage
│   ├── flashcards.php        # Système de flashcards
│   ├── mentorat.php         # Mentorat
│   └── logout.php            # Déconnexion
└── 📄 Utilitaires
    ├── activation.php        # Activation compte
    └── [autres pages]       # Pages utilitaires
```

---

## 🎨 Design & Branding

### **Palette de couleurs Notion/Vygo**
```css
/* Couleurs primaires */
--notion-blue: #4285f4;
--notion-green: #00c853;
--notion-purple: #8b5cf6;
--notion-orange: #f97316;
--notion-pink: #ec4899;

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
```

### **Design system**
- **Typography :** Inter (Google Fonts)
- **Style :** Glass morphism moderne avec inspiration Notion/Vygo
- **Responsive :** Mobile-first approach
- **Animations :** CSS3 smooth transitions
- **Components :** Cartes, badges, boutons cohérents

---

## 🚀 Installation & Configuration

### **Prérequis**
- PHP 8.3+
- MySQL 8.0+
- Apache/Nginx
- Composer (optionnel)

### **Installation rapide**
```bash
# 1. Cloner le projet
git clone [repository-url]
cd osbtconnect

# 2. Configurer la base de données
# Importer les fichiers SQL dans l'ordre :
# - sql/create_tables_dashboard.sql
# - sql/create_tables_learning_center.sql
# - sql/create_tables_mentorat.sql
# - sql/utilisateurs_test.sql

# 3. Configurer la connexion BDD
# Éditer config/database.php avec vos credentials

# 4. Lancer le serveur
php -S localhost:8000
# Ou configurer votre serveur web
```

### **Configuration BDD**
```php
// config/database.php
$host = 'localhost';
$dbname = 'osbtconnect2';
$username = 'root';
$password = '';
```

---

## 👥 Utilisateurs & Rôles

### **Comptes de test**
| Rôle | Identifiant | Mot de passe |
|------|-------------|--------------|
| Étudiant L1 | `john_l` | `123` |
| Étudiant L2 | `marie_l` | `123` |
| Admin | `admin` | `123` |
| Enseignant | `prof_maths` | `123` |

### **Rôles disponibles**
- **Étudiant** : Accès learning center, flashcards, mentorat, dashboard
- **Enseignant** : Gestion classes, création decks, mentorat, analytics
- **Admin** : Accès complet administratif et configuration
- **Staff** : Support et modération

---

## 📱 Fonctionnalités Détaillées

### **🏠 Dashboards spécialisés**
- **Dashboard Étudiant** : Vue d'ensemble de l'apprentissage, prochaines révisions, mentorat
- **Dashboard Professeur** : Analytics classes, gestion decks, sessions mentorat
- **Dashboard Admin** : Administration plateforme, statistiques globales, gestion utilisateurs

### **📚 Learning Center**
- Catalogue de decks par matière
- Statistiques d'apprentissage personnelles
- Progression et achievements
- Interface moderne et intuitive

### **🧠 Système de Flashcards**
- **Algorithme SM-2** : Répétition espacée optimisée
- **Évaluation adaptive** : 4 niveaux de difficulté (Again, Hard, Good, Easy)
- **Support multimédia** : Texte, images, explications
- **Tags et catégories** : Organisation avancée
- **Statistiques détaillées** : Taux de réussite, courbe d'oubli

### **👥 Mentorat**
- **Sessions personnalisées** : Un professeur pour plusieurs étudiants
- **Planning intelligent** : Gestion des créneaux
- **Suivi individualisé** : Progression par étudiant
- **Communication intégrée** : Messages et ressources

### **📊 Analytics & Reporting**
- **Tableaux de bord** : KPIs en temps réel
- **Graphiques interactifs** : Chart.js
- **Heatmaps** : Visualisation des performances
- **Export de données** : CSV, PDF (prévu)

---

## 🗄️ Base de Données

### **Tables principales**
- `utilisateurs` - Informations utilisateurs et rôles
- `decks` - Collections de flashcards
- `flashcards` - Cartes individuelles avec algorithme SM-2
- `revisions_srs` - Historique des révisions
- `sessions_mentorat` - Sessions de mentorat
- `classes` - Classes et matières
- `messages` - Messagerie interne
- `annonces` - Communications officielles

### **Relations clés**
- `utilisateurs` ←→ `decks` (1:N) - Création de contenu
- `utilisateurs` ←→ `flashcards` (N:M) - Révisions
- `utilisateurs` ←→ `sessions_mentorat` (N:M) - Mentorat
- `decks` ←→ `flashcards` (1:N) - Contenu

---

## 🔄 Algorithme de Répétition Espacée (SM-2)

### **Principe**
L'algorithme SM-2 (SuperMemo 2) optimise l'apprentissage en espaçant les révisions selon la performance de l'utilisateur.

### **Calculs**
```php
// Cas : Again (oublié)
$interval = 1;
$repetitions = 0;
$ease_factor = max(1.3, $ease_factor - 0.2);

// Cas : Hard (difficile)
$interval = max(1, round($interval * 1.2));
$ease_factor = max(1.3, $ease_factor - 0.15);

// Cas : Good (moyen)
$interval = round($interval * $ease_factor);
// Pas de changement à l'ease factor

// Cas : Easy (facile)
$interval = round($interval * $ease_factor * 1.5);
$ease_factor = min(2.5, $ease_factor + 0.1);
```

### **Avantages**
- **Optimisation temps** : Révision seulement quand nécessaire
- **Rétention maximale** : Espacement intelligent
- **Adaptation personnelle** : Difficulté ajustée automatiquement

---

## 🐛 Développement & Debug

### **Mode développement**
```php
// Activer dans chaque page
$dev_mode = true;
$debug_mode = true;

if ($dev_mode && !isset($_SESSION['user_id'])) {
    // Simulation utilisateur automatique
    $_SESSION['user_id'] = 1;
    $_SESSION['user_role'] = 'student';
}
```

### **Logs et erreurs**
- Error reporting activé en dev
- Logs dans error_log PHP
- Messages d'erreur utilisateur-friendly
- Validation des entrées côté serveur

---

## 🔄 Roadmap (Prochaines versions)

### **Version 2.1 (Court terme)**
- 📅 **Planning avancé** avec FullCalendar
- 🔔 **Notifications temps réel** (WebSocket)
- 📱 **PWA** pour mobile
- 🎯 **Gamification** avec badges et points

### **Version 2.2 (Moyen terme)**
- ☁️ **Cloud storage** pour fichiers
- 🔐 **OAuth** (Google/Microsoft)
- 🤖 **AI features** (recommendations personnalisées)
- 📹 **Visioconférence** intégrée

### **Version 3.0 (Long terme)**
- 📚 **Marketplace** de cours et decks
- 🌍 **Multi-langues** (i18n)
- 📊 **Business Intelligence** avancée
- 🔗 **Intégrations** tierces (LMS, SIS)

---

## 🛠️ Maintenance & Support

### **Tâches régulières**
- 🔄 **Backup** quotidien de la BDD
- 📊 **Monitoring** des performances
- 🔍 **Audit** de sécurité
- 📝 **Mise à jour** des dépendances

### **Sécurité**
- 🔐 **Prepared statements** PDO
- 🛡️ **Session sécurisée** avec timeout
- 🔒 **Password hashing** Bcrypt
- 🚫 **XSS et CSRF protection**
- 📝 **Input validation** stricte

---

## 📞 Support & Contact

### **Documentation**
- 📖 **Guide utilisateur** : `/docs/user-guide.md`
- 🔧 **Guide développeur** : `/docs/dev-guide.md`
- 🗄️ **API Reference** : `/docs/api.md`

### **Signaler un bug**
1. **Description** détaillée du problème
2. **Screenshots** si applicable
3. **Logs** d'erreur
4. **Étapes de reproduction**
5. **Environnement** (navigateur, PHP version)

### **Contributions**
- 🍴 **Fork** le projet
- 🔀 **Créer une branche** feature
- 📝 **Commits** descriptifs
- 🔄 **Pull request** documentée

---

## 📈 Performance & Optimisation

### **Optimisations actuelles**
- ⚡ **Lazy loading** des images
- 🗜️ **Compression** CSS/JS
- 💾 **Cache** navigateur
- 📱 **Responsive images**
- 🚀 **Async loading** des scripts

### **Métriques cibles**
- 🚀 **Load time** < 2s
- 📊 **PageSpeed** > 90
- 📱 **Mobile friendly** 100%
- ♿ **Accessibility** A+

---

## 🎯 Cas d'Usage

### **Pour l'étudiant**
1. **Connexion** au dashboard personnel
2. **Consultation** des decks disponibles
3. **Révision** des flashcards avec algorithme SM-2
4. **Suivi** de sa progression
5. **Demande** de sessions de mentorat
6. **Communication** avec ses professeurs

### **Pour le professeur**
1. **Création** de decks pédagogiques
2. **Suivi** de la progression des classes
3. **Planification** des sessions de mentorat
4. **Analyse** des performances étudiants
5. **Communication** avec les étudiants
6. **Gestion** des classes et matières

### **Pour l'administrateur**
1. **Supervision** de l'ensemble de la plateforme
2. **Gestion** des utilisateurs et rôles
3. **Configuration** des paramètres système
4. **Analyse** des statistiques globales
5. **Maintenance** et support technique

---

## 📜 Licence

**OSBT Connect** - Propriété de l'OSBT  
© 2024 Tous droits réservés

---

## 🎉 Conclusion

**OSBT Connect** représente une solution moderne et complète pour la gestion académique. Avec son architecture robuste, son design inspiré des meilleures plateformes (Notion, Vygo), et ses fonctionnalités pédagogiques avancées (flashcards avec SM-2, mentorat), il constitue la plateforme idéale pour l'éducation numérique à l'OSBT.

**Points forts :**
- 🎨 **Design moderne** et intuitif
- 🧠 **Pédagogie scientifique** (SM-2)
- 👥 **Accompagnement personnalisé** (mentorat)
- 📊 **Analytics** puissants
- 🔐 **Sécurité** robuste

**Ready to revolutionize education?** 🚀

---

*Made with ❤️ for OSBT students and faculty*
