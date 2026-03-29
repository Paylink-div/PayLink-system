<?php
// branch_report.php - نسخة مطورة تدعم تصدير Excel وتحليل الحركات التفصيلي

if (session_status() == PHP_SESSION_NONE) session_start();

require_once 'db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); 
    exit;
}

$pageTitle = 'تقارير الخزينة والتحليلات';

// تحديد التواريخ الافتراضية
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$account_filter = $_GET['account_id'] ?? 'all'; 

$error_message = '';
$account_details = [];
$grouped_analysis = [];
$detailed_movements = [];

// 1. جلب بيانات الحسابات والأرصدة الحالية
try {
    $acc_sql = "SELECT cba.id, cba.bank_name, cy.currency_code AS currency, cba.current_balance
                FROM company_bank_accounts cba
                JOIN currencies cy ON cba.currency_id = cy.id  
                WHERE cba.is_active = 1
                ORDER BY cba.bank_name";
    $res_acc = $conn->query($acc_sql);
    if ($res_acc) {
        while ($row = $res_acc->fetch_assoc()) {
            $account_details[$row['id']] = $row;
        }
    }
} catch (Exception $e) { 
    $error_message = "خطأ في جلب الحسابات: " . $e->getMessage(); 
}

// 2. تحليل الحركات المالية (ملخص العملات)
try {
    $analysis_sql = "SELECT 
                        COALESCE(bm.currency, 'LYD') as currency_name, 
                        bm.movement_type, 
                        SUM(bm.amount) AS total_amount
                     FROM bank_movements bm
                     WHERE bm.transaction_date >= ? AND bm.transaction_date <= ?";

    if ($account_filter !== 'all') {
        $analysis_sql .= " AND bm.account_id = " . intval($account_filter);
    }
    
    $analysis_sql .= " GROUP BY COALESCE(bm.currency, 'LYD'), bm.movement_type";

    $stmt_an = $conn->prepare($analysis_sql);
    $stmt_an->bind_param("ss", $start_date, $end_date);
    $stmt_an->execute();
    $res_an = $stmt_an->get_result();

    if ($res_an) {
        while ($row = $res_an->fetch_assoc()) {
            $curr = $row['currency_name'];
            if (!isset($grouped_analysis[$curr])) {
                $grouped_analysis[$curr] = ['deposit' => 0, 'withdrawal' => 0, 'transfer_in' => 0, 'transfer_out' => 0, 'net' => 0];
            }
            
            $amt = (float)$row['total_amount'];
            $type = trim($row['movement_type']);

            if (in_array($type, ['deposit', 'transfer_in', 'internal_transfer_in'])) {
                $grouped_analysis[$curr]['deposit'] += $amt;
                if (strpos($type, 'transfer') !== false) $grouped_analysis[$curr]['transfer_in'] += $amt;
                $grouped_analysis[$curr]['net'] += $amt;
            } 
            elseif (in_array($type, ['withdrawal', 'transfer_out', 'internal_transfer', 'external_transfer', 'internal_transfer_out'])) {
                $grouped_analysis[$curr]['withdrawal'] += $amt;
                if (strpos($type, 'transfer') !== false) $grouped_analysis[$curr]['transfer_out'] += $amt;
                $grouped_analysis[$curr]['net'] -= $amt;
            }
        }
    }

    // 3. جلب الحركات التفصيلية إذا تم اختيار حساب محدد
    if ($account_filter !== 'all') {
        $detail_sql = "SELECT bm.*, u.full_name as user_name 
                       FROM bank_movements bm 
                       LEFT JOIN users u ON bm.created_by_user_id = u.id
                       WHERE bm.account_id = ? AND bm.transaction_date >= ? AND bm.transaction_date <= ? 
                       ORDER BY bm.transaction_date DESC, bm.id DESC";
        $stmt_det = $conn->prepare($detail_sql);
        $stmt_det->bind_param("iss", $account_filter, $start_date, $end_date);
        $stmt_det->execute();
        $detailed_movements = $stmt_det->get_result()->fetch_all(MYSQLI_ASSOC);
    }

} catch (Exception $e) {
    $error_message = "فشل تحليل البيانات: " . $e->getMessage();
}

include 'header.php'; 
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

<style>
    .report-card { border-radius: 15px; border: none; transition: transform 0.2s; border-right: 5px solid #4e73df; background: #fff; }
    .report-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
    .stat-label { font-size: 0.8rem; color: #6c757d; font-weight: bold; }
    .stat-value { font-size: 1.1rem; font-weight: 700; }
    .net-box { border-radius: 10px; padding: 10px; background: #f8f9fa; border-top: 1px solid #eee; }
    .balance-badge { background: linear-gradient(45deg, #1e3c72, #2a5298); color: white; border-radius: 12px; padding: 15px; height: 100%; transition: 0.3s; }
    .table-detail thead { background: #f8f9fa; }
</style>

<div class="container-fluid py-4" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap text-right">
        <h2 class="text-dark font-weight-bold"><i class="fas fa-chart-line text-primary ml-2"></i> تقرير تحليل الخزينة</h2>
        <button onclick="exportToExcel()" class="btn btn-success shadow-sm">
            <i class="fas fa-file-excel ml-1"></i> تصدير إكسل
        </button>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger text-right border-0 shadow-sm"><i class="fas fa-exclamation-circle ml-2"></i> <?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4 border-0 text-right">
        <div class="card-body">
            <form method="GET" class="row align-items-end">
                <div class="col-md-3 col-6 mb-2">
                    <label class="small font-weight-bold text-muted">من تاريخ</label>
                    <input type="date" class="form-control shadow-sm" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <label class="small font-weight-bold text-muted">إلى تاريخ</label>
                    <input type="date" class="form-control shadow-sm" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-4 col-12 mb-2">
                    <label class="small font-weight-bold text-muted">تصفية حسب الحساب</label>
                    <select class="form-control shadow-sm" name="account_id">
                        <option value="all">كافة الحسابات البنكية</option>
                        <?php foreach ($account_details as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>" <?php echo ($account_filter == $acc['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($acc['bank_name']) . " (" . $acc['currency'] . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-block shadow-sm"><i class="fas fa-sync-alt ml-1"></i> تحديث</button>
                </div>
            </form>
        </div>
    </div>

    <h5 class="mb-3 text-right font-weight-bold text-secondary">ملخص النشاط المالي</h5>
    <div class="row text-right">
        <?php if (empty($grouped_analysis)): ?>
            <div class="col-12 text-center py-5 bg-white rounded shadow-sm">
                <p class="text-muted">لا توجد حركات مسجلة.</p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped_analysis as $currency => $data): ?>
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card shadow-sm report-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge badge-primary px-3 py-2"><?php echo $currency; ?></span>
                                <i class="fas fa-coins text-light fa-2x"></i>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <div class="stat-label">إيداع كلي (+)</div>
                                    <div class="stat-value text-success"><?php echo number_format($data['deposit'], 2); ?></div>
                                </div>
                                <div class="col-6 mb-2 text-left">
                                    <div class="stat-label">سحب كلي (-)</div>
                                    <div class="stat-value text-danger"><?php echo number_format($data['withdrawal'], 2); ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-label text-info">تحويلات واردة</div>
                                    <div class="stat-value text-info" style="font-size:0.9rem"><?php echo number_format($data['transfer_in'], 2); ?></div>
                                </div>
                                <div class="col-6 text-left">
                                    <div class="stat-label text-warning">تحويلات صادرة</div>
                                    <div class="stat-value text-warning" style="font-size:0.9rem"><?php echo number_format($data['transfer_out'], 2); ?></div>
                                </div>
                            </div>
                            <div class="net-box mt-3 d-flex justify-content-between align-items-center">
                                <span class="small font-weight-bold">صافي التدفق:</span>
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

    <?php if ($account_filter !== 'all' && !empty($detailed_movements)): ?>
        <h5 class="mt-4 mb-3 text-right font-weight-bold text-primary">سجل حركات الحساب التفصيلي</h5>
        <div class="card shadow-sm border-0 mb-4">
            <div class="table-responsive">
                <table class="table table-hover mb-0 text-right text-center" id="detailedTable">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>البيان / الوصف</th>
                            <th>نوع الحركة</th>
                            <th class="text-success">وارد (+)</th>
                            <th class="text-danger">صادر (-)</th>
                            <th>بواسطة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detailed_movements as $move): 
                            $is_in = in_array($move['movement_type'], ['deposit', 'transfer_in', 'internal_transfer_in']);
                        ?>
                            <tr>
                                <td class="small"><?php echo $move['transaction_date']; ?></td>
                                <td class="text-right"><?php echo htmlspecialchars($move['description']); ?></td>
                                <td>
                                    <span class="badge badge-light border"><?php echo $move['movement_type']; ?></span>
                                </td>
                                <td class="font-weight-bold text-success"><?php echo $is_in ? number_format($move['amount'], 2) : '-'; ?></td>
                                <td class="font-weight-bold text-danger"><?php echo !$is_in ? number_format($move['amount'], 2) : '-'; ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($move['user_name'] ?? 'System'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <h5 class="mt-4 mb-3 text-right font-weight-bold text-secondary">الأرصدة المتوفرة (الآن)</h5>
    <div class="row text-right" id="balanceCards">
        <?php foreach ($account_details as $acc): ?>
            <?php if ($account_filter == 'all' || $account_filter == $acc['id']): ?>
                <div class="col-md-4 mb-3">
                    <div class="balance-badge shadow-sm d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small opacity-75"><?php echo htmlspecialchars($acc['bank_name']); ?></div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo number_format($acc['current_balance'], 2); ?></div>
                        </div>
                        <div class="h4 mb-0 opacity-50 font-weight-bold"><?php echo $acc['currency']; ?></div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<script>
async function exportToExcel() {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('تقرير الخزينة');

    // إعداد العناوين
    worksheet.columns = [
        { header: 'العملة', key: 'curr', width: 15 },
        { header: 'إجمالي الإيداع', key: 'dep', width: 20 },
        { header: 'إجمالي السحب', key: 'with', width: 20 },
        { header: 'الصافي', key: 'net', width: 20 }
    ];

    // إضافة البيانات من الملخص
    <?php foreach ($grouped_analysis as $currency => $data): ?>
    worksheet.addRow({
        curr: '<?php echo $currency; ?>',
        dep: '<?php echo $data['deposit']; ?>',
        with: '<?php echo $data['withdrawal']; ?>',
        net: '<?php echo $data['net']; ?>'
    });
    <?php endforeach; ?>

    // تنسيق الصف الأول
    worksheet.getRow(1).font = { bold: true };
    worksheet.views = [{rightToLeft: true}];

    const buffer = await workbook.xlsx.writeBuffer();
    saveAs(new Blob([buffer]), `Bank_Report_<?php echo date('Y-m-d'); ?>.xlsx`);
}
</script>

<?php 
include 'footer.php'; 
if (isset($conn)) $conn->close(); 
?>