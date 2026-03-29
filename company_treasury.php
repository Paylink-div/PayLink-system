<?php
// company_treasury.php - إدارة خزينة الشركة وحساباتها - نسخة معدلة (إضافة ميزة الحذف)

if (session_status() == PHP_SESSION_NONE) session_start();

require_once 'db_connect.php'; 

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); 
    exit;
}

// معالجة طلب الحذف إذا تم إرساله
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    // نقوم بتعطيل الحساب (Soft Delete) أو حذفه نهائياً حسب رغبتك، هنا سنستخدم الحذف النهائي
    $stmt_del = $conn->prepare("DELETE FROM company_bank_accounts WHERE id = ?");
    $stmt_del->bind_param("i", $delete_id);
    if ($stmt_del->execute()) {
        header("Location: company_treasury.php?msg=" . urlencode("تم حذف الحساب البنكي بنجاح."));
        exit;
    } else {
        $error = "خطأ: لا يمكن حذف الحساب لارتباطه بسجلات حركات مالية.";
    }
}

$pageTitle = 'خزينة الشركة وحساباتها البنكية';
$message = $_GET['msg'] ?? '';
$error = $error ?? '';

// 1. جلب العملة الأساسية في النظام
$base_currency = null;
$stmt_base = $conn->prepare("SELECT id, currency_code FROM currencies WHERE is_base = 1 LIMIT 1");
$stmt_base->execute();
$base_currency_query = $stmt_base->get_result();

if ($base_currency_query && $base_currency_query->num_rows > 0) {
    $base_currency = $base_currency_query->fetch_assoc();
} else {
    $error = "تنبيه: لم يتم تحديد عملة أساسية في النظام بعد.";
}

// 2. جلب الحسابات والأرصدة 
$accounts = [];
$accounts_sql = "
    SELECT cba.*, cy.currency_code, b.name AS branch_name
    FROM company_bank_accounts cba
    JOIN currencies cy ON cba.currency_id = cy.id
    LEFT JOIN branches b ON cba.branch_id = b.id
    WHERE cba.is_active = 1
    ORDER BY cba.bank_name, cy.currency_code";

$stmt_acc = $conn->prepare($accounts_sql);
$stmt_acc->execute();
$accounts_result = $stmt_acc->get_result();
while($row = $accounts_result->fetch_assoc()) { $accounts[] = $row; }

// 3. جلب أسعار الصرف للتحويل
$rates = [];
if ($base_currency) {
    $stmt_rates = $conn->prepare("SELECT currency_code, buy_rate FROM exchange_rates");
    $stmt_rates->execute();
    $rates_query = $stmt_rates->get_result();
    while ($row = $rates_query->fetch_assoc()) { $rates[$row['currency_code']] = (float)$row['buy_rate']; }
}

// 4. تجميع الأرصدة
$summary_by_bank = [];
$total_base_balance = 0;
$base_currency_code = $base_currency['currency_code'] ?? '';

foreach ($accounts as &$account) { 
    $bank_name = $account['bank_name'];
    $current_balance = (float)$account['current_balance'];
    $currency_code = $account['currency_code'];
    
    $rate = ($currency_code === $base_currency_code) ? 1 : ($rates[$currency_code] ?? 0);
    $base_equivalent = $rate * $current_balance;
    
    $account['base_equivalent'] = $base_equivalent;
    $total_base_balance += $base_equivalent;

    if (!isset($summary_by_bank[$bank_name])) {
        $summary_by_bank[$bank_name] = ['total_accounts' => 0, 'total_base_balance' => 0];
    }
    $summary_by_bank[$bank_name]['total_accounts']++;
    $summary_by_bank[$bank_name]['total_base_balance'] += $base_equivalent;
}
unset($account);

include 'header.php'; 
?>

<style>
    :root { --primary: #4e73df; --success: #1cc88a; --warning: #f6c23e; --info: #36b9cc; --danger: #e74a3b; --dark: #5a5c69; }
    body { background-color: #f8f9fc; }
    .summary-card { border-radius: 15px; border: none; transition: transform 0.2s; background: #fff; }
    .summary-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
    .main-balance-card { 
        background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); 
        border-radius: 20px; border: none; color: #fff;
    }
    .btn-action-custom { border-radius: 12px; font-weight: 600; padding: 12px; margin-bottom: 10px; border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: 0.2s; }
    .btn-action-custom:active { transform: scale(0.95); }
    
    @media (max-width: 768px) {
        .table-responsive thead { display: none; }
        .table-responsive tr { display: block; margin-bottom: 1.5rem; background: #fff; border-radius: 15px; border: 1px solid #e3e6f0; padding: 10px; }
        .table-responsive td { display: flex; justify-content: space-between; border: none !important; padding: 8px 12px; }
        .table-responsive td::before { content: attr(data-label); font-weight: bold; color: var(--dark); }
    }
</style>

<div class="container-fluid py-3" dir="rtl text-right">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 font-weight-bold text-dark mb-1"><i class="fas fa-university text-primary ml-2"></i> خزينة المصارف</h1>
            <p class="text-muted small">إدارة الحسابات البنكية والأرصدة الجارية</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm text-right"><i class="fas fa-check-circle ml-2"></i> <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm text-right"><i class="fas fa-exclamation-triangle ml-2"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="row mb-4 px-2 justify-content-center">
        <div class="col-6 col-md-4 px-1"><a href="add_company_account.php" class="btn btn-success btn-block btn-action-custom shadow-sm"><i class="fas fa-plus-circle d-block mb-1"></i> حساب جديد</a></div>
        <div class="col-6 col-md-4 px-1"><a href="record_bank_movement.php" class="btn btn-warning btn-block btn-action-custom shadow-sm text-dark"><i class="fas fa-money-bill-wave d-block mb-1"></i> إيداع/سحب</a></div>
        <div class="col-6 col-md-4 px-1"><a href="bank_transfer.php" class="btn btn-primary btn-block btn-action-custom shadow-sm"><i class="fas fa-random d-block mb-1"></i> تحويل بنكي</a></div>
    </div>

    <?php if ($base_currency): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card main-balance-card shadow">
                <div class="card-body py-4 text-center">
                    <div class="text-xs text-uppercase font-weight-bold mb-1" style="opacity: 0.9;">إجمالي السيولة النقدية (المكافئ بالعملة الأساسية)</div>
                    <div class="h1 mb-0 font-weight-bold"><?php echo number_format($total_base_balance, 2); ?> <small style="font-size: 0.5em;"><?php echo htmlspecialchars($base_currency_code); ?></small></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <h5 class="font-weight-bold text-dark mb-3">توزيع السيولة حسب المصارف</h5>
    <div class="row mb-4 text-right">
        <?php foreach ($summary_by_bank as $bank => $data): ?>
            <div class="col-12 col-md-4 mb-3">
                <div class="card summary-card shadow-sm border-right-primary" style="border-right: 5px solid var(--primary);">
                    <div class="card-body py-3">
                        <div class="font-weight-bold text-primary h6 mb-1"><?php echo htmlspecialchars($bank); ?></div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($data['total_base_balance'], 2); ?> <span class="small"><?php echo $base_currency_code; ?></span></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card shadow-sm border-0 mb-5" style="border-radius: 15px;">
        <div class="card-header bg-white py-3 border-0">
            <h6 class="m-0 font-weight-bold text-primary text-right"><i class="fas fa-table ml-1"></i> تفاصيل الحسابات البنكية</h6>
        </div>
        <div class="card-body p-0 p-md-3 text-right">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light text-dark">
                        <tr>
                            <th>المصرف والفرع</th>
                            <th>رقم الحساب</th>
                            <th>العملة</th>
                            <th>الرصيد الفعلي</th>
                            <?php if ($base_currency): ?><th>المكافئ (<?php echo $base_currency_code; ?>)</th><?php endif; ?>
                            <th class="text-center">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($accounts as $acc): ?>
                            <tr>
                                <td data-label="المصرف:">
                                    <div class="font-weight-bold"><?php echo htmlspecialchars($acc['bank_name']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($acc['branch_name'] ?? 'الفرع الرئيسي'); ?></div>
                                </td>
                                <td data-label="الحساب:"><code><?php echo htmlspecialchars($acc['account_number']); ?></code></td>
                                <td data-label="العملة:"><span class="badge badge-info px-2 py-1"><?php echo htmlspecialchars($acc['currency_code']); ?></span></td>
                                <td data-label="الرصيد:" class="text-primary font-weight-bold"><?php echo number_format($acc['current_balance'], 2); ?></td>
                                <?php if ($base_currency): ?>
                                    <td data-label="المكافئ:" class="text-success"><?php echo number_format($acc['base_equivalent'], 2); ?></td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center">
                                        <a href="bank_movements_log.php?account_id=<?php echo $acc['id']; ?>" class="btn btn-sm btn-outline-info mx-1 border-0" title="السجل"><i class="fas fa-history"></i></a>
                                        <a href="edit_bank_account.php?id=<?php echo $acc['id']; ?>" class="btn btn-sm btn-outline-primary mx-1 border-0" title="تعديل"><i class="fas fa-edit"></i></a>
                                        <a href="javascript:void(0);" 
                                           onclick="if(confirm('هل أنت متأكد من حذف هذا الحساب؟ لا يمكن التراجع عن هذه الخطوة.')) window.location.href='company_treasury.php?delete_id=<?php echo $acc['id']; ?>';" 
                                           class="btn btn-sm btn-outline-danger mx-1 border-0" title="حذف">
                                           <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($accounts)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">لا توجد حسابات بنكية جارية.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
include 'footer.php'; 
if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
?>