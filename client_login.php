<?php
// client_login.php
session_start();
include 'db_connect.php'; 

if (isset($_SESSION['client_id'])) { header("Location: client_portal.php"); exit; }

$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_input = trim($_POST['login_input'] ?? ''); 
    if (!empty($login_input)) {
        $stmt = $conn->prepare("SELECT id, full_name FROM clients WHERE phone_number = ? OR id_number = ? LIMIT 1");
        $stmt->bind_param("ss", $login_input, $login_input);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $_SESSION['client_id'] = $row['id'];
            $_SESSION['client_name'] = $row['full_name'];
            header("Location: client_portal.php");
            exit;
        } else {
            $message = "بيانات الدخول غير صحيحة.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"><title>دخول العميل</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height: 100vh;">
<div class="container">
    <div class="card mx-auto shadow-sm" style="max-width: 400px; border-radius: 15px;">
        <div class="card-body p-4 text-center">
            <h3>بوابة العملاء</h3>
            <?php if ($message): ?><div class="alert alert-danger small"><?php echo $message; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group text-right">
                    <label>رقم الهاتف أو الوطني</label>
                    <input type="text" class="form-control" name="login_input" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">دخول</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>