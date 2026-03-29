<?php
// reset_db.php - النسخة النهائية المتوافقة مع بنية جداولك ونظام SaaS

if (session_status() == PHP_SESSION_NONE) session_start();

require_once 'db_connect.php'; 

// التحقق من الصلاحيات
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'مدير عام' || !isset($_SESSION['company_id'])) {
    die("⚠ وصول ممنوع.");
}

$company_id = intval($_SESSION['company_id']);
$current_user_id = intval($_SESSION['user_id']);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_reset'])) {
    
    // قائمة الجداول المحتمل وجودها
    $tables = [
        'transactions', 'treasury_transactions', 'exchange_rates', 
        'bank_transactions', 'bank_movements', 'bank_accounts', 
        'company_bank_accounts', 'clients', 'client_transactions', 
        'client_balances', 'treasury_balances', 'currencies_balances', 
        'audit_log', 'daily_reports', 'daily_closures', 'branches'
    ];

    $conn->begin_transaction();

    try {
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");

        foreach ($tables as $table) {
            // التحقق هل الجدول موجود وهل يحتوي على عمود company_id
            $check_column = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'company_id'");
            
            if ($check_column && $check_column->num_rows > 0) {
                // إذا كان العمود موجود، نحذف بيانات الشركة الحالية فقط
                $conn->query("DELETE FROM `$table` WHERE company_id = $company_id");
            } else {
                // إذا لم يوجد عمود company_id، قد يكون الجدول مرتبطاً عبر id الشركة بطريقة أخرى
                // أو أنه جدول إعدادات عامة لا يجب مسحه (مثل العملات الأساسية)
                continue; 
            }
        }

        // مسح مستخدمي هذه الشركة فقط ما عدا المدير الحالي
        $conn->query("DELETE FROM users WHERE company_id = $company_id AND id != $current_user_id");

        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $conn->commit();
        $message = "✅ تم تصفير بيانات شركتك بنجاح دون التأثير على النظام العام.";

    } catch (Exception $e) {
        $conn->rollback();
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $error = "❌ فشلت العملية: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تصفير البيانات - SaaS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Arial', sans-serif; }
        .reset-card { max-width: 600px; margin: 50px auto; border-radius: 15px; overflow: hidden; }
        .card-header { background: #d9534f; color: white; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card reset-card shadow">
            <div class="card-header text-center">
                <h3><i class="fas fa-trash-alt"></i> تصفير بيانات المؤسسة</h3>
            </div>
            <div class="card-body p-4">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                    <a href="index.php" class="btn btn-primary w-100">العودة للرئيسية</a>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if (!$message): ?>
                    <div class="alert alert-warning">
                        <strong>تنبيه:</strong> سيتم حذف كافة العمليات والأرصدة التابعة لشركتك فقط. هذا الإجراء لا يمكن التراجع عنه.
                    </div>
                    <form method="POST">
                        <input type="hidden" name="confirm_reset" value="1">
                        <button type="submit" class="btn btn-danger btn-lg w-100" onclick="return confirm('هل أنت متأكد تماماً؟');">
                            تنفيذ المسح الشامل للبيانات
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>