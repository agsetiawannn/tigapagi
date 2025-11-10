<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';

// Cek Sesi Admin
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

$progress_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error_msg = "";
$success_msg = "";

// 1. LOGIKA UPDATE (saat form disubmit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_progress'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    $sql = "UPDATE progress 
            SET title = '$title', description = '$description', updated_at = NOW() 
            WHERE id = $progress_id";

    if ($conn->query($sql)) {
        $success_msg = "Proyek berhasil diperbarui! <a href='admin_dashboard.php'>Kembali ke Dashboard</a>";
        // Ambil ulang data yang sudah di-update agar form menampilkan data terbaru
    } else {
        $error_msg = "Gagal memperbarui proyek: " . $conn->error;
    }
}

// 2. AMBIL DATA PROYEK (untuk mengisi form)
if ($progress_id > 0) {
    $result = $conn->query("
        SELECT p.*, c.name AS client_name 
        FROM progress p 
        JOIN clients c ON p.client_id = c.id 
        WHERE p.id = $progress_id
    ");

    if ($result && $result->num_rows === 1) {
        $progress = $result->fetch_assoc();
    } else {
        die("Proyek tidak ditemukan.");
    }
} else {
    die("ID Proyek tidak valid.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Proyek: <?= htmlspecialchars($progress['title']) ?></title>
    <style>body { font-family: sans-serif; margin: 20px; }</style>
</head>
<body>

<h2>Edit Proyek: <?= htmlspecialchars($progress['title']) ?></h2>

<?php if (!empty($error_msg)) echo "<p style='color:red;'>$error_msg</p>"; ?>
<?php if (!empty($success_msg)) echo "<p style='color:green;'>$success_msg</p>"; ?>

<p>Klien: <b><?= htmlspecialchars($progress['client_name']) ?></b></p>
<hr>

<form method="post">
    <label>Judul Proyek:</label><br>
    <input type="text" name="title" value="<?= htmlspecialchars($progress['title']) ?>" required style="width: 300px;"><br><br>
    
    <label>Deskripsi Proyek:</label><br>
    <textarea name="description" rows="5" cols="50" required><?= htmlspecialchars($progress['description']) ?></textarea><br><br>
    
    <button type="submit" name="update_progress">Simpan Perubahan</button>
</form>

<p><a href="admin_dashboard.php">← Kembali ke Dashboard</a></p>

</body>
</html>