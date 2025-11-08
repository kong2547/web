<?php
session_start();
require 'db.php'; // เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล

// ตรวจสอบสิทธิ์การเข้าถึงว่าเป็น 'admin'
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$search = '';
$result = null;

// ส่วนการจัดการการแก้ไขข้อมูลผู้ใช้ (ถูกย้ายมารวมในไฟล์นี้)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = (int)$_POST['user_id'];
    $new_role = $_POST['role'];
    $new_status = $_POST['status'];

    if ($user_id > 0) {
        $stmt = $conn->prepare("UPDATE users SET role = ?, status = ? WHERE id = ?");
        $stmt->execute([$new_role, $new_status, $user_id]);
        header("Location: user_data.php");
        exit();
    }
}

// ส่วนการจัดการการค้นหาผู้ใช้
if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE fullname LIKE ? OR username LIKE ?");
    $like = "%$search%";
    $stmt->execute([$like, $like]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $conn->prepare("SELECT * FROM users ORDER BY id ASC");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้งาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8f9fa;
        }
        .navbar {
            background: #343a40;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
        }
        .navbar a {
            color: #fff;
            text-decoration: none;
            margin-right: 15px;
            font-weight: bold;
        }
        .navbar a:hover {
            text-decoration: underline;
        }
        .navbar a.active,
        .navbar a.active:hover {
            color: #ffc107 !important;
        }
    </style>
</head>
<body>
    
    <!-- Navbar 
    <div class="navbar">
        <a href="index.php">🏠 หน้าแรก</a>
        <a href="building.php">🏢 อาคาร</a>
        <a href="plan.php">🏢 แผนผังตำแหน่งห้องภายในชั้น</a>
        <a href="logout.php" style="margin-left:auto; color:#ffc107;">🚪 ออกจากระบบ</a>
    </div> 
    -->
    <div class="container py-5">
        <h2 class="text-center mb-4 text-primary">รายชื่อผู้ใช้งาน</h2>

        <!-- Search Form -->
        <div class="d-flex justify-content-center mb-4">
            <form method="get" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="ค้นหาชื่อหรือ username" value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">ค้นหา</button>
            </form>
        </div>

        <!-- User Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th class="text-center">ID</th>
                        <th class="text-center">ชื่อ-นามสกุล</th>
                        <th class="text-center">Username</th>
                        <th class="text-center">สิทธิ์</th>
                        <th class="text-center">สถานะ</th>
                        <th class="text-center">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (is_array($result) && count($result) > 0) {
                        foreach ($result as $row) {
                            echo "<tr>";
                            echo "<td class='text-center'>" . htmlspecialchars($row['id']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['role']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                            echo "<td class='text-center'>";
                            echo "<a href='#' class='btn btn-warning btn-sm me-2' data-bs-toggle='modal' data-bs-target='#editUserModal' data-id='{$row['id']}' data-role='{$row['role']}' data-status='{$row['status']}'>แก้ไข</a>";
                            echo "<a href='delete_user.php?id={$row['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"คุณแน่ใจหรือไม่ที่จะลบ?\")'>ลบ</a>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center'>ไม่พบข้อมูลผู้ใช้งาน</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div class="text-center mt-4">
            <a href="admin_dashboard.php" class="btn btn-secondary">← กลับ Dashboard</a>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">แก้ไขผู้ใช้งาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="user_data.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="modal-user-id">
                        <div class="mb-3">
                            <label for="modal-username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="modal-username" disabled>
                        </div>
                        <div class="mb-3">
                            <label for="modal-role" class="form-label">สิทธิ์</label>
                            <select class="form-select" id="modal-role" name="role">
                                <option value="user">user</option>
                                <option value="admin">admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="modal-status" class="form-label">สถานะ</label>
                            <select class="form-select" id="modal-status" name="status">
                                <option value="pending">pending</option>
                                <option value="active">active</option>
                                <option value="rejected">rejected</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="update_user" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JavaScript สำหรับจัดการ Modal
        var editUserModal = document.getElementById('editUserModal');
        editUserModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Button ที่กด
            var userId = button.getAttribute('data-id');
            var userRole = button.getAttribute('data-role');
            var userStatus = button.getAttribute('data-status');
            var modalUserId = editUserModal.querySelector('#modal-user-id');
            var modalRoleSelect = editUserModal.querySelector('#modal-role');
            var modalStatusSelect = editUserModal.querySelector('#modal-status');
            
            modalUserId.value = userId;
            modalRoleSelect.value = userRole;
            modalStatusSelect.value = userStatus;

            // ค้นหา username จากตารางเพื่อแสดงใน modal
            var row = button.closest('tr');
            var username = row.querySelector('td:nth-child(3)').textContent;
            editUserModal.querySelector('#modal-username').value = username;
        });
    </script>
</body>
</html>
