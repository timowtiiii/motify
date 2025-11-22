
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
    .sidebar { width: 100px; }
    .sidebar .list-group-item { text-align: center; padding: 0.75rem 0.5rem; }
    .sidebar .list-group-item span { display: block; font-size: 0.7rem; margin-top: 5px; }
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
          <button class="list-group-item list-group-item-action" id="menu-consignment" title="Consignment">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-seam" viewBox="0 0 16 16"><path d="M8.186 1.113a.5.5 0 0 0-.372 0L1.846 3.5 8 5.961 14.154 3.5 8.186 1.113zM15 4.239l-6.5 2.6v7.922l6.5-2.6V4.24zM7.5 14.762V6.838L1 4.239v7.923l6.5 2.6zM7.443.184a1.5 1.5 0 0 1 1.114 0l7.129 2.852A.5.5 0 0 1 16 3.5v8.662a1 1 0 0 1-.629.928l-7.185 2.874a.5.5 0 0 1-.372 0L.63 13.09a1 1 0 0 1-.63-.928V3.5a.5.5 0 0 1 .314-.464L7.443.184z"/></svg>
            <span>Consignment</span>
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
          <div id="inventoryCategoryFilter" class="mb-3 d-flex flex-wrap gap-2">
            <button class="btn btn-sm btn-secondary active" data-category="helmet">Helmet</button>
            <button class="btn btn-sm btn-outline-secondary" data-category="jacket">Jacket</button>
            <button class="btn btn-sm btn-outline-secondary" data-category="topbox">Topbox</button>
            <button class="btn btn-sm btn-outline-secondary" data-category="bracket">Bracket</button>
            <button class="btn btn-sm btn-outline-secondary" data-category="others">Others</button>
          </div>
          <div class="d-flex flex-row gap-2 mb-3">
            <select id="filterBranch" class="form-select form-select-sm" style="width:220px"></select>
            <select id="inventoryStockLevelFilter" class="form-select form-select-sm" style="width:220px">
                <option value="">All Stock Levels</option>
                <option value="high">High Stock</option>
                <option value="low">Low Stock (5 or less)</option>
                <option value="out">Out of Stock</option>
            </select>
            <input id="inventorySearch" class="form-control form-control-sm" placeholder="Search..." style="width: 200px;">
          </div>
          <div id="inventoryContent" class="table-responsive"></div>
        </div>
      </div>

      <div id="panel-branches" class="d-none mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h4 class="text-primary">Branches</h4>
          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBranchModal">➕ Add Branch</button>
        </div>
        <div class="card p-3">
          <div id="branchesManage" class="table-responsive"></div>
        </div>
      </div>

      <div id="panel-suppliers" class="d-none mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h4 class="text-primary">Suppliers</h4>
          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSupplierModal">➕ Add Supplier</button>
        </div>
        <div class="card p-3">
          <div id="suppliersContent" class="table-responsive"></div>
        </div>
      </div>

      <div id="panel-consignment" class="d-none">
          <div class="d-flex justify-content-between align-items-center mb-3">
              <h4 class="text-primary">Consignment Management</h4>
              <button class="btn btn-primary" id="addConsignmentBtn">Add Consignment</button>
          </div>
  
          <div class="card mb-3">
              <div class="card-body">
                  <div class="row g-3 align-items-center">
                      <div class="col-md-4">
                          <label for="consignmentSupplierFilter" class="form-label">Filter by Supplier</label>
                          <select class="form-select supplier-select" id="consignmentSupplierFilter"></select>
                      </div>
                      <div class="col-md-4">
                          <label class="form-label">Total Owed to Suppliers</label>
                          <h4 class="mb-0">₱<span id="totalOwed">0.00</span></h4>
                      </div>
                  </div>
              </div>
          </div>
  
          <div class="card">
              <div class="card-header">
                  Consigned Items
              </div>
              <div class="card-body" id="consignmentContent">
                  <!-- Consignment list will be loaded here by script.js -->
                  <div class="text-center text-muted">Loading consignments...</div>
              </div>
          </div>
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
          <div class="d-flex align-items-center">
            <div id="logButtons" class="btn-group">
              <button id="downloadPdf" class="btn btn-outline-success btn-sm" style="display: none;">Download PDF</button>
              <button id="showActionLogs" class="btn btn-outline-secondary btn-sm">Action Logs</button>
              <button id="showSalesLogs" class="btn btn-outline-secondary btn-sm active">Sales Logs</button>
            </div>
            <div id="logsTimeRangeContainer" class="input-group input-group-sm ms-2" style="width: 300px;">
              <!-- This container will be populated by script.js -->
            </div>
          </div>
        </div>
        <div class="card p-3">
          <div id="logsContent" class="table-responsive"></div>
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
        <div id="posCategoryFilter" class="mb-3 d-flex flex-wrap gap-2">
            <button class="btn btn-sm btn-secondary active" data-category="">All</button>
            <button class="btn btn-sm btn-outline-secondary" data-category="helmet">Helmet</button>
            <button class="btn btn-sm btn-outline-secondary" data-category="jacket">Jacket</button>
            <button class="btn btn-sm btn-outline-secondary" data-category="topbox">Topbox</button>
            <button class="btn btn-sm btn-outline-secondary" data-category="bracket">Bracket</button>
            <button class="btn btn-sm btn-outline-secondary" data-category="others">Others</button>
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
    <form id="addItemForm" class="modal-content" enctype="multipart/form-data" autocomplete="off" novalidate>
      <div class="modal-header"><h5 class="modal-title">Add Item</h5></div>
      <div class="modal-body row g-2">
        <div class="col-md-6">
          <div class="mb-2">
            <input class="form-control" name="item_name" placeholder="Item Name" required>
            <div class="invalid-feedback">Item name is required.</div>
          </div>
          <input class="form-control mb-2" name="sku" placeholder="SKU (Stock Keeping Unit)(optional)">
          <div class="mb-2">
            <select class="form-select" name="category" required>
              <option value="">Select Category</option>
              <option value="helmet">Helmet</option>
              <option value="jacket">Jacket</option>
              <option value="topbox">Topbox</option>
              <option value="bracket">Bracket</option>
              <option value="others">Others</option>
            </select>
            <div class="invalid-feedback">Please select a category.</div>
          </div>
          <input type="number" step="0.01" min="0" class="form-control mb-2" name="price" placeholder="Price" required>
          <div class="invalid-feedback">Please enter a valid price.</div>
          <select class="form-select mb-2" name="branch_id" id="addItemBranchSelect" required>
          </select>
          <div class="invalid-feedback">Please select a branch.</div>
        </div>
        <div class="col-md-6">
          <div id="addItemOthersStockTypeContainer" class="d-none">
            <select class="form-select mb-2" name="others_stock_type">
              <option value="">Select Stock Type</option>
              <option value="sizes">Stock by Size</option>
              <option value="regular">Regular Stock</option>
            </select>
          </div>
          <div id="addItemSizeStock">
            <label class="small text-muted">Stock by Size</label>
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
          </div>
          <div id="addItemRegularStock" class="d-none">
            <label class="small text-muted">Stock</label>
            <input type="number" class="form-control" name="stock_regular" placeholder="Quantity" value="0">
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
    <form id="editItemForm" class="modal-content" enctype="multipart/form-data" autocomplete="off" novalidate>
      <div class="modal-header"><h5 class="modal-title">Edit Item</h5></div>
      <div class="modal-body row g-2">
        <input type="hidden" name="id" id="editItemId">
        <div class="col-md-6">
          <div class="mb-2">
            <input class="form-control" name="name" id="editItemName" placeholder="Item Name" required>
            <div class="invalid-feedback">Item name is required.</div>
          </div>
          <div class="mb-2">
            <select class="form-select" name="category" id="editItemCategory" required>
              <option value="">Select Category</option>
              <option value="helmet">Helmet</option>
              <option value="jacket">Jacket</option>
              <option value="topbox">Topbox</option>
              <option value="bracket">Bracket</option>
              <option value="others">Others</option>
            </select>
            <div class="invalid-feedback">Please select a category.</div>
          </div>
          <input type="number" step="0.01" min="0" class="form-control mb-2" name="price" id="editItemPrice" placeholder="Price" required>
          <div class="invalid-feedback">Please enter a valid price.</div>
          <select class="form-select mb-2" name="branch_id" id="editItemBranchSelect" required>
          </select>
          <div class="invalid-feedback">Please select a branch.</div>
        </div>
        <div class="col-md-6">
          <div id="editItemOthersStockTypeContainer" class="d-none">
            <select class="form-select mb-2" name="others_stock_type">
              <option value="">Select Stock Type</option>
              <option value="sizes">Stock by Size</option>
              <option value="regular">Regular Stock</option>
            </select>
          </div>
          <div id="editItemSizeStock">
            <label class="small text-muted">Stock by Size</label>
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
          </div>
          <div id="editItemRegularStock" class="d-none">
            <label class="small text-muted">Stock</label>
            <input type="number" class="form-control" name="stock_regular" id="editItemStockRegular" placeholder="Quantity" value="0">
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
    <form id="addBranchForm" class="modal-content" autocomplete="off" novalidate>
      <div class="modal-header"><h5 class="modal-title">Add Branch</h5></div>
      <div class="modal-body">
        <div class="mb-2">
          <input id="addBranchName" class="form-control" name="branch_name" placeholder="Branch name" required>
          <div class="invalid-feedback">Branch name is required.</div>
        </div>
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
    <form id="editBranchForm" class="modal-content" autocomplete="off" novalidate>
      <div class="modal-header"><h5 class="modal-title">Edit Branch</h5></div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editBranchId">
        <div class="mb-2">
          <input class="form-control" id="editBranchName" name="branch_name" placeholder="Branch name" required>
          <div class="invalid-feedback">Branch name is required.</div>
        </div>
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
    <form id="addSupplierForm" class="modal-content" autocomplete="off" novalidate>
      <div class="modal-header"><h5 class="modal-title">Add Supplier</h5></div>
      <div class="modal-body">
        <div class="mb-2">
          <input class="form-control" name="supplier_name" placeholder="Supplier Name" required>
          <div class="invalid-feedback">Supplier name is required.</div>
        </div>
        <input type="email" class="form-control mb-2" name="email" placeholder="Email (optional)">
        <input type="tel" class="form-control mb-2" name="phone" placeholder="Phone Number (e.g., +63 912 345 6789)" pattern="[0-9\+\-\(\)\s]*" title="Numbers and signs like +, -, () are allowed.">
        <input class="form-control mb-2" name="location" placeholder="Location">
        <textarea class="form-control mb-2" name="brands" placeholder="Brands"></textarea>
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
    <form id="editSupplierForm" class="modal-content" autocomplete="off" novalidate>
      <div class="modal-header"><h5 class="modal-title">Edit Supplier</h5></div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editSupplierId">
        <div class="mb-2">
          <input class="form-control" name="supplier_name" id="editSupplierName" placeholder="Supplier Name" required>
          <div class="invalid-feedback">Supplier name is required.</div>
        </div>
        <input type="email" class="form-control mb-2" name="email" id="editSupplierEmail" placeholder="Email (optional)">
        <input type="tel" class="form-control mb-2" name="phone" id="editSupplierPhone" placeholder="Phone Number (e.g., +63 912 345 6789)" pattern="[0-9\+\-\(\)\s]*" title="Numbers and signs like +, -, () are allowed.">
        <input class="form-control mb-2" name="location" id="editSupplierLocation" placeholder="Location">
        <textarea class="form-control mb-2" name="brands" id="editSupplierBrands" placeholder="Brands"></textarea>
        <textarea class="form-control" name="products" id="editSupplierProducts" placeholder="Products"></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Consignment Modal -->
<div class="modal fade" id="addConsignmentModal" tabindex="-1" aria-labelledby="addConsignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addConsignmentModalLabel">Add New Consignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addConsignmentForm" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="consignmentSupplier" class="form-label">Supplier</label>
                        <select class="form-select supplier-select" id="consignmentSupplier" name="supplier_id" required></select>
                        <div class="invalid-feedback">Please select a supplier.</div>
                    </div>
                    <div class="mb-3">
                        <label for="consignmentBranch" class="form-label">Assign to Branch</label>
                        <select class="form-select" id="consignmentBranch" name="branch_id" required></select>
                        <div class="invalid-feedback">Please select a branch.</div>
                    </div>
                    <div class="mb-3">
                        <label for="consignmentProduct" class="form-label">Product</label>
                        <select class="form-select" id="consignmentProduct" name="product_id" required></select>
                        <div class="invalid-feedback">Please select a product.</div>
                    </div>
                    <div class="mb-3">
                        <label for="consignmentCostPrice" class="form-label">Cost Price (per item)</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="consignmentCostPrice" name="cost_price" required>
                            <div class="invalid-feedback">Please enter a valid cost price.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="consignmentQuantity" class="form-label">Quantity Consigned</label>
                        <input type="number" class="form-control" id="consignmentQuantity" name="quantity_consigned" required min="1">
                        <div class="invalid-feedback">Please enter a quantity of at least 1.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Consignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Consignment Modal -->
<div class="modal fade" id="editConsignmentModal" tabindex="-1" aria-labelledby="editConsignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editConsignmentModalLabel">Edit Consignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editConsignmentForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="id" id="editConsignmentId">
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <p id="editConsignmentProduct" class="form-control-plaintext"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Supplier</label>
                        <p id="editConsignmentSupplier" class="form-control-plaintext"></p>
                    </div>
                    <div class="mb-3">
                        <label for="editConsignmentCostPrice" class="form-label">Cost Price (per item)</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="editConsignmentCostPrice" name="cost_price" required>
                            <div class="invalid-feedback">Please enter a valid cost price.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="editConsignmentQuantity" class="form-label">Quantity Consigned</label>
                        <input type="number" class="form-control" id="editConsignmentQuantity" name="quantity_consigned" required min="1"><div class="invalid-feedback">Quantity must be at least 1.</div>
                        <div class="form-text">Note: Changing this will adjust the main product stock.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="registerModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="registerForm" class="modal-content" autocomplete="off" novalidate>
      <div class="modal-header"><h5 class="modal-title">Add User</h5></div>
      <div class="modal-body">
        <div class="mb-2">
          <input class="form-control" name="username" placeholder="Username" required>
          <div class="invalid-feedback">Username is required.</div>
        </div>
        <div class="mb-2">
          <input type="password" class="form-control" name="password" placeholder="Password" required minlength="8">
          <div class="invalid-feedback">Password is required and must be at least 8 characters.</div>
        </div>
        <div id="registerEmailGroup" class="mb-2 d-none">
          <label for="registerEmail" class="form-label small text-muted">Email (Required for Owner)</label>
          <input type="email" class="form-control" id="registerEmail" name="email" placeholder="owner@example.com">
          <div class="invalid-feedback">A valid email is required for owners.</div>
        </div>
        <select name="role" id="registerRole" class="form-select mb-2">
          <option value="staff">Staff</option>
          <option value="owner">Owner</option>
        </select>
        <div class="mb-2">
          <select name="branch_id" class="form-select" id="userBranchSelect">
            <option value="">-- Assign to branch (optional) --</option>
          </select>
        </div>
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
    <form id="editUserForm" class="modal-content" autocomplete="off" novalidate>
      <div class="modal-header"><h5 class="modal-title">Edit User</h5></div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editUserId">
        <div class="mb-2">
          <input class="form-control" name="username" id="editUserName" placeholder="Username" required>
          <div class="invalid-feedback">Username is required.</div>
        </div>
        <div id="editEmailGroup" class="mb-2 d-none">
          <label for="editUserEmail" class="form-label small text-muted">Email (Required for Owner)</label>
          <input type="email" class="form-control" id="editUserEmail" name="email" placeholder="owner@example.com">
          <div class="invalid-feedback">A valid email is required for owners.</div>
        </div>
        <select name="role" class="form-select mb-2" id="editUserRole">
          <option value="staff">Staff</option>
          <option value="owner">Owner</option>
        </select>
        <div class="mb-2">
          <select name="branch_id" class="form-select" id="editUserBranchSelect">
            <option value="">-- Assign to branch (optional) --</option>
          </select>
        </div>
        <label for="editUserPassword" class="form-label small text-muted">New Password (Optional - min 8 characters)</label>
        <input type="password" class="form-control" id="editUserPassword" name="password" placeholder="Leave blank to keep current password" minlength="8">
        <div class="invalid-feedback">Password must be at least 8 characters.</div>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="script.js"></script>
<script>
    // Set the default value of the week input to the current week
    document.addEventListener('DOMContentLoaded', function() {
        const weekInput = document.getElementById('week-selector');
        const now = new Date();
        const year = now.getFullYear();
        const week = Math.ceil((((now - new Date(year, 0, 1)) / 86400000) + new Date(year, 0, 1).getDay() + 1) / 7);
        if(weekInput) weekInput.value = `${year}-W${String(week).padStart(2, '0')}`;
    });

    // Function to handle panel switching
    function handleMenuClick(e) {
        const targetId = e.currentTarget.id.replace('menu-', '');
        document.querySelectorAll('.sidebar .list-group-item').forEach(btn => btn.classList.remove('active'));
        e.currentTarget.classList.add('active');
        document.querySelectorAll('.main-content > div[id^="panel-"]').forEach(panel => panel.classList.add('d-none'));
        const activePanel = document.getElementById(`panel-${targetId}`);
        if (activePanel) {
          activePanel.classList.remove('d-none');          // If the consignment panel is now active, load its data.
          // This assumes you have a `loadConsignments` function in script.js
          if (targetId === 'consignment' && typeof loadConsignments === 'function') {
            loadConsignments();
          }
        }
    }

    // Attach event listeners to all sidebar menu buttons
    document.querySelectorAll('.sidebar .list-group-item').forEach(button => {
        button.removeEventListener('click', handleMenuClick); // Prevent duplicate listeners
        button.addEventListener('click', handleMenuClick);
    });
</script>
</body>
</html>