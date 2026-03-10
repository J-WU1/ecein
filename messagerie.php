<?php
// ============================================================
//  messagerie.php — Chat privé avec AJAX polling
// ============================================================
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$pageTitle = "Messagerie";
$userId    = (int) $_SESSION['user_id'];

// Destinataire pré-sélectionné via ?with=X (ex: depuis profil.php)
$withId = isset($_GET['with']) ? (int) $_GET['with'] : 0;

// ------------------------------------------------------------------
// RÉCUPÉRATION : Liste des conversations (une par interlocuteur)
// Le dernier message échangé avec chaque personne
// ------------------------------------------------------------------
$stmtConvs = $conn->prepare("
    SELECT
        u.id, u.prenom, u.nom, u.photo, u.poste,
        m.contenu      AS last_message,
        m.created_at   AS last_time,
        m.sender_id,
        (SELECT COUNT(*) FROM messages
         WHERE receiver_id = ? AND sender_id = u.id AND lu = 0) AS unread
    FROM users u
    JOIN messages m ON m.id = (
        SELECT id FROM messages
        WHERE (sender_id = ? AND receiver_id = u.id)
           OR (sender_id = u.id AND receiver_id = ?)
        ORDER BY created_at DESC
        LIMIT 1
    )
    WHERE u.id != ?
    ORDER BY m.created_at DESC
");
$stmtConvs->bind_param("iiii", $userId, $userId, $userId, $userId);
$stmtConvs->execute();
$conversations = $stmtConvs->get_result()->fetch_all(MYSQLI_ASSOC);

// Si ?with= et que cet utilisateur n'est pas encore dans les conversations,
// on le récupère quand même pour pouvoir lui écrire
$selectedUser = null;
if ($withId) {
    $stmtWith = $conn->prepare("SELECT id, prenom, nom, photo, poste FROM users WHERE id = ?");
    $stmtWith->bind_param("i", $withId);
    $stmtWith->execute();
    $selectedUser = $stmtWith->get_result()->fetch_assoc();

    // Marquer les messages de cet utilisateur comme lus
    $stmtRead = $conn->prepare(
        "UPDATE messages SET lu = 1 WHERE sender_id = ? AND receiver_id = ?"
    );
    $stmtRead->bind_param("ii", $withId, $userId);
    $stmtRead->execute();
}

// Si pas de ?with= mais des conversations existantes, on sélectionne la première
if (!$withId && !empty($conversations)) {
    $withId       = $conversations[0]['id'];
    $selectedUser = $conversations[0];
}

// ------------------------------------------------------------------
// RÉCUPÉRATION : Historique des messages avec l'interlocuteur choisi
// ------------------------------------------------------------------
$history = [];
if ($withId) {
    $stmtHist = $conn->prepare("
        SELECT m.*, u.prenom, u.nom, u.photo
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmtHist->bind_param("iiii", $userId, $withId, $withId, $userId);
    $stmtHist->execute();
    $history = $stmtHist->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ID du dernier message pour le polling AJAX
$lastId = !empty($history) ? end($history)['id'] : 0;

include 'includes/header.php';
?>

<div class="container-fluid mt-3" style="max-width:1100px;">
<div class="row g-0 rounded shadow overflow-hidden" style="height:calc(100vh - 90px); min-height:500px;">

    <!-- ============= COLONNE GAUCHE : Liste des conversations ============= -->
    <div class="col-md-4 col-lg-3 border-end bg-white d-flex flex-column" style="overflow:hidden;">

        <!-- En-tête -->
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-ece">
                <i class="bi bi-chat-dots-fill me-1"></i> Messagerie
            </h6>
            <!-- Bouton nouvelle conversation -->
            <button class="btn btn-sm btn-ece" data-bs-toggle="modal" data-bs-target="#newConvModal">
                <i class="bi bi-pencil-square"></i>
            </button>
        </div>

        <!-- Liste des conversations -->
        <div style="overflow-y:auto; flex:1;">
            <?php if (empty($conversations) && !$selectedUser): ?>
                <div class="text-center text-muted py-5 px-3">
                    <i class="bi bi-chat-dots fs-1 d-block mb-2"></i>
                    <small>Aucune conversation.<br>Commencez à écrire à quelqu'un !</small>
                </div>
            <?php endif; ?>

            <?php
            // Affiche l'utilisateur sélectionné en premier s'il n'est pas dans les convs
            $convIds = array_column($conversations, 'id');
            if ($selectedUser && !in_array($selectedUser['id'], $convIds)):
            ?>
            <a href="messagerie.php?with=<?= $selectedUser['id'] ?>"
               class="d-flex gap-2 p-3 text-decoration-none conversation-item active-conv border-bottom">
                <img src="uploads/<?= e($selectedUser['photo']) ?>"
                     class="rounded-circle flex-shrink-0"
                     width="44" height="44" style="object-fit:cover;">
                <div class="overflow-hidden">
                    <div class="fw-semibold text-dark small">
                        <?= e($selectedUser['prenom']) ?> <?= e($selectedUser['nom']) ?>
                    </div>
                    <small class="text-muted text-truncate d-block">Démarrer une conversation</small>
                </div>
            </a>
            <?php endif; ?>

            <?php foreach ($conversations as $conv): ?>
            <a href="messagerie.php?with=<?= $conv['id'] ?>"
               class="d-flex gap-2 p-3 text-decoration-none conversation-item border-bottom
                      <?= ($conv['id'] == $withId) ? 'active-conv' : '' ?>">
                <div class="position-relative flex-shrink-0">
                    <img src="uploads/<?= e($conv['photo']) ?>"
                         class="rounded-circle"
                         width="44" height="44" style="object-fit:cover;">
                    <?php if ($conv['unread'] > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                              style="font-size:0.6rem;">
                            <?= $conv['unread'] ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="overflow-hidden flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold text-dark small <?= $conv['unread'] > 0 ? 'fw-bold' : '' ?>">
                            <?= e($conv['prenom']) ?> <?= e($conv['nom']) ?>
                        </span>
                        <small class="text-muted flex-shrink-0 ms-1">
                            <?= date('H:i', strtotime($conv['last_time'])) ?>
                        </small>
                    </div>
                    <small class="text-muted text-truncate d-block">
                        <?= $conv['sender_id'] == $userId ? 'Vous : ' : '' ?>
                        <?= e(mb_substr($conv['last_message'], 0, 35)) ?>…
                    </small>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div><!-- fin colonne gauche -->

    <!-- ============= COLONNE DROITE : Zone de chat ============= -->
    <div class="col-md-8 col-lg-9 d-flex flex-column bg-light" style="overflow:hidden;">

        <?php if ($selectedUser): ?>

        <!-- En-tête du chat -->
        <div class="p-3 bg-white border-bottom d-flex align-items-center gap-2 shadow-sm">
            <a href="profil.php?id=<?= $selectedUser['id'] ?>">
                <img src="uploads/<?= e($selectedUser['photo']) ?>"
                     class="rounded-circle border border-warning"
                     width="42" height="42" style="object-fit:cover;">
            </a>
            <div>
                <a href="profil.php?id=<?= $selectedUser['id'] ?>"
                   class="fw-semibold text-dark text-decoration-none">
                    <?= e($selectedUser['prenom']) ?> <?= e($selectedUser['nom']) ?>
                </a>
                <small class="text-muted d-block"><?= e($selectedUser['poste'] ?? 'Membre ECE') ?></small>
            </div>
        </div>

        <!-- Zone des messages -->
        <div id="chat-box" class="flex-grow-1 p-3" style="overflow-y:auto;">
            <?php foreach ($history as $msg): ?>
                <?php if ($msg['sender_id'] == $userId): ?>
                <!-- Message envoyé -->
                <div class="msg-sent">
                    <span><?= nl2br(e($msg['contenu'])) ?></span>
                    <div class="msg-time"><?= date('d/m H:i', strtotime($msg['created_at'])) ?></div>
                </div>
                <?php else: ?>
                <!-- Message reçu -->
                <div class="msg-received d-flex gap-2 align-items-end">
                    <img src="uploads/<?= e($msg['photo']) ?>"
                         class="rounded-circle flex-shrink-0"
                         width="28" height="28" style="object-fit:cover;">
                    <div>
                        <span><?= nl2br(e($msg['contenu'])) ?></span>
                        <div class="msg-time"><?= date('d/m H:i', strtotime($msg['created_at'])) ?></div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Formulaire d'envoi -->
        <div class="p-3 bg-white border-top">
            <form id="send-message-form" class="d-flex gap-2 align-items-end">
                <input type="hidden" id="receiver-id" value="<?= $withId ?>">
                <textarea id="message-input" class="form-control" rows="1"
                          placeholder="Écrivez un message…"
                          style="resize:none; border-radius:20px; padding:10px 16px;"
                          onkeydown="if(event.key==='Enter' && !event.shiftKey){ event.preventDefault(); $('#send-message-form').submit(); }"></textarea>
                <button type="submit" class="btn btn-ece rounded-circle flex-shrink-0"
                        style="width:44px; height:44px;">
                    <i class="bi bi-send-fill"></i>
                </button>
            </form>
            <small class="text-muted ms-2">Entrée pour envoyer · Maj+Entrée pour sauter une ligne</small>
        </div>

        <!-- Stocker le dernier ID pour le polling -->
        <script>let lastMessageId = <?= $lastId ?>;</script>

        <?php else: ?>
        <!-- Aucune conversation sélectionnée -->
        <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
            <i class="bi bi-chat-heart fs-1 mb-3 text-ece"></i>
            <h5>Vos messages privés</h5>
            <p class="small text-center px-4">
                Envoyez des messages à vos connexions.<br>
                Cliquez sur une conversation ou commencez-en une nouvelle.
            </p>
            <button class="btn btn-ece" data-bs-toggle="modal" data-bs-target="#newConvModal">
                <i class="bi bi-pencil-square me-1"></i> Nouveau message
            </button>
        </div>
        <?php endif; ?>

    </div><!-- fin colonne droite -->
</div><!-- fin row -->
</div><!-- fin container -->

<!-- ============= MODALE : Nouvelle conversation ============= -->
<div class="modal fade" id="newConvModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil-square me-1"></i> Nouveau message</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <label class="form-label fw-semibold">Envoyer à :</label>
            <!-- Recherche et liste des connexions -->
            <input type="text" id="search-contact" class="form-control mb-3"
                   placeholder="Rechercher une connexion…">
            <div id="contact-list">
                <?php
                // Récupère toutes les connexions acceptées
                $stmtAll = $conn->prepare("
                    SELECT u.id, u.prenom, u.nom, u.photo, u.poste
                    FROM users u
                    JOIN friendships f ON (
                        (f.user_id = ? AND f.friend_id = u.id) OR
                        (f.friend_id = ? AND f.user_id = u.id)
                    )
                    WHERE f.status = 'accepted' AND u.id != ?
                    ORDER BY u.prenom, u.nom
                ");
                $stmtAll->bind_param("iii", $userId, $userId, $userId);
                $stmtAll->execute();
                $allFriends = $stmtAll->get_result()->fetch_all(MYSQLI_ASSOC);
                ?>
                <?php if (empty($allFriends)): ?>
                    <p class="text-muted small text-center">
                        Vous n'avez aucune connexion pour le moment.
                    </p>
                <?php endif; ?>
                <?php foreach ($allFriends as $f): ?>
                <a href="messagerie.php?with=<?= $f['id'] ?>"
                   class="d-flex gap-2 align-items-center p-2 rounded contact-item text-decoration-none text-dark border-bottom">
                    <img src="uploads/<?= e($f['photo']) ?>"
                         class="rounded-circle" width="38" height="38" style="object-fit:cover;">
                    <div>
                        <div class="fw-semibold small">
                            <?= e($f['prenom']) ?> <?= e($f['nom']) ?>
                        </div>
                        <small class="text-muted"><?= e($f['poste'] ?? 'Membre ECE') ?></small>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
  </div>
</div>

<style>
/* Styles spécifiques à la messagerie */
.conversation-item { transition: background 0.15s; }
.conversation-item:hover { background: #f0f2f5; }
.active-conv { background: #e8f0fe !important; border-left: 3px solid #003a7d; }
.msg-received { align-items: flex-end; }
</style>

<script>
// Filtre de recherche dans la modale "Nouveau message"
document.getElementById('search-contact')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.contact-item').forEach(function(item) {
        item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>

<?php include 'includes/footer.php'; ?>
