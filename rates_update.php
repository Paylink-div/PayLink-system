<?php


// ملف: rates_update.php (تحديث أسعار الصرف - مُعدَّل ليتوافق مع هيكل زوج العملات)



if (session_status() == PHP_SESSION_NONE) {

    session_start();

}


// تأكد من صحة مسار ملف الاتصال بقاعدة البيانات
include 'db_connect.php'; 
// 💡 تضمين دوال الصلاحيات (ACL)
require_once 'acl_functions.php'; 


// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");

    exit;

}


// 🛑 تطبيق التحقق من الصلاحية 💡 الجديد
// يجب أن يمتلك المستخدم صلاحية 'CAN_UPDATE_RATES' للوصول
require_permission($conn, 'CAN_UPDATE_RATES'); 


// -----------------------------------------------------------------------------------------
// 🛑 تعريف متغير الفرع التشغيلي (branch_filter_id) لتوحيد النمط 🛑
// -----------------------------------------------------------------------------------------
$user_role = $_SESSION['user_role'] ?? 'موظف';
$current_branch_id = $_SESSION['branch_id'] ?? 0;

// في هذا الملف، لا نستخدم هذا المتغير لتصفية أسعار الصرف، ولكنه مُعرَّف للتوحيد.
$operating_branch_id = ($user_role == 'مدير فرع' ? $current_branch_id : 0);
// -----------------------------------------------------------------------------------------


$message = '';
$rates_data = [];

// نفترض أن العملة الأساسية (LYD) هي ID=1، وهذه هي العملة FROM_CURRENCY_ID الثابتة
$base_currency_id = 1; 
$base_currency_code = 'LYD'; // للعرض فقط

// ---------------------------------------------
// 1. معالجة طلب تحديث الأسعار (POST)
// ---------------------------------------------

if (isset($_POST['update_rates'])) {
    
    // البيانات القادمة من النموذج الآن هي ID العملة الهدف (Target) وأسعارها
    $to_currency_ids = $_POST['currency_id']; 
    $buy_rates = $_POST['buy_rate'];
    $sell_rates = $_POST['sell_rate'];

    $success_count = 0;
    $current_datetime = date('Y-m-d H:i:s');
    
    // البدء بمعاملة (Transaction) لضمان نجاح جميع التحديثات أو فشلها كلها
    $conn->begin_transaction(); 

    try {
        
        // 🛑 تجهيز الاستعلام الجديد الذي يستخدم INSERT ... ON DUPLICATE KEY UPDATE 
        $update_sql = "
            INSERT INTO exchange_rates (from_currency_id, to_currency_id, buy_rate, sell_rate, effective_from)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                buy_rate = VALUES(buy_rate),
                sell_rate = VALUES(sell_rate),
                effective_from = VALUES(effective_from);
        ";
        $stmt = $conn->prepare($update_sql);

        if ($stmt === FALSE) {
            throw new Exception("فشل في إعداد استعلام التحديث: " . $conn->error);
        }

        foreach ($to_currency_ids as $index => $to_currency_id) {
            
            $target_id = intval($to_currency_id);
            $buy = floatval($buy_rates[$index]);
            $sell = floatval($sell_rates[$index]);
            
            // تخطي العملة الأساسية (لن نقوم بتحديث سعر LYD مقابل LYD)
            if ($target_id == $base_currency_id) {
                continue; 
            }
            
            // الربط: i i d d s
            $stmt->bind_param("iidds", $base_currency_id, $target_id, $buy, $sell, $current_datetime);

            if (!$stmt->execute()) {
                throw new Exception("خطأ في تحديث السعر للعملة ID: " . $target_id);
            }

            $success_count++;
        }

        $stmt->close();
        
        // إذا نجحت جميع التحديثات والإضافات
        $conn->commit();
        $message = '<div class="alert alert-success">تم تحديث أسعار ' . $success_count . ' عملة بنجاح.</div>';


    } catch (Exception $e) {
        // إذا حدث خطأ، يتم التراجع عن جميع التغييرات
        $conn->rollback();
        $message = '<div class="alert alert-danger">حدث خطأ أثناء تحديث الأسعار: ' . $e->getMessage() . '</div>';
    }

}

// ---------------------------------------------
// 2. جلب بيانات العملات وأسعار الصرف الحالية للعرض
// ---------------------------------------------

// جلب العملات الأجنبية فقط
$currency_sql = "SELECT id, currency_code, currency_name_ar FROM currencies WHERE id != $base_currency_id ORDER BY currency_code ASC";
$currency_result = $conn->query($currency_sql);

if ($currency_result === FALSE) {
    $message .= '<div class="alert alert-danger">خطأ فادح في جلب العملات: ' . $conn->error . '</div>';
} else {
    while ($currency = $currency_result->fetch_assoc()) {
        $currency_id = $currency['id'];
        
        // 🛑 جلب أحدث سعر صرف مسجل لزوج العملات (Base_ID -> Target_ID)
        $rate_sql = "SELECT buy_rate, sell_rate FROM exchange_rates 
                     WHERE from_currency_id = $base_currency_id AND to_currency_id = $currency_id
                     ORDER BY effective_from DESC LIMIT 1";
        
        $rate_result = $conn->query($rate_sql);
        
        if ($rate_result === FALSE) {
             error_log("SQL Error in fetching current rate for ID $currency_id: " . $conn->error);
             $rate = null;
        } else {
             $rate = $rate_result->fetch_assoc();
        }
        
        $rates_data[] = [
            'id' => $currency['id'], // هذا هو ID العملة الهدف (to_currency_id)
            'code' => $currency['currency_code'],
            'name' => $currency['currency_name_ar'],
            'buy_rate' => $rate ? $rate['buy_rate'] : 0.0000,
            'sell_rate' => $rate ? $rate['sell_rate'] : 0.0000,
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تحديث أسعار الصرف - PayLink</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .content { margin-right: 250px; padding: 20px; background-color: #f8f9fa; min-height: 100vh; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?> 

    <div class="content">
        <div class="container-fluid">
            <h1 class="mb-4 text-info"><i class="fas fa-dollar-sign"></i> تحديث أسعار الصرف</h1>
            <p>يتم تحديث أسعار العملات الأجنبية مقابل العملة الأساسية للنظام (<?php echo htmlspecialchars($base_currency_code); ?>).</p>
            <hr>
            
            <?php echo $message; ?>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    إدخال الأسعار الجديدة
                </div>
                <div class="card-body">
                    <form method="POST" action="rates_update.php">
                        <input type="hidden" name="update_rates" value="1">
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover text-center table-sm">
                                <thead>
                                    <tr>
                                        <th>الرمز</th>
                                        <th>اسم العملة</th>
                                        <th>سعر الشراء (<?php echo htmlspecialchars($base_currency_code); ?> مقابل العملة)</th>
                                        <th>سعر البيع (العملة مقابل <?php echo htmlspecialchars($base_currency_code); ?>)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($rates_data)): ?>
                                        <?php foreach ($rates_data as $rate): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($rate['code']); ?></td>
                                                <td><?php echo htmlspecialchars($rate['name']); ?></td> 
                                                <td>
                                                    <input type="hidden" name="currency_id[]" value="<?php echo $rate['id']; ?>">
                                                    <input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="buy_rate[]" 
                                                            value="<?php echo number_format($rate['buy_rate'], 4, '.', ''); ?>" required>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="sell_rate[]" 
                                                            value="<?php echo number_format($rate['sell_rate'], 4, '.', ''); ?>" required>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center">يرجى إضافة العملات أولاً من شاشة إدارة العملات.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (!empty($rates_data)): ?>
                            <button type="submit" class="btn btn-success mt-2"><i class="fas fa-sync"></i> تحديث الأسعار</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
        </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>


<?php 
// إغلاق الاتصال بقاعدة البيانات في نهاية الملف
if (isset($conn) && $conn->ping()) {
    $conn->close(); 
}
?>