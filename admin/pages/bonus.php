<?php
/**
 * Admin daily bonus management (7-day reward ladder + enable/disable).
 * @package MTASK\Admin
 * @var array $admin
 */
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Settings::set('daily_bonus_enabled', isset($_POST['daily_bonus_enabled']) ? '1' : '0');
    $rewards = $_POST['reward'] ?? [];
    foreach ($rewards as $day => $val) {
        $day = (int) $day;
        $val = max(0, (int) $val);
        if ($day >= 1 && $day <= 31) {
            Database::query(
                'INSERT INTO daily_bonus_rewards (`day`,`reward`) VALUES (?,?) ON DUPLICATE KEY UPDATE `reward` = VALUES(`reward`)',
                [$day, $val]
            );
        }
    }
    Logger::audit((int) $admin['id'], 'bonus.update', 'Updated daily bonus ladder');
    flash('success', 'Daily bonus settings saved.');
    header('Location: index.php?page=bonus');
    exit;
}

$ladder = Database::fetchAll('SELECT day, reward FROM daily_bonus_rewards ORDER BY day ASC');
adminHeader('Daily Bonus', 'bonus', $admin);
?>
<div class="card"><div class="card-body">
    <form method="post" action="index.php?page=bonus">
        <?= Security::csrfField() ?>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="daily_bonus_enabled" id="dbe" <?= Settings::getBool('daily_bonus_enabled') ? 'checked' : '' ?>>
            <label class="form-check-label" for="dbe">Daily bonus enabled</label>
        </div>
        <p class="text-muted small">A missed day resets the user's streak back to Day 1. After completing the final day, the ladder restarts.</p>
        <div class="row g-2">
            <?php foreach ($ladder as $row): ?>
                <div class="col-6 col-md-3">
                    <label class="form-label">Day <?= (int) $row['day'] ?> reward (MT)</label>
                    <input class="form-control" type="number" name="reward[<?= (int) $row['day'] ?>]" value="<?= (int) $row['reward'] ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-purple mt-3">Save Ladder</button>
    </form>
</div></div>
<?php adminFooter(); ?>
