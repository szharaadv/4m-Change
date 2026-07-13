<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
requireRole(['admin','manager']);
include '../../templates/header.php';
include '../../templates/navbar.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM change_requests WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) die('Data not found.');
if ($data['workflow_status'] !== 'Rejected') {
    header('Location: detail.php?id=' . $id);
    exit;
}

$users = $pdo->query("SELECT id, name FROM users WHERE role IN ('admin','qc_prod') ORDER BY name ASC")->fetchAll();
?>

<div class="page-header">
    <div>
        <div class="page-title">Edit & Resubmit</div>
        <div class="page-sub mono"><?= e($data['change_no']) ?></div>
    </div>
    <a href="detail.php?id=<?= $id ?>" class="btn">Cancel</a>
</div>

<?php if ($data['rejection_note']): ?>
<div class="rejection-banner mb-16">
    <div class="title">Rejection Reason</div>
    <div class="note"><?= e($data['rejection_note']) ?></div>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger"><?= e($_GET['error']) ?></div>
<?php endif; ?>

<form action="update.php" method="POST">
    <input type="hidden" name="id" value="<?= $data['id'] ?>">
    <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">

    <div class="form-section">
        <div class="section-title"><span class="section-dot"></span>4M Category</div>
        <div class="cat-group">
            <?php foreach (['Man','Material','Method','Machine'] as $cat): ?>
            <label>
                <input type="radio" name="category_4m" value="<?= $cat ?>" <?= $data['category_4m'] === $cat ? 'checked' : '' ?> required style="display:none">
                <span class="cat-btn <?= $data['category_4m'] === $cat ? 'active' : '' ?>"
                    onclick="this.closest('.cat-group').querySelectorAll('.cat-btn').forEach(b=>b.classList.remove('active'));this.classList.add('active');this.previousElementSibling.checked=true">
                    <?= $cat ?>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-section">
        <div class="section-title"><span class="section-dot"></span>Basic Info</div>
        <div class="d-grid grid-2 gap-16">
            <div>
                <label class="form-label">PIC <span class="required">*</span></label>
                <select name="pic_id" class="form-control" required>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $data['pic_id'] == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Part No</label>
                <input type="text" name="part_no" class="form-control" value="<?= e($data['part_no']) ?>">
            </div>
            <div>
                <label class="form-label">Implement Location <span class="required">*</span></label>
                <input type="text" name="implement_location" class="form-control" value="<?= e($data['implement_location']) ?>" required>
            </div>
            <div>
                <label class="form-label">Part Name <span class="required">*</span></label>
                <input type="text" name="part_name" class="form-control" value="<?= e($data['part_name']) ?>" required>
            </div>
            <div>
                <label class="form-label">Model <span class="required">*</span></label>
                <input type="text" name="model" class="form-control" value="<?= e($data['model']) ?>" required>
            </div>
            <div>
                <label class="form-label">Start Lot / Prod / Serial Eng</label>
                <input type="text" name="start_lot_serial" class="form-control" value="<?= e($data['start_lot_serial']) ?>">
            </div>
        </div>
    </div>

    <div class="form-section">
        <div class="section-title"><span class="section-dot"></span>Change Detail</div>
        <div class="d-grid grid-2 gap-16">
            <div>
                <label class="form-label">4M Change Item <span class="required">*</span></label>
                <textarea name="change_item" class="form-control" required><?= e($data['change_item']) ?></textarea>
            </div>
            <div>
                <label class="form-label">Action <span class="required">*</span></label>
                <textarea name="action_plan" class="form-control" required><?= e($data['action_plan']) ?></textarea>
            </div>
            <div>
                <label class="form-label">Before</label>
                <textarea name="before_desc" class="form-control"><?= e($data['before_desc']) ?></textarea>
            </div>
            <div>
                <label class="form-label">After</label>
                <textarea name="after_desc" class="form-control"><?= e($data['after_desc']) ?></textarea>
            </div>
        </div>
    </div>

    <div class="form-section">
        <div class="section-title"><span class="section-dot"></span>Approval Info</div>
        <div class="d-grid grid-2 gap-16">
            <div>
                <label class="form-label">Judge Status</label>
                <div class="radio-row">
                    <?php foreach (['OK','NG','Pending'] as $j): ?>
                    <label class="radio-opt">
                        <input type="radio" name="judge_status" value="<?= $j ?>" <?= $data['judge_status'] === $j ? 'checked' : '' ?>>
                        <?= $j ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <label class="form-label">Confirm Customer</label>
                <div class="radio-row">
                    <?php foreach (['Need','Not Need'] as $c): ?>
                    <label class="radio-opt">
                        <input type="radio" name="confirm_customer" value="<?= $c ?>" <?= $data['confirm_customer'] === $c ? 'checked' : '' ?>>
                        <?= $c ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="full-col">
                <label class="form-label">Evidence Note</label>
                <textarea name="evidence_note" class="form-control" style="min-height:70px"><?= e($data['evidence_note']) ?></textarea>
            </div>
        </div>
    </div>

    <div class="d-flex gap-8" style="justify-content:flex-end;padding-bottom:8px">
        <a href="detail.php?id=<?= $id ?>" class="btn">Cancel</a>
        <button type="submit" class="btn btn-success">Save & Resubmit</button>
    </div>
</form>

<?php include '../../templates/footer.php'; ?>
