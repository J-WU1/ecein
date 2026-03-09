<?php
/*  logout.php — Déconnexion */
require_once 'includes/auth.php';

/* Détruit toutes les variables de session */
$_SESSION = [];
session_destroy();

header("Location: login.php");
exit();
