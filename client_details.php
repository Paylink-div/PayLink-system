<?php
// client_details.php - تفاصيل العميل والسجل المالي - متوافق مع الهاتف - مع ميزة تصدير إكسل (ExcelJS)

if (session_status() == PHP_SESSION_NONE) session_start();

include 'functions.php'; 
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); 
    exit;
}

// استخدام PHPMailer لإرسال الإشعارات
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

$client_id = intval($_GET['id'] ?? 0); 
$user_id = $_SESSION['user_id'];

if ($client_id === 0) { header("Location: clients_management.php"); exit; }

$message = '';
$message_type = '';
$supported_currencies = ['LYD', 'USD', 'EUR']; 

// دالة إرسال البريد الإلكتروني
function send_client_email($to_email, $client_name, $trx_type, $amount, $currency) {
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) return false;
    $op = ($trx_type == 'DEPOSIT') ? 'إيداع' : 'سحب';
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'zagdon01@gmail.com'; 
        $mail->Password = 'hxnptzlifvxsxpsz'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->setFrom('zagdon01@gmail.com', 'PayLink Financial');
        $mail->addAddress($to_email, $client_name);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "إشعار حركة مالية - $client_name";
        $mail->Body = "<div dir='rtl'><h3>تنبيه حركة مالية</h3><p>تم تسجيل عملية <b>$op</b> بمبلغ <b>" . number_format($amount, 2) . " $currency</b> في حسابك.</p></div>";
        $mail->send();
        return true;
    } catch (Exception $e) { return false; }
}

// 1. جلب بيانات العميل
$client_q = $conn->prepare("SELECT full_name, email, daily_withdrawal_limit FROM clients WHERE id = ?"); 
$client_q->bind_param("i", $client_id);
$client_q->execute();
$client_res = $client_q->get_result();
if ($row = $client_res->fetch_assoc()) {
    $client_data = $row;
} else {
    header("Location: clients_management.php"); exit;
}

// جلب الأرصدة
$balances = array_fill_keys($supported_currencies, 0.00);
$bal_q = $conn->prepare("SELECT currency_code, current_balance FROM client_balances WHERE client_id = ?");
$bal_q->bind_param("i", $client_id);
$bal_q->execute();
$res = $bal_q->get_result();
while ($r = $res->fetch_assoc()) { $balances[$r['currency_code']] = (float)$r['current_balance']; }

// 2. معالجة العمليات المالية
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_transaction') {
    $amount = (float)$_POST['amount'];
    $trx_type = $_POST['transaction_type']; 
    $currency = $_POST['currency_code'];
    $notes = $_POST['notes'] ?? ''; 

    if ($amount > 0 && in_array($currency, $supported_currencies)) {
        $conn->begin_transaction();
        try {
            $old_bal = $balances[$currency];
            $new_bal = ($trx_type == 'DEPOSIT') ? $old_bal + $amount : $old_bal - $amount;
            
            if ($trx_type == 'WITHDRAW') {
                // تم إزالة شرط (if ($new_bal < 0)) للسماح بالسحب بالسالب
                
                $limit = (float)$client_data['daily_withdrawal_limit'];
                if ($limit > 0) {
                    $today = date('Y-m-d');
                    $check_limit_q = $conn->prepare("SELECT SUM(amount) as total_today FROM client_transactions WHERE client_id = ? AND transaction_type = 'WITHDRAW' AND currency_code = ? AND DATE(created_at) = ? AND is_deleted = 0");
                    $check_limit_q->bind_param("iss", $client_id, $currency, $today);
                    $check_limit_q->execute();
                    $limit_res = $check_limit_q->get_result()->fetch_assoc();
                    $total_withdrawn_today = (float)($limit_res['total_today'] ?? 0);

                    if (($total_withdrawn_today + $amount) > $limit) {
                        throw new Exception("خطأ: تجاوز سقف السحب اليومي المسموح به ($limit). المسحوب اليوم: $total_withdrawn_today");
                    }
                }
            }

            $up = $conn->prepare("INSERT INTO client_balances (client_id, currency_code, current_balance) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE current_balance = ?");
            $up->bind_param("isdd", $client_id, $currency, $new_bal, $new_bal);
            $up->execute();

            $ins = $conn->prepare("INSERT INTO client_transactions (client_id, user_id, transaction_type, currency_code, amount, balance_before, balance_after, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("iissddds", $client_id, $user_id, $trx_type, $currency, $amount, $old_bal, $new_bal, $notes);
            $ins->execute();

            $conn->commit();
            send_client_email($client_data['email'], $client_data['full_name'], $trx_type, $amount, $currency);
            header("Location: client_details.php?id=$client_id&message=تمت العملية بنجاح&type=success"); exit;
        } catch (Exception $e) { $conn->rollback(); $message = $e->getMessage(); $message_type = 'danger'; }
    }
}

// 3. التصفية لعرض الجدول
$start = $_GET['start_date'] ?? '';
$end = $_GET['end_date'] ?? '';
$where = "WHERE t.client_id = $client_id AND t.is_deleted = 0";
if ($start) $where .= " AND DATE(t.created_at) >= '$start'";
if ($end) $where .= " AND DATE(t.created_at) <= '$end'";

$query = "SELECT t.*, u.full_name AS user_name FROM client_transactions t JOIN users u ON t.user_id = u.id $where ORDER BY t.created_at DESC";
$transactions_data = $conn->query($query);

include 'header.php'; 
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

<style>
    :root { --primary: #4e73df; --success: #1cc88a; --danger: #e74a3b; --purple: #6f42c1; --dark: #5a5c69; }
    body { background-color: #f8f9fc; }
    .balance-card { border-radius: 15px; border: none; transition: transform 0.2s; }
    .balance-card:hover { transform: translateY(-5px); }
    .trx-form { border-radius: 15px; border: none; background: #fff; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); }
    
    @media (max-width: 768px) {
        .table-responsive thead { display: none; }
        .table-responsive tr { display: block; margin-bottom: 1rem; border: 1px solid #e3e6f0; border-radius: 10px; background: #fff; padding: 10px; }
        .table-responsive td { display: flex; justify-content: space-between; border: none !important; padding: 5px 10px; text-align: left; }
        .table-responsive td::before { content: attr(data-label); font-weight: bold; color: var(--dark); }
    }
</style>

<div class="container-fluid py-3" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <h1 class="h3 font-weight-bold text-dark"><i class="fas fa-wallet text-primary ml-2"></i> المحفظة: <?php echo htmlspecialchars($client_data['full_name']); ?></h1>
        <div class="d-flex">
            <button onclick="exportClientTransactions()" class="btn btn-success shadow-sm ml-2">
                <i class="fas fa-file-excel ml-1"></i> تصدير Excel
            </button>
            <a href="print_ledger.php?id=<?php echo $client_id; ?>&start_date=<?php echo $start; ?>&end_date=<?php echo $end; ?>" target="_blank" class="btn btn-primary shadow-sm ml-2">
                <i class="fas fa-print ml-1"></i> كشف حساب
            </a>
            <a href="clients_management.php" class="btn btn-outline-secondary shadow-sm"><i class="fas fa-chevron-right ml-1"></i> عودة</a>
        </div>
    </div>

    <?php if ($message || isset($_GET['message'])): ?>
        <div class="alert alert-<?php echo $message_type ?: ($_GET['type'] ?? 'info'); ?> shadow-sm border-0 text-right">
            <i class="fas fa-exclamation-triangle ml-2"></i> <?php echo $message ?: htmlspecialchars($_GET['message']); ?>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <?php foreach ($supported_currencies as $cur): $bal = $balances[$cur] ?? 0; ?>
            <div class="col-6 col-md-4 mb-3">
                <div class="card balance-card shadow-sm border-right-lg border-<?php echo ($bal >= 0) ? 'success' : 'danger'; ?>" style="border-right: 5px solid;">
                    <div class="card-body py-3 text-right">
                        <div class="text-xs font-weight-bold text-uppercase mb-1" style="color: var(--dark);">رصيد <?php echo $cur; ?></div>
                        <div class="h4 mb-0 font-weight-bold <?php echo ($bal >= 0) ? 'text-success' : 'text-danger'; ?>">
                            <?php echo number_format($bal, 2); ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($client_data['daily_withdrawal_limit'] > 0): ?>
    <div class="alert alert-warning border-0 shadow-sm text-right mb-4">
        <i class="fas fa-info-circle ml-2"></i> سقف السحب اليومي لهذا العميل هو: <strong><?php echo number_format($client_data['daily_withdrawal_limit'], 2); ?></strong>
    </div>
    <?php endif; ?>

    <div class="card trx-form mb-4">
        <div class="card-header bg-white border-0 py-3 text-right"><h6 class="m-0 font-weight-bold text-primary">إضافة حركة مالية جديدة</h6></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_transaction">
                <div class="row text-right">
                    <div class="col-md-3 col-6 mb-3">
                        <label class="small font-weight-bold">نوع الحركة:</label>
                        <select name="transaction_type" class="form-control border-0 bg-light" required>
                            <option value="DEPOSIT">إيداع (+)</option>
                            <option value="WITHDRAW">سحب (-)</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <label class="small font-weight-bold">العملة:</label>
                        <select name="currency_code" class="form-control border-0 bg-light">
                            <?php foreach($supported_currencies as $c) echo "<option value='$c'>$c</option>"; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="small font-weight-bold">المبلغ:</label>
                        <input type="number" step="0.01" class="form-control border-0 bg-light" name="amount" required placeholder="0.00">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="small font-weight-bold">الملاحظات:</label>
                        <input type="text" class="form-control border-0 bg-light" name="notes" placeholder="بيان العملية...">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block shadow-sm">تنفيذ العملية الآن</button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 15px;">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap text-right">
            <h6 class="m-0 font-weight-bold text-dark">سجل الحركات المالية</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-4">
                <input type="hidden" name="id" value="<?php echo $client_id; ?>">
                <div class="col-md-4 col-6"><input type="date" name="start_date" class="form-control form-control-sm mb-2" value="<?php echo htmlspecialchars($start); ?>"></div>
                <div class="col-md-4 col-6"><input type="date" name="end_date" class="form-control form-control-sm mb-2" value="<?php echo htmlspecialchars($end); ?>"></div>
                <div class="col-md-4 col-12"><button type="submit" class="btn btn-sm btn-dark btn-block shadow-sm">تصفية</button></div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover text-right" id="trxTable">
                    <thead class="bg-light">
                        <tr>
                            <th>التاريخ</th><th>النوع</th><th>المبلغ</th><th>الرصيد قبل</th><th>الرصيد بعد</th><th>الملاحظات</th><th>الإجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($transactions_data && $transactions_data->num_rows > 0): while($row = $transactions_data->fetch_assoc()): ?>
                            <tr>
                                <td data-label="التاريخ:"><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                <td data-label="النوع:" class="font-weight-bold <?php echo ($row['transaction_type'] == 'DEPOSIT') ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo ($row['transaction_type'] == 'DEPOSIT') ? 'إيداع' : 'سحب'; ?> (<?php echo $row['currency_code']; ?>)
                                </td>
                                <td data-label="المبلغ:"><?php echo number_format($row['amount'], 2); ?></td>
                                <td data-label="الرصيد قبل:"><?php echo number_format($row['balance_before'], 2); ?></td>
                                <td data-label="الرصيد بعد:"><?php echo number_format($row['balance_after'], 2); ?></td>
                                <td data-label="الملاحظات:"><?php echo htmlspecialchars($row['notes']); ?></td>
                                <td data-label="الإجراء:">
                                    <a href="print_ledger.php?id=<?php echo $client_id; ?>&trx_id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary shadow-sm">
                                        <i class="fas fa-print"></i> وصل
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="7" class="text-center">لا توجد حركات مالية مسجلة</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
async function exportClientTransactions() {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('سجل حركات العميل');
    worksheet.views = [{ rightToLeft: true }];
    const table = document.getElementById('trxTable');
    const rows = Array.from(table.querySelectorAll('tr'));
    rows.forEach((row, rowIndex) => {
        const rowData = [];
        const cells = Array.from(row.querySelectorAll('th, td'));
        cells.forEach((cell, index) => {
            if (index < cells.length - 1) {
                rowData.push(cell.innerText.trim());
            }
        });
        const excelRow = worksheet.addRow(rowData);
        if (rowIndex === 0) {
            excelRow.eachCell((cell) => {
                cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: '4E73DF' } };
                cell.font = { color: { argb: 'FFFFFF' }, bold: true };
                cell.alignment = { horizontal: 'center' };
                cell.border = { top: {style:'thin'}, left: {style:'thin'}, bottom: {style:'thin'}, right: {style:'thin'} };
            });
        } else {
            excelRow.eachCell((cell) => {
                cell.alignment = { horizontal: 'center' };
                cell.border = { top: {style:'thin'}, left: {style:'thin'}, bottom: {style:'thin'}, right: {style:'thin'} };
            });
        }
    });
    worksheet.columns.forEach(column => { column.width = 20; });
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    saveAs(blob, 'سجل_حساب_<?php echo htmlspecialchars($client_data['full_name']); ?>_' + new Date().toISOString().slice(0,10) + '.xlsx');
}
</script>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

<?php include 'footer.php'; ?>