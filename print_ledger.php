<?php
// print_ledger.php - النسخة النهائية المحدثة لربط اسم الشركة ديناميكياً

if (session_status() == PHP_SESSION_NONE) session_start();

require_once 'db_connect.php'; 
include 'functions.php'; 

if (!isset($_SESSION['user_id'])) {
    die("خطأ أمني: يرجى تسجيل الدخول أولاً.");
}

$client_id = intval($_GET['id'] ?? 0); 
$trx_id = intval($_GET['trx_id'] ?? 0); 

if ($client_id === 0) die("خطأ: لم يتم تحديد العميل.");

// --- التعديل: جلب اسم الشركة من الثابت المعرف في db_connect.php ---
$company_name = defined('COMPANY_NAME') ? COMPANY_NAME : 'نظام PayLink للخدمات المالية';

// جلب بيانات العميل
$client_q = $conn->prepare("SELECT full_name, phone_number FROM clients WHERE id = ?");
$client_q->bind_param("i", $client_id);
$client_q->execute();
$client_result = $client_q->get_result();

if ($client_result->num_rows > 0) {
    $client_data = $client_result->fetch_assoc();
    $client_name = htmlspecialchars($client_data['full_name']);
} else {
    die("خطأ: العميل غير موجود.");
}

// معالجة التصفية
$filter_start_date = $_GET['start_date'] ?? ''; 
$filter_end_date = $_GET['end_date'] ?? '';    
$supported_currencies = ['LYD', 'USD', 'EUR']; 

$where_clauses = ["t.is_deleted = 0"];
$params = [];
$types = "";

if ($trx_id > 0) {
    $where_clauses[] = "t.id = ?";
    $types .= "i";
    $params[] = $trx_id;
} else {
    $where_clauses[] = "t.client_id = ?";
    $types .= "i";
    $params[] = $client_id;
    
    if (!empty($filter_start_date)) {
        $where_clauses[] = "DATE(t.created_at) >= ?";
        $types .= "s"; $params[] = $filter_start_date;
    }
    if (!empty($filter_end_date)) {
        $where_clauses[] = "DATE(t.created_at) <= ?";
        $types .= "s"; $params[] = $filter_end_date;
    }
}

$where_string = implode(' AND ', $where_clauses);
$sql = "SELECT t.*, u.full_name AS user_name FROM client_transactions t 
        JOIN users u ON t.user_id = u.id 
        WHERE $where_string ORDER BY t.created_at ASC";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transactions_data = $stmt->get_result();

// حساب الرصيد الافتتاحي
$opening_balances = [];
if ($trx_id == 0) {
    foreach ($supported_currencies as $currency) {
        $opening_q = $conn->prepare("SELECT balance_after FROM client_transactions WHERE client_id = ? AND currency_code = ? AND DATE(created_at) < ? ORDER BY created_at DESC LIMIT 1");
        $date_ref = !empty($filter_start_date) ? $filter_start_date : date('Y-m-d');
        $opening_q->bind_param("iss", $client_id, $currency, $date_ref);
        $opening_q->execute();
        $res = $opening_q->get_result()->fetch_assoc();
        $opening_balances[$currency] = $res ? (float)$res['balance_after'] : 0.00;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo ($trx_id > 0) ? "وصل مالي - $client_name" : "كشف حساب - $client_name"; ?></title>
    <style>
        body { font-family: 'Arial', sans-serif; background: #f4f7f6; margin: 0; padding: 20px; direction: rtl; }
        .invoice-container { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin: auto; max-width: 900px; }
        .header-section { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
        .table-ledger { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table-ledger th, .table-ledger td { border: 1px solid #ddd; padding: 8px; text-align: right; font-size: 13px; }
        .table-ledger th { background: #f8f9fa; }
        .signatures-container { display: flex; justify-content: space-between; margin-top: 50px; padding: 20px 0; }
        .sig-box { text-align: center; width: 30%; }
        .sig-line { margin-top: 40px; border-top: 1px dashed #333; padding-top: 5px; font-weight: bold; font-size: 14px; }
        .stamp-circle { width: 85px; height: 85px; border: 2px dotted #aaa; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; color: #aaa; font-size: 12px; }
        .no-print-zone { text-align: center; margin-bottom: 20px; }
        .btn { padding: 10px 20px; cursor: pointer; border: none; border-radius: 5px; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-print { background: #2980b9; color: #fff; }
        @media print { .no-print-zone { display: none !important; } body { background: #fff; padding: 0; } .invoice-container { box-shadow: none; border: none; width: 100% !important; max-width: 100% !important; margin: 0 !important; } @page { size: auto; margin: 10mm; } }
    </style>
</head>
<body>

<div class="no-print-zone">
    <button class="btn btn-print" onclick="window.print()">طباعة</button>
    <a href="client_details.php?id=<?php echo $client_id; ?>" class="btn" style="background:#7f8c8d; color:#fff;">عودة</a>
</div>

<div class="invoice-container">
    <div class="header-section">
        <div class="company-info">
            <h2 style="margin:0;"><?php echo htmlspecialchars($company_name); ?></h2>
            <p style="margin:5px 0; font-size:14px; color:#666;">إيصال مالي رسمي</p>
        </div>
        <div style="text-align:center;">
            <h3 style="margin:0;"><?php echo ($trx_id > 0) ? "وصل مالي رقم #$trx_id" : "كشف حساب عميل"; ?></h3>
            <?php if($trx_id == 0): ?>
                <small>من: <?php echo $filter_start_date ?: 'البداية'; ?> إلى: <?php echo $filter_end_date ?: 'الآن'; ?></small>
            <?php endif; ?>
        </div>
        <div style="text-align:left;">
            <p style="margin:0;"><strong>العميل:</strong> <?php echo $client_name; ?></p>
            <p style="margin:0;"><strong>التاريخ:</strong> <?php echo date('Y-m-d'); ?></p>
        </div>
    </div>

    <?php 
    $transactions_by_currency = [];
    if ($transactions_data->num_rows > 0) {
        $transactions_data->data_seek(0);
        while ($row = $transactions_data->fetch_assoc()) {
            $transactions_by_currency[$row['currency_code']][] = $row;
        }
    }

    foreach ($supported_currencies as $currency): 
        if ($trx_id > 0 && !isset($transactions_by_currency[$currency])) continue;
        $current_balance = $opening_balances[$currency] ?? 0.00;
    ?>
    <div style="margin-bottom: 30px;">
        <h4 style="background: #eee; padding: 5px 10px; margin-bottom:0;">عملة: <?php echo $currency; ?></h4>
        <table class="table-ledger">
            <thead>
                <?php if($trx_id == 0): ?>
                <tr>
                    <th colspan="4">الرصيد الافتتاحي</th>
                    <th colspan="2" style="text-align:center; background:#f0f0f0;"><strong><?php echo number_format($current_balance, 2); ?></strong></th>
                </tr>
                <?php endif; ?>
                <tr>
                    <th style="width:15%">التاريخ</th>
                    <th style="width:35%">الملاحظات</th>
                    <th style="width:12%">سحب</th>
                    <th style="width:12%">إيداع</th>
                    <th style="width:14%">الرصيد النهائي</th>
                    <th style="width:12%">الموظف</th>
                </tr>
            </thead>
            <tbody>
                <?php if (isset($transactions_by_currency[$currency])): 
                    foreach ($transactions_by_currency[$currency] as $row):
                        $is_deposit = (strtoupper($row['transaction_type']) == 'DEPOSIT');
                        $amt = (float)$row['amount'];
                ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($row['notes']); ?></td>
                    <td style="color:red; font-weight:bold;"><?php echo !$is_deposit ? number_format($amt, 2) : '-'; ?></td>
                    <td style="color:green; font-weight:bold;"><?php echo $is_deposit ? number_format($amt, 2) : '-'; ?></td>
                    <td><strong><?php echo number_format($row['balance_after'], 2); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="6" style="text-align:center; color:#999;">لا توجد حركات لعرضها</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <div class="signatures-container">
        <div class="sig-box">
            <div class="sig-line">توقيع الموظف</div>
        </div>
        <div class="sig-box">
            <div class="stamp-circle">ختم الشركة</div>
        </div>
        <div class="sig-box">
            <div class="sig-line">توقيع العميل</div>
        </div>
    </div>
</div>
</body>
</html>