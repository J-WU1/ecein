<?php
// ============================================================
//  emplois.php — Offres d'emploi, stages, alternances
// ============================================================
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$pageTitle = "Emplois";
$userId    = (int) $_SESSION['user_id'];
$isAdmin   = ($_SESSION['user_role'] === 'admin');

// ------------------------------------------------------------------
// TRAITEMENT ADMIN : Ajouter une offre
// ------------------------------------------------------------------
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_job') {
    $titre      = trim($_POST['titre']       ?? '');
    $entreprise = trim($_POST['entreprise']  ?? '');
    $lieu       = trim($_POST['lieu']        ?? '');
    $type       = $_POST['type']              ?? 'CDI';
    $desc       = trim($_POST['description'] ?? '');
    $lien       = trim($_POST['lien']        ?? '');

    if ($titre && $entreprise) {
        $stmt = $conn->prepare("
            INSERT INTO jobs (titre, entreprise, lieu, type, description, lien, posted_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssi", $titre, $entreprise, $lieu, $type, $desc, $lien, $userId);
        $stmt->execute();

        // Notifie tous les utilisateurs de la nouvelle offre
        $stmtUsers = $conn->query("SELECT id FROM users WHERE id != $userId");
        while ($u = $stmtUsers->fetch_assoc()) {
            createNotification(
                $conn, $u['id'], 'job',
                "Nouvelle offre : $titre chez $entreprise ($type)",
                'emplois.php'
            );
        }
    }
    header("Location: emplois.php"); exit();
}

// ------------------------------------------------------------------
// TRAITEMENT ADMIN : Supprimer une offre
// ------------------------------------------------------------------
if ($isAdmin && isset($_GET['delete_job'])) {
    $jobId = (int) $_GET['delete_job'];
    $stmt  = $conn->prepare("DELETE FROM jobs WHERE id = ?");
    $stmt->bind_param("i", $jobId);
    $stmt->execute();
    header("Location: emplois.php"); exit();
}

// ------------------------------------------------------------------
// FILTRES de recherche
// ------------------------------------------------------------------
$filterType  = $_GET['type']   ?? '';
$filterQuery = trim($_GET['q'] ?? '');

// Construction dynamique de la requête avec filtres
$where  = "WHERE 1=1";
$params = [];
$types  = "";

if ($filterType && in_array($filterType, ['CDI','CDD','Stage','Alternance','Freelance'])) {
    $where   .= " AND j.type = ?";
    $params[] = $filterType;
    $types   .= "s";
}
if ($filterQuery) {
    $like     = "%$filterQuery%";
    $where   .= " AND (j.titre LIKE ? OR j.entreprise LIKE ? OR j.description LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= "sss";
}

$sql = "SELECT j.*, u.prenom, u.nom FROM jobs j
        LEFT JOIN users u ON u.id = j.posted_by
        $where ORDER BY j.created_at DESC";

$stmtJobs = $conn->prepare($sql);
if (!empty($params)) {
    $stmtJobs->bind_param($types, ...$params);
}
$stmtJobs->execute();
$jobs = $stmtJobs->get_result()->fetch_all(MYSQLI_ASSOC);

// Compteurs par type (pour les filtres)
$stmtCounts = $conn->query("SELECT type, COUNT(*) AS nb FROM jobs GROUP BY type");
$counts     = [];
while ($row = $stmtCounts->fetch_assoc()) {
    $counts[$row['type']] = $row['nb'];
}

include 'includes/header.php';
?>

<div class="container mt-4">
<div class="row g-3">

<!-- ============= COLONNE GAUCHE : Filtres ============= -->
<div class="col-lg-3">
    <div class="card sidebar-card">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-funnel-fill text-ece me-1"></i> Filtrer
        </div>
        <div class="card-body p-3">

            <!-- Barre de recherche -->
            <form method="GET" action="emplois.php" class="mb-3">
                <div class="input-group">
                    <input type="text" name="q" class="form-control form-control-sm"
                           placeholder="Rechercher…"
                           value="<?= e($filterQuery) ?>">
                    <button class="btn btn-sm btn-ece" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <?php if ($filterType): ?>
                    <input type="hidden" name="type" value="<?= e($filterType) ?>">
                <?php endif; ?>
            </form>

            <!-- Filtres par type -->
            <div class="list-group list-group-flush">
                <a href="emplois.php<?= $filterQuery ? '?q='.urlencode($filterQuery) : '' ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between
                          <?= !$filterType ? 'active' : '' ?>">
                    Tous les types
                    <span class="badge bg-secondary"><?= array_sum($counts) ?></span>
                </a>
                <?php
                $typeColors = [
                    'CDI'        => 'success',
                    'CDD'        => 'primary',
                    'Stage'      => 'warning',
                    'Alternance' => 'purple',
                    'Freelance'  => 'teal',
                ];
                $typeBadge  = [
                    'CDI'        => 'success',
                    'CDD'        => 'primary',
                    'Stage'      => 'warning',
                    'Alternance' => 'purple',
                    'Freelance'  => 'info',
                ];
                foreach (['CDI','CDD','Stage','Alternance','Freelance'] as $t):
                    $nb  = $counts[$t] ?? 0;
                    $sep = $filterQuery ? '&' : '?';
                ?>
                <a href="emplois.php<?= $filterQuery ? '?q='.urlencode($filterQuery) : '' ?><?= $sep ?>type=<?= $t ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between
                          <?= $filterType === $t ? 'active' : '' ?>">
                    <?= $t ?>
                    <span class="badge bg-secondary"><?= $nb ?></span>
                </a>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

    <!-- Bouton admin : ajouter une offre -->
    <?php if ($isAdmin): ?>
    <button class="btn btn-ece w-100 mt-3" data-bs-toggle="modal" data-bs-target="#addJobModal">
        <i class="bi bi-plus-circle-fill me-1"></i> Publier une offre
    </button>
    <?php endif; ?>
</div><!-- fin colonne gauche -->

<!-- ============= COLONNE PRINCIPALE : Liste des offres ============= -->
<div class="col-lg-9">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">
            <i class="bi bi-briefcase-fill text-ece me-2"></i>
            <?= count($jobs) ?> offre<?= count($jobs) > 1 ? 's' : '' ?>
            <?= $filterType ? " · $filterType" : '' ?>
            <?= $filterQuery ? " · \"" . e($filterQuery) . "\"" : '' ?>
        </h5>
    </div>

    <?php if (empty($jobs)): ?>
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-search fs-1 d-block mb-3"></i>
            <p>Aucune offre ne correspond à vos critères.</p>
            <a href="emplois.php" class="btn btn-outline-secondary btn-sm">
                Voir toutes les offres
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php foreach ($jobs as $job):
        $badgeClass = 'badge-' . $job['type'];
    ?>
    <div class="card job-card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">

                <!-- Icône entreprise (initiale) -->
                <div class="d-flex align-items-start gap-3 flex-grow-1">
                    <div class="rounded bg-light d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:52px; height:52px; font-size:1.4rem; font-weight:bold; color:#003a7d;">
                        <?= mb_strtoupper(mb_substr($job['entreprise'], 0, 1)) ?>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1"><?= e($job['titre']) ?></h6>
                        <div class="text-muted small mb-2">
                            <i class="bi bi-building me-1"></i><?= e($job['entreprise']) ?>
                            <?php if ($job['lieu']): ?>
                                · <i class="bi bi-geo-alt me-1"></i><?= e($job['lieu']) ?>
                            <?php endif; ?>
                        </div>

                        <!-- Badge type -->
                        <span class="badge <?= $badgeClass ?> text-white me-1">
                            <?= $job['type'] ?>
                        </span>

                        <!-- Description (tronquée) -->
                        <?php if ($job['description']): ?>
                        <p class="text-secondary small mt-2 mb-1"
                           style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">
                            <?= nl2br(e($job['description'])) ?>
                        </p>
                        <?php endif; ?>

                        <small class="text-muted">
                            Publié le <?= date('d/m/Y', strtotime($job['created_at'])) ?>
                            <?php if ($job['prenom']): ?>
                                par <?= e($job['prenom']) ?> <?= e($job['nom']) ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>

                <!-- Boutons -->
                <div class="d-flex flex-column gap-2 flex-shrink-0">
                    <?php if ($job['lien']): ?>
                    <a href="<?= e($job['lien']) ?>" target="_blank"
                       class="btn btn-sm btn-ece">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Postuler
                    </a>
                    <?php else: ?>
                    <button class="btn btn-sm btn-ece" data-bs-toggle="modal"
                            data-bs-target="#applyModal<?= $job['id'] ?>">
                        <i class="bi bi-send-fill me-1"></i>Postuler
                    </button>
                    <?php endif; ?>

                    <?php if ($isAdmin): ?>
                    <a href="emplois.php?delete_job=<?= $job['id'] ?>"
                       class="btn btn-sm btn-outline-danger"
                       onclick="return confirm('Supprimer cette offre ?')">
                        <i class="bi bi-trash"></i>
                    </a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- Modale Postuler (si pas de lien externe) -->
    <?php if (!$job['lien']): ?>
    <div class="modal fade" id="applyModal<?= $job['id'] ?>" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Postuler — <?= e($job['titre']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Pour postuler à cette offre, envoyez votre CV et lettre de motivation à :
                </p>
                <div class="alert alert-info py-2">
                    <i class="bi bi-envelope-fill me-1"></i>
                    <strong>recrutement@ece.fr</strong>
                    <br>
                    <small>Objet : Candidature — <?= e($job['titre']) ?> — <?= e($job['entreprise']) ?></small>
                </div>
                <p class="small text-muted">
                    Pensez à joindre votre CV depuis votre profil ECE In
                    (<a href="profil.php">Générer mon CV</a>).
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fermer</button>
                <a href="mailto:recrutement@ece.fr?subject=Candidature — <?= rawurlencode($job['titre']) ?>"
                   class="btn btn-ece btn-sm">
                    <i class="bi bi-envelope-fill me-1"></i> Envoyer un email
                </a>
            </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php endforeach; ?>

</div><!-- fin colonne principale -->
</div><!-- fin row -->
</div><!-- fin container -->

<!-- ============= MODALE ADMIN : Ajouter une offre ============= -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="addJobModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="emplois.php">
        <input type="hidden" name="action" value="add_job">
        <div class="modal-header">
            <h5 class="modal-title">
                <i class="bi bi-plus-circle-fill me-1"></i> Publier une offre
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label fw-semibold">Titre du poste *</label>
                <input type="text" name="titre" class="form-control" required
                       placeholder="Ex: Stage Développeur Web Full-Stack">
            </div>
            <div class="row g-2 mb-3">
                <div class="col">
                    <label class="form-label fw-semibold">Entreprise *</label>
                    <input type="text" name="entreprise" class="form-control" required
                           placeholder="Ex: ECE Paris">
                </div>
                <div class="col">
                    <label class="form-label fw-semibold">Localisation</label>
                    <input type="text" name="lieu" class="form-control"
                           placeholder="Ex: Paris 15e">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Type de contrat</label>
                <select name="type" class="form-select">
                    <option value="CDI">CDI</option>
                    <option value="CDD">CDD</option>
                    <option value="Stage" selected>Stage</option>
                    <option value="Alternance">Alternance</option>
                    <option value="Freelance">Freelance</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Description du poste</label>
                <textarea name="description" class="form-control" rows="4"
                          placeholder="Décrivez le poste, les missions, le profil recherché…"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Lien de candidature (optionnel)</label>
                <input type="url" name="lien" class="form-control"
                       placeholder="https://...">
                <div class="form-text">Si vide, un email de contact sera proposé.</div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-ece">
                <i class="bi bi-plus-circle-fill me-1"></i> Publier
            </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Couleurs des badges de type de contrat -->
<style>
.badge-CDI        { background-color: #198754 !important; }
.badge-CDD        { background-color: #0d6efd !important; }
.badge-Stage      { background-color: #fd7e14 !important; }
.badge-Alternance { background-color: #6f42c1 !important; }
.badge-Freelance  { background-color: #20c997 !important; }
</style>

<?php include 'includes/footer.php'; ?>
