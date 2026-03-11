<?php
// login.php — Page de connexion
require_once 'includes/db.php';     // connexion à la base de données MySQL via $conn
require_once 'includes/auth.php';   // démarre la session et charge les fonctions d'authentification

if (isset($_SESSION['user_id'])) {  // si l'utilisateur est déjà connecté on le redirige
    header("Location: index.php");  // redirige vers le fil d'actualité
    exit();                         // stoppe l'exécution du script après la redirection
}

$error = ''; // variable qui contiendra le message d'erreur à afficher dans le formulaire

if ($_SERVER['REQUEST_METHOD'] === 'POST') {            // traitement uniquement si le formulaire a été soumis
    $email    = trim($_POST['email']    ?? '');         // récupère l'email et supprime les espaces inutiles
    $password = trim($_POST['password'] ?? '');         // récupère le mot de passe et supprime les espaces

    if (empty($email) || empty($password)) {            // vérifie que les deux champs sont remplis
        $error = "Veuillez remplir tous les champs.";   // message si un champ est vide
    } else {

        // Recherche l'utilisateur par email
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?"); // requête préparée contre les injections SQL
        $stmt->bind_param("s", $email); // "s" = paramètre de type string
        $stmt->execute();               // exécute la requête avec l'email fourni
        $user = $stmt->get_result()->fetch_assoc();     // récupère la ligne correspondante sous forme de tableau

        if ($user && password_verify($password, $user['password'])) {   // vérifie que l'utilisateur existe ET que le mot de passe correspond au hash bcrypt
            
            // Connexion réussie :
            $_SESSION['user_id']   = $user['id'];                       // stocke l'ID en session pour identifier l'utilisateur sur chaque page
            $_SESSION['user_role'] = $user['role'];                     // stocke le rôle (admin/auteur) pour gérer les droits

            // Met à jour la date de dernière connexion
            $stmt2 = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?"); // prépare la mise à jour de la dernière connexion
            $stmt2->bind_param("i", $user['id']);           // "i" = paramètre de type integer
            $stmt2->execute();                              // enregistre la date et heure actuelles comme dernière connexion

            header("Location: index.php");                  // connexion réussie : redirige vers le fil d'actualité
            exit();                                         // stoppe l'exécution après la redirection
        } else {
            $error = "Email ou mot de passe incorrect.";    // message volontairement vague pour ne pas indiquer lequel est faux
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">                          <!-- encodage pour les accents -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- responsive mobile -->
    <title>ECE In — Connexion</title>               <!-- titre de l'onglet -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> <!-- Bootstrap 5 chargé directement car pas de header.php sur cette page -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet"> <!-- icônes Bootstrap -->
    <link href="css/style.css" rel="stylesheet">    <!-- nos styles personnalisés -->
</head>
<body>

<div class="auth-wrapper">  <!-- conteneur plein écran avec fond dégradé bleu ECE défini dans style.css -->
    <div class="auth-card"> <!-- card blanche centrée dans le fond bleu -->

        <!-- Logo -->
        <div class="text-center mb-4"> <!-- en-tête centré avec logo et accroche -->
            <div class="d-inline-block px-3 py-1 rounded" style="background:#003a7d;">  <!-- fond bleu ECE autour du logo -->
                <span class="text-white fw-bold fs-4">ECE</span>    <!-- "ECE" en blanc gras -->
                <span class="text-warning fw-bold fs-4">in</span>   <!-- "in" en jaune gras -->
            </div>
            <p class="text-muted mt-2 mb-0">Le réseau professionnel de l'ECE Paris</p>  <!-- accroche sous le logo -->
        </div>

        <!-- Message d'erreur -->
        <?php if ($error): ?> <!-- affiche le bloc d'erreur uniquement si $error est non vide -->
            <div class="alert alert-danger py-2 small"><?= e($error) ?></div> <!-- alerte rouge avec le message, e() protège contre le XSS -->
        <?php endif; ?>

        <!-- Formulaire -->
        <form method="POST" action="login.php"> <!-- formulaire POST vers cette même page -->
            <div class="mb-3">                  <!-- groupe champ email -->
                <label class="form-label fw-semibold">Email ECE</label>         <!-- libellé du champ -->
                <div class="input-group">       <!-- groupe Bootstrap pour coller l'icône au champ -->
                    <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span> <!-- icône enveloppe à gauche du champ -->
                    <input type="email" name="email" class="form-control"
                           placeholder="prenom.nom@ece.fr"
                           value="<?= e($_POST['email'] ?? '') ?>" required>    <!-- value repopulé pour ne pas re-saisir après une erreur -->
                </div>
            </div>

            <div class="mb-3">              <!-- groupe champ mot de passe -->
                <label class="form-label fw-semibold">Mot de passe</label>
                <div class="input-group">   <!-- groupe Bootstrap pour coller icône + bouton œil -->
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>   <!-- icône cadenas à gauche -->
                    <input type="password" name="password" id="password-input"
                           class="form-control" placeholder="••••••••" required>            <!-- id="password-input" ciblé par le JS du bouton œil -->
                    <button class="btn btn-outline-secondary" type="button" id="toggle-password"> <!-- bouton qui déclenche le toggle JS -->
                        <i class="bi bi-eye"></i> <!-- icône œil, changée en bi-eye-slash par le JS -->
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-ece w-100 fw-semibold py-2"> <!-- bouton de soumission pleine largeur -->
                Se connecter
            </button>
        </form>

        <hr> <!-- séparateur visuel entre le formulaire et le lien inscription -->

        <p class="text-center text-muted small mb-0">
            Pas encore de compte ?
            <a href="register.php" class="fw-semibold">S'inscrire</a>   <!-- lien vers la page d'inscription -->
        </p>

        <!-- Compte de test affiché en dev -->
        <div class="alert alert-info mt-3 py-2 small">                  <!-- bloc d'aide pour les tests, à supprimer en production -->
            <strong>Comptes de test :</strong><br>
            Admin : admin@ece.fr / password<br>                         <!-- compte admin de test -->
            User  : alice@ece.fr / password                             <!-- compte utilisateur de test -->
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> <!-- Bootstrap JS pour les composants -->
<script>

// Afficher/masquer le mot de passe
document.getElementById('toggle-password').addEventListener('click', function () {  // écoute le clic sur le bouton œil
    const input = document.getElementById('password-input');                        // récupère le champ mot de passe
    const icon  = this.querySelector('i');                                          // récupère l'icône dans le bouton
    if (input.type === 'password') {                                                // si le mot de passe est masqué
        input.type = 'text';                                                        // on rend le mot de passe visible
        icon.classList.replace('bi-eye', 'bi-eye-slash');                           // œil barré = mot de passe affiché
    } else {                                                                        // si le mot de passe est déjà visible
        input.type = 'password';                                                    // on remasque le mot de passe
        icon.classList.replace('bi-eye-slash', 'bi-eye');                           // œil normal = mot de passe masqué
    }
});
</script>
</body>
</html>
