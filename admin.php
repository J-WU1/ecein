<?php
// Ce fichier est le panneau d'administration, accessible uniquement aux admins
// On inclut la connexion à la base de données
require_once 'includes/db.php';
// On inclut les fonctions de sécurité et de session
require_once 'includes/auth.php';
// On vérifie que l'utilisateur est admin, sinon il est redirigé automatiquement
requireAdmin();

// On définit le titre de la page affiché dans l'onglet du navigateur
$pageTitle = "Administration";
// On récupère l'ID de l'admin connecté
$userId    = (int) $_SESSION['user_id'];
// On initialise les variables de message de succès et d'erreur à vide
$success   = '';
$error     = '';

// Si le formulaire a été soumis en POST, on traite l'action demandée
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // On récupère l'action demandée depuis les données POST
    $action = $_POST['action'] ?? '';

    // Si l'action est "add_user", on crée un nouvel utilisateur
    if ($action === 'add_user') {
        // On récupère et nettoie chaque champ du formulaire avec trim()
        $prenom   = trim($_POST['prenom']   ?? '');
        $nom      = trim($_POST['nom']      ?? '');
        $pseudo   = trim($_POST['pseudo']   ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');
        // On récupère le rôle, "auteur" par défaut si non renseigné
        $role     = $_POST['role']           ?? 'auteur';

        // On vérifie que tous les champs obligatoires sont remplis
        if (!$prenom || !$nom || !$pseudo || !$email || !$password) {
            $error = "Tous les champs sont obligatoires.";
        // On vérifie que le format de l'email est valide
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email invalide.";
        } else {
            // On vérifie qu'il n'existe pas déjà un compte avec cet email ou ce pseudo
            $stmt = $conn->prepare("SELECT id FROM users WHERE email=? OR pseudo=?");
            $stmt->bind_param("ss", $email, $pseudo);
            $stmt->execute();
            // Si une ligne existe, l'email ou le pseudo est déjà pris
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Cet email ou ce pseudo est déjà utilisé.";
            } else {
                // On hache le mot de passe avec password_hash pour ne jamais le stocker en clair
                $hash = password_hash($password, PASSWORD_DEFAULT);
                // On insère le nouvel utilisateur dans la base de données
                $stmt = $conn->prepare(
                    "INSERT INTO users (prenom, nom, pseudo, email, password, role) VALUES (?,?,?,?,?,?)"
                );
                $stmt->bind_param("ssssss", $prenom, $nom, $pseudo, $email, $hash, $role);
                $stmt->execute();
                // On affiche un message de succès avec le nom de l'utilisateur créé
                $success = "Utilisateur {$prenom} {$nom} créé avec succès.";
            }
        }
    }

    // Si l'action est "change_role", on modifie le rôle d'un utilisateur
    if ($action === 'change_role') {
        // On récupère l'ID de l'utilisateur cible
        $targetId = (int) ($_POST['target_id'] ?? 0);
        // On récupère le nouveau rôle demandé
        $newRole  = $_POST['new_role'] ?? 'auteur';
        // On vérifie que la cible existe, que ce n'est pas soi-même, et que le rôle est valide
        if ($targetId && $targetId !== $userId && in_array($newRole, ['auteur','admin'])) {
            $stmt = $conn->prepare("UPDATE users SET role=? WHERE id=?");
            $stmt->bind_param("si", $newRole, $targetId);
            $stmt->execute();
            $success = "Rôle mis à jour.";
        }
    }
}

// Si le paramètre delete_user est présent dans l'URL, on supprime un utilisateur
if (isset($_GET['delete_user'])) {
    // On récupère l'ID de l'utilisateur à supprimer
    $targetId = (int) $_GET['delete_user'];
    // On vérifie que la cible existe et que ce n'est pas son propre compte
    if ($targetId && $targetId !== $userId) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $targetId);
        $stmt->execute();
        $success = "Utilisateur supprimé.";
    } else {
        // On empêche l'admin de supprimer son propre compte
        $error = "Impossible de supprimer votre propre compte.";
    }
}

// On récupère le mot clé de recherche saisi dans la barre de recherche
$search = trim($_GET['search'] ?? '');
// Si une recherche est en cours, on filtre les utilisateurs par nom, prénom, email ou pseudo
if ($search) {
    // On prépare le mot clé avec des % pour la recherche partielle avec LIKE
    $like    = "%$search%";
    $stmtU   = $conn->prepare("
        SELECT u.*,
               (SELECT COUNT(*) FROM friendships f
                WHERE (f.user_id=u.id OR f.friend_id=u.id) AND f.status='accepted') AS nb_friends,
               (SELECT COUNT(*) FROM posts p WHERE p.user_id=u.id) AS nb_posts
        FROM users u
        WHERE u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR u.pseudo LIKE ?
        ORDER BY u.created_at DESC
    ");
    // On passe le mot clé 4 fois pour les 4 colonnes recherchées
    $stmtU->bind_param("ssss", $like, $like, $like, $like);
} else {
    // Sans recherche, on récupère tous les utilisateurs avec leurs stats
    $stmtU = $conn->prepare("
        SELECT u.*,
               (SELECT COUNT(*) FROM friendships f
                WHERE (f.user_id=u.id OR f.friend_id=u.id) AND f.status='accepted') AS nb_friends,
               (SELECT COUNT(*) FROM posts p WHERE p.user_id=u.id) AS nb_posts
        FROM users u
        ORDER BY u.created_at DESC
    ");
}
// On exécute la requête et on récupère tous les utilisateurs dans un tableau
$stmtU->execute();
$users = $stmtU->get_result()->fetch_all(MYSQLI_ASSOC);

// On calcule les statistiques globales pour chaque table importante du site
$stats = [];
// On boucle sur chaque table pour compter le nombre de lignes
foreach (['users','posts','friendships','messages','jobs','notifications'] as $table) {
    $r = $conn->query("SELECT COUNT(*) AS nb FROM $table");
    // On stocke le nombre dans le tableau $stats avec le nom de la table comme clé
    $stats[$table] = $r->fetch_assoc()['nb'];
}

// On inclut le header commun à toutes les pages
include 'includes/header.php';
?>

<div class="container mt-4">

    <!-- En-tête avec icône bouclier et nom de l'admin connecté -->
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center"
             style="width:44px;height:44px;">
            <i class="bi bi-shield-lock-fill fs-5 text-dark"></i>
        </div>
        <div>
            <h4 class="mb-0 fw-bold">Panneau d'administration</h4>
            <!-- On affiche le prénom et nom de l'admin avec e() pour éviter les failles XSS -->
            <small class="text-muted">Connecté en tant que <?= e($currentUser['prenom']) ?> <?= e($currentUser['nom']) ?></small>
        </div>
    </div>

    <!-- Alerte verte si une action a réussi -->
    <?php if ($success): ?>
        <div class="alert alert-success py-2"><?= e($success) ?></div>
    <?php endif; ?>
    <!-- Alerte rouge si une erreur s'est produite -->
    <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Grille de 6 cartes de statistiques globales -->
    <div class="row g-3 mb-4">
        <?php
        // On définit les 6 cartes avec leur label, clé de stats, icône et couleur Bootstrap
        $statCards = [
            ['label'=>'Membres',        'key'=>'users',         'icon'=>'bi-people-fill',       'color'=>'primary'],
            ['label'=>'Publications',   'key'=>'posts',         'icon'=>'bi-file-post-fill',     'color'=>'success'],
            ['label'=>'Connexions',     'key'=>'friendships',   'icon'=>'bi-diagram-3-fill',     'color'=>'info'],
            ['label'=>'Messages',       'key'=>'messages',      'icon'=>'bi-chat-dots-fill',     'color'=>'warning'],
            ['label'=>'Offres emploi',  'key'=>'jobs',          'icon'=>'bi-briefcase-fill',     'color'=>'danger'],
            ['label'=>'Notifications',  'key'=>'notifications', 'icon'=>'bi-bell-fill',          'color'=>'secondary'],
        ];
        // On boucle sur chaque carte pour l'afficher
        foreach ($statCards as $card):
        ?>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card text-center h-100">
                <div class="card-body py-3">
                    <!-- Icône colorée selon la couleur Bootstrap définie dans $statCards -->
                    <i class="bi <?= $card['icon'] ?> fs-3 text-<?= $card['color'] ?>"></i>
                    <!-- Nombre récupéré depuis $stats avec la clé de la table -->
                    <div class="fw-bold fs-4 mt-1"><?= $stats[$card['key']] ?></div>
                    <!-- Label affiché sous le nombre -->
                    <small class="text-muted"><?= $card['label'] ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tableau de gestion des membres avec barre de recherche et bouton d'ajout -->
    <div class="card mb-4">
        <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span class="fw-semibold">
                <i class="bi bi-people-fill text-ece me-1"></i>
                <!-- On affiche le nombre total de membres trouvés -->
                Gestion des membres (<?= count($users) ?>)
            </span>
            <div class="d-flex gap-2">
                <!-- Formulaire de recherche par nom, prénom, email ou pseudo -->
                <form method="GET" action="admin.php" class="d-flex gap-1">
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Rechercher un membre…"
                           <!-- On réaffiche le mot clé pour ne pas vider le champ après soumission -->
                           value="<?= e($search) ?>" style="width:200px;">
                    <button class="btn btn-sm btn-outline-secondary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                    <!-- Bouton croix pour réinitialiser la recherche, affiché seulement si une recherche est active -->
                    <?php if ($search): ?>
                    <a href="admin.php" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-x-lg"></i>
                    </a>
                    <?php endif; ?>
                </form>
                <!-- Bouton qui ouvre la modale d'ajout d'un utilisateur -->
                <button class="btn btn-sm btn-ece" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus-fill me-1"></i> Ajouter
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <!-- Tableau listant tous les membres avec leurs informations -->
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr class="admin-table">
                            <th class="ps-3">Membre</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th class="text-center">Posts</th>
                            <th class="text-center">Connexions</th>
                            <th>Inscription</th>
                            <th>Dernière connexion</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <!-- On boucle sur chaque utilisateur récupéré depuis la base -->
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <!-- Colonne avatar avec photo de profil cliquable vers le profil -->
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <a href="profil.php?id=<?= $u['id'] ?>">
                                    <img src="uploads/<?= e($u['photo']) ?>"
                                         class="rounded-circle border"
                                         width="36" height="36" style="object-fit:cover;">
                                </a>
                                <div>
                                    <!-- Lien vers le profil avec prénom et nom échappés -->
                                    <a href="profil.php?id=<?= $u['id'] ?>"
                                       class="text-dark text-decoration-none fw-semibold small">
                                        <?= e($u['prenom']) ?> <?= e($u['nom']) ?>
                                    </a>
                                    <!-- Pseudo affiché en dessous du nom -->
                                    <small class="text-muted d-block">@<?= e($u['pseudo']) ?></small>
                                </div>
                            </div>
                        </td>

                        <!-- Colonne email de l'utilisateur -->
                        <td><small><?= e($u['email']) ?></small></td>

                        <!-- Colonne rôle avec menu déroulant pour le modifier en temps réel -->
                        <td>
                            <!-- On ne peut pas modifier le rôle de son propre compte -->
                            <?php if ($u['id'] !== $userId): ?>
                            <form method="POST" class="d-inline">
                                <!-- Champ caché pour indiquer l'action change_role -->
                                <input type="hidden" name="action"    value="change_role">
                                <!-- Champ caché avec l'ID de l'utilisateur cible -->
                                <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                <!-- Le formulaire se soumet automatiquement au changement de valeur -->
                                <select name="new_role" class="form-select form-select-sm"
                                        style="width:100px;"
                                        onchange="this.form.submit()">
                                    <!-- Option pré-sélectionnée selon le rôle actuel de l'utilisateur -->
                                    <option value="auteur" <?= $u['role']==='auteur' ? 'selected':'' ?>>Auteur</option>
                                    <option value="admin"  <?= $u['role']==='admin'  ? 'selected':'' ?>>Admin</option>
                                </select>
                            </form>
                            <?php else: ?>
                                <!-- Pour son propre compte, on affiche juste un badge sans pouvoir le modifier -->
                                <span class="badge bg-warning text-dark">Admin (vous)</span>
                            <?php endif; ?>
                        </td>

                        <!-- Colonne nombre de posts publiés par l'utilisateur -->
                        <td class="text-center"><span class="badge bg-secondary"><?= $u['nb_posts'] ?></span></td>
                        <!-- Colonne nombre de connexions acceptées de l'utilisateur -->
                        <td class="text-center"><span class="badge bg-secondary"><?= $u['nb_friends'] ?></span></td>

                        <!-- Colonne date d'inscription formatée en jour/mois/année -->
                        <td><small class="text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></small></td>
                        <!-- Colonne dernière connexion, affiche "Jamais" si null -->
                        <td>
                            <small class="text-muted">
                                <?= $u['last_login']
                                    ? date('d/m/Y H:i', strtotime($u['last_login']))
                                    : 'Jamais' ?>
                            </small>
                        </td>

                        <!-- Colonne actions avec bouton de suppression -->
                        <td class="text-center">
                            <!-- On ne peut pas supprimer son propre compte -->
                            <?php if ($u['id'] !== $userId): ?>
                            <!-- Lien de suppression avec confirmation JavaScript avant d'agir -->
                            <a href="admin.php?delete_user=<?= $u['id'] ?>"
                               class="btn btn-sm btn-outline-danger py-0"
                               onclick="return confirm('Supprimer <?= e($u['prenom']) ?> <?= e($u['nom']) ?> ? Cette action est irréversible.')">
                                <i class="bi bi-trash"></i>
                            </a>
                            <?php else: ?>
                            <!-- Pour son propre compte, on affiche juste un tiret -->
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- fin container -->

<!-- Modale Bootstrap pour ajouter un nouvel utilisateur depuis l'interface admin -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <!-- Formulaire soumis en POST vers admin.php -->
      <form method="POST" action="admin.php">
        <!-- Champ caché qui indique au serveur que l'action est add_user -->
        <input type="hidden" name="action" value="add_user">
        <div class="modal-header">
            <h5 class="modal-title">
                <i class="bi bi-person-plus-fill me-1"></i> Ajouter un membre
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <!-- Champs prénom et nom sur la même ligne -->
            <div class="row g-2 mb-3">
                <div class="col">
                    <label class="form-label fw-semibold">Prénom *</label>
                    <input type="text" name="prenom" class="form-control" required placeholder="Alice">
                </div>
                <div class="col">
                    <label class="form-label fw-semibold">Nom *</label>
                    <input type="text" name="nom" class="form-control" required placeholder="Dupont">
                </div>
            </div>
            <!-- Champ pseudo avec le symbole @ devant -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Pseudo *</label>
                <div class="input-group">
                    <span class="input-group-text">@</span>
                    <input type="text" name="pseudo" class="form-control" required placeholder="alice_dupont">
                </div>
            </div>
            <!-- Champ email avec validation de format HTML5 -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Email *</label>
                <input type="email" name="email" class="form-control" required placeholder="alice@ece.fr">
            </div>
            <!-- Champ mot de passe provisoire avec longueur minimale de 6 caractères -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Mot de passe provisoire *</label>
                <input type="text" name="password" class="form-control" required
                       placeholder="Min. 6 caractères" minlength="6">
            </div>
            <!-- Menu déroulant pour choisir le rôle de l'utilisateur créé -->
            <div class="mb-3">
                <label class="form-label fw-semibold">Rôle</label>
                <select name="role" class="form-select">
                    <!-- Auteur est sélectionné par défaut car c'est le rôle standard -->
                    <option value="auteur" selected>Auteur (étudiant / enseignant)</option>
                    <option value="admin">Administrateur</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <!-- Bouton pour fermer la modale sans créer de compte -->
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <!-- Bouton de soumission du formulaire pour créer le compte -->
            <button type="submit" class="btn btn-ece">
                <i class="bi bi-person-plus-fill me-1"></i> Créer le compte
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
