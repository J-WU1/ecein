<?php
// ============================================================
//  register.php — Page d'inscription
// ============================================================

// Charge la connexion à la base de données
require_once 'includes/db.php';
// Charge les fonctions d'authentification et de session
require_once 'includes/auth.php';

// Si l'utilisateur est déjà connecté, on le redirige vers l'accueil
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Variables pour stocker les messages d'erreur ou de succès
$error   = '';
$success = '';

// On traite le formulaire uniquement si soumis en POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Récupère et nettoie tous les champs du formulaire
    $prenom   = trim($_POST['prenom']   ?? ''); // Prénom de l'utilisateur
    $nom      = trim($_POST['nom']      ?? ''); // Nom de famille
    $pseudo   = trim($_POST['pseudo']   ?? ''); // Pseudo unique sur le réseau
    $email    = trim($_POST['email']    ?? ''); // Email obligatoirement @ece.fr
    $password = trim($_POST['password'] ?? ''); // Mot de passe choisi
    $confirm  = trim($_POST['confirm']  ?? ''); // Confirmation du mot de passe

    // --- Validations côté serveur ---

    // Vérifie que tous les champs obligatoires sont remplis
    if (empty($prenom) || empty($nom) || empty($pseudo) || empty($email) || empty($password)) {
        $error = "Tous les champs sont obligatoires.";

    // Vérifie que l'email est au format valide (ex: prenom.nom@ece.fr)
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";

    // Vérifie que l'email se termine par @ece.fr (restriction ECE Paris)
    } elseif (!str_ends_with($email, '@ece.fr')) {
        $error = "Vous devez utiliser votre email ECE Paris (@ece.fr).";

    // Vérifie que le mot de passe fait au moins 6 caractères
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";

    // Vérifie que la confirmation correspond au mot de passe
    } elseif ($password !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";

    } else {
        // Vérifie que l'email ET le pseudo ne sont pas déjà utilisés en BDD
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR pseudo = ?");
        $stmt->bind_param("ss", $email, $pseudo); // "ss" = deux strings
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            // Un compte avec cet email ou ce pseudo existe déjà
            $error = "Cet email ou ce pseudo est déjà utilisé.";
        } else {
            // Hachage sécurisé du mot de passe avec PASSWORD_DEFAULT (bcrypt)
            // On ne stocke JAMAIS un mot de passe en clair en BDD
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Insertion du nouvel utilisateur dans la BDD
            $stmt2 = $conn->prepare(
                "INSERT INTO users (pseudo, email, password, nom, prenom) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt2->bind_param("sssss", $pseudo, $email, $hash, $nom, $prenom);

            if ($stmt2->execute()) {
                // Inscription réussie
                $success = "Compte créé avec succès ! Vous pouvez maintenant vous connecter.";
            } else {
                // Erreur lors de l'insertion en BDD
                $error = "Erreur lors de la création du compte. Réessayez.";
            }
        }
    }
}
?>