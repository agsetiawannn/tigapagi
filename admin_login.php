<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // PENTING: Cek dulu koneksi
    if ($conn->connect_error) {
        die("Koneksi ke db.php GAGAL: " . $conn->connect_error);
    }

    // Kode ini sudah benar
    $result = $conn->query("SELECT * FROM admin WHERE username='$username' AND password=MD5('$password')");
    
    if ($result && $result->num_rows === 1) {
        $_SESSION['admin'] = $username;
        header('Location: admin_dashboard.php');
        exit();
    } else {
        // Tampilkan juga error SQL jika query-nya yang salah
        if(!$result) {
            $error = "Error Query: " . $conn->error;
        } else {
            $error = "Username atau password salah.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Login Admin</title></head>
<body>
<h2>Admin Login</h2>
<form method="post">
    <label>Username:</label><br>
    <input type="text" name="username" required><br>
    <label>Password:</label><br>
    <input type="password" name="password" required><br><br>
    <button type="submit">Login</button>
</form>
<?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
</body>
</html>