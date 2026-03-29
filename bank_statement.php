<?php
session_start();
include 'db_connect.php'; 
require_once 'acl_functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 🛑 التحقق من الصلاحية: يجب أن يمتلك المستخدم صلاحية عرض كشوف الحساب
require_permission($conn, 'CAN_VIEW_BANK_STATEMENTS'); 

// ------------------------------------
// 1. تحديد متغيرات البحث
// ------------------------------------
$account_id = $conn->real_escape_string($_GET['account_id'] ?? 0);
// تعيين التواريخ الافتراضية للشهر الحالي
$start_date = $conn->real_escape_string($_GET['start_date'] ?? date('Y-m-01'));
$end_date   = $conn->real_escape_string($_GET['end_date'] ?? date('Y-m-d'));

$account_info = null;
$transactions = [];

// جلب جميع الحسابات لملء قائمة الاختيارات
$accounts_query = "SELECT id, bank_name, account_number, currency_code FROM bank_accounts WHERE is_active = 1";
$accounts_result = $conn->query($accounts_query);
$accounts = [];
while ($row = $accounts_result->fetch_assoc()) {
    $accounts[] = $row;
}

// ------------------------------------
// 2. منطق جلب كشف الحساب
// ------------------------------------
if ($account_id > 0) {
    // جلب معلومات الحساب المحدد
    $stmt_info = $conn->prepare("SELECT bank_name, account_number, currency_code FROM bank_accounts WHERE id = ?");
    $stmt_info->bind_param("i", $account_id);
    $stmt_info->execute();
    $account_info = $stmt_info->get_result()->fetch_assoc();

    // جلب حركات الحساب ضمن الفترة الزمنية
    $query = "
        SELECT 
            bt.created_at,
            bt.transaction_type,
            bt.amount,
            bt.balance_after,
            bt.notes,
            u.full_name AS user_name
        FROM 
            bank_transactions bt
        LEFT JOIN
            users u ON bt.user_id = u.id
        WHERE 
            bt.account_id = '$account_id' 
            AND bt.created_at >= '$start_date 00:00:00' 
            AND bt.created_at <= '$end_date 23:59:59'
        ORDER BY 
            bt.created_at ASC
    ";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
}

// ------------------------------------
// 3. منطق التصدير إلى Excel
// ------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'excel' && $account_id > 0) {
    // يجب إعادة تنفيذ الاستعلام لجلب البيانات بشكل خام للتصدير
    $export_result = $conn->query($query);

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="Statement_' . $account_id . '_' . date('Ymd') . '.xls"');
    
    // إنشاء جدول HTML بسيط للتصدير
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Arabic compatibility in Excel
    echo "<table>";
    echo "<tr><th colspan='6'>كشف حساب: " . htmlspecialchars($account_info['bank_name'] . ' - ' . $account_info['account_number']) . "</th></tr>";
    echo "<tr><th colspan='6'>الفترة: من $start_date إلى $end_date</th></tr>";
    echo "<tr><th>التاريخ</th><th>نوع العملية</th><th>المبلغ</th><th>الرصيد بعد العملية</th><th>الملاحظات</th><th>الموظف</th></tr>";

    while ($row = $export_result->fetch_assoc()) {
        $type_ar = match($row['transaction_type']) {
            'DEPOSIT' => 'إيداع (دائن)',
            'WITHDRAWAL' => 'سحب (مدين)',
            'INTERNAL_TRANSFER_IN' => 'تحويل داخلي (دائن)',
            'INTERNAL_TRANSFER_OUT' => 'تحويل داخلي (مدين)',
            'EXTERNAL_TRANSFER_IN' => 'تحويل خارجي (دائن)',
            'EXTERNAL_TRANSFER_OUT' => 'تحويل خارجي (مدين)',
            default => $row['transaction_type']
        };

        echo "<tr>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "<td>" . $type_ar . "</td>";
        echo "<td>" . number_format($row['amount'], 2) . "</td>";
        echo "<td>" . number_format($row['balance_after'], 2) . "</td>";
        echo "<td>" . htmlspecialchars($row['notes']) . "</td>";
        echo "<td>" . htmlspecialchars($row['user_name']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>كشف حساب بنكي</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 1200px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { border-bottom: 2px solid #17a2b8; padding-bottom: 10px; color: #17a2b8; }
        .filter-form { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; display: flex; gap: 15px; align-items: flex-end; }
        .filter-form label { font-weight: bold; margin-bottom: 5px; display: block; }
        .filter-form select, .filter-form input[type="date"] { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .filter-form button { background-color: #28a745; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .filter-form a { background-color: #17a2b8; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: right; }
        th { background-color: #e9ecef; }
        .deposit { color: #28a745; font-weight: bold; }
        .withdrawal { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 كشف الحساب المصرفي التفصيلي</h1>
        
        <form class="filter-form" action="bank_statement.php" method="GET">
            <div>
                <label for="account_id">الحساب البنكي:</label>
                <select id="account_id" name="account_id" required>
                    <option value="">-- اختر حساب --</option>
                    <?php 
                    $selected_account_name = '';
                    foreach ($accounts as $account): 
                        $selected = ($account['id'] == $account_id) ? 'selected' : '';
                        if ($selected) $selected_account_name = $account['bank_name'] . ' - ' . $account['account_number'];
                    ?>
                        <option value="<?php echo $account['id']; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($account['bank_name'] . ' | ' . $account['account_number'] . ' (' . $account['currency_code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="start_date">من تاريخ:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" required>
            </div>
            <div>
                <label for="end_date">إلى تاريخ:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" required>
            </div>
            <button type="submit">عرض الكشف</button>
            <?php if ($account_id > 0): ?>
                <a href="bank_statement.php?account_id=<?php echo $account_id; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&export=excel">
                    تصدير لـ Excel
                </a>
            <?php endif; ?>
        </form>

        <?php if ($account_info): ?>
        <h2>كشف حساب: <?php echo htmlspecialchars($account_info['bank_name'] . ' - ' . $account_info['account_number'] . ' (' . $account_info['currency_code'] . ')'); ?></h2>
        
        <table>
            <thead>
                <tr>
                    <th>التاريخ والوقت</th>
                    <th>نوع العملية</th>
                    <th>المبلغ</th>
                    <th>الرصيد بعد العملية</th>
                    <th>الملاحظات</th>
                    <th>الموظف</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($transactions)): ?>
                    <?php foreach ($transactions as $trx): 
                        $is_credit = in_array($trx['transaction_type'], ['DEPOSIT', 'INTERNAL_TRANSFER_IN', 'EXTERNAL_TRANSFER_IN']);
                        $class = $is_credit ? 'deposit' : 'withdrawal';
                        
                        $type_ar = match($trx['transaction_type']) {
                            'DEPOSIT' => 'إيداع (دائن)',
                            'WITHDRAWAL' => 'سحب (مدين)',
                            'INTERNAL_TRANSFER_IN' => 'تحويل داخلي (دائن)',
                            'INTERNAL_TRANSFER_OUT' => 'تحويل داخلي (مدين)',
                            'EXTERNAL_TRANSFER_IN' => 'تحويل خارجي (دائن)',
                            'EXTERNAL_TRANSFER_OUT' => 'تحويل خارجي (مدين)',
                            default => $trx['transaction_type']
                        };
                    ?>
                    <tr>
                        <td><?php echo $trx['created_at']; ?></td>
                        <td><?php echo $type_ar; ?></td>
                        <td class="<?php echo $class; ?>"><?php echo number_format($trx['amount'], 2); ?></td>
                        <td><?php echo number_format($trx['balance_after'], 2); ?></td>
                        <td><?php echo htmlspecialchars($trx['notes'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($trx['user_name'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center;">لا توجد حركات مسجلة في هذه الفترة الزمنية.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php elseif ($account_id > 0): ?>
            <p style="text-align: center; color: red;">لم يتم العثور على بيانات الحساب أو لا توجد حركات في الفترة المحددة.</p>
        <?php endif; ?>

        <p style="margin-top: 30px;"><a href="bank_accounts.php">العودة لملخص الحسابات البنكية</a></p>

    </div>
</body>
</html>
<?php $conn->close(); ?>