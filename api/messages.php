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
