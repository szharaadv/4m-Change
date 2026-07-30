<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
requirePermission('changes.create');
// Show the "Approval Info" block to users who can act as approvers.
$is_manager = canApproveAny();
include '../../templates/header.php';
include '../../templates/navbar.php';

?>

<div class="page-header">
    <div>
        <div class="page-title">New 4M Change</div>
        <div class="page-sub">Create a new change request</div>
    </div>
    <a href="index.php" class="btn">Cancel</a>
</div>

<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger"><?= e($_GET['error']) ?></div>
<?php endif; ?>

<form action="store.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= csrfToken() ?>">

    <!-- 4M Category -->
    <div class="form-section">
        <div class="section-title"><span class="section-dot"></span>4M Category</div>
        <div class="cat-group">
            <?php foreach (['Man','Material','Method','Machine'] as $cat): ?>
            <label>
                <input type="radio" name="category_4m" value="<?= $cat ?>" required style="display:none">
                <span class="cat-btn" onclick="this.closest('.cat-group').querySelectorAll('.cat-btn').forEach(b=>b.classList.remove('active'));this.classList.add('active');this.previousElementSibling.click()"><?= $cat ?></span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Basic Info -->
    <div class="form-section">
        <div class="section-title"><span class="section-dot"></span>Basic Info</div>
        <div class="d-grid grid-2 gap-16">
            <div>
                <label class="form-label">PIC</label>
                <input type="text" class="form-control" value="<?= e(currentUser()['name']) ?>" disabled style="background:#f5f5f5;color:var(--muted)">
                <input type="hidden" name="pic_id" value="<?= currentUserId() ?>">
                <div class="form-hint" style="margin-top:4px">Automatically filled with your name.</div>
            </div>
            <div>
                <label class="form-label">Part No</label>
                <input type="text" name="part_no" class="form-control" placeholder="">
            </div>
            <div>
                <label class="form-label">Implement Location <span class="required">*</span></label>
                <input type="text" name="implement_location" class="form-control" required>
            </div>
            <div>
                <label class="form-label">Part Name <span class="required">*</span></label>
                <input type="text" name="part_name" class="form-control" required>
            </div>
            <div>
                <label class="form-label">Model <span class="required">*</span></label>
                <input type="text" name="model" class="form-control" required>
            </div>
            <div>
                <label class="form-label">Start Lot / Prod / Serial Eng</label>
                <input type="text" name="start_lot_serial" class="form-control">
            </div>
        </div>
    </div>

    <!-- Historical Upload Option -->
    <div class="form-section">
        <div class="section-title"><span class="section-dot"></span>Historical Upload</div>
        <label style="display:flex; align-items:center; gap:12px; cursor:pointer; padding:12px; background:#f9fafb; border-radius:6px; border:1px solid #e5e7eb;">
            <input type="checkbox" id="isHistorical" name="is_historical" value="1" style="width:18px; height:18px; cursor:pointer;">
            <div>
                <div style="font-weight:600; color:#1f2937; font-size:14px;">This is an upload of an existing (historical) document</div>
                <div style="font-size:13px; color:#6b7280; margin-top:4px;">Check this if uploading a document/change that has already been completed. Status will automatically become Closed without requiring approval.</div>
            </div>
        </label>
        <div id="dateSection" style="margin-top:16px; display:none;">
            <label class="form-label">Change Date <span class="required">*</span></label>
            <input type="date" id="changeDate" name="change_date" class="form-control">
            <div class="form-hint">Date the change was made</div>
        </div>
    </div>

    <script>
    document.getElementById('isHistorical').addEventListener('change', function() {
        const dateSection = document.getElementById('dateSection');
        const changeDateInput = document.getElementById('changeDate');
        if (this.checked) {
            dateSection.style.display = 'block';
            changeDateInput.required = true;
            changeDateInput.value = new Date().toISOString().split('T')[0];
        } else {
            dateSection.style.display = 'none';
            changeDateInput.required = false;
        }
    });
    </script>

    <!-- Change Detail -->
    <div class="form-section">
        <div class="section-title"><span class="section-dot"></span>Change Detail</div>
        <div class="d-grid grid-2 gap-16">
            <div>
                <label class="form-label">4M Change Item <span class="required">*</span></label>
                <textarea name="change_item" class="form-control" required placeholder="Description of the item being changed..."></textarea>
            </div>
            <div>
                <label class="form-label">Action <span class="required">*</span></label>
                <textarea name="action_plan" class="form-control" required placeholder="Action taken..."></textarea>
            </div>
            <div>
                <label class="form-label">Before (condition before)</label>
                <textarea name="before_desc" class="form-control" placeholder="Condition before the change..."></textarea>
            </div>
            <div>
                <label class="form-label">After (condition after)</label>
                <textarea name="after_desc" class="form-control" placeholder="Condition after the change..."></textarea>
            </div>
        </div>
    </div>

    <!-- Approval Info -->
    <?php if ($is_manager): ?>
    <div class="form-section">
        <div class="section-title"><span class="section-dot"></span>Approval Info</div>
        <div class="d-grid grid-2 gap-16">
            <div>
                <label class="form-label">Judge Status <span style="font-weight:400;color:var(--muted)">(historical info)</span></label>
                <div class="radio-row">
                    <?php foreach (['OK','NG','Pending'] as $j): ?>
                    <label class="radio-opt">
                        <input type="radio" name="judge_status" value="<?= $j ?>" <?= $j === 'Pending' ? 'checked' : '' ?> required>
                        <?= $j ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="form-hint">Filled in by the submitter as historical data — does not affect the approval process.</div>
            </div>
            <div>
                <label class="form-label">Confirm Customer</label>
                <div class="radio-row">
                    <?php foreach (['Need','Not Need'] as $c): ?>
                    <label class="radio-opt">
                        <input type="radio" name="confirm_customer" value="<?= $c ?>" <?= $c === 'Not Need' ? 'checked' : '' ?> required>
                        <?= $c ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="full-col">
                <label class="form-label">Evidence Note</label>
                <textarea name="evidence_note" class="form-control" style="min-height:70px" placeholder="Evidence note / additional remarks..."></textarea>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Evidence Photos -->
    <div class="form-section">
        <div class="section-title"><span class="section-dot"></span>Evidence Photos</div>
        <div class="d-grid grid-2 gap-16">
            <div>
                <label class="form-label">Before Photo</label>
                <div class="upload-zone">
                    <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor" style="display:block;margin:0 auto 6px;opacity:0.4"><path d="M.5 9.9a.5.5 0 01.5.5v2.5a1 1 0 001 1h12a1 1 0 001-1v-2.5a.5.5 0 011 0v2.5a2 2 0 01-2 2H2a2 2 0 01-2-2v-2.5a.5.5 0 01.5-.5z"/><path d="M7.646 1.146a.5.5 0 01.708 0l3 3a.5.5 0 01-.708.708L8.5 2.707V11.5a.5.5 0 01-1 0V2.707L5.354 4.854a.5.5 0 11-.708-.708l3-3z"/></svg>
                    <span id="beforeLabel">Click to upload</span>
                    <div class="form-hint" style="margin-top:4px">JPG, PNG — max 5MB</div>
                    <input type="file" name="before_photo" accept="image/*" data-preview="beforePreview" data-label="beforeLabel">
                </div>
                <img id="beforePreview" class="upload-preview">
            </div>
            <div>
                <label class="form-label">After Photo</label>
                <div class="upload-zone">
                    <svg width="20" height="20" viewBox="0 0 16 16" fill="currentColor" style="display:block;margin:0 auto 6px;opacity:0.4"><path d="M.5 9.9a.5.5 0 01.5.5v2.5a1 1 0 001 1h12a1 1 0 001-1v-2.5a.5.5 0 011 0v2.5a2 2 0 01-2 2H2a2 2 0 01-2-2v-2.5a.5.5 0 011 0v2.5a2 2 0 01-2 2H2a2 2 0 01-2-2v-2.5a.5.5 0 01.5-.5z"/><path d="M7.646 1.146a.5.5 0 01.708 0l3 3a.5.5 0 01-.708.708L8.5 2.707V11.5a.5.5 0 01-1 0V2.707L5.354 4.854a.5.5 0 11-.708-.708l3-3z"/></svg>
                    <span id="afterLabel">Click to upload</span>
                    <div class="form-hint" style="margin-top:4px">JPG, PNG — max 5MB</div>
                    <input type="file" name="after_photo" accept="image/*" data-preview="afterPreview" data-label="afterLabel">
                </div>
                <img id="afterPreview" class="upload-preview">
            </div>
            <div class="full-col">
                <label class="form-label">Other Attachments</label>
                <input type="file" name="attachments[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx">
                <div class="form-hint">JPG, PNG, PDF, DOC, XLS — max 5MB per file</div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-8" style="justify-content:flex-end;padding-bottom:8px">
        <a href="index.php" class="btn">Cancel</a>
        <button type="submit" name="workflow_status" value="Draft" class="btn" id="draftBtn">Save Draft</button>
        <button type="submit" name="workflow_status" value="Submitted" class="btn btn-success" id="submitBtn">Submit</button>
    </div>

    <script>
    document.getElementById('isHistorical').addEventListener('change', function() {
        const draftBtn = document.getElementById('draftBtn');
        const submitBtn = document.getElementById('submitBtn');
        if (this.checked) {
            draftBtn.style.display = 'none';
            submitBtn.textContent = 'Save';
            submitBtn.setAttribute('name', 'workflow_status');
            submitBtn.setAttribute('value', 'Closed');
        } else {
            draftBtn.style.display = 'inline-block';
            submitBtn.textContent = 'Submit';
            submitBtn.setAttribute('value', 'Submitted');
        }
    });
    </script>

</form>

<script>
// Activate first category button on load
document.querySelector('.cat-btn')?.classList.add('active');
document.querySelector('input[name="category_4m"]')?.setAttribute('checked', true);
</script>

<?php include '../../templates/footer.php'; ?>
