<?php
// transactions_log.php - سجل العمليات (نسخة شركة واحدة)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once 'db_connect.php'; 
include_once 'functions.php'; 

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'موظف';
$user_permissions = $_SESSION['user_permissions'] ?? [];

$is_admin = (trim($user_role) == 'مدير عام');
$has_permission = is_array($user_permissions) && in_array('invoices_log', $user_permissions);

if (!$user_id || (!$is_admin && !$has_permission)) {
    header("Location: index.php?error=no_permission");
    exit;
}

$pageTitle = 'سجل جميع عمليات الصرف والفواتير';
$default_branch_id = $_SESSION['branch_id'] ?? 0;

if ($is_admin && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['switch_branch_id'])) {
    $_SESSION['operating_branch_id'] = (int)$_POST['switch_branch_id'];
    header("Location: transactions_log.php");
    exit();
}

$target_branch_id = ($is_admin) ? ($_SESSION['operating_branch_id'] ?? 0) : $default_branch_id;

$all_branches = [];
if ($is_admin) {
    $all_branches[0] = 'جميع الفروع (العرض الشامل)';
}

$stmt_br = $conn->prepare("SELECT id, name FROM branches ORDER BY id ASC");
$stmt_br->execute();
$res_br = $stmt_br->get_result();
while ($row = $res_br->fetch_assoc()) {
    $all_branches[$row['id']] = $row['name'];
}
$stmt_br->close();

$base_query = "SELECT t.*, c.currency_code 
               FROM transactions t 
               LEFT JOIN currencies c ON t.from_currency_id = c.id";

if ($target_branch_id == 0) {
    $query = $base_query . " ORDER BY t.transaction_date DESC LIMIT 500";
    $stmt = $conn->prepare($query);
} else {
    $query = $base_query . " WHERE t.branch_id = ? ORDER BY t.transaction_date DESC LIMIT 500";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $target_branch_id);
}

$stmt->execute();
$result = $stmt->get_result();

$column_titles = ['الرقم', 'التاريخ', 'النوع', 'العميل', 'المبلغ', 'العملة', 'السعر', 'الصافي', 'الدفع', 'الإجراءات'];
if ($is_admin) array_unshift($column_titles, 'الفرع'); 

include 'header.php'; 
?>

<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/all.min.css">

<style>
    .container-main { width: 100%; padding: 25px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-top: 20px; border-top: 5px solid #17a2b8; }
    h2 { color: #2c3e50; font-weight: bold; margin-bottom: 20px; }
    .table-log { width: 100%; border-collapse: collapse; background: #fff; }
    .table-log thead { background: #17a2b8; color: #fff; }
    .table-log th, .table-log td { padding: 12px 10px; border: 1px solid #eee; text-align: center; font-size: 0.9rem; }
    .table-log tbody tr:hover { background-color: #f9f9f9; }
    .branch-selector { margin-bottom: 20px; padding: 15px; background: #f1f8f9; border-radius: 8px; }
    .net-amount { color: #d9534f; font-weight: bold; font-size: 1rem; }
    .badge-serial { background: #f8f9fa; border: 1px solid #ddd; color: #333; padding: 5px 10px; border-radius: 4px; }
    .action-btns { display: flex; gap: 8px; justify-content: center; }
</style>

<div class="container-main" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-history text-info ml-2"></i> سجل جميع عمليات الصرف</h2>
        <span class="text-muted small">إجمالي العمليات: <?php echo $result->num_rows; ?></span>
    </div>

    <?php if ($is_admin) : ?>
    <div class="branch-selector shadow-sm border-right">
        <form method="POST" class="form-inline">
            <label class="ml-3 font-weight-bold"><i class="fas fa-filter text-info ml-1"></i> تصفية حسب الفرع:</label>
            <select name="switch_branch_id" onchange="this.form.submit()" class="form-control" style="min-width: 300px;">
                <?php foreach ($all_branches as $id => $name) : ?>
                    <option value="<?php echo $id; ?>" <?php echo ($id == $target_branch_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
    <?php if ($result && $result->num_rows > 0) : ?>
        <table class="table-log table-hover shadow-sm">
            <thead>
                <tr>
                    <?php foreach ($column_titles as $title): ?>
                        <th><?php echo $title; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) : 
                    $type_class = ($row['transaction_type'] == 'بيع') ? 'badge-success' : 'badge-warning';
                    $display_currency = !empty($row['currency_code']) ? $row['currency_code'] : '---';
                ?>
                <tr>
                    <?php if ($is_admin) : ?>
                        <td class="font-weight-bold text-info"><?php echo $all_branches[$row['branch_id']] ?? 'فرع مجهول'; ?></td>
                    <?php endif; ?>
                    <td><span class="badge-serial"><?php echo $row['serial_number']; ?></span></td>
                    <td class="small"><?php echo date('Y-m-d H:i', strtotime($row['transaction_date'])); ?></td>
                    <td><span class="badge <?php echo $type_class; ?> px-3 py-2"><?php echo $row['transaction_type']; ?></span></td>
                    <td class="font-weight-bold"><?php echo htmlspecialchars($row['client_name'] ?? '---'); ?></td>
                    <td class="font-weight-bold"><?php echo number_format($row['amount_foreign'], 2); ?></td>
                    <td><span class="badge badge-dark"><?php echo htmlspecialchars($display_currency); ?></span></td>
                    <td><?php echo number_format($row['rate_used'], 4); ?></td>
                    <td class="net-amount"><?php echo number_format($row['net_amount'], 2); ?> ل.د</td>
                    <td><?php echo htmlspecialchars($row['payment_method'] ?? 'نقداً'); ?></td>
                    <td>
                        <div class="action-btns">
                            <a href="print_invoice.php?serial=<?php echo $row['serial_number']; ?>" class="btn btn-sm btn-outline-success" target="_blank" title="طباعة الفاتورة">
                                <i class="fas fa-print"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else : ?>
        <div class="alert alert-light text-center py-5 border">
            <h4 class="text-muted">لا توجد عمليات مسجلة حالياً</h4>
        </div>
    <?php endif; ?>
    </div>
</div>

<?php 
include 'footer.php'; 
if (isset($conn)) $conn->close(); 
?>