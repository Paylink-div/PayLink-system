<?php
session_start();
include 'db_connect.php'; 
require_once 'acl_functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 🛑 التحقق من الصلاحية: يجب أن يمتلك المستخدم صلاحية إدارة الحسابات
// (هذه الصلاحية عادة للمديرين أو الإداريين)
require_permission($conn, 'CAN_MANAGE_BANK_ACCOUNTS'); 

$message = '';

// ------------------------------------
// منطق إضافة/تعديل الحسابات
// ------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_account'])) {
    $bank_name = $conn->real_escape_string($_POST['bank_name']);
    $account_number = $conn->real_escape_string($_POST['account_number']);
    $currency_code = $conn->real_escape_string($_POST['currency_code']);
    $branch_name = $conn->real_escape_string($_POST['branch_name']);
    
    // قيمة الرصيد الافتراضية عند الإضافة
    $initial_balance = floatval($_POST['initial_balance'] ?? 0); 

    $sql = "INSERT INTO bank_accounts (bank_name, account_number, currency_code, branch_name, current_balance) 
            VALUES ('$bank_name', '$account_number', '$currency_code', '$branch_name', $initial_balance)";

    if ($conn->query($sql) === TRUE) {
        $message = "<div style='color: green;'>✅ تم إضافة الحساب البنكي بنجاح.</div>";
    } else {
        $message = "<div style='color: red;'>❌ خطأ في إضافة الحساب: " . $conn->error . "</div>";
    }
}

// ------------------------------------
// منطق عرض جميع الحسابات (مع الرصيد الحالي)
// ------------------------------------
$accounts_query = "SELECT id, bank_name, account_number, currency_code, branch_name, current_balance FROM bank_accounts WHERE is_active = 1 ORDER BY bank_name";
$accounts_result = $conn->query($accounts_query);

// قائمة العملات المتاحة (يمكنك تعديلها حسب نظامك)
$currencies = ['LYD', 'USD', 'EUR', 'GBP'];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدارة الحسابات البنكية</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #007bff; padding-bottom: 10px; color: #007bff; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: right; }
        th { background-color: #f2f2f2; }
        .balance { font-weight: bold; color: #28a745; font-size: 1.1em; }
        .form-section { margin-top: 30px; padding: 20px; border: 1px solid #ccc; border-radius: 5px; background-color: #f9f9f9; }
        .form-section input[type="text"], .form-section select, .form-section input[type="number"] { width: 100%; padding: 10px; margin: 5px 0 15px 0; display: inline-block; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .form-section button { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; float: left; }
        .form-section button:hover { background-color: #0056b3; }
        .action-link { margin-left: 10px; color: #17a2b8; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏦 إدارة الحسابات البنكية للشركة</h1>
        <?php echo $message; ?>

        <h2>عرض الحسابات الحالية</h2>
        <table>
            <thead>
                <tr>
                    <th>المصرف</th>
                    <th>رقم الحساب</th>
                    <th>الفرع</th>
                    <th>العملة</th>
                    <th>الرصيد الحالي</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($accounts_result->num_rows > 0): ?>
                    <?php while ($account = $accounts_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($account['bank_name']); ?></td>
                            <td><?php echo htmlspecialchars($account['account_number']); ?></td>
                            <td><?php echo htmlspecialchars($account['branch_name']); ?></td>
                            <td><?php echo htmlspecialchars($account['currency_code']); ?></td>
                            <td class="balance"><?php echo number_format($account['current_balance'], 2) . ' ' . $account['currency_code']; ?></td>
                            <td>
                                <a href="bank_statement.php?account_id=<?php echo $account['id']; ?>" class="action-link">عرض كشف الحساب</a>
                                </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center;">لا توجد حسابات بنكية مسجلة حالياً.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="form-section">
            <h3>➕ إضافة حساب بنكي جديد</h3>
            <form action="bank_accounts.php" method="POST">
                <input type="hidden" name="add_account" value="1">

                <label for="bank_name">اسم المصرف:</label>
                <input type="text" id="bank_name" name="bank_name" required>

                <label for="account_number">رقم الحساب:</label>
                <input type="text" id="account_number" name="account_number" required>
                
                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label for="currency_code">نوع العملة:</label>
                        <select id="currency_code" name="currency_code" required>
                            <?php foreach ($currencies as $code): ?>
                                <option value="<?php echo $code; ?>"><?php echo $code; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label for="initial_balance">الرصيد الافتتاحي:</label>
                        <input type="number" id="initial_balance" name="initial_balance" step="0.01" value="0.00">
                    </div>
                </div>

                <label for="branch_name">اسم الفرع:</label>
                <input type="text" id="branch_name" name="branch_name">
                
                <button type="submit">حفظ الحساب</button>
                <div style="clear: both;"></div>
            </form>
        </div>

    </div>
</body>
</html>
<?php $conn->close(); ?>