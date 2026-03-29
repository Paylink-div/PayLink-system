<?php
// exchange.php - النسخة النهائية المعتمدة والمتوافقة مع بنية جدول transactions

if (session_status() == PHP_SESSION_NONE) session_start();

$pageTitle = 'إجراء عملية صرف';
require_once 'db_connect.php'; 
include 'functions.php'; 

$message = '';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ملاحظة: تم الحفاظ على الثوابت لضمان عمل المنظومة
if (!defined('CURRENT_COMPANY_ID')) {
    define('CURRENT_COMPANY_ID', 1);
}
$user_id = $_SESSION['user_id'];
$is_admin = (($_SESSION['user_role'] ?? '') == 'مدير عام');

// 1. إدارة الفروع
if ($is_admin && isset($_POST['switch_branch_id'])) {
    $_SESSION['operating_branch_id'] = (int)$_POST['switch_branch_id'];
}
$branch_id_for_transaction = ($is_admin) ? ($_SESSION['operating_branch_id'] ?? $_SESSION['branch_id']) : $_SESSION['branch_id'];

// 2. جلب الفروع
$branches = [];
if ($is_admin) {
    $res_br = $conn->query("SELECT id, name FROM branches");
    while ($row = $res_br->fetch_assoc()) { $branches[] = $row; }
}

$current_branch_name = "فرع غير معروف";
$res_curr_br = $conn->query("SELECT name FROM branches WHERE id = $branch_id_for_transaction");
if($row_br = $res_curr_br->fetch_assoc()) { $current_branch_name = $row_br['name']; }

// 3. جلب أرصدة الخزينة للفرع الحالي
$branch_treasury_balances = [];
$sql_bal = "SELECT tb.current_balance, c.currency_code, c.currency_name_ar 
            FROM treasury_balances tb 
            JOIN currencies c ON tb.currency_id = c.id 
            WHERE tb.branch_id = $branch_id_for_transaction";
$res_bal = $conn->query($sql_bal);
while ($row = $res_bal->fetch_assoc()) { $branch_treasury_balances[] = $row; }

// 4. جلب المصارف
$banks_list = [];
$sql_banks = "SELECT id, bank_name, account_number, current_balance FROM company_bank_accounts WHERE is_active = 1";
$res_banks = $conn->query($sql_banks);
if ($res_banks) {
    while ($row = $res_banks->fetch_assoc()) { $banks_list[] = $row; }
}

// 5. جلب أسعار الصرف ومعرفات العملات
$current_exchange_rates = [];
$sql_rates = "SELECT er.*, c.id as currency_id, c.currency_code, c.currency_name_ar 
              FROM exchange_rates er 
              JOIN currencies c ON er.currency_code = c.currency_code";
$res_rates = $conn->query($sql_rates);
while ($row = $res_rates->fetch_assoc()) {
    $current_exchange_rates[$row['currency_code']] = $row;
}

// 6. جلب العملاء
$registered_clients = [];
$res_cl = $conn->query("SELECT id, full_name FROM clients ORDER BY full_name ASC");
while ($row = $res_cl->fetch_assoc()) { $registered_clients[] = $row; }

// 7. جلب معرف الدينار الليبي (مهم لعمود to_currency_id)
$res_lyd_info = $conn->query("SELECT id FROM currencies WHERE currency_code = 'LYD' LIMIT 1");
$lyd_currency_id = ($res_lyd_info->num_rows > 0) ? $res_lyd_info->fetch_assoc()['id'] : 1;

// معالجة الحفظ وتحديث الأرصدة
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_transaction'])) {
    try {
        $type = $_POST['transaction_type']; 
        $currency_code = $_POST['currency'];
        $amount_foreign = floatval($_POST['amount_foreign']);
        $payment_method = $_POST['payment_method'];
        $bank_account_id = ($payment_method != 'كاش') ? (int)$_POST['bank_id'] : null;
        $client_name = ($_POST['client_type'] == 'registered') ? $_POST['registered_client_name'] : ($_POST['client_name_manual'] ?: 'عميل نقدي');

        if (isset($current_exchange_rates[$currency_code])) {
            $rate_info = $current_exchange_rates[$currency_code];
            $currency_id = $rate_info['currency_id'];
            $rate = ($type === 'شراء') ? $rate_info['buy_rate'] : $rate_info['sell_rate'];
            $comm_percent = $rate_info['commission_percentage'];
            
            $amount_LYD = $amount_foreign * $rate;
            $commission = $amount_LYD * ($comm_percent / 100);
            $net_amount = ($type === 'شراء') ? ($amount_LYD - $commission) : ($amount_LYD + $commission);

            $conn->begin_transaction();
            $serial = generate_serial_number();
            
            // 1. تسجيل العملية الأساسية (تم إضافة to_currency_id و is_finalized و transaction_date)
            $sql_ins = "INSERT INTO transactions (
                            transaction_type, amount_foreign, from_currency_id, to_currency_id, 
                            amount_LYD, rate_used, commission_percentage, commission_amount, 
                            net_amount, client_name, user_id, serial_number, branch_id, 
                            payment_method, is_finalized, transaction_date
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
            
            $stmt = $conn->prepare($sql_ins);
            // تعديل bind_param ليتناسب مع الأعمدة الـ 14 الممررة قيمها (سلسلة iid...)
            $stmt->bind_param("sdiddddddssiss", 
                $type, $amount_foreign, $currency_id, $lyd_currency_id, 
                $amount_LYD, $rate, $comm_percent, $commission, 
                $net_amount, $client_name, $user_id, $serial, 
                $branch_id_for_transaction, $payment_method
            );
            $stmt->execute();

            // 2. تحديث رصيد العملة الأجنبية في الخزينة
            $foreign_change = ($type === 'شراء') ? $amount_foreign : -$amount_foreign;
            $sql_update_foreign = "INSERT INTO treasury_balances (branch_id, currency_id, current_balance, last_updated) 
                                   VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE current_balance = current_balance + ?, last_updated = NOW()";
            $st_f = $conn->prepare($sql_update_foreign);
            $st_f->bind_param("iidd", $branch_id_for_transaction, $currency_id, $foreign_change, $foreign_change);
            $st_f->execute();

            // 3. تحديث رصيد الدينار الليبي أو المصرف
            if ($payment_method == 'كاش') {
                $lyd_change = ($type === 'شراء') ? -$net_amount : $net_amount;
                $sql_update_lyd = "INSERT INTO treasury_balances (branch_id, currency_id, current_balance, last_updated) 
                                   VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE current_balance = current_balance + ?, last_updated = NOW()";
                $st_l = $conn->prepare($sql_update_lyd);
                $st_l->bind_param("iidd", $branch_id_for_transaction, $lyd_currency_id, $lyd_change, $lyd_change);
                $st_l->execute();
            } else {
                $bank_change = ($type === 'شراء') ? -$net_amount : $net_amount;
                $conn->query("UPDATE company_bank_accounts SET current_balance = current_balance + $bank_change WHERE id = $bank_account_id");
            }

            $conn->commit();
            header("Location: print_invoice.php?serial=$serial");
            exit();
        }
    } catch (Exception $e) {
        $conn->rollback();
        $message = "❌ خطأ: " . $e->getMessage();
    }
}

include 'header.php'; 
?>

<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/all.min.css">

<div class="container-fluid" dir="rtl" style="padding:20px;">
    
    <?php if ($is_admin): ?>
    <div class="card shadow-sm mb-4 border-0 bg-light">
        <div class="card-body d-flex align-items-center justify-content-between py-2">
            <div><strong>الفرع النشط حالياً: </strong> <span class="badge badge-primary px-3 py-2" style="font-size:1rem;"><?php echo $current_branch_name; ?></span></div>
            <form method="POST" class="form-inline">
                <label class="ml-2 font-weight-bold">تبديل الفرع: </label>
                <select name="switch_branch_id" class="form-control" onchange="this.form.submit()">
                    <?php foreach($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo ($branch_id_for_transaction == $b['id']) ? 'selected' : ''; ?>>
                            <?php echo $b['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow p-4 border-0">
                <h4 class="text-primary mb-4 border-bottom pb-2">إجراء عملية صرف</h4>
                <?php if($message) echo "<div class='alert alert-danger'>$message</div>"; ?>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">نوع العملية</label>
                            <select name="transaction_type" id="t_type" class="form-control form-control-lg" onchange="calc()">
                                <option value="شراء">شراء من العميل (+)</option>
                                <option value="بيع">بيع للعميل (-)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">العملة الأجنبية</label>
                            <select name="currency" id="t_cur" class="form-control form-control-lg" required onchange="calc()">
                                <option value="">-- اختر العملة --</option>
                                <?php foreach($current_exchange_rates as $code => $r): ?>
                                    <option value="<?php echo $code; ?>"><?php echo $r['currency_name_ar']; ?> (<?php echo $code; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4 text-center">
                        <label class="font-weight-bold">المبلغ بالعملة الأجنبية</label>
                        <input type="number" step="0.01" name="amount_foreign" id="t_amt" class="form-control form-control-lg text-center shadow-sm" style="font-size: 2rem;" onkeyup="calc()" required>
                    </div>

                    <div class="row bg-light p-3 rounded mb-4 text-center border">
                        <div class="col-4 border-left">سعر الصرف: <br><strong id="v_rate" class="text-info">0.000</strong></div>
                        <div class="col-4 border-left">العمولة: <br><strong id="v_comm" class="text-danger">0.00</strong></div>
                        <div class="col-4">الصافي (دينار): <br><strong id="v_net" class="text-success" style="font-size:1.5rem;">0.00</strong></div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="font-weight-bold">طريقة تسوية الدينار</label>
                            <select name="payment_method" id="p_method" class="form-control" onchange="toggleBank()">
                                <option value="كاش">كاش (خزينة)</option>
                                <option value="تحويل">تحويل مصرفي</option>
                                <option value="شيك">شيك مصرفي</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="bank_div" style="display:none;">
                            <label class="font-weight-bold text-primary">اختر المصرف</label>
                            <select name="bank_id" class="form-control border-primary">
                                <?php if(empty($banks_list)): ?>
                                    <option value="">لا توجد مصارف في خزينة الشركة!</option>
                                <?php else: ?>
                                    <?php foreach($banks_list as $bank): ?>
                                        <option value="<?php echo $bank['id']; ?>">
                                            <?php echo htmlspecialchars($bank['bank_name']); ?> - <?php echo htmlspecialchars($bank['account_number']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="p-3 border rounded bg-white">
                        <label class="font-weight-bold">هوية العميل</label><br>
                        <input type="radio" name="client_type" value="external" checked onclick="toggleClient(false)"> عميل عابر 
                        <input type="radio" name="client_type" value="registered" onclick="toggleClient(true)"> عميل مسجل
                        <input type="text" name="client_name_manual" id="c_man" class="form-control mt-2" placeholder="اكتب اسم العميل...">
                        <select name="registered_client_name" id="c_reg" class="form-control mt-2" style="display:none;">
                            <?php foreach($registered_clients as $c) echo "<option value='".htmlspecialchars($c['full_name'])."'>".htmlspecialchars($c['full_name'])."</option>"; ?>
                        </select>
                    </div>

                    <button type="submit" name="submit_transaction" class="btn btn-primary btn-block btn-lg mt-4 shadow">تأكيد وحفظ العملية</button>
                </form>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow border-0">
                <div class="card-header bg-dark text-white font-weight-bold text-center">
                    أرصدة <?php echo $current_branch_name; ?>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0 text-center">
                        <thead class="bg-secondary text-white small">
                            <tr>
                                <th>العملة</th>
                                <th>الرصيد المتوفر</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($branch_treasury_balances)): ?>
                                <tr><td colspan="2" class="py-4 text-muted">لا توجد أرصدة مسجلة لهذا الفرع</td></tr>
                            <?php else: ?>
                                <?php foreach($branch_treasury_balances as $bal): ?>
                                    <tr>
                                        <td class="font-weight-bold"><?php echo $bal['currency_name_ar']; ?> (<?php echo $bal['currency_code']; ?>)</td>
                                        <td class="text-primary font-weight-bold"><?php echo number_format($bal['current_balance'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted text-center">
                    تتغير الأرصدة تلقائياً عند تبديل الفرع
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>

<script>
const rates = <?php echo json_encode($current_exchange_rates); ?>;
function calc() {
    const type = document.getElementById('t_type').value;
    const code = document.getElementById('t_cur').value;
    const amt = parseFloat(document.getElementById('t_amt').value) || 0;
    if (code && rates[code]) {
        let r = (type === 'شراء') ? parseFloat(rates[code].buy_rate) : parseFloat(rates[code].sell_rate);
        let cp = parseFloat(rates[code].commission_percentage);
        let total = amt * r;
        let comm = total * (cp / 100);
        let net = (type === 'شراء') ? (total - comm) : (total + comm);
        document.getElementById('v_rate').innerText = r.toFixed(3);
        document.getElementById('v_comm').innerText = comm.toFixed(2);
        document.getElementById('v_net').innerText = net.toLocaleString('en-US', {minimumFractionDigits: 2});
    }
}
function toggleBank() {
    const m = document.getElementById('p_method').value;
    document.getElementById('bank_div').style.display = (m !== 'كاش') ? 'block' : 'none';
}
function toggleClient(isReg) {
    document.getElementById('c_man').style.display = isReg ? 'none' : 'block';
    document.getElementById('c_reg').style.display = isReg ? 'block' : 'none';
}
</script>

<?php include 'footer.php'; ?>