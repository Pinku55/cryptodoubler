<?php
/**
 * Admin task management: create, edit, delete, enable/disable.
 * @package MTASK\Admin
 * @var array $admin
 */
declare(strict_types=1);

$categories = ['website','shortlink','telegram_channel','telegram_group','telegram_bot','instagram','facebook','twitter','youtube','survey','other'];

// ----- POST actions -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $data = [
            'title'         => trim((string) ($_POST['title'] ?? '')),
            'description'   => trim((string) ($_POST['description'] ?? '')),
            'category'      => in_array($_POST['category'] ?? '', $categories, true) ? $_POST['category'] : 'website',
            'url'           => trim((string) ($_POST['url'] ?? '')),
            'reward'        => max(0, (int) ($_POST['reward'] ?? 0)),
            'wait_time'     => max(0, (int) ($_POST['wait_time'] ?? 10)),
            'daily_limit'   => max(0, (int) ($_POST['daily_limit'] ?? 0)),
            'verify_type'   => in_array($_POST['verify_type'] ?? '', ['auto','timer','telegram_member','none'], true) ? $_POST['verify_type'] : 'timer',
            'verify_target' => trim((string) ($_POST['verify_target'] ?? '')),
            'status'        => ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'disabled',
        ];

        // Optional image upload.
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif'], true) && $_FILES['image']['size'] <= 2 * 1024 * 1024) {
                if (!is_dir(UPLOADS_PATH)) { @mkdir(UPLOADS_PATH, 0755, true); }
                $fname = 'task_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], UPLOADS_PATH . '/' . $fname)) {
                    $data['image'] = 'assets/uploads/' . $fname;
                }
            }
        }

        if ($data['title'] === '') {
            flash('error', 'Task title is required.');
        } elseif ($id > 0) {
            Database::update('tasks', $data, 'id = :id', ['id' => $id]);
            Logger::audit((int) $admin['id'], 'task.update', "Updated task #{$id}");
            flash('success', 'Task updated.');
        } else {
            $newId = Database::insert('tasks', $data + ['created_at' => date('Y-m-d H:i:s')]);
            Logger::audit((int) $admin['id'], 'task.create', "Created task #{$newId}");
            flash('success', 'Task created.');
        }
    } elseif ($action === 'toggle') {
        $t = Database::fetch('SELECT status FROM tasks WHERE id = ?', [$id]);
        if ($t) {
            $new = $t['status'] === 'active' ? 'disabled' : 'active';
            Database::update('tasks', ['status' => $new], 'id = :id', ['id' => $id]);
            flash('success', 'Task ' . ($new === 'active' ? 'enabled' : 'disabled') . '.');
        }
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM tasks WHERE id = ?', [$id]);
        Logger::audit((int) $admin['id'], 'task.delete', "Deleted task #{$id}");
        flash('warn', 'Task deleted.');
    }
    header('Location: index.php?page=tasks');
    exit;
}

$tasks = Database::fetchAll('SELECT * FROM tasks ORDER BY sort_order ASC, id DESC');
$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? Database::fetch('SELECT * FROM tasks WHERE id = ?', [$editId]) : null;

adminHeader('Tasks', 'tasks', $admin);
?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <h6 class="fw-bold mb-3"><?= $edit ? 'Edit Task' : 'Create Task' ?></h6>
            <form method="post" action="index.php?page=tasks" enctype="multipart/form-data">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" value="<?= Security::e($edit['title'] ?? '') ?>" required></div>
                <div class="mb-2"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"><?= Security::e($edit['description'] ?? '') ?></textarea></div>
                <div class="row">
                    <div class="col-6 mb-2"><label class="form-label">Category</label>
                        <select class="form-select" name="category">
                            <?php foreach ($categories as $c): ?><option value="<?= $c ?>" <?= ($edit['category'] ?? '') === $c ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $c)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 mb-2"><label class="form-label">Reward (MT)</label><input class="form-control" type="number" name="reward" value="<?= (int) ($edit['reward'] ?? 100) ?>"></div>
                </div>
                <div class="mb-2"><label class="form-label">URL</label><input class="form-control" name="url" value="<?= Security::e($edit['url'] ?? '') ?>" placeholder="https://"></div>
                <div class="row">
                    <div class="col-6 mb-2"><label class="form-label">Wait time (s)</label><input class="form-control" type="number" name="wait_time" value="<?= (int) ($edit['wait_time'] ?? 10) ?>"></div>
                    <div class="col-6 mb-2"><label class="form-label">Daily limit</label><input class="form-control" type="number" name="daily_limit" value="<?= (int) ($edit['daily_limit'] ?? 0) ?>"></div>
                </div>
                <div class="row">
                    <div class="col-6 mb-2"><label class="form-label">Verify</label>
                        <select class="form-select" name="verify_type">
                            <?php foreach (['timer','auto','telegram_member','none'] as $v): ?><option value="<?= $v ?>" <?= ($edit['verify_type'] ?? 'timer') === $v ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 mb-2"><label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?= ($edit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="disabled" <?= ($edit['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                        </select>
                    </div>
                </div>
                <div class="mb-2"><label class="form-label">Verify target (e.g. @channel)</label><input class="form-control" name="verify_target" value="<?= Security::e($edit['verify_target'] ?? '') ?>"></div>
                <div class="mb-3"><label class="form-label">Image (optional)</label><input class="form-control" type="file" name="image" accept="image/*"></div>
                <button class="btn btn-purple w-100"><?= $edit ? 'Update Task' : 'Create Task' ?></button>
                <?php if ($edit): ?><a href="index.php?page=tasks" class="btn btn-light w-100 mt-2">Cancel</a><?php endif; ?>
            </form>
        </div></div>
    </div>

    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <h6 class="fw-bold mb-3">All Tasks (<?= count($tasks) ?>)</h6>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Title</th><th>Category</th><th>Reward</th><th>Done</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($tasks as $t): ?>
                        <tr>
                            <td class="fw-semibold"><?= Security::e($t['title']) ?></td>
                            <td><small><?= Security::e(ucwords(str_replace('_', ' ', $t['category']))) ?></small></td>
                            <td><?= mt((int) $t['reward']) ?></td>
                            <td><?= (int) $t['completed_count'] ?></td>
                            <td><span class="badge-st st-<?= $t['status'] === 'active' ? 'active' : 'disabled' ?>"><?= Security::e($t['status']) ?></span></td>
                            <td class="text-nowrap">
                                <a class="btn btn-sm btn-light" href="index.php?page=tasks&edit=<?= (int) $t['id'] ?>"><i class="bi bi-pencil"></i></a>
                                <form class="d-inline" method="post" action="index.php?page=tasks"><?= Security::csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn btn-sm btn-light"><i class="bi bi-power"></i></button></form>
                                <form class="d-inline" method="post" action="index.php?page=tasks" onsubmit="return confirm('Delete task?')"><?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$tasks): ?><tr><td colspan="6" class="text-muted">No tasks yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>
<?php adminFooter(); ?>
