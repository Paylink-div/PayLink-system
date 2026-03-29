<?php

// transfer_between_accounts.php - تنفيذ تحويل بين حسابات الشركة البنكية (يعتمد على تسجيل الدخول فقط)


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


include 'db_connect.php'; 
// 🛑🛑 إزالة تضمين acl_functions.php 🛑🛑


// التحقق من تسجيل الدخول فقط
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); 
    exit;
}
// 🛑🛑 إزالة التحقق من صلاحية CAN_MANAGE_COMPANY_ACCOUNTS 🛑🛑


$current_lang = $_SESSION['lang'] ?? 'ar';
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
$active_accounts = [];


// =================================================================
// 1. جلب الحسابات البنكية النشطة (تم تصحيح أسماء الأعمدة)
// =================================================================

// تم تعديل 'currency' إلى 'currency_code' وتم استخدام 'is_active' بدلاً من 'status'
$accounts_query = "SELECT id, bank_name, account_number, currency_code AS currency FROM bank_accounts WHERE is_active = 1 ORDER BY bank_name, currency_code";

$result = $conn->query($accounts_query);


if ($result) {
    while ($row = $result->fetch_assoc()) {
        $active_accounts[] = $row;
    }
} else {
    $error_message .= "خطأ في جلب قائمة الحسابات النشطة: " . $conn->error;
}


// =================================================================
// 2. معالجة طلب التحويل (POST)
// =================================================================


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // تنظيف البيانات
    $source_id = (int)$_POST['source_account_id'];
    $destination_id = (int)$_POST['destination_account_id'];
    $amount = (float)$_POST['amount'];
    $transaction_date = $conn->real_escape_string($_POST['transaction_date']);
    $description = $conn->real_escape_string($_POST['description'] ?? 'تحويل داخلي');
    $exchange_rate = (float)$_POST['exchange_rate'] ?? 1.0;
    
    $source_currency = $_POST['source_currency'];
    $destination_currency = $_POST['destination_currency'];


    // --- التحقق الأولي ---
    if ($source_id == $destination_id) {
        $error_message = "لا يمكن التحويل من وإلى نفس الحساب.";
        goto end_transfer_process;
    }
    if ($source_id <= 0 || $destination_id <= 0 || $amount <= 0 || empty($transaction_date)) {
         $error_message = "الرجاء تعبئة جميع الحقول بشكل صحيح.";
        goto end_transfer_process;
    }
    if ($source_currency != $destination_currency && $exchange_rate <= 0) {
        $error_message = "يجب تحديد سعر الصرف للتحويل بين عملات مختلفة.";
        goto end_transfer_process;
    }


    // حساب المبلغ المحول إلى عملة الحساب المستلم
    $destination_amount = $amount;
    if ($source_currency != $destination_currency) {
        $destination_amount = $amount * $exchange_rate;
    }
    
    // --- بدء المعاملة (Transaction) لضمان الأتمتة ---
    $conn->begin_transaction();
    $all_queries_successful = true;
    
    // مرجع تحويل فريد يربط بين حركتي الخصم والإضافة
    $transfer_reference = 'TRANS-' . time() . '-' . mt_rand(1000, 9999); 


    try {
        
        // 1. تسجيل الحركة الصادرة (الخصم)
        $description_out = "تحويل داخلي صادر إلى: حساب رقم $destination_id ({$destination_currency}). " . $description;
        $sql_out = "INSERT INTO bank_movements (
                        account_id, movement_type, amount, currency, transaction_date, 
                        description, reference_number, created_by_user_id, created_at
                    ) VALUES (?, 'internal_out', ?, ?, ?, ?, ?, ?, NOW())";
        
        // تم تغيير نوع المعاملة من 'd' (فلوت) إلى 's' (سلسلة) لـ amount لضمان التوافق مع PHP 8.1+، لكن سأبقيها 'd' كما في الكود الأصلي
        // ملاحظة: الأفضل استخدام 'd' إذا كانت قاعدة البيانات تدعمها، أو 's' إذا كان هناك مشاوفل في التنسيق
        $stmt_out = $conn->prepare($sql_out);
        $stmt_out->bind_param("idssssi", 
            $source_id, $amount, $source_currency, $transaction_date, 
            $description_out, $transfer_reference, $user_id
        );


        if (!$stmt_out->execute()) {
            $all_queries_successful = false;
            $error_message = "فشل تسجيل الحركة الصادرة: " . $stmt_out->error;
        }
        $stmt_out->close();


        // 2. تسجيل الحركة الواردة (الإضافة)
        if ($all_queries_successful) {
            $description_in = "تحويل داخلي وارد من: حساب رقم $source_id ({$source_currency}). " . $description;
            $sql_in = "INSERT INTO bank_movements (
                            account_id, movement_type, amount, currency, transaction_date, 
                            description, reference_number, created_by_user_id, created_at
                        ) VALUES (?, 'internal_in', ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt_in = $conn->prepare($sql_in);
            $stmt_in->bind_param("idssssi", 
                $destination_id, $destination_amount, $destination_currency, $transaction_date, 
                $description_in, $transfer_reference, $user_id
            );


            if (!$stmt_in->execute()) {
                $all_queries_successful = false;
                $error_message = "فشل تسجيل الحركة الواردة: " . $stmt_in->error;
            }
            $stmt_in->close();
        }


        // --- إنهاء المعاملة ---
        if ($all_queries_successful) {
            $conn->commit();
            $success_message = "تم تنفيذ التحويل الداخلي بنجاح! مرجع التحويل: {$transfer_reference}";
            $_POST = array(); // مسح قيم النموذج
        } else {
            $conn->rollback();
            $error_message = "فشل التحويل الداخلي. تم التراجع عن جميع الحركات. السبب: " . $error_message;
        }


    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "حدث خطأ غير متوقع أثناء المعاملة: " . $e->getMessage();
    }

}


end_transfer_process:

?>


<!DOCTYPE html>

<html lang="<?php echo $current_lang; ?>" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>التحويلات الداخلية - PayLink</title>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <style>

        .content { margin-right: 250px; padding: 20px; min-height: 100vh; background-color: #f8f9fa; }

        @media (max-width: 768px) { .content { margin-right: 0 !important; padding-top: 70px; } }

        .transfer-card { border-top: 5px solid #ffc107; }

        .transfer-arrow { font-size: 2rem; color: #ffc100; margin: 0 15px; }

    </style>

</head>

<body>


    <?php include 'sidebar.php'; ?>


    <div class="content">

        <div class="container-fluid">

            <h1 class="mb-4 text-warning"><i class="fas fa-exchange-alt"></i> التحويلات بين الحسابات البنكية الداخلية</h1>

            <p class="lead">تنفيذ تحويلات مالية بين حسابات الشركة البنكية.</p>

            
            <hr>


            <?php if (!empty($success_message)): ?>

                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>

            <?php endif; ?>

            <?php if (!empty($error_message)): ?>

                <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?php echo $error_message; ?></div>

            <?php endif; ?>


            <?php if (count($active_accounts) < 2): ?>

                <div class="alert alert-info">

                    <i class="fas fa-info-circle"></i> يجب أن يكون لديك حسابين بنكيين نشطين على الأقل لتتمكن من إجراء تحويل داخلي. <a href="manage_bank_accounts.php" class="alert-link">إدارة الحسابات البنكية</a>

                </div>

            <?php else: ?>


                <div class="card shadow transfer-card">

                    <div class="card-header bg-warning text-dark">

                        تعبئة نموذج التحويل

                    </div>

                    <div class="card-body">

                        <form method="POST">

                            
                            <div class="row align-items-center mb-4">

                                
                                <div class="col-md-5">

                                    <div class="form-group">

                                        <label for="source_account_id"><i class="fas fa-arrow-up"></i> من حساب (Source):</label>

                                        <select class="form-control" id="source_account_id" name="source_account_id" required onchange="updateCurrencies()">

                                            <option value="">-- اختر الحساب المصدر --</option>

                                            <?php foreach ($active_accounts as $account): ?>

                                            <option value="<?php echo $account['id']; ?>" data-currency="<?php echo $account['currency']; ?>">

                                                <?php echo htmlspecialchars($account['bank_name']) . ' (' . $account['currency'] . ')'; ?>

                                            </option>

                                            <?php endforeach; ?>

                                        </select>

                                        <input type="hidden" id="source_currency" name="source_currency">

                                        <small class="form-text text-danger">تأكد من وجود رصيد كافٍ في هذا الحساب.</small>

                                    </div>

                                </div>


                                <div class="col-md-2 text-center">

                                    <i class="fas fa-long-arrow-alt-left transfer-arrow"></i>

                                </div>

                                
                                <div class="col-md-5">

                                    <div class="form-group">

                                        <label for="destination_account_id"><i class="fas fa-arrow-down"></i> إلى حساب (Destination):</label>

                                        <select class="form-control" id="destination_account_id" name="destination_account_id" required onchange="updateCurrencies()">

                                            <option value="">-- اختر الحساب المستلم --</option>

                                            <?php foreach ($active_accounts as $account): ?>

                                            <option value="<?php echo $account['id']; ?>" data-currency="<?php echo $account['currency']; ?>">

                                                <?php echo htmlspecialchars($account['bank_name']) . ' (' . $account['currency'] . ')'; ?>

                                            </option>

                                            <?php endforeach; ?>

                                        </select>

                                        <input type="hidden" id="destination_currency" name="destination_currency">

                                    </div>

                                </div>


                            </div>

                            
                            <div class="row">

                                <div class="col-md-6">

                                    <div class="form-group">

                                        <label for="amount"><i class="fas fa-money-bill-wave"></i> المبلغ المراد تحويله (بعملة المصدر):</label>

                                        <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" required>

                                        <small class="form-text text-info" id="source_currency_info">عملة المصدر: --</small>

                                    </div>

                                </div>

                                <div class="col-md-6">

                                    <div class="form-group">

                                        <label for="transaction_date"><i class="fas fa-calendar-alt"></i> تاريخ التحويل:</label>

                                        <input type="date" class="form-control" id="transaction_date" name="transaction_date" required value="<?php echo date('Y-m-d'); ?>">

                                    </div>

                                </div>

                            </div>

                            
                            <div class="form-group bg-light p-3 rounded" id="exchange_rate_group" style="display:none;">

                                <label for="exchange_rate"><i class="fas fa-dollar-sign"></i> سعر الصرف (1 وحدة من عملة المصدر = ؟ من عملة الوجهة):</label>

                                <input type="number" step="0.0001" min="0.0001" class="form-control" id="exchange_rate" name="exchange_rate" value="1.0">

                                <small class="form-text text-danger">مهم: الحسابات بعُملتين مختلفتين. المبلغ المستلم سيكون: المبلغ المحول * سعر الصرف.</small>

                                <small class="form-text text-info" id="destination_amount_info">المبلغ المستلم المتوقع: --</small>

                            </div>


                            <div class="form-group">

                                <label for="description"><i class="fas fa-comment-dots"></i> وصف/غرض التحويل: (اختياري)</label>

                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="أدخل تفاصيل التحويل لغرض التدقيق..."></textarea>

                            </div>

                            

                            <button type="submit" class="btn btn-warning btn-lg mt-3 text-dark"><i class="fas fa-check-double"></i> تأكيد تنفيذ التحويل</button>

                            <a href="company_treasury.php" class="btn btn-secondary btn-lg mt-3"><i class="fas fa-arrow-right"></i> العودة للخزينة</a>

                        </form>

                    </div>

                </div>

            <?php endif; ?>


        </div>

    </div>


    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>

        document.addEventListener('DOMContentLoaded', function() {

            updateCurrencies(); // لضبط الحالة الأولية عند تحميل الصفحة

            
            // تحديث سعر الصرف والمبلغ المستلم عند التغيير
            $('#amount, #exchange_rate').on('input', updateDestinationAmount);
        });


        // دالة لتحديث العملات وعرض حقل سعر الصرف
        function updateCurrencies() {
            const sourceSelect = document.getElementById('source_account_id');
            const destSelect = document.getElementById('destination_account_id');
            
            const sourceCurrency = sourceSelect.options[sourceSelect.selectedIndex].getAttribute('data-currency');
            const destCurrency = destSelect.options[destSelect.selectedIndex].getAttribute('data-currency');
            
            // تحديث حقول العملة المخفية
            document.getElementById('source_currency').value = sourceCurrency;
            document.getElementById('destination_currency').value = destCurrency;
            
            // تحديث معلومات عملة المصدر
            document.getElementById('source_currency_info').textContent = 'عملة المصدر: ' + (sourceCurrency || '--');


            const rateGroup = document.getElementById('exchange_rate_group');
            const rateInput = document.getElementById('exchange_rate');
            
            if (sourceCurrency && destCurrency && sourceCurrency !== destCurrency) {
                // إظهار حقل سعر الصرف وإلزامه
                rateGroup.style.display = 'block';
                rateInput.setAttribute('required', 'required');
                rateInput.value = rateInput.value === '1.0' ? '1.0' : rateInput.value; // حافظ على القيمة إن لم تكن 1.0
            } else {
                // إخفاء حقل سعر الصرف وتعيينه 1
                rateGroup.style.display = 'none';
                rateInput.removeAttribute('required');
                rateInput.value = '1.0';
            }
            
            updateDestinationAmount(); // تحديث المبلغ المتوقع بعد تغيير العملات
        }
        
        // دالة لحساب وعرض المبلغ المستلم المتوقع
        function updateDestinationAmount() {
            const amount = parseFloat($('#amount').val()) || 0;
            const rate = parseFloat($('#exchange_rate').val()) || 1.0;
            const destCurrency = $('#destination_currency').val();
            
            let destinationAmount = amount;
            if ($('#exchange_rate_group').is(':visible')) {
                destinationAmount = amount * rate;
            }
            
            $('#destination_amount_info').text('المبلغ المستلم المتوقع: ' + destinationAmount.toFixed(2) + ' ' + (destCurrency || '--'));
        }

    </script>
</body>
</html>

<?php $conn->close(); ?>