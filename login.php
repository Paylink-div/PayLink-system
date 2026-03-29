<?php
// login.php - النسخة الاحترافية الموحدة مع قفل الحماية التجريبي

// --- 1. كود حماية النسخة التجريبية (يجب أن يكون في البداية) ---
$expiry_date = "2026-04-05"; // تاريخ انتهاء الصلاحية (أسبوع من الآن)
$current_date = date("Y-m-d");

if ($current_date > $expiry_date) {
    die("
    <div style='text-align:center; padding:50px; font-family:\"Cairo\", sans-serif; direction:rtl; background:#f8fafc; height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center;'>
        <div style='background:white; padding:40px; border-radius:24px; box-shadow:0 10px 25px rgba(0,0,0,0.05); border:1px solid #e2e8f0; max-width:500px;'>
            <img src='images/logo.png' style='max-width:200px; margin-bottom:20px;'>
            <h2 style='color:#ef4444; margin-bottom:15px;'>انتهت الفترة التجريبية للمنظومة</h2>
            <p style='color:#64748b; font-size:1.1rem; line-height:1.6;'>نشكرك على تجربة نظام <b>PayLink</b>. للاستمرار في استخدام المنظومة والحصول على النسخة الكاملة، يرجى التواصل مع المطور.</p>
            <hr style='border:0; border-top:1px solid #eee; margin:25px 0;'>
            <div style='font-size:1.2rem; font-weight:700; color:#0f172a;'>
                <i class='fas fa-phone'></i> للتواصل والاشتراك: 
                <br>
                <span style='color:#38bdf8; font-size:1.5rem;'>0910288830</span>
            </div>
        </div>
    </div>
    ");
}
// --- نهاية كود الحماية ---

if (session_status() == PHP_SESSION_NONE) session_start();

// الربط بملف الاتصال الذكي 
include 'db_connect.php'; 

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$login_error = '';
$login_success = '';

if (isset($_GET['success_reset']) && $_GET['success_reset'] == 1) {
    $login_success = 'تم إعادة تعيين كلمة السر بنجاح.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username_input = $_POST['username'];
    $password_input = $_POST['password'];

    $sql = "SELECT id, username, full_name, password_hash, user_role, branch_id, is_active, permissions_json 
            FROM users 
            WHERE username = ?";
            
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        $login_error = 'عذراً، لم نتمكن من الوصول لبيانات الشركة في هذه القاعدة.';
    } else {
        $stmt->bind_param("s", $username_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password_input, $user['password_hash'])) {
                if ($user['is_active'] == 1) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['user_role'] = $user['user_role'];
                    $_SESSION['branch_id'] = $user['branch_id'] ?? 0;
                    
                    $permissions = json_decode($user['permissions_json'] ?? '[]', true);
                    $_SESSION['user_permissions'] = is_array($permissions) ? $permissions : [];

                    header("Location: index.php");
                    exit;
                } else {
                    $login_error = 'هذا الحساب معطل حالياً.';
                }
            } else {
                $login_error = 'كلمة المرور غير صحيحة.';
            }
        } else {
            $login_error = 'اسم المستخدم غير مسجل في هذه الشركة.';
        }
        $stmt->close();
    }
}

$display_company = defined('COMPANY_NAME') ? COMPANY_NAME : 'PayLink Login';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>دخول النظام | <?php echo $display_company; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #0f172a; --accent: #38bdf8; --error: #ef4444; }
        body { font-family: 'Cairo', sans-serif; background: #f8fafc; height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; }
        
        .login-card { background: white; width: 100%; max-width: 420px; padding: 40px; border-radius: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; position: relative; }
        .login-card::after { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 5px; background: var(--accent); border-radius: 24px 24px 0 0; }
        
        .header { text-align: center; margin-bottom: 30px; }
        .logo-box { margin-bottom: 15px; }
        .logo-box img { max-width: 250px; height: auto; }
        
        .header h2 { color: var(--primary); margin: 10px 0 5px; font-weight: 700; }
        .company-tag { display: inline-block; background: rgba(56, 189, 248, 0.1); color: var(--accent); padding: 4px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 600; }

        .input-group { margin-bottom: 20px; position: relative; }
        .input-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: #475569; }
        
        .field-container { position: relative; }
        .field-container i:not(.toggle-password) { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        
        input { width: 100%; padding: 12px 45px 12px 15px; border: 1.5px solid #e2e8f0; border-radius: 12px; font-family: 'Cairo'; font-size: 0.95rem; box-sizing: border-box; transition: 0.3s; background: #fcfdfe; }
        input:focus { border-color: var(--accent); outline: none; background: white; box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.1); }

        .toggle-password { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; padding: 5px; }
        .toggle-password:hover { color: var(--accent); }

        .btn-submit { width: 100%; padding: 14px; background: var(--primary); color: white; border: none; border-radius: 12px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: 0.3s; margin-top: 10px; }
        .btn-submit:hover { background: #1e293b; transform: translateY(-2px); }

        .alert { padding: 12px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .alert-danger { background: #fef2f2; color: var(--error); border: 1px solid #fee2e2; }
        
        .footer { text-align: center; margin-top: 25px; }
        .forgot-link { color: #64748b; text-decoration: none; font-size: 0.85rem; transition: 0.2s; }
        .forgot-link:hover { color: var(--accent); text-decoration: underline; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="header">
        <div class="logo-box">
            <img src="images/logo.png" alt="PayLink Logo">
        </div>
        <span class="company-tag"><?php echo $display_company; ?></span>
        <h2>تسجيل الدخول</h2>
        <p style="color: #64748b; font-size: 0.85rem;">أهلاً بك مجدداً، يرجى إدخال بياناتك</p>
    </div>

    <?php if ($login_error): ?>
        <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i> <?php echo $login_error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <label>اسم المستخدم</label>
            <div class="field-container">
                <i class="fas fa-user"></i>
                <input type="text" name="username" required placeholder="أدخل اسم المستخدم">
            </div>
        </div>

        <div class="input-group">
            <label>كلمة المرور</label>
            <div class="field-container">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" id="password" required placeholder="********">
                <i class="fas fa-eye toggle-password" id="eyeIcon"></i>
            </div>
        </div>

        <button type="submit" class="btn-submit">دخول <i class="fas fa-sign-in-alt mr-2"></i></button>
    </form>

    <div class="footer">
        <a href="forgot_password.php" class="forgot-link">نسيت كلمة المرور؟</a>
    </div>
</div>

<script>
    const eyeIcon = document.querySelector('#eyeIcon');
    const passwordField = document.querySelector('#password');

    eyeIcon.addEventListener('click', function () {
        const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordField.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
</script>

</body>
</html>