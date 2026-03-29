<?php
// currencies.php - إدارة العملات (نسخة متوافقة تماماً مع قاعدة بياناتك)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once 'db_connect.php';
include_once 'functions.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$currencies = [];

// 1. تعيين العملة الأساسية
if (isset($_GET['set_base_id']) && is_numeric($_GET['set_base_id'])) {
    $base_currency_id = intval($_GET['set_base_id']);
    $conn->begin_transaction();
    try {
        // تحديث الحقلين is_base و is_base_currency لضمان التوافق مع جدولك
        $conn->query("UPDATE currencies SET is_base_currency = 0, is_base = 0");
        $stmt = $conn->prepare("UPDATE currencies SET is_base_currency = 1, is_base = 1 WHERE id = ?");
        $stmt->bind_param("i", $base_currency_id);
        $stmt->execute();
        $conn->commit();
        $message = '<div class="alert alert-success">✅ تم تعيين العملة الأساسية بنجاح.</div>';
    } catch (Exception $e) {
        $conn->rollback();
        $message = '<div class="alert alert-danger">❌ فشل التحديث: ' . $e->getMessage() . '</div>';
    }
}

// 2. إضافة عملة جديدة
if (isset($_POST['add_currency'])) {
    $code = $conn->real_escape_string(trim(strtoupper($_POST['currency_code']))); 
    $name_ar = $conn->real_escape_string($_POST['currency_name_ar']); 
    
    if (!empty($code) && !empty($name_ar)) {
        $conn->begin_transaction(); 
        try {
            // ملاحظة: تم تغيير currency_name_ar إلى currency_name ليطابق صورتك
            $stmt_curr = $conn->prepare("INSERT INTO currencies (currency_code, currency_name, currency_name_ar, is_base_currency, is_base, is_active) VALUES (?, ?, ?, 0, 0, 1)");
            $stmt_curr->bind_param("sss", $code, $name_ar, $name_ar);
            $stmt_curr->execute();

            $stmt_rate = $conn->prepare("INSERT IGNORE INTO exchange_rates (currency_code, currency_name_ar, buy_rate, sell_rate, commission_percentage, last_updated) VALUES (?, ?, 1.0, 1.0, 0.0, NOW())");
            $stmt_rate->bind_param("ss", $code, $name_ar);
            $stmt_rate->execute();

            $conn->commit();
            $message = '<div class="alert alert-success">✅ تم إضافة العملة بنجاح وظهورها في القائمة.</div>';
        } catch (Exception $e) {
            $conn->rollback();
            $message = '<div class="alert alert-danger">❌ فشل الإضافة: ' . $e->getMessage() . '</div>';
        }
    }
}

// 3. حذف عملة
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $res = $conn->query("SELECT is_base_currency, currency_code FROM currencies WHERE id = $delete_id");
    $row = ($res) ? $res->fetch_assoc() : null;

    if ($row && $row['is_base_currency'] == 1) { 
        $message = '<div class="alert alert-danger">❌ لا يمكن حذف العملة الأساسية.</div>';
    } elseif ($row) {
        $conn->begin_transaction();
        try {
            $del_code = $row['currency_code'];
            $conn->query("DELETE FROM exchange_rates WHERE currency_code = '$del_code'");
            $conn->query("DELETE FROM currencies WHERE id = $delete_id");
            $conn->commit();
            $message = '<div class="alert alert-success">✅ تم الحذف بنجاح.</div>';
        } catch (Exception $e) {
            $conn->rollback();
            $message = '<div class="alert alert-danger">❌ فشل الحذف.</div>';
        }
    }
}

// 4. جلب البيانات للعرض (تم تعديل الاستعلام ليطابق صورتك)
$result = $conn->query("SELECT id, currency_code, currency_name, is_base_currency FROM currencies ORDER BY is_base_currency DESC, id DESC");
if ($result) { 
    while ($row = $result->fetch_assoc()) { 
        $currencies[] = $row; 
    } 
}

$pageTitle = 'إدارة العملات';
include 'header.php'; 
?>

<div class="container-fluid py-4" dir="rtl">
    <h2 class="text-dark mb-4"><i class="fas fa-coins text-warning ml-2"></i> إعدادات العملات</h2>
    <?php echo $message; ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">إضافة عملة جديدة</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="add_currency" value="1">
                        <div class="form-group mb-3">
                            <label>رمز العملة (مثلاً USD):</label>
                            <input type="text" class="form-control" name="currency_code" required maxlength="3" placeholder="3 حروف فقط">
                        </div>
                        <div class="form-group mb-4">
                            <label>الاسم بالعربية:</label>
                            <input type="text" class="form-control" name="currency_name_ar" required placeholder="مثلاً: دولار">
                        </div>
                        <button type="submit" class="btn btn-success btn-block">حفظ</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white">العملات المفعلة</div>
                <div class="table-responsive">
                    <table class="table text-center mb-0">
                        <thead>
                            <tr>
                                <th>الرمز</th>
                                <th>الاسم</th>
                                <th>النوع</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($currencies)): ?>
                                <tr><td colspan="4" class="text-muted py-3">لا توجد عملات مضافة بعد.</td></tr>
                            <?php else: ?>
                                <?php foreach ($currencies as $currency): ?>
                                    <tr class="<?php echo ($currency['is_base_currency'] == 1) ? 'table-info' : ''; ?>">
                                        <td><strong><?php echo htmlspecialchars($currency['currency_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($currency['currency_name']); ?></td> 
                                        <td><?php echo ($currency['is_base_currency'] == 1) ? '<span class="badge badge-primary">أساسية</span>' : '<span class="badge badge-secondary">فرعية</span>'; ?></td>
                                        <td>
                                            <?php if ($currency['is_base_currency'] == 0): ?>
                                                <a href="currencies.php?set_base_id=<?php echo $currency['id']; ?>" class="btn btn-sm btn-outline-info">تعيين كأساسي</a>
                                                <a href="currencies.php?delete_id=<?php echo $currency['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('تأكيد الحذف؟');">حذف</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>