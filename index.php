<?php
/*  index.php — Fil d'actualité (Accueil) */

/* Charge la connexion à la base de données */
require_once 'includes/db.php';
/* Charge les fonctions d'authentification et de session */
require_once 'includes/auth.php';
/* Redirige vers login.php si l'utilisateur n'est pas connecté */
requireLogin();

/* Titre affiché dans l'onglet du navigateur */
$pageTitle = "Accueil";
/* Récupère l'ID de l'utilisateur connecté depuis la session et le convertit en entier (sécurité) */
$userId = (int) $_SESSION['user_id'];

/* TRAITEMENT : Publication d'un nouveau post

   On vérifie que le formulaire a été soumis en POST avec l'action "post" */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post') {

    /* Récupère et nettoie le contenu texte du post */
    $contenu    = trim($_POST['contenu']    ?? '');
    /* Récupère le lieu indiqué par l'utilisateur */
    $lieu       = trim($_POST['lieu']       ?? '');
    /* Récupère la visibilité choisie (public/amis/prive), public par défaut */
    $visibilite = $_POST['visibilite']       ?? 'public';
    /* Chemin du fichier média uploadé (vide si pas de fichier) */
    $mediaPath  = '';
    /* Type du média : none, image, video ou pdf */
    $mediaType  = 'none';

    /* Vérifie si un fichier a été uploadé avec le post */
    if (!empty($_FILES['media']['name'])) {
        /* Extensions autorisées pour les uploads */
        $allowed = ['jpg','jpeg','png','gif','mp4','webm','pdf'];
        /* Récupère l'extension du fichier en minuscules */
        $ext     = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));

        /* Vérifie que l'extension est dans la liste autorisée */
        if (in_array($ext, $allowed)) {
            /* Génère un nom de fichier unique pour éviter les conflits */
            $filename  = uniqid('post_') . '.' . $ext;
            $uploadDir = 'uploads/';
            /* Déplace le fichier temporaire vers le dossier uploads */
            if (move_uploaded_file($_FILES['media']['tmp_name'], $uploadDir . $filename)) {
                $mediaPath = $filename;
                /* Détermine le type de média selon l'extension */
                if (in_array($ext, ['jpg','jpeg','png','gif'])) $mediaType = 'image';
                elseif (in_array($ext, ['mp4','webm']))          $mediaType = 'video';
                elseif ($ext === 'pdf')                           $mediaType = 'pdf';
            }
        }
    }

    /* Insère le post en BDD seulement s'il y a du texte ou un fichier */
    if ($contenu || $mediaPath) {
        /* Requête préparée pour protéger contre les injections SQL */
        $stmt = $conn->prepare(
            "INSERT INTO posts (user_id, contenu, media, media_type, lieu, visibilite)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        /* "i" = integer, "s" = string */
        $stmt->bind_param("isssss", $userId, $contenu, $mediaPath, $mediaType, $lieu, $visibilite);
        $stmt->execute();
    }

    /* Redirige pour éviter la re-soumission du formulaire au rechargement */
    header("Location: index.php");
    exit();
}

/* TRAITEMENT : Suppression d'un post */

if (isset($_GET['delete_post'])) {
    /* Convertit en entier pour éviter les injections SQL */
    $postId = (int) $_GET['delete_post'];
    /* La condition "AND user_id = ?" empêche de supprimer le post d'un autre utilisateur */
    $stmt   = $conn->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $postId, $userId);
    $stmt->execute();
    header("Location: index.php");
    exit();
}

/* RÉCUPÉRATION : Fil d'actualité 

   Affiche les posts publics + ses propres posts + posts amis si visibilité "amis"
   Triés du plus récent au plus ancien (ORDER BY DESC) */
$sql = "
    SELECT p.*, u.nom, u.prenom, u.photo, u.poste,
           -- Compte le nombre total de réactions sur ce post
           (SELECT COUNT(*) FROM reactions r WHERE r.post_id = p.id) AS nb_reactions,
           -- Compte le nombre total de commentaires sur ce post
           (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS nb_comments,
           -- Récupère la réaction de l'utilisateur connecté sur ce post (null si aucune)
           (SELECT type FROM reactions WHERE post_id = p.id AND user_id = ?) AS my_reaction
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.visibilite = 'public'                    -- Posts publics visibles par tous
       OR p.user_id = ?                              -- Ses propres posts toujours visibles
       OR (
           p.visibilite = 'amis'                     -- Posts amis visibles uniquement par les amis
           AND p.user_id IN (
               -- Sous-requête pour récupérer les IDs des amis acceptés
               SELECT CASE WHEN user_id = ? THEN friend_id ELSE user_id END
               FROM friendships
               WHERE (user_id = ? OR friend_id = ?) AND status = 'accepted'
           )
       )
    ORDER BY p.created_at DESC   -- Du plus récent au plus ancien
    LIMIT 30                     -- Maximum 30 posts affichés
";
$stmt = $conn->prepare($sql);
/* On passe 5 fois l'userId car il est utilisé 5 fois dans la requête */
$stmt->bind_param("iiiii", $userId, $userId, $userId, $userId, $userId);
$stmt->execute();
/* Récupère tous les résultats sous forme de tableau associatif */
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* RÉCUPÉRATION : Suggestions de connexion 

   Membres qui ne sont pas encore dans le réseau de l'utilisateur */
$stmtSugg = $conn->prepare("
    SELECT u.id, u.nom, u.prenom, u.photo, u.poste
    FROM users u
    WHERE u.id != ?    -- Exclut l'utilisateur connecté lui-même
      AND u.id NOT IN (
          -- Exclut les membres déjà connectés ou avec une demande en cours
          SELECT CASE WHEN user_id = ? THEN friend_id ELSE user_id END
          FROM friendships
          WHERE (user_id = ? OR friend_id = ?) AND status IN ('accepted','pending')
      )
    ORDER BY RAND()    -- Ordre aléatoire pour varier les suggestions
    LIMIT 4            -- Maximum 4 suggestions affichées
");
$stmtSugg->bind_param("iiii", $userId, $userId, $userId, $userId);
$stmtSugg->execute();
$suggestions = $stmtSugg->get_result()->fetch_all(MYSQLI_ASSOC);

/* RÉCUPÉRATION : Événement de la semaine
   
   On prend le post public le plus récent publié par un administrateur */
$stmtEvent = $conn->prepare("
    SELECT p.*, u.nom, u.prenom
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE u.role = 'admin'            -- Uniquement les posts des admins
      AND p.visibilite = 'public'     -- Uniquement les posts publics
    ORDER BY p.created_at DESC        -- Le plus récent en premier
    LIMIT 1                           -- On n'affiche qu'un seul événement
");
$stmtEvent->execute();
$eventSemaine = $stmtEvent->get_result()->fetch_assoc();

/* Charge le header commun (navbar, CSS, Bootstrap) */
include 'includes/header.php';
?>