<?php
                                    // logout.php — Déconnexion de l'utilisateur
require_once 'includes/auth.php';   // charge session_start() et les fonctions d'authentification

$_SESSION = [];                     // vide toutes les variables de session (user_id, user_role, etc.)
session_destroy();                  // détruit le fichier de session côté serveur

header("Location: login.php");      // redirige vers la page de connexion
exit();                             // stoppe l'exécution du script après la redirection
