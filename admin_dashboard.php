<?php
session_start();
include 'db.php';

// Validasi admin login
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

$error_msg = "";
$success_msg = "";

// === Tambah Klien Baru ===
if (isset($_POST['add_client'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $company = mysqli_real_escape_string($conn, $_POST['company']);
    
    $exists = $conn->query("SELECT id FROM clients WHERE email='$email'");
    if ($exists->num_rows > 0) {
        $error_msg = "Email sudah digunakan klien lain.";
    } else {
        // 🔥 Perbaikan Keamanan: Gunakan Prepared Statement untuk INSERT (opsional tapi disarankan)
        $conn->query("INSERT INTO clients (name, email, company, status) VALUES ('$name', '$email', '$company', 'active')");
        $success_msg = "Klien berhasil ditambahkan.";
    }
}

// === Tambah Proyek Baru ===
if (isset($_POST['add_progress'])) {
    $client_id = intval($_POST['client_id']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    if ($client_id > 0) {
        // 🔥 Perbaikan Keamanan: Gunakan Prepared Statement untuk INSERT (opsional tapi disarankan)
        $conn->query("INSERT INTO progress (client_id, title, description, status) VALUES ($client_id, '$title', '$description', '$status')");
        $success_msg = "Proyek berhasil ditambahkan.";
    } else {
        $error_msg = "Klien belum dipilih.";
    }
}

// === HAPUS KLIEN BARU ===
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $delete_id = intval($_GET['id']);
    
    if ($delete_id > 0) {
        // 🚨 Peringatan: Hapus data terkait di tabel lain (progress & client_progress)
        $conn->query("DELETE FROM progress WHERE client_id = $delete_id");
        $conn->query("DELETE FROM client_progress WHERE client_id = $delete_id");
        
        // Hapus klien utama
        $conn->query("DELETE FROM clients WHERE id = $delete_id");
        
        $success_msg = "Klien (ID: $delete_id) dan semua data proyek terkait berhasil dihapus.";
        
        // Redirect untuk membersihkan parameter GET dari URL
        header("Location: admin_dashboard.php");
        exit();
    } else {
        $error_msg = "ID Klien tidak valid untuk dihapus.";
    }
}


// === Ambil Data ===
$clients = $conn->query("SELECT id, name, email, company, status FROM clients ORDER BY name ASC");
$active_clients = $conn->query("SELECT id, name, company FROM clients WHERE status='active' ORDER BY name ASC");
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
    <input type="text" name="company" placeholder="Perusahaan">
    <button type="submit" name="add_client">Tambah Klien</button>
</form>

<h3>Tambah Proyek Baru</h3>
<form method="post">
    <select name="client_id" required>
        <option value="">-- Pilih Klien --</option>
        <?php while ($c = $active_clients->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['company']) ?>)</option>
        <?php endwhile; ?>
    </select>
    <input type="text" name="title" placeholder="Judul Proyek" required>
    <textarea name="description" placeholder="Deskripsi Proyek" rows="3" required></textarea>
    <select name="status">
        <option value="pending">Pending</option>
        <option value="ongoing">Ongoing</option>
        <option value="completed">Completed</option>
    </select>
    <button type="submit" name="add_progress">Tambah Proyek</button>
</form>

<h3>Daftar Klien</h3>
<table>
<tr>
    <th>ID</th>
    <th>Nama</th>
    <th>Email</th>
    <th>Perusahaan</th>
    <th>Status</th>
    <th>Aksi</th>
</tr>
<?php while ($row = $clients->fetch_assoc()): ?>
<tr>
    <td><?= $row['id'] ?></td>
    <td><?= htmlspecialchars($row['name']) ?></td>
    <td><?= htmlspecialchars($row['email']) ?></td>
    <td><?= htmlspecialchars($row['company']) ?></td>
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