<?php
// index.php — Fil d'actualité, page d'accueil du site

require_once 'includes/db.php';             // charge la connexion MySQL dans la variable $conn
require_once 'includes/auth.php';           // démarre la session et charge les fonctions d'auth
requireLogin();                             // redirige vers login.php si l'utilisateur n'est pas connecté

$pageTitle = "Accueil";                     // titre affiché dans l'onglet du navigateur via header.php
$userId    = (int) $_SESSION['user_id'];    // ID de l'utilisateur connecté, casté en int pour sécuriser les requêtes SQL

// Traitement de la publication d'un nouveau post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post') { // vérifie que c'est bien une soumission du formulaire de publication
    $contenu    = trim($_POST['contenu']    ?? '');         // texte du post, on supprime les espaces inutiles en début et fin
    $lieu       = trim($_POST['lieu']       ?? '');         // lieu optionnel associé au post, peut être vide
    $visibilite = $_POST['visibilite']      ?? 'public';    // niveau de visibilité choisi, 'public' par défaut si absent
    $mediaPath  = '';                                       // chemin du fichier uploadé, vide si pas de fichier
    $mediaType  = 'none';                                   // type du média : 'image', 'video', 'pdf' ou 'none'

    // Upload du fichier (image ou vidéo)
    if (!empty($_FILES['media']['name'])) {                 // on entre ici seulement si un fichier a été joint au post
        $allowed   = ['jpg','jpeg','png','gif','mp4','webm','pdf']; // seules ces extensions sont autorisées
        $ext       = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION)); // extension du fichier uploadé en minuscules

        if (in_array($ext, $allowed)) {                 // vérifie que l'extension est dans la liste blanche
            $filename  = uniqid('post_') . '.' . $ext;  // nom unique basé sur un timestamp microsecondes, évite les conflits de nom
            $uploadDir = 'uploads/';                    // dossier de destination des fichiers uploadés
            if (move_uploaded_file($_FILES['media']['tmp_name'], $uploadDir . $filename)) { // déplace le fichier du dossier temporaire PHP vers uploads/
                $mediaPath = $filename;                 // on mémorise le nom du fichier pour l'enregistrer en base
                if (in_array($ext, ['jpg','jpeg','png','gif'])) $mediaType = 'image';       // c'est une image
                elseif (in_array($ext, ['mp4','webm']))         $mediaType = 'video';       // c'est une vidéo
                elseif ($ext === 'pdf')                          $mediaType = 'pdf';        // c'est un PDF
            }
        }
    }

    if ($contenu || $mediaPath) {   // on insère en base seulement si le post a du contenu textuel ou un fichier
        $stmt = $conn->prepare(     // prépare l'insertion du post
            "INSERT INTO posts (user_id, contenu, media, media_type, lieu, visibilite)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isssss", $userId, $contenu, $mediaPath, $mediaType, $lieu, $visibilite); // lie les 6 paramètres : 1 int + 5 strings
        $stmt->execute();           // exécute l'insertion en base
    }

    header("Location: index.php");  // redirige pour éviter la re-soumission du formulaire au rechargement
    exit();                         // stoppe l'exécution du script après la redirection
}

// Traitement de la suppression d'un post
if (isset($_GET['delete_post'])) {  // déclenché quand l'URL contient ?delete_post=ID
    $postId = (int) $_GET['delete_post'];       // ID du post à supprimer, casté en int
    $stmt   = $conn->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?"); // AND user_id garantit qu'on ne supprime que ses propres posts
    $stmt->bind_param("ii", $postId, $userId);  // deux paramètres int
    $stmt->execute();               // exécute la suppression
    header("Location: index.php");  // redirige pour nettoyer l'URL après suppression
    exit();
}

// Récupère le fil d'actualité : posts de l'utilisateur + posts de ses amis + posts publics
$sql = "
    SELECT p.*, u.nom, u.prenom, u.photo, u.poste,
           (SELECT COUNT(*) FROM reactions r WHERE r.post_id = p.id) AS nb_reactions,
           (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS nb_comments,
           (SELECT type FROM reactions WHERE post_id = p.id AND user_id = ?) AS my_reaction
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.visibilite = 'public'
       OR p.user_id = ?
       OR (
           p.visibilite = 'amis'
           AND p.user_id IN (
               SELECT CASE WHEN user_id = ? THEN friend_id ELSE user_id END
               FROM friendships
               WHERE (user_id = ? OR friend_id = ?) AND status = 'accepted'
           )
       )
    ORDER BY p.created_at DESC
    LIMIT 30
"; // requête principale du fil : inclut les posts publics + les siens + les posts amis des connexions acceptées, triés du plus récent, limités à 30
$stmt = $conn->prepare($sql);   // prépare la requête
$stmt->bind_param("iiiii", $userId, $userId, $userId, $userId, $userId); // $userId apparaît 5 fois dans la requête
$stmt->execute();               // exécute la requête
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); // récupère tous les posts sous forme de tableau associatif

// Récupère les suggestions de connexion pour la sidebar droite
$stmtSugg = $conn->prepare("
    SELECT u.id, u.nom, u.prenom, u.photo, u.poste
    FROM users u
    WHERE u.id != ?
      AND u.id NOT IN (
          SELECT CASE WHEN user_id = ? THEN friend_id ELSE user_id END
          FROM friendships
          WHERE (user_id = ? OR friend_id = ?) AND status IN ('accepted','pending')
      )
    ORDER BY RAND()
    LIMIT 4
"); // exclut l'utilisateur connecté et ceux déjà en relation (amis ou demande en cours), ordre aléatoire, 4 max
$stmtSugg->bind_param("iiii", $userId, $userId, $userId, $userId);  // 4 fois $userId
$stmtSugg->execute();
$suggestions = $stmtSugg->get_result()->fetch_all(MYSQLI_ASSOC);    // tableau des utilisateurs suggérés

// Récupère l'événement de la semaine : le post public le plus récent d'un admin
$stmtEvent = $conn->prepare("
    SELECT p.*, u.nom, u.prenom
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE u.role = 'admin' AND p.visibilite = 'public'
    ORDER BY p.created_at DESC
    LIMIT 1
"); // joint users pour avoir le nom, filtre sur le rôle admin et la visibilité public, prend le plus récent
$stmtEvent->execute();          // pas de paramètre à lier
$eventSemaine = $stmtEvent->get_result()->fetch_assoc(); // null si aucun post admin trouvé

include 'includes/header.php';  // inclut la navbar et le head HTML
?>

<div class="container mt-4">    <!-- conteneur Bootstrap centré avec marge haute -->

    <div id="special-day-banner" class="special-day-banner d-none"></div> <!-- bannière vide masquée par d-none, remplie et rendue visible par main.js si on est un jour spécial -->

    <div class="row g-3">       <!-- grille Bootstrap avec gouttière 3 entre les colonnes -->

        <!-- Colonne de gauche : Profil rapide -->
        <div class="col-lg-3 d-none d-lg-block">            <!-- sidebar gauche masquée sur mobile, visible à partir de 992px -->
            <div class="card sidebar-card text-center p-3"> <!-- card avec position:sticky défini dans style.css -->
                <img src="uploads/<?= e($currentUser['photo']) ?>"              <!-- photo de l utilisateur connecté récupérée depuis la session -->
                     class="rounded-circle mx-auto mb-2 border border-warning"  <!-- cercle centré avec bordure jaune ECE -->
                     width="72" height="72" style="object-fit:cover;">          <!-- 72x72 pixels, recadrage sans déformation -->
                <h6 class="mb-0"><?= e($currentUser['prenom']) ?> <?= e($currentUser['nom']) ?></h6> <!-- prénom et nom protégés contre XSS -->
                <small class="text-muted"><?= e($currentUser['poste'] ?? 'Membre ECE In') ?></small> <!-- poste ou texte par défaut si non renseigné -->
                <hr> <!-- séparateur visuel entre les infos et les liens -->
                <a href="profil.php" class="btn btn-sm btn-outline-secondary w-100">                 <!-- bouton pleine largeur vers son propre profil -->
                    <i class="bi bi-person-circle me-1"></i> Voir mon profil
                </a>
                <a href="reseau.php" class="btn btn-sm btn-outline-secondary w-100 mt-2">            <!-- bouton vers la page réseau, mt-2 = marge haute -->
                    <i class="bi bi-people me-1"></i> Mon réseau
                </a>
            </div>
        </div>

        <!-- Colonne centrale : Fil d'actualité -->
        <div class="col-lg-6 col-12">               <!-- pleine largeur sur mobile -->

            <?php if ($eventSemaine): ?>            <!-- bloc événement affiché seulement si un post admin existe -->
            <div class="card mb-3 border-warning">  <!-- card avec bordure jaune pour la distinguer des posts normaux -->
                <div class="card-header bg-warning text-dark fw-semibold"> <!-- en-tête fond jaune texte noir -->
                    <i class="bi bi-star-fill me-1"></i> Événement de la semaine
                </div>
                <div class="card-body">
                    <p class="mb-1"><?= nl2br(e($eventSemaine['contenu'])) ?></p> <!-- nl2br = sauts de ligne en <br>, e() = protection XSS -->
                    <small class="text-muted">
                        Par <?= e($eventSemaine['prenom']) ?> <?= e($eventSemaine['nom']) ?> <!-- nom de l'admin qui a posté -->
                        — <?= date('d/m/Y à H:i', strtotime($eventSemaine['created_at'])) ?> <!-- date formatée lisible -->
                    </small>
                    <?php if ($eventSemaine['media'] && $eventSemaine['media_type'] === 'image'): ?> <!-- affiche l'image liée à l'événement si elle existe -->
                        <img src="uploads/<?= e($eventSemaine['media']) ?>" class="img-fluid rounded mt-2"> <!-- image responsive avec coins arrondis -->
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Formulaire de publication -->
            <div class="card mb-3"> <!-- card du formulaire de nouveau post -->
                <div class="card-body">
                    <form method="POST" action="index.php" enctype="multipart/form-data">   <!-- enctype="multipart/form-data" obligatoire pour l'upload de fichiers -->
                        <input type="hidden" name="action" value="post">                    <!-- champ caché qui indique à PHP que c'est une publication de post -->
                        <div class="d-flex gap-2 align-items-start">                        <!-- avatar et textarea côte à côte avec gap -->
                            <img src="uploads/<?= e($currentUser['photo']) ?>"              <!-- photo de l utilisateur connecté -->
                                 class="rounded-circle border border-warning"
                                 width="42" height="42" style="object-fit:cover;">
                            <textarea name="contenu" class="form-control"
                                      rows="2" placeholder="Partagez quelque chose avec votre réseau..."></textarea> <!-- zone de saisie du texte du post -->
                        </div>

                        <!-- Prévisualisation du média -->
                        <div id="media-preview" class="mt-2"></div> <!-- zone vide, remplie par main.js avec l'aperçu de l'image ou vidéo sélectionnée -->

                        <div class="d-flex flex-wrap gap-2 mt-3 align-items-center"> <!-- flex-wrap pour retour à la ligne sur mobile si nécessaire -->
                            <!-- Upload fichier -->
                            <label class="btn btn-sm btn-outline-secondary mb-0"> <!-- label qui sert de bouton pour déclencher le clic sur l'input file caché -->
                                <i class="bi bi-image me-1"></i> Photo/Vidéo
                                <input type="file" name="media" id="post-media-input"
                                       accept="image/*,video/*,.pdf" class="d-none"> <!-- input file masqué, accepte images, vidéos et PDF -->
                            </label>

                            <!-- Lieu -->
                            <input type="text" name="lieu" class="form-control form-control-sm"
                                   style="width:140px;" placeholder="📍 Lieu"> <!-- champ lieu inline avec les autres options -->

                            <!-- Visibilité -->
                            <select name="visibilite" class="form-select form-select-sm" style="width:130px;"> <!-- liste de visibilité -->
                                <option value="public">🌍 Public</option> <!-- visible par tous les membres -->
                                <option value="amis">👥 Amis</option> <!-- visible uniquement par ses connexions -->
                                <option value="prive">🔒 Privé</option> <!-- visible uniquement par soi-même -->
                            </select>

                            <button type="submit" class="btn btn-sm btn-ece ms-auto"> <!-- ms-auto pousse le bouton tout à droite -->
                                <i class="bi bi-send-fill me-1"></i> Publier
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Fil de Posts -->
            <?php if (empty($posts)): ?> <!-- affiche ce message si l'utilisateur n'a pas encore de posts à voir -->
                <div class="card">
                    <div class="card-body text-center text-muted py-5"> <!-- py-5 pour centrer visuellement le message -->
                        <i class="bi bi-newspaper fs-1 d-block mb-2"></i>
                        Votre fil est vide. Ajoutez des connexions pour voir leurs publications !
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($posts as $post): ?> <!-- boucle sur chaque post du fil -->
            <div class="card post-card mb-3">   <!-- post-card a un effet hover d'ombre dans style.css -->
                <div class="card-body">

                    <!-- En-tête du post : photo + nom + date -->
                    <div class="d-flex justify-content-between align-items-start">   <!-- ligne du haut : infos auteur à gauche + bouton supprimer à droite -->
                        <div class="d-flex gap-2">                                   <!-- photo et bloc texte auteur côte à côte -->
                            <a href="profil.php?id=<?= $post['user_id'] ?>">         <!-- lien vers le profil de l'auteur du post -->
                                <img src="uploads/<?= e($post['photo']) ?>"
                                     class="rounded-circle border"
                                     width="44" height="44" style="object-fit:cover;">
                            </a>
                            <div>
                                <a href="profil.php?id=<?= $post['user_id'] ?>" class="fw-semibold text-dark text-decoration-none">
                                    <?= e($post['prenom']) ?> <?= e($post['nom']) ?> <!-- nom de l'auteur protégé XSS -->
                                </a>
                                <div class="text-muted small">
                                    <?= e($post['poste'] ?? '') ?>  <!-- poste de l'auteur si renseigné -->
                                    <?php if ($post['lieu']): ?>    <!-- affiche le lieu seulement s'il est renseigné -->
                                        · <i class="bi bi-geo-alt-fill"></i> <?= e($post['lieu']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted" style="font-size:0.72rem;">
                                    <?= date('d/m/Y à H:i', strtotime($post['created_at'])) ?> <!-- date et heure du post au format français -->
                                    <?php
                                    $visIcons = ['public'=>'🌍','amis'=>'👥','prive'=>'🔒'];   // tableau icône par visibilité
                                    echo ' · ' . ($visIcons[$post['visibilite']] ?? '');        // affiche l'icône de visibilité du post
                                    ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($post['user_id'] == $userId): ?>          <!-- bouton supprimer visible uniquement à l'auteur -->
                        <a href="index.php?delete_post=<?= $post['id'] ?>"
                           class="btn btn-sm btn-outline-danger py-0"
                           onclick="return confirm('Supprimer ce post ?')"> <!-- boîte de confirmation JS avant suppression -->
                            <i class="bi bi-trash"></i>
                        </a>
                        <?php endif; ?>
                    </div>

                    <!-- Contenu texte -->
                    <?php if ($post['contenu']): ?> <!-- on n'affiche le bloc texte que si le post a du contenu -->
                        <p class="mt-3 mb-2"><?= nl2br(e($post['contenu'])) ?></p> <!-- nl2br = sauts de ligne en HTML, e() = anti-XSS -->
                    <?php endif; ?>

                    <!-- Média -->
                    <?php if ($post['media']): ?>                           <!-- affiché uniquement si un fichier est associé -->
                    <div class="post-media">                                <!-- classe définie dans style.css pour le max-height et l'object-fit -->
                        <?php if ($post['media_type'] === 'image'): ?>      <!-- rendu pour les images -->
                            <img src="uploads/<?= e($post['media']) ?>" class="img-fluid rounded">
                        <?php elseif ($post['media_type'] === 'video'): ?>  <!-- rendu pour les vidéos avec lecteur natif -->
                            <video src="uploads/<?= e($post['media']) ?>" controls class="w-100 rounded" style="max-height:350px;"></video>
                        <?php elseif ($post['media_type'] === 'pdf'): ?>    <!-- rendu pour les PDFs : bouton vers le fichier -->
                            <a href="uploads/<?= e($post['media']) ?>" target="_blank" class="btn btn-sm btn-outline-danger mt-2">
                                <i class="bi bi-file-pdf-fill me-1"></i> Voir le PDF
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <hr class="divider"> <!-- ligne fine de séparation entre le contenu et les boutons -->

                    <!-- Compteurs de réactions et commentaires -->
                    <div class="d-flex gap-3 text-muted small mb-2"> <!-- ligne compteurs -->
                        <span>👍 <span id="reactions-<?= $post['id'] ?>"><?= $post['nb_reactions'] ?></span> réaction<?= $post['nb_reactions'] > 1 ? 's' : '' ?></span> <!-- pluriel conditionnel -->
                        <span class="toggle-comments" data-post-id="<?= $post['id'] ?>" style="cursor:pointer;"> <!-- clique pour ouvrir/fermer les commentaires -->
                            💬 <?= $post['nb_comments'] ?> commentaire<?= $post['nb_comments'] > 1 ? 's' : '' ?>
                        </span>
                    </div>

                    <!-- Boutons de réaction -->
                    <div class="d-flex gap-1 flex-wrap">                            <!-- boutons réaction en ligne, flex-wrap si écran étroit -->
                        <button class="reaction-btn <?= $post['my_reaction'] === 'like' ? 'active' : '' ?>" <!-- classe active si nous avons déjà mis like sur ce post -->
                                data-post-id="<?= $post['id'] ?>" data-type="like">  <!-- data lus par main.js pour l'AJAX -->
                            👍 J'aime <span class="reaction-count"></span>          <!-- span vide mis à jour par main.js après la réaction -->
                        </button>
                        <button class="reaction-btn <?= $post['my_reaction'] === 'love' ? 'active' : '' ?>"
                                data-post-id="<?= $post['id'] ?>" data-type="love">
                            ❤️ J'adore <span class="reaction-count"></span>
                        </button>
                        <button class="reaction-btn <?= $post['my_reaction'] === 'bravo' ? 'active' : '' ?>"
                                data-post-id="<?= $post['id'] ?>" data-type="bravo">
                            👏 Bravo <span class="reaction-count"></span>
                        </button>
                        <button class="reaction-btn toggle-comments" data-post-id="<?= $post['id'] ?>"> <!-- ce bouton toggle aussi les commentaires via toggle-comments -->
                            💬 Commenter
                        </button>
                    </div>

                    <!-- Section commentaires -->
                    <div id="comments-<?= $post['id'] ?>" class="post-comments d-none mt-3"> <!-- div masquée par d-none, togglée par main.js au clic -->

                        <!-- Chargement des commentaires existants -->
                        <?php
                        $stmtC = $conn->prepare("
                            SELECT c.*, u.nom, u.prenom, u.photo
                            FROM comments c
                            JOIN users u ON c.user_id = u.id
                            WHERE c.post_id = ?
                            ORDER BY c.created_at ASC
                        ");                 // récupère les commentaires de ce post avec le nom et la photo de leur auteur, triés du plus ancien au plus récent
                        $stmtC->bind_param("i", $post['id']); // lie l'ID du post courant
                        $stmtC->execute();  // exécute la requête
                        $comments = $stmtC->get_result()->fetch_all(MYSQLI_ASSOC); // tableau des commentaires existants
                        ?>

                        <div class="comments-list">                 <!-- conteneur dans lequel main.js injecte les nouveaux commentaires en AJAX -->
                        <?php foreach ($comments as $comment): ?>   <!-- boucle sur les commentaires existants chargés au rendu de la page -->
                            <div class="d-flex gap-2 mb-2">
                                <img src="uploads/<?= e($comment['photo']) ?>" <!-- photo de l'auteur du commentaire -->
                                     class="rounded-circle" width="32" height="32" style="object-fit:cover;">
                                <div class="bg-light rounded p-2 flex-grow-1"> <!-- bulle grise du commentaire -->
                                    <strong class="small"><?= e($comment['prenom']) ?> <?= e($comment['nom']) ?></strong> <!-- nom de l'auteur en gras -->
                                    <p class="mb-0 small"><?= nl2br(e($comment['contenu'])) ?></p> <!-- texte du commentaire -->
                                    <span class="text-muted" style="font-size:0.68rem;">
                                        <?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?> <!-- heure du commentaire -->
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?> <!-- fin boucle commentaires -->
                        </div>

                        <!-- Formulaire d'ajout de commentaire (AJAX) -->
                        <form class="comment-form d-flex gap-2 mt-2" data-post-id="<?= $post['id'] ?>"> <!-- data-post-id transmis à l'API via main.js, pas de action= car soumis en AJAX -->
                            <img src="uploads/<?= e($currentUser['photo']) ?>" <!-- photo de l'utilisateur connecté à gauche du champ -->
                                 class="rounded-circle" width="32" height="32" style="object-fit:cover;">
                            <input type="text" name="comment" class="form-control form-control-sm"
                                   placeholder="Écrire un commentaire...">      <!-- champ de saisie du commentaire -->
                            <button type="submit" class="btn btn-sm btn-ece">   <!-- soumission interceptée par main.js -->
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </form>
                    </div>

                </div>
            </div>
            <?php endforeach; ?> <!-- fin boucle posts -->

        </div> <!-- fin colonne centrale -->

        <!-- Colonne de droite : Suggestions de connexion -->
        <div class="col-lg-3 d-none d-lg-block">    <!-- sidebar droite masquée sur mobile -->
            <div class="card sidebar-card">         <!-- sticky défini dans style.css -->
                <div class="card-header bg-white fw-semibold border-bottom">
                    <i class="bi bi-person-plus-fill me-1 text-ece"></i> Suggestions
                </div>
                <div class="card-body p-2">
                    <?php if (empty($suggestions)): ?> <!-- message si tous les membres sont déjà connectés -->
                        <p class="text-muted small text-center py-3">Vous êtes connecté à tout le monde !</p>
                    <?php endif; ?>
                    <?php foreach ($suggestions as $s): ?> <!-- boucle sur les 4 suggestions de connexion -->
                    <div class="d-flex align-items-center gap-2 p-2 rounded hover-bg">
                        <a href="profil.php?id=<?= $s['id'] ?>"> <!-- lien vers le profil de la personne suggérée -->
                            <img src="uploads/<?= e($s['photo']) ?>"
                                 class="rounded-circle border" width="44" height="44" style="object-fit:cover;">
                        </a>
                        <div class="flex-grow-1 overflow-hidden"> <!-- overflow-hidden requis pour que text-truncate fonctionne -->
                            <a href="profil.php?id=<?= $s['id'] ?>" class="text-dark text-decoration-none fw-semibold small d-block text-truncate">
                                <?= e($s['prenom']) ?> <?= e($s['nom']) ?> <!-- nom tronqué si trop long -->
                            </a>
                            <small class="text-muted d-block text-truncate"><?= e($s['poste'] ?? 'Membre ECE') ?></small> <!-- poste ou valeur par défaut -->
                        </div>
                        <button class="btn btn-sm btn-ece add-friend-btn flex-shrink-0"
                                data-user-id="<?= $s['id'] ?>"> <!-- data-user-id lu par main.js pour envoyer la demande AJAX -->
                            <i class="bi bi-person-plus-fill"></i>
                        </button>
                    </div>
                    <?php endforeach; ?> <!-- fin boucle suggestions -->
                </div>
            </div>
        </div>
    </div> <!-- fin row -->
</div> <!-- fin container -->

<?php include 'includes/footer.php'; ?> <!-- inclut le footer HTML, Bootstrap JS, jQuery et main.js -->
