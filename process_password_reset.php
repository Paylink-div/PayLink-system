<?php
session_start();

// =========================================================================================
// process_password_reset.php - توليد رمز OTP وإعادة توجيه المستخدم لصفحة التحقق
// =========================================================================================

// 1. تضمين المكتبات والاتصال (تم تصحيح المسار لـ db_connect.php)
require_once 'PHPMailer/Exception.php'; 
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';
require_once 'db_connect.php';      // 🛑 تم تصحيح اسم ملف اتصال قاعدة البيانات 🛑


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;


// =========================================================================================
// 💡 دالة إرسال الإيميل (تأكد من تحديث الإعدادات هنا) 💡
// =========================================================================================
function send_email_function($to, $subject, $body) {
    
    // 🛑 يجب تعديل هذه الإعدادات بمعلومات بريدك الإلكتروني الحقيقية وكلمة سر التطبيق 🛑
    $smtp_settings = [
        'Host'       => 'smtp.gmail.com',
        'SMTPAuth'   => true,
        'Username'   => 'zagdon01@gmail.com',    // 💡 بريدك الإلكتروني الكامل 💡
        'Password'   => 'uijy ylvl avpz sdbu',       // 🔑 كلمة سر التطبيق (App Password) 🔑
        'SMTPSecure' => 'ssl',                     
        'Port'       => 465,                     
        'setFrom'    => ['your_email@gmail.com', 'PayLink Support'] 
    ];
    
    $mail = new PHPMailer(true);

    try {
        // 1. إعدادات الخادم
        $mail->isSMTP();
        $mail->Host       = $smtp_settings['Host'];
        $mail->SMTPAuth   = $smtp_settings['SMTPAuth'];
        $mail->Username   = $smtp_settings['Username'];
        $mail->Password   = $smtp_settings['Password'];
        $mail->SMTPSecure = $smtp_settings['SMTPSecure'];
        $mail->Port       = $smtp_settings['Port'];
        $mail->SMTPDebug  = 0; // استخدم 2 لتتبع المشكلة إن لم يعمل الإرسال
        $mail->CharSet    = 'UTF-8';
        
        // 2. إعدادات المرسل والمستلم
        $mail->setFrom($smtp_settings['setFrom'][0], $smtp_settings['setFrom'][1]);
        $mail->addAddress($to);

        // 3. المحتوى
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}


// =========================================================================================
// 2. منطق التحقق، توليد الرمز، والإرسال
// =========================================================================================

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
    
    global $conn; // استخدام الاتصال الذي تم تضمينه من db_connect.php
    $email = trim($_POST['email']);
    
    // 1. التحقق من وجود المستخدم
    $user_sql = "SELECT id, full_name FROM users WHERE email = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("s", $email);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();

    if ($user_result->num_rows === 0) {
        $_SESSION['error_message'] = "لم يتم العثور على بريد إلكتروني مسجل.";
        header("Location: forgot_password.php");
        exit;
    }
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();

    // 2. توليد رمز تحقق قصير (4 أرقام)
    $token_code = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT); 
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes')); // ينتهي بعد 10 دقائق

    // 3. تخزين الرمز (Code) في جدول password_resets
    // نستخدم REPLACE INTO لتحديث الرمز إذا كان موجوداً بالفعل
    $insert_sql = "REPLACE INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sss", $email, $token_code, $expires_at);

    if (!$insert_stmt->execute()) {
        $_SESSION['error_message'] = "خطأ في قاعدة البيانات أثناء توليد الرمز.";
        header("Location: forgot_password.php");
        exit;
    }
    $insert_stmt->close();
    
    // 4. إرسال الإيميل
    $subject = "رمز التحقق لحسابك في منظومة PayLink";
    $body = "مرحباً " . $user_data['full_name'] . "،\n\n";
    $body .= "رمز التحقق الخاص بك هو: " . $token_code . "\n\n"; // 💡 إرسال الرمز 💡
    $body .= "يرجى إدخال هذا الرمز لإكمال عملية إعادة تعيين كلمة السر. الرمز صالح لمدة 30 ثانيه دقائق.\n\nفريق دعم PayLink.";
    
    if (send_email_function($email, $subject, $body)) {
        // 5. حفظ البريد في الجلسة وإعادة التوجيه لصفحة التحقق
        $_SESSION['reset_email'] = $email; // نحفظ الإيميل
        $_SESSION['success_message'] = "تم إرسال رمز التحقق إلى بريدك الإلكتروني بنجاح.";
        header("Location: verify_token.php"); // 💡 إعادة توجيه لصفحة التحقق الجديدة 💡
        exit;
    } else {
        // نبلغ المستخدم بفشل الإرسال بسبب إعدادات SMTP
        $_SESSION['error_message'] = "خطأ: فشل في إرسال البريد الإلكتروني. يرجى مراجعة إعدادات SMTP الخاصة بك.";
        header("Location: forgot_password.php");
        exit;
    }
} else {
    // إذا تم الوصول للصفحة بدون إرسال بريد
     header("Location: forgot_password.php");
     exit;
}
?>