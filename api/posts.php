<?php
// Ce fichier est un endpoint AJAX qui gère les réactions et les commentaires sur les posts
// On inclut la connexion à la base de données
require_once '../includes/db.php';
// On inclut les fonctions de sécurité et de session
require_once '../includes/auth.php';

// On indique au navigateur que la réponse sera du JSON et non du HTML
header('Content-Type: application/json');

// Si l'utilisateur n'est pas connecté, on refuse la requête
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit();
}

// On récupère l'ID de l'utilisateur connecté en le forçant en entier pour la sécurité
$userId = (int) $_SESSION['user_id'];
// On récupère l'action demandée depuis les données POST
$action = $_POST['action'] ?? '';

// Si l'action demandée est "react", on gère l'ajout ou le retrait d'une réaction
if ($action === 'react') {
    // On récupère l'ID du post concerné
    $postId = (int) ($_POST['post_id'] ?? 0);
    // On récupère le type de réaction envoyé (like, love ou bravo)
    $type   = $_POST['type'] ?? 'like';

    // On vérifie que le type de réaction est bien parmi les valeurs autorisées
    if (!in_array($type, ['like', 'love', 'bravo'])) {
        echo json_encode(['success' => false]);
        exit();
    }

    // On vérifie si l'utilisateur a déjà réagi à ce post
    $stmt = $conn->prepare("SELECT id, type FROM reactions WHERE post_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $postId, $userId);
    $stmt->execute();
    // On stocke la réaction existante si elle existe
    $existing = $stmt->get_result()->fetch_assoc();

    // Si une réaction existe déjà pour cet utilisateur sur ce post
    if ($existing) {
        if ($existing['type'] === $type) {
            // Si c'est le même type, on supprime la réaction (comportement toggle)
            $stmt = $conn->prepare("DELETE FROM reactions WHERE post_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $postId, $userId);
            $stmt->execute();
            // L'utilisateur n'a plus de réaction active sur ce post
            $reacted = false;
        } else {
            // Si c'est un type différent, on met à jour la réaction existante
            $stmt = $conn->prepare("UPDATE reactions SET type = ? WHERE post_id = ? AND user_id = ?");
            $stmt->bind_param("sii", $type, $postId, $userId);
            $stmt->execute();
            // L'utilisateur a toujours une réaction active
            $reacted = true;
        }
    } else {
        // Si aucune réaction n'existe encore, on en crée une nouvelle
        $stmt = $conn->prepare("INSERT INTO reactions (post_id, user_id, type) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $postId, $userId, $type);
        $stmt->execute();
        // L'utilisateur a maintenant une réaction active
        $reacted = true;

        // On récupère l'auteur du post pour lui envoyer une notification
        $stmtPost = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmtPost->bind_param("i", $postId);
        $stmtPost->execute();
        $post = $stmtPost->get_result()->fetch_assoc();

        // On n'envoie pas de notification si l'utilisateur réagit à son propre post
        if ($post && $post['user_id'] != $userId) {
            // On récupère le prénom et nom de l'utilisateur qui a réagi
            $stmtUser = $conn->prepare("SELECT prenom, nom FROM users WHERE id = ?");
            $stmtUser->bind_param("i", $userId);
            $stmtUser->execute();
            $u = $stmtUser->get_result()->fetch_assoc();
            // On construit le texte de la notification
            $msg = e($u['prenom']) . ' ' . e($u['nom']) . ' a réagi à votre publication.';
            // On crée la notification pour l'auteur du post
            createNotification($conn, $post['user_id'], 'like', $msg, '../index.php');
        }
    }

    // On recompte le nombre total de réactions sur ce post pour mettre à jour l'affichage
    $stmtCount = $conn->prepare("SELECT COUNT(*) AS nb FROM reactions WHERE post_id = ?");
    $stmtCount->bind_param("i", $postId);
    $stmtCount->execute();
    $count = $stmtCount->get_result()->fetch_assoc()['nb'];

    // On retourne le résultat en JSON : succès, état de la réaction, et nouveau compteur
    echo json_encode(['success' => true, 'reacted' => $reacted, 'count' => $count]);
    exit();
}

// Si l'action demandée est "comment", on gère l'ajout d'un commentaire
if ($action === 'comment') {
    // On récupère l'ID du post concerné
    $postId  = (int) ($_POST['post_id'] ?? 0);
    // On récupère le contenu du commentaire en supprimant les espaces inutiles
    $contenu = trim($_POST['contenu']   ?? '');

    // Si le commentaire est vide ou que l'ID du post est manquant, on refuse
    if (!$contenu || !$postId) {
        echo json_encode(['success' => false]);
        exit();
    }

    // On insère le commentaire dans la table comments
    $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, contenu) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $postId, $userId, $contenu);
    $stmt->execute();

    // On récupère les infos de l'utilisateur pour les renvoyer au JavaScript
    $stmtUser = $conn->prepare("SELECT prenom, nom, photo FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $user = $stmtUser->get_result()->fetch_assoc();

    // On récupère l'auteur du post pour lui envoyer une notification
    $stmtPost = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmtPost->bind_param("i", $postId);
    $stmtPost->execute();
    $post = $stmtPost->get_result()->fetch_assoc();

    // On n'envoie pas de notification si l'utilisateur commente son propre post
    if ($post && $post['user_id'] != $userId) {
        $msg = e($user['prenom']) . ' ' . e($user['nom']) . ' a commenté votre publication.';
        // On crée la notification pour l'auteur du post
        createNotification($conn, $post['user_id'], 'comment', $msg, '../index.php');
    }

    // On retourne les données du commentaire en JSON pour que le JS l'affiche sans recharger
    echo json_encode([
        'success' => true,
        // On échappe toutes les données retournées pour éviter les failles XSS côté client
        'contenu' => htmlspecialchars($contenu, ENT_QUOTES, 'UTF-8'),
        'prenom'  => htmlspecialchars($user['prenom'], ENT_QUOTES, 'UTF-8'),
        'nom'     => htmlspecialchars($user['nom'], ENT_QUOTES, 'UTF-8'),
        'photo'   => htmlspecialchars($user['photo'], ENT_QUOTES, 'UTF-8'),
    ]);
    exit();
}

// Si l'action ne correspond ni à "react" ni à "comment", on retourne une erreur
echo json_encode(['success' => false, 'error' => 'Action inconnue']);
