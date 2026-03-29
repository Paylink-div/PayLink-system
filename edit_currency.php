<?php
session_start();
include 'db_connect.php'; 

// التحقق من الصلاحية
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = '';
$currency_id = 0;
$currency_data = null;
$local_currency_code = 'LYD';

// -----------------------------------------------------------------------
// 1. جلب بيانات العملة الحالية
// -----------------------------------------------------------------------
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $currency_id = intval($_GET['id']);
    
    $fetch_sql = "SELECT * FROM currencies WHERE id = $currency_id LIMIT 1";
    $result = $conn->query($fetch_sql);

    if ($result && $result->num_rows > 0) {
        $currency_data = $result->fetch_assoc();
    } else {
        $message = '<div class="alert alert-danger">خطأ: لم يتم العثور على العملة المطلوبة.</div>';
    }
} else {
    $message = '<div class="alert alert-danger">خطأ: لم يتم تحديد معرف العملة بشكل صحيح.</div>';
}

// -----------------------------------------------------------------------
// 2. معالجة تحديث بيانات العملة (POST)
// -----------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_currency']) && $currency_data) {
    
    $new_code = $conn->real_escape_string(strtoupper($_POST['currency_code']));
    $new_name = $conn->real_escape_string($_POST['currency_name']);
    
    if (empty($new_code) || empty($new_name)) {
        $message = '<div class="alert alert-danger">الرجاء إدخال رمز واسم العملة.</div>';
    } else {
        $update_sql = "UPDATE currencies SET currency_name = '$new_name'";

        // ⚠ حماية رمز العملة المحلية (LYD) من التغيير
        if ($currency_data['currency_code'] != $local_currency_code) {
             // التحقق من أن الرمز الجديد غير مستخدم من قبل عملة أخرى
            $check_code_sql = "SELECT id FROM currencies WHERE currency_code = '$new_code' AND id != $currency_id";
            if ($conn->query($check_code_sql)->num_rows > 0) {
                $message = '<div class="alert alert-danger">الرمز الجديد مستخدم بالفعل لعملة أخرى.</div>';
            } else {
                $update_sql .= ", currency_code = '$new_code'";
            }
        }
        
        // إكمال جملة التحديث
        $update_sql .= " WHERE id = $currency_id";

        if (empty($message)) { // إذا لم تكن هناك رسالة خطأ سابقة
            if ($conn->query($update_sql) === TRUE) {
                // تحديث البيانات المعروضة فوراً
                $currency_data['currency_code'] = $new_code;
                $currency_data['currency_name'] = $new_name;
                $message = '<div class="alert alert-success">تم تحديث بيانات العملة بنجاح!</div>';
            } else {
                $message = '<div class="alert alert-danger">خطأ في التحديث: ' . $conn->error . '</div>';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعديل العملة - PayLink</title>
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
            <h1 class="mb-4 text-primary"><i class="fas fa-edit"></i> تعديل بيانات العملة</h1>
            <hr>
            
            <?php echo $message; ?>

            <?php if ($currency_data): ?>
                <div class="card shadow-sm col-md-8 mx-auto">
                    <div class="card-header bg-warning text-white">
                        تعديل: <?php echo htmlspecialchars($currency_data['currency_name'] . ' (' . $currency_data['currency_code'] . ')'); ?>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="edit_currency.php?id=<?php echo $currency_id; ?>">
                            
                            <div class="form-group">
                                <label for="currency_code">رمز العملة (مثل USD):</label>
                                <input type="text" class="form-control" id="currency_code" name="currency_code" 
                                       value="<?php echo htmlspecialchars($currency_data['currency_code']); ?>" 
                                       required maxlength="3" 
                                       <?php echo ($currency_data['currency_code'] == $local_currency_code) ? 'disabled title="لا يمكن تعديل رمز العملة الأساسية"' : ''; ?>>
                                <?php if ($currency_data['currency_code'] == $local_currency_code): ?>
                                    <small class="form-text text-danger">لا يمكن تعديل رمز العملة المحلية (<?php echo $local_currency_code; ?>).</small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="currency_name">اسم العملة:</label>
                                <input type="text" class="form-control" id="currency_name" name="currency_name" 
                                       value="<?php echo htmlspecialchars($currency_data['currency_name']); ?>" required>
                            </div>
                            
                            <a href="currencies.php" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> العودة</a>
                            <button type="submit" name="update_currency" class="btn btn-warning float-left">
                                <i class="fas fa-save"></i> حفظ التعديلات
                            </button>
                        </form>
                    </div>
                </div>
            <?php elseif (empty($message)): ?>
                 <div class="alert alert-info col-md-8 mx-auto">
                    الرجاء العودة إلى شاشة إدارة العملات لاختيار عملة للتعديل.
                </div>
            <?php endif; ?>

        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>