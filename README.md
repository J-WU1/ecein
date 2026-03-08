# ECE In — Guide d'installation et de démarrage

Réseau social professionnel pour la communauté ECE Paris.  
Projet Web ING2 2026 — Stack : PHP, MySQL, Bootstrap 5, jQuery/AJAX.

---

## Prérequis

- **MAMP** (Windows)
- PHP ≥ 7.4
- MySQL ≥ 5.7
- Navigateur moderne (Chrome, Firefox, Edge)

---

## Installation en 4 étapes

### 1. Copier les fichiers du projet

Copiez le dossier `ecein/` dans le répertoire web de MAMP :

- **MAMP** → `C:\MAMP\htdocs\ecein\`

### 2. Importer la base de données

1. Démarrez MAMP et ouvrez **phpMyAdmin** : `http://localhost:8888/phpMyAdmin/`
2. Créez une base de données nommée `ecein`
3. Importez le fichier `ecein.sql` : **Importer → Choisir un fichier → ecein.sql → Exécuter**

### 3. Configurer la connexion BDD

Ouvrez le fichier `includes/db.php` et adaptez les paramètres selon votre configuration :

```php
define('DB_HOST', 'localhost');
define('DB_PORT', 8889);
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'ecein');
```

### 4. Générer les images par défaut (optionnel)

Si les photos de profil par défaut ne s'affichent pas, lancez ce script **une seule fois** :

```
http://localhost:8888/ecein/uploads/generate_defaults.php
```

---

## Accès à l'application

URL : `http://localhost:8888/ecein/`

### Comptes de test

| Rôle  | Email              | Mot de passe |
|-------|--------------------|-------------|
| Admin | admin@ece.fr       | password    |
| User  | alice@ece.fr       | password    |
| User  | bob@ece.fr         | password    |
| User  | clara@ece.fr       | password    |

---

## Structure des fichiers

```
ecein/
│
├── index.php           → Accueil / Fil d'actualité
├── login.php           → Connexion
├── register.php        → Inscription
├── logout.php          → Déconnexion
├── reseau.php          → Mon Réseau
├── profil.php          → Profil utilisateur + CV
├── notifications.php   → Notifications
├── messagerie.php      → Messagerie privée (AJAX)
├── emplois.php         → Offres d'emploi
├── admin.php           → Panneau administrateur
│
├── api/
│   ├── posts.php       → AJAX : réactions + commentaires
│   ├── friends.php     → AJAX : demandes d'amis
│   └── messages.php    → AJAX : envoi + polling messages
│
├── includes/
│   ├── db.php          → Connexion MySQL
│   ├── auth.php        → Session + fonctions utilitaires
│   ├── header.php      → Navbar commune
│   └── footer.php      → Footer + scripts JS
│
├── css/
│   └── style.css       → Styles personnalisés ECE In
│
├── js/
│   └── main.js         → jQuery/AJAX (réactions, chat, amis...)
│
├── uploads/            → Photos, vidéos, PDF uploadés
│   ├── default.png         → Avatar par défaut
│   ├── default_cover.jpg   → Couverture par défaut
│   └── generate_defaults.php
│
└── ecein.sql           → Script SQL (tables + données de test)
```

---

## Fonctionnalités implémentées

### 1. Authentification & Utilisateurs
- Inscription avec validation email `@ece.fr` obligatoire
- Connexion avec `password_hash` / `password_verify`
- Sessions PHP + protection des pages avec `requireLogin()`
- Deux rôles : **auteur** et **administrateur**

### 2. Page Accueil (Fil d'actualité)
- Événement de la semaine (dernier post de l'admin mis en avant)
- Publication de posts : texte, photo, vidéo, PDF
- Choix de visibilité : Public / Amis / Privé
- Réactions AJAX (👍 J'aime, ❤️ J'adore, 👏 Bravo) sans rechargement
- Commentaires AJAX sans rechargement
- Suppression de ses propres posts
- Suggestions de connexions (sidebar)
- Bannière jours spéciaux (Noël, Saint-Valentin, etc.) via JavaScript

### 3. Mon Réseau
- Affichage de ses connexions avec photo cliquable → profil
- Demandes reçues avec Accept / Refus (AJAX + fallback)
- Suggestions : amis d'amis puis membres aléatoires
- Bouton "Se connecter" AJAX
- Bouton "Retirer" une connexion

### 4. Profil (Vous)
- Photo de profil + photo de couverture modifiables
- Bio, poste, entreprise, localisation
- Section Formation (chronologique inversée) : ajout/suppression
- Section Projets : ajout/suppression
- **Génération de CV** en HTML → impression / export PDF via le navigateur
- Publications du profil avec gestion de la visibilité
- Boutons contextuels selon la relation (connecter / message / accepter)

### 5. Notifications
- Types : like, commentaire, demande d'ami, message, offre d'emploi, événement
- Icônes et couleurs par type
- Groupées par date, point bleu pour les non lues
- Marquage automatique à l'ouverture

### 6. Messagerie
- Interface type LinkedIn Messenger (liste convs + zone chat)
- **Polling AJAX toutes les 3 secondes** (sans WebSocket)
- Historique complet des échanges
- Badges de messages non lus dans la navbar et la liste
- Envoi par Entrée (Maj+Entrée = saut de ligne)
- Modale "Nouveau message" avec recherche

### 7. Emplois
- Filtres par type de contrat (CDI, CDD, Stage, Alternance, Freelance)
- Recherche texte (titre, entreprise, description)
- Postuler via lien externe ou email
- Admin : publier / supprimer une offre + notification automatique à tous

### 8. Administration
- Tableau de tous les membres avec stats (posts, connexions, dernière connexion)
- Ajouter un membre manuellement
- Supprimer un membre
- Changer le rôle en temps réel (select → submit)
- Recherche dans la liste des membres
- Tableau de bord avec 6 statistiques globales

---

## Technologies utilisées

| Technologie        | Usage                                      |
|--------------------|--------------------------------------------|
| PHP 7.4+           | Back-end, sessions, requêtes BDD           |
| MySQL / MySQLi     | Base de données relationnelle              |
| HTML5 / CSS3       | Structure et mise en forme                 |
| Bootstrap 5.3      | Mise en page responsive, composants UI     |
| Bootstrap Icons    | Icônes                                     |
| JavaScript (ES6)   | Interactions front-end                     |
| jQuery 3.7         | Requêtes AJAX, DOM                         |
| AJAX               | Réactions, commentaires, chat, amis        |

---

## Notes pour la soutenance

- Tout le code est **commenté** (sections claires dans chaque fichier)
- L'architecture suit le modèle **client-serveur** décrit dans l'exemple fourni
- Les endpoints AJAX sont dans le dossier `api/` (séparation claire front/back)
- La fonction `e()` (dans `auth.php`) protège **toutes** les sorties HTML contre les failles XSS
- Les requêtes SQL utilisent **des requêtes préparées** (`prepare` / `bind_param`) pour se protéger des injections SQL
- Le polling toutes les **3 secondes** simule un chat temps réel sans WebSocket (adapté à WAMP/XAMPP)
