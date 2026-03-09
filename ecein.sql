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

-- Fichier en cours de création
