<?php
// add_company_account.php - إضافة حساب بنكي جديد - نسخة معدلة (إزالة قيود الشركات)

if (session_status() == PHP_SESSION_NONE) session_start();

require_once 'db_connect.php';

// 1. التحقق من تسجيل الدخول فقط (تم إزالة التحقق من company_id)
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$pageTitle = 'إضافة حساب بنكي جديد';

$message = '';
$error = '';

// جلب العملات والفروع المتاحة في النظام بالكامل
$currencies = [];
$branches = [];

// استعلام العملات بدون شرط company_id
$currencies_result = $conn->query("SELECT id, currency_code FROM currencies WHERE is_active = 1 ORDER BY currency_code");
if ($currencies_result) {
    while ($row = $currencies_result->fetch_assoc()) { $currencies[] = $row; }
}

// استعلام الفروع بدون شرط company_id
$branches_result = $conn->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name");
if ($branches_result) {
    while ($row = $branches_result->fetch_assoc()) { $branches[] = $row; }
}

// 2. معالجة نموذج الإضافة
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $current_balance = (float)($_POST['current_balance'] ?? 0.00);
    $currency_id = (int)($_POST['currency_id'] ?? 0);
    $branch_id = (int)($_POST['branch_id'] ?? 0); 
    $branch_id_param = ($branch_id > 0) ? $branch_id : null;

    if (empty($bank_name) || empty($account_number) || $currency_id == 0) {
        $error = "الرجاء ملء جميع الحقول المطلوبة (المصرف، رقم الحساب، والعملة).";
    } else {
        // تم حذف حقل company_id من جملة INSERT
        $sql = "INSERT INTO company_bank_accounts 
                (bank_name, account_number, current_balance, currency_id, branch_id, is_active, last_updated) 
                VALUES (?, ?, ?, ?, ?, 1, NOW())"; 
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssdii", $bank_name, $account_number, $current_balance, $currency_id, $branch_id_param);
            if ($stmt->execute()) {
                header("Location: company_treasury.php?msg=" . urlencode("تم إضافة الحساب بنجاح!"));
                exit;
            } else {
                $error = "خطأ في الإضافة: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

include 'header.php'; 
?>

<style>
    :root { --primary: #4e73df; --bg-gray: #f8f9fc; }
    body { background-color: var(--bg-gray); }
    .form-card { border-radius: 20px; border: none; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); }
    .input-group-text { background-color: #f1f3f9; border: none; color: var(--primary); }
    .form-control { border-radius: 10px; border: 1px solid #e3e6f0; padding: 12px; height: auto; }
    .form-control:focus { box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.1); }
    label { font-size: 0.9rem; margin-bottom: 8px; color: #4e4e4e; }
    .btn-submit { border-radius: 12px; padding: 15px; font-weight: bold; font-size: 1.1rem; transition: 0.3s; }
    .btn-submit:active { transform: scale(0.98); }
    
    @media (max-width: 768px) {
        .container-fluid { padding-left: 10px; padding-right: 10px; }
        .btn-submit { width: 100%; margin-bottom: 10px; }
        .btn-reset { width: 100%; }
    }
</style>

<div class="container-fluid py-3" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 font-weight-bold text-dark mb-0">إضافة حساب بنكي</h1>
        <a href="company_treasury.php" class="btn btn-sm btn-outline-secondary px-3 shadow-sm border-0"><i class="fas fa-chevron-right ml-1"></i> رجوع</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4"><i class="fas fa-exclamation-circle ml-2"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card form-card mb-5">
        <div class="card-body p-4">
            <form method="POST">
                <div class="row text-right">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">اسم المصرف / البنك <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-university"></i></span>
                            </div>
                            <input type="text" class="form-control" name="bank_name" required placeholder="مثال: مصرف الأمان" value="<?php echo htmlspecialchars($_POST['bank_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">رقم الحساب <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-fingerprint"></i></span>
                            </div>
                            <input type="text" class="form-control" name="account_number" required placeholder="000-000-000" value="<?php echo htmlspecialchars($_POST['account_number'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">العملة <span class="text-danger">*</span></label>
                        <select class="form-control" name="currency_id" required>
                            <option value="">-- اختر العملة --</option>
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?php echo $currency['id']; ?>" <?php echo (isset($_POST['currency_id']) && $_POST['currency_id'] == $currency['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($currency['currency_code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">الرصيد الافتتاحي</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-coins"></i></span>
                            </div>
                            <input type="number" step="0.01" class="form-control" name="current_balance" value="<?php echo htmlspecialchars($_POST['current_balance'] ?? '0.00'); ?>">
                        </div>
                    </div>

                    <div class="col-12 form-group">
                        <label class="font-weight-bold">الفرع (اختياري)</label>
                        <select class="form-control" name="branch_id">
                            <option value="0">حساب عام (غير تابع لفرع)</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['id']; ?>" <?php echo (isset($_POST['branch_id']) && $_POST['branch_id'] == $branch['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($branch['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row flex-column-reverse flex-md-row">
                    <div class="col-md-6">
                        <button type="reset" class="btn btn-light btn-block btn-submit text-muted btn-reset">مسح الحقول</button>
                    </div>
                    <div class="col-md-6 mb-3 mb-md-0">
                        <button type="submit" class="btn btn-primary btn-block btn-submit shadow">
                            <i class="fas fa-check-circle ml-1"></i> حفظ الحساب البنكي
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
include 'footer.php'; 
if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
?>