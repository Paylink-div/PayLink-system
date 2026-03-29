<?php
// record_bank_movement.php - نسخة نهائية معدلة لتتوافق مع بنية جدول bank_movements

if (session_status() == PHP_SESSION_NONE) session_start();

require_once 'db_connect.php'; 

// 1. التحقق من الهوية
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); 
    exit;
}

$current_user_id = $_SESSION['user_id'];
$pageTitle = 'تسجيل حركة مالية';
$success_message = '';
$error_message = '';
$active_accounts = [];

// =================================================================
// 1. جلب كافة الحسابات البنكية المتاحة مع جلب كود العملة
// =================================================================
$accounts_query = "
    SELECT cba.id, cba.bank_name, cba.account_number, cba.current_balance, cur.currency_code 
    FROM company_bank_accounts cba
    LEFT JOIN currencies cur ON cba.currency_id = cur.id
    WHERE cba.is_active = 1
";
$stmt_acc = $conn->prepare($accounts_query);
$stmt_acc->execute();
$res_acc = $stmt_acc->get_result();
while ($row = $res_acc->fetch_assoc()) { $active_accounts[] = $row; }

// =================================================================
// 2. معالجة الطلب (POST)
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_id = (int)$_POST['account_id'];
    $movement_type = $_POST['movement_type'];
    $amount = (float)$_POST['amount'];
    $transaction_date = $_POST['transaction_date'];
    $description = trim($_POST['description'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? '');
    $beneficiary = trim($_POST['beneficiary_details'] ?? '');
    $to_account_id = isset($_POST['to_account_id']) ? (int)$_POST['to_account_id'] : 0;

    // جلب بيانات الحساب المختار (الرصيد والعملة)
    $source_account = null;
    foreach($active_accounts as $acc) { 
        if($acc['id'] == $account_id) $source_account = $acc; 
    }
    
    $check_balance = $source_account['current_balance'] ?? 0;
    $currency_code = $source_account['currency_code'] ?? 'LYD'; // القيمة الافتراضية إذا لم توجد عملة

    if ($amount <= 0) {
        $error_message = "يرجى إدخال مبلغ صحيح أكبر من صفر.";
    } elseif (($movement_type != 'deposit') && ($amount > $check_balance)) {
        $error_message = "عذراً، الرصيد الحالي لا يسمح بإجراء هذه العملية (الرصيد الحالي: " . number_format($check_balance, 2) . ")";
    } elseif ($movement_type === 'internal_transfer' && $account_id === $to_account_id) {
        $error_message = "لا يمكن التحويل لنفس الحساب.";
    } else {
        $conn->begin_transaction();
        try {
            // أ- استعلام الإدخال الأساسي (يشمل جميع أعمدة الجدول التي تطلب قيمة)
            $ins_sql = "INSERT INTO bank_movements (
                            account_id, client_id, movement_type, amount, currency, 
                            transaction_date, description, reference_number, 
                            beneficiary_details, created_by_user_id, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt_ins = $conn->prepare($ins_sql);
            
            // تحديد النوع الفعلي للحركة
            $actual_type = ($movement_type === 'internal_transfer') ? 'transfer_out' : $movement_type;
            $client_id = 0; // قيمة افتراضية للعميل في الحركات العامة

            // ربط المعاملات (iisdsssssi)
            $stmt_ins->bind_param("iisdsssssi", 
                $account_id, 
                $client_id, 
                $actual_type, 
                $amount, 
                $currency_code, 
                $transaction_date, 
                $description, 
                $reference_number, 
                $beneficiary, 
                $current_user_id
            );
            $stmt_ins->execute();

            // ب- تحديث رصيد الحساب الأساسي
            $balance_adj = ($movement_type === 'deposit') ? $amount : -$amount;
            $upd_sql = "UPDATE company_bank_accounts SET current_balance = current_balance + ? WHERE id = ?";
            $stmt_upd = $conn->prepare($upd_sql);
            $stmt_upd->bind_param("di", $balance_adj, $account_id);
            $stmt_upd->execute();

            // ج- معالجة التحويل الداخلي (الطرف الثاني - الحساب المستلم)
            if ($movement_type === 'internal_transfer' && $to_account_id > 0) {
                // جلب عملة الحساب المستلم
                $target_currency = 'LYD';
                foreach($active_accounts as $acc) { if($acc['id'] == $to_account_id) $target_currency = $acc['currency_code']; }

                $desc_to = "تحويل وارد من حساب: " . ($source_account['bank_name'] ?? $account_id) . " | " . $description;
                $in_type = 'transfer_in';
                
                $stmt_ins->bind_param("iisdsssssi", 
                    $to_account_id, 
                    $client_id, 
                    $in_type, 
                    $amount, 
                    $target_currency, 
                    $transaction_date, 
                    $desc_to, 
                    $reference_number, 
                    $beneficiary, 
                    $current_user_id
                );
                $stmt_ins->execute();

                // تحديث رصيد الحساب المستلم (زيادة)
                $stmt_upd->bind_param("di", $amount, $to_account_id);
                $stmt_upd->execute();
            }

            $conn->commit();
            $success_message = "تم تسجيل العملية بنجاح وتحديث الأرصدة.";
            
            header("Location: record_bank_movement.php?success=" . urlencode($success_message));
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "خطأ فني في قاعدة البيانات: " . $e->getMessage();
        }
    }
}

if (isset($_GET['success'])) { $success_message = htmlspecialchars($_GET['success']); }

include 'header.php'; 
?>

<style>
    :root { --deposit: #1cc88a; --withdrawal: #e74a3b; --transfer: #4e73df; }
    .card-transfer { border-radius: 15px; border: none; overflow: hidden; }
    .type-indicator { height: 6px; transition: 0.3s; }
    .form-control { border-radius: 10px; padding: 12px; border: 1px solid #ddd; }
    .bg-deposit { background-color: var(--deposit); }
    .bg-withdrawal { background-color: var(--withdrawal); }
    .bg-transfer { background-color: var(--transfer); }
    .btn-action { border-radius: 12px; padding: 14px; font-weight: bold; transition: 0.2s; }
    .btn-action:active { transform: scale(0.97); }
</style>

<div class="container-fluid py-3" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4 text-right">
        <div>
            <h1 class="h4 font-weight-bold text-dark mb-0">حركة مالية جديدة</h1>
            <small class="text-muted">إدارة الحسابات البنكية والخزينة</small>
        </div>
        <a href="company_treasury.php" class="btn btn-light btn-circle shadow-sm"><i class="fas fa-times"></i></a>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success border-0 shadow-sm text-right"><i class="fas fa-check-circle ml-2"></i> <?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger border-0 shadow-sm text-right"><i class="fas fa-exclamation-triangle ml-2"></i> <?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="card card-transfer shadow-sm mb-5">
        <div id="status_bar" class="type-indicator bg-deposit"></div>
        <div class="card-body p-4 text-right">
            <form method="POST" id="transferForm">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label class="small font-weight-bold">نوع العملية</label>
                        <select name="movement_type" id="movement_type" class="form-control" required onchange="updateUI()">
                            <option value="deposit" data-class="bg-deposit">📥 إيداع / تحويل وارد (+)</option>
                            <option value="withdrawal" data-class="bg-withdrawal">📤 سحب نقد / مصروفات (-)</option>
                            <option value="internal_transfer" data-class="bg-transfer">🔄 تحويل داخلي (بين حساباتنا)</option>
                            <option value="external_transfer" data-class="bg-withdrawal">💸 تحويل خارجي (لجهة أخرى)</option>
                        </select>
                    </div>

                    <div class="col-md-4 form-group">
                        <label class="small font-weight-bold" id="label_account_primary">إلى حساب (المستلم)</label>
                        <select name="account_id" class="form-control" required>
                            <option value="">-- اختر الحساب --</option>
                            <?php foreach ($active_accounts as $acc): ?>
                                <option value="<?php echo $acc['id']; ?>">
                                    <?php echo htmlspecialchars($acc['bank_name'] . " - " . $acc['currency_code']); ?> 
                                    (رصيد: <?php echo number_format($acc['current_balance'], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 form-group">
                        <label class="small font-weight-bold">المبلغ</label>
                        <input type="number" step="0.01" name="amount" class="form-control font-weight-bold text-primary" placeholder="0.00" required>
                    </div>
                </div>

                <div id="box_internal" style="display:none;" class="p-3 mb-3 bg-light rounded border-right border-primary">
                    <label class="small font-weight-bold text-primary">الحساب المحول إليه (المستلم)</label>
                    <select name="to_account_id" class="form-control">
                        <option value="">-- اختر الحساب المستلم --</option>
                        <?php foreach ($active_accounts as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>">
                                <?php echo htmlspecialchars($acc['bank_name'] . " - " . $acc['account_number']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="box_external" style="display:none;" class="form-group animated fadeIn">
                    <label class="small font-weight-bold text-danger">اسم الجهة المستلمة / المستفيد</label>
                    <input type="text" name="beneficiary_details" class="form-control" placeholder="مثال: شركة التوريدات العالمية">
                </div>

                <div class="row">
                    <div class="col-6 form-group">
                        <label class="small">التاريخ</label>
                        <input type="date" name="transaction_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-6 form-group">
                        <label class="small">رقم المرجع</label>
                        <input type="text" name="reference_number" class="form-control" placeholder="رقم الإيصال أو الحركة">
                    </div>
                </div>

                <div class="form-group">
                    <label class="small">البيان / التفاصيل</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="اكتب ملاحظات إضافية هنا..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-action shadow mt-3">
                    <i class="fas fa-check-double ml-2"></i> اعتماد العملية المالية
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function updateUI() {
    const type = document.getElementById('movement_type').value;
    const bar = document.getElementById('status_bar');
    const label = document.getElementById('label_account_primary');
    const selectedOpt = document.querySelector('#movement_type option:checked');
    
    bar.className = 'type-indicator ' + selectedOpt.getAttribute('data-class');
    document.getElementById('box_internal').style.display = (type === 'internal_transfer') ? 'block' : 'none';
    document.getElementById('box_external').style.display = (type === 'external_transfer' || type === 'withdrawal') ? 'block' : 'none';
    
    if (type === 'deposit') {
        label.innerText = "إلى حساب (المستلم)";
    } else if (type === 'internal_transfer') {
        label.innerText = "من حساب (المصدر)";
    } else {
        label.innerText = "من حساب (المصدر)";
    }
}
document.addEventListener('DOMContentLoaded', updateUI);
</script>

<?php include 'footer.php'; ?>