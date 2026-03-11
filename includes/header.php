<?php
// includes/header.php — En-tête commun inclus en haut de chaque page
// À appeler après require_once db.php et auth.php

$currentUser  = getCurrentUser($conn);                              // récupère le tableau de données de l'utilisateur connecté
$nbNotifs     = $currentUser ? countUnreadNotifications($conn) : 0; // nombre de notifs non lues, 0 si non connecté
$nbMessages   = $currentUser ? countUnreadMessages($conn) : 0;      // nombre de messages non lus, 0 si non connecté
$currentPage  = basename($_SERVER['PHP_SELF']);                     // nom du fichier PHP en cours ex: "index.php"
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"> <!-- encodage UTF-8 pour les accents et caractères spéciaux -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">      <!-- responsive mobile -->
    <title>ECE In — <?= $pageTitle ?? 'Social Media Professionnel' ?></title>   <!-- titre de l'onglet, $pageTitle défini dans chaque page -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">      <!-- Bootstrap 5 : grille, composants, utilitaires -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet"> <!-- Bootstrap Icons : toutes les icônes bi-* -->
    <link href="css/style.css" rel="stylesheet">                                <!-- nos styles personnalisés ECE In -->
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-ece sticky-top shadow-sm">       <!-- navbar-dark : texte blanc | bg-ece : fond bleu ECE | sticky-top : reste collée en haut au scroll -->
    <div class="container-fluid px-4">      <!-- container pleine largeur avec padding horizontal -->

        <!-- Logo + Titre -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">   <!-- logo cliquable vers l'accueil -->
            <div class="ece-logo-badge">    <!-- badge blanc "ECEin" défini dans style.css -->
                <span>ECE</span><span class="text-warning">in</span>                <!-- "ECE" blanc + "in" jaune -->
            </div>
        </a>

        <!-- Bouton mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">    <!-- bouton hamburger visible sous 992px, ouvre/ferme le menu via collapse Bootstrap -->
            <span class="navbar-toggler-icon"></span> <!-- icône trois traits du hamburger -->
        </button>

        <div class="collapse navbar-collapse" id="navbarMain"> <!-- contenu collapsible de la navbar -->
            <?php if ($currentUser): ?> <!-- affiche la navigation uniquement si connecté -->
            
            <!-- Navigation principale -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">      <!-- liste des liens, poussée à gauche (me-auto) -->
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">   <!-- 'active' si on est sur index.php -->
                        <i class="bi bi-house-fill"></i><br>  <!-- icône maison -->
                        <small>Accueil</small>                <!-- texte sous l'icône -->
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'reseau.php' ? 'active' : '' ?>" href="reseau.php"> <!-- 'active' si on est sur reseau.php -->
                        <i class="bi bi-people-fill"></i><br> <!-- icône groupe de personnes -->
                        <small>Mon Réseau</small>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'profil.php' ? 'active' : '' ?>" href="profil.php"> <!-- 'active' si on est sur profil.php -->
                        <i class="bi bi-person-fill"></i><br> <!-- icône personne -->
                        <small>Vous</small>
                    </a>
                </li>
                <li class="nav-item position-relative">       <!-- position-relative pour placer la pastille en absolu -->
                    <a class="nav-link <?= $currentPage === 'notifications.php' ? 'active' : '' ?>" href="notifications.php">
                        <i class="bi bi-bell-fill"></i>       <!-- icône cloche -->
                        <?php if ($nbNotifs > 0): ?>          <!-- affiche la pastille uniquement s'il y a des notifs -->
                            <span class="badge-notif"><?= $nbNotifs ?></span> <!-- pastille rouge avec le nombre -->
                        <?php endif; ?>
                        <br><small>Notifications</small>
                    </a>
                </li>
                <li class="nav-item position-relative"> <!-- position-relative pour la pastille messages -->
                    <a class="nav-link <?= $currentPage === 'messagerie.php' ? 'active' : '' ?>" href="messagerie.php">
                        <i class="bi bi-chat-dots-fill"></i> <!-- icône bulle de chat -->
                        <?php if ($nbMessages > 0): ?> <!-- affiche la pastille uniquement s'il y a des messages non lus -->
                            <span class="badge-notif"><?= $nbMessages ?></span> <!-- pastille rouge avec le nombre -->
                        <?php endif; ?>
                        <br><small>Messagerie</small>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'emplois.php' ? 'active' : '' ?>" href="emplois.php">
                        <i class="bi bi-briefcase-fill"></i><br>    <!-- icône mallette -->
                        <small>Emplois</small>
                    </a>
                </li>
                <?php if ($currentUser['role'] === 'admin'): ?>     <!-- lien admin visible seulement pour les admins -->
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'admin.php' ? 'active' : '' ?>" href="admin.php">
                        <i class="bi bi-shield-lock-fill"></i><br>  <!-- icône bouclier cadenas -->
                        <small>Admin</small>
                    </a>
                </li>
                <?php endif; ?> <!-- fin du bloc conditionnel admin -->
            </ul>

            <!-- Avatar + Déconnexion -->
            <div class="d-flex align-items-center gap-2">                   <!-- conteneur photo + bouton déco, alignés horizontalement -->
                <a href="profil.php" class="d-flex align-items-center text-white text-decoration-none gap-2"> <!-- lien vers son propre profil -->
                    <img src="uploads/<?= e($currentUser['photo']) ?>"      <!-- photo de l utilisateur connecté -->
                         class="rounded-circle border border-warning"       <!-- cercle avec bordure jaune ECE -->
                         width="36" height="36" style="object-fit:cover;"   <!-- taille fixe 36x36 -->
                         alt="Photo de profil">
                    <small class="d-none d-lg-inline"><?= e($currentUser['prenom']) ?></small>  <!-- prénom masqué sur mobile (d-none), visible sur grand écran (d-lg-inline) -->
                </a>
                <a href="logout.php" class="btn btn-sm btn-outline-warning ms-2">               <!-- bouton de déconnexion -->
                    <i class="bi bi-box-arrow-right"></i>                   <!-- icône porte de sortie -->
                </a>
            </div>

            <!-- Non connecté -->
            <?php else: ?>                  <!-- si l'utilisateur n'est pas connecté -->
            <ul class="navbar-nav ms-auto"> <!-- liens poussés à droite (ms-auto) -->
                <li class="nav-item">
                    <a class="nav-link" href="login.php">Connexion</a>                  <!-- lien vers la page de connexion -->
                </li>
                <li class="nav-item">
                    <a class="btn btn-warning ms-2" href="register.php">S'inscrire</a>  <!-- bouton jaune vers l'inscription -->
                </li>
            </ul>
            <?php endif; ?> <!-- fin du bloc connecté/non connecté -->
        </div>
    </div>
</nav>
<!-- Fin NAVBAR -->
