<?php
//  api/friends.php — Endpoint AJAX pour les demandes d'ami
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit();
}

$userId   = (int) $_SESSION['user_id'];
$action   = $_POST['action'] ?? '';
$friendId = (int) ($_POST['friend_id'] ?? 0);

if (!$friendId || $friendId === $userId) {
    echo json_encode(['success' => false]);
    exit();
}

// ACTION : Envoyer une demande d'ami
if ($action === 'request') {
    // Vérifie qu'il n'existe pas déjà une relation
    $stmt = $conn->prepare("
        SELECT id FROM friendships
        WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
    ");
    $stmt->bind_param("iiii", $userId, $friendId, $friendId, $userId);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Déjà en relation']);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO friendships (user_id, friend_id, status) VALUES (?, ?, 'pending')");
    $stmt->bind_param("ii", $userId, $friendId);
    $stmt->execute();

    // Notification au destinataire
    $stmtUser = $conn->prepare("SELECT prenom, nom FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $u = $stmtUser->get_result()->fetch_assoc();

    createNotification(
        $conn,
        $friendId,
        'friend_request',
        e($u['prenom']) . ' ' . e($u['nom']) . ' vous a envoyé une demande de connexion.',
        '../reseau.php'
    );

    echo json_encode(['success' => true]);
    exit();
}

// ACTION : Accepter une demande d'ami
if ($action === 'accept') {
    $stmt = $conn->prepare("
        UPDATE friendships SET status = 'accepted'
        WHERE user_id = ? AND friend_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("ii", $friendId, $userId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Notification à celui qui avait envoyé la demande
        $stmtUser = $conn->prepare("SELECT prenom, nom FROM users WHERE id = ?");
        $stmtUser->bind_param("i", $userId);
        $stmtUser->execute();
        $u = $stmtUser->get_result()->fetch_assoc();

        createNotification(
            $conn,
            $friendId,
            'friend_accept',
            e($u['prenom']) . ' ' . e($u['nom']) . ' a accepté votre demande de connexion.',
            '../reseau.php'
        );
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Demande introuvable']);
    }
    exit();
}

// ACTION : Refuser une demande d'ami
if ($action === 'reject') {
    $stmt = $conn->prepare("
        DELETE FROM friendships WHERE user_id = ? AND friend_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("ii", $friendId, $userId);
    $stmt->execute();

    echo json_encode(['success' => $stmt->affected_rows > 0]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Action inconnue']);
