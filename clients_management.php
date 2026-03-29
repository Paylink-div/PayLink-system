<?php
// clients_management.php - إدارة العملاء مع ميزة سقف السحب للمدير العام
if (session_status() == PHP_SESSION_NONE) session_start();

include 'functions.php'; 
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$is_general_manager = (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'مدير عام');

$pageTitle = 'إدارة العملاء';
$current_file_name = 'clients_management.php'; 
$message = '';
$message_type = '';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'];

// 1. معالجة العمليات (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && $_POST['action'] == 'update_limit' && $is_general_manager) {
        $client_id = intval($_POST['client_id']);
        $limit_value = floatval($_POST['daily_limit']);
        $stmt = $conn->prepare("UPDATE clients SET daily_withdrawal_limit = ? WHERE id = ?");
        $stmt->bind_param("di", $limit_value, $client_id);
        if ($stmt->execute()) { $message = "تم تحديث سقف السحب بنجاح."; $message_type = 'success'; }
    }
    
    $full_name = trim($_POST['full_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $passport_image_path = '';
    if (isset($_FILES['passport_image']) && $_FILES['passport_image']['error'] == 0) {
        $target_dir = "uploads/passports/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $new_filename = uniqid('passport_') . '.' . pathinfo($_FILES["passport_image"]["name"], PATHINFO_EXTENSION);
        if (move_uploaded_file($_FILES["passport_image"]["tmp_name"], $target_dir . $new_filename)) {
            $passport_image_path = $target_dir . $new_filename;
        }
    }

    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO clients (full_name, phone_number, id_number, address, passport_image_path, email) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $full_name, $phone_number, $id_number, $address, $passport_image_path, $email);
        if ($stmt->execute()) { $message = "تم إضافة العميل بنجاح."; $message_type = 'success'; }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $client_id = intval($_POST['client_id']);
        $sql = "UPDATE clients SET full_name=?, phone_number=?, id_number=?, address=?, email=? " . (!empty($passport_image_path) ? ", passport_image_path=?" : "") . " WHERE id=?";
        $stmt = $conn->prepare($sql);
        if (!empty($passport_image_path)) $stmt->bind_param("ssssssi", $full_name, $phone_number, $id_number, $address, $email, $passport_image_path, $client_id);
        else $stmt->bind_param("sssssi", $full_name, $phone_number, $id_number, $address, $email, $client_id);
        if ($stmt->execute()) { $message = "تم التحديث بنجاح."; $message_type = 'success'; }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $client_id = intval($_POST['client_id']);
        $stmt = $conn->prepare("DELETE FROM clients WHERE id=?");
        $stmt->bind_param("i", $client_id);
        if ($stmt->execute()) { $message = "تم حذف العميل."; $message_type = 'success'; }
    }
    
    if ($message_type === 'success') {
        header("Location: {$current_file_name}?message=" . urlencode($message) . "&type=success"); exit;
    }
}

// 2. جلب البيانات
$search = $conn->real_escape_string($_GET['search'] ?? '');
$search_sql = !empty($search) ? " WHERE (full_name LIKE '%$search%' OR phone_number LIKE '%$search%') " : "";
$clients_data = $conn->query("SELECT * FROM clients $search_sql ORDER BY id DESC");

include 'header.php'; 
?>

<style>
    :root { --primary: #4e73df; --success: #1cc88a; --info: #36b9cc; --danger: #e74a3b; --dark: #5a5c69; --purple: #6f42c1; --orange: #fd7e14; }
    body { background-color: #f8f9fc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .btn-orange { background-color: var(--orange) !important; border-color: var(--orange) !important; color: white !important; }
    .btn-wallet { background: var(--purple); color: #fff; border: none; }
    .client-table-card { border-radius: 12px; border: none; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); }
</style>

<div class="container-fluid py-3" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-4 text-right flex-wrap">
        <div>
            <h1 class="h3 font-weight-bold text-dark mb-1"><i class="fas fa-user-friends text-primary ml-2"></i> إدارة العملاء</h1>
        </div>
    </div>

    <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 text-right">
            <i class="fas fa-check-circle ml-2"></i> <?php echo htmlspecialchars($_GET['message']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-12 col-md-4 mb-3">
            <button class="btn btn-success btn-block btn-lg shadow-sm" style="border-radius: 10px;" data-toggle="modal" data-target="#addClientModal">
                <i class="fas fa-plus-circle ml-1"></i> إضافة عميل جديد
            </button>
        </div>
        <div class="col-12 col-md-8">
            <form method="GET" class="input-group shadow-sm" style="border-radius: 10px; overflow: hidden;">
                <input type="text" name="search" class="form-control form-control-lg text-right border-0" placeholder="ابحث بالاسم، الهاتف، أو الرقم الوطني..." value="<?php echo htmlspecialchars($search); ?>">
                <div class="input-group-append">
                    <button class="btn btn-primary px-4" type="submit"><i class="fas fa-search"></i></button>
                    <?php if($search): ?><a href="clients_management.php" class="btn btn-warning"><i class="fas fa-times"></i></a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card client-table-card overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive"> 
                <table class="table table-hover mb-0 text-right">
                    <thead style="background: #f8f9fc; color: var(--dark);">
                        <tr>
                            <th class="border-0">العميل</th>
                            <th class="border-0 text-center">رقم الهاتف</th>
                            <th class="border-0 text-center">سقف السحب</th> 
                            <th class="border-0 text-center">تاريخ التسجيل</th>
                            <th class="border-0 text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($clients_data && $clients_data->num_rows > 0): ?>
                            <?php while ($row = $clients_data->fetch_assoc()): 
                                $client_name_param = urlencode($row['full_name']);
                                $direct_url = $base_url . "/portal/client_direct_login.php?client_name=" . $client_name_param;
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle ml-3" style="width:35px; height:35px; background: #eaecf4; border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--primary); font-weight:bold;">
                                                <?php echo mb_substr($row['full_name'], 0, 1, 'utf-8'); ?>
                                            </div>
                                            <div>
                                                <div class="font-weight-bold text-dark"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                                <div class="small text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($row['id_number']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center"><?php echo htmlspecialchars($row['phone_number']); ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-pill badge-warning text-dark p-2">
                                            <?php echo number_format($row['daily_withdrawal_limit'] ?? 0, 2); ?> د.ل
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                                    <td class="text-center">
                                        <div class="action-btns">
                                            <?php if($is_general_manager): ?>
                                            <button class="btn btn-sm btn-orange limit-btn" data-toggle="modal" data-target="#limitModal" 
                                                    data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['full_name']); ?>"
                                                    data-current="<?php echo $row['daily_withdrawal_limit'] ?? 0; ?>" title="تحديد سقف السحب">
                                                <i class="fas fa-hand-holding-usd"></i>
                                            </button>
                                            <?php endif; ?>

                                            <button class="btn btn-sm btn-outline-dark" onclick="copyClientLink('<?php echo $direct_url; ?>')" title="نسخ الرابط المباشر">
                                                <i class="fas fa-link"></i>
                                            </button>
                                            <a href="client_details.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-wallet shadow-sm" title="المحفظة">
                                                <i class="fas fa-wallet"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-info edit-btn" data-toggle="modal" data-target="#editClientModal" 
                                                    data-id="<?php echo $row['id']; ?>" data-fullname="<?php echo htmlspecialchars($row['full_name']); ?>"
                                                    data-phone="<?php echo htmlspecialchars($row['phone_number']); ?>" data-email="<?php echo htmlspecialchars($row['email']); ?>"
                                                    data-idnumber="<?php echo htmlspecialchars($row['id_number']); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger delete-btn" data-toggle="modal" data-target="#deleteClientModal" 
                                                    data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['full_name']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">لا توجد بيانات عملاء.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="limitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <form method="POST">
                <div class="modal-header bg-orange text-white" style="border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title"><i class="fas fa-hand-holding-usd ml-2"></i> تحديد سقف سحب يومي</h5>
                </div>
                <div class="modal-body text-right">
                    <input type="hidden" name="action" value="update_limit">
                    <input type="hidden" name="client_id" id="limit-client-id">
                    <div class="form-group">
                        <label class="font-weight-bold">العميل:</label>
                        <input type="text" class="form-control-plaintext text-primary font-weight-bold" id="limit-client-name" readonly>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">سقف السحب (دينار):</label>
                        <input type="number" step="0.01" class="form-control form-control-lg" name="daily_limit" id="limit-current-val" required placeholder="مثال: 5000">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light px-4" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-orange px-5 shadow-sm">حفظ السقف</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addClientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header bg-success text-white" style="border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title"><i class="fas fa-user-plus ml-2"></i> إضافة عميل جديد</h5>
                </div>
                <div class="modal-body text-right">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label class="small font-weight-bold">الاسم الرباعي:*</label>
                        <input type="text" class="form-control" name="full_name" required placeholder="أدخل الاسم الكامل">
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">رقم الهاتف:*</label>
                            <input type="text" class="form-control" name="phone_number" required placeholder="09xxxxxxx">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">الرقم الوطني:</label>
                            <input type="text" class="form-control" name="id_number" placeholder="رقم الهوية">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">البريد الإلكتروني (اختياري):</label>
                        <input type="email" class="form-control" name="email" placeholder="example@mail.com">
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">تحميل صورة الجواز/الهوية:</label>
                        <input type="file" class="form-control-file" name="passport_image">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light px-4" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success px-5 shadow-sm">حفظ العميل</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editClientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg" style="border-radius: 15px;">
            <form method="POST">
                <div class="modal-header bg-info text-white" style="border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title"><i class="fas fa-user-edit ml-2"></i> تحديث بيانات العميل</h5>
                </div>
                <div class="modal-body text-right">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="client_id" id="edit-client-id">
                    <div class="form-group">
                        <label class="small font-weight-bold">الاسم الكامل:</label>
                        <input type="text" class="form-control" name="full_name" id="edit-full_name" required>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">الهاتف:</label>
                        <input type="text" class="form-control" name="phone_number" id="edit-phone_number" required>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">الرقم الوطني:</label>
                        <input type="text" class="form-control" name="id_number" id="edit-id_number">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">إغلاق</button>
                    <button type="submit" class="btn btn-info px-4 shadow-sm">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteClientModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
            <form method="POST">
                <div class="modal-body text-center py-4">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="client_id" id="delete-client-id">
                    <div class="mb-3"><i class="fas fa-exclamation-circle text-danger fa-4x"></i></div>
                    <h5 class="font-weight-bold">هل أنت متأكد؟</h5>
                    <p class="small text-muted">سيتم حذف العميل <span id="delete-client-name" class="text-danger font-weight-bold"></span>.</p>
                </div>
                <div class="modal-footer border-0 justify-content-center pb-4">
                    <button type="button" class="btn btn-light px-4" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger px-4 shadow-sm">نعم، احذف</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

<script>
function copyClientLink(link) {
    const el = document.createElement('textarea');
    el.value = link;
    document.body.appendChild(el); 
    el.select();
    document.execCommand('copy');
    alert("✅ تم نسخ رابط العميل بنجاح");
    document.body.removeChild(el);
}

$(document).ready(function() {
    $('.limit-btn').on('click', function() {
        $('#limit-client-id').val($(this).data('id'));
        $('#limit-client-name').val($(this).data('name'));
        $('#limit-current-val').val($(this).data('current'));
    });
    $('.edit-btn').on('click', function() {
        $('#edit-client-id').val($(this).data('id'));
        $('#edit-full_name').val($(this).data('fullname'));
        $('#edit-phone_number').val($(this).data('phone'));
        $('#edit-id_number').val($(this).data('idnumber'));
    });
    $('.delete-btn').on('click', function() {
        $('#delete-client-id').val($(this).data('id'));
        $('#delete-client-name').text($(this).data('name'));
    });
});
</script>

<?php include 'footer.php'; ?>