<?php
require_once '../../config/database.php';
require_once '../../helpers/auth.php';
require_once '../../helpers/common.php';
require_once '../../helpers/audit.php';
requireRole(['superadmin']);
verifyCsrf();

$departments = [
    'Production (Assy, TR, PK)',
    'Painting', 'MS1', 'MS2', 'CR',
    'QC Incoming', 'QC Inhouse', 'QC Product', 'Picking Yard',
];

$routing = $_POST['routing'] ?? [];

try {
    $pdo->beginTransaction();

    // Delete all existing routing
    $pdo->exec("DELETE FROM department_managers");
    $pdo->exec("DELETE FROM department_qc");

    $insertMgr = $pdo->prepare("INSERT IGNORE INTO department_managers (department, manager_id) VALUES (?, ?)");
    $insertQc  = $pdo->prepare("INSERT IGNORE INTO department_qc (department, qc_id) VALUES (?, ?)");

    foreach ($departments as $dept) {
        $managers = $routing[$dept]['manager'] ?? [];
        $qcs      = $routing[$dept]['qc']      ?? [];

        foreach ($managers as $mgrId) {
            $insertMgr->execute([$dept, (int)$mgrId]);
        }
        foreach ($qcs as $qcId) {
            $insertQc->execute([$dept, (int)$qcId]);
        }
    }

    $pdo->commit();
    writeAuditLog($pdo, 'ROUTING_UPDATED', 'Update routing approval department');
    header('Location: index.php?success=1');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: index.php?error=' . urlencode($e->getMessage()));
    exit;
}