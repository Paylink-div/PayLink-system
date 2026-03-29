<?php
// bank_reports.php - تقارير وتحليلات الخزينة (نسخة معدلة لإزالة قيود الشركات)

if (session_status() == PHP_SESSION_NONE) session_start();

require_once 'db_connect.php'; 

// 1. التحقق من تسجيل الدخول فقط (تم حذف شرط company_id)
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); 
    exit;
}

$pageTitle = 'تقارير الخزينة والتحليلات';

// معالجة فلاتر البحث
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // بداية الشهر الحالي
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$account_filter = $_GET['account_id'] ?? 'all'; 

$error_message = '';
$account_details = [];
$grouped_analysis = [];

// 2. جلب الحسابات البنكية المتاحة في النظام (تم حذف شرط company_id)
$acc_sql = "SELECT cba.id, cba.bank_name, cy.currency_code AS currency, cba.current_balance
            FROM company_bank_accounts cba
            JOIN currencies cy ON cba.currency_id = cy.id  
            WHERE cba.is_active = 1
            ORDER BY cba.bank_name";

$stmt_acc = $conn->prepare($acc_sql);
$stmt_acc->execute();
$res_acc = $stmt_acc->get_result();
while ($row = $res_acc->fetch_assoc()) {
    $account_details[$row['id']] = $row;
}
$stmt_acc->close();

// 3. تحليل الحركات حسب الفترة (تم حذف قيد company_id)
$analysis_sql = "SELECT bm.currency, bm.movement_type, SUM(bm.amount) AS total_amount
                 FROM bank_movements bm
                 WHERE bm.transaction_date BETWEEN ? AND ?";

if ($account_filter !== 'all') {
    $analysis_sql .= " AND bm.account_id = " . intval($account_filter);
}
$analysis_sql .= " GROUP BY bm.currency, bm.movement_type";

$stmt_an = $conn->prepare($analysis_sql);
$stmt_an->bind_param("ss", $start_date, $end_date);
$stmt_an->execute();
$res_an = $stmt_an->get_result();

while ($row = $res_an->fetch_assoc()) {
    $curr = $row['currency'];
    if (!isset($grouped_analysis[$curr])) {
        $grouped_analysis[$curr] = ['deposit' => 0, 'withdrawal' => 0, 'net' => 0];
    }
    
    $amt = (float)$row['total_amount'];
    // تصنيف الحركات
    if (in_array($row['movement_type'], ['deposit', 'transfer_in'])) {
        $grouped_analysis[$curr]['deposit'] += $amt;
        $grouped_analysis[$curr]['net'] += $amt;
    } else {
        $grouped_analysis[$curr]['withdrawal'] += $amt;
        $grouped_analysis[$curr]['net'] -= $amt;
    }
}
$stmt_an->close();

include 'header.php'; 
?>

<style>
    .report-card { border-radius: 15px; border: none; transition: transform 0.2s; }
    .report-card:hover { transform: translateY(-5px); }
    .stat-label { font-size: 0.85rem; color: #6c757d; font-weight: bold; }
    .stat-value { font-size: 1.2rem; font-weight: 700; }
    .net-box { border-radius: 10px; padding: 10px; background: #f8f9fa; }
    .balance-badge { background: linear-gradient(45deg, #1e3c72, #2a5298); color: white; border-radius: 10px; padding: 15px; }
    
    @media (max-width: 768px) {
        .stat-value { font-size: 1rem; }
        .btn-update { width: 100%; margin-top: 10px; }
    }
</style>

<div class="container-fluid py-4" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <h2 class="text-dark font-weight-bold"><i class="fas fa-chart-pie text-primary ml-2"></i> تقرير تحليل الخزينة</h2>
        <div class="text-muted small">النظام المالي الموحد</div>
    </div>

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <form method="GET" class="row align-items-end text-right">
                <div class="col-md-3 col-6 mb-2">
                    <label class="small font-weight-bold">من تاريخ</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <label class="small font-weight-bold">إلى تاريخ</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-4 col-12 mb-2">
                    <label class="small font-weight-bold">الحساب البنكي</label>
                    <select class="form-control text-right" name="account_id">
                        <option value="all">كافة الحسابات</option>
                        <?php foreach ($account_details as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>" <?php echo ($account_filter == $acc['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($acc['bank_name']) . " (" . $acc['currency'] . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-12">
                    <button type="submit" class="btn btn-primary btn-block shadow-sm btn-update">
                        <i class="fas fa-sync-alt ml-1"></i> تحديث
                    </button>
                </div>
            </form>
        </div>
    </div>

    <h5 class="mb-3 text-right font-weight-bold">النشاط المالي خلال الفترة</h5>
    <div class="row text-right">
        <?php if (empty($grouped_analysis)): ?>
            <div class="col-12 text-center py-5">
                <p class="text-muted mt-3">لا توجد حركات مالية مسجلة في هذه الفترة.</p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped_analysis as $currency => $data): ?>
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card shadow-sm report-card border-right-primary h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge badge-primary px-3 py-2"><?php echo $currency; ?></span>
                                <i class="fas fa-coins text-light fa-2x"></i>
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="stat-label">إجمالي الإيداع</div>
                                    <div class="stat-value text-success"><?php echo number_format($data['deposit'], 2); ?></div>
                                </div>
                                <div class="col-6 text-left">
                                    <div class="stat-label">إجمالي السحب</div>
                                    <div class="stat-value text-danger"><?php echo number_format($data['withdrawal'], 2); ?></div>
                                </div>
                            </div>
                            
                            <div class="net-box mt-3 d-flex justify-content-between align-items-center">
                                <span class="small font-weight-bold">صافي الحركة:</span>
                                <span class="stat-value <?php echo ($data['net'] >= 0) ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo ($data['net'] >= 0 ? '+' : '') . number_format($data['net'], 2); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <h5 class="mt-4 mb-3 text-right font-weight-bold">الأرصدة الفعلية المتوفرة (الآن)</h5>
    <div class="row text-right">
        <?php foreach ($account_details as $acc): ?>
            <?php if ($account_filter == 'all' || $account_filter == $acc['id']): ?>
                <div class="col-md-4 mb-3">
                    <div class="balance-badge shadow-sm d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small opacity-75"><?php echo htmlspecialchars($acc['bank_name']); ?></div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo number_format($acc['current_balance'], 2); ?></div>
                        </div>
                        <div class="h4 mb-0 opacity-50"><?php echo $acc['currency']; ?></div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<?php 
include 'footer.php'; 
if (isset($conn)) $conn->close(); 
?>