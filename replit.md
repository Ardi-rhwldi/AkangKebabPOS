# Outlet Akang Kebab - POS & Inventory System

## 📋 Overview
Aplikasi Point of Sale (POS) dan Inventory Management untuk Outlet Akang Kebab. Dibuat menggunakan PHP native dan PostgreSQL database dengan tema warna orange dan putih.

## 🎯 Fitur Utama
- **Login/Logout Admin**: Autentikasi sederhana menggunakan session PHP
- **Dashboard**: Ringkasan stok produk, penjualan hari ini, dan statistik
- **Inventory Management**: CRUD produk (Create, Read, Update, Delete)
- **Point of Sale**: Sistem kasir dengan keranjang belanja dan proses pembayaran
- **Laporan Penjualan**: Daftar transaksi dengan filter tanggal

## 🛠 Teknologi
- **Backend**: PHP 8.2 (Native, no framework)
- **Database**: PostgreSQL 16
- **Frontend**: Bootstrap 5, CSS Custom
- **Server**: PHP Built-in Development Server

## 📂 Struktur File
```
├── config.php           # Konfigurasi database & helper functions
├── login.php            # Halaman login
├── logout.php           # Proses logout
├── index.php            # Dashboard utama
├── inventory.php        # Halaman inventory management
├── pos.php              # Halaman Point of Sale
├── sales_report.php     # Halaman laporan penjualan
└── style.css            # Custom CSS tema orange-putih
```

## 🗄 Database Schema
### Tabel `users`
- id (SERIAL PRIMARY KEY)
- username (VARCHAR)
- password (VARCHAR)
- created_at (TIMESTAMP)

### Tabel `products`
- id (SERIAL PRIMARY KEY)
- name (VARCHAR)
- price (DECIMAL)
- stock (INT)
- category (VARCHAR)
- created_at (TIMESTAMP)

### Tabel `sales`
- id (SERIAL PRIMARY KEY)
- product_id (INT)
- product_name (VARCHAR)
- quantity (INT)
- price (DECIMAL)
- total_price (DECIMAL)
- sale_date (TIMESTAMP)

## 🔐 Default Login
- **Username**: admin
- **Password**: admin123

## 🎨 Tema Warna
- **Primary**: Orange (#FFA500)
- **Secondary**: Orange Gelap (#FF8C00)
- **Accent**: Hijau (#32CD32)
- **Background**: Putih (#FFFFFF)

## 🚀 Cara Menjalankan
Aplikasi sudah dikonfigurasi dengan workflow "PHP Web Server" yang otomatis menjalankan server PHP di port 5000. Klik tombol Run atau akses melalui Webview.

## 📊 Recent Changes
- [19 Nov 2025] Initial creation - Semua fitur dasar POS dan inventory
- [19 Nov 2025] Setup database PostgreSQL dengan sample data
- [19 Nov 2025] Implementasi login system dengan session
- [19 Nov 2025] UI/UX dengan tema orange-putih menggunakan Bootstrap 5

## 🔒 Keamanan
- Menggunakan `pg_escape_string()` untuk mencegah SQL injection
- Session-based authentication untuk admin
- Input validation pada semua form

## 📝 Catatan
- Aplikasi ini menggunakan tingkat kompleksitas menengah (tidak terlalu rumit)
- Fokus pada fungsionalitas dasar tanpa fitur advanced seperti AJAX atau framework modern
- Responsive design untuk mobile dan desktop
- Kode dengan komentar lengkap untuk kemudahan pemahaman
