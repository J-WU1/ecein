<?php
/*  reseau.php — Mon Réseau */

require_once 'includes/db.php';         /* Charge la connexion à la base de données */
require_once 'includes/auth.php';       /* Charge les fonctions d'authentification */
requireLogin();                         /* Redirige vers login.php si l'utilisateur n'est pas connecté */


$pageTitle = "Mon Réseau";              /* Titre de la page */
$userId = (int) $_SESSION['user_id'];   /* ID de l'utilisateur connecté converti en entier pour la sécurité */

/* TRAITEMENT : Accepter une demande de connexion (fallback sans JS)*/

if (isset($_GET['accept'])) {
    $fid  = (int) $_GET['accept'];      /* ID de l'utilisateur qui avait envoyé la demande */
    /* Met à jour le statut de "pending" à "accepted" dans la table friendships */
    $stmt = $conn->prepare("UPDATE friendships SET status='accepted' WHERE user_id=? AND friend_id=?");
    $stmt->bind_param("ii", $fid, $userId);
    $stmt->execute();
    header("Location: reseau.php"); exit();
}

/* TRAITEMENT : Refuser une demande de connexion */

if (isset($_GET['reject'])) {
    $fid  = (int) $_GET['reject'];      /* ID de l'utilisateur qui avait envoyé la demande */
    /* Supprime complètement la demande de la table friendships */
    $stmt = $conn->prepare("DELETE FROM friendships WHERE user_id=? AND friend_id=?");
    $stmt->bind_param("ii", $fid, $userId);
    $stmt->execute();
    header("Location: reseau.php"); exit();
}

/* TRAITEMENT : Retirer une connexion existante */

if (isset($_GET['remove'])) {
    $fid  = (int) $_GET['remove'];      /* ID de l'ami à retirer */
    /* Supprime dans les deux sens car user_id et friend_id peuvent être inversés */
    $stmt = $conn->prepare("
        DELETE FROM friendships
        WHERE (user_id=? AND friend_id=?) OR (user_id=? AND friend_id=?)
    ");
    $stmt->bind_param("iiii", $userId, $fid, $fid, $userId);
    $stmt->execute();
    header("Location: reseau.php"); exit();
}

/* RÉCUPÉRATION : Liste de mes amis (statut "accepted") */

$stmtFriends = $conn->prepare("
    SELECT u.id, u.nom, u.prenom, u.photo, u.poste, u.entreprise, u.bio
    FROM users u
    JOIN friendships f ON (
        -- La relation peut être dans les deux sens (A→B ou B→A)
        (f.user_id = ? AND f.friend_id = u.id) OR
        (f.friend_id = ? AND f.user_id = u.id)
    )
    WHERE f.status = 'accepted'  -- Uniquement les connexions confirmées
      AND u.id != ?              -- Exclut l'utilisateur lui-même
    ORDER BY u.prenom, u.nom     -- Tri alphabétique par prénom
");
$stmtFriends->bind_param("iii", $userId, $userId, $userId);
$stmtFriends->execute();
$friends = $stmtFriends->get_result()->fetch_all(MYSQLI_ASSOC);
$friendIds = array_column($friends, 'id');                  /* Extrait juste les IDs des amis pour les utiliser dans les suggestions */

/* RÉCUPÉRATION : Demandes de connexion reçues (en attente) */

$stmtPending = $conn->prepare("
    SELECT u.id, u.nom, u.prenom, u.photo, u.poste
    FROM users u
    JOIN friendships f ON f.user_id = u.id
    -- On cherche les demandes où je suis le destinataire (friend_id = mon ID)
    WHERE f.friend_id = ? AND f.status = 'pending'
");
$stmtPending->bind_param("i", $userId);
$stmtPending->execute();
$pendingRequests = $stmtPending->get_result()->fetch_all(MYSQLI_ASSOC);

/* RÉCUPÉRATION : Suggestions (amis d'amis non encore connectés) */

$suggestions = [];
if (!empty($friendIds)) {
    /* Crée une liste d'IDs séparés par des virgules pour la clause IN */
    $inList   = implode(',', array_map('intval', $friendIds));
    /* Cherche les amis de mes amis qui ne sont pas encore dans mon réseau */
    $stmtSugg = $conn->query("
        SELECT DISTINCT u.id, u.nom, u.prenom, u.photo, u.poste
        FROM users u
        JOIN friendships f ON (f.user_id = u.id OR f.friend_id = u.id)
        WHERE f.status = 'accepted'
          AND u.id != $userId              -- Pas moi
          AND u.id NOT IN ($inList, $userId) -- Pas déjà dans mon réseau
          AND (f.user_id IN ($inList) OR f.friend_id IN ($inList)) -- Ami d'un ami
        LIMIT 6
    ");
    $suggestions = $stmtSugg->fetch_all(MYSQLI_ASSOC);
}

/* Si moins de 6 suggestions via amis d'amis, complète avec des membres aléatoires */
if (count($suggestions) < 6) {
    /* Liste de tous les IDs à exclure pour éviter les doublons */
    $excludeIds = array_merge($friendIds, [$userId], array_column($suggestions, 'id'));
    $exclude    = implode(',', array_map('intval', $excludeIds));
    $limit      = 6 - count($suggestions);          /* Nombre de suggestions manquantes */
    $stmtMore   = $conn->query("
        SELECT u.id, u.nom, u.prenom, u.photo, u.poste
        FROM users u
        WHERE u.id NOT IN ($exclude)
          AND u.id NOT IN (
              -- Exclut les membres avec qui une demande est déjà en cours
              SELECT CASE WHEN user_id = $userId THEN friend_id ELSE user_id END
              FROM friendships WHERE (user_id = $userId OR friend_id = $userId)
          )
        ORDER BY RAND()  -- Ordre aléatoire pour varier les suggestions
        LIMIT $limit
    ");
    /* Fusionne les deux tableaux de suggestions */
    $suggestions = array_merge($suggestions, $stmtMore->fetch_all(MYSQLI_ASSOC));
}

/* Charge le header commun */
include 'includes/header.php';
?>