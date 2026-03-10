<?php
// ============================================================
//  profil.php — Page de profil utilisateur
//  Accessible en ?id=X pour voir le profil d'un autre membre
// ============================================================
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$pageTitle   = "Profil";
$currentUid  = (int) $_SESSION['user_id'];

// Détermine quel profil afficher
$profileId   = isset($_GET['id']) ? (int) $_GET['id'] : $currentUid;
$isOwnProfile = ($profileId === $currentUid);

// Récupère l'utilisateur du profil
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $profileId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

if (!$profile) {
    header("Location: index.php"); exit();
}

// ------------------------------------------------------------------
// TRAITEMENT : Mise à jour du profil (auteur = profil proprio)
// ------------------------------------------------------------------
if ($isOwnProfile && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Mise à jour des infos de profil ---
    if ($action === 'update_profile') {
        $bio        = trim($_POST['bio']        ?? '');
        $poste      = trim($_POST['poste']      ?? '');
        $entreprise = trim($_POST['entreprise'] ?? '');
        $localisation = trim($_POST['localisation'] ?? '');

        // Upload photo de profil
        if (!empty($_FILES['photo']['name'])) {
            $ext  = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif'])) {
                $filename = 'avatar_' . $currentUid . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], 'uploads/' . $filename);
                $stmt = $conn->prepare("UPDATE users SET photo=? WHERE id=?");
                $stmt->bind_param("si", $filename, $currentUid);
                $stmt->execute();
                $profile['photo'] = $filename;
            }
        }

        // Upload photo de couverture
        if (!empty($_FILES['cover']['name'])) {
            $ext  = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png'])) {
                $filename = 'cover_' . $currentUid . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['cover']['tmp_name'], 'uploads/' . $filename);
                $stmt = $conn->prepare("UPDATE users SET cover=? WHERE id=?");
                $stmt->bind_param("si", $filename, $currentUid);
                $stmt->execute();
                $profile['cover'] = $filename;
            }
        }

        $stmt = $conn->prepare("UPDATE users SET bio=?, poste=?, entreprise=?, localisation=? WHERE id=?");
        $stmt->bind_param("ssssi", $bio, $poste, $entreprise, $localisation, $currentUid);
        $stmt->execute();

        $profile['bio'] = $bio; $profile['poste'] = $poste;
        $profile['entreprise'] = $entreprise; $profile['localisation'] = $localisation;
    }

    // --- Ajout d'une formation ---
    if ($action === 'add_formation') {
        $titre  = trim($_POST['titre']         ?? '');
        $etab   = trim($_POST['etablissement'] ?? '');
        $debut  = (int) ($_POST['date_debut']  ?? 0);
        $fin    = (int) ($_POST['date_fin']    ?? 0);
        $desc   = trim($_POST['description']   ?? '');

        if ($titre) {
            $stmt = $conn->prepare(
                "INSERT INTO formations (user_id, titre, etablissement, date_debut, date_fin, description)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("issiis", $currentUid, $titre, $etab, $debut, $fin, $desc);
            $stmt->execute();
        }
    }

    // --- Suppression d'une formation ---
    if ($action === 'del_formation') {
        $fid  = (int) ($_POST['formation_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM formations WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $fid, $currentUid);
        $stmt->execute();
    }

    // --- Ajout d'un projet ---
    if ($action === 'add_projet') {
        $titre = trim($_POST['titre']       ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $lien  = trim($_POST['lien']        ?? '');
        $debut = $_POST['date_debut']        ?? null;
        $fin   = $_POST['date_fin']          ?? null;

        if ($titre) {
            $stmt = $conn->prepare(
                "INSERT INTO projets (user_id, titre, description, date_debut, date_fin, lien)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("isssss", $currentUid, $titre, $desc, $debut, $fin, $lien);
            $stmt->execute();
        }
    }

    // --- Suppression d'un projet ---
    if ($action === 'del_projet') {
        $pid  = (int) ($_POST['projet_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM projets WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $pid, $currentUid);
        $stmt->execute();
    }

    header("Location: profil.php"); exit();
}

// ------------------------------------------------------------------
// RÉCUPÉRATION : Données du profil
// ------------------------------------------------------------------
$stmtForm = $conn->prepare("SELECT * FROM formations WHERE user_id=? ORDER BY date_debut DESC");
$stmtForm->bind_param("i", $profileId);
$stmtForm->execute();
$formations = $stmtForm->get_result()->fetch_all(MYSQLI_ASSOC);

$stmtProj = $conn->prepare("SELECT * FROM projets WHERE user_id=? ORDER BY date_debut DESC");
$stmtProj->bind_param("i", $profileId);
$stmtProj->execute();
$projets = $stmtProj->get_result()->fetch_all(MYSQLI_ASSOC);

// Posts du profil (visibles par le visiteur)
$visibiliteCond = $isOwnProfile
    ? "p.user_id = ?"
    : "(p.visibilite = 'public' OR (p.visibilite = 'amis' AND " . (areFriends($conn, $currentUid, $profileId) ? "1=1" : "1=0") . "))
       AND p.user_id = ?";

$stmtPosts = $conn->prepare("
    SELECT p.*,
           (SELECT COUNT(*) FROM reactions r WHERE r.post_id = p.id) AS nb_reactions,
           (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS nb_comments
    FROM posts p
    WHERE $visibiliteCond
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmtPosts->bind_param("i", $profileId);
$stmtPosts->execute();
$profilePosts = $stmtPosts->get_result()->fetch_all(MYSQLI_ASSOC);

// Statut de la relation avec l'utilisateur courant
$friendStatus = null;
if (!$isOwnProfile) {
    $stmtRel = $conn->prepare("
        SELECT status, user_id FROM friendships
        WHERE (user_id=? AND friend_id=?) OR (user_id=? AND friend_id=?)
    ");
    $stmtRel->bind_param("iiii", $currentUid, $profileId, $profileId, $currentUid);
    $stmtRel->execute();
    $rel = $stmtRel->get_result()->fetch_assoc();
    if ($rel) {
        $friendStatus = $rel['status'];
        $isSender     = ($rel['user_id'] == $currentUid);
    }
}

// Nombre de connexions du profil
$stmtNbF = $conn->prepare("
    SELECT COUNT(*) AS nb FROM friendships
    WHERE (user_id=? OR friend_id=?) AND status='accepted'
");
$stmtNbF->bind_param("ii", $profileId, $profileId);
$stmtNbF->execute();
$nbConnexions = $stmtNbF->get_result()->fetch_assoc()['nb'];

include 'includes/header.php';
?>

<div class="container mt-4">

<!-- =================== CARTE PROFIL PRINCIPALE =================== -->
<div class="card mb-4 overflow-hidden">

    <!-- Photo de couverture -->
    <div class="position-relative">
        <img src="uploads/<?= e($profile['cover'] ?? 'default_cover.jpg') ?>"
             class="cover-photo w-100" style="height:220px; object-fit:cover;">

        <?php if ($isOwnProfile): ?>
        <!-- Bouton modifier la couverture -->
        <label class="btn btn-sm btn-light position-absolute bottom-0 end-0 m-2 opacity-75">
            <i class="bi bi-camera-fill me-1"></i> Changer la couverture
            <input type="file" accept="image/*" id="cover-upload" class="d-none">
        </label>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <div class="d-flex flex-column flex-md-row align-items-start gap-3">

            <!-- Avatar -->
            <div class="position-relative" style="margin-top:-55px;">
                <img src="uploads/<?= e($profile['photo']) ?>"
                     class="profile-avatar" id="profile-photo-img">
                <?php if ($isOwnProfile): ?>
                <label class="btn btn-sm btn-light position-absolute bottom-0 end-0 rounded-circle p-1"
                       style="width:28px;height:28px;line-height:1;" title="Changer la photo">
                    <i class="bi bi-camera-fill" style="font-size:0.75rem;"></i>
                    <input type="file" accept="image/*" id="avatar-upload" class="d-none">
                </label>
                <?php endif; ?>
            </div>

            <!-- Infos principales -->
            <div class="flex-grow-1 mt-1">
                <h4 class="mb-0 fw-bold"><?= e($profile['prenom']) ?> <?= e($profile['nom']) ?></h4>
                <p class="text-muted mb-1"><?= e($profile['poste'] ?? '') ?>
                    <?php if ($profile['entreprise']): ?>
                        <span class="text-secondary">· <?= e($profile['entreprise']) ?></span>
                    <?php endif; ?>
                </p>
                <?php if ($profile['localisation']): ?>
                    <small class="text-muted"><i class="bi bi-geo-alt-fill me-1"></i><?= e($profile['localisation']) ?></small>
                <?php endif; ?>
                <p class="text-muted small mt-1">
                    <i class="bi bi-people-fill me-1"></i><?= $nbConnexions ?> connexion<?= $nbConnexions > 1 ? 's' : '' ?>
                </p>
            </div>

            <!-- Boutons d'action -->
            <div class="d-flex gap-2 flex-wrap mt-2 mt-md-0">
                <?php if ($isOwnProfile): ?>
                    <button class="btn btn-ece" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="bi bi-pencil-fill me-1"></i> Modifier le profil
                    </button>
                    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#cvModal">
                        <i class="bi bi-file-earmark-person-fill me-1"></i> Générer mon CV
                    </button>
                <?php else: ?>
                    <?php if ($friendStatus === 'accepted'): ?>
                        <a href="messagerie.php?with=<?= $profileId ?>" class="btn btn-ece">
                            <i class="bi bi-chat-dots-fill me-1"></i> Message
                        </a>
                        <a href="reseau.php?remove=<?= $profileId ?>" class="btn btn-outline-secondary"
                           onclick="return confirm('Retirer cette connexion ?')">
                            <i class="bi bi-person-dash-fill me-1"></i> Connexion
                        </a>
                    <?php elseif ($friendStatus === 'pending' && !$isSender): ?>
                        <a href="reseau.php?accept=<?= $profileId ?>" class="btn btn-ece">Accepter</a>
                        <a href="reseau.php?reject=<?= $profileId ?>" class="btn btn-outline-secondary">Refuser</a>
                    <?php elseif ($friendStatus === 'pending' && $isSender): ?>
                        <button class="btn btn-secondary" disabled>Demande envoyée</button>
                    <?php else: ?>
                        <button class="btn btn-ece add-friend-btn" data-user-id="<?= $profileId ?>">
                            <i class="bi bi-person-plus-fill me-1"></i> Se connecter
                        </button>
                        <a href="messagerie.php?with=<?= $profileId ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-chat-dots me-1"></i> Message
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bio -->
        <?php if ($profile['bio']): ?>
        <p class="mt-3 mb-0 text-secondary"><?= nl2br(e($profile['bio'])) ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">

<!-- =================== COLONNE GAUCHE =================== -->
<div class="col-lg-4">

    <!-- Formations -->
    <div class="card mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center fw-semibold">
            <span><i class="bi bi-mortarboard-fill text-ece me-1"></i> Formation</span>
            <?php if ($isOwnProfile): ?>
            <button class="btn btn-sm btn-ece py-0" data-bs-toggle="modal" data-bs-target="#addFormationModal">
                <i class="bi bi-plus-lg"></i>
            </button>
            <?php endif; ?>
        </div>
        <div class="card-body p-3">
            <?php if (empty($formations)): ?>
                <p class="text-muted small text-center">Aucune formation renseignée.</p>
            <?php endif; ?>
            <?php foreach ($formations as $f): ?>
            <div class="d-flex gap-2 mb-3 align-items-start">
                <div class="bg-light rounded p-2 flex-shrink-0">
                    <i class="bi bi-book-fill text-ece fs-5"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-semibold small"><?= e($f['titre']) ?></div>
                    <div class="text-muted" style="font-size:0.8rem;"><?= e($f['etablissement']) ?></div>
                    <div class="text-muted" style="font-size:0.75rem;">
                        <?= $f['date_debut'] ?: '?' ?> — <?= $f['date_fin'] ?: 'En cours' ?>
                    </div>
                    <?php if ($f['description']): ?>
                        <div class="text-secondary" style="font-size:0.78rem;"><?= e($f['description']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($isOwnProfile): ?>
                <form method="POST" class="ms-1">
                    <input type="hidden" name="action" value="del_formation">
                    <input type="hidden" name="formation_id" value="<?= $f['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1"
                            onclick="return confirm('Supprimer ?')">
                        <i class="bi bi-trash" style="font-size:0.7rem;"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Projets -->
    <div class="card mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center fw-semibold">
            <span><i class="bi bi-kanban-fill text-ece me-1"></i> Projets</span>
            <?php if ($isOwnProfile): ?>
            <button class="btn btn-sm btn-ece py-0" data-bs-toggle="modal" data-bs-target="#addProjetModal">
                <i class="bi bi-plus-lg"></i>
            </button>
            <?php endif; ?>
        </div>
        <div class="card-body p-3">
            <?php if (empty($projets)): ?>
                <p class="text-muted small text-center">Aucun projet renseigné.</p>
            <?php endif; ?>
            <?php foreach ($projets as $p): ?>
            <div class="d-flex gap-2 mb-3 align-items-start">
                <div class="bg-light rounded p-2 flex-shrink-0">
                    <i class="bi bi-lightbulb-fill text-warning fs-5"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-semibold small"><?= e($p['titre']) ?></div>
                    <?php if ($p['date_debut']): ?>
                        <div class="text-muted" style="font-size:0.75rem;">
                            <?= date('m/Y', strtotime($p['date_debut'])) ?>
                            <?= $p['date_fin'] ? ' — ' . date('m/Y', strtotime($p['date_fin'])) : ' — En cours' ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($p['description']): ?>
                        <div class="text-secondary" style="font-size:0.78rem;"><?= e($p['description']) ?></div>
                    <?php endif; ?>
                    <?php if ($p['lien']): ?>
                        <a href="<?= e($p['lien']) ?>" target="_blank" class="small">
                            <i class="bi bi-link-45deg"></i> Lien
                        </a>
                    <?php endif; ?>
                </div>
                <?php if ($isOwnProfile): ?>
                <form method="POST" class="ms-1">
                    <input type="hidden" name="action" value="del_projet">
                    <input type="hidden" name="projet_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1"
                            onclick="return confirm('Supprimer ?')">
                        <i class="bi bi-trash" style="font-size:0.7rem;"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div><!-- fin colonne gauche -->

<!-- =================== COLONNE DROITE : Posts du profil =================== -->
<div class="col-lg-8">
    <div class="card">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-grid-fill text-ece me-1"></i> Publications
        </div>
        <div class="card-body">
            <?php if (empty($profilePosts)): ?>
                <p class="text-muted text-center py-4">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Aucune publication visible.
                </p>
            <?php endif; ?>
            <?php foreach ($profilePosts as $post): ?>
            <div class="border-bottom pb-3 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <small class="text-muted">
                        <?= date('d/m/Y à H:i', strtotime($post['created_at'])) ?>
                        <?php if ($post['lieu']): ?>
                            · <i class="bi bi-geo-alt-fill"></i> <?= e($post['lieu']) ?>
                        <?php endif; ?>
                    </small>
                    <?php if ($isOwnProfile): ?>
                    <a href="index.php?delete_post=<?= $post['id'] ?>"
                       class="btn btn-sm btn-outline-danger py-0"
                       onclick="return confirm('Supprimer ?')">
                        <i class="bi bi-trash"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php if ($post['contenu']): ?>
                    <p class="mb-1 mt-1"><?= nl2br(e($post['contenu'])) ?></p>
                <?php endif; ?>
                <?php if ($post['media']): ?>
                <div class="mt-2">
                    <?php if ($post['media_type'] === 'image'): ?>
                        <img src="uploads/<?= e($post['media']) ?>" class="img-fluid rounded" style="max-height:200px;">
                    <?php elseif ($post['media_type'] === 'video'): ?>
                        <video src="uploads/<?= e($post['media']) ?>" controls class="rounded" style="max-height:180px;"></video>
                    <?php elseif ($post['media_type'] === 'pdf'): ?>
                        <a href="uploads/<?= e($post['media']) ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-file-pdf-fill me-1"></i> Voir le PDF
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <small class="text-muted">
                    👍 <?= $post['nb_reactions'] ?> · 💬 <?= $post['nb_comments'] ?>
                </small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

</div><!-- fin row -->
</div><!-- fin container -->

<!-- =================== MODALES =================== -->

<?php if ($isOwnProfile): ?>

<!-- Modale : Modifier le profil -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_profile">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil-fill me-1"></i> Modifier mon profil</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label fw-semibold">Poste actuel</label>
                <input type="text" name="poste" class="form-control"
                       value="<?= e($profile['poste'] ?? '') ?>" placeholder="Ex: Étudiant Ingénieur">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Entreprise / École</label>
                <input type="text" name="entreprise" class="form-control"
                       value="<?= e($profile['entreprise'] ?? '') ?>" placeholder="Ex: ECE Paris">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Localisation</label>
                <input type="text" name="localisation" class="form-control"
                       value="<?= e($profile['localisation'] ?? '') ?>" placeholder="Ex: Paris, France">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Biographie</label>
                <textarea name="bio" class="form-control" rows="3"
                          placeholder="Parlez un peu de vous..."><?= e($profile['bio'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Photo de profil</label>
                <input type="file" name="photo" class="form-control" accept="image/*">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Photo de couverture</label>
                <input type="file" name="cover" class="form-control" accept="image/*">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-ece">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modale : Ajouter une formation -->
<div class="modal fade" id="addFormationModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add_formation">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-mortarboard-fill me-1"></i> Ajouter une formation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label fw-semibold">Titre du diplôme / formation *</label>
                <input type="text" name="titre" class="form-control" required
                       placeholder="Ex: Cycle Ingénieur — Spécialisation IA">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Établissement</label>
                <input type="text" name="etablissement" class="form-control" placeholder="Ex: ECE Paris">
            </div>
            <div class="row g-2">
                <div class="col">
                    <label class="form-label fw-semibold">Année de début</label>
                    <input type="number" name="date_debut" class="form-control"
                           min="1990" max="2040" placeholder="2022">
                </div>
                <div class="col">
                    <label class="form-label fw-semibold">Année de fin</label>
                    <input type="number" name="date_fin" class="form-control"
                           min="1990" max="2040" placeholder="2027">
                </div>
            </div>
            <div class="mb-3 mt-2">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="2"
                          placeholder="Ex: Mention Bien, spécialisation..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-ece">Ajouter</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modale : Ajouter un projet -->
<div class="modal fade" id="addProjetModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add_projet">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-lightbulb-fill me-1"></i> Ajouter un projet</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label fw-semibold">Titre du projet *</label>
                <input type="text" name="titre" class="form-control" required
                       placeholder="Ex: Projet ECE In — Social Media">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="2"
                          placeholder="Décrivez le projet..."></textarea>
            </div>
            <div class="row g-2">
                <div class="col">
                    <label class="form-label fw-semibold">Date de début</label>
                    <input type="month" name="date_debut" class="form-control">
                </div>
                <div class="col">
                    <label class="form-label fw-semibold">Date de fin</label>
                    <input type="month" name="date_fin" class="form-control">
                </div>
            </div>
            <div class="mb-3 mt-2">
                <label class="form-label fw-semibold">Lien (GitHub, site...)</label>
                <input type="url" name="lien" class="form-control" placeholder="https://...">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-ece">Ajouter</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- =================== MODALE CV =================== -->
<div class="modal fade" id="cvModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-file-earmark-person-fill me-1"></i> Mon CV — Aperçu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-0">
            <!-- CV généré en HTML (style impression) -->
            <div id="cv-content" style="padding:30px; font-family: 'Segoe UI', sans-serif; background:#fff;">

                <!-- En-tête CV -->
                <div style="display:flex; align-items:center; gap:20px; border-bottom:3px solid #003a7d; padding-bottom:16px; margin-bottom:20px;">
                    <img src="uploads/<?= e($profile['photo']) ?>"
                         style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid #003a7d;">
                    <div>
                        <h2 style="margin:0;color:#003a7d;"><?= e($profile['prenom']) ?> <?= e($profile['nom']) ?></h2>
                        <p style="margin:4px 0;color:#555;"><?= e($profile['poste'] ?? '') ?>
                            <?= $profile['entreprise'] ? ' · ' . e($profile['entreprise']) : '' ?>
                        </p>
                        <p style="margin:0;color:#777;font-size:0.85rem;">
                            <?= e($profile['email']) ?>
                            <?= $profile['localisation'] ? ' · ' . e($profile['localisation']) : '' ?>
                        </p>
                    </div>
                </div>

                <!-- Résumé -->
                <?php if ($profile['bio']): ?>
                <div style="margin-bottom:20px;">
                    <h4 style="color:#003a7d;border-bottom:1px solid #ddd;padding-bottom:4px;">Profil</h4>
                    <p style="color:#333;"><?= nl2br(e($profile['bio'])) ?></p>
                </div>
                <?php endif; ?>

                <!-- Formation -->
                <?php if (!empty($formations)): ?>
                <div style="margin-bottom:20px;">
                    <h4 style="color:#003a7d;border-bottom:1px solid #ddd;padding-bottom:4px;">Formation</h4>
                    <?php foreach ($formations as $f): ?>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex;justify-content:space-between;">
                            <strong><?= e($f['titre']) ?></strong>
                            <span style="color:#777;font-size:0.85rem;">
                                <?= $f['date_debut'] ?: '' ?><?= $f['date_fin'] ? ' — ' . $f['date_fin'] : ($f['date_debut'] ? ' — En cours' : '') ?>
                            </span>
                        </div>
                        <div style="color:#555;"><?= e($f['etablissement']) ?></div>
                        <?php if ($f['description']): ?>
                            <div style="color:#777;font-size:0.85rem;"><?= e($f['description']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Projets -->
                <?php if (!empty($projets)): ?>
                <div style="margin-bottom:20px;">
                    <h4 style="color:#003a7d;border-bottom:1px solid #ddd;padding-bottom:4px;">Projets</h4>
                    <?php foreach ($projets as $p): ?>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex;justify-content:space-between;">
                            <strong><?= e($p['titre']) ?></strong>
                            <?php if ($p['date_debut']): ?>
                            <span style="color:#777;font-size:0.85rem;">
                                <?= date('m/Y', strtotime($p['date_debut'])) ?>
                                <?= $p['date_fin'] ? ' — ' . date('m/Y', strtotime($p['date_fin'])) : ' — En cours' ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($p['description']): ?>
                            <div style="color:#555;font-size:0.9rem;"><?= e($p['description']) ?></div>
                        <?php endif; ?>
                        <?php if ($p['lien']): ?>
                            <a href="<?= e($p['lien']) ?>" style="font-size:0.85rem;color:#003a7d;"><?= e($p['lien']) ?></a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Pied du CV -->
                <div style="text-align:center;color:#aaa;font-size:0.75rem;margin-top:30px;border-top:1px solid #eee;padding-top:10px;">
                    CV généré par ECE In · <?= date('d/m/Y') ?>
                </div>

            </div><!-- fin cv-content -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
            <button class="btn btn-ece" onclick="printCV()">
                <i class="bi bi-printer-fill me-1"></i> Imprimer / Enregistrer en PDF
            </button>
        </div>
    </div>
  </div>
</div>

<script>
// Impression du CV dans une nouvelle fenêtre (→ PDF via le navigateur)
function printCV() {
    const content = document.getElementById('cv-content').innerHTML;
    const win = window.open('', '_blank');
    win.document.write(`<!DOCTYPE html>
    <html><head>
        <meta charset="UTF-8">
        <title>CV — <?= e($profile['prenom']) ?> <?= e($profile['nom']) ?></title>
        <style>
            body { font-family: 'Segoe UI', sans-serif; margin: 20px; }
            @media print { body { margin: 0; } }
        </style>
    </head><body>${content}</body></html>`);
    win.document.close();
    win.focus();
    win.print();
    win.close();
}
</script>

<?php endif; // isOwnProfile ?>

<?php include 'includes/footer.php'; ?>
