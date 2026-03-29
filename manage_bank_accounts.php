<?php

// manage_bank_accounts.php - إدارة الحسابات البنكية للشركة

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'db_connect.php'; 
// 🛑 تم حذف: require_once 'acl_functions.php';

// التحقق من تسجيل الدخول فقط (بعد إزالة ACL)
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); 
    exit;
}

$current_lang = $_SESSION['lang'] ?? 'ar';
$user_id = $_SESSION['user_id']; // يمكن الاحتفاظ به لتسجيل حركات لاحقة
$success_message = '';
$error_message = '';
$edit_mode = false;
$edit_account = [];

// =================================================================
// 1. معالجة طلبات الإضافة والتعديل (POST)
// =================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // تنظيف البيانات
    $bank_name = $conn->real_escape_string($_POST['bank_name']);
    $account_number = $conn->real_escape_string($_POST['account_number']);
    $currency_code = $conn->real_escape_string($_POST['currency_code']); // تم التعديل
    $branch_name = $conn->real_escape_string($_POST['branch_name'] ?? NULL);
    $current_balance = (float)$_POST['current_balance'];
    $is_active = (int)$_POST['is_active']; // تم التعديل
    $action = $_POST['action'] ?? 'add';
    $account_id = (int)($_POST['account_id'] ?? 0);

    // --- التحقق الأولي ---
    if (empty($bank_name) || empty($account_number) || empty($currency_code)) {
        $error_message = "الرجاء تعبئة الحقول المطلوبة (اسم البنك، رقم الحساب، رمز العملة).";
        goto end_process;
    }
    
    if ($action === 'add') {
        // إضافة حساب جديد
        $sql = "INSERT INTO bank_accounts (
                    bank_name, account_number, currency_code, branch_name, current_balance, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        // تم حذف 'user_id' من bind_param
        $stmt->bind_param("sssdsi", 
            $bank_name, $account_number, $currency_code, $branch_name, $current_balance, $is_active
        );

        if ($stmt->execute()) {
            $success_message = "تم إضافة الحساب البنكي بنجاح.";
        } else {
            $error_message = "فشل إضافة الحساب: " . $stmt->error;
        }
        $stmt->close();

    } elseif ($action === 'edit' && $account_id > 0) {
        // تعديل حساب موجود
        $sql = "UPDATE bank_accounts SET 
                    bank_name = ?, account_number = ?, currency_code = ?, branch_name = ?, current_balance = ?, is_active = ?
                WHERE id = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssdsii", 
            $bank_name, $account_number, $currency_code, $branch_name, $current_balance, $is_active, $account_id
        );

        if ($stmt->execute()) {
            $success_message = "تم تحديث الحساب البنكي بنجاح.";
            $edit_mode = false; // الخروج من وضع التعديل
        } else {
            $error_message = "فشل تحديث الحساب: " . $stmt->error;
        }
        $stmt->close();
    }
}

// =================================================================
// 2. معالجة طلبات الحذف والتعديل (GET)
// =================================================================

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $account_id = (int)$_GET['id'];
    
    if ($action === 'delete' && $account_id > 0) {
        // حذف منطقي (Soft Delete) - الأفضل عدم حذف البيانات المالية
        $sql = "UPDATE bank_accounts SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $account_id);
        
        if ($stmt->execute()) {
            $success_message = "تم تعطيل (حذف منطقي) الحساب بنجاح.";
        } else {
            $error_message = "فشل تعطيل الحساب: " . $stmt->error;
        }
        $stmt->close();

    } elseif ($action === 'edit' && $account_id > 0) {
        // جلب بيانات الحساب للتعديل
        $sql = "SELECT id, bank_name, account_number, currency_code, branch_name, current_balance, is_active FROM bank_accounts WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $account_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $edit_account = $result->fetch_assoc();
            $edit_mode = true;
        } else {
            $error_message = "الحساب غير موجود أو غير صالح.";
        }
        $stmt->close();
    }
}

// =================================================================
// 3. جلب قائمة الحسابات البنكية (لعرضها في الجدول)
// =================================================================

// 🛑 تم تصحيح: استخدام currency_code AS currency و is_active AS status
// 🛑 تم حذف: العمود غير الموجود 'transf'
$accounts_query = "
    SELECT 
        id, bank_name, account_number, currency_code AS currency, 
        current_balance, is_active AS status, branch_name 
    FROM 
        bank_accounts 
    ORDER BY 
        bank_name, currency_code";

$accounts_result = $conn->query($accounts_query);
$accounts = [];

if ($accounts_result) {
    while ($row = $accounts_result->fetch_assoc()) {
        $accounts[] = $row;
    }
}

// =================================================================
// 4. جلب قائمة العملات المتاحة (افتراضياً)
// =================================================================

$currencies_query = "SELECT currency_code FROM currencies ORDER BY currency_code";
$currencies_result = $conn->query($currencies_query);
$currencies = [];

if ($currencies_result) {
    while ($row = $currencies_result->fetch_assoc()) {
        $currencies[] = $row['currency_code'];
    }
} else {
    // توفير عملات افتراضية إذا كان الجدول غير موجود أو حدث خطأ
    $currencies = ['LYD', 'USD', 'EUR', 'GBP'];
}

end_process:
?>


<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الحسابات البنكية - PayLink</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .content { margin-right: 250px; padding: 20px; min-height: 100vh; background-color: #f8f9fa; }
        @media (max-width: 768px) { .content { margin-right: 0 !important; padding-top: 70px; } }
        .action-cell { min-width: 150px; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <h1 class="mb-4 text-primary"><i class="fas fa-university"></i> إدارة الحسابات البنكية للشركة</h1>
            
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <?php echo $edit_mode ? '<i class="fas fa-edit"></i> تعديل حساب بنكي' : '<i class="fas fa-plus-circle"></i> إضافة حساب بنكي جديد'; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'add'; ?>">
                        <input type="hidden" name="account_id" value="<?php echo $edit_account['id'] ?? ''; ?>">

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="bank_name">اسم البنك:</label>
                                <input type="text" class="form-control" id="bank_name" name="bank_name" value="<?php echo htmlspecialchars($edit_account['bank_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="account_number">رقم الحساب:</label>
                                <input type="text" class="form-control" id="account_number" name="account_number" value="<?php echo htmlspecialchars($edit_account['account_number'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="currency_code">رمز العملة:</label>
                                <select class="form-control" id="currency_code" name="currency_code" required>
                                    <option value="">-- اختر عملة --</option>
                                    <?php 
                                    $selected_currency = $edit_account['currency_code'] ?? '';
                                    foreach ($currencies as $currency): ?>
                                        <option value="<?php echo $currency; ?>" <?php echo ($selected_currency === $currency) ? 'selected' : ''; ?>>
                                            <?php echo $currency; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="current_balance">الرصيد الحالي (<?php echo $edit_mode ? htmlspecialchars($edit_account['currency_code']) : 'العملة المختارة'; ?>):</label>
                                <input type="number" step="0.01" class="form-control" id="current_balance" name="current_balance" value="<?php echo htmlspecialchars($edit_account['current_balance'] ?? 0.00); ?>" required <?php echo $edit_mode ? 'readonly title="لا يمكن تعديل الرصيد مباشرة، استخدم حركات البنك."' : ''; ?>>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="branch_name">اسم الفرع (اختياري):</label>
                                <input type="text" class="form-control" id="branch_name" name="branch_name" value="<?php echo htmlspecialchars($edit_account['branch_name'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="is_active">الحالة:</label>
                            <select class="form-control" id="is_active" name="is_active">
                                <option value="1" <?php echo (isset($edit_account['is_active']) && $edit_account['is_active'] == 1) ? 'selected' : ''; ?>>نشط (Active)</option>
                                <option value="0" <?php echo (isset($edit_account['is_active']) && $edit_account['is_active'] == 0) ? 'selected' : ''; ?>>مغلق/معطل (Closed/Disabled)</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="fas fa-save"></i> <?php echo $edit_mode ? 'حفظ التعديلات' : 'إضافة الحساب'; ?>
                        </button>
                        <?php if ($edit_mode): ?>
                            <a href="manage_bank_accounts.php" class="btn btn-secondary mt-3"><i class="fas fa-times"></i> إلغاء التعديل</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <h2 class="mt-5 mb-3 text-secondary"><i class="fas fa-list-alt"></i> قائمة الحسابات البنكية المسجلة</h2>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead class="bg-dark text-white">
                        <tr>
                            <th>#</th>
                            <th>اسم البنك</th>
                            <th>رقم الحساب</th>
                            <th>العملة</th>
                            <th>الفرع</th>
                            <th>الرصيد الحالي</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($accounts) > 0): ?>
                            <?php foreach ($accounts as $account): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($account['id']); ?></td>
                                    <td><?php echo htmlspecialchars($account['bank_name']); ?></td>
                                    <td><?php echo htmlspecialchars($account['account_number']); ?></td>
                                    <td><?php echo htmlspecialchars($account['currency']); ?></td>
                                    <td><?php echo htmlspecialchars($account['branch_name'] ?? '---'); ?></td>
                                    <td><?php echo number_format($account['current_balance'], 2) . ' ' . htmlspecialchars($account['currency']); ?></td>
                                    <td>
                                        <?php if ($account['status'] == 1): ?>
                                            <span class="badge badge-success">نشط</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">مغلق/معطل</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-cell">
                                        <a href="manage_bank_accounts.php?action=edit&id=<?php echo $account['id']; ?>" class="btn btn-sm btn-info mr-2"><i class="fas fa-edit"></i> تعديل</a>
                                        <a href="manage_bank_accounts.php?action=delete&id=<?php echo $account['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد من تعطيل هذا الحساب؟')"><i class="fas fa-times-circle"></i> تعطيل</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">لا توجد حسابات بنكية مسجلة حالياً.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>


        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>