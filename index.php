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

<div class="container-fluid mt-3">
  <div class="row">
    <div class="col-md-2">
      <div class="list-group">
        <button class="list-group-item list-group-item-action active" id="menu-dashboard">Dashboard</button>
        <button class="list-group-item list-group-item-action" id="menu-inventory">Inventory</button>
        <button class="list-group-item list-group-item-action" id="menu-pos">POS</button>
        <?php if($role==='owner'): ?>
          <button class="list-group-item list-group-item-action" id="menu-branches">Branches</button>
          <button class="list-group-item list-group-item-action" id="menu-accounts">Accounts</button>
          <button class="list-group-item list-group-item-action" id="menu-logs">Logs</button>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-md-10">
      <div id="panel-dashboard" class="mb-3">
        <div class="card p-3">
          <div class="d-flex justify-content-between align-items-center">
            <h4 class="text-primary">Dashboard</h4>
            <button class="btn btn-sm btn-outline-primary" id="refreshDashboard">Refresh</button>
          </div>
          <div id="stats" class="row g-3 mt-3"></div>
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
            <input id="inventorySearch" class="form-control form-control-sm ms-auto" placeholder="Search...">
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

      <div id="panel-accounts" class="d-none mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h4 class="text-primary">Accounts</h4>
          <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#registerModal">➕ Add User</button>
        </div>
        <div class="card p-3">
          <div id="accountsContent" class="table-responsive"></div>
        </div>
      </div>

      <div id="panel-logs" class="d-none mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h4 class="text-primary">Logs</h4>
          <div class="btn-group">
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
          <input class="form-control mb-2" name="sku" placeholder="SKU (optional)">
          <input class="form-control mb-2" name="category" placeholder="Category">
          <input type="number" class="form-control mb-2" name="quantity" placeholder="Quantity" required>
          <input type="number" step="0.01" class="form-control mb-2" name="price" placeholder="Price" required>
          <select class="form-select mb-2" name="branch_id" id="addItemBranchSelect" required></select>
        </div>
        <div class="col-md-6">
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
          <input class="form-control mb-2" name="category" id="editItemCategory" placeholder="Category">
          <input type="number" class="form-control mb-2" name="stock" id="editItemQty" placeholder="Quantity" required>
          <input type="number" step="0.01" class="form-control mb-2" name="price" id="editItemPrice" placeholder="Price" required>
          <select class="form-select mb-2" name="branch_id" id="editItemBranchSelect" required></select>
        </div>
        <div class="col-md-6">
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


<script>const USER_ROLE = <?= json_encode($role) ?>; const ASSIGNED_BRANCH = <?= json_encode($assigned_branch) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="script.js"></script>
</body>
</html>