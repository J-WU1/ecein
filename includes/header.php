<?php
/* includes/header.php — En-tête commun à toutes les pages
   Appelé après avoir chargé db.php et auth.php */

/* Récupère les informations de l'utilisateur connecté depuis la BDD */
$currentUser = getCurrentUser($conn);
/* Compte le nombre de notifications non lues (0 si non connecté) */
$nbNotifs    = $currentUser ? countUnreadNotifications($conn) : 0;
/* Compte le nombre de messages non lus (0 si non connecté) */
$nbMessages  = $currentUser ? countUnreadMessages($conn) : 0;
/* Récupère le nom du fichier PHP actuel pour savoir quelle page est active */
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <!-- Encodage UTF-8 pour supporter les accents et caractères spéciaux -->
    <meta charset="UTF-8">
    <!-- Rend le site responsive sur mobile -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Titre dynamique : change selon la page (ex: "ECE In — Accueil") -->
    <title>ECE In — <?= $pageTitle ?? 'Social Media Professionnel' ?></title>

    <!-- Bootstrap 5 : framework CSS pour la mise en page responsive -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons : bibliothèque d'icônes utilisée dans la navbar -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Notre CSS personnalisé avec les couleurs et styles ECE In -->
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<!-- NAVBAR -->
 
<!-- navbar-dark : texte blanc | bg-ece : fond bleu ECE | sticky-top : reste en haut en scrollant -->
<nav class="navbar navbar-expand-lg navbar-dark bg-ece sticky-top shadow-sm">
    <div class="container-fluid px-4">

        <!-- Logo ECE In cliquable qui ramène à l'accueil -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <div class="ece-logo-badge">
                <!-- "ECE" en bleu et "in" en jaune pour reproduire le style LinkedIn -->
                <span>ECE</span><span class="text-warning">in</span>
            </div>
        </a>

        <!-- Bouton hamburger affiché uniquement sur mobile (Bootstrap collapse) -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>