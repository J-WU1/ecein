<?php
//  includes/db.php — Connexion à la base de données MySQL

define('DB_HOST', 'localhost');
define('DB_PORT', 3306);        // port MySQL sur MAMP
define('DB_USER', 'root');      // utilisateur MySQL par défaut sur MAMP
define('DB_PASS', 'root');      // mot de passe MySQL par défaut sur MAMP
define('DB_NAME', 'ecein');

// Connexion via mysqli (compatible MAMP)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Vérification de la connexion
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// Encodage UTF-8 pour les caractères spéciaux (accents, émojis)
$conn->set_charset("utf8mb4");
