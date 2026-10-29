<?php
/**
 * Dashboard / Halaman Utama
 * Menampilkan ringkasan stok produk dan penjualan hari ini
 */

require_once 'config.php';
check_login();

// Ambil total produk
$query_total_products = "SELECT COUNT(*) as total FROM products";
// UBAH: pg_query -> mysqli_query
$result_total_products = mysqli_query($conn, $query_total_products);
// UBAH: pg_fetch_assoc -> mysqli_fetch_assoc
$total_products = mysqli_fetch_assoc($result_total_products)['total'];

// Ambil total stok
$query_total_stock = "SELECT SUM(stock) as total FROM products";
$result_total_stock = mysqli_query($conn, $query_total_stock);
$total_stock = mysqli_fetch_assoc($result_total_stock)['total'] ?: 0;

// Ambil penjualan hari ini
// UBAH: CURRENT_DATE -> CURDATE() (Agar lebih standar MySQL)
$query_today_sales = "SELECT COUNT(*) as total, COALESCE(SUM(total_price), 0) as revenue 
                      FROM sales 
                      WHERE DATE(sale_date) = CURDATE()";
$result_today_sales = mysqli_query($conn, $query_today_sales);
$today_sales = mysqli_fetch_assoc($result_today_sales);

// Ambil produk dengan stok rendah (kurang dari 10)
$query_low_stock = "SELECT * FROM products WHERE stock < 10 ORDER BY stock ASC LIMIT 5";
$result_low_stock = mysqli_query($conn, $query_low_stock);

// Ambil penjualan terbaru (5 transaksi terakhir)
$query_recent_sales = "SELECT * FROM sales ORDER BY sale_date DESC LIMIT 5";
$result_recent_sales = mysqli_query($conn, $query_recent_sales);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Outlet Akang Kebab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-orange">
        <div class="container-fluid">
            <a class="navbar-brand text-white fw-bold" href="index.php">🥙 Outlet Akang Kebab</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link text-white" href="index.php">Dashboard</a></li>
                    
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                    <li class="nav-item"><a class="nav-link text-white" href="inventory.php">Inventory</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="suppliers.php">Supplier</a></li>
                    <?php endif; ?>
                    
                    <li class="nav-item"><a class="nav-link text-white" href="pos.php">POS</a></li>
                    
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                    <li class="nav-item"><a class="nav-link text-white" href="sales_report.php">Laporan</a></li>
                    <?php endif; ?>
                    
                    <li class="nav-item"><a class="nav-link text-white" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-12">
                <h3>Selamat Datang, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h3>
                <p class="text-muted">Dashboard Ringkasan Outlet Akang Kebab</p>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card card-stat card-orange">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Produk</h5>
                        <h2 class="fw-bold"><?php echo $total_products; ?></h2>
                        <p class="small mb-0">Jenis produk</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stat card-green">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Stok</h5>
                        <h2 class="fw-bold"><?php echo $total_stock; ?></h2>
                        <p class="small mb-0">Unit tersedia</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stat card-blue">
                    <div class="card-body text-center">
                        <h5 class="card-title">Penjualan Hari Ini</h5>
                        <h2 class="fw-bold"><?php echo $today_sales['total']; ?></h2>
                        <p class="small mb-0">Transaksi</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stat card-success">
                    <div class="card-body text-center">
                        <h5 class="card-title">Pendapatan Hari Ini</h5>
                        <h2 class="fw-bold">Rp <?php echo number_format($today_sales['revenue'], 0, ',', '.'); ?></h2>
                        <p class="small mb-0">Total pendapatan</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">⚠️ Produk Stok Rendah</h5>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($result_low_stock) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Nama Produk</th>
                                            <th>Kategori</th>
                                            <th class="text-end">Stok</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($product = mysqli_fetch_assoc($result_low_stock)): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td><?php echo htmlspecialchars($product['category']); ?></td>
                                                <td class="text-end">
                                                    <span class="badge bg-warning text-dark"><?php echo $product['stock']; ?></span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">Semua produk memiliki stok yang cukup.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">📊 Penjualan Terbaru</h5>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($result_recent_sales) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Produk</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($sale = mysqli_fetch_assoc($result_recent_sales)): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                                                <td class="text-center"><?php echo $sale['quantity']; ?></td>
                                                <td class="text-end">Rp <?php echo number_format($sale['total_price'], 0, ',', '.'); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">Belum ada penjualan hari ini.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require_once 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- JAVASCRIPT FIX: ANTI BACK BUTTON -->
<script>
// Memaksa reload halaman jika diakses melalui Back/Forward Cache browser
window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        // Jika halaman dimuat dari cache memori, paksa reload penuh
        window.location.reload();
    }
});
</script>
</body>
</html>