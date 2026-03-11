<?php
// Ce fichier est un endpoint AJAX qui gère les demandes de connexion entre utilisateurs
// On inclut la connexion à la base de données
require_once '../includes/db.php';
// On inclut les fonctions de sécurité et de session
require_once '../includes/auth.php';

// On indique que la réponse sera du JSON
header('Content-Type: application/json');

// Si l'utilisateur n'est pas connecté, on bloque la requête
if (!isset($_SESSION['user_id'])) {
    // On retourne une erreur en JSON et on arrête le script
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit();
}

// On récupère l'ID de l'utilisateur connecté en le forçant en entier pour la sécurité
$userId   = (int) $_SESSION['user_id'];
// On récupère l'action demandée depuis les données POST
$action   = $_POST['action'] ?? '';
// On récupère l'ID de l'autre utilisateur concerné par l'action
$friendId = (int) ($_POST['friend_id'] ?? 0);

// Si l'ID de l'autre utilisateur est manquant ou identique au sien, on refuse
if (!$friendId || $friendId === $userId) {
    // On retourne un échec et on arrête le script
    echo json_encode(['success' => false]);
    exit();
}

// Si l'action est "request", on envoie une demande de connexion
if ($action === 'request') {
    // On vérifie qu'il n'existe pas déjà une relation entre ces deux utilisateurs dans les deux sens
    $stmt = $conn->prepare("
        SELECT id FROM friendships
        WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
    ");
    // On passe les deux IDs dans les deux sens pour couvrir tous les cas
    $stmt->bind_param("iiii", $userId, $friendId, $friendId, $userId);
    $stmt->execute();

    // Si une relation existe déjà, on refuse d'en créer une nouvelle
    if ($stmt->get_result()->num_rows > 0) {
        // On retourne une erreur et on arrête le script
        echo json_encode(['success' => false, 'error' => 'Déjà en relation']);
        exit();
    }

    // On insère la nouvelle demande avec le statut "pending" (en attente)
    $stmt = $conn->prepare("INSERT INTO friendships (user_id, friend_id, status) VALUES (?, ?, 'pending')");
    $stmt->bind_param("ii", $userId, $friendId);
    $stmt->execute();

    // On récupère le prénom et nom de l'expéditeur pour construire la notification
    $stmtUser = $conn->prepare("SELECT prenom, nom FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    // On stocke les infos de l'utilisateur dans un tableau associatif
    $u = $stmtUser->get_result()->fetch_assoc();

    // On envoie une notification au destinataire de la demande
    createNotification(
        $conn,
        $friendId,
        'friend_request',
        // On échappe les données avec e() pour éviter les failles XSS
        e($u['prenom']) . ' ' . e($u['nom']) . ' vous a envoyé une demande de connexion.',
        '../reseau.php'
    );

    // On retourne un succès en JSON
    echo json_encode(['success' => true]);
    exit();
}

// Si l'action est "accept", on accepte une demande de connexion reçue
if ($action === 'accept') {
    // On met à jour le statut de la demande de "pending" à "accepted"
    $stmt = $conn->prepare("
        UPDATE friendships SET status = 'accepted'
        WHERE user_id = ? AND friend_id = ? AND status = 'pending'
    ");
    // friendId est celui qui a envoyé la demande, userId est celui qui l'accepte
    $stmt->bind_param("ii", $friendId, $userId);
    $stmt->execute();

    // Si affected_rows > 0, une ligne a bien été modifiée donc la demande existait
    if ($stmt->affected_rows > 0) {
        // On récupère les infos de l'utilisateur qui accepte pour la notification
        $stmtUser = $conn->prepare("SELECT prenom, nom FROM users WHERE id = ?");
        $stmtUser->bind_param("i", $userId);
        $stmtUser->execute();
        $u = $stmtUser->get_result()->fetch_assoc();

        // On notifie celui qui avait envoyé la demande que son invitation est acceptée
        createNotification(
            $conn,
            $friendId,
            'friend_accept',
            e($u['prenom']) . ' ' . e($u['nom']) . ' a accepté votre demande de connexion.',
            '../reseau.php'
        );
        // On retourne un succès en JSON
        echo json_encode(['success' => true]);
    } else {
        // Si aucune ligne modifiée, la demande n'existait pas ou était déjà acceptée
        echo json_encode(['success' => false, 'error' => 'Demande introuvable']);
    }
    exit();
}

// Si l'action est "reject", on supprime la demande de connexion en attente
if ($action === 'reject') {
    // On supprime la ligne dans friendships correspondant à la demande en attente
    $stmt = $conn->prepare("
        DELETE FROM friendships WHERE user_id = ? AND friend_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("ii", $friendId, $userId);
    $stmt->execute();

    // On retourne true si la suppression a fonctionné (affected_rows > 0), false sinon
    echo json_encode(['success' => $stmt->affected_rows > 0]);
    exit();
}

// Si l'action ne correspond à aucun cas connu, on retourne une erreur
echo json_encode(['success' => false, 'error' => 'Action inconnue']);
