<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

$client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($client_id > 0) {
    // PENTING: Hapus dulu semua progress terkait klien (karena foreign key)
    $conn->query("DELETE FROM progress WHERE client_id = $client_id");

    // Hapus klien
    $conn->query("DELETE FROM clients WHERE id = $client_id");

    header("Location: admin_dashboard.php");
    exit();
} else {
    die("ID Klien tidak valid.");
}
?>