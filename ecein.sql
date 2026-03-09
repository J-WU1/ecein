--  ECE In -- Base de données
--  À importer dans phpMyAdmin

CREATE DATABASE IF NOT EXISTS ecein CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ecein;

-- USERS
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    pseudo      VARCHAR(50)  NOT NULL UNIQUE,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    nom         VARCHAR(50)  NOT NULL,
    prenom      VARCHAR(50)  NOT NULL,
    role        ENUM('auteur','admin') NOT NULL DEFAULT 'auteur',
    photo       VARCHAR(255) DEFAULT 'default.png',
    cover       VARCHAR(255) DEFAULT 'default_cover.jpg',
    bio         TEXT,
    poste       VARCHAR(100),
    entreprise  VARCHAR(100),
    localisation VARCHAR(100),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login  DATETIME
);


-- FORMATIONS (page "Vous")
CREATE TABLE formations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    titre       VARCHAR(150) NOT NULL,
    etablissement VARCHAR(150),
    date_debut  YEAR,
    date_fin    YEAR,
    description TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- PROJETS (page "Vous")

CREATE TABLE projets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    titre       VARCHAR(150) NOT NULL,
    description TEXT,
    date_debut  DATE,
    date_fin    DATE,
    lien        VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


-- POSTS (fil d'actualité)

CREATE TABLE posts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    contenu     TEXT,
    media       VARCHAR(255),          -- chemin fichier image/vidéo
    media_type  ENUM('image','video','pdf','none') DEFAULT 'none',
    lieu        VARCHAR(100),
    visibilite  ENUM('public','amis','prive') DEFAULT 'public',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


-- REACTIONS (like, love, etc.)

CREATE TABLE reactions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    post_id     INT NOT NULL,
    user_id     INT NOT NULL,
    type        ENUM('like','love','bravo') DEFAULT 'like',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


-- COMMENTAIRES

CREATE TABLE comments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    post_id     INT NOT NULL,
    user_id     INT NOT NULL,
    contenu     TEXT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


-- AMIS / RÉSEAU

CREATE TABLE friendships (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,         -- celui qui envoie la demande
    friend_id   INT NOT NULL,         -- celui qui reçoit
    status      ENUM('pending','accepted','rejected') DEFAULT 'pending',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_friendship (user_id, friend_id),
    FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE
);


-- MESSAGES PRIVÉS

CREATE TABLE messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT NOT NULL,
    receiver_id INT NOT NULL,
    contenu     TEXT NOT NULL,
    lu          TINYINT(1) DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- NOTIFICATIONS
CREATE TABLE notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,          -- destinataire
    type        VARCHAR(50) NOT NULL,  -- 'like','comment','friend_request','job','event'
    contenu     TEXT,
    lien        VARCHAR(255),
    lu          TINYINT(1) DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- EMPLOIS
CREATE TABLE jobs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    titre       VARCHAR(150) NOT NULL,
    entreprise  VARCHAR(150) NOT NULL,
    lieu        VARCHAR(100),
    type        ENUM('CDI','CDD','Stage','Alternance','Freelance') NOT NULL,
    description TEXT,
    lien        VARCHAR(255),
    posted_by   INT,                   -- admin qui a posté
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ALBUMS PHOTOS
CREATE TABLE albums (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    titre       VARCHAR(150) NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE album_photos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    album_id    INT NOT NULL,
    post_id     INT NOT NULL,          -- lien vers le post contenant la photo
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id)  REFERENCES posts(id)  ON DELETE CASCADE
);


-- DONNÉES DE TEST

-- Admin
INSERT INTO users (pseudo, email, password, nom, prenom, role, bio, poste)
VALUES (
    'admin',
    'admin@ece.fr',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'Admin',
    'ECE',
    'admin',
    'Administrateur de la plateforme ECE In',
    'Administrateur'
);

-- Utilisateurs de test
INSERT INTO users (pseudo, email, password, nom, prenom, role, bio, poste, entreprise)
VALUES
('alice_dupont', 'alice@ece.fr',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Dupont', 'Alice', 'auteur',
 'Étudiante ING2 passionnée par le développement web et l\'IA.',
 'Étudiante Ingénieure', 'ECE Paris'),

('bob_martin', 'bob@ece.fr',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Martin', 'Bob', 'auteur',
 'Alternant en cybersécurité, fan de CTF.',
 'Alternant Cybersécurité', 'Thales'),

('clara_petit', 'clara@ece.fr',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Petit', 'Clara', 'auteur',
 'Chercheuse en traitement du signal à ECE.',
 'Enseignante-Chercheuse', 'ECE Paris');

-- Formations de test
INSERT INTO formations (user_id, titre, etablissement, date_debut, date_fin, description)
VALUES
(2, 'Cycle Ingénieur', 'ECE Paris', 2022, 2027, 'Spécialisation Numérique & Intelligence Artificielle'),
(2, 'Baccalauréat Scientifique', 'Lycée Henri IV', 2019, 2022, 'Mention Très Bien'),
(3, 'Cycle Ingénieur', 'ECE Paris', 2021, 2026, 'Spécialisation Cybersécurité'),
(4, 'Doctorat Traitement du Signal', 'Sorbonne Université', 2015, 2018, '');

-- Amitiés de test
INSERT INTO friendships (user_id, friend_id, status) VALUES
(2, 3, 'accepted'),
(2, 4, 'accepted'),
(3, 4, 'pending');

-- Posts de test
INSERT INTO posts (user_id, contenu, visibilite, lieu) VALUES
(2, 'Super journée à l\'ECE, notre projet web avance bien ! 🚀', 'public', 'ECE Paris'),
(3, 'Nouvel article sur la cybersécurité des objets connectés publié sur mon profil.', 'public', 'Paris'),
(4, 'Conférence sur le traitement du signal demain à 14h amphi A. Tout le monde est bienvenu !', 'public', 'ECE Paris');

-- Offres d'emploi de test
INSERT INTO jobs (titre, entreprise, lieu, type, description, posted_by) VALUES
('Stage Développeur Web', 'ECE Paris', 'Paris 15e', 'Stage',
 'Stage de fin d\'études de 6 mois. Stack : PHP, MySQL, JS. Rejoignez l\'équipe DSI de l\'ECE.', 1),
('Alternance Cybersécurité', 'Thales', 'Vélizy-Villacoublay', 'Alternance',
 'Rejoignez notre équipe CERT. Profil : réseaux, sécurité, pentest.', 1),
('CDI Enseignant-Chercheur', 'ECE Paris', 'Paris', 'CDI',
 'Poste ouvert en département Électronique et Traitement du Signal.', 1);

-- Notifications de test
INSERT INTO notifications (user_id, type, contenu, lien) VALUES
(2, 'event', 'Conférence sur le traitement du signal demain à 14h', 'index.php'),
(2, 'friend_request', 'Clara Petit vous a envoyé une demande de connexion', 'reseau.php'),
(3, 'job', 'Nouvelle offre : Stage Développeur Web chez ECE Paris', 'emplois.php');
