<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/auth.php';
adminGuard();

$activePage = 'products';

$id      = (int) ($_GET['id'] ?? 0);
$product = $id ? getProduct($pdo, $id) : null;

if (!$product) {
    header('Location: products.php');
    exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $model       = trim($_POST['model']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $collections = $_POST['collections']      ?? [];

    if ($model === '')       $errors[] = 'Model name is required.';
    if (empty($collections)) $errors[] = 'Select at least one collection.';

    $modelImage = $product['image'];
    if (!empty($_FILES['model_image']['name'])) {
        $newImg = fileToBase64($_FILES['model_image']);
        if ($newImg) { $modelImage = $newImg; }
        else { $errors[] = 'Invalid image. Use JPG, PNG or WEBP under 5MB.'; }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $pdo->prepare('UPDATE products SET model=:m,image=:i,description=:d WHERE id=:id')
                ->execute([':m'=>$model,':i'=>$modelImage,':d'=>$description?:null,':id'=>$id]);

            $pdo->prepare('DELETE FROM product_collections WHERE product_id=?')->execute([$id]);
            $stmtCol = $pdo->prepare('INSERT INTO product_collections (product_id,collection) VALUES (:pid,:col)');
            foreach ($collections as $col) {
                $col = trim($col);
                if ($col !== '') $stmtCol->execute([':pid'=>$id,':col'=>$col]);
            }

            $storageLabels = $_POST['storage_label'] ?? [];
            $storagePrices = $_POST['storage_price'] ?? [];
            $storageIds    = $_POST['storage_id']    ?? [];
            $keepIds       = [];

            $stmtUpd  = $pdo->prepare('UPDATE product_storages SET label=:label,price=:price WHERE id=:id AND product_id=:pid');
            $stmtIns  = $pdo->prepare('INSERT INTO product_storages (product_id,label,price) VALUES (:pid,:label,:price)');
            $stmtAuto = $pdo->prepare('INSERT IGNORE INTO storages (label) VALUES (:label)');

            foreach ($storageLabels as $i => $label) {
                $label = trim($label); $price = floatval($storagePrices[$i] ?? 0); $sid = (int)($storageIds[$i] ?? 0);
                if ($label === '') continue;
                if ($sid > 0) { $stmtUpd->execute([':label'=>$label,':price'=>$price,':id'=>$sid,':pid'=>$id]); $keepIds[] = $sid; }
                else { $stmtIns->execute([':pid'=>$id,':label'=>$label,':price'=>$price]); $keepIds[] = (int)$pdo->lastInsertId(); }
                $stmtAuto->execute([':label'=>$label]);
            }
            if (!empty($keepIds)) {
                $ph = implode(',', array_fill(0, count($keepIds), '?'));
                $pdo->prepare("DELETE FROM product_storages WHERE product_id=? AND id NOT IN ($ph)")
                    ->execute(array_merge([$id], $keepIds));
            } else {
                $pdo->prepare('DELETE FROM product_storages WHERE product_id=?')->execute([$id]);
            }

            $colorNames   = $_POST['color_name'] ?? [];
            $colorIds     = $_POST['color_id']   ?? [];
            $keepColorIds = [];

            $stmtCUpd = $pdo->prepare('UPDATE product_colors SET name=:name WHERE id=:id AND product_id=:pid');
            $stmtCIns = $pdo->prepare('INSERT INTO product_colors (product_id,name,image) VALUES (:pid,:name,:image)');
            $stmtCImg = $pdo->prepare('UPDATE product_colors SET image=:image WHERE id=:id');

            foreach ($colorNames as $i => $name) {
                $name = trim($name); $cid = (int)($colorIds[$i] ?? 0);
                if ($name === '') continue;
                if ($cid > 0) {
                    $stmtCUpd->execute([':name'=>$name,':id'=>$cid,':pid'=>$id]);
                    if (!empty($_FILES['color_image']['name'][$i]) && $_FILES['color_image']['error'][$i] === UPLOAD_ERR_OK) {
                        $cImg = fileToBase64(['name'=>$_FILES['color_image']['name'][$i],'type'=>$_FILES['color_image']['type'][$i],'tmp_name'=>$_FILES['color_image']['tmp_name'][$i],'error'=>$_FILES['color_image']['error'][$i],'size'=>$_FILES['color_image']['size'][$i]]);
                        if ($cImg) $stmtCImg->execute([':image'=>$cImg,':id'=>$cid]);
                    }
                    $keepColorIds[] = $cid;
                } else {
                    $cImg = null;
                    if (!empty($_FILES['color_image']['name'][$i]) && $_FILES['color_image']['error'][$i] === UPLOAD_ERR_OK) {
                        $cImg = fileToBase64(['name'=>$_FILES['color_image']['name'][$i],'type'=>$_FILES['color_image']['type'][$i],'tmp_name'=>$_FILES['color_image']['tmp_name'][$i],'error'=>$_FILES['color_image']['error'][$i],'size'=>$_FILES['color_image']['size'][$i]]);
                    }
                    $stmtCIns->execute([':pid'=>$id,':name'=>$name,':image'=>$cImg]);
                    $keepColorIds[] = (int)$pdo->lastInsertId();
                }
                $pdo->prepare('INSERT IGNORE INTO colors (name) VALUES (:n)')->execute([':n'=>$name]);
            }
            if (!empty($keepColorIds)) {
                $pl = implode(',', array_fill(0, count($keepColorIds), '?'));
                $pdo->prepare("DELETE FROM product_colors WHERE product_id=? AND id NOT IN ($pl)")
                    ->execute(array_merge([$id], $keepColorIds));
            } else {
                $pdo->prepare('DELETE FROM product_colors WHERE product_id=?')->execute([$id]);
            }

            // Specs — replace all then re-insert
            $pdo->prepare('DELETE FROM product_specs WHERE product_id=?')->execute([$id]);
            $specKeys   = $_POST['spec_key']   ?? [];
            $specValues = $_POST['spec_value'] ?? [];
            $stmtSpec   = $pdo->prepare('INSERT INTO product_specs (product_id, spec_key, spec_value, sort_order) VALUES (:pid,:key,:val,:order)');
            foreach ($specKeys as $i => $key) {
                $key = trim($key); $val = trim($specValues[$i] ?? '');
                if ($key !== '' && $val !== '') {
                    $stmtSpec->execute([':pid' => $id, ':key' => $key, ':val' => $val, ':order' => $i]);
                }
            }

            $pdo->commit();
            $success = true;
            $product = getProduct($pdo, $id);

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Edit — <?= e($product['model']) ?> — Admin — Gadget Genesis</title>
  <link rel="stylesheet" href="../style.css"/>
  <style>
    body { background: #f1f3f8; }
    .adm-wrap    { display:flex;min-height:100vh; }
    .adm-sidebar { width:220px;flex-shrink:0;background:var(--navy);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100; }
    .adm-logo    { padding:22px 20px 18px;border-bottom:1px solid rgba(255,255,255,.1); }
    .adm-logo img  { height:36px;width:auto; }
    .adm-logo span { display:block;font-size:11px;color:rgba(255,255,255,.5);margin-top:4px;letter-spacing:.5px;text-transform:uppercase; }
    .adm-nav { flex:1;padding:16px 0; }
    .adm-nav a { display:flex;align-items:center;gap:10px;padding:11px 20px;color:rgba(255,255,255,.7);text-decoration:none;font-size:14px;font-weight:600;transition:background .15s,color .15s;border-left:3px solid transparent; }
    .adm-nav a:hover,.adm-nav a.active { background:rgba(255,255,255,.08);color:#fff;border-left-color:#60a5fa; }
    .adm-nav a [data-lucide] { width:16px;height:16px;flex-shrink:0; }
    .adm-nav .nav-section { font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:16px 20px 6px; }
    .adm-footer { padding:16px 20px;border-top:1px solid rgba(255,255,255,.1);display:flex;flex-direction:column;gap:8px; }
    .adm-footer a { display:flex;align-items:center;gap:8px;font-size:13px;color:rgba(255,255,255,.5);text-decoration:none;transition:color .15s; }
    .adm-footer a:hover { color:#fff; }
    .adm-footer [data-lucide] { width:14px;height:14px; }

    .adm-main  { margin-left:220px;flex:1;padding:32px; }
    .adm-topbar { display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px; }
    .adm-topbar h1 { font-size:22px;font-weight:800;color:var(--navy); }
    .btn-back { display:inline-flex;align-items:center;gap:7px;padding:9px 16px;background:#fff;color:var(--navy);border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;transition:background .15s; }
    .btn-back:hover { background:#f1f3f8; }
    .btn-back [data-lucide] { width:14px;height:14px; }

    .form-wrap { max-width:820px; }
    .adm-form-card { background:#fff;border:1px solid var(--border);border-radius:14px;padding:28px 32px;margin-bottom:20px; }
    .adm-form-card h2 { font-size:16px;font-weight:800;color:var(--navy);margin-bottom:20px;display:flex;align-items:center;gap:8px; }
    .adm-form-card h2 [data-lucide] { width:16px;height:16px; }

    .field { display:flex;flex-direction:column;gap:6px;margin-bottom:18px; }
    .field:last-child { margin-bottom:0; }
    .field label { font-size:13px;font-weight:600;color:var(--navy); }
    .field input[type=text],.field textarea { padding:10px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:14px;font-family:inherit;color:var(--navy);outline:none;transition:border-color .15s; }
    .field input:focus,.field textarea:focus { border-color:var(--navy); }
    .field textarea { resize:vertical;min-height:90px; }
    .required { color:#ef4444; }

    .upload-box { border:2px dashed var(--border);border-radius:10px;padding:20px;text-align:center;cursor:pointer;position:relative;transition:border-color .15s; }
    .upload-box:hover { border-color:var(--navy); }
    .upload-box input[type=file] { position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%; }
    .upload-box p { font-size:13px;color:var(--gray);margin:4px 0; }
    .upload-box p strong { color:var(--navy); }
    .preview-img { max-width:120px;max-height:120px;object-fit:cover;border-radius:8px;margin-top:10px;display:none; }
    .current-img { width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid var(--border);margin-bottom:10px;display:block; }

    .collection-grid { display:flex;flex-wrap:wrap;gap:8px; }
    .coll-item { display:flex;align-items:center;gap:6px;padding:6px 12px;background:#f3f4f6;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s; }
    .coll-item input { margin:0; }
    .coll-item:has(input:checked) { background:#e0e7ff;color:#3730a3; }

    .picker-row { display:flex;gap:8px;margin-bottom:12px; }
    .autocomplete-wrap { position:relative;flex:1; }
    .autocomplete-wrap input { width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box; }
    .autocomplete-wrap input:focus { border-color:var(--navy); }
    .autocomplete-list { position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid var(--border);border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.1);z-index:50;display:none;max-height:200px;overflow-y:auto; }
    .autocomplete-list.open { display:block; }
    .autocomplete-item { padding:9px 12px;font-size:13px;cursor:pointer; }
    .autocomplete-item:hover,.autocomplete-item.active { background:#f3f4f6; }
    .new-tag { font-size:11px;background:#dcfce7;color:#166534;border-radius:4px;padding:1px 6px;margin-left:6px; }
    .btn-add { padding:9px 18px;background:var(--navy);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap; }

    .col-labels { display:grid;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--gray);letter-spacing:.4px;margin-bottom:6px;padding:0 4px; }
    .storage-labels { grid-template-columns:1fr 180px 32px; }
    .color-labels   { grid-template-columns:1fr 80px 32px; }
    .variant-row { display:grid;gap:8px;align-items:center;margin-bottom:8px; }
    .storage-row { grid-template-columns:1fr 180px 32px; }
    .color-row   { grid-template-columns:1fr 80px 32px; }
    .variant-label { font-size:13px;font-weight:600;color:var(--navy); }
    .variant-row input[type=number] { padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none; }
    .variant-row input[type=number]:focus { border-color:var(--navy); }
    .btn-remove { background:#fee2e2;color:#dc2626;border:none;border-radius:6px;width:28px;height:28px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center; }
    .btn-remove:hover { background:#fecaca; }

    .img-upload { position:relative;width:52px;height:52px;border:1.5px dashed var(--border);border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;overflow:hidden; }
    .img-upload input[type=file] { position:absolute;inset:0;opacity:0;cursor:pointer; }
    .img-upload img { width:100%;height:100%;object-fit:cover;display:none; }
    .upload-plus { font-size:20px;color:var(--gray); }

    .form-actions { display:flex;gap:10px;align-items:center;margin-top:8px; }
    .btn-save { padding:12px 28px;background:var(--navy);color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:8px; }
    .btn-save:hover { opacity:.88; }
    .btn-save [data-lucide] { width:16px;height:16px; }
    .btn-cancel-link { padding:12px 20px;background:#fff;color:var(--navy);border:1.5px solid var(--border);border-radius:9px;font-size:14px;font-weight:600;text-decoration:none; }
    .btn-cancel-link:hover { background:#f1f3f8; }

    .alert-success { background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:13px;display:flex;align-items:center;gap:8px; }
    .alert-error   { background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:13px; }
    .alert-error p { margin:3px 0; }
  </style>
</head>
<body>
<div class="adm-wrap">

  <!-- Sidebar -->
  <aside class="adm-sidebar">
    <div class="adm-logo">
      <img src="../logo.jpg" alt="Gadget Genesis"/>
      <span>Admin Panel</span>
    </div>
    <nav class="adm-nav">
      <div class="nav-section">Overview</div>
      <a href="index.php"><i data-lucide="layout-dashboard"></i> Dashboard</a>
      <div class="nav-section">Store</div>
      <a href="orders.php"><i data-lucide="package"></i> Orders</a>
      <a href="products.php" class="active"><i data-lucide="box"></i> Products</a>
      <a href="add-product.php"><i data-lucide="plus-circle"></i> Add Product</a>
      <a href="discounts.php"><i data-lucide="percent"></i> Discounts</a>
      <a href="coupons.php"><i data-lucide="tag"></i> Coupons</a>
      <a href="customers.php"><i data-lucide="users"></i> Customers</a>
      <a href="reviews.php"><i data-lucide="star"></i> Reviews</a>
      <div class="nav-section">Account</div>
      <a href="change-password.php"><i data-lucide="key-round"></i> Change Password</a>
    </nav>
    <div class="adm-footer">
      <span style="font-size:12px;color:rgba(255,255,255,.4);display:flex;align-items:center;gap:6px;">
        <i data-lucide="user" style="width:12px;height:12px;"></i>
        <?= htmlspecialchars(adminUsername(), ENT_QUOTES, 'UTF-8') ?>
      </span>
      <a href="logout.php"><i data-lucide="log-out"></i> Sign Out</a>
    </div>
  </aside>

  <!-- Main -->
  <main class="adm-main">
    <div class="adm-topbar">
      <h1>Edit — <?= e($product['model']) ?></h1>
      <a href="products.php" class="btn-back"><i data-lucide="arrow-left"></i> All Products</a>
    </div>

    <?php if ($success): ?>
      <div class="alert-success"><i data-lucide="circle-check"></i> Product updated successfully.</div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="alert-error">
        <?php foreach ($errors as $err): ?><p>✕ <?= e($err) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="form-wrap">
      <form method="POST" enctype="multipart/form-data" novalidate>

        <!-- Model -->
        <div class="adm-form-card">
          <h2><i data-lucide="smartphone"></i> Model</h2>
          <div class="field">
            <label>Model Name <span class="required">*</span></label>
            <input type="text" name="model" value="<?= e($product['model']) ?>" required/>
          </div>
          <div class="field">
            <label>Model Image <small style="font-weight:400;color:var(--gray)">(leave empty to keep current)</small></label>
            <?php if ($product['image']): ?>
              <img src="<?= $product['image'] ?>" id="currentModelImg" class="current-img" alt="Current"/>
            <?php endif; ?>
            <div class="upload-box">
              <input type="file" name="model_image" id="model_image" accept="image/*"/>
              <p><strong>Click to upload new image</strong></p>
              <p style="font-size:12px;">JPG, PNG, WEBP — max 5MB</p>
              <img class="preview-img" id="modelPreview" alt="Preview"/>
            </div>
          </div>
        </div>

        <!-- Collections -->
        <div class="adm-form-card">
          <h2><i data-lucide="layout-grid"></i> Collections <span class="required">*</span></h2>
          <div class="collection-grid">
            <?php foreach (COLLECTIONS as $col): ?>
              <label class="coll-item">
                <input type="checkbox" name="collections[]" value="<?= e($col) ?>"
                       <?= in_array($col, $product['collections']) ? 'checked' : '' ?>/>
                <?= e($col) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Description -->
        <div class="adm-form-card">
          <h2><i data-lucide="file-text"></i> Description</h2>
          <div class="field">
            <textarea name="description" placeholder="Describe the product…"><?= e($product['description'] ?? '') ?></textarea>
          </div>
        </div>

        <!-- Specifications -->
        <div class="adm-form-card">
          <h2><i data-lucide="list"></i> Specifications</h2>
          <div class="col-labels" id="specColLabels"
               style="grid-template-columns:1fr 1fr 32px;<?= empty($product['specs']) ? 'display:none' : '' ?>">
            <span>Label</span><span>Value</span><span></span>
          </div>
          <div class="variant-list" id="specList">
            <?php foreach ($product['specs'] as $spec): ?>
              <div class="variant-row" style="grid-template-columns:1fr 1fr 32px;">
                <input type="text" name="spec_key[]"   value="<?= e($spec['spec_key']) ?>"   placeholder="e.g. Display"       style="padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none;"/>
                <input type="text" name="spec_value[]" value="<?= e($spec['spec_value']) ?>" placeholder="e.g. 6.1-inch OLED" style="padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none;"/>
                <button type="button" class="btn-remove" onclick="removeRow(this,'specList','specColLabels')">✕</button>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn-add" id="addSpecBtn" style="margin-top:10px;">
            + Add Specification
          </button>
        </div>

        <!-- Storage & Pricing -->
        <div class="adm-form-card">
          <h2><i data-lucide="hard-drive"></i> Storage &amp; Pricing</h2>
          <div class="picker-row">
            <div class="autocomplete-wrap">
              <input type="text" id="storagePicker" placeholder="Add storage option…" autocomplete="off"/>
              <div class="autocomplete-list" id="storageDropdown"></div>
            </div>
            <button type="button" class="btn-add" id="addStorageBtn">Add</button>
          </div>
          <div class="col-labels storage-labels" id="storageColLabels"
               style="<?= empty($product['storages']) ? 'display:none' : '' ?>">
            <span>Storage</span><span>Price (UGX)</span><span></span>
          </div>
          <div class="variant-list" id="storageList">
            <?php foreach ($product['storages'] as $s): ?>
              <div class="variant-row storage-row">
                <span class="variant-label"><?= e($s['label']) ?></span>
                <input type="hidden" name="storage_label[]" value="<?= e($s['label']) ?>"/>
                <input type="hidden" name="storage_id[]"    value="<?= $s['id'] ?>"/>
                <input type="number" name="storage_price[]" value="<?= (int)$s['price'] ?>" min="0" step="1"/>
                <button type="button" class="btn-remove" onclick="removeRow(this,'storageList','storageColLabels')">✕</button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Colors -->
        <div class="adm-form-card">
          <h2><i data-lucide="palette"></i> Colors</h2>
          <div class="picker-row">
            <div class="autocomplete-wrap">
              <input type="text" id="colorPicker" placeholder="Add color…" autocomplete="off"/>
              <div class="autocomplete-list" id="colorDropdown"></div>
            </div>
            <button type="button" class="btn-add" id="addColorBtn">Add</button>
          </div>
          <div class="col-labels color-labels" id="colorColLabels"
               style="<?= empty($product['colors']) ? 'display:none' : '' ?>">
            <span>Color</span><span>Image</span><span></span>
          </div>
          <div class="variant-list" id="colorList">
            <?php foreach ($product['colors'] as $c): ?>
              <div class="variant-row color-row">
                <span class="variant-label"><?= e($c['name']) ?></span>
                <input type="hidden" name="color_name[]" value="<?= e($c['name']) ?>"/>
                <input type="hidden" name="color_id[]"   value="<?= $c['id'] ?>"/>
                <div class="img-upload">
                  <?php if ($c['image']): ?>
                    <img src="<?= $c['image'] ?>" alt="<?= e($c['name']) ?>" style="display:block"/>
                  <?php else: ?>
                    <span class="upload-plus">+</span>
                  <?php endif; ?>
                  <input type="file" name="color_image[]" accept="image/*" onchange="previewColorImg(this)"/>
                </div>
                <button type="button" class="btn-remove" onclick="removeRow(this,'colorList','colorColLabels')">✕</button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Actions -->
        <div class="form-actions">
          <a href="products.php" class="btn-cancel-link">Cancel</a>
          <button type="submit" class="btn-save">
            <i data-lucide="save"></i> Save Changes
          </button>
        </div>

      </form>
    </div>
  </main>
</div>

<div class="toast" id="toast" style="position:fixed;bottom:24px;right:24px;background:var(--navy);color:#fff;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;display:none;z-index:9999;"></div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 3000);
}

document.getElementById('model_image').addEventListener('change', function () {
  const reader = new FileReader();
  reader.onload = e => {
    const img = document.getElementById('modelPreview');
    img.src = e.target.result; img.style.display = 'block';
    const cur = document.getElementById('currentModelImg');
    if (cur) cur.style.display = 'none';
  };
  if (this.files[0]) reader.readAsDataURL(this.files[0]);
});

async function searchAC(url, dd, field, onSelect, q, onNew) {
  try {
    const data = await (await fetch(url)).json();
    dd.innerHTML = ''; dd.classList.remove('open');
    data.forEach(item => {
      const el = document.createElement('div');
      el.className = 'autocomplete-item'; el.textContent = item[field];
      el.addEventListener('click', () => onSelect(item[field]));
      dd.appendChild(el);
    });
    if (!data.some(r => r[field].toLowerCase() === q.toLowerCase())) {
      const el = document.createElement('div');
      el.className = 'autocomplete-item';
      el.innerHTML = `${q} <span class="new-tag">Add new</span>`;
      el.addEventListener('click', () => onNew(q));
      dd.appendChild(el);
    }
    if (dd.children.length) dd.classList.add('open');
  } catch { dd.classList.remove('open'); }
}
function closeDD(dd) { dd.classList.remove('open'); dd.innerHTML = ''; }
function handleKeys(e, dd, onEnter) {
  const items = [...dd.querySelectorAll('.autocomplete-item')];
  const idx   = items.findIndex(el => el.classList.contains('active'));
  if (e.key === 'ArrowDown')  { e.preventDefault(); items[idx+1]?.classList.add('active'); items[idx]?.classList.remove('active'); }
  else if (e.key === 'ArrowUp') { e.preventDefault(); items[idx-1]?.classList.add('active'); items[idx]?.classList.remove('active'); }
  else if (e.key === 'Enter') { e.preventDefault(); idx >= 0 ? items[idx].click() : onEnter(); }
  else if (e.key === 'Escape') closeDD(dd);
}

const storagePicker = document.getElementById('storagePicker');
const storageDD     = document.getElementById('storageDropdown');
let sTimer;
storagePicker.addEventListener('input', function () {
  clearTimeout(sTimer);
  const q = this.value.trim();
  if (!q) { closeDD(storageDD); return; }
  sTimer = setTimeout(() => searchAC('../api/storages.php?q='+encodeURIComponent(q), storageDD, 'label',
    val => { storagePicker.value = val; closeDD(storageDD); addStorage(val); }, q,
    val => { storagePicker.value = val; closeDD(storageDD); addStorage(val); }
  ), 250);
});
storagePicker.addEventListener('keydown', e => handleKeys(e, storageDD, () => addStorage(storagePicker.value.trim())));
document.getElementById('addStorageBtn').addEventListener('click', () => addStorage(storagePicker.value.trim()));
document.addEventListener('click', e => { if (!e.target.closest('.autocomplete-wrap') && !e.target.closest('#addStorageBtn')) closeDD(storageDD); });

function addStorage(val) {
  val = val.trim();
  if (!val) { showToast('Enter a storage value.'); return; }
  const existing = [...document.querySelectorAll('#storageList .variant-label')].map(el => el.textContent);
  if (existing.includes(val)) { showToast(val + ' already added.'); return; }
  const row = document.createElement('div');
  row.className = 'variant-row storage-row';
  row.innerHTML = `<span class="variant-label">${val}</span>
    <input type="hidden" name="storage_label[]" value="${val}"/>
    <input type="hidden" name="storage_id[]" value="0"/>
    <input type="number" name="storage_price[]" placeholder="0" min="0" step="1"/>
    <button type="button" class="btn-remove" onclick="removeRow(this,'storageList','storageColLabels')">✕</button>`;
  document.getElementById('storageList').appendChild(row);
  document.getElementById('storageColLabels').style.display = 'grid';
  storagePicker.value = ''; storagePicker.focus(); closeDD(storageDD);
  fetch('../api/storages.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({label:val}) }).catch(()=>{});
}

const colorPicker = document.getElementById('colorPicker');
const colorDD     = document.getElementById('colorDropdown');
let cTimer;
colorPicker.addEventListener('input', function () {
  clearTimeout(cTimer);
  const q = this.value.trim();
  if (!q) { closeDD(colorDD); return; }
  cTimer = setTimeout(() => searchAC('../api/colors.php?q='+encodeURIComponent(q), colorDD, 'name',
    val => { colorPicker.value = val; closeDD(colorDD); addColor(val); }, q,
    val => { colorPicker.value = val; closeDD(colorDD); addColor(val); }
  ), 250);
});
colorPicker.addEventListener('keydown', e => handleKeys(e, colorDD, () => addColor(colorPicker.value.trim())));
document.getElementById('addColorBtn').addEventListener('click', () => addColor(colorPicker.value.trim()));
document.addEventListener('click', e => { if (!e.target.closest('#colorPicker') && !e.target.closest('#colorDropdown') && !e.target.closest('#addColorBtn')) closeDD(colorDD); });

function addColor(val) {
  val = val.trim();
  if (!val) { showToast('Enter a color name.'); return; }
  const existing = [...document.querySelectorAll('#colorList .variant-label')].map(el => el.textContent);
  if (existing.includes(val)) { showToast(val + ' already added.'); return; }
  const row = document.createElement('div');
  row.className = 'variant-row color-row';
  row.innerHTML = `<span class="variant-label">${val}</span>
    <input type="hidden" name="color_name[]" value="${val}"/>
    <input type="hidden" name="color_id[]" value="0"/>
    <div class="img-upload">
      <input type="file" name="color_image[]" accept="image/*" onchange="previewColorImg(this)"/>
      <img alt="${val}"/>
      <span class="upload-plus">+</span>
    </div>
    <button type="button" class="btn-remove" onclick="removeRow(this,'colorList','colorColLabels')">✕</button>`;
  document.getElementById('colorList').appendChild(row);
  document.getElementById('colorColLabels').style.display = 'grid';
  colorPicker.value = ''; colorPicker.focus(); closeDD(colorDD);
  fetch('../api/colors.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({name:val}) }).catch(()=>{});
}

function previewColorImg(input) {
  if (!input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    const wrap = input.closest('.img-upload');
    wrap.querySelector('img').src = e.target.result;
    wrap.querySelector('img').style.display = 'block';
    const plus = wrap.querySelector('.upload-plus');
    if (plus) plus.style.display = 'none';
  };
  reader.readAsDataURL(input.files[0]);
}

function removeRow(btn, listId, labelsId) {
  btn.closest('.variant-row').remove();
  if (!document.getElementById(listId).children.length)
    document.getElementById(labelsId).style.display = 'none';
}

/* ── Specs ── */
document.getElementById('addSpecBtn').addEventListener('click', () => addSpecRow('', ''));
function addSpecRow(key, val) {
  const row = document.createElement('div');
  row.className = 'variant-row';
  row.style.gridTemplateColumns = '1fr 1fr 32px';
  row.innerHTML = `
    <input type="text"  name="spec_key[]"   value="${key}"  placeholder="e.g. Display"       style="padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none;"/>
    <input type="text"  name="spec_value[]" value="${val}"  placeholder="e.g. 6.1-inch OLED" style="padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;outline:none;"/>
    <button type="button" class="btn-remove" onclick="removeRow(this,'specList','specColLabels')">✕</button>`;
  document.getElementById('specList').appendChild(row);
  document.getElementById('specColLabels').style.display = 'grid';
}

document.addEventListener('DOMContentLoaded', () => { if (window.lucide) lucide.createIcons(); });
</script>
</body>
</html>
