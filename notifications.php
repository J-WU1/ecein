<?php
// ============================================================
//  notifications.php — Centre de notifications
// ============================================================
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$pageTitle = "Notifications";
$userId    = (int) $_SESSION['user_id'];

// Marque toutes les notifications comme lues à l'ouverture de la page
$stmtMark = $conn->prepare("UPDATE notifications SET lu = 1 WHERE user_id = ?");
$stmtMark->bind_param("i", $userId);
$stmtMark->execute();

// Récupère toutes les notifications (30 dernières)
$stmtNotifs = $conn->prepare("
    SELECT * FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 30
");
$stmtNotifs->bind_param("i", $userId);
$stmtNotifs->execute();
$notifications = $stmtNotifs->get_result()->fetch_all(MYSQLI_ASSOC);

// Icônes et couleurs selon le type de notification
function notifStyle($type) {
    $map = [
        'like'           => ['icon' => 'bi-hand-thumbs-up-fill', 'color' => 'text-primary',  'bg' => 'bg-primary'],
        'love'           => ['icon' => 'bi-heart-fill',           'color' => 'text-danger',   'bg' => 'bg-danger'],
        'bravo'          => ['icon' => 'bi-emoji-smile-fill',     'color' => 'text-warning',  'bg' => 'bg-warning'],
        'comment'        => ['icon' => 'bi-chat-fill',            'color' => 'text-success',  'bg' => 'bg-success'],
        'friend_request' => ['icon' => 'bi-person-plus-fill',     'color' => 'text-info',     'bg' => 'bg-info'],
        'friend_accept'  => ['icon' => 'bi-person-check-fill',    'color' => 'text-success',  'bg' => 'bg-success'],
        'message'        => ['icon' => 'bi-chat-dots-fill',       'color' => 'text-primary',  'bg' => 'bg-primary'],
        'job'            => ['icon' => 'bi-briefcase-fill',       'color' => 'text-secondary','bg' => 'bg-secondary'],
        'event'          => ['icon' => 'bi-calendar-event-fill',  'color' => 'text-warning',  'bg' => 'bg-warning'],
    ];
    return $map[$type] ?? ['icon' => 'bi-bell-fill', 'color' => 'text-muted', 'bg' => 'bg-secondary'];
}

include 'includes/header.php';
?>

<div class="container mt-4" style="max-width:700px;">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">
            <i class="bi bi-bell-fill text-ece me-2"></i>Notifications
        </h5>
        <?php if (!empty($notifications)): ?>
        <a href="notifications.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-check2-all me-1"></i>Tout marquer comme lu
        </a>
        <?php endif; ?>
    </div>

    <div class="card">
        <?php if (empty($notifications)): ?>
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-bell-slash fs-1 d-block mb-3"></i>
            <p>Vous n'avez aucune notification pour le moment.</p>
        </div>
        <?php else: ?>

        <?php
        // Groupe les notifications par date
        $grouped = [];
        foreach ($notifications as $n) {
            $day = date('Y-m-d', strtotime($n['created_at']));
            $grouped[$day][] = $n;
        }

        $labels = [
            date('Y-m-d')                           => "Aujourd'hui",
            date('Y-m-d', strtotime('-1 day'))       => 'Hier',
            date('Y-m-d', strtotime('-2 days'))      => 'Il y a 2 jours',
        ];
        ?>

        <?php foreach ($grouped as $day => $items): ?>
            <div class="px-3 pt-3 pb-1">
                <small class="text-muted fw-semibold text-uppercase" style="font-size:0.7rem; letter-spacing:1px;">
                    <?= $labels[$day] ?? date('d/m/Y', strtotime($day)) ?>
                </small>
            </div>

            <?php foreach ($items as $notif):
                $style = notifStyle($notif['type']);
                $link  = $notif['lien'] ? htmlspecialchars($notif['lien']) : '#';
            ?>
            <a href="<?= $link ?>"
               class="d-flex align-items-center gap-3 px-3 py-3 border-bottom text-decoration-none
                      <?= $notif['lu'] ? '' : 'bg-light' ?> notif-item">

                <!-- Icône du type -->
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0
                            <?= $style['bg'] ?> bg-opacity-10"
                     style="width:44px; height:44px;">
                    <i class="bi <?= $style['icon'] ?> <?= $style['color'] ?> fs-5"></i>
                </div>

                <!-- Contenu -->
                <div class="flex-grow-1">
                    <div class="text-dark small <?= $notif['lu'] ? '' : 'fw-semibold' ?>">
                        <?= e($notif['contenu']) ?>
                    </div>
                    <small class="text-muted">
                        <?= date('H:i', strtotime($notif['created_at'])) ?>
                    </small>
                </div>

                <!-- Point bleu si non lu -->
                <?php if (!$notif['lu']): ?>
                <div class="rounded-circle bg-primary flex-shrink-0"
                     style="width:10px; height:10px;"></div>
                <?php endif; ?>

            </a>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <?php endif; ?>
    </div>

</div>

<style>
.notif-item:hover { background: #f0f2f5 !important; transition: background 0.15s; }
</style>

<?php include 'includes/footer.php'; ?>
