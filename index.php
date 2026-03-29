<?php
// index.php - لوحة التحكم الرئيسية (نسخة الالتزام بصلاحيات الشركة)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// التعديل للربط بالملف الصحيح الموحد
include 'db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if (!isset($_SESSION['user_role']) || !isset($_SESSION['full_name']) || !isset($_SESSION['user_permissions'])) {
    $user_query = $conn->prepare("SELECT u.full_name, u.user_role, u.branch_id, u.permissions_json 
                                 FROM users u
                                 WHERE u.id = ?"); 
    $user_query->bind_param("i", $user_id);
    $user_query->execute();
    $user_result = $user_query->get_result();

    if ($user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
        $_SESSION['full_name'] = $user_data['full_name'];
        $_SESSION['user_role'] = $user_data['user_role']; 
        $_SESSION['branch_id'] = $user_data['branch_id'] ?? 0;
        
        $permissions = json_decode($user_data['permissions_json'] ?? '[]', true);
        $_SESSION['user_permissions'] = is_array($permissions) ? $permissions : [];
    } else {
        session_destroy();
        header("Location: login.php");
        exit;
    }
    $user_query->close();
}

$current_user_name = $_SESSION['full_name'] ?? 'مستخدم غير معروف';
$current_user_role = $_SESSION['user_role'] ?? 'موظف'; 
$current_user_branch_id = $_SESSION['branch_id'] ?? 0;
$user_permissions = $_SESSION['user_permissions'] ?? [];

$operating_branch_id = $current_user_branch_id;
$operating_branch_name = "الفرع الخاص"; 

if ($current_user_role == 'مدير عام') {
    $operating_branch_id = 0;
    $operating_branch_name = "الرئيسي (عرض شامل لجميع الفروع)";
    $_SESSION['operating_branch_id'] = 0;
    $_SESSION['operating_branch_name'] = $operating_branch_name;
} else {
    $operating_branch_id = $current_user_branch_id;
    if ($current_user_branch_id != 0 && !isset($_SESSION['branch_name'])) {
        $branch_name_q = $conn->prepare("SELECT name FROM branches WHERE id = ?");
        $branch_name_q->bind_param("i", $current_user_branch_id);
        $branch_name_q->execute();
        $branch_result_data = $branch_name_q->get_result();
        if ($branch_result_data->num_rows > 0) {
            $_SESSION['branch_name'] = $branch_result_data->fetch_assoc()['name'];
        }
        $branch_name_q->close();
    }
    $operating_branch_name = $_SESSION['branch_name'] ?? 'غير محدد';
}

$sections = [
    ['title' => 'إجراء عملية صرف', 'desc' => 'بدء عملية صرف عملات جديدة', 'icon' => 'fas fa-exchange-alt', 'color' => '#f59e0b', 'link' => 'exchange.php', 'permission_key' => 'exchange_process'],
    ['title' => 'سجل الفواتير', 'desc' => 'مراجعة كافة العمليات السابقة', 'icon' => 'fas fa-file-invoice-dollar', 'color' => '#8b5cf6', 'link' => 'transactions_log.php', 'permission_key' => 'invoices_log'],
    ['title' => 'التقارير الشاملة', 'desc' => 'تحليل الأداء المالي والعمليات', 'icon' => 'fas fa-chart-pie', 'color' => '#3b82f6', 'link' => 'branch_report.php', 'permission_key' => 'comprehensive_reports'],
    ['title' => 'أرصدة الخزينة', 'desc' => 'إدارة وتسوية مبالغ الخزينة', 'icon' => 'fas fa-wallet', 'color' => '#ef4444', 'link' => 'treasury_adjustment.php', 'permission_key' => 'treasury_balance_management'],
    ['title' => 'إدارة العملاء', 'desc' => 'بيانات العملاء وحساباتهم', 'icon' => 'fas fa-user-friends', 'color' => '#64748b', 'link' => 'clients_management.php', 'permission_key' => 'clients_management'],
    ['title' => 'أسعار الصرف', 'desc' => 'تحديث أسعار البيع والشراء', 'icon' => 'fas fa-sync-alt', 'color' => '#f97316', 'link' => 'manage_rates.php', 'permission_key' => 'exchange_rate_settings'],
    ['title' => 'العملات والأرصدة', 'desc' => 'تهيئة العملات المتاحة بالنظام', 'icon' => 'fas fa-coins', 'color' => '#10b981', 'link' => 'currencies.php', 'permission_key' => 'currency_balance_management'],
    ['title' => 'خزينة البنك', 'desc' => 'متابعة حسابات الشركة البنكية', 'icon' => 'fas fa-university', 'color' => '#06b6d4', 'link' => 'company_treasury.php', 'permission_key' => 'company_treasury'],
    ['title' => 'المستخدمين', 'desc' => 'إدارة الصلاحيات والموظفين', 'icon' => 'fas fa-user-shield', 'color' => '#4b5563', 'link' => 'user_management.php', 'permission_key' => 'users_management'],
    ['title' => 'إدارة الفروع', 'desc' => 'إضافة وتعديل بيانات فروع الشركة', 'icon' => 'fas fa-store-alt', 'color' => '#7c3aed', 'link' => 'branch_management.php', 'permission_key' => 'branch_management'],
];

// دالة التحقق - تعتمد فقط وحصرياً على ما هو مفعّل في الصلاحيات
function can_access($permission_key, $user_permissions) {
    return in_array($permission_key, $user_permissions);
}

$display_company = defined('COMPANY_NAME') ? COMPANY_NAME : 'PayLink';
$pageTitle = 'لوحة التحكم - ' . $display_company;
include 'header.php'; 
?>

<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/all.min.css">

<style>
    :root { --main-bg: #f8fafc; --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
    body { background-color: var(--main-bg); font-family: 'Segoe UI', Tahoma, sans-serif; }
    .welcome-section { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 15px; padding: 30px; color: white; margin-bottom: 40px; box-shadow: var(--card-shadow); }
    .stat-badge { background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); padding: 5px 15px; border-radius: 50px; font-size: 0.9rem; }
    .modern-card { background: white; border: none; border-radius: 16px; padding: 25px; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: all 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-decoration: none !important; position: relative; }
    .modern-card:hover { transform: translateY(-8px); box-shadow: var(--card-shadow); }
    .icon-wrapper { width: 70px; height: 70px; border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 15px; }
    .card-title-text { color: #1e293b; font-weight: 700; font-size: 1.1rem; text-align: center; }
    .card-desc-text { color: #64748b; font-size: 0.8rem; text-align: center; margin: 0; }
    .card-arrow { position: absolute; bottom: 15px; left: 15px; color: #cbd5e1; }
</style>

<div class="container py-5" dir="rtl">
    <div class="welcome-section shadow-lg">
        <div class="row align-items-center">
            <div class="col-md-8 text-right">
                <h1 class="font-weight-bold mb-2">لوحة التحكم</h1>
                <p class="mb-3 opacity-75">مرحباً، <strong><?php echo htmlspecialchars($current_user_name); ?></strong>.</p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="stat-badge ml-2"><i class="fas fa-user-tag ml-1"></i> الدور: <?php echo htmlspecialchars($current_user_role); ?></span>
                    <span class="stat-badge"><i class="fas fa-map-marker-alt ml-1"></i> الفرع: <?php echo htmlspecialchars($operating_branch_name); ?></span>
                </div>
            </div>
            <div class="col-md-4 text-left d-none d-md-block">
                <i class="fas fa-chart-line fa-5x opacity-25"></i>
            </div>
        </div>
    </div>

    <div class="row">
        <?php foreach ($sections as $section): 
            // التعديل الجوهري: استخدام دالة can_access للتحقق من الميزة المحددة للشركة فقط
            if (!can_access($section['permission_key'], $user_permissions)) continue;
        ?>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-4">
                <a href="<?php echo htmlspecialchars($section['link']); ?>" class="modern-card">
                    <div class="icon-wrapper" style="background-color: <?php echo $section['color']; ?>15; color: <?php echo $section['color']; ?>;">
                        <i class="<?php echo htmlspecialchars($section['icon']); ?>"></i>
                    </div>
                    <h5 class="card-title-text"><?php echo htmlspecialchars($section['title']); ?></h5>
                    <p class="card-desc-text"><?php echo htmlspecialchars($section['desc']); ?></p>
                    <div class="card-arrow"><i class="fas fa-chevron-left"></i></div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>

<?php 
include 'footer.php'; 
if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
?>