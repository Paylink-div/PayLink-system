<?php

// edit_company_account.php - تعديل بيانات حساب بنكي للشركة

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';
require_once 'acl_functions.php'; 
include 'sidebar.php'; 


// التحقق من اتصال قاعدة البيانات
if (!isset($conn) || $conn->connect_error) {
    die("خطأ في الاتصال بقاعدة البيانات.");
}

$current_user_id = $_SESSION['user_id'] ?? 0;

// 1. التحقق من تسجيل الدخول والصلاحية
if (!$current_user_id) {
    header("Location: index.php");
    exit;
}

if (!check_permission('CAN_MANAGE_COMPANY_ACCOUNTS')) {
    header("Location: unauthorized.php");
    exit;
}


$message = '';
$error = '';
$account_data = null;
$account_id = $_REQUEST['id'] ?? null; // قد يأتي من GET أو POST


// جلب البيانات الأساسية (العملات والفروع)
$currencies = $conn->query("SELECT id, currency_code FROM currencies ORDER BY currency_code")->fetch_all(MYSQLI_ASSOC);
$branches = $conn->query("SELECT id, name FROM branches ORDER BY name")->fetch_all(MYSQLI_ASSOC);


// ==========================================
// 2. معالجة طلب جلب بيانات الحساب الحالي
// ==========================================
if ($account_id) {
    $stmt = $conn->prepare("SELECT * FROM company_bank_accounts WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $account_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "الحساب البنكي غير موجود.";
            $account_id = null; // إيقاف المزيد من المعالجة
        } else {
            $account_data = $result->fetch_assoc();
        }
        $stmt->close();
    } else {
        $error = "خطأ في تجهيز استعلام الجلب: " . $conn->error;
    }
} else if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // إذا لم يكن هناك ID في GET ولم يكن طلب POST، نعرض خطأ
    $error = "يجب تحديد معرّف الحساب المراد تعديله.";
}


// ==========================================
// 3. معالجة إرسال نموذج التعديل (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $account_id) {
    
    // فلترة وتجهيز البيانات
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $currency_id = (int) ($_POST['currency_id'] ?? 0);
    $branch_id = (int) ($_POST['branch_id'] ?? null); // يمكن أن يكون NULL
    $current_balance = (float) ($_POST['current_balance'] ?? 0);

    // التحقق من صحة البيانات
    if (empty($bank_name) || empty($account_number) || $currency_id <= 0) {
        $error = "الرجاء تعبئة حقول اسم المصرف ورقم الحساب والعملة بشكل صحيح.";
    } elseif (!is_numeric($current_balance) || $current_balance < 0) {
         $error = "الرصيد الحالي غير صالح.";
    } else {
        // تحديث الحقول في قاعدة البيانات
        $sql = "UPDATE company_bank_accounts 
                SET bank_name = ?, account_number = ?, currency_id = ?, branch_id = ?, current_balance = ?, last_updated = NOW() 
                WHERE id = ?";
        
        // تجهيز الاستعلام
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            // ربط المعاملات
            // sssidi : 3x strings, 1x integer (branch_id), 1x float (balance), 1x integer (id)
            // ملاحظة: بما أن branch_id قد يكون NULL في قاعدة البيانات، يجب التعامل معه في bind_param.
            // في PHP، يفضل إرساله كـ int أو NULL.
            // يتم استخدام 'i' لـ branch_id هنا، وسنرسل NULL أو الرقم.

            $branch_param = $branch_id > 0 ? $branch_id : null;
            $stmt->bind_param("ssiisi", 
                                $bank_name, 
                                $account_number, 
                                $currency_id, 
                                $branch_param, // يجب أن تكون int
                                $current_balance, 
                                $account_id);

            if ($stmt->execute()) {
                $message = "تم تحديث بيانات الحساب البنكي بنجاح.";
                // إعادة جلب البيانات المحدثة للعرض
                $account_data['bank_name'] = $bank_name;
                $account_data['account_number'] = $account_number;
                $account_data['currency_id'] = $currency_id;
                $account_data['branch_id'] = $branch_param;
                $account_data['current_balance'] = $current_balance;

            } else {
                $error = "فشل التحديث: " . $stmt->error;
            }
            $stmt->close();
        } else {
             $error = "خطأ في تجهيز استعلام التحديث: " . $conn->error;
        }
    }
}

// أغلق الاتصال مؤقتاً لتضمين ملف القالب
$conn->close();
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعديل حساب بنكي - PayLink</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* يجب التأكد من تطابق الستايل مع ملف sidebar.php */
        .content { margin-right: 250px; padding: 20px; background-color: #f8f9fa; min-height: 100vh; }
    </style>
</head>
<body>
    <div class="content">
        <div class="container-fluid">
            <h1 class="mb-4 text-primary"><i class="fas fa-edit"></i> تعديل حساب بنكي للشركة</h1>
            <p class="lead">تحديث تفاصيل الحساب البنكي المحدد.</p>
            <hr>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($account_data): ?>
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white">
                    تعديل بيانات الحساب: #<?php echo htmlspecialchars($account_data['id']); ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="edit_company_account.php">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($account_data['id']); ?>">

                        <div class="form-group">
                            <label for="bank_name">اسم المصرف:</label>
                            <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                   value="<?php echo htmlspecialchars($account_data['bank_name'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="account_number">رقم الحساب:</label>
                            <input type="text" class="form-control" id="account_number" name="account_number" 
                                   value="<?php echo htmlspecialchars($account_data['account_number'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="current_balance">الرصيد الحالي:</label>
                                <input type="number" step="0.01" class="form-control" id="current_balance" name="current_balance" 
                                       value="<?php echo htmlspecialchars($account_data['current_balance'] ?? 0.00); ?>" required>
                                <small class="form-text text-muted">هذا هو الرصيد الفعلي للحساب.</small>
                            </div>
                            
                            <div class="form-group col-md-6">
                                <label for="currency_id">العملة:</label>
                                <select class="form-control" id="currency_id" name="currency_id" required>
                                    <option value="">اختر عملة</option>
                                    <?php foreach ($currencies as $currency): ?>
                                        <option value="<?php echo htmlspecialchars($currency['id']); ?>"
                                            <?php echo (isset($account_data['currency_id']) && $account_data['currency_id'] == $currency['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($currency['currency_code']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="branch_id">الفرع المسؤول (اختياري):</label>
                            <select class="form-control" id="branch_id" name="branch_id">
                                <option value="0">غير محدد</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo htmlspecialchars($branch['id']); ?>"
                                        <?php echo (isset($account_data['branch_id']) && $account_data['branch_id'] == $branch['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($branch['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save"></i> حفظ التعديلات</button>
                        <a href="company_treasury.php" class="btn btn-secondary mt-3"><i class="fas fa-arrow-right"></i> العودة للخزينة</a>
                    </form>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>