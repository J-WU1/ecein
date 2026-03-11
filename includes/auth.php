<?php
// Ce fichier contient toutes les fonctions liées à la sécurité et à la gestion des sessions

// On démarre la session PHP pour pouvoir lire et écrire dans $_SESSION sur toutes les pages
session_start();

// Cette fonction vérifie que l'utilisateur est bien connecté avant d'accéder à une page
function requireLogin() {
    // Si la variable de session user_id n'existe pas, l'utilisateur n'est pas connecté
    if (!isset($_SESSION['user_id'])) {
        // On redirige vers la page de connexion
        header("Location: login.php");
        // On arrête l'exécution du script pour éviter que la page continue à se charger
        exit();
    }
}

// Cette fonction vérifie que l'utilisateur connecté est bien un administrateur
function requireAdmin() {
    // On vérifie d'abord qu'il est connecté en appelant requireLogin()
    requireLogin();
    // On vérifie ensuite que son rôle en session est bien "admin"
    if ($_SESSION['user_role'] !== 'admin') {
        // Si ce n'est pas un admin, on le renvoie vers la page d'accueil
        header("Location: index.php");
        // On arrête le script
        exit();
    }
}

// Cette fonction retourne toutes les informations de l'utilisateur connecté depuis la base de données
function getCurrentUser($conn) {
    // Si personne n'est connecté, on retourne null immédiatement
    if (!isset($_SESSION['user_id'])) return null;

    // On récupère l'ID en session et on le force en entier pour éviter les injections SQL
    $id   = (int) $_SESSION['user_id'];
    // On prépare la requête SQL pour sécuriser la récupération des données
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    // On lie le paramètre : "i" signifie que c'est un entier
    $stmt->bind_param("i", $id);
    // On exécute la requête
    $stmt->execute();
    // On récupère le résultat
    $result = $stmt->get_result();
    // On retourne les données sous forme de tableau associatif
    return $result->fetch_assoc();
}

// Cette fonction compte le nombre de notifications non lues pour l'utilisateur connecté
function countUnreadNotifications($conn) {
    // Si personne n'est connecté, on retourne 0
    if (!isset($_SESSION['user_id'])) return 0;

    // On récupère l'ID de l'utilisateur connecté
    $id   = (int) $_SESSION['user_id'];
    // On compte les notifications où lu = 0 (non lues) pour cet utilisateur
    $stmt = $conn->prepare("SELECT COUNT(*) AS nb FROM notifications WHERE user_id = ? AND lu = 0");
    // On lie l'ID en entier
    $stmt->bind_param("i", $id);
    $stmt->execute();
    // On récupère la ligne de résultat
    $row = $stmt->get_result()->fetch_assoc();
    // On retourne le nombre de notifications non lues
    return $row['nb'];
}

// Cette fonction compte le nombre de messages privés non lus pour l'utilisateur connecté
function countUnreadMessages($conn) {
    // Si personne n'est connecté, on retourne 0
    if (!isset($_SESSION['user_id'])) return 0;

    // On récupère l'ID de l'utilisateur connecté
    $id   = (int) $_SESSION['user_id'];
    // On compte les messages reçus (receiver_id) où lu = 0 pour cet utilisateur
    $stmt = $conn->prepare("SELECT COUNT(*) AS nb FROM messages WHERE receiver_id = ? AND lu = 0");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    // On retourne le nombre de messages non lus
    return $row['nb'];
}

// Cette fonction protège contre les failles XSS en échappant les caractères spéciaux HTML
function e($str) {
    // htmlspecialchars transforme par exemple < en &lt; pour éviter l'injection de code HTML
    // ENT_QUOTES protège aussi bien les guillemets simples que doubles
    // ?? '' retourne une chaîne vide si $str est null pour éviter les erreurs
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Cette fonction crée une nouvelle notification pour un utilisateur dans la base de données
function createNotification($conn, $userId, $type, $contenu, $lien = '#') {
    // On prépare l'insertion avec 4 paramètres : l'ID destinataire, le type, le contenu, le lien
    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, type, contenu, lien) VALUES (?, ?, ?, ?)"
    );
    // "i" = entier pour l'ID, "s" = chaîne de caractères pour les trois autres paramètres
    $stmt->bind_param("isss", $userId, $type, $contenu, $lien);
    $stmt->execute();
}

// Cette fonction vérifie si deux utilisateurs sont amis avec le statut "accepted"
function areFriends($conn, $userId, $otherId) {
    // On vérifie dans les deux sens : A ami B OU B ami A
    $stmt = $conn->prepare(
        "SELECT id FROM friendships
         WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?))
         AND status = 'accepted'"
    );
    // On passe les deux IDs dans les deux sens pour couvrir tous les cas possibles
    $stmt->bind_param("iiii", $userId, $otherId, $otherId, $userId);
    $stmt->execute();
    // num_rows > 0 signifie qu'une relation acceptée existe, on retourne true ou false
    return $stmt->get_result()->num_rows > 0;
}
