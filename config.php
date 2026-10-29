<?php
/**
 * File Konfigurasi Database
 * Menghubungkan aplikasi dengan MySQL database
 * UPDATE: Menambahkan header anti-cache agar tombol back setelah logout tidak bisa digunakan.
 * MODIFIKASI: Menambahkan fungsi check_access() untuk kontrol level user (admin/kasir).
 */

// ============================================================================
// 1. HEADER ANTI-CACHE (PENTING UNTUK FITUR LOGOUT AMAN)
// ============================================================================
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Mulai session untuk semua halaman
session_start();

// ============================================================================
// 2. KONEKSI DATABASE
// ============================================================================

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$dbname = getenv('DB_NAME') ?: 'db_akang_kebab';
$port = getenv('DB_PORT') ?: '3306';

$conn = mysqli_connect($host, $user, $password, $dbname, $port);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8");

/**
 * Fungsi untuk melindungi dari SQL Injection
 */
function escape_string($conn, $string) {
    return mysqli_real_escape_string($conn, $string);
}

/**
 * Fungsi untuk redirect halaman
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Fungsi untuk cek apakah user sudah login.
 * Tambahan: Memuat data role user ke session saat dipanggil (jika belum ada).
 */
function check_login() {
    global $conn;
    
    // Cek apakah user sudah login
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
    
    // Cek dan muat role jika belum ada di session (misal setelah login)
    if (!isset($_SESSION['role'])) {
        $user_id = intval($_SESSION['user_id']);
        $query = "SELECT role FROM users WHERE id = $user_id";
        $result = mysqli_query($conn, $query);
        
        if ($result && $user = mysqli_fetch_assoc($result)) {
            $_SESSION['role'] = $user['role'];
        } else {
            // Jika ID tidak valid, paksa logout
            redirect('logout.php'); 
        }
    }
}

/**
 *@param array $allowed_roles Array of roles (e.g., ['admin', 'kasir'])
 */
function check_access(array $allowed_roles) {
    check_login(); // Pastikan sudah login dan role sudah dimuat
    
    $current_role = $_SESSION['role'] ?? 'guest'; // Default ke guest jika tidak ada
    
    if (!in_array($current_role, $allowed_roles)) {
        // Jika peran tidak diizinkan, arahkan ke halaman utama atau error
        
        // Kasir hanya boleh di POS, jadi admin hanya boleh di dashboard
        if ($current_role == 'kasir') {
             redirect('pos.php'); // Arahkan kasir ke halaman yang diizinkan
        } else {
             redirect('index.php'); // Arahkan lainnya ke dashboard
        }
       
    }
}
?>