<?php
// ملف: transaction_process.php (النسخة النهائية والمصححة)
// ** تم حذف خانة الربح والخسارة وإضافة خانة طريقة الدفع وحذف مصروفات التشغيل **

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// 1. تضمين اتصال قاعدة البيانات
include 'db_connect.php'; 
// 2. تضمين دوال الصلاحيات (ACL)
require_once 'acl_functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_file_name = 'transaction_process.php'; 
$teller_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'موظف';
$is_general_manager = ($user_role == 'مدير عام');
$last_transaction_id = null; 
$client_phone_number_after = ''; 
$client_id_number_after = ''; 


// =======================================================================================
// 🛑 منطق تحديد الفرع التشغيلي (Branch Filtering Logic) 🛑
// =======================================================================================

$branch_filter_id = 0;

if ($is_general_manager) {
    // المدير العام يستخدم الفرع الذي اختاره من لوحة التحكم
    $branch_filter_id = $_SESSION['operating_branch_id'] ?? ($_SESSION['branch_id'] ?? 0);

    // لا يمكن إجراء عملية صرف ضمن سياق "عرض شامل" (ID=0)
    if ($branch_filter_id === 0 && ($_SESSION['branch_id'] ?? 0) !== 0) {
        $branch_filter_id = $_SESSION['branch_id'];
    }

} else {
    // الموظف أو مدير الفرع العادي يستخدم فرعه الثابت
    $branch_filter_id = $_SESSION['branch_id'] ?? 0;
}

// الفرع الذي ستتم عليه العملية: هو الفرع المُحدد بالتصفية
$transaction_branch_id = $branch_filter_id;

// جلب اسم الفرع للتوثيق في الصفحة
$current_branch_name = 'غير محدد';
if ($transaction_branch_id > 0) {
    if (!isset($conn) || $conn->ping() === false) {
        include 'db_connect.php'; 
    }
    $branch_name_q = $conn->prepare("SELECT name FROM branches WHERE id = ?");
    $branch_name_q->bind_param("i", $transaction_branch_id);
    $branch_name_q->execute();
    $branch_name_r = $branch_name_q->get_result();
    if ($branch_name_r->num_rows > 0) {
        $current_branch_name = $branch_name_r->fetch_assoc()['name'];
    }
    $branch_name_q->close();
}


$message = '';
$message_type = '';

// =======================================================================================


// -----------------------------------------------------
// 1. جلب البيانات الأساسية (العملاء، العملات، الفروع)
// -----------------------------------------------------

// جلب العملات
$currencies_query = "SELECT id, currency_code, currency_name_ar FROM currencies ORDER BY currency_code ASC";
$currencies_data = $conn->query($currencies_query);
$currencies_array = [];
if ($currencies_data) {
    while($row = $currencies_data->fetch_assoc()){
        $currencies_array[$row['id']] = $row;
    }
    $currencies_data->data_seek(0); 
}

// جلب العملاء
$clients_query = "SELECT id, full_name, id_number, phone_number FROM clients ORDER BY full_name ASC";
$clients_data_result = $conn->query($clients_query);
$clients_data_array = [];
if ($clients_data_result) {
    while($row = $clients_data_result->fetch_assoc()){
        $clients_data_array[$row['id']] = $row;
    }
    $clients_data_result->data_seek(0); 
}


// -----------------------------------------------------
// 2. معالجة طلبات إجراء المعاملة (POST)
// -----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // تأكد من أن الفرع التشغيلي تم تعيينه بشكل صحيح للعملية
    if ($transaction_branch_id === 0) {
        $message = "فشل العملية: يجب تحديد فرع نشط لإجراء عملية صرف. (لا يمكن العمل في سياق 'العرض الشامل')";
        $message_type = 'danger';
        goto end_processing;
    }
    
    // تنظيف البيانات
    $client_id = intval($_POST['client_id'] ?? 0);
    $transaction_type = $_POST['transaction_type'] ?? ''; // SELL, BUY
    $base_currency_id = intval($_POST['base_currency_id'] ?? 0); // العملة الأساس
    $target_currency_id = intval($_POST['target_currency_id'] ?? 0); // العملة الهدف
    
    $amount_base = (float)($_POST['amount_base'] ?? 0); // المبلغ الأساس (الكمية التي تم إدخالها يدوياً)
    $amount_target = (float)($_POST['amount_target'] ?? 0); // المبلغ الهدف (القيمة المحتسبة من JS)
    
    $rate_used = (float)($_POST['rate_used'] ?? 1); // السعر المستخدم (من JS)
    
    // 🛑 تم حذف الربح/الخسارة
    // $profit_loss = 0.00; // هذا المتغير لم يعد يستخدم في استعلام الإدخال

    $discount = (float)($_POST['discount'] ?? 0); // الخصم
    
    // 💡 الجديد: طريقة الدفع
    $payment_method = trim($_POST['payment_method'] ?? '');
    
    // 💡 تم حذف وصف المصروف

    // إعادة احتساب المبلغ الهدف في PHP للتحقق
    if ($amount_base > 0 && $rate_used > 0) {
        $calculated_target = $amount_base * $rate_used;
        $amount_target_check = max(0, $calculated_target - $discount); 
        $amount_target = $amount_target_check;
    }


    // إعداد متغيرات المعاملة (العملة الداخلة والخارجة)
    $currency_received_id = null;
    $amount_received = 0;
    $currency_sold_id = null;
    $amount_sold = 0;
    $required_balance_currency_id = null;
    $required_balance_amount = 0;
    
    // جلب بيانات العميل للتوثيق
    if (isset($clients_data_array[$client_id])) {
        $client_phone_number_after = $clients_data_array[$client_id]['phone_number'];
        $client_id_number_after = $clients_data_array[$client_id]['id_number']; 
    }


    if ($transaction_type == 'SELL') {
        // عملية بيع عملات (نحن نبيع الهدف ونستلم الأساس)
        // العميل يدفع عملة الأساس (Base) ونحن نستلمها. نحن ندفع عملة الهدف (Target) ويستلمها العميل.
        $currency_received_id = $base_currency_id; // (القيمة التي دخلت للخزينة)
        $amount_received = $amount_base; 
        $currency_sold_id = $target_currency_id; // (الكمية التي خرجت من الخزينة)
        $amount_sold = $amount_target;
        
        $required_balance_currency_id = $currency_sold_id; // العملة التي يجب أن يكون لها رصيد كافٍ
        $required_balance_amount = $amount_sold;
        
    } elseif ($transaction_type == 'BUY') {
        // عملية شراء عملات (نحن نشتري الهدف وندفع الأساس)
        // العميل يدفع عملة الهدف (Target) ونحن نستلمها. نحن ندفع عملة الأساس (Base) ويستلمها العميل.
        $currency_received_id = $target_currency_id; // (العملة التي دخلت للخزينة)
        $amount_received = $amount_base; // كمية العملة المستلمة
        $currency_sold_id = $base_currency_id; // (العملة التي خرجت من الخزينة)
        $amount_sold = $amount_target; // قيمة العملة المدفوعة
        
        $required_balance_currency_id = $currency_sold_id; // العملة التي يجب أن يكون لها رصيد كافٍ
        $required_balance_amount = $amount_sold;
        
    } else {
        $message = "فشل العملية: نوع العملية غير صحيح. (مسموح: بيع أو شراء فقط)";
        $message_type = 'danger';
        goto end_processing;
    }
    
    // التحقق الأساسي
    if ($client_id == 0 || $base_currency_id == 0 || $target_currency_id == 0 || $amount_base <= 0 || empty($payment_method)) {
        $message = "يرجى ملء جميع الحقول المطلوبة (العميل، العملات، المبالغ، وطريقة الدفع).";
        $message_type = 'danger';
    } 
    elseif ($amount_target <= 0) {
        $message = "القيمة المحسوبة (العملة الأخرى) صفر أو سالبة. يرجى مراجعة قيمة الخصم أو سعر الصرف.";
        $message_type = 'danger';
    } 
    elseif ($base_currency_id == $target_currency_id) {
        $message = "لا يمكن أن تكون العملتان متطابقتين في عملية تبادل.";
        $message_type = 'danger';
    } 
    else {
        
        // 🛑 التحقق من الرصيد للعملة التي سيتم صرفها (Sold)
        $check_balance_sql = $conn->prepare("SELECT current_balance FROM treasury_balances WHERE currency_id = ? AND branch_id = ?");
        $check_balance_sql->bind_param("ii", $required_balance_currency_id, $transaction_branch_id); 
        $check_balance_sql->execute();
        $result = $check_balance_sql->get_result();
        $current_out_balance = $result->num_rows > 0 ? (float)$result->fetch_assoc()['current_balance'] : 0;
        $check_balance_sql->close();

        if ($current_out_balance < $required_balance_amount) {
            $currency_sold_code = $currencies_array[$required_balance_currency_id]['currency_code'] ?? 'N/A';
            $message = "فشل العملية: الرصيد الحالي لـ {$currency_sold_code} غير كافٍ في فرع {$current_branch_name}. الرصيد المتاح: " . number_format($current_out_balance, 4);
            $message_type = 'danger';
        } else {
            $conn->begin_transaction();
            try {
                
                // 1. خصم الرصيد (العملة المباعة/المدفوعة) - تطبيق branch_filter_id
                $update_out = $conn->prepare("UPDATE treasury_balances SET current_balance = current_balance - ?, last_updated = NOW() WHERE currency_id = ? AND branch_id = ?");
                $update_out->bind_param("dii", $required_balance_amount, $required_balance_currency_id, $transaction_branch_id); 
                if (!$update_out->execute()) throw new Exception("خطأ في خصم الرصيد: " . $update_out->error);
                $update_out->close();

                // 2. إضافة الرصيد (العملة المستلمة) - تطبيق branch_filter_id
                if ($amount_received > 0) {
                    $update_in = $conn->prepare("
                        INSERT INTO treasury_balances (currency_id, current_balance, last_updated, branch_id)
                        VALUES (?, ?, NOW(), ?)
                        ON DUPLICATE KEY UPDATE 
                            current_balance = current_balance + VALUES(current_balance),
                            last_updated = NOW()
                    ");
                    $update_in->bind_param("idi", $currency_received_id, $amount_received, $transaction_branch_id); 
                    if (!$update_in->execute()) throw new Exception("خطأ في إضافة الرصيد: " . $update_in->error);
                    $update_in->close();
                }

                // 3. تسجيل المعاملة
                // 🛑 تم التأكد من توافق الأعمدة وعدد المتغيرات مع (حذف الربح/الخسارة و إضافة طريقة الدفع)
                $insert_transaction_sql = "
                    INSERT INTO transactions (
                        client_id, user_id, amount_received, from_currency_id, 
                        amount_sold, to_currency_id, rate_used, 
                        transaction_type, discount_amount, branch_id, payment_method, transaction_date
                    ) VALUES (
                        ?, ?, ?, ?, 
                        ?, ?, ?, 
                        ?, ?, ?, ?, NOW()
                    )
                ";
                
                $insert_stmt = $conn->prepare($insert_transaction_sql);
                if ($insert_stmt === false) {
                    throw new Exception("فشل في إعداد استعلام التبادل: " . $conn->error);
                }

                // 🛑 التصحيح هنا: سلسلة النوع يجب أن تكون iidisidsdis (11 حرفًا) لتطابق الـ 11 متغيرًا
                // الترتيب: i, i, d, i, d, i, d, s, d, i, s
                $insert_stmt->bind_param("iidisidsdis", 
                    $client_id, 
                    $teller_id, 
                    $amount_received, 
                    $currency_received_id, 
                    $amount_sold, 
                    $currency_sold_id, 
                    $rate_used, 
                    $transaction_type, 
                    $discount, 
                    $transaction_branch_id,
                    $payment_method 
                );

                if (!$insert_stmt->execute()) {
                    throw new Exception("خطأ في تسجيل المعاملة: " . $insert_stmt->error);
                }
                
                $last_transaction_id = $insert_stmt->insert_id; 
                $insert_stmt->close();

                // 4. تحديث invoice_serial للعملية
                $get_max_serial_sql = "SELECT MAX(invoice_serial) AS max_serial FROM transactions WHERE branch_id = ?"; 
                $max_serial_stmt = $conn->prepare($get_max_serial_sql);
                $max_serial_stmt->bind_param("i", $transaction_branch_id);
                $max_serial_stmt->execute();
                $max_serial_result = $max_serial_stmt->get_result();
                $max_serial = $max_serial_result->fetch_assoc()['max_serial'] ?? 0;
                $max_serial_stmt->close();

                $new_invoice_serial = $max_serial + 1;
                
                $update_serial_sql = "UPDATE transactions SET invoice_serial = ? WHERE id = ?";
                $update_serial_stmt = $conn->prepare($update_serial_sql);
                $update_serial_stmt->bind_param("ii", $new_invoice_serial, $last_transaction_id);
                if (!$update_serial_stmt->execute()) throw new Exception("خطأ في تحديث رقم الفاتورة: " . $update_serial_stmt->error);
                $update_serial_stmt->close();

                $conn->commit();
                
                $currency_code_r = $currencies_array[$currency_received_id]['currency_code'] ?? 'N/A';
                $success_msg = "✅ تمت المعاملة بنجاح في فرع {$current_branch_name}. تم استلام " . number_format($amount_received, 4) . " {$currency_code_r} مقابل " . number_format($amount_sold, 4) . " " . ($currencies_array[$currency_sold_id]['currency_code'] ?? 'N/A') . ".";
                
                $message = $success_msg;
                $message_type = 'success';
                
            } catch (Exception $e) {
                $conn->rollback();
                $message = "فشل العملية: " . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}
end_processing:
// -----------------------------------------------------
// 3. جلب الأرصدة الحالية (للعرض)
// -----------------------------------------------------
$display_branch_id = $transaction_branch_id;

$balances_data_after = null;
if ($display_branch_id > 0) {
    // 🛑 تطبيق branch_filter_id في جلب الأرصدة للعرض 🛑
    $balances_query = "
        SELECT 
            tb.current_balance, c.currency_code, c.currency_name_ar
        FROM 
            treasury_balances tb
        JOIN 
            currencies c ON tb.currency_id = c.id
        WHERE
            tb.branch_id = {$display_branch_id}
        ORDER BY 
            c.currency_code ASC
    ";
    
    // قد تم إغلاق الاتصال مسبقاً في حالة POST، لذا نتحقق
    if (!isset($conn) || $conn->ping() === false) {
        // إعادة الاتصال إذا تم إغلاقه مسبقاً
        include 'db_connect.php'; 
    }
    
    $balances_data_after = $conn->query($balances_query);
}

// -----------------------------------------------------
// 4. جلب أسعار الصرف
$rates_query = "SELECT from_currency_id AS base_currency_id, to_currency_id AS target_currency_id, buy_rate, sell_rate FROM exchange_rates";
$rates_result = $conn->query($rates_query);
$exchange_rates = [];
if ($rates_result) {
    while($row = $rates_result->fetch_assoc()){
        $key = $row['base_currency_id'] . '_' . $row['target_currency_id'];
        $exchange_rates[$key] = [
            'buy' => (float)$row['buy_rate'],
            'sell' => (float)$row['sell_rate']
        ];
    }
}

// أغلق الاتصال فقط إذا كان لا يزال مفتوحاً
if (isset($conn) && $conn->ping()) {
    $conn->close();
}

?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إجراء عملية صرف - PayLink</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .content { margin-right: 250px; padding: 20px; background-color: #f8f9fa; min-height: 100vh; }
        .qr-placeholder { display: flex; flex-direction: column; align-items: center; margin-top: 15px; }
        .rate-info { font-size: 1.1em; font-weight: bold; margin-bottom: 15px; }
        .rate-info span { color: #2980b9; margin-left: 10px; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?> 

    <div class="content">
        <div class="container-fluid">
            <h1 class="mb-4 text-primary"><i class="fas fa-cash-register"></i> إجراء عملية صرف (بيع/شراء)</h1>
            
            <?php if ($transaction_branch_id > 0): ?>
                <p class="lead text-primary">الفرع النشط للعملية: <?php echo htmlspecialchars($current_branch_name); ?> (رقم: <?php echo htmlspecialchars($transaction_branch_id); ?>)</p>
            <?php else: ?>
                <div class="alert alert-danger">
                    لا يمكن إجراء عملية صرف. يرجى تحديد فرع نشط للعملية. إذا كنت مديراً عاماً، يرجى اختيار الفرع من لوحة التحكم أولاً.
                </div>
            <?php endif; ?>
            <hr>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> text-center d-flex flex-column align-items-center">
                <?php echo $message; ?>
                
                <?php if ($message_type == 'success' && $last_transaction_id): ?>
                    <h5 class="mt-4">إدارة الفاتورة رقم: INV-<?php echo $last_transaction_id; ?></h5>
                    <div class="d-flex justify-content-center flex-wrap">
                        
                        <?php
                            // إنشاء رابط الإيصال مع بيانات التوثيق
                            $receipt_url_params = "transaction_id={$last_transaction_id}&id_number=" . urlencode($client_id_number_after) . "&phone=" . urlencode($client_phone_number_after);
                            $receipt_link = "receipt.php?{$receipt_url_params}";
                            
                            // يتم استخدام amount_received و currency_received_id لرسالة النجاح
                            $amount_display = number_format($amount_received, 2);
                            $currency_code_final = $currencies_array[$currency_received_id]['currency_code'] ?? 'N/A';
                            
                            $whatsapp_text = "مرحباً بك! تمت عملية الصرف/التبادل رقم INV-{$last_transaction_id} بنجاح. المبلغ: {$amount_display} {$currency_code_final}. طريقة الدفع: {$payment_method}.";

                            $whatsapp_message = urlencode($whatsapp_text);
                            $whatsapp_link = "https://wa.me/{$client_phone_number_after}?text={$whatsapp_message}";
                        ?>
                        
                        <a href="<?php echo $receipt_link; ?>" target="_blank" class="btn btn-info mt-2 mx-2">
                            <i class="fas fa-print"></i> طباعة / توثيق الإيصال
                        </a>
                        
                        <?php if ($client_phone_number_after): ?>
                        <a href="<?php echo $whatsapp_link; ?>" target="_blank" class="btn btn-success mt-2 mx-2">
                            <i class="fab fa-whatsapp"></i> إرسال عبر واتساب
                        </a>
                        <?php endif; ?>
                        
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($transaction_branch_id > 0): // إخفاء النموذج إذا لم يكن هناك فرع نشط ?>
            <div class="row">
                <div class="col-lg-7">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            إدخال تفاصيل العملية
                        </div>
                        <div class="card-body">
                            <form method="POST" action="<?php echo $current_file_name; ?>">
                                
                                <div class="form-group">
                                    <label for="client_id">العميل:</label>
                                    <select class="form-control" name="client_id" required>
                                        <option value="">-- اختر العميل (المستفيد) --</option>
                                        <?php 
                                            foreach ($clients_data_array as $client): ?>
                                                <option value="<?php echo $client['id']; ?>">
                                                    <?php echo htmlspecialchars($client['full_name'] . ' (' . $client['id_number'] . ')'); ?>
                                                </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="transaction_type">نوع العملية:</label>
                                    <select class="form-control" id="transaction_type" name="transaction_type" required>
                                        <option value="">-- اختر نوع العملية --</option>
                                        <option value="SELL">بيع عملة (نحن نبيع عملة الهدف ونستلم الأساس)</option>
                                        <option value="BUY">شراء عملة (نحن نشتري عملة الهدف وندفع الأساس)</option>
                                        </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="payment_method">طريقة الدفع/الاستلام:</label>
                                    <select class="form-control" id="payment_method" name="payment_method" required>
                                        <option value="">-- اختر طريقة الدفع --</option>
                                        <option value="كاش">كاش (نقدي)</option>
                                        <option value="شيك">شيك مصرفي</option>
                                        <option value="حوالة">حوالة مصرفية</option>
                                        <option value="دفع إلكتروني">دفع إلكتروني</option>
                                    </select>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="base_currency_id">العملة الأساس (التي سيتم الدفع أو الاستلام بها):</label>
                                        <select class="form-control" id="base_currency_id" name="base_currency_id" required>
                                            <option value="">-- اختر العملة --</option>
                                            <?php foreach ($currencies_array as $currency): ?>
                                                <option value="<?php echo $currency['id']; ?>">
                                                    <?php echo htmlspecialchars($currency['currency_name_ar'] . ' (' . $currency['currency_code'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6" id="target_currency_group">
                                        <label for="target_currency_id">العملة الهدف (المراد تبادلها):</label>
                                        <select class="form-control" id="target_currency_id" name="target_currency_id" required>
                                            <option value="">-- اختر العملة --</option>
                                            <?php foreach ($currencies_array as $currency): ?>
                                                <option value="<?php echo $currency['id']; ?>">
                                                    <?php echo htmlspecialchars($currency['currency_name_ar'] . ' (' . $currency['currency_code'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="alert alert-secondary text-center" id="rate_display" style="display:none;">
                                    <p class="rate-info m-0">سعر الصرف المستخدم: <span id="current_rate_span">0.0000</span></p>
                                    <small id="rate_type_label" class="text-muted"></small>
                                </div>


                                <h5 class="mt-4 text-dark">المبالغ والحسابات</h5>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="amount_base" id="amount_base_label">الكمية المطلوبة/المدخلة يدوياً:</label>
                                        <input type="number" step="0.0001" class="form-control" id="amount_base" name="amount_base" required>
                                    </div>
                                    <div class="form-group col-md-6" id="amount_target_group">
                                        <label for="amount_target" id="amount_target_label">القيمة المكافئة المحسوبة (بالعملة الأخرى):</label>
                                        <input type="number" step="0.0001" class="form-control" id="amount_target" name="amount_target" readonly required>
                                    </div>
                                </div>

                                <div class="form-row" id="financial_controls_row">
                                    <div class="form-group col-md-12"> <label for="discount">الخصم (اختياري):</label>
                                        <input type="number" step="0.01" class="form-control" id="discount" name="discount" value="0">
                                        <small class="form-text text-muted">يُطبق على القيمة النهائية للعملة الهدف المحولة.</small>
                                    </div>
                                    </div>
                                
                                <input type="hidden" id="rate_used" name="rate_used">


                                <button type="submit" class="btn btn-primary btn-block mt-3"><i class="fas fa-check-circle"></i> إتمام عملية الصرف</button>
                            </form>
                        </div>
                    </div>
                </div>


                <div class="col-lg-5">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <i class="fas fa-balance-scale"></i> الأرصدة المتبقية في فرع <?php echo htmlspecialchars($current_branch_name); ?>
                        </div>
                        <div class="card-body">
                            <?php if ($balances_data_after && $balances_data_after->num_rows > 0): ?>
                                <ul class="list-group">
                                    <?php while ($row = $balances_data_after->fetch_assoc()): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo htmlspecialchars($row['currency_name_ar']); ?> (<?php echo htmlspecialchars($row['currency_code']); ?>)
                                            <span class="badge badge-success badge-pill" style="font-size: 1.1em; padding: 10px;">
                                                <?php echo number_format((float)$row['current_balance'], 4); ?>
                                            </span>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php elseif ($display_branch_id > 0): ?>
                                <div class="alert alert-warning text-center">
                                    لا توجد أرصدة عملات مسجلة لهذا الفرع حالياً.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    الرجاء اختيار فرع لعرض أرصدته.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        
        const exchangeRates = <?php echo json_encode($exchange_rates); ?>;
        
        const baseCurrencySelect = document.getElementById('base_currency_id');
        const targetCurrencySelect = document.getElementById('target_currency_id');
        const transactionTypeSelect = document.getElementById('transaction_type');
        const amountBaseInput = document.getElementById('amount_base');
        const amountBaseLabel = document.getElementById('amount_base_label');
        const amountTargetInput = document.getElementById('amount_target');
        const amountTargetLabel = document.getElementById('amount_target_label');
        const discountInput = document.getElementById('discount');
        const rateUsedHidden = document.getElementById('rate_used');
        const currentRateSpan = document.getElementById('current_rate_span');
        const rateDisplayDiv = document.getElementById('rate_display');
        const rateTypeLabel = document.getElementById('rate_type_label');
        
        // عناصر التحكم الجديدة
        const targetCurrencyGroup = document.getElementById('target_currency_group');
        const amountTargetGroup = document.getElementById('amount_target_group');
        const financialControlsRow = document.getElementById('financial_controls_row');

        // دالة تحديث واجهة المستخدم حسب نوع العملية
        function updateUIByType() {
            const type = transactionTypeSelect.value;
            
            // إعادة تعيين الحقول
            amountTargetInput.value = '';
            rateUsedHidden.value = '';
            rateDisplayDiv.style.display = 'none';

            if (type === 'SELL' || type === 'BUY') {
                
                // تحديث تسمية الحقول حسب نوع العملية لتوضيح منطق الإضافة/الخصم
                if (type === 'BUY') {
                    // BUY: نشتري الهدف (الدولار) وندفع الأساس (الدينار).
                    // amount_base: كمية الهدف (الدولار)، amount_target: القيمة بالأساس (الدينار)
                    amountBaseLabel.textContent = 'الكمية التي نستلمها (العملة الهدف):';
                    amountTargetLabel.textContent = 'القيمة التي ندفعها (العملة الأساس) (محسوب):';
                } else { // SELL
                    // SELL: نبيع الهدف (الدولار) ونستلم الأساس (الدينار).
                    // amount_base: الكمية المستلمة بالأساس (الدينار)، amount_target: كمية الهدف المباعة (الدولار)
                    amountBaseLabel.textContent = 'المبلغ الذي نستلمه (العملة الأساس):';
                    amountTargetLabel.textContent = 'الكمية التي نبيعها (العملة الهدف) (محسوب):';
                }
                
                // إظهار حقول التبادل
                targetCurrencyGroup.style.display = 'block';
                amountTargetGroup.style.display = 'block';
                financialControlsRow.style.display = 'flex';
                targetCurrencySelect.setAttribute('required', 'required');
                
                calculateTransaction(); // إعادة حساب التبادل
                
            } else {
                 // حالة لا يوجد اختيار
                amountBaseLabel.textContent = 'الكمية المطلوبة/المدخلة يدوياً:';
                amountTargetLabel.textContent = 'القيمة المكافئة المحسوبة (بالعملة الأخرى):';
                targetCurrencyGroup.style.display = 'block';
                amountTargetGroup.style.display = 'block';
                financialControlsRow.style.display = 'flex';
                targetCurrencySelect.removeAttribute('required');
            }
        }
        
        // دالة الحساب الرئيسية (لعمليات البيع والشراء فقط)
        function calculateTransaction() {
            const type = transactionTypeSelect.value;
            
            if (!type || (type !== 'SELL' && type !== 'BUY')) {
                return;
            }

            const baseId = baseCurrencySelect.value;
            const targetId = targetCurrencySelect.value;
            const amountBase = parseFloat(amountBaseInput.value) || 0;
            const discount = parseFloat(discountInput.value) || 0;

            let finalRate = 0;
            let amountTarget = 0;
            let rateKey = baseId + '_' + targetId; // السعر المباشر (Base -> Target)
            let inverseRateKey = targetId + '_' + baseId; // السعر العكسي (Target -> Base)
            
            rateDisplayDiv.style.display = 'none';

            if (baseId && targetId && baseId !== targetId && amountBase > 0) {
                
                // 1. حساب السعر بناءً على أسعار الصرف
                
                if (type === 'SELL') {
                    // SELL (نحن نبيع الهدف ونستلم الأساس)
                    // نحتاج سعر يحول من الأساس إلى الهدف (Base -> Target)
                    
                    if (exchangeRates[rateKey]) {
                        finalRate = exchangeRates[rateKey].sell; 
                        rateTypeLabel.textContent = 'السعر المستخدم: سعر بيع (SELL RATE) Base -> Target';
                    } else if (exchangeRates[inverseRateKey]) {
                        finalRate = 1 / exchangeRates[inverseRateKey].buy; 
                        rateTypeLabel.textContent = 'السعر المستخدم: مقلوب سعر الشراء (BUY RATE INVERSE) Target -> Base';
                    }
                    
                } else if (type === 'BUY') {
                    // BUY (نحن نشتري الهدف وندفع الأساس)
                    // نحتاج سعر يحول من الهدف إلى الأساس (Target -> Base)
                    
                    if (exchangeRates[inverseRateKey]) {
                        finalRate = exchangeRates[inverseRateKey].buy; 
                        rateTypeLabel.textContent = 'السعر المستخدم: سعر شراء (BUY RATE) Target -> Base';
                    } else if (exchangeRates[rateKey]) {
                        finalRate = 1 / exchangeRates[rateKey].sell; 
                        rateTypeLabel.textContent = 'السعر المستخدم: مقلوب سعر البيع (SELL RATE INVERSE) Base -> Target';
                    }
                }
                
                // 2. تطبيق النتائج على الحقول
                if (finalRate > 0) {
                    amountTarget = amountBase * finalRate;
                    
                    // الخصم يقلل المبلغ (amountTarget)
                    amountTarget = amountTarget - discount;
                    
                    // تأكد أن القيمة الهدف لا تقل عن الصفر بعد الخصم
                    amountTarget = Math.max(0, amountTarget); 

                    // عرض السعر
                    currentRateSpan.textContent = finalRate.toFixed(6);
                    rateDisplayDiv.style.display = 'block';

                    // تحديث الحقول
                    amountTargetInput.value = amountTarget.toFixed(4);
                    rateUsedHidden.value = finalRate.toFixed(6); 
                    
                } else {
                    currentRateSpan.textContent = 'غير متوفر';
                    rateDisplayDiv.style.display = 'block';
                    amountTargetInput.value = '';
                    rateUsedHidden.value = '';
                }
            } else {
                amountTargetInput.value = '';
                rateUsedHidden.value = '';
            }
        }

        // ربط الأحداث
        transactionTypeSelect.addEventListener('change', updateUIByType);
        baseCurrencySelect.addEventListener('change', updateUIByType); 
        targetCurrencySelect.addEventListener('change', updateUIByType); 
        amountBaseInput.addEventListener('input', calculateTransaction);
        discountInput.addEventListener('input', calculateTransaction);

        // تشغيل الدوال عند التحميل
        document.addEventListener('DOMContentLoaded', () => {
             // عند التحميل، تأكد من تشغيل دالة التحديث في حال وجود بيانات POST سابقة
            updateUIByType();
            calculateTransaction();
        });
    </script>
</body>
</html>