<?php
// bank_transfer.php - تنفيذ تحويل مالي بين حسابات الشركة البنكية مع دعم تسجيل العملة

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

// 1. التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];

// تعريف متغيرات الصفحة
$pageTitle = 'تنفيذ تحويل مالي داخلي';
$message = '';
$error = '';
$accounts = [];

// 2. جلب كافة حسابات النظام النشطة مع العملات
$stmt_acc = $conn->prepare("
    SELECT cba.id, cba.bank_name, cba.account_number, cba.current_balance, cy.currency_code 
    FROM company_bank_accounts cba
    JOIN currencies cy ON cba.currency_id = cy.id
    WHERE cba.is_active = 1
    ORDER BY cba.bank_name
");
$stmt_acc->execute();
$accounts_query = $stmt_acc->get_result();

if ($accounts_query) {
    while ($row = $accounts_query->fetch_assoc()) {
        $accounts[$row['id']] = $row;
    }
}
$stmt_acc->close();

// 3. معالجة نموذج التحويل
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $source_account_id = (int)($_POST['source_account_id'] ?? 0);
    $destination_account_id = (int)($_POST['destination_account_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? 'تحويل داخلي'); 
    $transaction_date = date("Y-m-d");

    if ($source_account_id === 0 || $destination_account_id === 0 || $amount <= 0) {
        $error = "الرجاء تحديد الحسابين وإدخال مبلغ صحيح.";
    } elseif ($source_account_id === $destination_account_id) {
        $error = "لا يمكن التحويل من وإلى نفس الحساب.";
    } elseif (!isset($accounts[$source_account_id]) || !isset($accounts[$destination_account_id])) {
        $error = "تنبيه أمني: أحد الحسابات المحددة غير موجود.";
    } else {
        $source_account = $accounts[$source_account_id];
        $dest_account = $accounts[$destination_account_id];
        $currency = $source_account['currency_code']; // العملة الموحدة للتحويل

        if ($source_account['currency_code'] !== $dest_account['currency_code']) {
            $error = "فشل: يجب أن تكون العملة متطابقة (" . $currency . ").";
        } elseif ($source_account['current_balance'] < $amount) {
            $error = "فشل: الرصيد غير كافٍ. المتاح: " . number_format($source_account['current_balance'], 2);
        } else {
            
            $conn->begin_transaction();
            try {
                // 1. خصم من المصدر
                $stmt1 = $conn->prepare("UPDATE company_bank_accounts SET current_balance = current_balance - ? WHERE id = ?");
                $stmt1->bind_param("di", $amount, $source_account_id);
                $stmt1->execute();

                // 2. إضافة للوجهة
                $stmt2 = $conn->prepare("UPDATE company_bank_accounts SET current_balance = current_balance + ? WHERE id = ?");
                $stmt2->bind_param("di", $amount, $destination_account_id);
                $stmt2->execute();

                // 3. سجل حركة الخصم (أضفنا حقل currency)
                $stmt3 = $conn->prepare("INSERT INTO bank_movements (account_id, movement_type, amount, currency, transaction_date, description, created_by_user_id) VALUES (?, 'internal_transfer_out', ?, ?, ?, ?, ?)");
                $desc_out = "تحويل إلى: " . $dest_account['bank_name'] . " | " . $description;
                $stmt3->bind_param("idssssi", $source_account_id, $amount, $currency, $transaction_date, $desc_out, $current_user_id);
                $stmt3->execute();

                // 4. سجل حركة الإضافة (أضفنا حقل currency)
                $stmt4 = $conn->prepare("INSERT INTO bank_movements (account_id, movement_type, amount, currency, transaction_date, description, created_by_user_id) VALUES (?, 'internal_transfer_in', ?, ?, ?, ?, ?)");
                $desc_in = "تحويل من: " . $source_account['bank_name'] . " | " . $description;
                $stmt4->bind_param("idssssi", $destination_account_id, $amount, $currency, $transaction_date, $desc_in, $current_user_id);
                $stmt4->execute();

                $conn->commit();
                $success_msg = "✅ تم التحويل بنجاح بقيمة " . number_format($amount, 2) . " " . $currency;
                header("Location: bank_transfer.php?success=" . urlencode($success_msg));
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                $error = "حدث خطأ أثناء العملية: " . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['success'])) { $message = htmlspecialchars($_GET['success']); }

include 'header.php'; 
?>

<div class="container-fluid" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4 text-right">
        <div>
            <h1 class="text-primary mb-0"><i class="fas fa-exchange-alt"></i> تحويل مالي داخلي</h1>
            <p class="lead mb-0">نقل الأموال بين الحسابات البنكية (نفس العملة)</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary shadow-sm">
            <i class="fas fa-arrow-right ml-1"></i> العودة للرئيسية
        </a>
    </div>
    <hr>
    
    <?php if ($message): ?>
        <div class="alert alert-success shadow-sm text-right"><i class="fas fa-check-circle ml-2"></i> <?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm text-right"><i class="fas fa-exclamation-triangle ml-2"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (count($accounts) < 2): ?>
        <div class="alert alert-warning text-right">
            <i class="fas fa-info-circle ml-2"></i> لا يمكن إجراء تحويل داخلي، تحتاج لحسابين نشطين على الأقل.
        </div>
    <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow border-0">
                    <div class="card-header bg-dark text-white font-weight-bold text-right">إنشاء أمر تحويل جديد</div>
                    <div class="card-body text-right">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label class="font-weight-bold text-danger">من الحساب (المصدر):</label>
                                    <select class="form-control border-danger shadow-sm" id="source_account_id" name="source_account_id" required>
                                        <option value="">--- اختر حساب الخصم ---</option>
                                        <?php foreach ($accounts as $id => $acc): ?>
                                            <option value="<?php echo $id; ?>" data-currency="<?php echo $acc['currency_code']; ?>">
                                                <?php echo htmlspecialchars($acc['bank_name']) . " [" . $acc['currency_code'] . "] - رصيد: " . number_format($acc['current_balance'], 2); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label class="font-weight-bold text-success">إلى الحساب (الوجهة):</label>
                                    <select class="form-control border-success shadow-sm" id="destination_account_id" name="destination_account_id" required>
                                        <option value="">--- اختر حساب الإيداع ---</option>
                                        <?php foreach ($accounts as $id => $acc): ?>
                                            <option value="<?php echo $id; ?>" data-currency="<?php echo $acc['currency_code']; ?>">
                                                <?php echo htmlspecialchars($acc['bank_name']) . " [" . $acc['currency_code'] . "]"; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group mt-3">
                                <label class="font-weight-bold">المبلغ المراد تحويله:</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0.01" class="form-control form-control-lg shadow-sm" name="amount" placeholder="0.00" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text bg-primary text-white"><i class="fas fa-money-bill-wave"></i></span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>وصف/ملاحظات العملية:</label>
                                <textarea class="form-control shadow-sm" name="description" rows="2" placeholder="اكتب سب التحويل أو أي ملاحظة..."></textarea>
                            </div>

                            <div class="alert alert-info py-2 border-0 shadow-sm">
                                <small><i class="fas fa-info-circle ml-1"></i> ملاحظة: يجب أن تتطابق عملة الحسابين لإتمام التحويل.</small>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block btn-lg shadow mt-4">
                                <i class="fas fa-check-circle ml-1"></i> تنفيذ التحويل الآن
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// التحقق من العملات قبل الإرسال
document.querySelector('form').addEventListener('submit', function(e) {
    const src = document.getElementById('source_account_id');
    const dst = document.getElementById('destination_account_id');
    
    const srcCurrency = src.options[src.selectedIndex].getAttribute('data-currency');
    const dstCurrency = dst.options[dst.selectedIndex].getAttribute('data-currency');

    if (src.value === dst.value) {
        alert('❌ لا يمكن التحويل لنفس الحساب!');
        e.preventDefault();
    } else if (srcCurrency !== dstCurrency) {
        alert('❌ خطأ: العملات غير متطابقة! عملة المصدر (' + srcCurrency + ') تختلف عن عملة الوجهة (' + dstCurrency + ').');
        e.preventDefault();
    }
});
</script>

<?php 
include 'footer.php'; 
if (isset($conn)) { $conn->close(); }
?>