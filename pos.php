<?php

require_once 'config.php';
check_access(['admin', 'kasir']); 

$message = '';
$message_type = '';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}

// Logika CRUD Keranjang dan Pembayaran
if (isset($_POST['add_to_cart'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    
    // Pastikan kuantitas lebih dari 0
    if ($quantity < 1) {
        $message = 'Kuantitas harus minimal 1.';
        $message_type = 'danger';
    } else {
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = $quantity;
        }
        
        // Cek apakah ada data pencarian, jika ada, hilangkan agar produk terlihat
        if (isset($_GET['search'])) {
            header("Location: pos.php?search=" . urlencode($_GET['search']));
            exit;
        }
        $message = 'Produk ditambahkan ke keranjang!';
        $message_type = 'success';
    }
}

if (isset($_GET['remove'])) {
    $product_id = intval($_GET['remove']);
    unset($_SESSION['cart'][$product_id]);
    $message = 'Item dihapus dari keranjang!';
    $message_type = 'info';
}

if (isset($_POST['process_payment'])) {
    if (count($_SESSION['cart']) > 0) {
        
        mysqli_begin_transaction($conn);
        $success = true;
        
        $payment_method = escape_string($conn, $_POST['payment_method']); 
        $pay_amount = floatval($_POST['pay_amount']); 
        $change_amount = floatval($_POST['change_amount']); 
        
        $current_cart_total = 0;
        
        $user_id = $_SESSION['user_id'] ?? 1; 
        
        // Ambil data produk di keranjang untuk menghitung total
        $product_ids_safe = array_map(function($id) use ($conn) { return intval($id); }, array_keys($_SESSION['cart']));
        $product_ids = implode(',', $product_ids_safe);
        
        if (!empty($product_ids)) {
            $query_cart = "SELECT id, name, price, stock FROM products WHERE id IN ($product_ids)";
            $result_cart = mysqli_query($conn, $query_cart);
            $products_in_cart = [];

            while ($product = mysqli_fetch_assoc($result_cart)) {
                $quantity = $_SESSION['cart'][$product['id']];
                $subtotal = $product['price'] * $quantity;
                $current_cart_total += $subtotal;
                $products_in_cart[$product['id']] = array_merge($product, ['quantity' => $quantity, 'subtotal' => $subtotal]);
            }

            // Insert ke transactions
            $query_transaction = "INSERT INTO transactions (user_id, transaction_date, total_amount, payment_method, pay_amount, change_amount)
                                  VALUES ($user_id, NOW(), $current_cart_total, '$payment_method', $pay_amount, $change_amount)";

            if (mysqli_query($conn, $query_transaction)) {
                $transaction_id = mysqli_insert_id($conn);
                $receipt_items = array(); 
                
                // Simpan Detail Penjualan dan Update Stok
                foreach ($products_in_cart as $product_id => $product_data) {
                    $quantity = $product_data['quantity'];
                    $product = $product_data;
                    
                    if ($product['stock'] >= $quantity) {
                        $total_price = $product['price'] * $quantity;
                        $p_name = escape_string($conn, $product['name']);
                        
                        // Simpan transaksi penjualan (Kini dihubungkan dengan transaction_id)
                        $query_sale = "INSERT INTO sales (transaction_id, product_id, product_name, quantity, price, total_price) 
                                       VALUES ($transaction_id, $product_id, '$p_name', $quantity, {$product['price']}, $total_price)";
                        
                        if (mysqli_query($conn, $query_sale)) {
                            // Update stok produk
                            $new_stock = $product['stock'] - $quantity;
                            $query_update = "UPDATE products SET stock=$new_stock WHERE id=$product_id";
                            
                            if (!mysqli_query($conn, $query_update)) {
                                $success = false;
                                $message = 'Gagal update stok produk ' . htmlspecialchars($product['name']) . '!';
                                $message_type = 'danger';
                                break; 
                            }
                            
                            // SIMPAN DATA UNTUK STRUK
                            $receipt_items[] = array(
                                'name' => htmlspecialchars($product['name']),
                                'price' => $product['price'],
                                'quantity' => $quantity,
                                'subtotal' => $total_price
                            );
                            
                        } else {
                            $success = false;
                            $message = 'Gagal menyimpan data penjualan produk ' . htmlspecialchars($product['name']) . '!';
                            $message_type = 'danger';
                            break;
                        }
                    } else {
                        $success = false;
                        $message = 'Stok produk ' . htmlspecialchars($product['name']) . ' tidak mencukupi atau produk tidak ditemukan!';
                        $message_type = 'danger';
                        break; 
                    }
                }
                
            } else {
                $success = false;
                $message = 'Gagal menyimpan data transaksi utama!';
                $message_type = 'danger';
            }
        }
        
        if ($success) {
            mysqli_commit($conn); // Commit jika semua berhasil
            
            // SIMPAN DATA STRUK KE SESSION
            $_SESSION['receipt_data'] = array(
                'transaction_id' => $transaction_id,
                'items' => $receipt_items,
                'total_amount' => $current_cart_total,
                'payment_method' => $payment_method,
                'pay_amount' => $pay_amount,
                'change_amount' => $change_amount,
                'date' => date('Y-m-d H:i:s')
            );
            
            $_SESSION['cart'] = array();
            
            // Redirect untuk membersihkan POST dan menampilkan struk
            header("Location: pos.php?success=1");
            exit;
            
        } else {
            mysqli_rollback($conn); // Rollback jika ada yang gagal
        }
    } else {
        $message = 'Keranjang kosong!';
        $message_type = 'warning';
    }
}

// Handle sukses dari redirect
if (isset($_GET['success']) && !isset($message)) {
    // Pesan sukses akan ditampilkan melalui JS struk, jadi kita hanya bersihkan GET
    $message = "Pembayaran Berhasil!";
    $message_type = 'success';
}

// ----------------------------------------------------
// Logika Pencarian dan Pengambilan Data Produk
// ----------------------------------------------------

$search_query = '';
$search_sql = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_query = trim($_GET['search']);
    $safe_query = escape_string($conn, $search_query);
    // Tambahkan filter berdasarkan nama produk
    $search_sql = " AND (name LIKE '%$safe_query%' OR category LIKE '%$safe_query%')";
}

// Mengambil semua produk dengan stok > 0.
$query_products = "SELECT * FROM products WHERE stock > 0 $search_sql ORDER BY category, name";
$result_products = mysqli_query($conn, $query_products);

// ----------------------------------------------------
// Logika Perhitungan Total Keranjang
// ----------------------------------------------------

$cart_total = 0;
$cart_items = array();
if (count($_SESSION['cart']) > 0) {
    // Escape product IDs untuk query yang aman
    $product_ids_safe = array_map(function($id) use ($conn) {
        return intval($id); // Pastikan hanya integer
    }, array_keys($_SESSION['cart']));
    
    $product_ids = implode(',', $product_ids_safe);
    
    // Jika tidak ada ID, jangan query
    if (!empty($product_ids)) {
        $query_cart = "SELECT * FROM products WHERE id IN ($product_ids)";
        $result_cart = mysqli_query($conn, $query_cart);
        
        while ($product = mysqli_fetch_assoc($result_cart)) {
            $quantity = $_SESSION['cart'][$product['id']];
            $subtotal = $product['price'] * $quantity;
            $cart_total += $subtotal;
            
            $cart_items[] = array(
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'subtotal' => $subtotal
            );
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS - Outlet Akang Kebab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* Custom styles for Kebab theme */
        .navbar-orange {
            background-color: #f97316; /* Tailwind orange-500 equivalent */
        }
        .text-orange {
            color: #f97316 !important;
        }
        .btn-orange {
            background-color: #f97316;
            border-color: #f97316;
            color: white;
        }
        .btn-orange:hover {
            background-color: #ea580c; /* Slightly darker orange */
            border-color: #ea580c;
            color: white;
        }
        .product-card {
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
        }
        .product-img {
            width: 100%;
            height: 150px; /* Tinggi tetap untuk gambar */
            object-fit: cover; /* Memastikan gambar mengisi area tanpa terdistorsi */
            background-color: #f3f4f6; /* Warna latar belakang untuk placeholder */
        }
        /* Sticky header for product list and table */
        .card-header.sticky-top {
            top: 0;
            z-index: 10;
        }
        .table-responsive .sticky-top {
            position: sticky;
            top: 0;
            background-color: #fff; /* Pastikan background putih */
        }
    </style>
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
        <h3 class="mb-4">💰 Point of Sale (POS)</h3>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-7 mb-4">
                <div class="card">
                    <div class="card-header bg-orange text-white sticky-top">
                        <h5 class="mb-0 d-flex justify-content-between align-items-center">
                            Pilih Produk
                        </h5>
                        
                        <form method="GET" action="pos.php" class="mt-2">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Cari produk..." name="search" 
                                       value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn btn-light" type="submit">
                                    <i class="bi bi-search text-orange"></i>
                                </button>
                                <?php if (!empty($search_query)): ?>
                                <a href="pos.php" class="btn btn-light" title="Hapus Pencarian">
                                    <i class="bi bi-x-lg text-danger"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                        </div>
                    <div class="card-body" style="max-height: 70vh; overflow-y: auto;">
                        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
                            <?php if (mysqli_num_rows($result_products) > 0): ?>
                                <?php while ($product = mysqli_fetch_assoc($result_products)): 
                                    // Tentukan URL gambar, gunakan placeholder jika kosong
                                    $image_src = !empty($product['image_url']) 
                                        ? htmlspecialchars($product['image_url']) 
                                        : 'https://placehold.co/200x150/f97316/ffffff?text=Akang+Kebab';
                                ?>
                                    <div class="col">
                                        <div class="card product-card h-100">
                                            <img src="<?php echo $image_src; ?>" class="card-img-top product-img" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 onerror="this.onerror=null;this.src='https://placehold.co/200x150/f97316/ffffff?text=Akang+Kebab';">
                                                 
                                            <div class="card-body d-flex flex-column">
                                                <h6 class="card-title fw-bold"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                <p class="mb-1"><small class="text-muted"><?php echo htmlspecialchars($product['category']); ?></small></p>
                                                <h5 class="fw-bold text-orange mb-2">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></h5>
                                                <p class="mb-3 small">Stok: <?php echo $product['stock']; ?></p>
                                                
                                                <div class="mt-auto">
                                                    <form method="POST" action="?search=<?php echo urlencode($search_query); ?>" class="d-flex gap-2">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <input type="number" name="quantity" class="form-control form-control-sm" 
                                                               value="1" min="1" max="<?php echo $product['stock']; ?>" style="width: 70px;">
                                                        <button type="submit" name="add_to_cart" class="btn btn-sm btn-orange flex-grow-1">
                                                            <i class="bi bi-cart-plus"></i> Tambah
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="col-12"><p class="text-muted text-center">Tidak ada produk tersedia <?php echo !empty($search_query) ? "dengan nama/kategori '<b>" . htmlspecialchars($search_query) . "</b>'" : ""; ?></p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-5 mb-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">🛒 Keranjang Belanja</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($cart_items) > 0): ?>
                            <div class="table-responsive mb-3" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm align-middle">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Produk</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Subtotal</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cart_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <span class="fw-bold d-block"><?php echo htmlspecialchars($item['name']); ?></span>
                                                    <small class="text-muted">@<?php echo number_format($item['price'], 0, ',', '.'); ?></small>
                                                </td>
                                                <td class="text-center"><?php echo $item['quantity']; ?></td>
                                                <td class="text-end">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td>
                                                <td class="text-end">
                                                    <a href="?remove=<?php echo $item['id']; ?>" class="text-danger remove-item-btn" 
                                                       data-product-name="<?php echo htmlspecialchars($item['name']); ?>">
                                                        <i class="bi bi-x-circle-fill"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="border-top pt-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="mb-0 fw-bold">Total Bayar</h4>
                                    <h3 class="mb-0 fw-bold text-orange">Rp <?php echo number_format($cart_total, 0, ',', '.'); ?></h3>
                                </div>
                                
                                <button type="button" class="btn btn-success w-100 btn-lg" data-bs-toggle="modal" data-bs-target="#paymentModal">
                                    <i class="bi bi-cash-coin"></i> Proses Pembayaran
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-cart-x fs-1 text-muted"></i>
                                <p class="text-muted mt-2">Keranjang masih kosong.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-orange text-white">
                    <h5 class="modal-title fw-bold">Rincian Pembayaran</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="paymentForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Total Tagihan</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light">Rp</span>
                                <input type="text" class="form-control fw-bold text-end" value="<?php echo number_format($cart_total, 0, ',', '.'); ?>" readonly>
                                <input type="hidden" id="grandTotal" name="grand_total" value="<?php echo $cart_total; ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Metode Pembayaran</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="payment_method" id="methodCash" value="cash" checked onchange="togglePaymentMethod()">
                                    <label class="btn btn-outline-success w-100 py-3" for="methodCash">
                                        <i class="bi bi-cash fs-4 d-block"></i> TUNAI / CASH
                                    </label>
                                </div>
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="payment_method" id="methodQris" value="qris" onchange="togglePaymentMethod()">
                                    <label class="btn btn-outline-primary w-100 py-3" for="methodQris">
                                        <i class="bi bi-qr-code-scan fs-4 d-block"></i> QRIS
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 text-center p-3 bg-light rounded border" id="qrisContainer" style="display: none;">
                            <label class="form-label fw-bold d-block">Scan QRIS di Bawah Ini</label>
                            <img src="uploads/qris.jpeg" 
                                 class="img-fluid rounded shadow-sm" style="max-width: 200px; max-height: 200px;" 
                                 alt="Kode QRIS Pembayaran">
                            <p class="mt-2 small text-muted">Pastikan jumlah yang ditransfer sesuai dengan total tagihan (Rp <?php echo number_format($cart_total, 0, ',', '.'); ?>)</p>
                        </div>
                        <div id="cashInputContainer">
                            <div class="mb-3">
                                <label for="payAmount" class="form-label fw-bold">Uang Diterima (Bayar)</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control text-end fw-bold" id="payAmount" name="pay_amount" 
                                            placeholder="0" required min="0" value="<?php echo $cart_total; ?>" oninput="calculateChange()">
                                </div>
                                <div class="form-text text-end" id="shortageAlert" style="color: red; display: none;">
                                    Uang pembayaran kurang!
                                </div>
                            </div>

                            <div class="mb-4 p-3 bg-light rounded border">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-muted">Kembalian</span>
                                    <h4 class="fw-bold mb-0 text-success" id="changeText">Rp 0</h4>
                                </div>
                                <input type="hidden" name="change_amount" id="changeAmountInput" value="0">
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="process_payment" id="btnSubmitPayment" class="btn btn-orange btn-lg">
                                <i class="bi bi-check-circle-fill"></i> Bayar & Selesai
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage">Yakin ingin menghapus item ini?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <a id="confirmRemoveLink" href="#" class="btn btn-danger">Hapus</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header text-center border-0 pb-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-0" id="receiptContent">
                    <h5 class="modal-title w-100 fw-bold" style="text-align: center;">🥙 Outlet Akang Kebab</h5>
                    <p class="text-center small mb-2 border-bottom pb-2">
                        Kp. Bojongpari, Desa. Jayabakti, Kec. Cidahu, Kab. Sukabumi<br>
                        Telp: 0858-6432-7611<br>
                        <br>
                        Rincian Belanja kamu ada di bawah sini
                    </p>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Transaksi ID:</span>
                        <span id="receipt-transaction-id">#00000</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-3 border-bottom pb-3">
                        <span>Tanggal:</span>
                        <span id="receipt-date">YYYY-MM-DD HH:MM</span>
                    </div>

                    <div id="receipt-items-list" class="mb-3">
                        </div>

                    <div class="d-flex justify-content-between fw-bold border-top pt-2">
                        <span>TOTAL</span>
                        <span id="receipt-total">Rp 0</span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span>Metode Bayar (<span id="receipt-method"></span>)</span>
                        <span id="receipt-pay-amount">Rp 0</span>
                    </div>
                    <div class="d-flex justify-content-between small border-bottom pb-2 mb-3">
                        <span>Kembalian</span>
                        <span id="receipt-change">Rp 0</span>
                    </div>

                    <p class="text-center small mt-3">
                        Terima kasih sudah jajan disini, Jangan Lupa Balik lagi ke Outlet Akang Kebab yaa
                    </p>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printReceipt()">
                            <i class="bi bi-printer"></i> Cetak Struk
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require_once 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const formatRupiah = (number) => {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(number);
        }

        function calculateChange() {
            const total = parseInt(document.getElementById('grandTotal').value) || 0;
            const payInput = document.getElementById('payAmount');
            const pay = parseInt(payInput.value) || 0;
            const submitBtn = document.getElementById('btnSubmitPayment');
            const shortageAlert = document.getElementById('shortageAlert');
            
            const change = pay - total;
            
            document.getElementById('changeText').innerText = formatRupiah(change >= 0 ? change : 0);
            document.getElementById('changeAmountInput').value = change >= 0 ? change : 0;

            if (pay >= total) {
                submitBtn.disabled = false;
                payInput.classList.remove('is-invalid');
                payInput.classList.add('is-valid');
                shortageAlert.style.display = 'none';
            } else {
                submitBtn.disabled = true;
                payInput.classList.add('is-invalid');
                payInput.classList.remove('is-valid');
                shortageAlert.style.display = 'block';
            }
        }

        // Fungsi untuk mengaktifkan/menonaktifkan input sesuai metode pembayaran
        function togglePaymentMethod() {
            const isQris = document.getElementById('methodQris').checked;
            const cashContainer = document.getElementById('cashInputContainer');
            const qrisContainer = document.getElementById('qrisContainer');
            const payAmountInput = document.getElementById('payAmount');
            const grandTotal = parseFloat(document.getElementById('grandTotal').value);
            const btnSubmitPayment = document.getElementById('btnSubmitPayment');
            
            if (isQris) {
                // Sembunyikan input Cash, tampilkan QRIS
                cashContainer.style.display = 'none';
                qrisContainer.style.display = 'block';
                // Nonaktifkan validasi, tombol selalu aktif untuk QRIS 
                btnSubmitPayment.disabled = false; 
                // Set nilai bayar sama dengan total tagihan untuk data backend
                payAmountInput.value = grandTotal; 
                // Reset kembalian
                document.getElementById('changeAmountInput').value = 0;
                document.getElementById('changeText').textContent = formatRupiah(0);

            } else {
                // Tampilkan input Cash, sembunyikan QRIS
                cashContainer.style.display = 'block';
                qrisContainer.style.display = 'none';
                
                // Pastikan input cash terisi dan hitung kembalian
                calculateChange(); 
                
                // Jika input kosong atau < total, tombol akan dinonaktifkan di calculateChange()
                if (parseFloat(payAmountInput.value) < grandTotal) {
                     btnSubmitPayment.disabled = true;
                }
            }
        }

        // Event listener untuk tombol hapus (mengganti confirm())
        document.querySelectorAll('.remove-item-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const productName = this.getAttribute('data-product-name');
                const removeLink = this.getAttribute('href');
                
                document.getElementById('confirmMessage').innerText = `Yakin ingin menghapus item "${productName}" dari keranjang?`;
                document.getElementById('confirmRemoveLink').setAttribute('href', removeLink);
                
                var myModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
                myModal.show();
            });
        });


        // **Fungsi Cetak Struk**
        function printReceipt() {
            var printContent = document.getElementById('receiptContent').innerHTML;
            
            // Buat window cetak baru
            var printWindow = window.open('', '_blank', 'height=600,width=400');
            printWindow.document.write('<html><head><title>Struk Pembayaran</title>');
            // Sertakan CSS minimal untuk tampilan cetak
            printWindow.document.write('<style>');
            printWindow.document.write('@page { size: 80mm auto; margin: 10mm; } body { font-family: monospace; font-size: 10px; } .d-flex { display: flex; justify-content: space-between; } .text-center { text-align: center; } .fw-bold { font-weight: bold; } .border-bottom { border-bottom: 1px dashed #000; } .border-top { border-top: 1px dashed #000; } .pb-2 { padding-bottom: 5px; } .pt-2 { padding-top: 5px; } .mb-2 { margin-bottom: 5px; } .small { font-size: 9px; } .p-0 { padding: 0; } .w-100 { width: 100%; }');
            printWindow.document.write('</style></head><body>');
            
            // Masukkan konten modal (hilangkan tombol cetak)
            printWindow.document.write('<div style="width: 250px; margin: auto;">');
            // Menghapus tombol cetak dari konten sebelum dicetak
            let contentWithoutPrintBtn = printContent.replace(/<div class="d-grid gap-2">[\s\S]*?<\/button>[\s\S]*?<\/div>/, '');
            printWindow.document.write(contentWithoutPrintBtn);
            printWindow.document.write('</div></body></html>');
            
            printWindow.document.close();
            printWindow.focus();
            setTimeout(function(){
                printWindow.print();
                printWindow.close();
            }, 500); // Beri jeda sebentar untuk loading

        }

        // **Logika Tampil Struk Setelah Transaksi**
        <?php
        $receipt_data_json = 'null';
        if (isset($_SESSION['receipt_data'])) {
            $receipt_data_json = json_encode($_SESSION['receipt_data']);
            unset($_SESSION['receipt_data']); // Hapus data struk setelah diambil
        }
        echo "const receiptData = $receipt_data_json;";
        ?>

        if (receiptData) {
            const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
            
            // Format item list
            let itemsHtml = '';
            receiptData.items.forEach(item => {
                itemsHtml += `
                    <div class="d-flex justify-content-between small">
                        <span style="max-width: 60%;">${item.quantity} x ${item.name} (@${formatRupiah(item.price).replace('Rp', '')})</span>
                        <span class="text-end" style="white-space: nowrap;">${formatRupiah(item.subtotal)}</span>
                    </div>
                `;
            });
            
            // Isi detail struk
            document.getElementById('receipt-transaction-id').textContent = '#' + receiptData.transaction_id;
            document.getElementById('receipt-date').textContent = new Date(receiptData.date).toLocaleString('id-ID', {
                year: 'numeric', month: 'short', day: 'numeric', 
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
            });
            document.getElementById('receipt-items-list').innerHTML = itemsHtml;
            document.getElementById('receipt-total').textContent = formatRupiah(receiptData.total_amount);
            document.getElementById('receipt-method').textContent = receiptData.payment_method.toUpperCase();
            document.getElementById('receipt-pay-amount').textContent = formatRupiah(receiptData.pay_amount);
            document.getElementById('receipt-change').textContent = formatRupiah(receiptData.change_amount);

            receiptModal.show();
        }


    </script>
    <script>
// Fix for back-forward cache issue in some browsers
window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});
</script>
</body>
</html>