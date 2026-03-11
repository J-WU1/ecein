<?php
// Ce fichier est un endpoint AJAX qui gère l'envoi et la réception des messages privés
// Il fonctionne en deux modes : POST pour envoyer un message, GET pour récupérer les nouveaux
// On inclut la connexion à la base de données
require_once '../includes/db.php';
// On inclut les fonctions de sécurité et de session
require_once '../includes/auth.php';

// On indique que la réponse sera du JSON
header('Content-Type: application/json');

// Si l'utilisateur n'est pas connecté, on bloque la requête
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit();
}

// On récupère l'ID de l'utilisateur connecté
$userId = (int) $_SESSION['user_id'];

// Si la méthode HTTP est POST, c'est qu'on veut envoyer un message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // On récupère l'action demandée
    $action     = $_POST['action']      ?? '';
    // On récupère l'ID du destinataire
    $receiverId = (int) ($_POST['receiver_id'] ?? 0);
    // On récupère le contenu du message en supprimant les espaces inutiles avec trim()
    $contenu    = trim($_POST['contenu'] ?? '');

    // Si l'action n'est pas "send", ou que le destinataire ou le contenu sont manquants, on refuse
    if ($action !== 'send' || !$receiverId || !$contenu) {
        echo json_encode(['success' => false]);
        exit();
    }

    // On insère le nouveau message dans la table messages
    $stmt = $conn->prepare(
        "INSERT INTO messages (sender_id, receiver_id, contenu) VALUES (?, ?, ?)"
    );
    // "i" = entier, "s" = chaîne de caractères
    $stmt->bind_param("iis", $userId, $receiverId, $contenu);
    $stmt->execute();
    // On récupère l'ID auto-généré du message qui vient d'être inséré
    $newId = $conn->insert_id;

    // On vérifie si un message a déjà été envoyé à ce destinataire dans les 10 dernières minutes
    // Cela évite d'envoyer une notification à chaque message dans une même conversation
    $stmtCheck = $conn->prepare("
        SELECT id FROM messages
        WHERE sender_id = ? AND receiver_id = ?
          AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
          AND id != ?
        LIMIT 1
    ");
    $stmtCheck->bind_param("iii", $userId, $receiverId, $newId);
    $stmtCheck->execute();

    // Si aucun message récent n'existe, on envoie une notification au destinataire
    if ($stmtCheck->get_result()->num_rows === 0) {
        // On récupère le prénom et nom de l'expéditeur pour la notification
        $stmtU = $conn->prepare("SELECT prenom, nom FROM users WHERE id = ?");
        $stmtU->bind_param("i", $userId);
        $stmtU->execute();
        $u   = $stmtU->get_result()->fetch_assoc();
        // On construit le texte de la notification en échappant les données avec e()
        $msg = e($u['prenom']) . ' ' . e($u['nom']) . ' vous a envoyé un message.';
        // On crée la notification avec un lien direct vers la conversation
        createNotification($conn, $receiverId, 'message', $msg, '../messagerie.php?with=' . $userId);
    }

    // On retourne les données du message en JSON pour que le JS l'affiche immédiatement
    echo json_encode([
        'success' => true,
        'message' => [
            // L'ID du message inséré en base
            'id'         => $newId,
            // Le contenu brut du message
            'contenu'    => $contenu,
            // L'ID de l'expéditeur
            'sender_id'  => $userId,
            // true car c'est forcément notre propre message dans ce cas
            'is_mine'    => true,
            // On formate la date pour l'affichage dans le chat
            'created_at' => date('d/m H:i'),
        ]
    ]);
    exit();
}

// Si la méthode HTTP est GET, c'est le polling : on cherche les nouveaux messages reçus
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // On récupère l'action demandée
    $action     = $_GET['action']      ?? '';
    // L'ID de l'autre personne dans la conversation
    $receiverId = (int) ($_GET['receiver_id'] ?? 0);
    // L'ID du dernier message déjà affiché, pour ne récupérer que les nouveaux
    $lastId     = (int) ($_GET['last_id']     ?? 0);

    // Si l'action n'est pas "poll" ou que l'ID de l'autre personne est manquant, on retourne vide
    if ($action !== 'poll' || !$receiverId) {
        echo json_encode(['messages' => []]);
        exit();
    }

    // On récupère uniquement les messages envoyés par l'autre personne après le dernier ID connu
    $stmt = $conn->prepare("
        SELECT m.id, m.contenu, m.sender_id, m.created_at,
               u.prenom, u.nom, u.photo
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.sender_id = ? AND m.receiver_id = ?
          AND m.id > ?
        ORDER BY m.created_at ASC
    ");
    // receiverId est l'expéditeur, userId est le destinataire qui reçoit le polling
    $stmt->bind_param("iii", $receiverId, $userId, $lastId);
    $stmt->execute();
    // On récupère tous les résultats sous forme de tableau associatif
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Si de nouveaux messages ont été trouvés, on les marque comme lus dans la base
    if (!empty($rows)) {
        $stmtRead = $conn->prepare(
            "UPDATE messages SET lu = 1 WHERE sender_id = ? AND receiver_id = ? AND id > ?"
        );
        $stmtRead->bind_param("iii", $receiverId, $userId, $lastId);
        $stmtRead->execute();
    }

    // On formate chaque message pour le retourner proprement au JavaScript
    $messages = array_map(function($row) use ($userId) {
        return [
            // On force l'ID en entier
            'id'         => (int) $row['id'],
            // Le contenu brut du message
            'contenu'    => $row['contenu'],
            // L'ID de l'expéditeur
            'sender_id'  => (int) $row['sender_id'],
            // false car dans le polling on ne récupère que les messages des autres
            'is_mine'    => false,
            // La photo de profil de l'expéditeur pour l'afficher dans le chat
            'photo'      => $row['photo'],
            // On formate la date lisiblement pour l'affichage
            'created_at' => date('d/m H:i', strtotime($row['created_at'])),
        ];
    }, $rows);

    // On retourne le tableau de messages en JSON
    echo json_encode(['messages' => $messages]);
    exit();
}

// Si la méthode HTTP n'est ni POST ni GET, on retourne une erreur
echo json_encode(['success' => false, 'error' => 'Méthode non supportée']);
