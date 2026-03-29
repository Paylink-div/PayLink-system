<?php

// sidebar.php - النسخة النهائية: مع نظام الصلاحيات (ACL) ومصححة من خطأ unexpected end of file

// 🚨 1. التحقق من حالة الجلسة قبل البدء بها
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// =======================================================================================
// 💡 2. آليات الترجمة وتحديد اللغة (Translation Mechanism)
// =======================================================================================

// تحديد اللغة الحالية: تثبيت اللغة على العربي (ar) بشكل دائم
$current_lang = 'ar'; 
$_SESSION['lang'] = 'ar'; // تثبيت الجلسة أيضاً

// تعيين الاتجاه لـ HTML/BODY: تثبيت الاتجاه على RTL بشكل دائم
$page_dir = 'rtl';

// مصفوفة الترجمة (تم تحديثها بالترجمات الجديدة)
$translations = [
    'ar' => [
        'PayLink' => 'PayLink',
        'Faster transfers, greater trust' => 'تحويلات أسرع، ثقة أكبر',
        'General Manager' => 'المدير العام',
        'Branch Manager' => 'مدير الفرع',
        'Employee' => 'الموظف',
        'Unknown User' => 'مستخدم غير معروف',
        'Operating Branch' => 'فرع التشغيل',
        'General (Comprehensive View)' => 'الرئيسي (عرض شامل)',
        'Not associated with a branch' => 'غير مرتبط بفرع',
        'Dashboard' => 'لوحة التحكم',
        'System Management' => 'إدارة النظام',
        
        // 🆕 الترجمات المضافة لخاصية التقارير ونهاية اليوم
        'Send End-of-Day Report' => 'إرسال تقرير نهاية اليوم', // ⬅ ترجمة جديدة
        'Perform Transaction' => 'إجراء عملية صرف', 
        'View Transactions Log' => 'سجل الفواتير', 
        
        // 🚨 ترجمات خزينة الشركة البنكية
        'Company Bank Treasury' => 'خزينة الشركة البنكية',
        'Manage Bank Accounts' => 'إدارة الحسابات البنكية',
        'Record Bank Movement' => 'تسجيل حركة بنكية',
        'Transfer Between Accounts' => 'تحويل بين حسابات الشركة',
        'Bank Movements Log' => 'سجل الحركات البنكية',
        
        // ⬅ تمت إضافة الترجمة الجديدة هنا
        'Branch Management' => 'إدارة الفروع',
        'Currencies and Balances Management' => 'إدارة العملات والأرصدة',
        
        // 💡 التعديل: اسم القسم الجديد لأسعار الصرف
        'Rate Management' => 'إدارة وتعديل أسعار الصرف',
        
        // 🆕 الترجمة الجديدة بعد الدمج
        'Client & Account Management' => 'إدارة وحسابات العملاء', 
        'Client Management' => 'إدارة العملاء', 
        'User Management' => 'إدارة المستخدمين',
        'Update Exchange Rates' => 'تحديث أسعار الصرف',
        'Treasury Balances Management' => 'إدارة أرصدة الخزينة',
        'Reports and Reconciliation' => 'التقارير والمطابقة',
        // 🚨 تم تغيير التقرير الشامل ليعكس صفحة العرض الجديدة
        'Comprehensive Reports' => 'عرض تقارير نهاية اليوم', 
        'Logout' => 'تسجيل الخروج',
        'User Role' => 'دور المستخدم', 
        'Toggle Menu' => 'القائمة', // ترجمة جديدة لزر القائمة على الجوال
    ]
];

// دالة الترجمة
function __($text) {
    global $translations;
    // بما أن اللغة ثابتة على 'ar'، سنستخدم الترجمة العربية أو النص الأصلي إذا لم تتوفر ترجمة
    return $translations['ar'][$text] ?? $text; 
}


// =======================================================================================
// 3. جلب الدور والبيانات الأساسية والصلاحيات
// =======================================================================================

$user_full_name = $_SESSION['full_name'] ?? __('Unknown User');
$raw_user_role = $_SESSION['user_role'] ?? 'موظف'; 
$current_branch_id = $_SESSION['branch_id'] ?? 0; 
$current_user_id = $_SESSION['user_id'] ?? 0; 

// 🚨 الإضافة المطلوبة: جلب الصلاحيات من الجلسة 🚨
$user_permissions = $_SESSION['user_permissions'] ?? [];


// =======================================================================================
// 4. تحديد الفرع التشغيلي
// =======================================================================================

$is_general_manager = ($raw_user_role == 'مدير عام'); 

if ($is_general_manager) {
    $operating_branch_id = $_SESSION['operating_branch_id'] ?? 0;
    $operating_branch_name = $_SESSION['operating_branch_name'] ?? __('General (Comprehensive View)');
} else {
    $operating_branch_id = $current_branch_id;
    $operating_branch_name = $_SESSION['branch_name'] ?? __('Not associated with a branch');
}


// =======================================================================================
// 5. تحديد طريقة عرض الدور وتنسيقه
// =======================================================================================

$role_display = $raw_user_role;
switch ($raw_user_role) {
    case 'مدير عام':
        $role_class = 'badge-danger'; 
        break;
    case 'مدير فرع':
        $role_class = 'badge-warning'; 
        break;
    case 'موظف':
    default:
        $role_class = 'badge-success'; 
        break;
}

// الحصول على اسم الملف الحالي لتمييز الرابط النشط
$current_page = basename($_SERVER['PHP_SELF']); 


// 🚨 دالة التحقق من الصلاحيات للقائمة الجانبية (ACL Check Function) 🚨
function check_permission($permission_key, $user_permissions, $current_role) {
    // المدير العام يرى جميع الروابط
    if ($current_role == 'مدير عام') {
        return true;
    }
    // للموظف أو مدير الفرع، يتم التحقق مما إذا كانت الصلاحية موجودة في مصفوفة الجلسة
    return in_array($permission_key, $user_permissions);
}

// =======================================================================================
// 6. مصفوفة الروابط الجانبية (يجب أن تتطابق مفاتيحها مع permissions_key في index.php)
// =======================================================================================

$sidebar_links = [
    // المفتاح                  // الرابط                 // الترجمة                            // أيقونة            // لون الأيقونة
    'exchange_process'             => ['link' => 'exchange.php',               'title' => 'Perform Transaction',              'icon' => 'fas fa-exchange-alt',     'color' => '#FBC02D'],
    'invoices_log'                 => ['link' => 'transactions_log.php',       'title' => 'View Transactions Log',            'icon' => 'fas fa-file-invoice-dollar', 'color' => '#6A1B9A'],
    'exchange_rate_settings'       => ['link' => 'manage_rates.php',           'title' => 'Rate Management',                  'icon' => 'fas fa-sync-alt',         'color' => '#FF5722'],
    'branch_management'            => ['link' => 'branch_management.php',      'title' => 'Branch Management',                'icon' => 'fas fa-building',         'color' => '#673AB7'],
    'treasury_balance_management'  => ['link' => 'treasury_adjustment.php',    'title' => 'Treasury Balances Management',     'icon' => 'fas fa-wallet',           'color' => '#e74c3c'],
    'currency_balance_management'  => ['link' => 'currencies.php',             'title' => 'Currencies and Balances Management', 'icon' => 'fas fa-coins',            'color' => '#009688'],
    'clients_management'           => ['link' => 'clients_management.php',     'title' => 'Client Management',                'icon' => 'fas fa-handshake',        'color' => '#A0522D'],
    'users_management'             => ['link' => 'user_management.php',        'title' => 'User Management',                  'icon' => 'fas fa-users-cog',        'color' => '#7f8c8d'],
    'comprehensive_reports'        => ['link' => 'branch_report.php',          'title' => 'Comprehensive Reports',            'icon' => 'fas fa-chart-line',       'color' => '#0D47A1'],
    'send_report'                  => ['link' => 'send_report.php',            'title' => 'Send End-of-Day Report',           'icon' => 'fas fa-paper-plane',      'color' => '#2ecc71'],
    'company_treasury'             => ['link' => 'company_treasury.php',       'title' => 'Company Bank Treasury',            'icon' => 'fas fa-piggy-bank',       'color' => '#00bcd4'],
];


?>

<script>
    // 💡 تطبيق اتجاه الصفحة (RTL) فوراً قبل تحميل المحتوى
    document.documentElement.setAttribute('dir', '<?php echo $page_dir; ?>');
    document.body.setAttribute('dir', '<?php echo $page_dir; ?>');
    
    // 💡 إضافة كلاس لتعديل هامش المحتوى في ملف index.php على سطح المكتب
    document.body.classList.add('page-rtl-desktop');
</script>

<nav class="navbar navbar-expand-lg mobile-navbar" dir="rtl">
    <a class="navbar-brand" href="index.php" style="color: #ff7f50; font-weight: bold;"><?php echo __('PayLink'); ?></a>
    
    <button class="navbar-toggler" type="button" id="sidebarCollapseMobile">
        <i class="fas fa-bars"></i> <span class="d-none d-sm-inline-block"><?php echo __('Toggle Menu'); ?></span>
    </button>
</nav>

<div class="sidebar" dir="rtl" id="sidebar">

    <div class="sidebar-header">

        <h3 style="color: #ff7f50;"><?php echo __('PayLink'); ?></h3>
        
        <p style="color: #95a5a6; font-size: 0.9em; margin-top: -5px; margin-bottom: 10px;"><?php echo __('Faster transfers, greater trust'); ?></p>

        <span class="badge <?php echo $role_class; ?> mb-2"><?php echo __('User Role'); ?>: <?php echo __($raw_user_role); ?></span>
        <h5 style="color: #bdc3c7; font-size: 1.1em;"><?php echo htmlspecialchars($user_full_name); ?></h5>
        
        <?php if ($operating_branch_id != 0): ?>
            <p style="color: #2ecc71; font-size: 0.8em; margin-top: 5px;">
                <i class="fas fa-code-branch"></i> <?php echo __('Operating Branch'); ?>: <?php echo htmlspecialchars($operating_branch_name); ?>
            </p>
        <?php elseif ($is_general_manager && $operating_branch_id == 0): ?>
            <p style="color: #63d1f4; font-size: 0.8em; margin-top: 5px;">
                <i class="fas fa-code-branch"></i> <?php echo __('Operating Branch'); ?>: <?php echo __('General (Comprehensive View)'); ?>
            </p>
        <?php else: ?>
            <p style="color: #e74c3c; font-size: 0.8em; margin-top: 5px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo __('Not associated with a branch'); ?>
            </p>
        <?php endif; ?>
    </div>

    <ul class="list-unstyled components">

        
        <li>
            <a href="index.php" class="<?php echo $current_page == 'index.php' || $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> <?php echo __('Dashboard'); ?>
            </a>
        </li>
        
        <div class="section-title"><?php echo __('System Management'); ?></div>


        <?php 
        // 🚨 التعديل: التكرار على مصفوفة الروابط والتحقق من الصلاحيات 🚨
        foreach ($sidebar_links as $permission_key => $link_data): 
            if (check_permission($permission_key, $user_permissions, $raw_user_role)):
        ?>
            <li>
                <a href="<?php echo htmlspecialchars($link_data['link']); ?>" 
                   class="<?php echo $current_page == $link_data['link'] ? 'active' : ''; ?>">
                    <i class="<?php echo htmlspecialchars($link_data['icon']); ?>" style="color: <?php echo htmlspecialchars($link_data['color']); ?>;"></i> 
                    <?php echo __($link_data['title']); ?>
                </a>
            </li>
        <?php 
            endif;
        endforeach; // 👈 تم التصحيح هنا: من enduest إلى endforeach
        ?>

    </ul>

    
    <ul class="list-unstyled CTAs logout-link">
        <li>
            <a href="logout.php" class="btn btn-block btn-outline-light"><i class="fas fa-sign-out-alt"></i> <?php echo __('Logout'); ?></a>
        </li>
        
    </ul>

</div>


<style>
/* ----------------------------------------------------------------------- */
/* تنسيقات الشريط الجانبي (Sidebar CSS) */
/* ----------------------------------------------------------------------- */

.sidebar {
    width: 250px;
    position: fixed;
    top: 0;
    right: 0; 
    left: auto;
    height: 100vh; 
    background: #2c3e50; 
    color: #fff;
    padding: 20px;
    box-shadow: -2px 0 5px rgba(0, 0, 0, 0.2); 
    z-index: 1000;
    overflow-y: auto; 
    transition: all 0.3s; /* لتسهيل حركة الإخفاء والإظهار */
}

/* 🚨 شريط التنقل العلوي (يظهر فقط على الجوال) */
.mobile-navbar {
    display: none; /* إخفاء افتراضياً */
    background: #2c3e50; 
    border-bottom: 3px solid #ff7f50;
    z-index: 999;
}

.mobile-navbar .navbar-toggler {
    border-color: #ff7f50;
    color: #ff7f50;
    font-size: 1rem;
}

.mobile-navbar .navbar-toggler:focus {
    box-shadow: none;
}

/* ----------------------------------------------------------------------- */
/* 🚨 التجاوبية (Mobile Responsiveness) */
/* ----------------------------------------------------------------------- */

@media (max-width: 768px) {
    /* 1. إظهار شريط التنقل العلوي على الجوال */
    .mobile-navbar {
        display: flex;
        position: fixed;
        top: 0;
        width: 100%;
    }
    
    /* 2. إخفاء الشريط الجانبي وتحريكه خارج الشاشة على الجوال */
    .sidebar {
        /* تحريك القائمة خارج الشاشة لجهة اليمين */
        right: -250px; 
        box-shadow: none;
        padding-top: 60px; /* ليتجنب شريط التنقل العلوي */
    }
    
    /* 3. الكلاس الخاص بإظهار الشريط الجانبي عند الضغط على الزر */
    .sidebar.active {
        right: 0;
        box-shadow: -2px 0 5px rgba(0, 0, 0, 0.2);
    }
    
    /* 4. إجبار محتوى الصفحة على بدء الظهور أسفل شريط التنقل العلوي */
    .page-rtl-desktop .content {
        margin-right: 0 !important;
        padding-top: 70px; /* لترك مسافة لشريط التنقل العلوي الثابت */
    }
    
    /* 5. إخفاء رأس الشريط الجانبي لتقليل الازدحام على الجوال */
    .sidebar-header {
        padding-top: 0;
        margin-top: -10px;
    }
}


/* ----------------------------------------------------------------------- */
/* تنسيقات الرابطة (Desktop Styles) */
/* ----------------------------------------------------------------------- */


.sidebar-header {
    text-align: center;
    padding-bottom: 20px;
    border-bottom: 1px solid #4a627a;
    margin-bottom: 15px;
}


.list-unstyled {
    padding: 0;
    list-style: none;
}


.list-unstyled a {
    padding: 10px;
    font-size: 1.0em;
    display: block;
    color: #bdc3c7;
    text-decoration: none;
    transition: all 0.3s;
    border-radius: 5px; 
    margin-bottom: 5px;
}


.list-unstyled a:hover, .list-unstyled a.active {
    color: #fff;
    background: #34495e; 
}


.list-unstyled a i {
    /* تثبيت هامش الأيقونات على اليسار دائمًا (للتوافق مع RTL) */
    margin-right: 0; 
    margin-left: 10px; 
    width: 20px;
    text-align: center;
    /* 🚨 الأيقونات التي لم يتم تحديد لون لها يدوياً ستأخذ هذا اللون */
    color: #bdc3c7; 
}


.section-title {
    padding: 10px 10px 5px 10px;
    font-size: 0.9em;
    color: #95a5a6;
    font-weight: bold;
    text-transform: uppercase;
}


.logout-link {
    margin-top: 30px;
    border-top: 1px solid #4a627a;
    padding-top: 10px;
}


.badge-danger { background-color: #e74c3c !important; }
.badge-warning { background-color: #f39c12 !important; } 
.badge-success { background-color: #2ecc71 !important; }


/* 🚨 تم إزالة جميع كلاسات الألوان من هذا الملف، وتم تطبيق الألوان يدوياً في كود PHP */
/* تم إبقاء الكود أعلاه على حالته الأصلية لتكون الأيقونات ملونة يدويًا في شيفرة HTML/PHP نفسها */


</style>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mobileCollapseButton = document.getElementById('sidebarCollapseMobile');
        const content = document.querySelector('.content'); // نستخدم هنا .content الذي هو موجود في index.php


        if (mobileCollapseButton && sidebar) {
            mobileCollapseButton.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }
        
        // إخفاء القائمة عند النقر خارجها على الجوال 
        if (content) {
            content.addEventListener('click', function() {
                if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            });
        }
        
        // منع إخفاء القائمة عند النقر داخلها
        if (sidebar) {
            sidebar.addEventListener('click', function(e) {
                // نستخدم window.innerWidth للتأكد أننا في وضع الجوال
                if (window.innerWidth <= 768) {
                    e.stopPropagation();
                }
            });
        }
        
        // تأكد من إخفاء القائمة في وضع سطح المكتب عند إعادة تحميل الصفحة إذا كانت نشطة 
        if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
        }
    });
</script>