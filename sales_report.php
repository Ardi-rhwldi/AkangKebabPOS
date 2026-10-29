<?php

require_once 'config.php';
check_access(['admin']); 

$message = '';

// Set tanggal default (hari ini)
$start_date = isset($_GET['start_date']) ? escape_string($conn, $_GET['start_date']) : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? escape_string($conn, $_GET['end_date']) : date('Y-m-d');

// Query untuk mengambil data penjualan
$query_sales = "SELECT * FROM sales 
                WHERE DATE(sale_date) BETWEEN '$start_date' AND '$end_date' 
                ORDER BY sale_date DESC";
$result_sales = mysqli_query($conn, $query_sales);

// Hitung total penjualan
$query_total = "SELECT COUNT(*) as total_transactions, COALESCE(SUM(total_price), 0) as total_revenue 
                FROM sales 
                WHERE DATE(sale_date) BETWEEN '$start_date' AND '$end_date'";
$result_total = mysqli_query($conn, $query_total);
$total_data = mysqli_fetch_assoc($result_total);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan - Outlet Akang Kebab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --orange-color: #ff8c00; 
            --light-orange-color: #ffa500; 
            --blue-color: #129bbdff;
            --success-color: #129bbdff;
            --white: #FFFFFF; 
        }
        body {
            background-color: #f8f9fa;
        }
        .navbar-orange {
            background-color: var(--orange-color);
        }
        .btn-orange, .card-header.bg-orange {
            background-color: var(--orange-color);
            border-color: var(--orange-color);
            color: white;
        }
        .btn-orange:hover {
            background-color: #e67e22; 
            border-color: #e67e22;
            color: white;
        }
        .text-orange {
            color: var(--orange-color) !important;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .card-stat {
            border: none;
        }
        .card-blue {
            background-color: var(--blue-color);
            color: white;
        }
        .card-success {
            background-color: var(--success-color);
            color: white;
        }
        .btn-secondary-orange {
            background-color: #f0f0f0;
            border-color: #ccc;
            color: var(--orange-color);
        }
        .btn-secondary-orange:hover {
            background-color: #e6e6e6;
            border-color: #b3b3b3;
            color: var(--orange-color);
        }
        
        /* Definisi Style Footer */
        .footer-orange {
            /* Menggunakan variabel yang sudah didefinisikan di atas */
            background: linear-gradient(135deg, var(--light-orange-color) 0%, var(--orange-color) 100%);
            box-shadow: 0 -2px 4px rgba(0,0,0,0.1);
            color: var(--white);
        }

        .footer-orange a {
            color: var(--white);
            opacity: 0.8;
            transition: all 0.3s ease;
        }

        .footer-orange a:hover {
            color: var(--white);
            opacity: 1;
        }

        /* --- PRINT STYLES --- */
        @media print {
            /* Sembunyikan navigasi, filter, dan tombol cetak */
            .navbar, .filter-section, .no-print, .footer-orange {
                display: none !important;
            }
            /* Pastikan konten utama muncul di bagian atas halaman */
            .container.mt-4 {
                margin-top: 0 !important;
                padding-top: 0 !important;
            }
            /* Ubah tata letak statistik menjadi satu kolom */
            .card-stat {
                page-break-inside: avoid; /* Hindari pemisahan kartu di halaman */
                margin-bottom: 10px;
                width: 100%;
                color: black !important;
                background-color: #f0f0f0 !important;
                border: 1px solid #ccc;
            }
            .card-blue, .card-success {
                background-color: #fff !important;
                color: black !important;
                border: 1px solid #dee2e6;
            }
            /* Judul Laporan */
            h3 {
                text-align: center;
                margin-top: 20px;
                margin-bottom: 20px;
            }
            /* Hapus bayangan kartu */
            .card {
                box-shadow: none;
                border: 1px solid #ccc;
                border-radius: 0;
            }
            /* Pastikan total terlihat jelas */
             .table-secondary {
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
                background-color: #d1d9e0 !important;
            }
            .text-orange {
                color: #000 !important;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-orange no-print">
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
        <h3 class="mb-4">📊 Laporan Penjualan</h3>

        <div class="card mb-4 filter-section">
            <div class="card-body">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Tanggal Mulai</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">Tanggal Akhir</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo $end_date; ?>" required>
                    </div>
                    <div class="col-md-3 d-grid">
                         <label class="form-label d-none d-md-block">&nbsp;</label> 
                        <button type="submit" class="btn btn-orange">🔍 Filter</button>
                    </div>
                    <div class="col-md-3 d-grid">
                         <label class="form-label d-none d-md-block">&nbsp;</label> 
                        <button type="button" class="btn btn-secondary-orange" onclick="window.print()">
                            <i class="bi bi-printer-fill"></i> Cetak Laporan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card card-stat card-blue">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Transaksi</h5>
                        <h2 class="fw-bold"><?php echo $total_data['total_transactions']; ?></h2>
                        <p class="small mb-0">Periode: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card card-stat card-success">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Pendapatan</h5>
                        <h2 class="fw-bold">Rp <?php echo number_format($total_data['total_revenue'], 0, ',', '.'); ?></h2>
                        <p class="small mb-0">Periode: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-5"> 
            <div class="card-header bg-orange text-white">
                <h5 class="mb-0">Detail Transaksi</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tanggal & Waktu</th>
                                <th>Produk</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Harga Satuan</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result_sales) > 0): ?>
                                <?php while ($sale = mysqli_fetch_assoc($result_sales)): ?>
                                    <tr>
                                        <td><?php echo $sale['id']; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($sale['sale_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                                        <td class="text-center"><?php echo $sale['quantity']; ?></td>
                                        <td class="text-end">Rp <?php echo number_format($sale['price'], 0, ',', '.'); ?></td>
                                        <td class="text-end fw-bold">Rp <?php echo number_format($sale['total_price'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr class="table-secondary fw-bold">
                                    <td colspan="5" class="text-end">TOTAL KESELURUHAN:</td>
                                    <td class="text-end text-orange">Rp <?php echo number_format($total_data['total_revenue'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Tidak ada transaksi pada periode ini</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div> <footer class="footer-orange py-3 mt-4 no-print">
        <div class="container text-center">
            <span class="text-white small me-3">
                &copy; <?php echo date('Y'); ?> Outlet Akang Kebab.
            </span>
            <a href="#" target="_blank" class="text-white mx-2" title="Instagram"><i class="bi bi-instagram"></i></a>
            <a href="#" target="_blank" class="text-white mx-2" title="GitHub"><i class="bi bi-github"></i></a>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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