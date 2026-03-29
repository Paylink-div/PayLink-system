<?php
// edit_company.php - تعديل بيانات الشركة وصلاحيات الأقسام
include 'db_connect.php'; 

// 1. حماية الصفحة: التأكد من أن الداخل هو السوبر أدمن
if (!isset($_SESSION['super_admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$success = "";
$error = "";

// تعريف قائمة الأقسام المتاحة (يجب أن تطابق الموجودة في add_company)
$system_features = [
    'exchange_process'           => ['name' => 'إجراء عمليات الصرف', 'icon' => 'fa-sync-alt'],
    'invoices_log'               => ['name' => 'سجل الفواتير والعمليات', 'icon' => 'fa-receipt'],
    'treasury_balance_management' => ['name' => 'إدارة أرصدة الخزينة', 'icon' => 'fa-vault'],
    'company_treasury'           => ['name' => 'الحسابات البنكية للشركة', 'icon' => 'fa-university'],
    'currency_balance_management'=> ['name' => 'إدارة العملات والأرصدة', 'icon' => 'fa-coins'],
    'exchange_rate_settings'      => ['name' => 'إدارة أسعار الصرف', 'icon' => 'fa-chart-line'],
    'clients_management'         => ['name' => 'إدارة العملاء', 'icon' => 'fa-user-friends'],
    'branch_management'          => ['name' => 'إدارة الفروع', 'icon' => 'fa-store-alt'],
    'users_management'           => ['name' => 'إدارة المستخدمين والصلاحيات', 'icon' => 'fa-user-shield'],
    'comprehensive_reports'      => ['name' => 'تقارير الأداء الشاملة', 'icon' => 'fa-file-signature']
];

// 2. جلب بيانات الشركة المراد تعديلها
if (!isset($_GET['id'])) {
    die("معرف الشركة غير موجود.");
}

$company_id = $_GET['id'];
$stmt = $master_conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();

if (!$company) {
    die("الشركة غير موجودة في النظام.");
}

// فك تشفير الميزات الحالية للشركة
$current_features = json_decode($company['enabled_features'], true) ?: [];

// 3. معالجة تحديث البيانات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $c_name   = $_POST['company_name'];
    $status   = $_POST['status']; // إضافة حالة الشركة (نشط/موقف)
    $selected_features = isset($_POST['features']) ? json_encode($_POST['features'], JSON_UNESCAPED_UNICODE) : '[]';

    $update_stmt = $master_conn->prepare("UPDATE companies SET company_name = ?, status = ?, enabled_features = ? WHERE id = ?");
    $update_stmt->bind_param("sssi", $c_name, $status, $selected_features, $company_id);
    
    if ($update_stmt->execute()) {
        $success = "تم تحديث بيانات الشركة بنجاح!";
        // تحديث البيانات المعروضة في الصفحة بعد الحفظ
        $current_features = json_decode($selected_features, true);
        $company['company_name'] = $c_name;
        $company['status'] = $status;
    } else {
        $error = "حدث خطأ أثناء التحديث: " . $master_conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعديل شركة | <?php echo htmlspecialchars($company['company_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Cairo', sans-serif; background: #0f172a; color: #f8fafc; margin: 0; display: flex; }
        .sidebar { width: 260px; background: #1e293b; height: 100vh; position: fixed; right: 0; padding: 25px; border-left: 1px solid #334155; box-sizing: border-box; }
        .main-content { flex: 1; margin-right: 260px; padding: 40px; }
        
        .card { background: #1e293b; padding: 35px; border-radius: 24px; border: 1px solid #334155; max-width: 900px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .form-group { margin-bottom: 25px; }
        label.main-label { display: block; margin-bottom: 10px; color: #38bdf8; font-weight: 700; font-size: 1rem; }
        
        input[type="text"], select { width: 100%; padding: 14px; border-radius: 12px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; font-family: 'Cairo'; transition: 0.3s; }
        
        .features-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px; margin-top: 15px; }
        .feature-option { background: #0f172a; border: 1px solid #334155; padding: 15px; border-radius: 15px; display: flex; align-items: center; cursor: pointer; transition: 0.3s; }
        .feature-option:hover { border-color: #38bdf8; }
        .feature-option input { width: 18px; height: 18px; margin-left: 12px; }
        
        /* تلوين الميزات المختارة حالياً */
        .feature-option.is-checked { border-color: #38bdf8; background: rgba(56, 189, 248, 0.05); }

        .btn-update { width: 100%; padding: 16px; background: #22c55e; border: none; border-radius: 12px; color: #0f172a; font-weight: 800; font-size: 1.1rem; cursor: pointer; transition: 0.3s; margin-top: 30px; }
        .btn-update:hover { background: #4ade80; transform: translateY(-3px); }
        
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 25px; }
        .alert-success { background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2); }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .status-active { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
        .status-inactive { background: rgba(239, 68, 68, 0.2); color: #f87171; }
    </style>
</head>
<body>

<div class="sidebar">
    <div style="text-align: center; margin-bottom: 30px;">
        <h2 style="color: #38bdf8; margin: 0;">PAYLINK</h2>
        <small style="color: #64748b;">تعديل بيانات الشركة</small>
    </div>
    <hr style="border: 0; border-top: 1px solid #334155; margin: 20px 0;">
    <a href="admin_dashboard.php" style="color: #f8fafc; text-decoration: none; display: flex; align-items: center; gap: 10px; padding: 12px; border-radius: 10px; background: rgba(255,255,255,0.05);">
        <i class="fas fa-arrow-right"></i> عودة للوحة التحكم
    </a>
</div>

<div class="main-content">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h2 style="margin: 0; color: #f8fafc;">إدارة شركة: <?php echo htmlspecialchars($company['company_name']); ?></h2>
            <span class="status-badge <?php echo $company['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                <?php echo $company['status'] == 'active' ? 'نشط' : 'موقوف'; ?>
            </span>
        </div>

        <?php if($success): ?> <div class="alert alert-success"><i class="fas fa-check-circle ml-2"></i> <?php echo $success; ?></div> <?php endif; ?>
        <?php if($error): ?> <div class="alert alert-error"><i class="fas fa-exclamation-circle ml-2"></i> <?php echo $error; ?></div> <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="main-label">اسم الشركة</label>
                <input type="text" name="company_name" value="<?php echo htmlspecialchars($company['company_name']); ?>" required>
            </div>

            <div class="form-group">
                <label class="main-label">حالة الحساب</label>
                <select name="status">
                    <option value="active" <?php echo $company['status'] == 'active' ? 'selected' : ''; ?>>نشط (يسمح بالدخول)</option>
                    <option value="inactive" <?php echo $company['status'] == 'inactive' ? 'selected' : ''; ?>>موقوف (يمنع الدخول)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="main-label">الرابط (Subdomain)</label>
                <input type="text" value="<?php echo $company['subdomain']; ?>.paylink.test" disabled style="opacity: 0.6; cursor: not-allowed;">
                <small style="color: #64748b;">لا يمكن تغيير الرابط بعد إنشاء الشركة لارتباطه بقاعدة البيانات.</small>
            </div>

            <div class="form-group">
                <label class="main-label">تعديل الأقسام المفعلة</label>
                <div class="features-grid">
                    <?php foreach($system_features as $key => $info): 
                        $checked = in_array($key, $current_features) ? 'checked' : '';
                        $active_class = $checked ? 'is-checked' : '';
                    ?>
                        <label class="feature-option <?php echo $active_class; ?>">
                            <input type="checkbox" name="features[]" value="<?php echo $key; ?>" <?php echo $checked; ?>>
                            <i class="fas <?php echo $info['icon']; ?> ml-2"></i>
                            <span><?php echo $info['name']; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn-update">تحديث البيانات وحفظ التغييرات</button>
        </form>
    </div>
</div>

<script>
    // تحسين التفاعل البصري
    document.querySelectorAll('.feature-option input').forEach(input => {
        input.addEventListener('change', function() {
            if(this.checked) {
                this.parentElement.classList.add('is-checked');
            } else {
                this.parentElement.classList.remove('is-checked');
            }
        });
    });
</script>

</body>
</html>
