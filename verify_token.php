<?php

// =========================================================================================
// verify_token.php - واجهة إدخال رمز التحقق ومعالجته
// =========================================================================================

session_start();

// 🛑 التأكد من أن ملف الاتصال موجود 🛑
require_once 'db_connect.php'; 

// التحقق من وجود الإيميل في الجلسة (لضمان أن المستخدم بدأ العملية من forgot_password.php)
if (!isset($_SESSION['reset_email'])) {
    // إذا لم يتم البدء بالعملية، أعد التوجيه
    header("Location: forgot_password.php");
    exit;
}

$email = $_SESSION['reset_email'];
$message = ''; // رسالة للعرض للمستخدم
$message_type = 'info'; // نوع الرسالة (success, danger, info)

// إذا كانت هناك رسالة نجاح من الصفحة السابقة (مثل "تم إرسال الرمز بنجاح")
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']); 
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['token_code'])) {
    
    global $conn; 
    $token_code = trim($_POST['token_code']);
    
    // 💡 نقطة التحقق من الصلاحية: يتم جلب الوقت الحالي
    $current_time = date('Y-m-d H:i:s');

    // 1. التحقق من الرمز في قاعدة البيانات وصلاحيته
    // لضمان صلاحية 30 ثانية، يجب أن تكون قيمة expires_at في قاعدة البيانات 
    // قد تم تعيينها لتكون (وقت الإرسال + 30 ثانية).
    // هذا الاستعلام يتحقق من أن الوقت الحالي ($current_time) لم يتجاوز وقت انتهاء الصلاحية.
    $sql = "SELECT token FROM password_resets WHERE email = ? AND token = ? AND expires_at > ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $email, $token_code, $current_time);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // 2. نجح التحقق: نحفظ الـ Token في الجلسة ونوجه لصفحة إعادة التعيين
        $_SESSION['verified_token'] = $token_code; 
        
        // يمكننا حذف الرمز من قاعدة البيانات فوراً لضمان عدم استخدامه مرتين
        $delete_sql = "DELETE FROM password_resets WHERE email = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("s", $email);
        $delete_stmt->execute();
        $delete_stmt->close();

        $_SESSION['reset_email'] = $email; // نؤكد وجود الإيميل قبل التوجيه
        header("Location: reset_password.php"); // 💡 التوجيه لصفحة كلمة السر الجديدة 💡
        exit;
    } else {
        $message = "خطأ: رمز التحقق غير صحيح أو انتهت صلاحيته (الصلاحية 30 ثانية).";
        $message_type = 'danger';
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>التحقق من رمز إعادة التعيين</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { 
            background-color: #e9ecef; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
        }
        .verify-container { 
            width: 95%; 
            max-width: 450px; 
            padding: 30px; 
            margin: auto; 
            background-color: #ffffff; 
            border-radius: 10px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            text-align: center;
        }
        #token_code {
            font-size: 1.5rem;
            letter-spacing: 5px; 
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .btn-primary { background-color: #007bff; border-color: #007bff; }
        .btn-primary:hover { background-color: #0056b3; border-color: #0056b3; }
    </style>
</head>

<body>
    <div class="verify-container">

        <h3 class="text-center text-primary mb-4">🔐 التحقق من رمز إعادة التعيين</h3>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> text-center font-weight-bold">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <p class="text-muted small">
            لقد تم إرسال رمز تحقق مكون من 4 أرقام إلى بريدك الإلكتروني: 
            <strong class="text-primary"><?php echo htmlspecialchars($email); ?></strong>. 
            يرجى إدخال الرمز للمتابعة:
        </p>

        <form action="verify_token.php" method="POST">
            <div class="form-group row justify-content-center">
                <div class="col-sm-8 col-10"> 
                    <input type="text" class="form-control text-center form-control-lg" id="token_code" name="token_code" required maxlength="4" placeholder="الرمز السري">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block mt-3">تحقق وتابع</button>
        </form>
        
        <hr class="mt-4">
        <p class="text-center">
            <a href="forgot_password.php" class="text-secondary small">هل لم يصلك الرمز؟ أعد الإرسال</a>
        </p>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>