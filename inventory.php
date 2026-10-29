<?php

require_once 'config.php';
check_access(['admin']); 

$message = '';
$message_type = '';

$upload_dir = 'uploads/';

function sanitize_filename($filename) {
    // Ganti semua karakter non-alfanumerik, spasi, dan titik dengan hyphen
    $filename = preg_replace("/[^a-zA-Z0-9\s\.\-]/", "", $filename);
    $filename = str_replace([' ', '.'], '-', $filename);
    return strtolower(trim($filename, '-'));
}

/**
 * Fungsi untuk mengupload file dan mengembalikan nama file baru atau pesan error.
 */
function handle_upload($file_array, $product_name) {
    global $upload_dir;

    if ($file_array['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $file_array['tmp_name'];
        $file_name = $file_array['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($file_ext, $allowed_ext)) {
            return ['error' => 'Format file tidak didukung. Hanya JPG, JPEG, PNG, GIF.'];
        }
        
        if ($file_array['size'] > $max_size) {
            return ['error' => 'Ukuran file terlalu besar. Maksimal 2MB.'];
        }

        // Pastikan direktori ada
        if (!is_dir($upload_dir)) {
            // Coba buat direktori jika belum ada (mode 0777, rekursif)
            if (!mkdir($upload_dir, 0777, true)) {
                 return ['error' => 'Gagal membuat direktori upload.'];
            }
        }

        // Generate nama file unik berdasarkan waktu dan nama produk
        $new_file_name = uniqid('prod-') . '-' . sanitize_filename($product_name) . '.' . $file_ext;
        $file_target = $upload_dir . $new_file_name;

        if (move_uploaded_file($file_tmp, $file_target)) {
            return ['filename' => $file_target]; // Mengembalikan path relatif
        } else {
            return ['error' => 'Gagal memindahkan file yang diupload. Cek izin direktori.'];
        }
    }
    // Jika tidak ada file yang diupload atau error lainnya
    if ($file_array['error'] === UPLOAD_ERR_NO_FILE) {
         return ['filename' => ''];
    }
    return ['error' => 'Terjadi kesalahan upload yang tidak diketahui: ' . $file_array['error']];
}

/**
 * Fungsi untuk menghapus file gambar lama dari server.
 */
function delete_old_image($conn, $product_id) {
    $query = "SELECT image_url FROM products WHERE id=$product_id";
    $result = mysqli_query($conn, $query);
    $product = mysqli_fetch_assoc($result);
    
    if ($product && !empty($product['image_url'])) {
        $old_path = $product['image_url'];
        // Pastikan file_exists sebelum unlink (unlink file yang tidak ada akan menghasilkan warning)
        if (file_exists($old_path) && !is_dir($old_path)) {
            // Coba hapus file, abaikan jika gagal (misalnya karena permission)
            @unlink($old_path);
        }
    }
}


// ==========================================
// 1. PROSES CRUD (Create, Update, Delete)
// ==========================================

// Proses tambah produk
if (isset($_POST['add_product'])) {
    $name = escape_string($conn, $_POST['name']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category = escape_string($conn, $_POST['category']);
    $image_path = '';

    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_result = handle_upload($_FILES['product_image'], $name);
        
        if (isset($upload_result['error'])) {
            $message = 'Gagal menambahkan produk: ' . $upload_result['error'];
            $message_type = 'danger';
            // Pindah ke akhir proses jika upload gagal kritis
            goto end_of_add_product; 
        }
        $image_path = $upload_result['filename'];
    }

    // Query INSERT diupdate
    $query = "INSERT INTO products (name, price, stock, category, image_url) VALUES ('$name', $price, $stock, '$category', '$image_path')";
    if (mysqli_query($conn, $query)) {
        $message = 'Produk berhasil ditambahkan!';
        $message_type = 'success';
    } else {
        $message = 'Gagal menambahkan produk: ' . mysqli_error($conn);
        $message_type = 'danger';
        // Hapus file yang baru diupload jika database gagal
        if (!empty($image_path) && file_exists($image_path)) {
            @unlink($image_path); 
        }
    }
    end_of_add_product:
}

// Proses edit produk
if (isset($_POST['edit_product'])) {
    $id = intval($_POST['id']);
    $name = escape_string($conn, $_POST['name']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category = escape_string($conn, $_POST['category']);

    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_result = handle_upload($_FILES['product_image'], $name);
        
        if (isset($upload_result['error'])) {
            $message = 'Gagal mengupdate produk: ' . $upload_result['error'];
            $message_type = 'danger';
            goto end_of_edit_product;
        }
        
        // Jika upload baru berhasil, hapus gambar lama
        delete_old_image($conn, $id);
        $new_image_path = $upload_result['filename'];
    }

    // Query UPDATE diupdate untuk menyertakan image_url baru/lama
    $query = "UPDATE products SET name='$name', price=$price, stock=$stock, category='$category', image_url='$new_image_path' WHERE id=$id";
    if (mysqli_query($conn, $query)) {
        $message = 'Produk berhasil diupdate!';
        $message_type = 'success';
        
        // Hapus parameter 'edit' dari memori server agar form di bawah kembali kosong
        unset($_GET['edit']); 
    } else {
        $message = 'Gagal mengupdate produk: ' . mysqli_error($conn);
        $message_type = 'danger';
    }
    end_of_edit_product:
}

// Proses hapus produk
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Hapus file gambar terkait sebelum menghapus record
    delete_old_image($conn, $id); 

    $query = "DELETE FROM products WHERE id=$id";
    if (mysqli_query($conn, $query)) {
        $message = 'Produk berhasil dihapus!';
        $message_type = 'success';
    } else {
        $message = 'Gagal menghapus produk: ' . mysqli_error($conn);
        $message_type = 'danger';
    }
}

// Ambil data produk untuk edit (jika ada)
$edit_product = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $query_edit = "SELECT * FROM products WHERE id=$id";
    $result_edit = mysqli_query($conn, $query_edit);
    $edit_product = mysqli_fetch_assoc($result_edit);
}

// Filter dan Pencarian
$search_keyword = isset($_GET['search']) ? escape_string($conn, $_GET['search']) : '';
$filter_category = isset($_GET['filter_category']) ? escape_string($conn, $_GET['filter_category']) : '';

$query_products = "SELECT * FROM products";
$conditions = [];

if (!empty($search_keyword)) {
    $conditions[] = "name LIKE '%$search_keyword%'";
}

if (!empty($filter_category)) {
    $conditions[] = "category = '$filter_category'";
}

if (count($conditions) > 0) {
    $query_products .= " WHERE " . implode(' AND ', $conditions);
}

$query_products .= " ORDER BY id DESC";
$result_products = mysqli_query($conn, $query_products);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Outlet Akang Kebab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        /* Gaya kustom untuk menyesuaikan tampilan yang sudah ada */
        .navbar-orange {
            background-color: #ff8c00; /* Warna oranye tua/dark orange */
        }
        .btn-orange {
            background-color: #ff8c00;
            border-color: #ff8c00;
            color: white;
        }
        .btn-orange:hover {
            background-color: #e67e00;
            border-color: #e67e00;
            color: white;
        }
        .text-orange {
            color: #ff8c00 !important;
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
        <h3 class="mb-4">📦 Inventory Management</h3>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            
            <?php if ($message_type == 'success' && !isset($_GET['edit'])): ?>
            <script>
                // Membersihkan URL setelah sukses CRUD (kecuali sedang edit)
                if (window.history.replaceState) {
                    window.history.replaceState(null, null, window.location.pathname);
                }
            </script>
            <?php endif; ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header bg-orange text-white">
                        <h5 class="mb-0"><?php echo $edit_product ? 'Edit Produk' : 'Tambah Produk Baru'; ?></h5>
                    </div>
                    <div class="card-body">
                        <!-- PENTING: Tambahkan enctype="multipart/form-data" -->
                        <form method="POST" action="inventory.php" enctype="multipart/form-data">
                            <?php if ($edit_product): ?>
                                <input type="hidden" name="id" value="<?php echo $edit_product['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Nama Produk</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                        value="<?php echo $edit_product ? htmlspecialchars($edit_product['name']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="price" class="form-label">Harga (Rp)</label>
                                <input type="number" class="form-control" id="price" name="price" 
                                        value="<?php echo $edit_product ? $edit_product['price'] : ''; ?>" min="0" step="100" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="stock" class="form-label">Stok</label>
                                <input type="number" class="form-control" id="stock" name="stock" 
                                        value="<?php echo $edit_product ? $edit_product['stock'] : ''; ?>" min="0" required>
                            </div>

                            <div class="mb-3">
                                <label for="category" class="form-label">Kategori</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Pilih Kategori</option>
                                    <option value="Kebab" <?php echo ($edit_product && $edit_product['category'] == 'Kebab') ? 'selected' : ''; ?>>Kebab</option>
                                    <option value="Burger" <?php echo ($edit_product && $edit_product['category'] == 'Burger') ? 'selected' : ''; ?>>Burger</option>
                                    <option value="Snack" <?php echo ($edit_product && $edit_product['category'] == 'Snack') ? 'selected' : ''; ?>>Snack</option>
                                    <option value="Lainnya" <?php echo ($edit_product && $edit_product['category'] == 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                                </select>
                            </div>
                            
                            <!-- INPUT BARU UNTUK GAMBAR (FILE UPLOAD) -->
                            <div class="mb-3">
                                <label for="product_image" class="form-label">Upload Gambar Produk (Opsional)</label>
                                <input type="file" class="form-control" id="product_image" name="product_image" accept="image/*">
                                <div class="form-text">Maksimal ukuran file: 2MB. Format: JPG, PNG, GIF.</div>
                                
                                <?php 
                                // Pratinjau gambar saat Edit
                                $current_image = $edit_product ? $edit_product['image_url'] : '';
                                if ($edit_product && !empty($current_image)): 
                                ?>
                                    <!-- Menyimpan URL gambar saat ini agar bisa dipertahankan jika tidak ada upload baru -->
                                    <input type="hidden" name="current_image_url" value="<?php echo htmlspecialchars($current_image); ?>">
                                    <div class="mt-2">
                                        <p class="small mb-1 text-muted">Gambar Saat Ini:</p>
                                        <img src="<?php echo htmlspecialchars($current_image); ?>" 
                                             alt="Gambar Produk Saat Ini" 
                                             style="max-width: 100px; height: auto; border-radius: 4px; border: 1px solid #ccc;"
                                             onerror="this.onerror=null;this.src='https://placehold.co/100x100/cccccc/333333?text=N/A';"
                                             >
                                    </div>
                                <?php endif; ?>
                            </div>
                            <!-- END INPUT BARU -->
                            
                            <?php if ($edit_product): ?>
                                <button type="submit" name="edit_product" class="btn btn-orange w-100">Update Produk</button>
                                <a href="inventory.php" class="btn btn-secondary w-100 mt-2">Batal</a>
                            <?php else: ?>
                                <button type="submit" name="add_product" class="btn btn-orange w-100">Tambah Produk</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header bg-orange text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Daftar Produk</h5>
                        <span class="badge bg-light text-dark"><?php echo mysqli_num_rows($result_products); ?> Item</span>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row g-2 mb-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="Cari nama produk..." 
                                            value="<?php echo htmlspecialchars($search_keyword); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select name="filter_category" class="form-select">
                                    <option value="">-- Semua Kategori --</option>
                                    <option value="Kebab" <?php echo ($filter_category == 'Kebab') ? 'selected' : ''; ?>>Kebab</option>
                                    <option value="Burger" <?php echo ($filter_category == 'Burger') ? 'selected' : ''; ?>>Burger</option>
                                    <option value="Snack" <?php echo ($filter_category == 'Snack') ? 'selected' : ''; ?>>Snack</option>
                                    <option value="Lainnya" <?php echo ($filter_category == 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                                    <?php if(!empty($search_keyword) || !empty($filter_category)): ?>
                                        <a href="inventory.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <!-- KOLOM GAMBAR -->
                                        <th class="text-center">Gambar</th>
                                        <!-- END KOLOM GAMBAR -->
                                        <th>Nama Produk</th>
                                        <th>Kategori</th>
                                        <th class="text-end">Harga</th>
                                        <th class="text-center">Stok</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result_products) > 0): ?>
                                        <?php while ($product = mysqli_fetch_assoc($result_products)): ?>
                                            <tr>
                                                <td><?php echo $product['id']; ?></td>
                                                <!-- TAMPILAN GAMBAR DARI PATH LOKAL -->
                                                <td class="text-center">
                                                    <?php if (!empty($product['image_url'])): ?>
                                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                            alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                            style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"
                                                            onerror="this.onerror=null;this.src='https://placehold.co/50x50/cccccc/333333?text=N/A';"
                                                            title="Gambar Produk">
                                                    <?php else: ?>
                                                        <i class="bi bi-image-fill text-muted" title="Tanpa Gambar" style="font-size: 1.5rem;"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <!-- END TAMPILAN GAMBAR -->
                                                <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($product['category']); ?></span></td>
                                                <td class="text-end">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></td>
                                                <td class="text-center">
                                                    <span class="badge <?php echo $product['stock'] < 10 ? 'bg-danger' : 'bg-success'; ?>">
                                                        <?php echo $product['stock']; ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="bi bi-pencil-square"></i> Edit</a>
                                                    <!-- Gunakan event 'click' untuk konfirmasi, bukan alert() -->
                                                    <a href="?delete=<?php echo $product['id']; ?>" class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Yakin ingin menghapus produk ini? Ini juga akan menghapus file gambar terkait.')" title="Hapus"><i class="bi bi-trash"></i> Hapus</a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <!-- UPDATE COLSPAN JADI 7 -->
                                            <td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>Data tidak ditemukan</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require_once 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Memastikan halaman me-refresh setelah back button ditekan
        window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
            }
        });
    </script>
</body>
</html>