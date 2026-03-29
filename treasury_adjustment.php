<?php
// treasury_adjustment.php - إدارة أرصدة الخزائن (نسخة شركة واحدة)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once 'db_connect.php'; 
include_once 'functions.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); 
    exit;
}

$user_id = $_SESSION['user_id']; 
$user_role = $_SESSION['user_role'] ?? 'موظف'; 
$current_branch_id = $_SESSION['branch_id'] ?? 0;
$is_admin = (trim($user_role) === 'مدير عام');

$currencies_result = $conn->query("SELECT id, currency_code, currency_name_ar FROM currencies ORDER BY currency_code ASC");
$currencies_data = $currencies_result ? $currencies_result->fetch_all(MYSQLI_ASSOC) : [];

$branches_sql = "SELECT id, name FROM branches WHERE is_active = 1";
if (!$is_admin && $current_branch_id > 0) {
    $branches_sql .= " AND id = $current_branch_id";
}
$branches_result = $conn->query($branches_sql . " ORDER BY name ASC");
$branches_data = $branches_result ? $branches_result->fetch_all(MYSQLI_ASSOC) : [];

$message = ''; $message_type = '';
$is_view_only_request = isset($_POST['is_view_only']);

if ($_SERVER["REQUEST_METHOD"] == "POST" && !$is_view_only_request) { 
    $target_branch_id = intval($_POST['branch_id']); 
    $currency_id = intval($_POST['currency_id']);
    $amount = (float)$_POST['amount'];
    $adjustment_type = $_POST['adjustment_type']; 

    $check_curr = $conn->query("SELECT currency_code FROM currencies WHERE id = $currency_id");
    
    if ($check_curr->num_rows == 0 || $amount <= 0) {
        $message = "بيانات غير صالحة.";
        $message_type = 'danger';
    } else {
        $conn->begin_transaction();
        try {
            $currency_code = $check_curr->fetch_assoc()['currency_code'];
            $amount_change = ($adjustment_type == 'deposit') ? $amount : -$amount;
            $amount_in = ($adjustment_type == 'deposit') ? $amount : 0;
            $amount_out = ($adjustment_type == 'withdraw') ? $amount : 0;
            $trans_type = ($adjustment_type == 'deposit') ? 'CAPITAL_IN' : 'EXPENSE';

            $sql_trans = "INSERT INTO treasury_transactions (branch_id, transaction_type, currency_in_code, amount_in, currency_out_code, amount_out, user_id, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $st_trans = $conn->prepare($sql_trans);
            $st_trans->bind_param("issdsdi", $target_branch_id, $trans_type, $currency_code, $amount_in, $currency_code, $amount_out, $user_id);
            $st_trans->execute();

            $sql_bal = "INSERT INTO treasury_balances (branch_id, currency_id, current_balance, last_updated) 
                        VALUES (?, ?, ?, NOW()) 
                        ON DUPLICATE KEY UPDATE current_balance = current_balance + ?, last_updated = NOW()";
            $st_bal = $conn->prepare($sql_bal);
            $st_bal->bind_param("iidd", $target_branch_id, $currency_id, $amount_change, $amount_change);
            $st_bal->execute();

            $conn->commit();
            header("Location: treasury_adjustment.php?msg=تم تحديث الرصيد بنجاح&type=success&branch_id=" . $target_branch_id);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $message = "حدث خطأ: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

$display_branch_id = $is_admin ? (intval($_POST['view_branch_id'] ?? $_GET['branch_id'] ?? ($branches_data[0]['id'] ?? 0))) : $current_branch_id;

if ($display_branch_id > 0) {
    $bal_sql = "SELECT tb.current_balance, c.currency_code, c.currency_name_ar 
                FROM treasury_balances tb 
                JOIN currencies c ON tb.currency_id = c.id 
                WHERE tb.branch_id = $display_branch_id 
                ORDER BY c.currency_code ASC";
    $balances_data_after = $conn->query($bal_sql);
}

if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type'] ?? 'info');
}

$pageTitle = 'إدارة أرصدة الخزينة';
include 'header.php'; 
?>

<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/all.min.css">

<div class="container-fluid py-4" dir="rtl">
    <h1 class="text-dark mb-4"><i class="fas fa-wallet text-info ml-2"></i> إدارة الخزينة النقدية</h1>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> text-center"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-5 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white">تعديل رصيد يدوي</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group mb-3">
                            <label>الفرع:</label>
                            <select class="form-control" name="branch_id" required <?php if (!$is_admin) echo 'disabled'; ?>>
                                <?php foreach ($branches_data as $br): ?>
                                    <option value="<?php echo $br['id']; ?>" <?php echo ($br['id'] == $display_branch_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($br['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>العملة:</label>
                            <select class="form-control" name="currency_id" required>
                                <option value="">-- اختر العملة --</option>
                                <?php foreach ($currencies_data as $cur): ?>
                                    <option value="<?php echo $cur['id']; ?>"><?php echo htmlspecialchars($cur['currency_name_ar'])." (".$cur['currency_code'].")"; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>المبلغ:</label>
                            <input type="number" step="0.0001" class="form-control" name="amount" required>
                        </div>
                        <div class="form-group mb-4">
                            <label>نوع الحركة:</label>
                            <select class="form-control" name="adjustment_type">
                                <option value="deposit">إيداع رصيد</option>
                                <option value="withdraw">سحب رصيد</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-info btn-block">تنفيذ الحركة</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <span>الأرصدة المتوفرة</span>
                    <?php if ($is_admin): ?>
                    <form method="POST" class="form-inline">
                        <select class="form-control form-control-sm ml-2" name="view_branch_id">
                            <?php foreach ($branches_data as $br): ?>
                                <option value="<?php echo $br['id']; ?>" <?php echo ($br['id'] == $display_branch_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($br['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="is_view_only" value="1">
                        <button type="submit" class="btn btn-light btn-sm">عرض</button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (isset($balances_data_after) && $balances_data_after->num_rows > 0): ?>
                            <?php while ($row = $balances_data_after->fetch_assoc()): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?php echo htmlspecialchars($row['currency_name_ar']); ?></span>
                                    <span class="badge badge-info p-2"><?php echo number_format($row['current_balance'], 2); ?> <?php echo htmlspecialchars($row['currency_code']); ?></span>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5">لا توجد أرصدة لهذا الفرع.</div>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
include 'footer.php'; 
if (isset($conn)) $conn->close(); 
?>