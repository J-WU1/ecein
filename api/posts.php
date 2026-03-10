<?php
//  api/posts.php — Endpoint AJAX pour réactions et commentaires
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Doit être connecté
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit();
}

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// ACTION : Ajouter ou retirer une réaction sur un post
if ($action === 'react') {
    $postId = (int) ($_POST['post_id'] ?? 0);
    $type   = $_POST['type'] ?? 'like';

    // Vérifie que la réaction est valide
    if (!in_array($type, ['like', 'love', 'bravo'])) {
        echo json_encode(['success' => false]);
        exit();
    }

    // Vérifie si l'utilisateur a déjà réagi
    $stmt = $conn->prepare("SELECT id, type FROM reactions WHERE post_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $postId, $userId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        if ($existing['type'] === $type) {
            // Même type → on retire la réaction (toggle)
            $stmt = $conn->prepare("DELETE FROM reactions WHERE post_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $postId, $userId);
            $stmt->execute();
            $reacted = false;
        } else {
            // Type différent → on met à jour
            $stmt = $conn->prepare("UPDATE reactions SET type = ? WHERE post_id = ? AND user_id = ?");
            $stmt->bind_param("sii", $type, $postId, $userId);
            $stmt->execute();
            $reacted = true;
        }
    } else {
        // Pas encore de réaction → on en crée une
        $stmt = $conn->prepare("INSERT INTO reactions (post_id, user_id, type) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $postId, $userId, $type);
        $stmt->execute();
        $reacted = true;

        // Notification à l'auteur du post (si ce n'est pas lui-même)
        $stmtPost = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmtPost->bind_param("i", $postId);
        $stmtPost->execute();
        $post = $stmtPost->get_result()->fetch_assoc();

        if ($post && $post['user_id'] != $userId) {
            $stmtUser = $conn->prepare("SELECT prenom, nom FROM users WHERE id = ?");
            $stmtUser->bind_param("i", $userId);
            $stmtUser->execute();
            $u = $stmtUser->get_result()->fetch_assoc();
            $msg = e($u['prenom']) . ' ' . e($u['nom']) . ' a réagi à votre publication.';
            createNotification($conn, $post['user_id'], 'like', $msg, '../index.php');
        }
    }

// Fichier en cours de développement
