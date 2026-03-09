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

// Compte les messages non lus pour l'utilisateur connecté
function countUnreadMessages($conn) {
    if (!isset($_SESSION['user_id'])) return 0;

    $id   = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) AS nb FROM messages WHERE receiver_id = ? AND lu = 0");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['nb'];
}

// Échappe les sorties HTML pour éviter les failles XSS
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Crée une notification pour un utilisateur
function createNotification($conn, $userId, $type, $contenu, $lien = '#') {
    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, type, contenu, lien) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("isss", $userId, $type, $contenu, $lien);
    $stmt->execute();
}

// Vérifie si deux utilisateurs sont amis (statut 'accepted')
function areFriends($conn, $userId, $otherId) {
    $stmt = $conn->prepare(
        "SELECT id FROM friendships
         WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
         AND status = 'accepted'"
    );
    $stmt->bind_param("iiii", $userId, $otherId, $otherId, $userId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}
