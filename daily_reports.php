<?php
// daily_reports.php - تقارير العمليات المالية (شامل / حسب الفرع)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php'; 
require_once 'functions.php';

// 1. التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || !defined('CURRENT_COMPANY_ID')) {
    header("Location: index.php");
    exit;
}

$comp_id = CURRENT_COMPANY_ID;
$user_role = $_SESSION['user_role'] ?? 'موظف';

// 2. استقبال متغيرات الفلترة
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$branch_filter = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;

// 3. بناء الاستعلام (Query) بناءً على الفلترة
$where_clauses = ["t.company_id = $comp_id", "t.is_deleted = 0"];

if ($start_date) {
    $where_clauses[] = "DATE(t.created_at) >= '$start_date'";
}
if ($end_date) {
    $where_clauses[] = "DATE(t.created_at) <= '$end_date'";
}
if ($branch_filter > 0) {
    $where_clauses[] = "u.branch_id = $branch_filter";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// جلب قائمة الفروع للـ Dropdown
$branches_res = $conn->query("SELECT id, name FROM branches WHERE company_id = $comp_id AND is_active = 1");

// استعلام جلب البيانات مع الربط بجداول الفروع والموظفين والعملاء
$query = "SELECT t.*, u.full_name as user_name, b.name as branch_name, c.full_name as client_name
          FROM client_transactions t
          JOIN users u ON t.user_id = u.id
          LEFT JOIN branches b ON u.branch_id = b.id
          JOIN clients c ON t.client_id = c.id
          $where_sql
          ORDER BY t.created_at DESC";

$results = $conn->query($query);

// حساب الإجماليات لكل عملة
$totals = [];
$stats_query = "SELECT transaction_type, currency_code, SUM(amount) as total_amount 
                FROM client_transactions t
                JOIN users u ON t.user_id = u.id
                $where_sql
                GROUP BY transaction_type, currency_code";
$stats_res = $conn->query($stats_query);

while($st = $stats_res->fetch_assoc()) {
    $totals[$st['currency_code']][$st['transaction_type']] = $st['total_amount'];
}

include 'header.php';
?>

<div class="container-fluid py-4" dir="rtl text-right">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-invoice-dollar text-primary ml-2"></i> تقارير الحركات المالية</h1>
        <div class="text-muted">شركة: <b><?php echo htmlspecialchars($_SESSION['company_name'] ?? ''); ?></b></div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="daily_reports.php" class="row align-items-end text-right">
                <div class="col-md-3 mb-2 text-right">
                    <label class="small font-weight-bold">من تاريخ:</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3 mb-2 text-right">
                    <label class="small font-weight-bold">إلى تاريخ:</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3 mb-2 text-right">
                    <label class="small font-weight-bold">الفرع:</label>
                    <select name="branch_id" class="form-control">
                        <option value="0">--- جميع الفروع (شامل) ---</option>
                        <?php while($br = $branches_res->fetch_assoc()): ?>
                            <option value="<?php echo $br['id']; ?>" <?php echo ($branch_filter == $br['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($br['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <button type="submit" class="btn btn-primary btn-block shadow-sm">
                        <i class="fas fa-search ml-1"></i> عرض التقرير
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <?php foreach ($totals as $curr => $types): 
            $dep = $types['DEPOSIT'] ?? 0;
            $wit = $types['WITHDRAW'] ?? 0;
            $net = $dep - $wit;
        ?>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-right-primary shadow-sm h-100 py-2" style="border-right: 5px solid #4e73df !important; border-left: 0;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2 text-right">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">صافي حركة (<?php echo $curr; ?>)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($net, 2); ?></div>
                            <div class="mt-2 small">
                                <span class="text-success"><i class="fas fa-arrow-up"></i> إيداع: <?php echo number_format($dep, 2); ?></span><br>
                                <span class="text-danger"><i class="fas fa-arrow-down"></i> سحب: <?php echo number_format($wit, 2); ?></span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-coins fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-list ml-1"></i> تفاصيل الحركات</h6>
            <button onclick="window.print()" class="btn btn-sm btn-light"><i class="fas fa-print"></i> طباعة</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover text-center mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>التاريخ</th>
                            <th>الفرع</th>
                            <th>الموظف</th>
                            <th>العميل</th>
                            <th>نوع العملية</th>
                            <th>المبلغ</th>
                            <th>العملة</th>
                            <th>البيان</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($results && $results->num_rows > 0): ?>
                            <?php while($row = $results->fetch_assoc()): ?>
                                <tr>
                                    <td class="small"><?php echo $row['created_at']; ?></td>
                                    <td><span class="badge badge-light"><?php echo htmlspecialchars($row['branch_name'] ?? 'الإدارة'); ?></span></td>
                                    <td class="small"><?php echo htmlspecialchars($row['user_name']); ?></td>
                                    <td class="font-weight-bold"><?php echo htmlspecialchars($row['client_name']); ?></td>
                                    <td>
                                        <?php if($row['transaction_type'] == 'DEPOSIT'): ?>
                                            <span class="text-success font-weight-bold"><i class="fas fa-plus-circle ml-1"></i> إيداع</span>
                                        <?php else: ?>
                                            <span class="text-danger font-weight-bold"><i class="fas fa-minus-circle ml-1"></i> سحب</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-weight-bold"><?php echo number_format($row['amount'], 2); ?></td>
                                    <td><span class="badge badge-info"><?php echo $row['currency_code']; ?></span></td>
                                    <td class="text-right small"><?php echo htmlspecialchars($row['notes']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="py-5 text-muted">لا توجد حركات مالية مسجلة لهذه الفترة.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, form, header, .sidebar { display: none !important; }
    .card { border: none !important; shadow: none !important; }
    body { background: white; }
}
</style>

<?php 
include 'footer.php'; 
if (isset($conn)) $conn->close(); 
?>