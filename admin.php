<?php
//  admin.php — Panneau d'administration
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireAdmin(); // Redirige si pas admin

$pageTitle = "Administration";
$userId    = (int) $_SESSION['user_id'];
$success   = '';
$error     = '';

// TRAITEMENT : Ajouter un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $prenom   = trim($_POST['prenom']   ?? '');
        $nom      = trim($_POST['nom']      ?? '');
        $pseudo   = trim($_POST['pseudo']   ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = $_POST['role']           ?? 'auteur';

        if (!$prenom || !$nom || !$pseudo || !$email || !$password) {
            $error = "Tous les champs sont obligatoires.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email invalide.";
        } else {
            // Vérifie unicité email/pseudo
            $stmt = $conn->prepare("SELECT id FROM users WHERE email=? OR pseudo=?");
            $stmt->bind_param("ss", $email, $pseudo);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Cet email ou ce pseudo est déjà utilisé.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    "INSERT INTO users (prenom, nom, pseudo, email, password, role) VALUES (?,?,?,?,?,?)"
                );
                $stmt->bind_param("ssssss", $prenom, $nom, $pseudo, $email, $hash, $role);
                $stmt->execute();
                $success = "Utilisateur {$prenom} {$nom} créé avec succès.";
            }
        }
    }

    // Changer le rôle d'un utilisateur
    if ($action === 'change_role') {
        $targetId = (int) ($_POST['target_id'] ?? 0);
        $newRole  = $_POST['new_role'] ?? 'auteur';
        if ($targetId && $targetId !== $userId && in_array($newRole, ['auteur','admin'])) {
            $stmt = $conn->prepare("UPDATE users SET role=? WHERE id=?");
            $stmt->bind_param("si", $newRole, $targetId);
            $stmt->execute();
            $success = "Rôle mis à jour.";
        }
    }
}

// TRAITEMENT : Supprimer un utilisateur (via GET pour simplifier)
if (isset($_GET['delete_user'])) {
    $targetId = (int) $_GET['delete_user'];
    if ($targetId && $targetId !== $userId) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $targetId);
        $stmt->execute();
        $success = "Utilisateur supprimé.";
    } else {
        $error = "Impossible de supprimer votre propre compte.";
    }
}

// RÉCUPÉRATION : Tous les utilisateurs
$search = trim($_GET['search'] ?? '');
if ($search) {
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
    $stmtU->bind_param("ssss", $like, $like, $like, $like);
} else {
    $stmtU = $conn->prepare("
        SELECT u.*,
               (SELECT COUNT(*) FROM friendships f
                WHERE (f.user_id=u.id OR f.friend_id=u.id) AND f.status='accepted') AS nb_friends,
               (SELECT COUNT(*) FROM posts p WHERE p.user_id=u.id) AS nb_posts
        FROM users u
        ORDER BY u.created_at DESC
    ");
}
$stmtU->execute();
$users = $stmtU->get_result()->fetch_all(MYSQLI_ASSOC);

// STATISTIQUES globales
$stats = [];
foreach (['users','posts','friendships','messages','jobs','notifications'] as $table) {
    $r = $conn->query("SELECT COUNT(*) AS nb FROM $table");
    $stats[$table] = $r->fetch_assoc()['nb'];
}

include 'includes/header.php';
?>

<div class="container mt-4">

    <!-- En-tête admin -->
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center"
             style="width:44px;height:44px;">
            <i class="bi bi-shield-lock-fill fs-5 text-dark"></i>
        </div>
        <div>
            <h4 class="mb-0 fw-bold">Panneau d'administration</h4>
            <small class="text-muted">Connecté en tant que <?= e($currentUser['prenom']) ?> <?= e($currentUser['nom']) ?></small>
        </div>
    </div>

    <!-- Alertes -->
    <?php if ($success): ?>
        <div class="alert alert-success py-2"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= e($error) ?></div>
    <?php endif; ?>

    <!--STATISTIQUES-->
    <div class="row g-3 mb-4">
        <?php
        $statCards = [
            ['label'=>'Membres',        'key'=>'users',         'icon'=>'bi-people-fill',       'color'=>'primary'],
            ['label'=>'Publications',   'key'=>'posts',         'icon'=>'bi-file-post-fill',     'color'=>'success'],
            ['label'=>'Connexions',     'key'=>'friendships',   'icon'=>'bi-diagram-3-fill',     'color'=>'info'],
            ['label'=>'Messages',       'key'=>'messages',      'icon'=>'bi-chat-dots-fill',     'color'=>'warning'],
            ['label'=>'Offres emploi',  'key'=>'jobs',          'icon'=>'bi-briefcase-fill',     'color'=>'danger'],
            ['label'=>'Notifications',  'key'=>'notifications', 'icon'=>'bi-bell-fill',          'color'=>'secondary'],
        ];
        foreach ($statCards as $card):
        ?>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card text-center h-100">
                <div class="card-body py-3">
                    <i class="bi <?= $card['icon'] ?> fs-3 text-<?= $card['color'] ?>"></i>
                    <div class="fw-bold fs-4 mt-1"><?= $stats[$card['key']] ?></div>
                    <small class="text-muted"><?= $card['label'] ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!--GESTION DES UTILISATEURS-->
    <div class="card mb-4">
        <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span class="fw-semibold">
                <i class="bi bi-people-fill text-ece me-1"></i>
                Gestion des membres (<?= count($users) ?>)
            </span>
            <div class="d-flex gap-2">
                <!-- Recherche -->
                <form method="GET" action="admin.php" class="d-flex gap-1">
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Rechercher un membre…"
                           value="<?= e($search) ?>" style="width:200px;">
                    <button class="btn btn-sm btn-outline-secondary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                    <?php if ($search): ?>
                    <a href="admin.php" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-x-lg"></i>
                    </a>
                    <?php endif; ?>
                </form>
                <!-- Ajouter un utilisateur -->
                <button class="btn btn-sm btn-ece" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus-fill me-1"></i> Ajouter
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
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
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <!-- Avatar + Nom -->
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <a href="profil.php?id=<?= $u['id'] ?>">
                                    <img src="uploads/<?= e($u['photo']) ?>"
                                         class="rounded-circle border"
                                         width="36" height="36" style="object-fit:cover;">
                                </a>
                                <div>
                                    <a href="profil.php?id=<?= $u['id'] ?>"
                                       class="text-dark text-decoration-none fw-semibold small">
                                        <?= e($u['prenom']) ?> <?= e($u['nom']) ?>
                                    </a>
                                    <small class="text-muted d-block">@<?= e($u['pseudo']) ?></small>
                                </div>
                            </div>
                        </td>

                        <!-- Email -->
                        <td><small><?= e($u['email']) ?></small></td>

                        <!-- Rôle (modifiable en ligne) -->
                        <td>
                            <?php if ($u['id'] !== $userId): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action"    value="change_role">
                                <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                <select name="new_role" class="form-select form-select-sm"
                                        style="width:100px;"
                                        onchange="this.form.submit()">
                                    <option value="auteur" <?= $u['role']==='auteur' ? 'selected':'' ?>>Auteur</option>
                                    <option value="admin"  <?= $u['role']==='admin'  ? 'selected':'' ?>>Admin</option>
                                </select>
                            </form>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Admin (vous)</span>
                            <?php endif; ?>
                        </td>

                        <!-- Compteurs -->
                        <td class="text-center"><span class="badge bg-secondary"><?= $u['nb_posts'] ?></span></td>
                        <td class="text-center"><span class="badge bg-secondary"><?= $u['nb_friends'] ?></span></td>

                        <!-- Dates -->
                        <td><small class="text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></small></td>
                        <td>
                            <small class="text-muted">
                                <?= $u['last_login']
                                    ? date('d/m/Y H:i', strtotime($u['last_login']))
                                    : 'Jamais' ?>
                            </small>
                        </td>

                        <!-- Actions -->
                        <td class="text-center">
                            <?php if ($u['id'] !== $userId): ?>
                            <a href="admin.php?delete_user=<?= $u['id'] ?>"
                               class="btn btn-sm btn-outline-danger py-0"
                               onclick="return confirm('Supprimer <?= e($u['prenom']) ?> <?= e($u['nom']) ?> ? Cette action est irréversible.')">
                                <i class="bi bi-trash"></i>
                            </a>
                            <?php else: ?>
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

<!--MODALE : Ajouter un utilisateur-->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="admin.php">
        <input type="hidden" name="action" value="add_user">
        <div class="modal-header">
            <h5 class="modal-title">
                <i class="bi bi-person-plus-fill me-1"></i> Ajouter un membre
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
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
            <div class="mb-3">
                <label class="form-label fw-semibold">Pseudo *</label>
                <div class="input-group">
                    <span class="input-group-text">@</span>
                    <input type="text" name="pseudo" class="form-control" required placeholder="alice_dupont">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Email *</label>
                <input type="email" name="email" class="form-control" required placeholder="alice@ece.fr">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Mot de passe provisoire *</label>
                <input type="text" name="password" class="form-control" required
                       placeholder="Min. 6 caractères" minlength="6">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Rôle</label>
                <select name="role" class="form-select">
                    <option value="auteur" selected>Auteur (étudiant / enseignant)</option>
                    <option value="admin">Administrateur</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-ece">
                <i class="bi bi-person-plus-fill me-1"></i> Créer le compte
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
