<?php
/**
 * Admin payment methods: create/edit/delete, enable/disable, sort order,
 * dynamic custom fields (defined as JSON).
 * @package MTASK\Admin
 * @var array $admin
 */
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        // Validate the dynamic fields JSON.
        $fieldsRaw = trim((string) ($_POST['fields'] ?? '[]'));
        $fields = json_decode($fieldsRaw, true);
        if (!is_array($fields)) {
            flash('error', 'Custom fields must be valid JSON array.');
            header('Location: index.php?page=payments');
            exit;
        }
        $data = [
            'name'       => trim((string) ($_POST['name'] ?? '')),
            'code'       => preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_POST['code'] ?? ''))),
            'icon'       => trim((string) ($_POST['icon'] ?? 'bi-wallet2')),
            'min_amount' => max(0, (int) ($_POST['min_amount'] ?? 0)),
            'fields'     => json_encode($fields),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'status'     => ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'disabled',
        ];
        if ($data['name'] === '' || $data['code'] === '') {
            flash('error', 'Name and code are required.');
        } elseif ($id > 0) {
            Database::update('payment_methods', $data, 'id = :id', ['id' => $id]);
            Logger::audit((int) $admin['id'], 'payment.update', "Updated method #{$id}");
            flash('success', 'Payment method updated.');
        } else {
            try {
                Database::insert('payment_methods', $data + ['created_at' => date('Y-m-d H:i:s')]);
                Logger::audit((int) $admin['id'], 'payment.create', "Created method {$data['code']}");
                flash('success', 'Payment method created.');
            } catch (Throwable $e) {
                flash('error', 'Code must be unique.');
            }
        }
    } elseif ($action === 'toggle') {
        $m = Database::fetch('SELECT status FROM payment_methods WHERE id = ?', [$id]);
        if ($m) { Database::update('payment_methods', ['status' => $m['status'] === 'active' ? 'disabled' : 'active'], 'id = :id', ['id' => $id]); flash('success', 'Status updated.'); }
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM payment_methods WHERE id = ?', [$id]);
        Logger::audit((int) $admin['id'], 'payment.delete', "Deleted method #{$id}");
        flash('warn', 'Payment method deleted.');
    }
    header('Location: index.php?page=payments');
    exit;
}

$methods = Database::fetchAll('SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC');
$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? Database::fetch('SELECT * FROM payment_methods WHERE id = ?', [$editId]) : null;

adminHeader('Payment Methods', 'payments', $admin);
?>
<div class="row g-3">
    <div class="col-lg-5"><div class="card"><div class="card-body">
        <h6 class="fw-bold mb-3"><?= $edit ? 'Edit Method' : 'Add Method' ?></h6>
        <form method="post" action="index.php?page=payments">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="mb-2"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= Security::e($edit['name'] ?? '') ?>" required></div>
            <div class="row">
                <div class="col-6 mb-2"><label class="form-label">Code (unique)</label><input class="form-control" name="code" value="<?= Security::e($edit['code'] ?? '') ?>" <?= $edit ? 'readonly' : '' ?> required></div>
                <div class="col-6 mb-2"><label class="form-label">Icon (bootstrap)</label><input class="form-control" name="icon" value="<?= Security::e($edit['icon'] ?? 'bi-wallet2') ?>"></div>
            </div>
            <div class="row">
                <div class="col-6 mb-2"><label class="form-label">Min amount (MT)</label><input class="form-control" type="number" name="min_amount" value="<?= (int) ($edit['min_amount'] ?? 20000) ?>"></div>
                <div class="col-6 mb-2"><label class="form-label">Sort order</label><input class="form-control" type="number" name="sort_order" value="<?= (int) ($edit['sort_order'] ?? 0) ?>"></div>
            </div>
            <div class="mb-2"><label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="active" <?= ($edit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="disabled" <?= ($edit['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Custom fields (JSON)</label>
                <textarea class="form-control" name="fields" rows="5" style="font-family:monospace;font-size:13px"><?= Security::e($edit['fields'] ?? '[{"name":"account","label":"Account","type":"text","required":true}]') ?></textarea>
                <small class="text-muted">Array of {name, label, type, required}. These render as inputs in the Mini App.</small>
            </div>
            <button class="btn btn-purple w-100"><?= $edit ? 'Update' : 'Create' ?></button>
            <?php if ($edit): ?><a class="btn btn-light w-100 mt-2" href="index.php?page=payments">Cancel</a><?php endif; ?>
        </form>
    </div></div></div>
    <div class="col-lg-7"><div class="card"><div class="card-body">
        <h6 class="fw-bold mb-3">Methods (<?= count($methods) ?>)</h6>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>#</th><th>Name</th><th>Code</th><th>Min</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($methods as $m): ?>
                <tr>
                    <td><i class="bi <?= Security::e($m['icon']) ?>"></i></td>
                    <td class="fw-semibold"><?= Security::e($m['name']) ?></td>
                    <td><code><?= Security::e($m['code']) ?></code></td>
                    <td><?= mt((int) $m['min_amount']) ?></td>
                    <td><span class="badge-st st-<?= $m['status'] === 'active' ? 'active' : 'disabled' ?>"><?= Security::e($m['status']) ?></span></td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-light" href="index.php?page=payments&edit=<?= (int) $m['id'] ?>"><i class="bi bi-pencil"></i></a>
                        <form class="d-inline" method="post" action="index.php?page=payments"><?= Security::csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm btn-light"><i class="bi bi-power"></i></button></form>
                        <form class="d-inline" method="post" action="index.php?page=payments" onsubmit="return confirm('Delete method?')"><?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$methods): ?><tr><td colspan="6" class="text-muted">No methods.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div></div></div>
</div>
<?php adminFooter(); ?>
