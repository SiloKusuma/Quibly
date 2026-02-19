<?php
$root_path = ''; $api_path = 'api/'; $home_url = 'index.php';
$current_page = 'home'; $page_title = 'Home';
require 'includes/config.php';
require 'includes/functions.php';
require 'includes/auth_handler.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    if (isset($_POST['create_post'])) {
        $censored = censorMessage($_POST['content']);
        $stmt = $db->prepare('INSERT INTO posts (user_id, content, image_url) VALUES (:u,:c,:i)');
        $stmt->bindValue(':u', (int)$_SESSION['user_id']);
        $stmt->bindValue(':c', $censored['text']);
        $stmt->bindValue(':i', $_POST['image_url'] ?? '');
        $stmt->execute();
        header('Location: index.php'); exit;
    }
    if (isset($_POST['create_story'])) {
        $stmt = $db->prepare('INSERT INTO stories (user_id, image_url) VALUES (:u,:i)');
        $stmt->bindValue(':u', (int)$_SESSION['user_id']);
        $stmt->bindValue(':i', $_POST['story_image_url']);
        $stmt->execute();
        header('Location: index.php'); exit;
    }
}

$sts = null;
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $db->prepare('SELECT stories.*, users.username FROM stories JOIN users ON stories.user_id=users.id JOIN follows ON stories.user_id=follows.following_id WHERE follows.follower_id=:u AND stories.created_at>=datetime("now","-1 day") GROUP BY stories.user_id ORDER BY stories.created_at DESC');
    $stmt->bindValue(':u', $uid, SQLITE3_INTEGER);
    $sts = $stmt->execute();
}

require 'includes/header.php';
?>

<?php if (isset($_SESSION['user_id'])): ?>
<div class="compose-area d-none d-sm-block">
    <div class="d-flex gap-3">
        <div class="modern-avatar"><?= getAvatarHtml($_SESSION['username'], $_SESSION['avatar_url'] ?? '', 48) ?></div>
        <div class="flex-grow-1">
            <form method="POST">
                <textarea name="content" class="compose-input" placeholder="What's on your mind? Use #hashtag or @mention" required></textarea>
                <input type="text" name="image_url" class="form-control form-control-sm border-0 bg-light mb-2" placeholder="Image URL (Optional)">
                <div class="d-flex justify-content-between align-items-center border-top pt-2">
                    <span class="text-muted small"><i class="bi bi-hash"></i> Use hashtags</span>
                    <button name="create_post" class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm">Post</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="story-container">
    <div class="story-circle" onclick="<?= isset($_SESSION['user_id']) ? "new bootstrap.Modal(document.getElementById('addStory')).show()" : "showAuthModal()" ?>">
        <div class="story-ring d-flex align-items-center justify-content-center bg-light"><i class="bi bi-plus-lg text-dark"></i></div>
        <span class="small">Story</span>
    </div>
    <?php if ($sts) while ($s = $sts->fetchArray(SQLITE3_ASSOC)): ?>
    <div class="story-circle" onclick="openStory('<?= htmlspecialchars($s['image_url']) ?>','<?= htmlspecialchars($s['username']) ?>')">
        <div class="story-ring"><img src="<?= htmlspecialchars($s['image_url']) ?>" loading="lazy"></div>
        <span class="small text-truncate d-block">@<?= htmlspecialchars($s['username']) ?></span>
    </div>
    <?php endwhile; ?>
</div>

<div id="feed-container">
<?php
$ps = $db->query('SELECT posts.*, users.username, users.role, users.avatar_url FROM posts JOIN users ON posts.user_id=users.id ORDER BY posts.created_at DESC LIMIT 10');
while ($p = $ps->fetchArray(SQLITE3_ASSOC)) echo renderPostHtml($p, $db);
?>
</div>
<div id="loading-trigger" class="text-center py-5"><div class="spinner-border spinner-border-sm text-dark"></div></div>

<?php require 'includes/footer.php'; ?>
