<?php
// edit_bank_account.php - تعديل بيانات الحساب البنكي (نسخة معدلة بدون قيود الشركة)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

// 1. التحقق من تسجيل الدخول فقط (تم إزالة شرط company_id)
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';

// 2. التحقق من وجود معرف الحساب المطلوب تعديله
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: company_treasury.php");
    exit;
}

$account_id = intval($_GET['id']);

// 3. جلب بيانات الحساب (تم إزالة شرط company_id للسماح بالوصول العام)
$stmt = $conn->prepare("SELECT * FROM company_bank_accounts WHERE id = ?");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result();
$account = $result->fetch_assoc();

if (!$account) {
    die("خطأ: الحساب غير موجود.");
}

// 4. جلب العملات والفروع المتاحة (تم إزالة شرط company_id)
$currencies = $conn->query("SELECT id, currency_code, currency_name FROM currencies");
$branches = $conn->query("SELECT id, name FROM branches");

// 5. معالجة طلب التحديث
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_account'])) {
    $bank_name = $_POST['bank_name'];
    $account_number = $_POST['account_number'];
    $currency_id = $_POST['currency_id'];
    $branch_id = !empty($_POST['branch_id']) ? $_POST['branch_id'] : NULL;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // تحديث البيانات (تم إزالة company_id من شرط WHERE)
    $update_sql = "UPDATE company_bank_accounts 
                   SET bank_name = ?, account_number = ?, currency_id = ?, branch_id = ?, is_active = ?, last_updated = CURRENT_TIMESTAMP 
                   WHERE id = ?";
    
    $stmt_upd = $conn->prepare($update_sql);
    $stmt_upd->bind_param("ssiiii", $bank_name, $account_number, $currency_id, $branch_id, $is_active, $account_id);

    if ($stmt_upd->execute()) {
        header("Location: company_treasury.php?msg=" . urlencode("تم تحديث بيانات الحساب بنجاح."));
        exit;
    } else {
        $error = "حدث خطأ أثناء التحديث: " . $conn->error;
    }
}

$pageTitle = 'تعديل حساب بنكي';
include 'header.php';
?>

<div class="container mt-5" dir="rtl">
    <div class="row justify-content-center">
        <div class="col-md-8 text-right">
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header bg-primary text-white text-center">
                    <h3 class="font-weight-light my-2"><i class="fas fa-edit"></i> تعديل بيانات الحساب البنكي</h3>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="small mb-1 font-weight-bold">اسم المصرف</label>
                                <input class="form-control text-right" name="bank_name" type="text" value="<?php echo htmlspecialchars($account['bank_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="small mb-1 font-weight-bold">رقم الحساب</label>
                                <input class="form-control text-right" name="account_number" type="text" value="<?php echo htmlspecialchars($account['account_number']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="small mb-1 font-weight-bold">العملة</label>
                                <select class="form-control text-right" name="currency_id" required>
                                    <?php while($curr = $currencies->fetch_assoc()): ?>
                                        <option value="<?php echo $curr['id']; ?>" <?php echo ($curr['id'] == $account['currency_id']) ? 'selected' : ''; ?>>
                                            <?php echo $curr['currency_code'] . " - " . $curr['currency_name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="small mb-1 font-weight-bold">الفرع المسؤول</label>
                                <select class="form-control text-right" name="branch_id">
                                    <option value="">-- غير محدد (عام) --</option>
                                    <?php while($br = $branches->fetch_assoc()): ?>
                                        <option value="<?php echo $br['id']; ?>" <?php echo ($br['id'] == $account['branch_id']) ? 'selected' : ''; ?>>
                                            <?php echo $br['name']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="custom-control custom-checkbox mb-4">
                            <input class="custom-control-input" id="isActive" name="is_active" type="checkbox" <?php echo ($account['is_active'] == 1) ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="isActive">الحساب نشط (يظهر في التقارير)</label>
                        </div>

                        <div class="form-group d-flex align-items-center justify-content-between mt-4 mb-0">
                            <a class="btn btn-outline-secondary" href="company_treasury.php">إلغاء والعودة</a>
                            <button class="btn btn-primary px-4" type="submit" name="update_account">حفظ التعديلات</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center small text-muted">
                    آخر تحديث لهذا الحساب: <?php echo $account['last_updated']; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
include 'footer.php'; 
if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
?>