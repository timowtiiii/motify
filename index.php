
<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'staff';
$assigned_branch = $_SESSION['assigned_branch_id'] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Motify System</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="style.css" rel="stylesheet">
  <style>
    .sidebar { width: 80px; }
    .sidebar .list-group-item { text-align: center; padding: 0.75rem 0.5rem; }
    .sidebar .list-group-item span { display: block; font-size: 0.65rem; margin-top: 5px; }
    .sidebar .list-group-item svg { width: 24px; height: 24px; }
    .main-content { flex-grow: 1; }
    .container-fluid.d-flex { min-height: calc(100vh - 56px); }
  </style>
</head>
<body class="bg-light text-dark">

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand text-primary" href="#">Motify</a>
    <div class="d-flex align-items-center">
      <div class="me-3 small text-muted">👤 <?= htmlspecialchars($username) ?> <span class="text-muted">(<small><?= htmlspecialchars($role) ?></small>)</span></div>
      <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>
  </div>
</nav>

<div class="container-fluid d-flex p-0">
    <div class="sidebar bg-white shadow-sm p-2">
      <div class="list-group">
        <button class="list-group-item list-group-item-action active" id="menu-dashboard" title="Dashboard">
          <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16"><path d="M4 11H2v3h2zm5-4H7v7h2zm5-5h-2v12h2zM1.5 1a.5.5 0 0 0-.5.5v13a.5.5 0 0 0 1 0v-13a.5.5 0 0 0-.5-.5"/></svg>
          <span>Dashboard</span>
        </button>
        <button class="list-group-item list-group-item-action" id="menu-inventory" title="Inventory">
          <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16"><path d="M8 1.5A1.5 1.5 0 0 0 6.5 0h-5A1.5 1.5 0 0 0 0 1.5v13A1.5 1.5 0 0 0 1.5 16h13a1.5 1.5 0 0 0 1.5-1.5v-13A1.5 1.5 0 0 0 14.5 0h-5A1.5 1.5 0 0 0 8 1.5M10 5a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0v-3A.5.5 0 0 1 10 5M8.5 4.5a.5.5 0 0 1 1 0v3a.5.5 0 0 1-1 0zM7 5.5a.5.5 0 0 1 1 0v3a.5.5 0 0 1-1 0zM5.5 5a.5.5 0 0 1 .5.5v3a.5.5 0 0 1-1 0v-3a.5.5 0 0 1 .5-.5"/></svg>
          <span>Inventory</span>
        </button>
        <button class="list-group-item list-group-item-action" id="menu-pos" title="POS">
          <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16"><path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5M3.102 4l1.313 7h8.17l1.313-7zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4m-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2m7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/></svg>
          <span>POS</span>
        </button>
        <?php if($role==='owner'): ?>
          <button class="list-group-item list-group-item-action" id="menu-branches" title="Branches">
            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16"><path d="M8.354 1.146a.5.5 0 0 0-.708 0l-6 6A.5.5 0 0 0 1.5 7.5v7a.5.5 0 0 0 .5.5h4.5a.5.5 0 0 0 .5-.5v-4h2v4a.5.5 0 0 0 .5.5H14a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.146-.354L13 1.146zM12 13H9.5v-4h-3v4H3.5V7.207l4.5-4.5 4.5 4.5z"/></svg>
            <span>Branches</span>
          </button>
          <button class="list-group-item list-group-item-action" id="menu-suppliers" title="Suppliers">
            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4m-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10s-3.516.68-4.168 1.332c-.678.678-.83 1.418-.832 1.664z"/></svg>
            <span>Suppliers</span>
          </button>
          <button class="list-group-item list-group-item-action" id="menu-accounts" title="Accounts">
            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4m-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10s-3.516.68-4.168 1.332c-.678.678-.83 1.418-.832 1.664z"/></svg>
            <span>Accounts</span>
          </button>
          <button class="list-group-item list-group-item-action" id="menu-logs" title="Logs">
            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16"><path d="M8.5 6.5a.5.5 0 0 0-1 0V10a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 0-1H9z"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0"/></svg>
            <span>Logs</span>
          </button>
        <?php endif; ?>
      </div>
    </div>

    <div class="main-content p-3">
      <div id="panel-dashboard" class="mb-3">
        <div class="card p-3">
          <h4 class="text-primary">Dashboard</h4>
          <div id="dashboard-grid" class="row g-3 mt-3"></div>
        </div>
      </div>

      <div id="panel-inventory" class="d-none mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h4 class="text-primary">Inventory</h4>
          <?php if($role==='owner'): ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">➕ Add Item</button>
          <?php endif; ?>
        </div>
        <div class="card p-3">
          <div class="d-flex mb-2 gap-2 align-items-center">
            <label for="filterBranch" class="mb-0 me-2">Branch:</label>
            <select id="filterBranch" class="form-select form-select-sm" style="width:220px"></select>
            <input id="inventorySearch" class="form-control form-control-sm ms-auto" placeholder="Search..." style="width: 200px;">
          </div>
          <div id="inventoryContent" class="table-responsive"></div>
        </div>
      </div>

      <div id="panel-branches" class="d-none mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h4 class="text-primary">Branches</h4>
          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBranchModal">➕ Add Branch</button>
        </div>
        <div id="branchesManage" class="row g-3"></div>
      </div>

      <div id="panel-suppliers" class="d-none mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h4 class="text-primary">Suppliers</h4>
          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSupplierModal">➕ Add Supplier</button>
        </div>
        <div id="suppliersContent" class="table-responsive"></div>
      </div>

      <div id="panel-accounts" class="d-none mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h4 class="text-primary">Accounts</h4>
          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#registerModal">➕ Add User</button>
        </div>
        <div class="card p-3">
          <div id="accountsContent" class="table-responsive"></div>
        </div>
      </div>

      <div id="panel-logs" class="d-none mb-3"><div class="d-flex justify-content-between align-items-center mb-2">
          <h4 class="text-primary">Logs</h4>
          <div class="btn-group">
            <button id="downloadExcel" class="btn btn-outline-success btn-sm" style="display: none;">Download Excel</button>
            <button id="showActionLogs" class="btn btn-outline-secondary btn-sm">Action Logs</button>
            <button id="showSalesLogs" class="btn btn-outline-secondary btn-sm">Sales Logs</button>
          </div>
        </div>
        <div class="card p-3">
          <div id="logsContent"></div>
        </div>
      </div>

      <div id="panel-pos" class="d-none mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h4 class="text-primary">Point of Sale</h4>
          <div class="d-flex gap-2 align-items-center">
            <label for="posBranchSelect" class="mb-0">Branch:</label>
            <select id="posBranchSelect" class="form-select form-select-sm" style="width:180px"></select>
            <input id="posSearch" class="form-control form-control-sm" placeholder="Search product..." style="width:260px">
          </div>
        </div>
        <div id="posProducts" class="row g-3"></div>
      </div>

    </div>
  </div>
  
<div style="position:fixed;right:20px;bottom:20px;z-index:9999">
  <button id="cartButton" class="btn btn-primary">🛒 Cart (<span id="cartCount">0</span>)</button>
</div>

<div class="modal fade" id="addItemModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form id="addItemForm" class="modal-content" enctype="multipart/form-data" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title">Add Item</h5></div>
      <div class="modal-body row g-2">
        <div class="col-md-6">
          <input class="form-control mb-2" name="item_name" placeholder="Item Name" required>
          <input class="form-control mb-2" name="sku" placeholder="SKU (Stock Keeping Unit)(optional)">
          <select class="form-select mb-2" name="category">
            <option value="">Select Category</option>
            <option value="helmet">Helmet</option>
            <option value="jacket">Jacket</option>
            <option value="topbox">Topbox</option>
            <option value="bracket">Bracket</option>
            <option value="others">Others</option>
          </select>
          <input type="number" step="0.01" class="form-control mb-2" name="price" placeholder="Price" required>
          <select class="form-select mb-2" name="branch_id" id="addItemBranchSelect" required></select>
        </div>
        <div class="col-md-6">
          <label class="small text-muted">Stock</label>
          <div class="input-group mb-2">
            <span class="input-group-text">S</span>
            <input type="number" class="form-control" name="stock_s" placeholder="Quantity" value="0">
          </div>
          <div class="input-group mb-2">
            <span class="input-group-text">M</span>
            <input type="number" class="form-control" name="stock_m" placeholder="Quantity" value="0">
          </div>
          <div class="input-group mb-2">
            <span class="input-group-text">L</span>
            <input type="number" class="form-control" name="stock_l" placeholder="Quantity" value="0">
          </div>
          <div class="input-group mb-2">
            <span class="input-group-text">XL</span>
            <input type="number" class="form-control" name="stock_xl" placeholder="Quantity" value="0">
          </div>
          <label class="small text-muted">Upload Image (optional)</label>
          <input type="file" class="form-control mb-2" name="photo" id="addItemPhoto">
          <img id="addItemPreview" class="img-fluid" src="" alt="" style="display:none;max-height:160px">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save Item</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="editItemModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form id="editItemForm" class="modal-content" enctype="multipart/form-data" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title">Edit Item</h5></div>
      <div class="modal-body row g-2">
        <input type="hidden" name="id" id="editItemId">
        <div class="col-md-6">
          <input class="form-control mb-2" name="name" id="editItemName" placeholder="Item Name" required>
          <select class="form-select mb-2" name="category" id="editItemCategory">
            <option value="">Select Category</option>
            <option value="helmet">Helmet</option>
            <option value="jacket">Jacket</option>
            <option value="topbox">Topbox</option>
            <option value="bracket">Bracket</option>
            <option value="others">Others</option>
          </select>
          <input type="number" step="0.01" class="form-control mb-2" name="price" id="editItemPrice" placeholder="Price" required>
          <select class="form-select mb-2" name="branch_id" id="editItemBranchSelect" required></select>
        </div>
        <div class="col-md-6">
          <label class="small text-muted">Stock</label>
          <div class="input-group mb-2">
            <span class="input-group-text">S</span>
            <input type="number" class="form-control" name="stock_s" id="editItemStockS" placeholder="Quantity" value="0">
          </div>
          <div class="input-group mb-2">
            <span class="input-group-text">M</span>
            <input type="number" class="form-control" name="stock_m" id="editItemStockM" placeholder="Quantity" value="0">
          </div>
          <div class="input-group mb-2">
            <span class="input-group-text">L</span>
            <input type="number" class="form-control" name="stock_l" id="editItemStockL" placeholder="Quantity" value="0">
          </div>
          <div class="input-group mb-2">
            <span class="input-group-text">XL</span>
            <input type="number" class="form-control" name="stock_xl" id="editItemStockXL" placeholder="Quantity" value="0">
          </div>
          <label class="small text-muted">Current Image</label>
          <img id="editItemCurrentImg" class="img-fluid" src="" alt="" style="display:none;max-height:160px">
          <label class="small text-muted mt-2">Replace Image</label>
          <input type="file" class="form-control mb-2" name="photo" id="editItemPhoto">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save changes</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="addBranchModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="addBranchForm" class="modal-content" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title">Add Branch</h5></div>
      <div class="modal-body">
        <input id="addBranchName" class="form-control mb-2" name="branch_name" placeholder="Branch name" required>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Create</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="editBranchModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="editBranchForm" class="modal-content" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title">Edit Branch</h5></div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editBranchId">
        <input class="form-control mb-2" id="editBranchName" name="branch_name" placeholder="Branch name" required>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="addSupplierModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="addSupplierForm" class="modal-content" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title">Add Supplier</h5></div>
      <div class="modal-body">
        <input class="form-control mb-2" name="supplier_name" placeholder="Supplier Name" required>
        <input type="email" class="form-control mb-2" name="email" placeholder="Email">
        <input class="form-control mb-2" name="phone" placeholder="Phone Number">
        <input class="form-control mb-2" name="location" placeholder="Location">
        <textarea class="form-control" name="products" placeholder="Products"></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save Supplier</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="editSupplierModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="editSupplierForm" class="modal-content" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title">Edit Supplier</h5></div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editSupplierId">
        <input class="form-control mb-2" name="supplier_name" id="editSupplierName" placeholder="Supplier Name" required>
        <input type="email" class="form-control mb-2" name="email" id="editSupplierEmail" placeholder="Email">
        <input class="form-control mb-2" name="phone" id="editSupplierPhone" placeholder="Phone Number">
        <input class="form-control mb-2" name="location" id="editSupplierLocation" placeholder="Location">
        <textarea class="form-control" name="products" id="editSupplierProducts" placeholder="Products"></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="registerModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="registerForm" class="modal-content" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title">Add User</h5></div>
      <div class="modal-body">
        <input class="form-control mb-2" name="username" placeholder="Username" required>
        <input type="password" class="form-control mb-2" name="password" placeholder="Password" required>
        <select name="role" class="form-select mb-2">
          <option value="staff">Staff</option>
          <option value="owner">Owner</option>
        </select>
        <select name="branch_id" class="form-select mb-2" id="userBranchSelect">
          <option value="">-- Assign to branch (optional) --</option>
        </select>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Create</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="editUserForm" class="modal-content" autocomplete="off">
      <div class="modal-header"><h5 class="modal-title">Edit User</h5></div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editUserId">
        <input class="form-control mb-2" name="username" id="editUserName" placeholder="Username" required>
        <select name="role" class="form-select mb-2" id="editUserRole">
          <option value="staff">Staff</option>
          <option value="owner">Owner</option>
        </select>
        <select name="branch_id" class="form-select mb-2" id="editUserBranchSelect">
          <option value="">-- Assign to branch (optional) --</option>
        </select>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="checkoutModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Checkout</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="checkoutItems"></div>
        <hr>
        <div class="d-flex justify-content-between fw-bold">
          <span>Total:</span>
          <span>₱<span id="checkoutTotal">0.00</span></span>
        </div>
        <div class="mt-3">
          <label>Payment Method</label>
          <select id="paymentMode" class="form-select">
            <option>Cash</option>
            <option>GCash</option>
            <option>Credit Card</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button id="confirmCheckout" class="btn btn-primary">Confirm</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="receiptModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Receipt</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <pre id="receiptContent"></pre>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="printReceiptButton">🖨️ Print</button>
      </div>
    </div>
  </div>
</div>

<footer class="text-center text-muted small py-3">
  © 2025 Motify. All rights reserved
</footer>

<script>const USER_ROLE = <?= json_encode($role) ?>; const ASSIGNED_BRANCH = <?= json_encode($assigned_branch) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="script.js"></script>
</body>
</html>