<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // ambil data client aktif berdasarkan email
    $sql = "SELECT * FROM clients WHERE email='$email' AND status='active'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows == 1) {
        $client = $result->fetch_assoc();

        // simpan session
        $_SESSION['client_id'] = $client['id'];
        $_SESSION['client_name'] = $client['name'];

        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Email tidak terdaftar atau akun tidak aktif.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login Client</title>
</head>
<body>
<h2>Client Login</h2>
<form method="post">
    <label>Email:</label><br>
    <input type="email" name="email" required><br><br>
    <button type="submit">Login</button>
</form>
<?php if (!empty($error)) echo "<p>$error</p>"; ?>
</body>
</html>
