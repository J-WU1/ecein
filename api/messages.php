<?php
//  api/messages.php — Endpoint AJAX messagerie
//  Actions : send (envoyer) | poll (récupérer nouveaux msgs)
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit();
}

$userId = (int) $_SESSION['user_id'];

// ACTION : Envoyer un message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action']      ?? '';
    $receiverId = (int) ($_POST['receiver_id'] ?? 0);
    $contenu    = trim($_POST['contenu'] ?? '');

    if ($action !== 'send' || !$receiverId || !$contenu) {
        echo json_encode(['success' => false]);
        exit();
    }

    // Insère le message
    $stmt = $conn->prepare(
        "INSERT INTO messages (sender_id, receiver_id, contenu) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iis", $userId, $receiverId, $contenu);
    $stmt->execute();
    $newId = $conn->insert_id;

    // Notification au destinataire (une seule fois par session de chat = si pas de msg récent)
    $stmtCheck = $conn->prepare("
        SELECT id FROM messages
        WHERE sender_id = ? AND receiver_id = ?
          AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
          AND id != ?
        LIMIT 1
    ");
    $stmtCheck->bind_param("iii", $userId, $receiverId, $newId);
    $stmtCheck->execute();

    if ($stmtCheck->get_result()->num_rows === 0) {
        $stmtU = $conn->prepare("SELECT prenom, nom FROM users WHERE id = ?");
        $stmtU->bind_param("i", $userId);
        $stmtU->execute();
        $u   = $stmtU->get_result()->fetch_assoc();
        $msg = e($u['prenom']) . ' ' . e($u['nom']) . ' vous a envoyé un message.';
        createNotification($conn, $receiverId, 'message', $msg, '../messagerie.php?with=' . $userId);
    }
    // Retourne le message créé
    echo json_encode([
        'success' => true,
        'message' => [
            'id'         => $newId,
            'contenu'    => $contenu,
            'sender_id'  => $userId,
            'is_mine'    => true,
            'created_at' => date('d/m H:i'),
        ]
    ]);
    exit();
}

// ACTION : Polling — récupère les messages arrivés après last_id
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action     = $_GET['action']      ?? '';
    $receiverId = (int) ($_GET['receiver_id'] ?? 0); // "receiver" = l'autre personne
    $lastId     = (int) ($_GET['last_id']     ?? 0);

    if ($action !== 'poll' || !$receiverId) {
        echo json_encode(['messages' => []]);
        exit();
    }

    // On cherche les nouveaux messages REÇUS (envoyés par l'autre)
    $stmt = $conn->prepare("
        SELECT m.id, m.contenu, m.sender_id, m.created_at,
               u.prenom, u.nom, u.photo
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.sender_id = ? AND m.receiver_id = ?
          AND m.id > ?
        ORDER BY m.created_at ASC
    ");
    $stmt->bind_param("iii", $receiverId, $userId, $lastId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Marque ces messages comme lus
    if (!empty($rows)) {
        $stmtRead = $conn->prepare(
            "UPDATE messages SET lu = 1 WHERE sender_id = ? AND receiver_id = ? AND id > ?"
        );
        $stmtRead->bind_param("iii", $receiverId, $userId, $lastId);
        $stmtRead->execute();
    }

    // Formate les messages pour le JS
    $messages = array_map(function($row) use ($userId) {
        return [
            'id'         => (int) $row['id'],
            'contenu'    => $row['contenu'],
            'sender_id'  => (int) $row['sender_id'],
            'is_mine'    => false,          // ce sont forcément des messages reçus
            'photo'      => $row['photo'],
            'created_at' => date('d/m H:i', strtotime($row['created_at'])),
        ];
    }, $rows);

    echo json_encode(['messages' => $messages]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Méthode non supportée']);
