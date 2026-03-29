<?php
// branch_summary.php - ملخص نشاط الفرع (النسخة النهائية)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// تأكد من وجود هذا الملف
include 'db_connect.php'; 

// 1. التحقق من تسجيل الدخول والصلاحية
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'مدير عام') {
    header("Location: index.php");
    exit;
}

$current_user_name = $_SESSION['full_name'] ?? 'مدير عام';

// ========================================================
// منطق جلب البيانات
// ========================================================

$error_message = '';

// جلب قائمة الفروع (مطلوب لجلب اسم الفرع)
$branches = [];
$branch_query = $conn->query("SELECT id, name FROM branches ORDER BY name ASC"); 
if ($branch_query === false) {
    $error_message = "خطأ في قاعدة البيانات: " . $conn->error . ". (الجدول: branches).";
} else {
    while ($row = $branch_query->fetch_assoc()) {
        $branches[] = $row;
    }
}

// 2. تحديد الفرع المُختار
$selected_branch_id = $_GET['branch_id'] ?? null;
$selected_branch_name = '';
$branch_data = []; 

if ($selected_branch_id && empty($error_message) && !empty($branches)) {
    
    // العثور على اسم الفرع
    foreach ($branches as $branch) {
        if ($branch['id'] == $selected_branch_id) {
            $selected_branch_name = $branch['name'];
            break;
        }
    }

    if ($selected_branch_name) {
        
        // أ. إجمالي عدد العمليات اليوم (حل مشكلة عمود 'amount')
        $today = date('Y-m-d');
        
        $stmt_transactions = $conn->prepare("
            SELECT 
                COUNT(id) AS total_count, 
                SUM(amount_sold) + SUM(amount_received) AS total_amount_aggregated 
            FROM transactions 
            WHERE branch_id = ? AND DATE(transaction_date) = ?
        ");
        
        if ($stmt_transactions === false) {
             $error_message = "خطأ في إعداد استعلام العمليات: " . $conn->error;
        } else {
            $stmt_transactions->bind_param("is", $selected_branch_id, $today);
            $stmt_transactions->execute();
            $result_transactions = $stmt_transactions->get_result()->fetch_assoc();
            
            $branch_data['transactions_today']['total_count'] = $result_transactions['total_count'] ?? 0;
            $branch_data['transactions_today']['total_amount'] = $result_transactions['total_amount_aggregated'] ?? 0;
        }

        // ب. أرصدة الخزينة الحالية للفرع
        // 🚨 الحل القاطع لخطأ 'current_balance': استخدام علامات الاقتباس العكسية 🚨
        $stmt_balances = $conn->prepare("
            SELECT 
                c.currency_code AS currency_code, 
                t.current_balance AS balance 
            FROM currencies_balances t
            JOIN currencies c ON t.currency_id = c.id
            WHERE t.branch_id = ?
        ");
        
        if ($stmt_balances === false) {
             $error_message = "خطأ في إعداد استعلام أرصدة الخزينة. (خطأ MySQL: " . $conn->error . ").";
             $branch_data['balances'] = [];
        } else {
            $stmt_balances->bind_param("i", $selected_branch_id);
            $stmt_balances->execute();
            $result_balances = $stmt_balances->get_result();
            $branch_data['balances'] = $result_balances->fetch_all(MYSQLI_ASSOC);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ملخص الفروع - PayLink</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* تم تعديل هذا النمط لإزالة المساحة الجانبية للفروع */
        .content { margin-right: 250px; padding: 20px; background-color: #f8f9fa; min-height: 100vh; }
    </style>
</head>
<body>

    <?php 
    // يجب دمج ملف db_connect.php قبل استدعاء sidebar.php لضمان عمل جلب الفروع
    include 'sidebar.php'; 
    ?> 

    <div class="content">
        <div class="container-fluid">
            <h1 class="mb-4 text-danger">
                <i class="fas fa-eye"></i> 
                <?php echo $selected_branch_name ? "ملخص الفرع: " . htmlspecialchars($selected_branch_name) : "ملخص نشاط الفروع (اختر فرعاً من الشريط الجانبي)"; ?>
            </h1>
            <p class="lead">أهلاً بك <?php echo htmlspecialchars($current_user_name); ?>.</p>
            <hr>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> *خطأ حرج في قاعدة البيانات:* <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-12">
                    <?php if ($selected_branch_name): ?>
                        <div class="card shadow">
                            <div class="card-header bg-info text-white">
                                <h3><i class="fas fa-chart-area"></i> بيانات الفرع: <?php echo htmlspecialchars($selected_branch_name); ?></h3>
                            </div>
                            <div class="card-body">
                                
                                <h5 class="mt-4 mb-3 text-primary">📊 ملخص العمليات اليومية (<?php echo date('Y-m-d'); ?>)</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="alert alert-success">
                                            <strong>عدد العمليات:</strong> <?php echo number_format($branch_data['transactions_today']['total_count'] ?? 0); ?> عملية
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="alert alert-warning">
                                            <strong>إجمالي مبالغ العمليات:</strong> <?php echo number_format($branch_data['transactions_today']['total_amount'] ?? 0, 2); ?>
                                        </div>
                                    </div>
                                </div>

                                <h5 class="mt-4 mb-3 text-primary">💰 أرصدة الخزينة الحالية</h5>
                                <?php if (!empty($branch_data['balances'])): ?>
                                    <ul class="list-group">
                                        <?php foreach ($branch_data['balances'] as $balance): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                *<?php echo htmlspecialchars($balance['currency_code']); ?>*
                                                <span class="badge badge-secondary badge-pill p-2" style="font-size: 1em;">
                                                    <?php echo number_format($balance['balance'], 2); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="alert alert-secondary">لا توجد أرصدة مُسجلة لهذا الفرع حالياً.</div>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-primary">
                            <i class="fas fa-hand-point-up"></i> يرجى اختيار فرع من القائمة *"ملخص الفروع"* في شريط التنقل لعرض ملخص نشاطه.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>