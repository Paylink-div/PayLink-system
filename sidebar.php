<?php
// sidebar.php - النسخة الاحترافية باللون الأبيض (Clean White Edition)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once 'db_connect.php';

// --- وظيفة التحقق من تفعيل الميزة للشركة ---
function is_active($feature_key) {
    if (!defined('ENABLED_FEATURES')) return false;
    if (ENABLED_FEATURES === 'all') return true; 

    $features = ENABLED_FEATURES;
    if (is_string($features)) {
        $features = json_decode($features, true);
    }
    
    return is_array($features) && in_array($feature_key, $features);
}

$translations = [
    'ar' => [
        'Dashboard' => 'الرئيسية',
        'System Management' => 'إدارة العمليات',
        'Financial Management' => 'الخزينة والمالية',
        'Perform Transaction' => 'إجراء عملية صرف', 
        'View Transactions Log' => 'سجل الفواتير', 
        'Treasury Balances' => 'أرصدة الخزينة',
        'Company Bank Treasury' => 'خزينة الشركة البنكية',
        'Client Management' => 'إدارة العملاء',
        'Rate Management' => 'إدارة أسعار الصرف',
        'Currencies Balances' => 'العملات والأرصدة',
        'Branch Management' => 'إدارة الفروع',
        'User Management' => 'المستخدمين والصلاحيات',
        'Comprehensive Reports' => 'تقارير نهاية اليوم', 
        'Logout' => 'تسجيل الخروج',
        'System Settings' => 'الإعدادات الأساسية',
        'Unknown User' => 'مستخدم غير معروف'
    ]
];

function __($text) {
    global $translations;
    return $translations['ar'][$text] ?? $text; 
}

$user_full_name = $_SESSION['full_name'] ?? __('Unknown User');
$raw_user_role = $_SESSION['user_role'] ?? 'موظف'; 
$user_permissions = $_SESSION['user_permissions'] ?? [];
if (!is_array($user_permissions)) { $user_permissions = []; }

function can_view($permission_key, $feature_key = null) {
    global $user_permissions, $raw_user_role;
    $role = trim($raw_user_role);
    if ($role == 'مدير عام' || $role == 'أدمن') return true;
    return in_array($permission_key, $user_permissions);
}

$current_page = basename($_SERVER['PHP_SELF']); 
$display_name = defined('COMPANY_NAME') ? COMPANY_NAME : 'PayLink';
?>

<nav class="navbar navbar-expand-lg mobile-navbar d-lg-none" dir="rtl" style="background: #ffffff; border-bottom: 1px solid #edf2f7; padding: 10px 20px;">
    <button class="navbar-toggler text-dark" type="button" id="sidebarCollapseMobile" style="border: 1px solid #e2e8f0;">
        <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand" href="index.php" style="color: #1a202c; font-weight: bold; margin-right: auto;">
        <i class="fas fa-wallet ml-2 text-primary"></i>
        <?php echo $display_name; ?>
    </a>
</nav>

<div class="sidebar shadow-sm" dir="rtl" id="sidebar">
    <div class="sidebar-header">
        <div class="brand-wrapper">
            <div class="brand-logo">
                <i class="fas fa-university text-white"></i>
            </div>
            <div class="brand-info">
                <span class="brand-name"><?php echo $display_name; ?></span>
                <span class="system-status"><span class="status-dot"></span> نشط حالياً</span>
            </div>
        </div>
        
        <div class="user-profile-box">
            <div class="user-avatar">
                <i class="fas fa-user-circle text-primary"></i>
            </div>
            <div class="user-details">
                <p class="user-name text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($user_full_name); ?></p>
                <span class="user-role-badge"><?php echo htmlspecialchars($raw_user_role); ?></span>
            </div>
        </div>
    </div>

    <ul class="list-unstyled components">
        <li class="nav-item">
            <a href="index.php" class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <span class="icon-box bg-light"><i class="fas fa-th-large text-primary"></i></span> 
                <span><?php echo __('Dashboard'); ?></span>
            </a>
        </li>

        <li class="section-title"><span><?php echo __('System Management'); ?></span></li>

        <?php if (is_active('exchange_process') && can_view('exchange_process')): ?>
            <li><a href="exchange.php" class="<?php echo $current_page == 'exchange.php' ? 'active' : ''; ?>">
                <span class="icon-box"><i class="fas fa-exchange-alt" style="color: #f59e0b;"></i></span> <?php echo __('Perform Transaction'); ?></a></li>
        <?php endif; ?>

        <?php if (is_active('invoices_log') && can_view('invoices_log')): ?>
            <li><a href="transactions_log.php" class="<?php echo $current_page == 'transactions_log.php' ? 'active' : ''; ?>">
                <span class="icon-box"><i class="fas fa-receipt" style="color: #6366f1;"></i></span> <?php echo __('View Transactions Log'); ?></a></li>
        <?php endif; ?>

        <li class="section-title"><span><?php echo __('Financial Management'); ?></span></li>

        <?php if (is_active('treasury_balance_management') && can_view('treasury_balance_management')): ?>
            <li><a href="treasury_adjustment.php" class="<?php echo $current_page == 'treasury_adjustment.php' ? 'active' : ''; ?>">
                <span class="icon-box"><i class="fas fa-box-open" style="color: #ef4444;"></i></span> <?php echo __('Treasury Balances'); ?></a></li>
        <?php endif; ?>

        <?php if (is_active('company_treasury') && can_view('company_treasury')): ?>
            <li><a href="company_treasury.php" class="<?php echo ($current_page == 'company_treasury.php' || $current_page == 'record_bank_movement.php') ? 'active' : ''; ?>">
                <span class="icon-box"><i class="fas fa-piggy-bank" style="color: #10b981;"></i></span> <?php echo __('Company Bank Treasury'); ?></a></li>
        <?php endif; ?>

        <?php if (is_active('currency_balance_management') && can_view('currency_balance_management')): ?>
            <li><a href="currencies.php" class="<?php echo $current_page == 'currencies.php' ? 'active' : ''; ?>">
                <span class="icon-box"><i class="fas fa-money-bill-wave" style="color: #22c55e;"></i></span> <?php echo __('Currencies Balances'); ?></a></li>
        <?php endif; ?>

        <?php if (is_active('exchange_rate_settings') && can_view('exchange_rate_settings')): ?>
            <li><a href="manage_rates.php" class="<?php echo $current_page == 'manage_rates.php' ? 'active' : ''; ?>">
                <span class="icon-box"><i class="fas fa-chart-line" style="color: #f97316;"></i></span> <?php echo __('Rate Management'); ?></a></li>
        <?php endif; ?>

        <li class="section-title"><span><?php echo __('System Settings'); ?></span></li>

        <?php if (is_active('clients_management') && can_view('clients_management')): ?>
            <li><a href="clients_management.php" class="<?php echo $current_page == 'clients_management.php' ? 'active' : ''; ?>">
                <span class="icon-box"><i class="fas fa-user-friends" style="color: #64748b;"></i></span> <?php echo __('Client Management'); ?></a></li>
        <?php endif; ?>

        <?php if (is_active('branch_management') && can_view('branch_management')): ?>
            <li><a href="branch_management.php" class="<?php echo $current_page == 'branch_management.php' ? 'active' : ''; ?>">
                <span class="icon-box"><i class="fas fa-store-alt" style="color: #8b5cf6;"></i></span> <?php echo __('Branch Management'); ?></a></li>
        <?php endif; ?>

        <?php if (is_active('users_management') && can_view('users_management')): ?>
            <li><a href="user_management.php" class="<?php echo $current_page == 'user_management.php' ? 'active' : ''; ?>">
                <span class="icon-box"><i class="fas fa-user-shield" style="color: #3b82f6;"></i></span> <?php echo __('User Management'); ?></a></li>
        <?php endif; ?>

        <?php if (is_active('comprehensive_reports') && can_view('comprehensive_reports')): ?>
            <li><a href="branch_report.php" class="<?php echo $current_page == 'branch_report.php' ? 'active' : ''; ?>">
                <span class="icon-box"><i class="fas fa-file-signature" style="color: #06b6d4;"></i></span> <?php echo __('Comprehensive Reports'); ?></a></li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-power-off"></i>
            <span><?php echo __('Logout'); ?></span>
        </a>
    </div>
</div>

<style>
:root { 
    --sb-bg: #ffffff90; 
    --sb-accent: #0a0683; 
    --sb-text: #000000; 
    --sb-title: #000000;
    --sb-hover: #f8fafc;
    --sb-active-bg: #eff6ff;
}

.sidebar { 
    width: 270px; 
    position: fixed; 
    top: 0; 
    right: 0; 
    height: 100vh; 
    background: var(--sb-bg); 
    color: var(--sb-text); 
    z-index: 1100; 
    overflow-y: auto; 
    transition: all 0.3s ease; 
    display: flex; 
    flex-direction: column; 
    border-left: 1px solid #edf2f7; 
}

.sidebar-header { padding: 25px 20px; border-bottom: 1px solid #f1f5f9; }
.brand-wrapper { display: flex; align-items: center; margin-bottom: 20px; }
.brand-logo { 
    width: 38px; height: 38px; background: var(--sb-accent); 
    border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; 
    box-shadow: 0 4px 10px rgb(26, 212, 12); 
}
.brand-info { margin-right: 12px; }
.brand-name { display: block; font-weight: 800; font-size: 1.15rem; color: #1e293b; letter-spacing: -0.5px; }
.system-status { font-size: 0.65rem; color: #10b981; font-weight: 600; display: flex; align-items: center; }
.status-dot { width: 6px; height: 6px; background: #10b981; border-radius: 50%; margin-left: 5px; animation: pulse 2s infinite; }

.user-profile-box { 
    display: flex; align-items: center; background: #f8fafc; 
    padding: 12px; border-radius: 12px; border: 1px solid #f1f5f9; 
}
.user-avatar { 
    width: 36px; height: 36px; border-radius: 10px; background: #fff; 
    display: flex; align-items: center; justify-content: center; font-size: 1.3rem; 
    border: 1px solid #e2e8f0;
}
.user-name { margin: 0; font-size: 0.85rem; font-weight: 700; color: #334155; }
.user-role-badge { 
    font-size: 0.65rem; color: #64748b; font-weight: 700; 
    background: #e2e8f0; padding: 1px 6px; border-radius: 4px; display: inline-block; 
}

.components { padding: 15px 12px; flex-grow: 1; }
.section-title { 
    padding: 20px 15px 10px 15px; font-size: 0.75rem; color: var(--sb-title); 
    font-weight: 700; text-transform: uppercase; letter-spacing: 1px; list-style: none;
}

.icon-box {
    width: 32px; height: 32px; border-radius: 8px; background: #f8fafc;
    display: flex; align-items: center; justify-content: center; margin-left: 12px;
    transition: 0.3s; border: 1px solid #f1f5f9;
}

.list-unstyled li a { 
    padding: 10px 14px; display: flex; align-items: center; color: var(--sb-text); 
    text-decoration: none !important; border-radius: 10px; margin-bottom: 4px; 
    font-size: 0.92rem; transition: 0.2s ease; font-weight: 600; 
}
.list-unstyled li a:hover { background: var(--sb-hover); color: var(--sb-accent); }
.list-unstyled li a:hover .icon-box { background: #f1eded; transform: scale(1.1); }

.list-unstyled li a.active { 
    background: var(--sb-active-bg); color: var(--sb-accent); 
    border-right: 3px solid var(--sb-accent); border-radius: 0 10px 10px 0; 
}
.list-unstyled li a.active .icon-box { background: #fff; border-color: #dbeafe; }

.sidebar-footer { padding: 20px; border-top: 1px solid #1171d1; }
.logout-btn { 
    display: flex; align-items: center; justify-content: center; width: 100%; 
    padding: 12px; background: #fff; color: #ef4444; border-radius: 10px; 
    text-decoration: none !important; font-weight: 700; font-size: 0.9rem; 
    border: 1px solid #ef0a0a; transition: 0.3s; 
}
.logout-btn i { margin-left: 8px; }
.logout-btn:hover { background: #ef4444; color: #bc2424; border-color: #ef4444; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2); }

@keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }

@media (max-width: 992px) { 
    .sidebar { right: -270px; border-left: none; box-shadow: 0 0 20px rgba(0,0,0,0.1); } 
    .sidebar.active { right: 0; } 
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const btn = document.getElementById('sidebarCollapseMobile');
    const sidebar = document.getElementById('sidebar');
    
    if(btn) {
        btn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
});
</script>