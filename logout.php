<?php
/**
 * Proses Logout
 * Menghapus session dan redirect ke login
 */

session_start();

// Hapus semua session
session_destroy();

// Redirect ke halaman login
header("Location: login.php");
exit();
?>
