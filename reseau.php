<?php
// reseau.php — Mon Réseau : connexions, demandes reçues, suggestions

require_once 'includes/db.php';             // charge la connexion MySQL dans $conn
require_once 'includes/auth.php';           // démarre la session et charge les fonctions d'auth
requireLogin();                             // redirige vers login.php si non connecté

$pageTitle = "Mon Réseau";                  // titre de l'onglet du navigateur
$userId    = (int) $_SESSION['user_id'];    // ID de l'utilisateur connecté, casté en int pour sécuriser les requêtes

// Traitement de l'acceptation d'une demande d'ami
if (isset($_GET['accept'])) {               // déclenché par le lien ?accept=ID dans les demandes reçues
    $fid  = (int) $_GET['accept'];          // ID de la personne dont on accepte la demande, casté en int
    $stmt = $conn->prepare("UPDATE friendships SET status='accepted' WHERE user_id=? AND friend_id=?"); // passe le statut de 'pending' à 'accepted'
    $stmt->bind_param("ii", $fid, $userId); // user_id = celui qui a envoyé, friend_id = moi qui accepte
    $stmt->execute();                       // exécute la mise à jour
    header("Location: reseau.php"); exit(); // redirige pour nettoyer l'URL et éviter la re-soumission
}

// Traitement du refus d'une demande d'ami
if (isset($_GET['reject'])) {               // déclenché par le lien ?reject=ID
    $fid  = (int) $_GET['reject'];          // ID de la personne dont on refuse la demande
    $stmt = $conn->prepare("DELETE FROM friendships WHERE user_id=? AND friend_id=?"); // supprime la ligne dans friendships
    $stmt->bind_param("ii", $fid, $userId); // user_id = envoyeur, friend_id = moi
    $stmt->execute();
    header("Location: reseau.php"); exit(); // redirige pour nettoyer l'URL
}

// Traitement du retrait d'une connexion existante
if (isset($_GET['remove'])) {               // déclenché par le lien ?remove=ID
    $fid  = (int) $_GET['remove'];          // ID de la connexion à retirer
    $stmt = $conn->prepare("
        DELETE FROM friendships
        WHERE (user_id=? AND friend_id=?) OR (user_id=? AND friend_id=?)
    ");                                     // supprime dans les deux sens car la demande peut être dans n'importe quelle direction
    $stmt->bind_param("iiii", $userId, $fid, $fid, $userId); // couvre les deux cas (qui a envoyé la demande en premier)
    $stmt->execute();
    header("Location: reseau.php"); exit(); // redirige après suppression
}

// Récupère le terme de recherche dans l'URL (?q=...)
$searchQuery = trim($_GET['q'] ?? '');      // vide si aucune recherche active, trim supprime les espaces inutiles

// Récupère la liste des amis acceptés, filtrée si une recherche est active
if ($searchQuery) {             // si l'utilisateur a tapé quelque chose dans la barre de recherche
    $like = "%$searchQuery%";   // format LIKE avec % des deux côtés pour la recherche partielle
    $stmtFriends = $conn->prepare("
        SELECT u.id, u.nom, u.prenom, u.photo, u.poste, u.entreprise, u.bio
        FROM users u
        JOIN friendships f ON (
            (f.user_id = ? AND f.friend_id = u.id) OR
            (f.friend_id = ? AND f.user_id = u.id)
        )
        WHERE f.status = 'accepted' AND u.id != ?
          AND (u.prenom LIKE ? OR u.nom LIKE ? OR u.poste LIKE ? OR u.entreprise LIKE ?)
        ORDER BY u.prenom, u.nom
    ");     // joint dans les deux sens pour couvrir qui a envoyé la demande, filtre sur prénom/nom/poste/entreprise
    $stmtFriends->bind_param("iiissss", $userId, $userId, $userId, $like, $like, $like, $like); // 3 int + 4 strings
} else {    // pas de recherche : on récupère tous les amis sans filtre
    $stmtFriends = $conn->prepare("
        SELECT u.id, u.nom, u.prenom, u.photo, u.poste, u.entreprise, u.bio
        FROM users u
        JOIN friendships f ON (
            (f.user_id = ? AND f.friend_id = u.id) OR
            (f.friend_id = ? AND f.user_id = u.id)
        )
        WHERE f.status = 'accepted' AND u.id != ?
        ORDER BY u.prenom, u.nom
    ");     // même requête mais sans clause LIKE, triés par prénom puis nom
    $stmtFriends->bind_param("iii", $userId, $userId, $userId); // 3 fois $userId
}
$stmtFriends->execute();                    // exécute la requête amis
$friends   = $stmtFriends->get_result()->fetch_all(MYSQLI_ASSOC); // tableau de tous les amis récupérés
$friendIds = array_column($friends, 'id');  // extrait uniquement les IDs pour les utiliser dans les requêtes suivantes

// Récupère les demandes d'ami reçues
$stmtPending = $conn->prepare("
    SELECT u.id, u.nom, u.prenom, u.photo, u.poste
    FROM users u
    JOIN friendships f ON f.user_id = u.id
    WHERE f.friend_id = ? AND f.status = 'pending'
");                                         // f.friend_id = moi signifie que l'autre personne m'a envoyé la demande
$stmtPending->bind_param("i", $userId);     // mon ID comme destinataire
$stmtPending->execute();
$pendingRequests = $stmtPending->get_result()->fetch_all(MYSQLI_ASSOC); // tableau des demandes en attente

// Construit la liste des suggestions : d'abord les amis de mes amis
$suggestions = [];          // initialise un tableau vide de suggestions
if (!empty($friendIds)) {   // on cherche les amis d'amis seulement si j'ai au moins un ami
    $inList = implode(',', array_map('intval', $friendIds)); // liste d'IDs séparés par des virgules, array_map intval pour sécuriser
    $stmtSugg = $conn->query("
        SELECT DISTINCT u.id, u.nom, u.prenom, u.photo, u.poste
        FROM users u
        JOIN friendships f ON (
            (f.user_id = u.id OR f.friend_id = u.id)
        )
        WHERE f.status = 'accepted'
          AND u.id != $userId
          AND u.id NOT IN ($inList, $userId)
          AND (f.user_id IN ($inList) OR f.friend_id IN ($inList))
        LIMIT 6
    "); // récupère des utilisateurs qui sont amis avec mes amis mais pas encore avec moi
    $suggestions = $stmtSugg->fetch_all(MYSQLI_ASSOC); // tableau des suggestions d'amis d'amis
}

// Complète avec des utilisateurs aléatoires si moins de 6 suggestions trouvées
if (count($suggestions) < 6) {              // on complète uniquement s'il manque des suggestions
    $excludeIds = array_merge($friendIds, [$userId], array_column($suggestions, 'id')); // fusionne les IDs à exclure : amis + moi + suggestions déjà trouvées
    $exclude    = implode(',', array_map('intval', $excludeIds)); // liste CSV des IDs à exclure
    $limit      = 6 - count($suggestions);  // nombre de suggestions manquantes à compléter
    $stmtMore   = $conn->query("
        SELECT u.id, u.nom, u.prenom, u.photo, u.poste
        FROM users u
        WHERE u.id NOT IN ($exclude)
          AND u.id NOT IN (
              SELECT CASE WHEN user_id = $userId THEN friend_id ELSE user_id END
              FROM friendships WHERE (user_id = $userId OR friend_id = $userId)
          )
        ORDER BY RAND()
        LIMIT $limit
    "); // utilisateurs aléatoires parmi ceux qu'on ne connaît pas encore
    $suggestions = array_merge($suggestions, $stmtMore->fetch_all(MYSQLI_ASSOC)); // fusionne les deux tableaux de suggestions
}

include 'includes/header.php'; // inclut la navbar et le head HTML
?>

<div class="container mt-4">    <!-- conteneur Bootstrap centré avec marge haute -->
<div class="row g-3">           <!-- grille Bootstrap avec gouttière -->

<div class="col-lg-8">          <!-- colonne principale : 8/12 sur grand écran, pleine largeur sur mobile -->

    <?php if (!empty($pendingRequests)): ?> <!-- section demandes reçues, affichée seulement s'il y en a -->
    <div class="card mb-4">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-person-check-fill text-warning me-1"></i>
            Demandes de connexion
            <span class="badge bg-danger ms-1"><?= count($pendingRequests) ?></span>    <!-- badge rouge avec le nombre de demandes en attente -->
        </div>
        <div class="card-body">
            <div class="row g-3">
            <?php foreach ($pendingRequests as $req): ?>                                <!-- boucle sur chaque demande reçue -->
                <div class="col-md-6 friendship-request" id="req-<?= $req['id'] ?>">    <!-- id pour que main.js puisse faire disparaître la carte après accept/reject -->
                    <div class="d-flex gap-3 align-items-center p-2 border rounded">
                        <a href="profil.php?id=<?= $req['id'] ?>">                      <!-- lien vers le profil de la personne -->
                            <img src="uploads/<?= e($req['photo']) ?>"
                                 class="rounded-circle border border-warning"
                                 width="52" height="52" style="object-fit:cover;">
                        </a>
                        <div class="flex-grow-1">
                            <a href="profil.php?id=<?= $req['id'] ?>" class="fw-semibold text-dark text-decoration-none">
                                <?= e($req['prenom']) ?> <?= e($req['nom']) ?>          <!-- nom de la personne qui demande la connexion -->
                            </a>
                            <small class="text-muted d-block"><?= e($req['poste'] ?? '') ?></small> <!-- poste si renseigné -->
                        </div>
                        <div class="d-flex flex-column gap-1">                          <!-- boutons accepter/refuser empilés verticalement -->
                            <button class="btn btn-sm btn-ece accept-friend-btn"
                                    data-user-id="<?= $req['id'] ?>">Accepter</button>  <!-- data-user-id lu par main.js pour l'AJAX -->
                            <button class="btn btn-sm btn-outline-secondary reject-friend-btn"
                                    data-user-id="<?= $req['id'] ?>">Refuser</button>   <!-- même principe pour refus -->
                        </div>
                    </div>
                </div>
            <?php endforeach; ?> <!-- fin boucle demandes -->
            </div>
        </div>
    </div>
    <?php endif; ?> <!-- fin section demandes -->

    <form method="GET" action="reseau.php" class="mb-3">            <!-- formulaire GET pour la recherche dans les connexions -->
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0">   <!-- icône loupe collée à gauche du champ -->
                <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" name="q" class="form-control border-start-0 ps-0"
                   placeholder="Rechercher dans vos connexions (nom, poste, entreprise…)"
                   value="<?= e($searchQuery) ?>">                  <!-- valeur repopulée pour garder la saisie après soumission -->
            <?php if ($searchQuery): ?>                             <!-- bouton X pour effacer la recherche, affiché seulement quand il y a une recherche active -->
            <a href="reseau.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg"></i>
            </a>
            <?php endif; ?>
            <button class="btn btn-ece" type="submit">Rechercher</button> <!-- bouton de soumission -->
        </div>
    </form>

    <div class="card mb-4">                                                                     <!-- card principale des connexions -->
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-people-fill text-ece me-1"></i>
            <?= $searchQuery ? 'Résultats pour "' . e($searchQuery) . '"' : 'Mes connexions' ?> <!-- titre dynamique selon qu'on est en recherche ou non -->
            <span class="badge bg-secondary ms-1"><?= count($friends) ?></span>                 <!-- badge gris avec le nombre de connexions affichées -->
        </div>
        <div class="card-body">
            <?php if (empty($friends)): ?>                                                      <!-- message si aucune connexion (ou aucun résultat de recherche) -->
                <p class="text-muted text-center py-4">
                    <i class="bi bi-people fs-1 d-block mb-2"></i>
                    <?php if ($searchQuery): ?>                                                 <!-- message différent selon qu'on cherche ou non -->
                        Aucune connexion ne correspond à "<?= e($searchQuery) ?>".<br>
                        <a href="reseau.php" class="btn btn-outline-secondary btn-sm mt-2">
                            Voir toutes mes connexions
                        </a>
                    <?php else: ?>
                        Vous n'avez pas encore de connexions.<br>
                        <small>Utilisez les suggestions ci-dessous pour commencer votre réseau !</small>
                    <?php endif; ?>
                </p>
            <?php else: ?>                                                  <!-- on a des connexions à afficher -->
            <div class="row g-3">                                           <!-- grille Bootstrap pour les cartes amis -->
            <?php foreach ($friends as $friend): ?>                         <!-- boucle sur chaque ami -->
                <div class="col-md-6 col-lg-4">                             <!-- 2 par ligne sur md, 3 par ligne sur lg -->
                    <div class="card friend-card h-100 position-relative">  <!-- h-100 pour toutes les cards à la même hauteur dans la ligne -->
                        <a href="profil.php?id=<?= $friend['id'] ?>">       <!-- lien vers le profil de l'ami -->
                            <img src="uploads/<?= e($friend['photo']) ?>"
                                 class="rounded-circle border border-warning mx-auto d-block"
                                 width="70" height="70" style="object-fit:cover;">
                        </a>
                        <div class="mt-2">
                            <a href="profil.php?id=<?= $friend['id'] ?>"
                               class="fw-semibold text-dark text-decoration-none d-block">
                                <?= e($friend['prenom']) ?> <?= e($friend['nom']) ?>                                <!-- nom de l'ami protégé XSS -->
                            </a>
                            <small class="text-muted d-block"><?= e($friend['poste'] ?? 'Membre ECE') ?></small>    <!-- poste ou valeur par défaut -->
                            <?php if ($friend['entreprise']): ?>                                                    <!-- affiche l'entreprise seulement si renseignée -->
                                <small class="text-muted d-block">
                                    <i class="bi bi-building me-1"></i><?= e($friend['entreprise']) ?>
                                </small>
                            <?php endif; ?>
                            <?php if ($friend['bio']): ?>                                                           <!-- affiche un extrait de bio seulement si renseignée -->
                                <p class="text-muted small mt-1 mb-2" style="font-size:0.78rem; line-height:1.3;">
                                    <?= e(mb_substr($friend['bio'], 0, 80)) ?>…                                     <!-- tronqué à 80 caractères pour ne pas casser le layout -->
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-1 justify-content-center mt-auto pt-2">                              <!-- boutons en bas de la card, mt-auto les pousse en bas -->
                            <a href="messagerie.php?with=<?= $friend['id'] ?>"
                               class="btn btn-sm btn-outline-secondary">                                            <!-- lien vers la messagerie avec cet ami -->
                                <i class="bi bi-chat-dots-fill me-1"></i>Message
                            </a>
                            <a href="reseau.php?remove=<?= $friend['id'] ?>"
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Retirer cette connexion ?')">                               <!-- confirmation avant suppression -->
                                <i class="bi bi-person-dash-fill"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?> <!-- fin boucle amis -->
            </div>
            <?php endif; ?> <!-- fin condition amis/vide -->
        </div>
    </div>

</div> <!-- fin colonne principale -->

<div class="col-lg-4"> <!-- colonne suggestions : 4/12 sur grand écran -->
    <div class="card">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-person-plus-fill text-ece me-1"></i> Personnes à connaître
        </div>
        <div class="card-body p-2">
            <?php if (empty($suggestions)): ?>                              <!-- message si tous les membres sont déjà connectés -->
                <p class="text-muted small text-center py-3">
                    Vous êtes connecté à tous les membres !
                </p>
            <?php endif; ?>
            <?php foreach ($suggestions as $s): ?>                          <!-- boucle sur les suggestions -->
            <div class="d-flex align-items-center gap-2 p-2 border-bottom"> <!-- séparateur entre chaque suggestion -->
                <a href="profil.php?id=<?= $s['id'] ?>">                    <!-- lien vers le profil de la personne suggérée -->
                    <img src="uploads/<?= e($s['photo']) ?>"
                         class="rounded-circle border" width="48" height="48" style="object-fit:cover;">
                </a>
                <div class="flex-grow-1 overflow-hidden">                   <!-- overflow-hidden pour text-truncate -->
                    <a href="profil.php?id=<?= $s['id'] ?>"
                       class="fw-semibold text-dark text-decoration-none small d-block text-truncate">
                        <?= e($s['prenom']) ?> <?= e($s['nom']) ?>          <!-- nom tronqué si trop long -->
                    </a>
                    <small class="text-muted d-block text-truncate">
                        <?= e($s['poste'] ?? 'Membre ECE') ?>               <!-- poste ou valeur par défaut -->
                    </small>
                </div>
                <button class="btn btn-sm btn-ece add-friend-btn flex-shrink-0"
                        data-user-id="<?= $s['id'] ?>">                     <!-- data-user-id lu par main.js pour envoyer la demande en AJAX -->
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
