<?php
// Ce fichier gère la connexion à la base de données MySQL

// On définit le nom du serveur de base de données (ici en local avec MAMP)
define('DB_HOST', 'localhost');

// On définit le port MySQL, 3306 est le port par défaut sur MAMP
define('DB_PORT', 3306);

// On définit le nom d'utilisateur MySQL, "root" est l'utilisateur par défaut sur MAMP
define('DB_USER', 'root');

// On définit le mot de passe MySQL, "root" est le mot de passe par défaut sur MAMP
define('DB_PASS', 'root');

// On définit le nom de la base de données que l'on va utiliser
define('DB_NAME', 'ecein');

// On crée la connexion à la base de données avec MySQLi en utilisant les constantes définies au-dessus
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// On vérifie si la connexion a échoué
if ($conn->connect_error) {
    // Si erreur, on arrête tout le script et on affiche le message d'erreur
    die("Erreur de connexion : " . $conn->connect_error);
}

// On force l'encodage UTF-8 pour pouvoir gérer les accents et les émojis correctement
$conn->set_charset("utf8mb4");
