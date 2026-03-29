<?php
// add_company.php - النسخة النهائية المحدثة لضمان إضافة المدير تلقائياً
include 'db_connect.php'; 

// 1. حماية الصفحة
if (!isset($_SESSION['super_admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

// تعريف ميزات النظام
$system_features = [
    'exchange_process'           => ['name' => 'إجراء عمليات الصرف', 'icon' => 'fa-sync-alt'],
    'invoices_log'               => ['name' => 'سجل الفواتير والعمليات', 'icon' => 'fa-receipt'],
    'treasury_balance_management' => ['name' => 'إدارة أرصدة الخزينة', 'icon' => 'fa-vault'],
    'company_treasury'           => ['name' => 'الحسابات البنكية للشركة', 'icon' => 'fa-university'],
    'currency_balance_management'=> ['name' => 'إدارة العملات والأرصدة', 'icon' => 'fa-coins'],
    'exchange_rate_settings'      => ['name' => 'إدارة أسعار الصرف', 'icon' => 'fa-chart-line'],
    'clients_management'         => ['name' => 'إدارة العملاء', 'icon' => 'fa-user-friends'],
    'branch_management'          => ['name' => 'إدارة الفروع', 'icon' => 'fa-store-alt'],
    'users_management'           => ['name' => 'إدارة المستخدمين', 'icon' => 'fa-user-shield'],
    'comprehensive_reports'      => ['name' => 'تقارير الأداء الشاملة', 'icon' => 'fa-file-signature']
];

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $c_name   = $_POST['company_name'];
    $sub      = strtolower(trim($_POST['subdomain']));
    $db_name  = "paylink_db_" . $sub;
    $source_db = "paylink_bd"; 

    $selected_features = isset($_POST['features']) ? json_encode($_POST['features'], JSON_UNESCAPED_UNICODE) : '[]';

    // التحقق من التكرار
    $check = $master_conn->prepare("SELECT id FROM companies WHERE subdomain = ?");
    $check->bind_param("s", $sub);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $error = "عذراً، هذا الرابط (Subdomain) مستخدم مسبقاً لشركة أخرى!";
    } else {
        // 1. إنشاء قاعدة البيانات الجديدة
        if ($master_conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
            
            // 2. جلب كافة الجداول من القاعدة الأم
            $tables_query = $master_conn->query("SHOW TABLES FROM `$source_db` ");
            
            if ($tables_query) {
                while ($row = $tables_query->fetch_array()) {
                    $table_name = $row[0];
                    if ($table_name == 'companies' || $table_name == 'super_admins') continue; 
                    // 3. نسخ هيكلة الجدول
                    $master_conn->query("CREATE TABLE `$db_name`.`$table_name` LIKE `$source_db`.`$table_name` ");
                }

                // --- إضافة المدير العام تلقائياً للشركة الجديدة ---
                $admin_user = 'admin';
                $admin_pass = password_hash('123456', PASSWORD_DEFAULT);
                $admin_name = 'المدير العام';
                
                // تم تعديل user_role إلى 'مدير عام' لتطابق قائمة الـ ENUM في قاعدتك
                // وتمت إضافة phone_number كقيمة فارغة لضمان عدم حدوث خطأ
                $insert_admin = "INSERT INTO `$db_name`.`users` 
                                (`username`, `password_hash`, `full_name`, `user_role`, `is_active`, `permissions_json`, `created_at`, `phone_number`) 
                                VALUES (?, ?, ?, 'مدير عام', 1, ?, NOW(), '')";
                
                $stmt_admin = $master_conn->prepare($insert_admin);
                if ($stmt_admin) {
                    $stmt_admin->bind_param("ssss", $admin_user, $admin_pass, $admin_name, $selected_features);
                    if (!$stmt_admin->execute()) {
                        $error = "فشل إنشاء مستخدم المدير: " . $stmt_admin->error;
                    }
                    $stmt_admin->close();
                }
                // ----------------------------------------------

                // 4. حفظ بيانات الشركة في السجل المركزي
                $sql = "INSERT INTO companies (company_name, subdomain, db_name, enabled_features, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())";
                $stmt = $master_conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("ssss", $c_name, $sub, $db_name, $selected_features);
                    if ($stmt->execute()) {
                        $success = "بفضل الله، تم إنشاء الشركة بنجاح، وتجهيز حساب المدير (admin) بكلمة سر (123456).";
                    } else {
                        $error = "حدث خطأ أثناء تسجيل الشركة في النظام المركزي: " . $master_conn->error;
                    }
                    $stmt->close();
                }
            } else {
                $error = "لم نتمكن من الوصول إلى جداول قاعدة البيانات الأم للنسخ.";
            }
        } else {
             $error = "فشل إنشاء قاعدة البيانات الجديدة: " . $master_conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إضافة شركة جديدة | PayLink Central</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Cairo', sans-serif; background: #0f172a; color: #f8fafc; margin: 0; display: flex; }
        .sidebar { width: 260px; background: #1e293b; height: 100vh; position: fixed; right: 0; padding: 25px; border-left: 1px solid #334155; box-sizing: border-box; }
        .main-content { flex: 1; margin-right: 260px; padding: 40px; }
        .card { background: #1e293b; padding: 35px; border-radius: 24px; border: 1px solid #334155; max-width: 800px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .form-group { margin-bottom: 25px; }
        label { display: block; margin-bottom: 10px; color: #38bdf8; font-weight: bold; }
        input[type="text"] { width: 100%; padding: 14px; border-radius: 12px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px; margin-top: 15px; }
        .feature-option { background: #0f172a; padding: 15px; border-radius: 15px; border: 1px solid #334155; display: flex; align-items: center; cursor: pointer; transition: 0.3s; }
        .feature-option:hover { border-color: #38bdf8; }
        .btn-save { width: 100%; padding: 16px; background: #38bdf8; border: none; border-radius: 12px; color: #0f172a; font-weight: 800; font-size: 1.1rem; cursor: pointer; transition: 0.3s; margin-top: 30px; }
        .btn-save:hover { background: #7dd3fc; transform: translateY(-2px); }
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 25px; border: 1px solid transparent; }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #f87171; border-color: rgba(239, 68, 68, 0.2); }
        .alert-success { background: rgba(34, 197, 94, 0.1); color: #4ade80; border-color: rgba(34, 197, 94, 0.2); }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2 style="color: #38bdf8; text-align: center;">PAYLINK</h2>
        <hr style="border: 0; border-top: 1px solid #334155; margin: 20px 0;">
        <a href="admin_dashboard.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 10px; padding: 10px;">
            <i class="fas fa-chart-line"></i> لوحة التحكم الرئيسية
        </a>
    </div>
    <div class="main-content">
        <div class="card">
            <h2 style="margin-top: 0;">إضافة شركة جديدة وتجهيز النظام</h2>
            <p style="color: #94a3b8; margin-bottom: 30px;">سيقوم النظام بإنشاء قاعدة بيانات مخصصة ونسخ كافة الجداول وإضافة المدير آلياً.</p>

            <?php if($error): ?> <div class="alert alert-error"><i class="fas fa-times-circle"></i> <?php echo $error; ?></div> <?php endif; ?>
            <?php if($success): ?> <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div> <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>اسم الشركة التجاري</label>
                    <input type="text" name="company_name" placeholder="مثال: شركة المدار للصرافة" required>
                </div>
                <div class="form-group">
                    <label>الرابط المختصر (Subdomain)</label>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="text" name="subdomain" placeholder="مثال: almadar" required style="flex: 1;">
                        <span style="font-weight: bold; color: #64748b;">.paylink.test</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>تفعيل الأقسام للشركة</label>
                    <div class="features-grid">
                        <?php foreach($system_features as $key => $info): ?>
                            <label class="feature-option">
                                <input type="checkbox" name="features[]" value="<?php echo $key; ?>" checked style="margin-left: 10px;">
                                <i class="fas <?php echo $info['icon']; ?> ml-2" style="color: #94a3b8; width: 25px;"></i> 
                                <span style="font-size: 0.9rem;"><?php echo $info['name']; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="btn-save">بدء عملية التجهيز والإنشاء</button>
            </form>
        </div>
    </div>
</body>
</html>