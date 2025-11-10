<?php
session_start();
// Hapus atau beri komentar pada baris di bawah ini setelah testing selesai untuk alasan keamanan.
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';

// 1. Validasi Admin
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

$error_msg = "";
$success_msg = "";

// LOGIKA: Hapus Proyek
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    // Pastikan ID tidak nol
    if ($id > 0) {
        $conn->query("DELETE FROM progress WHERE id=$id");
        $success_msg = "Proyek berhasil dihapus!";
    }
    header("Location: admin_dashboard.php");
    exit();
}

// LOGIKA: Menambah Klien Baru
if (isset($_POST['add_client'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $company = mysqli_real_escape_string($conn, $_POST['company']);
    
    $check = $conn->query("SELECT id FROM clients WHERE email='$email'");
    if ($check && $check->num_rows > 0) {
        $error_msg = "Email '$email' sudah terdaftar. Gunakan email lain.";
    } else {
        $sql = "INSERT INTO clients (name, email, company, status) VALUES ('$name', '$email', '$company', 'active')";
        if ($conn->query($sql)) {
            $success_msg = "Klien '$name' berhasil ditambahkan. Silakan tambahkan proyek di bawah.";
        } else {
            $error_msg = "Gagal menambahkan klien: " . $conn->error;
        }
    }
    // Redirect setelah penambahan
    header("Location: admin_dashboard.php");
    exit();
}

// LOGIKA: Menambah Proyek Baru (DIKEMBALIKAN)
if (isset($_POST['add_progress'])) {
    $client_id = intval($_POST['client_id']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $status = mysqli_real_escape_string($conn, $_POST['status']); 
    
    if ($client_id > 0) {
        $sql = "INSERT INTO progress (client_id, title, description, status) 
                VALUES ($client_id, '$title', '$description', '$status')";
        
        if (!$conn->query($sql)) {
             $error_msg = "Gagal menambahkan proyek: " . $conn->error;
        }
    }
    header("Location: admin_dashboard.php");
    exit();
}


// LOGIKA: Update Status Progress
if (isset($_POST['update_status'])) {
    $id = intval($_POST['progress_id']);
    $new_status = $_POST['status'];
    
    $allowed_statuses = ['pending', 'ongoing', 'completed'];
    if (in_array($new_status, $allowed_statuses)) {
        $conn->query("UPDATE progress SET status='$new_status', updated_at=NOW() WHERE id=$id");
        header("Location: admin_dashboard.php");
        exit();
    }
}

// Mengambil data untuk tabel clients dan clients dropdown
$clients_list = $conn->query("SELECT id, name, email, company, status FROM clients ORDER BY name ASC");
$clients_dropdown = $conn->query("SELECT id, name, company FROM clients WHERE status='active' ORDER BY name ASC");

// Mengambil data untuk tabel progress
$progress_list = $conn->query("
    SELECT p.id, c.name AS client_name, p.title, p.description, p.status
    FROM progress p
    JOIN clients c ON p.client_id = c.id
    ORDER BY p.created_at DESC
");

?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        form { margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #eee; border-radius: 5px; }
        input[type="text"], input[type="email"], select, textarea { padding: 5px; margin-bottom: 10px; }
        button { padding: 8px 12px; background-color: #007bff; color: white; border: none; cursor: pointer; border-radius: 3px; }
        button:hover { background-color: #0056b3; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>

<h2>Admin Dashboard</h2>
<p>Selamat datang, <b><?= htmlspecialchars($_SESSION['admin']) ?></b> | <a href="logout.php">Logout</a></p>

<?php if (!empty($error_msg)) echo "<p class='error'>$error_msg</p>"; ?>
<?php if (!empty($success_msg)) echo "<p class='success'>$success_msg</p>"; ?>

<hr>
<h3>Tambah Klien Baru</h3>
<form method="post">
    <label>Nama Klien:</label><br>
    <input type="text" name="name" required><br>
    
    <label>Email Klien:</label><br>
    <input type="email" name="email" required><br>
    
    <label>Perusahaan:</label><br>
    <input type="text" name="company"><br><br>
    
    <button type="submit" name="add_client">Tambah Klien</button>
</form>

<hr>
<h3>Tambah Proyek Baru</h3>
<form method="post">
    <label>Pilih Klien:</label><br>
    <select name="client_id" required>
        <option value="">-- Pilih Klien --</option>
        <?php while($c = $clients_dropdown->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>">
                <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['company']) ?>)
            </option>
        <?php endwhile; ?>
    </select><br>
    
    <label>Judul Proyek:</label><br>
    <input type="text" name="title" required><br>
    
    <label>Deskripsi Proyek:</label><br>
    <textarea name="description" rows="3" cols="50" required></textarea><br>
    
    <label>Status Awal:</label><br>
    <select name="status">
        <option value="pending">Pending</option>
        <option value="ongoing">Ongoing</option>
        <option value="completed">Completed</option>
    </select><br><br>
    
    <button type="submit" name="add_progress">Tambah Proyek</button>
</form>

<hr>
<h3>Daftar Klien Aktif</h3>
<table border="1" cellpadding="6">
    <tr>
        <th>ID</th>
        <th>Nama Klien</th>
        <th>Email</th>
        <th>Perusahaan</th>
        <th>Status</th>
        <th>Action</th> </tr>
    <?php while ($client = $clients_list->fetch_assoc()): ?>
    <tr>
        <td><?= $client['id'] ?></td>
        <td><?= htmlspecialchars($client['name']) ?></td>
        <td><?= htmlspecialchars($client['email']) ?></td>
        <td><?= htmlspecialchars($client['company']) ?></td>
        <td><?= htmlspecialchars($client['status']) ?></td>
        <td>
            <a href="edit_client.php?id=<?= $client['id'] ?>">Edit</a> | 
            <a href="delete_client.php?id=<?= $client['id'] ?>" onclick="return confirm('PERINGATAN! Menghapus klien juga akan menghapus SEMUA progressnya. Lanjutkan?');">Hapus</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<hr>
<h3>Kelola Progress Klien</h3>
<table border="1" cellpadding="6">
    <tr>
        <th>Client</th>
        <th>Title</th>
        <th>Description</th>
        <th>Status</th>
        <th>Update</th>
        <th>Action</th> 
    </tr>
    <?php if ($progress_list && $progress_list->num_rows > 0): ?>
        <?php while ($row = $progress_list->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['client_name']) ?></td>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['description']) ?></td>
            <td>
                <form method="post" style="margin:0; padding:0; background:none; border:none;">
                    <input type="hidden" name="progress_id" value="<?= $row['id'] ?>">
                    <select name="status">
                        <option value="pending" <?= $row['status']=='pending'?'selected':'' ?>>Pending</option>
                        <option value="ongoing" <?= $row['status']=='ongoing'?'selected':'' ?>>Ongoing</option>
                        <option value="completed" <?= $row['status']=='completed'?'selected':'' ?>>Completed</option>
                    </select>
            </td>
            <td>
                    <button type="submit" name="update_status">Update</button>
                </form>
            </td>
            <td>
                <a href="edit_progress.php?id=<?= $row['id'] ?>">Edit</a> | 
                <a href="admin_dashboard.php?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Yakin ingin menghapus proyek ini?');">Hapus</a>
            </td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="6">Belum ada data progress.</td></tr>
    <?php endif; ?>
</table>

</body>
</html>