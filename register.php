<?php
// register.php — Page d'inscription
require_once 'includes/db.php';     // connexion à la base de données MySQL
require_once 'includes/auth.php';   // démarre la session et charge les fonctions d'auth

if (isset($_SESSION['user_id'])) {  // si l'utilisateur est déjà connecté, inutile de s'inscrire
    header("Location: index.php");  // redirige vers l'accueil
    exit();                         // stoppe le script
}

$error   = ''; // message d'erreur affiché si une validation échoue
$success = ''; // message de succès affiché après création du compte

if ($_SERVER['REQUEST_METHOD'] === 'POST') {    // traitement uniquement si le formulaire a été soumis
    $prenom   = trim($_POST['prenom']   ?? ''); // récupère le prénom sans espaces inutiles
    $nom      = trim($_POST['nom']      ?? ''); // récupère le nom sans espaces inutiles
    $pseudo   = trim($_POST['pseudo']   ?? ''); // récupère le pseudo sans espaces inutiles
    $email    = trim($_POST['email']    ?? ''); // récupère l'email sans espaces inutiles
    $password = trim($_POST['password'] ?? ''); // récupère le mot de passe sans espaces inutiles
    $confirm  = trim($_POST['confirm']  ?? ''); // récupère la confirmation du mot de passe

    //  Validations 
    if (empty($prenom) || empty($nom) || empty($pseudo) || empty($email) || empty($password)) { // vérifie que tous les champs sont remplis
        $error = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {                                     // vérifie que l'email a le format xxx@yyy.zzz
        $error = "Adresse email invalide.";
    } elseif (!str_ends_with($email, '@ece.fr')) {                                              // vérifie que l'email se termine bien par @ece.fr
        $error = "Vous devez utiliser votre email ECE Paris (@ece.fr).";
    } elseif (strlen($password) < 6) {                                                          // vérifie que le mot de passe fait au moins 6 caractères
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $confirm) {                                                         // vérifie que les deux saisies du mot de passe sont identiques
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR pseudo = ?");           // vérifie l'unicité de l'email et du pseudo
        $stmt->bind_param("ss", $email, $pseudo);                                               // lie les deux paramètres string
        $stmt->execute();                                                                       // exécute la requête
        if ($stmt->get_result()->num_rows > 0) {                                                // si au moins une ligne trouvée, email ou pseudo déjà utilisé
            $error = "Cet email ou ce pseudo est déjà utilisé.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);                                 // génère un hash bcrypt, on ne stocke jamais le mot de passe en clair

            $stmt2 = $conn->prepare(                                                            // prépare l'insertion du nouvel utilisateur
                "INSERT INTO users (pseudo, email, password, nom, prenom) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt2->bind_param("sssss", $pseudo, $email, $hash, $nom, $prenom);                 // lie les 5 paramètres string

            if ($stmt2->execute()) {                                                            // si l'insertion réussit
                $success = "Compte créé avec succès ! Vous pouvez maintenant vous connecter.";
            } else {
                $error = "Erreur lors de la création du compte. Réessayez.";                    // erreur SQL inattendue
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">                                                                                      <!-- encodage pour les accents -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">                                      <!-- responsive mobile -->
    <title>ECE In — Inscription</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">      <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet"> <!-- icônes Bootstrap -->
    <link href="css/style.css" rel="stylesheet">                                                                <!-- nos styles personnalisés -->
</head>
<body>

<div class="auth-wrapper py-4">                         <!-- conteneur plein écran fond bleu ECE, py-4 pour espace haut/bas sur mobile -->
    <div class="auth-card" style="max-width:480px;">    <!-- card blanche, un peu plus large que login pour le formulaire -->

        <!-- Logo -->
        <div class="text-center mb-4">                  <!-- en-tête avec logo et titre -->
            <div class="d-inline-block px-3 py-1 rounded" style="background:#003a7d;">  <!-- fond bleu autour du logo -->
                <span class="text-white fw-bold fs-4">ECE</span>                        <!-- "ECE" en blanc -->
                <span class="text-warning fw-bold fs-4">in</span>                       <!-- "in" en jaune -->
            </div>
            <h5 class="mt-2">Créer votre compte</h5>                                    <!-- titre de la page -->
            <p class="text-muted small mb-0">Rejoignez la communauté professionnelle ECE Paris</p> <!-- accroche -->
        </div>
        
        <!-- Alertes -->
        <?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= e($error)   ?></div><?php endif; ?> <!-- alerte rouge si erreur de validation -->
        <?php if ($success): ?><div class="alert alert-success py-2 small"><?= e($success) ?><br>
            <a href="login.php" class="fw-semibold">→ Se connecter</a></div><?php endif; ?> <!-- alerte verte + lien connexion si succès -->

        <?php if (!$success): ?>                    <!-- masque le formulaire après un succès pour éviter de re-soumettre -->
        <form method="POST" action="register.php">  <!-- formulaire POST vers cette même page -->
            
            <!-- Prénom + Nom -->
            <div class="row g-2 mb-3">  <!-- prénom et nom côte à côte sur la même ligne grâce à la grille Bootstrap -->
                <div class="col">       <!-- colonne prénom -->
                    <label class="form-label fw-semibold">Prénom</label>
                    <input type="text" name="prenom" class="form-control"
                           placeholder="Alice" value="<?= e($_POST['prenom'] ?? '') ?>" required> <!-- value repopulé pour garder la saisie après une erreur -->
                </div>
                <div class="col">       <!-- colonne nom -->
                    <label class="form-label fw-semibold">Nom</label>
                    <input type="text" name="nom" class="form-control"
                           placeholder="Dupont" value="<?= e($_POST['nom'] ?? '') ?>" required>
                </div>
            </div>

            <!-- Pseudo -->
            <div class="mb-3">              
                <label class="form-label fw-semibold">Pseudo</label>
                <div class="input-group">   <!-- groupe Bootstrap pour coller l'icône @ au champ -->
                    <span class="input-group-text"><i class="bi bi-at"></i></span> <!-- icône @ à gauche -->
                    <input type="text" name="pseudo" class="form-control"
                           placeholder="alice_dupont" value="<?= e($_POST['pseudo'] ?? '') ?>" required>
                </div>
            </div>

            <!-- Email ECE -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Email ECE <span class="text-muted small">(obligatoire)</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>   <!-- icône enveloppe -->
                    <input type="email" name="email" class="form-control"
                           placeholder="prenom.nom@ece.fr" value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="form-text">Seules les adresses @ece.fr sont acceptées.</div>        <!-- aide sous le champ -->
            </div>

            <!-- Mot de passe -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Mot de passe</label>
                <input type="password" name="password" class="form-control"
                       placeholder="6 caractères minimum" required minlength="6"> <!-- minlength HTML5 = validation côté client en plus du PHP -->
            </div>

            <!-- Confirmation -->
            <div class="mb-4"> <!-- mb-4 pour espace avant le bouton -->
                <label class="form-label fw-semibold">Confirmer le mot de passe</label>
                <input type="password" name="confirm" class="form-control"
                       placeholder="••••••••" required>                         <!-- doit être identique au champ précédent, vérifié côté PHP -->
            </div>

            <button type="submit" class="btn btn-ece w-100 fw-semibold py-2">   <!-- bouton de soumission pleine largeur -->
                Créer mon compte
            </button>
        </form>
        <?php endif; ?> <!-- fin du bloc conditionnel formulaire -->

        <hr>
        <p class="text-center text-muted small mb-0">
            Déjà un compte ? <a href="login.php" class="fw-semibold">Se connecter</a>                   <!-- lien vers la page de connexion -->
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>    <!-- Bootstrap JS -->
</body>
</html>
