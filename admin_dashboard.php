<?php
// admin_dashboard.php - لوحة التحكم المركزية المحدثة مع ميزة الحذف
include 'db_connect.php'; 

// حماية الصفحة: التأكد أن الداخل هو صاحب المنظومة فقط
if (!isset($_SESSION['super_admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

// 1. معالجة حذف الشركة
if (isset($_GET['delete_id'])) {
    $d_id = intval($_GET['delete_id']);
    
    // ملاحظة: هذا سيحذف سجل الشركة من القاعدة المركزية فقط
    // إذا كنت تريد حذف قاعدة بيانات الشركة الفعلية ستحتاج لأمر DROP DATABASE (خطير جداً)
    $master_conn->query("DELETE FROM companies WHERE id = $d_id");
    
    header("Location: admin_dashboard.php?msg=deleted"); 
    exit;
}

// 2. معالجة تغيير الحالة (إيقاف / تشغيل)
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $c_id = intval($_GET['id']);
    $new_status = ($_GET['toggle_status'] == 'active') ? 'inactive' : 'active';
    $master_conn->query("UPDATE companies SET status = '$new_status' WHERE id = $c_id");
    header("Location: admin_dashboard.php"); 
    exit;
}

// 3. جلب الإحصائيات
$total_companies = $master_conn->query("SELECT COUNT(*) as count FROM companies")->fetch_assoc()['count'];
$active_companies = $master_conn->query("SELECT COUNT(*) as count FROM companies WHERE status = 'active'")->fetch_assoc()['count'];
$inactive_companies = $master_conn->query("SELECT COUNT(*) as count FROM companies WHERE status = 'inactive'")->fetch_assoc()['count'];

// 4. جلب قائمة الشركات
$result = $master_conn->query("SELECT * FROM companies ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة التحكم المركزية | PayLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Cairo', sans-serif; background: #0f172a; color: #f8fafc; margin: 0; display: flex; }
        .sidebar { width: 240px; background: #1e293b; height: 100vh; padding: 15px; border-left: 1px solid #334155; position: fixed; right: 0; box-sizing: border-box; }
        .main-content { flex: 1; padding: 30px; margin-right: 240px; width: calc(100% - 240px); }
        
        .stats-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: #1e293b; padding: 18px; border-radius: 12px; border: 1px solid #334155; display: flex; align-items: center; }
        .stat-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-left: 12px; }
        .stat-info h3 { margin: 0; font-size: 0.85rem; color: #94a3b8; }
        .stat-info p { margin: 2px 0 0; font-size: 1.5rem; font-weight: bold; }

        .card { background: #1e293b; border-radius: 12px; padding: 20px; border: 1px solid #334155; }
        .btn { padding: 7px 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 0.8rem; transition: 0.3s; }
        .btn-primary { background: #38bdf8; color: #0f172a; }
        .btn-edit { background: #475569; color: white; }
        .btn-stop { background: #f59e0b; color: white; } /* برتقالي للإيقاف المؤقت */
        .btn-start { background: #22c55e; color: white; }
        .btn-delete { background: #ef4444; color: white; }
        .btn-delete:hover { background: #b91c1c; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: right; border-bottom: 1px solid #334155; font-size: 0.9rem; }
        th { color: #38bdf8; background: rgba(56, 189, 248, 0.05); }
        
        .status-badge { padding: 3px 10px; border-radius: 15px; font-size: 0.7rem; font-weight: bold; }
        .active-badge { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
        .inactive-badge { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        
        .nav-link { color: #94a3b8; display: block; padding: 12px; text-decoration: none; border-radius: 8px; margin-bottom: 5px; }
        .nav-link.active { background: rgba(56, 189, 248, 0.15); color: #38bdf8; font-weight: bold; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2 style="color: #38bdf8; text-align: center; font-size: 1.4rem; margin-bottom: 30px;">PAYLINK</h2>
    <nav>
        <a href="admin_dashboard.php" class="nav-link active"><i class="fas fa-building ml-2"></i> إدارة الشركات</a>
        <a href="logout.php" class="nav-link" style="color: #ef4444;"><i class="fas fa-power-off ml-2"></i> تسجيل خروج</a>
    </nav>
</div>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h1 style="font-size: 1.7rem;">إدارة الشركات المشتركة</h1>
        <a href="add_company.php" class="btn btn-primary"><i class="fas fa-plus"></i> إضافة شركة جديدة</a>
    </div>

    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(56, 189, 248, 0.1); color: #38bdf8;"><i class="fas fa-list"></i></div>
            <div class="stat-info"><h3>إجمالي الشركات</h3><p><?php echo $total_companies; ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info"><h3>مفعّلة</h3><p><?php echo $active_companies; ?></p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(252, 2, 2, 0.93); color: #ef4444;"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info"><h3>متوقفة</h3><p><?php echo $inactive_companies; ?></p></div>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>اسم الشركة</th>
                    <th>Subdomain</th>
                    <th>قاعدة البيانات</th>
                    <th>الحالة</th>
                    <th>تاريخ التسجيل</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): 
                    $status_class = ($row['status'] == 'active') ? 'active-badge' : 'inactive-badge';
                    $status_text = ($row['status'] == 'active') ? 'نشط' : 'متوقف';
                ?>
                <tr>
                    <td><strong><?php echo $row['company_name']; ?></strong></td>
                    <td><span style="color: #38bdf8;"><?php echo $row['subdomain']; ?></span></td>
                    <td><code><?php echo $row['db_name']; ?></code></td>
                    <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                    <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                    <td>
                        <div style="display: flex; gap: 5px;">
                            <a href="edit_company.php?id=<?php echo $row['id']; ?>" class="btn btn-edit" title="تعديل">
                                <i class="fas fa-edit"></i>
                            </a>

                            <?php if($row['status'] == 'active'): ?>
                                <a href="admin_dashboard.php?toggle_status=active&id=<?php echo $row['id']; ?>" 
                                   class="btn btn-stop" onclick="return confirm('إيقاف المنظومة مؤقتاً؟')" title="إيقاف">
                                    <i class="fas fa-pause"></i>
                                </a>
                            <?php else: ?>
                                <a href="admin_dashboard.php?toggle_status=inactive&id=<?php echo $row['id']; ?>" 
                                   class="btn btn-start" title="تفعيل">
                                    <i class="fas fa-play"></i>
                                </a>
                            <?php endif; ?>

                            <a href="admin_dashboard.php?delete_id=<?php echo $row['id']; ?>" 
                               class="btn btn-delete" 
                               onclick="return confirm('⚠️ تحذير: هل أنت متأكد من حذف هذه الشركة نهائياً؟ لا يمكن التراجع!')" 
                               title="حذف">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>