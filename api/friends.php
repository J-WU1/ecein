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

// Fichier en cours de développement
