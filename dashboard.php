<?php

session_start();

// 🛑 تم حذف: require_once 'lang_manager.php'; 

include 'db_connect.php';

// -----------------------------------------------------------------------
// 1. التحقق من صلاحية الوصول (يعتمد فقط على وجود الجلسة)
// -----------------------------------------------------------------------

if (!isset($_SESSION['user_id'])) {
    // إذا لم يكن المستخدم مسجل الدخول، يتم توجيهه إلى صفحة الدخول
    header("Location: login.php");
    exit;
}

// تحديد البيانات المعروضة
$user_full_name = $_SESSION['full_name'] ?? 'مستخدم غير معروف'; 
$user_role = $_SESSION['user_role'] ?? 'ضيف'; // يمكن إبقاء هذا للعرض

// 🛑 تم حذف منطق ترجمة الأدوار والاعتماد على $lang

// -----------------------------------------------------------------------
// 2. كود لوحة القيادة (Dashboard Logic) يمكن إضافته هنا لاحقاً
// -----------------------------------------------------------------------

// إذا كان لديك ملف sidebar.php فيجب عليك التأكد من أن هذا الملف لا يحتوي على أي منطق ACL.
// بما أن الكود يعتمد على sidebar.php، سيتم إبقاء التضمين.

// تعريف ثابت لاتجاه اللغة لغرض CSS والتنسيق
$lang_direction = 'rtl';
$current_lang = 'ar'; 

?>

<!DOCTYPE html>

<html lang="<?php echo $current_lang; ?>" dir="<?php echo $lang_direction; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - PayLink</title> 
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <style>
        /* تم إبقاء CSS الديناميكي للـ Sidebar ليعمل بشكل صحيح مع RTL */
        .sidebar {
            width: 250px;
            position: fixed;
            top: 0;
            <?php echo ($lang_direction == 'rtl' ? 'right' : 'left'); ?>: 0; 
            height: 100vh;
            background: #2c3e50; 
            color: #fff;
            padding: 20px;
            box-shadow: <?php echo ($lang_direction == 'rtl' ? '-2px 0' : '2px 0'); ?> 5px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }
        .sidebar-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #4a627a;
            margin-bottom: 15px;
        }
        .sidebar-header h3 {
            margin-bottom: 5px;
            color: #ff7f50; 
        }
        .role-tag {
            font-size: 0.8em;
            padding: 2px 8px;
            background: #ff7f50;
            border-radius: 15px;
            display: inline-block;
            color: white;
        }
        .list-unstyled a {
            padding: 10px;
            font-size: 1.1em;
            display: block;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
        }
        .list-unstyled a:hover, .list-unstyled a.active {
            color: #fff;
            background: #34495e; 
            border-radius: 5px;
        }
        .list-unstyled a i {
            margin-<?php echo ($lang_direction == 'rtl' ? 'left' : 'right'); ?>: 10px;
            width: 20px;
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
        .content {
            margin-<?php echo ($lang_direction == 'rtl' ? 'right' : 'left'); ?>: 250px; 
            padding: 20px;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .lang-switcher {
            position: absolute;
            top: 15px;
            /* وضع زر التبديل في الزاوية المقابلة للشريط الجانبي */
            <?php echo ($lang_direction == 'rtl' ? 'left' : 'right'); ?>: 20px;
            z-index: 1000;
        }
    </style>
</head>

<body>
    
    <?php include 'sidebar.php'; ?> 

    <div class="content">
        <div class="container-fluid">
            <h1 class="mb-4">مرحباً بك، <?php echo htmlspecialchars($user_full_name); ?>!</h1>
            <p class="lead">تسجيل الدخول كـ: <strong><?php echo htmlspecialchars($user_role); ?></strong></p>
            <hr>

            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title text-primary"><i class="fas fa-hand-holding-usd"></i> إجمالي العمليات اليوم</h5>
                            <p class="card-text display-4">0</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title text-success"><i class="fas fa-money-bill-alt"></i> إجمالي الربح اليوم</h5>
                            <p class="card-text display-4">0.00 LYD</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title text-warning"><i class="fas fa-chart-bar"></i> رصيد الخزينة الرئيسي</h5>
                            <p class="card-text display-4">... LYD</p>
                        </div>
                    </div>
                </div>
                
            </div>

            </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>