<?php
// client_portal.php - بوابة العميل
session_start();
include 'db_connect.php';

$url_client_slug = $_GET['client_slug'] ?? '';
$clean_client_name = str_replace('_', ' ', $url_client_slug);

// التحقق من وجود العميل بالاسم فقط
$stmt_check = $conn->prepare("SELECT id, full_name FROM clients WHERE full_name = ? LIMIT 1");
$stmt_check->bind_param("s", $clean_client_name);
$stmt_check->execute();
$auth_result = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

if (!$auth_result) {
    die("<div style='text-align:center; margin-top:50px;'><h2>عذراً، الرابط غير صحيح.</h2></div>");
}

$_SESSION['client_id']   = $auth_result['id'];
$_SESSION['client_name'] = $auth_result['full_name'];
$client_id = $_SESSION['client_id'];
$supported_currencies = ['LYD', 'USD', 'EUR']; 

// جلب الأرصدة
$current_balances = array_fill_keys($supported_currencies, 0.00);
$bal_q = $conn->prepare("SELECT currency_code, current_balance FROM client_balances WHERE client_id = ?");
$bal_q->bind_param("i", $client_id);
$bal_q->execute();
$res = $bal_q->get_result();
while ($row = $res->fetch_assoc()) { $current_balances[$row['currency_code']] = (float)$row['current_balance']; }

// جلب الحركات
$stmt_trx = $conn->prepare("SELECT t.*, u.full_name AS user_name FROM client_transactions t JOIN users u ON t.user_id = u.id WHERE t.client_id = ? AND t.is_deleted = 0 ORDER BY t.created_at DESC");
$stmt_trx->bind_param("i", $client_id);
$stmt_trx->execute();
$client_transactions = $stmt_trx->get_result();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"><title>بوابة العميل</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="text-primary mb-4">أهلاً بك، <?php echo htmlspecialchars($_SESSION['client_name']); ?></h2>
    <div class="row mb-4">
        <?php foreach ($supported_currencies as $currency): ?>
            <div class="col-md-4 mb-3">
                <div class="card p-3 text-center shadow-sm">
                    <h5>رصيد <?php echo $currency; ?></h5>
                    <h3 class="font-weight-bold"><?php echo number_format($current_balances[$currency], 2); ?></h3>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">آخر الحركات المالية</div>
        <div class="table-responsive">
            <table class="table table-hover text-center">
                <thead><tr><th>التاريخ</th><th>النوع</th><th>المبلغ</th><th>الرصيد بعد</th></tr></thead>
                <tbody>
                <?php while($t = $client_transactions->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i', strtotime($t['created_at'])); ?></td>
                        <td><?php echo ($t['transaction_type'] == 'DEPOSIT') ? 'إيداع' : 'سحب'; ?></td>
                        <td><?php echo number_format($t['amount'], 2); ?></td>
                        <td><?php echo number_format($t['balance_after'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>