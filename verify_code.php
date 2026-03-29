<?php
// verify_code.php - صفحة إدخال رمز التحقق وتعيين كلمة السر الجديدة

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'db_connect.php'; 

$message = '';
$message_class = '';

// التحقق من أن المستخدم قادم من صفحة forgot_password
if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_attempt'])) {
    header("Location: forgot_password.php");
    exit;
}

$user_id = $_SESSION['reset_user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $code = $_POST['code'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($code) || empty($new_password) || empty($confirm_password)) {
        $message = "يرجى ملء جميع الحقول.";
        $message_class = 'alert-danger';
    } elseif ($new_password !== $confirm_password) {
        $message = "كلمة السر الجديدة وتأكيدها غير متطابقين.";
        $message_class = 'alert-danger';
    } elseif (strlen($new_password) < 6) {
        $message = "يجب أن تكون كلمة السر 6 أحرف على الأقل.";
        $message_class = 'alert-danger';
    } else {
        
        // 1. التحقق من الرمز والوقت
        $current_time = time();
        $stmt = $conn->prepare("SELECT reset_code_expires_at FROM users WHERE id = ? AND reset_code = ? LIMIT 1");
        $stmt->bind_param("is", $user_id, $code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $expiry_time = (int)$user['reset_code_expires_at'];

            if ($expiry_time > $current_time) {
                
                // 2. تحديث كلمة السر
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_code = NULL, reset_code_expires_at = NULL WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                $update_stmt->execute();

                if ($update_stmt->affected_rows > 0) {
                    
                    // 3. مسح بيانات الجلسة وإعادة توجيه للـ Login
                    unset($_SESSION['reset_user_id']);
                    unset($_SESSION['reset_attempt']);
                    
                    header("Location: index.php?success_reset=1");
                    exit;
                    
                } else {
                    $message = "فشل في تحديث كلمة السر. يرجى المحاولة مرة أخرى.";
                    $message_class = 'alert-danger';
                }

            } else {
                $message = "رمز التحقق منتهي الصلاحية. يرجى العودة لصفحة الاستعادة وطلب رمز جديد.";
                $message_class = 'alert-danger';
            }
        
        } else {
            $message = "رمز التحقق غير صحيح.";
            $message_class = 'alert-danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدخال الرمز - PayLink</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .verify-container { background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); width: 100%; max-width: 450px; }
        .logo-text { color: #ff7f50; margin-bottom: 20px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="verify-container">
        <h2 class="text-center logo-text">PayLink</h2>
        <h4 class="text-center mb-4">تأكيد الرمز وكلمة السر الجديدة</h4>

        <?php if ($message): ?>
            <div class="alert <?php echo $message_class; ?> text-center" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php else: ?>
             <div class="alert alert-info text-center">
                <i class="fas fa-info-circle"></i> تم إرسال رمز التحقق إلى رقم هاتفك/واتساب.
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="code"><i class="fas fa-key"></i> رمز التحقق (6 أرقام)</label>
                <input type="text" class="form-control" id="code" name="code" maxlength="6" required>
            </div>
            
            <div class="form-group">
                <label for="new_password"><i class="fas fa-lock"></i> كلمة السر الجديدة</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-lock-open"></i> تأكيد كلمة السر الجديدة</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn btn-success btn-block mt-4">إعادة تعيين كلمة السر</button>
            <hr>
            <p class="text-center"><a href="forgot_password.php">إعادة طلب رمز جديد</a></p>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>