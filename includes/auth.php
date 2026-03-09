<?php
//  includes/auth.php — Fonctions d'authentification & session

session_start();

// Vérifie si l'utilisateur est connecté, sinon redirige vers login
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

// Vérifie si l'utilisateur est admin, sinon redirige
function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header("Location: index.php");
        exit();
    }
}

// Retourne l'utilisateur connecté (tableau associatif)
function getCurrentUser($conn) {
    if (!isset($_SESSION['user_id'])) return null;

    $id   = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Compte les notifications non lues pour l'utilisateur connecté
function countUnreadNotifications($conn) {
    if (!isset($_SESSION['user_id'])) return 0;

    $id   = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) AS nb FROM notifications WHERE user_id = ? AND lu = 0");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['nb'];
}

// Fichier en cours de création
