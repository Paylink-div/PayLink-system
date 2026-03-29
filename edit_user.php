<?php

session_start();

include 'db_connect.php'; 
// قد تحتاج أيضاً لتضمين ملف acl_functions.php هنا إذا كنت تستخدم دالة منه في مكان آخر

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}


$message = '';
$user_data = null;


if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    
    // 🛑 التعديل رقم 1: إضافة phone_number إلى استعلام جلب البيانات 🛑
    $sql = "SELECT id, username, full_name, user_role, phone_number, is_active FROM users WHERE id = $user_id";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    } else {
        $message = '<div class="alert alert-danger">معرف المستخدم غير صالح أو غير موجود.</div>';
    }
} else {
    header("Location: user_management.php");
    exit;
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && $user_data) {
    // 🛑 التعديل رقم 2: استقبال حقل phone_number من نموذج POST 🛑
    $username = $conn->real_escape_string($_POST['username']);
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $user_role = $conn->real_escape_string($_POST['user_role']);
    $phone_number = $conn->real_escape_string($_POST['phone_number']); // الحقل الجديد
    $password = $_POST['password'] ?? '';
    
    // 🛑 التعديل رقم 3: إضافة phone_number إلى حقول التحديث 🛑
    $update_fields = "username = '$username', full_name = '$full_name', user_role = '$user_role', phone_number = '$phone_number'";


    // تحديث كلمة المرور فقط إذا تم إدخال واحدة
    if (!empty($password)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $update_fields .= ", password_hash = '$password_hash'";
    }


    $update_sql = "UPDATE users SET $update_fields WHERE id = $user_id";
    
    // 🛑 المنطقة المحتملة للكود المفقود: إلغاء الشرط الأمني 🛑
    // بما أننا لا نراه، سنتجاوزها ونفترض أن المشكلة هي فقط في حقل الهاتف المفقود.
    // إذا ظهرت الرسالة بعد هذا التعديل، فسنحتاج إلى مراجعة ملف users_management.php

    if ($conn->query($update_sql) === TRUE) {
        // إذا تم تحديث بياناتك (بما في ذلك الهاتف)، يجب تحديث الجلسة فوراً
        if ($user_id == $_SESSION['user_id']) {
            $_SESSION['full_name'] = $full_name;
            // إذا كنت تخزن الهاتف في الجلسة، قم بتحديثه هنا
            // $_SESSION['phone_number'] = $phone_number; 
        }

        header("Location: user_management.php?success=updated");
        exit;
    } else {
        $message = '<div class="alert alert-danger">خطأ في التحديث: ' . $conn->error . '</div>';
    }
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعديل المستخدم: <?php echo htmlspecialchars($user_data['username'] ?? ''); ?></title>
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
            <h1 class="mb-4 text-warning"><i class="fas fa-user-edit"></i> تعديل بيانات المستخدم</h1>
            <hr>
            
            <?php echo $message; ?>

            <?php if ($user_data): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-white">
                    تعديل: <?php echo htmlspecialchars($user_data['full_name']); ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="edit_user.php?id=<?php echo $user_data['id']; ?>">
                        
                        <div class="form-group">
                            <label for="username">اسم المستخدم:</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">الاسم الكامل:</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone_number">رقم الهاتف:</label>
                            <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="user_role">الدور/الصلاحية:</label>
                            <select class="form-control" id="user_role" name="user_role" required>
                                <option value="MANAGER" <?php echo ($user_data['user_role'] == 'MANAGER') ? 'selected' : ''; ?>>MANAGER (مدير)</option>
                                <option value="TELLER" <?php echo ($user_data['user_role'] == 'TELLER') ? 'selected' : ''; ?>>TELLER (صراف)</option>
                            </select>
                        </div>


                        <div class="form-group">
                            <label for="password">كلمة المرور الجديدة (اتركها فارغة لعدم التغيير):</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="form-text text-muted">يرجى إدخال كلمة مرور جديدة فقط إذا كنت ترغب في تغييرها.</small>
                        </div>
                        
                        <button type="submit" class="btn btn-warning mt-3"><i class="fas fa-save"></i> حفظ التعديلات</button>
                        <a href="user_management.php" class="btn btn-secondary mt-3">إلغاء والعودة</a>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>


</body>
</html>

<?php $conn->close(); ?>