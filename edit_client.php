<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

$client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error_msg = "";
$success_msg = "";

// LOGIKA UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_client'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $company = mysqli_real_escape_string($conn, $_POST['company']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $sql = "UPDATE clients 
            SET name = '$name', email = '$email', company = '$company', status = '$status'
            WHERE id = $client_id";

    if ($conn->query($sql)) {
        $success_msg = "Detail klien berhasil diperbarui! <a href='admin_dashboard.php'>Kembali</a>";
    } else {
        $error_msg = "Gagal memperbarui: " . $conn->error;
    }
}

// AMBIL DATA KLIEN (untuk mengisi form)
if ($client_id > 0) {
    $result = $conn->query("SELECT * FROM clients WHERE id = $client_id");
    if ($result && $result->num_rows === 1) {
        $client = $result->fetch_assoc();
    } else {
        die("Klien tidak ditemukan.");
    }
} else {
    die("ID Klien tidak valid.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Klien: <?= htmlspecialchars($client['name']) ?></title>
    <style>body { font-family: sans-serif; margin: 20px; }</style>
</head>
<body>

<h2>Edit Detail Klien: <?= htmlspecialchars($client['name']) ?></h2>

<?php if (!empty($error_msg)) echo "<p style='color:red;'>$error_msg</p>"; ?>
<?php if (!empty($success_msg)) echo "<p style='color:green;'>$success_msg</p>"; ?>

<form method="post">
    <label>Nama Klien:</label><br>
    <input type="text" name="name" value="<?= htmlspecialchars($client['name']) ?>" required style="width: 300px;"><br><br>
    
    <label>Email Klien:</label><br>
    <input type="email" name="email" value="<?= htmlspecialchars($client['email']) ?>" required style="width: 300px;"><br><br>
    
    <label>Perusahaan:</label><br>
    <input type="text" name="company" value="<?= htmlspecialchars($client['company']) ?>" style="width: 300px;"><br><br>

    <label>Status Akun:</label><br>
    <select name="status">
        <option value="active" <?= $client['status'] == 'active' ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $client['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select><br><br>
    
    <button type="submit" name="update_client">Simpan Perubahan</button>
</form>

<p><a href="admin_dashboard.php">← Kembali ke Dashboard</a></p>

</body>
</html>