<?php
// admin_login.php - بوابة الإدارة العامة
include 'db_connect.php'; 

// التأكد أننا في الرابط الرئيسي وليس subdomain (منع دخول الأدمن من روابط الشركات)
if (defined('IS_COMPANY_CONTEXT')) {
    header("Location: login.php"); 
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    $stmt = $master_conn->prepare("SELECT * FROM super_admins WHERE username = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if ($admin && password_verify($pass, $admin['password'])) {
        $_SESSION['super_admin_id'] = $admin['id'];
        $_SESSION['super_admin_name'] = $admin['full_name'];
        header("Location: admin_dashboard.php");
        exit;
    } else {
        $error = "بيانات الدخول غير صحيحة!";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>بوابة صاحب المنظومة | PayLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Cairo', sans-serif; background: #0f172a; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; color: white; }
        .login-card { background: #1e293b; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); width: 380px; text-align: center; border: 1px solid #334155; }
        .logo { font-size: 1.8rem; font-weight: bold; color: #38bdf8; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 2px; }
        h3 { margin-bottom: 25px; color: #94a3b8; font-weight: 400; font-size: 1.1rem; }
        
        .input-group { position: relative; margin-bottom: 15px; text-align: right; }
        input { width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; font-family: 'Cairo'; }
        input:focus { border-color: #38bdf8; outline: none; }
        
        /* ميزة العين */
        .toggle-password { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #64748b; z-index: 10; }
        .toggle-password:hover { color: #38bdf8; }

        button { width: 100%; padding: 12px; background: #38bdf8; border: none; border-radius: 10px; color: #0f172a; font-weight: bold; cursor: pointer; font-size: 1rem; transition: 0.3s; margin-top: 10px; }
        button:hover { background: #0ea5e9; transform: translateY(-2px); }
        
        .error { background: rgba(248, 113, 113, 0.1); color: #f87171; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 0.85rem; border: 1px solid rgba(248, 113, 113, 0.2); }
        
        .forgot-link { display: block; margin-top: 20px; color: #64748b; text-decoration: none; font-size: 0.85rem; transition: 0.3s; }
        .forgot-link:hover { color: #38bdf8; }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="logo">PayLink Admin</div>
        <h3>دخول الإدارة العامة</h3>

        <?php if(isset($error)) echo "<div class='error'><i class='fas fa-exclamation-circle'></i> $error</div>"; ?>

        <form method="POST">
            <div class="input-group">
                <input type="text" name="username" placeholder="اسم المستخدم" required>
            </div>

            <div class="input-group">
                <input type="password" name="password" id="passwordField" placeholder="كلمة المرور" required>
                <i class="fas fa-eye toggle-password" id="eyeIcon"></i>
            </div>

            <button type="submit">دخول النظام</button>
        </form>

        <a href="forgot_password.php" class="forgot-link">نسيت كلمة المرور؟</a>
    </div>

    <script>
        // كود تشغيل ميزة العين
        const passwordField = document.querySelector('#passwordField');
        const eyeIcon = document.querySelector('#eyeIcon');

        eyeIcon.addEventListener('click', function () {
            // تبديل نوع الحقل
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            
            // تبديل شكل الأيقونة
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>

</body>
</html>