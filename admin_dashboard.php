<?php
session_start();
include 'db.php'; // Mengasumsikan $conn sudah terkoneksi dengan benar

// Validasi admin login
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

$error_msg = "";
$success_msg = "";

// === Tambah Klien Baru (Ditingkatkan Keamanan) ===
if (isset($_POST['add_client'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    
    // Validasi input dasar
    if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Nama atau format Email tidak valid.";
    } else {
        
        // 1. Cek duplikasi menggunakan Prepared Statement
        $stmt_check = $conn->prepare("SELECT id FROM clients WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $exists = $stmt_check->get_result();
        
        if ($exists->num_rows > 0) {
            $error_msg = "Email sudah digunakan klien lain.";
        } else {
            // 2. Insert Klien baru menggunakan Prepared Statement (Sangat Direkomendasikan)
            $stmt_insert = $conn->prepare("INSERT INTO clients (name, email, status) VALUES (?, ?, 'active')");
            $stmt_insert->bind_param("ss", $name, $email);
            
            if ($stmt_insert->execute()) {
                $success_msg = "Klien berhasil ditambahkan.";
            } else {
                $error_msg = "Gagal menambahkan klien: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}


// === HAPUS KLIEN BARU (Menggunakan Prepared Statement) ===
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = intval($_GET['id']);
    
    if ($delete_id > 0) {
        // 🔥 Perbaikan Keamanan: Gunakan Prepared Statement untuk menghapus data
        
        // Hapus data terkait di tabel lain (progress & client_progress)
        $conn->prepare("DELETE FROM progress WHERE client_id = ?")->execute([$delete_id]);
        $conn->prepare("DELETE FROM client_progress WHERE client_id = ?")->execute([$delete_id]);
        
        // Hapus klien utama
        $stmt_delete = $conn->prepare("DELETE FROM clients WHERE id = ?");
        $stmt_delete->bind_param("i", $delete_id);
        $stmt_delete->execute();
        
        $success_msg = "Klien (ID: $delete_id) dan semua data proyek terkait berhasil dihapus.";
        $stmt_delete->close();
        
        // Redirect untuk membersihkan parameter GET dari URL
        header("Location: admin_dashboard.php");
        exit();
    } else {
        $error_msg = "ID Klien tidak valid untuk dihapus.";
    }
}


// === Ambil Data (Koreksi Syntax SQL) ===
$clients = $conn->query("SELECT id, name, email, status FROM clients ORDER BY name ASC");

// KOREKSI SINTAKS SQL: Menghapus koma setelah 'name'
$active_clients = $conn->query("SELECT id, name FROM clients WHERE status='active' ORDER BY name ASC");

// Query progress tidak memiliki masalah sintaks
$progress = $conn->query("
    SELECT p.id, c.name AS client_name, p.title, p.description, p.status
    FROM progress p
    JOIN clients c ON p.client_id = c.id
    ORDER BY p.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #222; }
        table { border-collapse: collapse; width: 100%; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        input, select, textarea { padding: 5px; width: 100%; box-sizing: border-box; }
        button { background: #007bff; color: white; border: none; padding: 7px 12px; border-radius: 3px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .msg { padding: 8px; margin: 10px 0; }
        .error { background: #f8d7da; color: #842029; }
        .success { background: #d1e7dd; color: #0f5132; }
        h3 { margin-top: 40px; }
        a.btn { background: #198754; color: #fff; padding: 6px 10px; border-radius: 4px; text-decoration: none; display: inline-block; margin-right: 5px; }
        a.btn-edit { background: #198754; }
        a.btn-delete { background: #dc3545; }
        a.btn-delete:hover { background: #c82333; }
    </style>
</head>
<body>
<h2>Admin Dashboard</h2>
<p>Login sebagai <b><?= htmlspecialchars($_SESSION['admin']) ?></b> | <a href="logout.php">Logout</a></p>

<?php if ($error_msg): ?><div class="msg error"><?= $error_msg ?></div><?php endif; ?>
<?php if ($success_msg): ?><div class="msg success"><?= $success_msg ?></div><?php endif; ?>

<h3>Tambah Klien Baru</h3>
<form method="post">
    <input type="text" name="name" placeholder="Nama Klien" required>
    <input type="email" name="email" placeholder="Email Klien" required>
    <button type="submit" name="add_client">Tambah Klien</button>
</form>


<h3>Daftar Klien</h3>
<table>
<tr>
    <th>ID</th>
    <th>Nama</th>
    <th>Email</th>
    <th>Status</th>
    <th>Aksi</th> </tr>
<?php while ($row = $clients->fetch_assoc()): ?>
<tr>
    <td><?= $row['id'] ?></td>
    <td><?= htmlspecialchars($row['name']) ?></td>
    <td><?= htmlspecialchars($row['email']) ?></td>
    <td><?= htmlspecialchars($row['status']) ?></td>
    <td>
        <a class="btn btn-edit" href="save_progress.php?client_id=<?= $row['id'] ?>">Edit Progress</a>
        
        <a class="btn btn-delete" 
        href="?action=delete&id=<?= $row['id'] ?>"
        onclick="return confirm('Apakah Anda yakin ingin menghapus klien ID <?= $row['id'] ?> dan semua data terkait?')"
        >Hapus</a>
    </td>
</tr>
<?php endwhile; ?>
</table>
</body>
</html>