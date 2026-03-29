<?php
session_start();
include 'db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $user_role = $conn->real_escape_string($_POST['user_role']);
    
    // تشفير كلمة المرور قبل الحفظ
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // التحقق من أن اسم المستخدم غير موجود
    $check_sql = "SELECT id FROM users WHERE username = '$username'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        $message = '<div class="alert alert-danger">اسم المستخدم هذا موجود بالفعل.</div>';
    } else {
        // إدراج المستخدم الجديد
        $sql = "INSERT INTO users (username, password_hash, full_name, user_role, is_active) 
                VALUES ('$username', '$password_hash', '$full_name', '$user_role', 1)";
        
        if ($conn->query($sql) === TRUE) {
            header("Location: user_management.php?success=added");
            exit;
        } else {
            $message = '<div class="alert alert-danger">خطأ: ' . $conn->error . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إضافة مستخدم جديد</title>
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
            <h1 class="mb-4 text-success"><i class="fas fa-user-plus"></i> إضافة مستخدم جديد</h1>
            <hr>
            
            <?php echo $message; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    بيانات المستخدم
                </div>
                <div class="card-body">
                    <form method="POST" action="add_user.php">
                        
                        <div class="form-group">
                            <label for="username">اسم المستخدم:</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">كلمة المرور:</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">الاسم الكامل:</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="user_role">الدور/الصلاحية:</label>
                            <select class="form-control" id="user_role" name="user_role" required>
                                <option value="MANAGER">MANAGER (مدير)</option>
                                <option value="TELLER">TELLER (صراف)</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-success mt-3"><i class="fas fa-save"></i> حفظ وإضافة</button>
                        <a href="user_management.php" class="btn btn-secondary mt-3">إلغاء والعودة</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
<?php $conn->close(); ?>