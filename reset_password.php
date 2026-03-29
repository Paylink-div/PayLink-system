<?php


session_start();


// 🛑 التأكد من أن هذا الملف موجود في نفس المجلد 🛑
include 'db_connect.php'; 


$message = '';

$message_type = '';


$show_reset_form = false; 



// 1. التحقق من وجود بيانات التحقق في الجلسة
if (isset($_SESSION['reset_email']) && isset($_SESSION['verified_token'])) {
    // تم التحقق من البريد والرمز بنجاح في صفحة verify_token.php
    $user_email = $_SESSION['reset_email'];
    $show_reset_form = true; // نعرض نموذج إدخال كلمة السر
} else {
    // إذا لم يتم التحقق، نعرض رسالة خطأ ونوجه لصفحة طلب إعادة التعيين
    $message = "يجب البدء بعملية إعادة تعيين كلمة السر أولاً.";
    $message_type = 'danger';
    // التوجيه التلقائي بعد رسالة الخطأ
    // header("Location: forgot_password.php");
    // exit;
}



// 2. معالجة إدخال كلمة السر الجديدة (فقط إذا كانت البيانات صالحة)
if ($show_reset_form && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_password'])) {


    $new_password = $_POST['new_password'];

    $confirm_password = $_POST['confirm_password'];
    
    
    if ($new_password !== $confirm_password) {
        $message = "كلمتا السر غير متطابقتين.";
        $message_type = 'danger';
    } elseif (strlen($new_password) < 6) {
        $message = "يجب أن تكون كلمة السر 6 أحرف على الأقل.";
        $message_type = 'danger';
    } else {
        // 3. تحديث كلمة السر
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // 💡 السطر المُصحح (يستخدم password_hash كاسم عمود) 💡
        $update_sql = "UPDATE users SET password_hash = ? WHERE email = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ss", $hashed_password, $user_email);


        if ($update_stmt->execute()) {
            
            // 4. حذف الرمز من جدول password_resets (لتنظيف القاعدة)
            $delete_sql = "DELETE FROM password_resets WHERE email = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("s", $user_email);
            $delete_stmt->execute();
            $delete_stmt->close();


            // 5. مسح بيانات الجلسة الأمنية
            unset($_SESSION['reset_email']);
            unset($_SESSION['verified_token']);
            
            // 6. عرض رسالة النجاح وإعادة التوجيه لصفحة تسجيل الدخول
            $_SESSION['success_message'] = "تمت إعادة تعيين كلمة السر بنجاح. يمكنك الآن تسجيل الدخول.";
            header("Location: login.php");
            exit;


        } else {
            $message = "خطأ في تحديث كلمة السر: " . $conn->error;
            $message_type = 'danger';
        }
        $update_stmt->close();
    }
}


?>



<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>إعادة تعيين كلمة السر</title>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <style>

        body { 
            background-color: #f8f9fa; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
        }

        .reset-container { 
            width: 90%; /* أصبح أوسع قليلاً على الموبايل */
            max-width: 400px; 
            padding: 20px; /* زيادة الهامش الداخلي */
            margin: auto; 
            background-color: white; 
            border-radius: 8px; 
            box-shadow: 0 0 15px rgba(0,0,0,0.1); 
        }

        .reset-container a {
            color: #007bff;
        }

    </style>

</head>

<body>

    <div class="reset-container">

        <h3 class="text-center text-primary mb-4">🆕 تعيين كلمة السر الجديدة</h3>

        
        <?php if ($message): ?>

            <div class="alert alert-<?php echo $message_type; ?> text-center"><?php echo $message; ?></div>

        <?php endif; ?>


        <?php if ($show_reset_form): ?>

            <form method="POST" action="reset_password.php">

                <p class="text-center small">أدخل كلمة السر الجديدة لحساب: <br><strong><?php echo htmlspecialchars($user_email); ?></strong></p>

                <div class="form-group">

                    <label for="new_password">كلمة السر الجديدة:</label>

                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">

                </div>

                <div class="form-group">

                    <label for="confirm_password">تأكيد كلمة السر الجديدة:</label>

                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">

                </div>

                <button type="submit" class="btn btn-primary btn-block mt-4">تغيير كلمة السر</button>

            </form>

        <?php endif; ?>

        

        <hr class="mt-3">

        <p class="text-center small"><a href="login.php" class="text-secondary">العودة لتسجيل الدخول</a></p>

    </div>

</body>

</html>