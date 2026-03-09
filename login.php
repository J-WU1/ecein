<?php
/*  login.php — Page de connexion */

/* Charge la connexion à la base de données (db.php) */
require_once 'includes/db.php';
/* Charge les fonctions d'authentification et de session (auth.php) */
require_once 'includes/auth.php';

/* Si l'utilisateur est déjà connecté (session active), on le redirige directement vers l'accueil */
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit(); /* Arrête l'exécution du script après la redirection */
}

/* Variable pour stocker le message d'erreur à afficher dans le formulaire */
$error = '';

/* On traite le formulaire uniquement si la page est appelée en POST (soumission du formulaire) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Récupère et nettoie les champs soumis (trim supprime les espaces inutiles) */
    $email    = trim($_POST['email']    ?? ''); /* Email saisi par l'utilisateur */
    $password = trim($_POST['password'] ?? ''); /* Mot de passe saisi */

    /* Vérifie que les deux champs sont remplis */
    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        /* Requête préparée pour chercher l'utilisateur par email */
        /* Les requêtes préparées protègent contre les injections SQL */
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email); // "s" = type string
        $stmt->execute();
        /* Récupère le résultat sous forme de tableau associatif */
        $user = $stmt->get_result()->fetch_assoc();

        /* Vérifie que l'utilisateur existe ET que le mot de passe correspond au hash stocké */
        /* password_verify compare le mot de passe saisi avec le hash en BDD */
        if ($user && password_verify($password, $user['password'])) {

            /* Connexion réussie : on stocke les infos dans la session PHP */
            $_SESSION['user_id']   = $user['id'];   /* ID de l'utilisateur connecté */
            $_SESSION['user_role'] = $user['role'];  /* Rôle : "auteur" ou "admin" */

            /* Met à jour la date et heure de dernière connexion dans la BDD */
            $stmt2 = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt2->bind_param("i", $user['id']); // "i" = type integer
            $stmt2->execute();

            /* Redirige vers la page d'accueil après connexion réussie */
            header("Location: index.php");
            exit();
        } else {
            /* Email ou mot de passe incorrect : message d'erreur générique (sécurité) */
            $error = "Email ou mot de passe incorrect.";
        }
    }
}
?>