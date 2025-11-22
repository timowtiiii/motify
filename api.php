<?php
// api.php - central router
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';
session_start();
date_default_timezone_set('Asia/Manila');

function jsonRes($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function is_owner(){ return (isset($_SESSION['role']) && $_SESSION['role']==='owner'); }
$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

try {
  switch($action){

    // ---------------- BRANCHES ----------------

    case 'get_branches':
      $res = $mysqli->query("SELECT id,name FROM branches ORDER BY id ASC");
      $out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
      jsonRes(['ok'=>true,'branches'=>$out]);
      break;

    case 'add_branch':
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $name = trim($_POST['branch_name'] ?? '');
      if($name==='') jsonRes(['ok'=>false,'error'=>'missing name']);
      $stmt = $mysqli->prepare("INSERT INTO branches (name, created_at) VALUES (?, NOW())");
      $stmt->bind_param('s',$name);
      if($stmt->execute()) jsonRes(['ok'=>true,'id'=>$stmt->insert_id]);
      jsonRes(['ok'=>false,'error'=>$mysqli->error]);
      break;

    case 'edit_branch':
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $id = intval($_POST['id'] ?? 0);
      $name = trim($_POST['branch_name'] ?? '');
      if(!$id || $name==='') jsonRes(['ok'=>false,'error'=>'invalid']);
      $stmt = $mysqli->prepare("UPDATE branches SET name=? WHERE id=?");
      $stmt->bind_param('si',$name,$id);
      jsonRes(['ok'=>$stmt->execute()]);
      break;

    case 'delete_branch':
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $id = intval($_POST['id'] ?? 0);
      if(!$id) jsonRes(['ok'=>false,'error'=>'missing id']);
      $stmt = $mysqli->prepare("DELETE FROM branches WHERE id=?");
      $stmt->bind_param('i',$id);
      jsonRes(['ok'=>$stmt->execute()]);
      break;

    // --------------------------------------------------------------------------------------------------------------------

    // ---------------- SUPPLIERS ----------------
    case 'get_suppliers':
      $res = $mysqli->query("SELECT id,name,email,phone,location,brands,products FROM suppliers ORDER BY id ASC");
      $out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
      jsonRes(['ok'=>true,'suppliers'=>$out]);
      break;

    case 'add_supplier':
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $name = trim($_POST['supplier_name'] ?? '');
      if($name==='') jsonRes(['ok'=>false,'error'=>'missing name']);
      $email = trim($_POST['email'] ?? '');
      $phone = trim($_POST['phone'] ?? '');
      $location = trim($_POST['location'] ?? '');
      $brands = trim($_POST['brands'] ?? '');
      $products = trim($_POST['products'] ?? '');
      $stmt = $mysqli->prepare("INSERT INTO suppliers (name, email, phone, location, brands, products, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
      $stmt->bind_param('ssssss',$name, $email, $phone, $location, $brands, $products);
      if($stmt->execute()) jsonRes(['ok'=>true,'id'=>$stmt->insert_id]);
      jsonRes(['ok'=>false,'error'=>$mysqli->error]);
      break;

    case 'delete_supplier':
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $id = intval($_POST['id'] ?? 0);
      if(!$id) jsonRes(['ok'=>false,'error'=>'missing id']);
      $stmt = $mysqli->prepare("DELETE FROM suppliers WHERE id=?");
      $stmt->bind_param('i',$id);
      jsonRes(['ok'=>$stmt->execute()]);
      break;

    case 'edit_supplier':
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $id = intval($_POST['id'] ?? 0);
      if(!$id) jsonRes(['ok'=>false,'error'=>'missing id']);
      $name = trim($_POST['supplier_name'] ?? '');
      if($name==='') jsonRes(['ok'=>false,'error'=>'missing name']);
      $email = trim($_POST['email'] ?? '');
      $phone = trim($_POST['phone'] ?? '');
      $location = trim($_POST['location'] ?? '');
      $brands = trim($_POST['brands'] ?? '');
      $products = trim($_POST['products'] ?? '');
      $stmt = $mysqli->prepare("UPDATE suppliers SET name=?, email=?, phone=?, location=?, brands=?, products=? WHERE id=?");
      $stmt->bind_param('ssssssi', $name, $email, $phone, $location, $brands, $products, $id);
      if($stmt->execute()) jsonRes(['ok'=>true]);
      jsonRes(['ok'=>false,'error'=>$mysqli->error]);
      break;

    // ---------------- CONSIGNMENT ----------------
    case 'get_consignments':
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $supplier_id = isset($_GET['supplier_id']) && $_GET['supplier_id'] !== '' ? intval($_GET['supplier_id']) : null;
      
      $sql = "SELECT c.*, p.name as product_name, s.name as supplier_name, b.name as branch_name
              FROM consignments c 
              JOIN products p ON c.product_id = p.id 
              JOIN suppliers s ON c.supplier_id = s.id
              LEFT JOIN branches b ON c.branch_id = b.id";
      
      if ($supplier_id) {
          $sql .= " WHERE c.supplier_id = " . $supplier_id;
      }
      
      $sql .= " ORDER BY c.created_at DESC";
      
      $res = $mysqli->query($sql);
      if (!$res) jsonRes(['ok' => false, 'error' => $mysqli->error]);
      if (!$res) {
          error_log("MySQL Error in get_consignments: " . $mysqli->error . " Query: " . $sql);
          jsonRes(['ok' => false, 'error' => 'Database query failed: ' . $mysqli->error]);
      }
      
      $out = [];
      while ($r = $res->fetch_assoc()) {
          // To determine the size, we need to check the product's stock entries
          $stock_res = $mysqli->query("SELECT size FROM product_stocks WHERE product_id = " . intval($r['product_id']));
          $sizes = [];
          while($stock_row = $stock_res->fetch_assoc()){
            if (!$stock_row) { // Defensive check, though fetch_assoc usually returns false on no more rows
                error_log("MySQL Error in get_consignments (product_stocks fetch_assoc): " . $mysqli->error . " Product ID: " . intval($r['product_id']));
                continue; // Skip this stock row if there's an issue
            }
            $sizes[] = $stock_row['size'];
          }
          if(count($sizes) > 1 && in_array('os', $sizes)){ // if 'os' and other sizes exist, filter out 'os'
            $sizes = array_filter($sizes, fn($s) => $s !== 'os');
          }

          $r['size'] = !empty($sizes) ? implode(', ', $sizes) : 'N/A';
          $out[] = $r;
      }
      jsonRes(['ok'=>true,'consignments'=>$out]);
      break;

    case 'add_consignment':
        if($method!=='POST' || !is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
        $product_id = intval($_POST['product_id'] ?? 0);
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        $cost_price = floatval($_POST['cost_price'] ?? 0);
        $branch_id = intval($_POST['branch_id'] ?? 0);
        $quantity = intval($_POST['quantity_consigned'] ?? 0);

        if (!$product_id || !$supplier_id || !$branch_id || $cost_price <= 0 || $quantity <= 0) jsonRes(['ok' => false, 'error' => 'Invalid input. All fields are required.']);

        $mysqli->begin_transaction();

        // Insert into consignments table
        $stmt = $mysqli->prepare("INSERT INTO consignments (product_id, supplier_id, branch_id, cost_price, quantity_consigned) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('iiidi', $product_id, $supplier_id, $branch_id, $cost_price, $quantity);
        if (!$stmt->execute()) { $mysqli->rollback(); jsonRes(['ok' => false, 'error' => 'Failed to create consignment record.']); }

        // Update product stock
        $stock_stmt = $mysqli->prepare("UPDATE product_stocks SET quantity = quantity + ? WHERE product_id = ?");
        $stock_stmt->bind_param('ii', $quantity, $product_id);
        if (!$stock_stmt->execute()) { $mysqli->rollback(); jsonRes(['ok' => false, 'error' => 'Failed to update product stock.']); }

        $mysqli->commit();
        jsonRes(['ok' => true]);
        break;

    case 'mark_consignment_paid':
        if($method!=='POST' || !is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
        $id = intval($_POST['id'] ?? 0);
        if (!$id) jsonRes(['ok' => false, 'error' => 'Invalid ID.']);
        $stmt = $mysqli->prepare("UPDATE consignments SET status = 'paid' WHERE id = ?");
        $stmt->bind_param('i', $id);
        jsonRes(['ok' => $stmt->execute()]);
        break;

    case 'edit_consignment':
        if($method!=='POST' || !is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
        $id = intval($_POST['id'] ?? 0);
        $cost_price = floatval($_POST['cost_price'] ?? 0);
        $new_quantity = intval($_POST['quantity_consigned'] ?? 0);

        if (!$id || $cost_price <= 0 || $new_quantity <= 0) jsonRes(['ok' => false, 'error' => 'Invalid input.']);

        $mysqli->begin_transaction();

        // Get current consignment details to calculate stock difference
        $stmt = $mysqli->prepare("SELECT product_id, quantity_consigned, quantity_sold FROM consignments WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $current_consignment = $res->fetch_assoc();

        if (!$current_consignment) { $mysqli->rollback(); jsonRes(['ok' => false, 'error' => 'Consignment not found.']); }
        if ($new_quantity < $current_consignment['quantity_sold']) { $mysqli->rollback(); jsonRes(['ok' => false, 'error' => 'New quantity cannot be less than the quantity already sold (' . $current_consignment['quantity_sold'] . ').']); }

        $product_id = $current_consignment['product_id'];
        $quantity_diff = $new_quantity - $current_consignment['quantity_consigned'];

        // Update the consignment record
        $update_stmt = $mysqli->prepare("UPDATE consignments SET cost_price = ?, quantity_consigned = ? WHERE id = ?");
        $update_stmt->bind_param('dii', $cost_price, $new_quantity, $id);
        if (!$update_stmt->execute()) { $mysqli->rollback(); jsonRes(['ok' => false, 'error' => 'Failed to update consignment record.']); }

        // Adjust the product stock based on the difference
        $stock_stmt = $mysqli->prepare("UPDATE product_stocks SET quantity = quantity + ? WHERE product_id = ?");
        $stock_stmt->bind_param('ii', $quantity_diff, $product_id);
        if (!$stock_stmt->execute()) { $mysqli->rollback(); jsonRes(['ok' => false, 'error' => 'Failed to update product stock.']); }

        $mysqli->commit();
        jsonRes(['ok' => true]);
        break;

    // --------------------------------------------------------------------------------------------------------------------

    // ---------------- PRODUCTS ----------------
    case 'get_products':
        $q = trim($_GET['q'] ?? $_REQUEST['q'] ?? '');
        $source = $_GET['source'] ?? ''; // 'pos' or 'inventory'
        $category = trim($_GET['category'] ?? '');
        $stock_level = trim($_GET['stock_level'] ?? '');

        // branch filter: if staff, default to assigned branch
        $branch = null;
        if (isset($_GET['branch_id'])) {
            $branch = $_GET['branch_id'] !== '' ? intval($_GET['branch_id']) : null;
        } elseif (isset($_REQUEST['branch_id'])) {
            $branch = $_REQUEST['branch_id'] !== '' ? intval($_REQUEST['branch_id']) : null;
        }

        // If staff, enforce assigned branch in POS queries when requested
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'staff' && $source === 'pos') {
            $assigned = $_SESSION['assigned_branch_id'] ?? null;
            if ($assigned) {
                $branch = intval($assigned);
            }
        }

        $join_clause = "LEFT JOIN branches b ON p.branch_id = b.id";
        $where_conditions = "1=1";

        if ($stock_level !== '') {
            $join_clause .= " JOIN product_stocks ps ON p.id = ps.product_id";
            if ($stock_level === 'low') {
                $where_conditions .= " AND ps.quantity <= 5 AND ps.quantity > 0";
            } elseif ($stock_level === 'out') {
                $where_conditions .= " AND ps.quantity = 0";
            } elseif ($stock_level === 'high') {
                $where_conditions .= " AND ps.quantity > 10";
            }
        }
        $sql = "SELECT 
                    p.id, p.sku, p.name, p.category, p.price, p.branch_id, 
                    COALESCE(p.photo, '') AS photo, 
                    b.name AS branch_name,                    
                    (SELECT SUM(c.quantity_consigned - c.quantity_sold) FROM consignments c WHERE c.product_id = p.id AND c.status = 'active') AS consigned_stock
                FROM products p $join_clause WHERE $where_conditions";
        if ($branch !== null && $branch !== '') {
            $sql .= " AND p.branch_id = " . intval($branch);
        }
        if ($q !== '') {
            $q_esc = $mysqli->real_escape_string($q);
            $sql .= " AND (p.name LIKE '%$q_esc%' OR p.sku LIKE '%$q_esc%' OR p.category LIKE '%$q_esc%')";
        }
        if ($category === 'consignment') {
            // Special filter: show products that have an active consignment record.
            $sql .= " AND EXISTS (SELECT 1 FROM consignments c WHERE c.product_id = p.id AND c.status = 'active')";
        } elseif ($category !== '') {
            $cat_esc = $mysqli->real_escape_string($category);
            $sql .= " AND p.category = '$cat_esc'";
        }
        // Add GROUP BY to avoid duplicate products when joining with product_stocks
        if ($stock_level !== '') {
            $sql .= " GROUP BY p.id";
        }

        $sql .= " ORDER BY p.id DESC LIMIT 1000";
        $res = $mysqli->query($sql);
        if ($res === false) {
            jsonRes(['ok' => false, 'error' => $mysqli->error]);
        }
        $products = [];
        while ($r = $res->fetch_assoc()) {
            $img = trim($r['photo']);

            if ($img === '') {
                $r['photo'] = 'uploads/no-image.png';
            } else {
                if (strpos($img, '/') === false) {
                    $r['photo'] = 'uploads/' . $img;
                } else {
                    $r['photo'] = $img;
                }
            }
            $r['id'] = (int)$r['id'];

            $r['price'] = (float)$r['price'];
            $r['branch_id'] = $r['branch_id'] !== null ? (int)$r['branch_id'] : null;
            $r['stocks'] = []; // Initialize stocks array
            $products[$r['id']] = $r;
        }

        // Fetch product stocks and determine stock display type
        if (!empty($products)) {
            $product_ids = implode(',', array_keys($products));
            $stock_sql = "SELECT * FROM product_stocks WHERE product_id IN ($product_ids)";
            $stock_res = $mysqli->query($stock_sql);
            while ($stock_row = $stock_res->fetch_assoc()) {
                $stock_row['quantity'] = (int)$stock_row['quantity'];
                $products[$stock_row['product_id']]['stocks'][] = $stock_row;                


            }

            // Determine stock display type for each product based on category and actual stock entries
            foreach ($products as &$product) { // Use reference to modify original array
                $has_os_stock = false;
                $has_size_stocks = false;
                foreach ($product['stocks'] as $stock) {
                    if ($stock['size'] === 'os') {
                        $has_os_stock = true;
                    } elseif (in_array($stock['size'], ['s', 'm', 'l', 'xl'])) {
                        $has_size_stocks = true;
                    }
                }
                if ($product['category'] === 'bracket' || $product['category'] === 'topbox') {
                    $product['stock_display_type'] = 'regular';
                } elseif ($product['category'] === 'others') {
                    $product['stock_display_type'] = $has_size_stocks ? 'sizes' : 'regular';
                } else { // Default for other categories like 'helmet', 'jacket'
                    $product['stock_display_type'] = 'sizes';
                }
            }
        }

        jsonRes(['ok' => true, 'products' => array_values($products)]);
        break;

    // --------------------------------------------------------------------------------------------------------------------

    case 'add_product': // owner only
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $name = trim($_POST['item_name'] ?? $_POST['name'] ?? '');
      if($name==='') jsonRes(['ok'=>false,'error'=>'Item name is required.']);
      $sku = trim($_POST['sku'] ?? '');
      $category = trim($_POST['category'] ?? '');
      if(!isset($_POST['price']) || !is_numeric($_POST['price']) || floatval($_POST['price']) < 0) jsonRes(['ok'=>false,'error'=>'A valid, non-negative price is required.']);
      $price = floatval($_POST['price']);
      $branch_id = (isset($_POST['branch_id']) && $_POST['branch_id']!=='') ? intval($_POST['branch_id']) : null;
      // file upload
      $photo_path = null;
      if(!empty($_FILES['photo']['name'])){
        if(!is_dir('uploads')) @mkdir('uploads',0755,true);
        $fname = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/','_',basename($_FILES['photo']['name']));
        $target = 'uploads/'.$fname;
        if(move_uploaded_file($_FILES['photo']['tmp_name'],$target)) $photo_path = $fname;
      }
      $sql = "INSERT INTO products (sku,name,category,price,branch_id,photo) VALUES (?,?,?,?,?,?)";
            $stmt = $mysqli->prepare($sql);
      $stmt->bind_param('sssdis', $sku,$name,$category,$price,$branch_id,$photo_path);
      if($stmt->execute()) {
        $product_id = $stmt->insert_id;
        
        $stock_type_for_others = $_POST['others_stock_type'] ?? '';

        // Determine which stock type to save
        $save_regular_stock = ($category === 'bracket' || $category === 'topbox' || ($category === 'others' && $stock_type_for_others === 'regular'));
        $save_size_stock = ($category === 'helmet' || $category === 'jacket' || ($category === 'others' && $stock_type_for_others === 'sizes'));

        // Insert regular stock if applicable (for 'os' size)
        if ($save_regular_stock && isset($_POST['stock_regular'])) {
            $quantity = intval($_POST['stock_regular']);
            if ($quantity >= 0) {
                            $stock_stmt = $mysqli->prepare("INSERT INTO product_stocks (product_id, size, quantity) VALUES (?, 'os', ?)");
                            $stock_stmt->bind_param('ii', $product_id, $quantity);
                $stock_stmt->execute();
            }
        }

        // Insert size-based stock if applicable
        if ($save_size_stock) {
            $sizes = ['s', 'm', 'l', 'xl'];
            foreach ($sizes as $size) {
                if (isset($_POST['stock_' . $size])) {
                    $quantity = intval($_POST['stock_' . $size]);
                    if ($quantity >= 0) {
                            $stock_stmt = $mysqli->prepare("INSERT INTO product_stocks (product_id, size, quantity) VALUES (?, ?, ?)");
                            $stock_stmt->bind_param('isi', $product_id, $size, $quantity);
                        $stock_stmt->execute();
                    }
                }
            }
        }
        // Always return success if product and stock insertion was attempted
        jsonRes(['ok'=>true,'id'=>$product_id]);
      }
      else { // Only return error if the initial product insertion failed
        jsonRes(['ok'=>false,'error'=>$mysqli->error]);
      }
      break;

    // --------------------------------------------------------------------------------------------------------------------

    case 'edit_product': // owner only
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $id = intval($_POST['id'] ?? 0); if(!$id) jsonRes(['ok'=>false,'error'=>'missing id']);
      $name = trim($_POST['name'] ?? $_POST['item_name'] ?? ''); if($name==='') jsonRes(['ok'=>false,'error'=>'Item name is required.']);
      $sku = trim($_POST['sku'] ?? '');
      $category = trim($_POST['category'] ?? '');
      if(!isset($_POST['price']) || !is_numeric($_POST['price']) || floatval($_POST['price']) < 0) jsonRes(['ok'=>false,'error'=>'A valid, non-negative price is required.']);
      $price = floatval($_POST['price']);
      $branch_id = (isset($_POST['branch_id']) && $_POST['branch_id']!=='') ? intval($_POST['branch_id']) : null;
      // handle photo
      $photo_path = null;
      if(!empty($_FILES['photo']['name'])){
        if(!is_dir('uploads')) @mkdir('uploads',0755,true);
        $fname = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/','_',basename($_FILES['photo']['name']));
        $target = 'uploads/'.$fname;
        if(move_uploaded_file($_FILES['photo']['tmp_name'],$target)) $photo_path = $fname;
      }
      // build update
            $fields = [];
      $types = '';
      $vals = [];
      $fields[] = "sku=?"; $types.='s'; $vals[]=$sku;
      $fields[] = "name=?"; $types.='s'; $vals[]=$name;
      $fields[] = "category=?"; $types.='s'; $vals[]=$category;
      $fields[] = "price=?"; $types.='d'; $vals[]=$price;
      if($branch_id===null) $fields[] = "branch_id = NULL";
      else { $fields[] = "branch_id = ?"; $types.='i'; $vals[]=$branch_id;}
      if ($photo_path !== null) {
          $fields[] = "photo = ?"; $types.='s'; $vals[] = $photo_path;
      }
      $sql = "UPDATE products SET ".implode(', ',$fields)." WHERE id=?";
      $types .= 'i'; $vals[] = $id;
      $stmt = $mysqli->prepare($sql);
      if($types!=='') $stmt->bind_param($types, ...$vals);
      if($stmt->execute()) {
        // Delete all existing stocks for this product to re-insert them cleanly
        $delete_stocks_stmt = $mysqli->prepare("DELETE FROM product_stocks WHERE product_id = ?");
        $delete_stocks_stmt->bind_param('i', $id);
            $delete_stocks_stmt->execute();
        
        $stock_type_for_others = $_POST['others_stock_type'] ?? '';

        // Determine which stock type to save
        $save_regular_stock = ($category === 'bracket' || $category === 'topbox' || ($category === 'others' && $stock_type_for_others === 'regular'));
        $save_size_stock = ($category === 'helmet' || $category === 'jacket' || ($category === 'others' && $stock_type_for_others === 'sizes'));

        // Insert regular stock if applicable (for 'os' size)
        if ($save_regular_stock && isset($_POST['stock_regular'])) {
            $quantity = intval($_POST['stock_regular']);
            if ($quantity >= 0) {
                            $stock_stmt = $mysqli->prepare("INSERT INTO product_stocks (product_id, size, quantity) VALUES (?, 'os', ?)");
                            $stock_stmt->bind_param('ii', $id, $quantity);
                $stock_stmt->execute();
            }
        }

        // Update size-based stock if applicable
        if ($save_size_stock) {
            $sizes = ['s', 'm', 'l', 'xl'];
            foreach ($sizes as $size) {
                if (isset($_POST['stock_' . $size])) {
                    $quantity = intval($_POST['stock_' . $size]);
                    if ($quantity >= 0) {
                            $stock_stmt = $mysqli->prepare("INSERT INTO product_stocks (product_id, size, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = ?");
                            $stock_stmt->bind_param('isii', $id, $size, $quantity, $quantity);
                        $stock_stmt->execute();
                    }
                }
            }
        }
        // Always return success if product and stock update was attempted
        jsonRes(['ok'=>true]);
      }
      else { // Only return error if the initial product update failed
        jsonRes(['ok'=>false,'error'=>$mysqli->error]);
      }
      break;

    // --------------------------------------------------------------------------------------------------------------------

    case 'delete_product':
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $id = intval($_POST['id'] ?? 0);
      if(!$id) jsonRes(['ok'=>false,'error'=>'missing id']);
      $stmt = $mysqli->prepare("DELETE FROM products WHERE id=?");
      $stmt->bind_param('i',$id);
      jsonRes(['ok'=>$stmt->execute()]);
      break;

    // --------------------------------------------------------------------------------------------------------------------

    // ---------------- ACCOUNTS ----------------
    case 'get_accounts':
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $res = $mysqli->query("SELECT u.id,u.username,u.email,u.role,u.assigned_branch_id,b.name AS branch_name FROM users u LEFT JOIN branches b ON u.assigned_branch_id=b.id ORDER BY u.id DESC");
      $out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
      jsonRes(['ok'=>true,'accounts'=>$out]);
      break;

    // --------------------------------------------------------------------------------------------------------------------

    case 'add_user':
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $username = trim($_POST['username'] ?? '');
      $role = ($_POST['role'] ?? 'staff')==='owner' ? 'owner' : 'staff';
      $branch_id = (isset($_POST['branch_id']) && $_POST['branch_id']!=='') ? intval($_POST['branch_id']) : null;
      $password = $_POST['password'] ?? '';
      $email = trim($_POST['email'] ?? '');      
      if(strlen($password) < 8) jsonRes(['ok'=>false,'error'=>'Password must be at least 8 characters.']);
      if($username==='' || $password==='') jsonRes(['ok'=>false,'error'=>'missing']);
      if($role === 'owner' && !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonRes(['ok'=>false,'error'=>'A valid email is required for the owner role.']);
      $stmt = $mysqli->prepare("SELECT id FROM users WHERE username=? LIMIT 1"); $stmt->bind_param('s',$username); $stmt->execute(); $res = $stmt->get_result();
      if($res->fetch_assoc()) jsonRes(['ok'=>false,'error'=>'username exists']);
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $sql = "INSERT INTO users (username,password,email,role,assigned_branch_id,created_at) VALUES (?,?,?,?,?,NOW())";
      $stmt = $mysqli->prepare($sql);
      $stmt->bind_param('ssssi', $username,$hash,$email,$role,$branch_id);
      if($stmt->execute()) jsonRes(['ok'=>true,'id'=>$stmt->insert_id]);
      jsonRes(['ok'=>false,'error'=>$mysqli->error]);
      break;

    // --------------------------------------------------------------------------------------------------------------------

    case 'edit_user':
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $id = intval($_POST['id'] ?? 0); if(!$id) jsonRes(['ok'=>false,'error'=>'missing id']);
      $username = trim($_POST['username'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $role = ($_POST['role'] ?? 'staff') === 'owner' ? 'owner' : 'staff';
      $branch_id = (isset($_POST['branch_id']) && $_POST['branch_id']!=='') ? intval($_POST['branch_id']) : null;
      $password = $_POST['password'] ?? '';

      if($password !== '' && strlen($password) < 8) jsonRes(['ok'=>false,'error'=>'New password must be at least 8 characters long.']);
      if($role === 'owner' && !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonRes(['ok'=>false,'error'=>'A valid email is required for the owner role.']);

      // Start transaction
      $mysqli->begin_transaction();

      $update_user_sql = "UPDATE users SET username=?, email=?, role=?, assigned_branch_id=? WHERE id=?";
      $stmt = $mysqli->prepare($update_user_sql);
      $stmt->bind_param('sssii', $username, $email, $role, $branch_id, $id);
      $user_updated = $stmt->execute();

      if ($password !== '') {
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $update_pass_sql = "UPDATE users SET password=? WHERE id=?";
          $pass_stmt = $mysqli->prepare($update_pass_sql);
          $pass_stmt->bind_param('si', $hash, $id);
          $pass_stmt->execute();
      }

      if ($user_updated) {
        $mysqli->commit();
        jsonRes(['ok' => true]);
      } else {
        $mysqli->rollback();
        jsonRes(['ok' => false, 'error' => $stmt->error]);
      }
      break;

    // --------------------------------------------------------------------------------------------------------------------

    case 'delete_user':
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $id = intval($_POST['id'] ?? 0); if(!$id) jsonRes(['ok'=>false,'error'=>'missing id']);
      $stmt = $mysqli->prepare("DELETE FROM users WHERE id=?"); $stmt->bind_param('i',$id);
      jsonRes(['ok'=>$stmt->execute()]);
      break;

    // --------------------------------------------------------------------------------------------------------------------

    // ---------------- LOGS ----------------
    case 'get_logs':
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $type = $_GET['type'] ?? 'action'; // action or sales
      $time_range_type = $_GET['time_range_type'] ?? ''; // weekly, monthly, yearly
      $time_range_value = $_GET['time_range_value'] ?? ''; // The selected week, month, or year

      if($type==='sales'){
        $sql = "SELECT s.*, u.username, b.name AS branch_name FROM receipts s LEFT JOIN users u ON s.created_by=u.id LEFT JOIN branches b ON s.branch_id=b.id WHERE 1=1";

        if ($time_range_type === 'weekly' && preg_match('/^\d{4}-W\d{2}$/', $time_range_value)) {
          $year = (int)substr($time_range_value, 0, 4);
          $week = (int)substr($time_range_value, 6, 2);
          $date = new DateTime();
          $date->setISODate($year, $week);
          $start_date = $date->format('Y-m-d');
          $date->modify('+6 days');
          $end_date = $date->format('Y-m-d');
          $sql .= " AND s.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
        } elseif ($time_range_type === 'monthly' && preg_match('/^\d{4}-\d{2}$/', $time_range_value)) {
          $sql .= " AND DATE_FORMAT(s.created_at, '%Y-%m') = '$time_range_value'";
        } elseif ($time_range_type === 'yearly' && preg_match('/^\d{4}$/', $time_range_value)) {
          $sql .= " AND YEAR(s.created_at) = '$time_range_value'";
        }

        $sql .= " ORDER BY s.id DESC LIMIT 200";
        $res = $mysqli->query($sql);
        $out = [];
        while ($r = $res->fetch_assoc()) {
            $items = json_decode($r['items'], true);
            $product_names = [];
            if (is_array($items)) {
                foreach ($items as $item) { $product_names[] = $item['name']; }
            }
            $r['products'] = implode(', ', $product_names);
            $out[] = $r;
        }
        jsonRes(['ok'=>true,'sales'=>$out]);
      } else {
        $res = $mysqli->query("SELECT l.*, u.username, b.name AS branch_name FROM action_logs l LEFT JOIN users u ON l.user_id=u.id LEFT JOIN branches b ON l.branch_id=b.id ORDER BY l.id DESC LIMIT 500");
        $out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
        jsonRes(['ok'=>true,'actions'=>$out]);
      }
      break;

    // --------------------------------------------------------------------------------------------------------------------

// ---------------- RECENT/SUMMARY & CHECKOUT ----------------
case 'get_recent_sales':
  $res = $mysqli->query("SELECT r.*, u.username FROM receipts r LEFT JOIN users u ON r.created_by=u.id ORDER BY r.id DESC LIMIT 10");
  $out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
  jsonRes(['ok'=>true,'sales'=>$out]);
  break;

    // --------------------------------------------------------------------------------------------------------------------

case 'get_stats':
  $r = $mysqli->query("SELECT COUNT(*) AS cnt FROM products")->fetch_assoc();
  $r2 = $mysqli->query("SELECT IFNULL(SUM(quantity),0) AS total_stock FROM product_stocks")->fetch_assoc();
  $r3 = $mysqli->query("SELECT COUNT(*) AS sales_count FROM receipts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc();
  jsonRes(['ok'=>true,'products'=>intval($r['cnt']),'total_stock'=>floatval($r2['total_stock']),'sales_30d'=>intval($r3['sales_count'])]);
  break;

    // --------------------------------------------------------------------------------------------------------------------

    case 'checkout':
  if ($method !== 'POST') jsonRes(['ok' => false, 'error' => 'POST required']);

  // Support both JSON and form-data
  $raw = file_get_contents('php://input');
  $payload = json_decode($raw, true);
  if (!$payload || !isset($payload['items'])) $payload = $_POST;

  $items = $payload['items'] ?? [];
  $payment = $payload['payment_mode'] ?? ($payload['payment'] ?? 'Cash');
  $branch_id = isset($payload['branch_id']) && $payload['branch_id'] !== '' ? intval($payload['branch_id']) : null;
  $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

  if (!is_array($items) || empty($items)) jsonRes(['ok' => false, 'error' => 'cart empty']);

  $mysqli->begin_transaction();
  $detailed = [];
  $total = 0.0;

  foreach ($items as $it) {
    $pid = intval($it['id']);
    $qty = intval($it['qty']);
    $size = $it['size'];
    if ($pid <= 0 || $qty <= 0) continue;

    $stmt = $mysqli->prepare("SELECT id,name,price FROM products WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$row = $res->fetch_assoc()) continue;
    
    $stock_stmt = $mysqli->prepare("SELECT quantity FROM product_stocks WHERE product_id = ? AND size = ?");
    $stock_stmt->bind_param('is', $pid, $size);
    $stock_stmt->execute();
    $stock_res = $stock_stmt->get_result();
    $stock_row = $stock_res->fetch_assoc();

    if (!$stock_row || $qty > $stock_row['quantity']) {
        $qty = $stock_row ? $stock_row['quantity'] : 0;
    }
    if ($qty <= 0) continue;

    $line = (floatval($row['price']) * 1.12) * $qty; // Use VAT-inclusive price for line total
    $total += $line;
    $detailed[] = [
      'id' => intval($row['id']),
      'name' => $row['name'],
      'qty' => $qty,
      'size' => $size,
      'price' => floatval($row['price']) * 1.12 // Return VAT-inclusive price
    ];

    $stmt2 = $mysqli->prepare("UPDATE product_stocks SET quantity = quantity - ? WHERE product_id=? AND size = ? AND quantity >= ?");
    $stmt2->bind_param('iisi', $qty, $pid, $size, $qty);
    $stmt2->execute();

    // --- START CONSIGNMENT UPDATE ---
    // Find active consignments for the sold product and update them.
    $qty_to_process = $qty;
    $active_consignments_stmt = $mysqli->prepare(
        "SELECT id, quantity_consigned, quantity_sold 
         FROM consignments 
         WHERE product_id = ? AND status = 'active' AND quantity_sold < quantity_consigned 
         ORDER BY created_at ASC"
    );
    $active_consignments_stmt->bind_param('i', $pid);
    $active_consignments_stmt->execute();
    $active_consignments_res = $active_consignments_stmt->get_result();

    $update_sold_stmt = $mysqli->prepare("UPDATE consignments SET quantity_sold = ? WHERE id = ?");
    $mark_paid_stmt = $mysqli->prepare("UPDATE consignments SET status = 'paid' WHERE id = ?");

    while ($c = $active_consignments_res->fetch_assoc()) {
        if ($qty_to_process <= 0) break;

        $remaining_in_batch = $c['quantity_consigned'] - $c['quantity_sold'];
        $deduct_from_this_batch = min($qty_to_process, $remaining_in_batch);

        $new_sold_qty = $c['quantity_sold'] + $deduct_from_this_batch;
        $update_sold_stmt->bind_param('ii', $new_sold_qty, $c['id']);
        $update_sold_stmt->execute();

        // If this batch is now fully sold, mark it as paid.
        if ($new_sold_qty >= $c['quantity_consigned']) {
            $mark_paid_stmt->bind_param('i', $c['id']);
            $mark_paid_stmt->execute();
        }

        $qty_to_process -= $deduct_from_this_batch;
    }
    // --- END CONSIGNMENT UPDATE ---

    if ($stmt2->affected_rows <= 0) {
      $mysqli->rollback();
      jsonRes(['ok' => false, 'error' => 'insufficient stock for product id ' . $pid . ' size ' . $size]);
    }
  }

  if (empty($detailed)) {
    $mysqli->rollback();
    jsonRes(['ok' => false, 'error' => 'no valid items']);
  }

  $receipt_no = 'RCPT-' . strtoupper(uniqid());
  $items_json = json_encode($detailed, JSON_UNESCAPED_UNICODE);
  $current_time = date('Y-m-d H:i:s'); // Use PHP-generated time

  $stmt = $mysqli->prepare("INSERT INTO receipts (receipt_no, items, total, payment_mode, branch_id, created_by, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param('ssdsiis', $receipt_no, $items_json, $total, $payment, $branch_id, $user_id, $current_time);

  if ($stmt->execute()) {
    // ✅ Log action
    $meta = 'receipt:' . $receipt_no;
    $log = $mysqli->prepare("INSERT INTO action_logs (user_id, action, meta, branch_id) VALUES (?, 'checkout', ?, ?)");
    $log->bind_param('isi', $user_id, $meta, $branch_id);
    $log->execute();

    $mysqli->commit();

    // Calculate VAT details assuming the total is VAT-inclusive
    $vat_rate = 0.12; // 12%
    $vatable_sales = $total / (1 + $vat_rate);
    $vat_amount = $total - $vatable_sales;

    jsonRes([
        'ok' => true, 
        'receipt_no' => $receipt_no, 
        'total' => $total, 
        'items_sold' => $detailed, 
        'payment_mode' => $payment, 
        'timestamp' => $current_time,
        'username' => $_SESSION['username'] ?? 'N/A',
        'vatable_sales' => $vatable_sales,
        'vat_amount' => $vat_amount
    ]);
  } else {
    $mysqli->rollback();
    jsonRes(['ok' => false, 'error' => $mysqli->error]);
  }
  break;

    // --------------------------------------------------------------------------------------------------------------------


    case 'get_dashboard_data':
      if (is_owner()) {
        $dashboard_data = [
            'sales_today' => 0,
            'sales_yesterday' => 0,
            'sales_this_month' => 0,
            'sales_last_month' => 0,
            'total_sales' => 0,
            'sales_timeline_data' => [],
            'low_stocks' => [],
            'sales_per_branch' => [],
            'sales_per_branch_timeline' => [],
            'least_sold_products' => [],
        ];
  
        // Sales Today, Yesterday, This Month, Last Month, Total Sales, Sales Per Branch, Sales Timeline, Low Stocks...
        // (All existing owner dashboard queries remain here)
        $sales_today_query = "SELECT SUM(total) AS sales_today FROM receipts WHERE DATE(created_at) = CURDATE()";
        $sales_today_result = $mysqli->query($sales_today_query);
        $sales_today_data = $sales_today_result->fetch_assoc();
        $dashboard_data['sales_today'] = $sales_today_data['sales_today'] ?? 0;
  
        $sales_yesterday_query = "SELECT SUM(total) AS sales_yesterday FROM receipts WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY";
        $sales_yesterday_result = $mysqli->query($sales_yesterday_query);
        $sales_yesterday_data = $sales_yesterday_result->fetch_assoc();
        $dashboard_data['sales_yesterday'] = $sales_yesterday_data['sales_yesterday'] ?? 0;
  
        $sales_this_month_query = "SELECT SUM(total) AS sales_this_month FROM receipts WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
        $sales_this_month_result = $mysqli->query($sales_this_month_query);
        $sales_this_month_data = $sales_this_month_result->fetch_assoc();
        $dashboard_data['sales_this_month'] = $sales_this_month_data['sales_this_month'] ?? 0;
  
        $sales_last_month_query = "SELECT SUM(total) AS sales_last_month FROM receipts WHERE MONTH(created_at) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
        $sales_last_month_result = $mysqli->query($sales_last_month_query);
        $sales_last_month_data = $sales_last_month_result->fetch_assoc();
        $dashboard_data['sales_last_month'] = $sales_last_month_data['sales_last_month'] ?? 0;
  
        $total_sales_query = "SELECT SUM(total) AS total_sales FROM receipts";
        $total_sales_result = $mysqli->query($total_sales_query);
        $total_sales_data = $total_sales_result->fetch_assoc();
        $dashboard_data['total_sales'] = $total_sales_data['total_sales'] ?? 0;
  
        $sales_per_branch_query = "SELECT b.name, SUM(r.total) as total_sales FROM receipts r JOIN branches b ON r.branch_id = b.id WHERE r.branch_id IS NOT NULL GROUP BY r.branch_id, b.name ORDER BY total_sales DESC";
        $sales_per_branch_result = $mysqli->query($sales_per_branch_query);
        while ($row = $sales_per_branch_result->fetch_assoc()) $dashboard_data['sales_per_branch'][] = $row;
  
        $sales_timeline_query = "SELECT DATE(created_at) as date, SUM(total) as sales FROM receipts WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC";
        $sales_timeline_result = $mysqli->query($sales_timeline_query);
        while ($row = $sales_timeline_result->fetch_assoc()) $dashboard_data['sales_timeline_data'][] = ['date' => $row['date'], 'sales' => (float)$row['sales']];
  
        $low_stocks_query = "SELECT p.name, ps.size, ps.quantity FROM product_stocks ps JOIN products p ON ps.product_id = p.id WHERE ps.quantity <= 5 ORDER BY ps.quantity ASC, p.name ASC";
        $low_stocks_result = $mysqli->query($low_stocks_query);
        while ($row = $low_stocks_result->fetch_assoc()) {
            $dashboard_data['low_stocks'][] = $row;
        }        

        // --- START: Trending Products (Owner) ---
        $trending_products_query = "SELECT items FROM receipts WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $trending_res = $mysqli->query($trending_products_query);

        $item_sales = [];
        while ($r = $trending_res->fetch_assoc()) {
            $items = json_decode($r['items'], true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    $id = intval($item['id']);
                    if ($id > 0) {
                        $qty = intval($item['qty']);
                        if (!isset($item_sales[$id])) $item_sales[$id] = 0;
                        $item_sales[$id] += $qty;
                    }
                }
            }
        }

        $dashboard_data['trending_products'] = [];
        if (!empty($item_sales)) {
            arsort($item_sales); // Sort by quantity sold, descending
            $top_product_ids = array_slice(array_keys($item_sales), 0, 15); // Get top 15

            if (!empty($top_product_ids)) {
                $ids_placeholder = implode(',', array_fill(0, count($top_product_ids), '?'));
                $types = str_repeat('i', count($top_product_ids));
                
                $product_sql = "SELECT id, name, photo FROM products WHERE id IN ($ids_placeholder)";
                $product_stmt = $mysqli->prepare($product_sql);
                $product_stmt->bind_param($types, ...$top_product_ids);
                $product_stmt->execute();
                $product_res = $product_stmt->get_result();

                while ($p_row = $product_res->fetch_assoc()) {
                    $p_row['qty_sold'] = $item_sales[$p_row['id']];
                    // Correct the photo path
                    $img = trim($p_row['photo'] ?? '');
                    if ($img === '' || $img === null) {
                        $p_row['photo'] = 'uploads/no-image.png';
                    } elseif (strpos($img, '/') === false) {
                        // Prepend the uploads directory if it's just a filename
                        $p_row['photo'] = 'uploads/' . $img;
                    }
                    $dashboard_data['trending_products'][] = $p_row;
                }
            }
        }
        // --- END: Trending Products (Owner) ---

        // --- START: Least Sold Products (Owner) ---
        $all_products_query = "SELECT id, name FROM products";
        $all_products_res = $mysqli->query($all_products_query);
        $all_product_sales = [];
        while ($p_row = $all_products_res->fetch_assoc()) {
            $all_product_sales[$p_row['id']] = ['name' => $p_row['name'], 'qty_sold' => 0];
        }

        // Use the already fetched sales data from trending products
        foreach ($item_sales as $id => $qty) {
            if (isset($all_product_sales[$id])) {
                $all_product_sales[$id]['qty_sold'] = $qty;
            }
        }

        // Sort by quantity sold, ascending
        uasort($all_product_sales, function($a, $b) {
            return $a['qty_sold'] <=> $b['qty_sold'];
        });

        // Get the top 15 least sold products
        $dashboard_data['least_sold_products'] = array_slice($all_product_sales, 0, 15, true);


        // --- END: Least Sold Products (Owner) ---

        // New: Sales Per Branch Timeline (Last 30 Days)
        $sales_per_branch_timeline_query = "SELECT DATE(r.created_at) as date, b.name as branch_name, SUM(r.total) as sales 
                                            FROM receipts r 
                                            JOIN branches b ON r.branch_id = b.id 
                                            WHERE r.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND r.branch_id IS NOT NULL
                                            GROUP BY DATE(r.created_at), r.branch_id 
                                            ORDER BY date ASC, branch_name ASC";
        $sales_per_branch_timeline_result = $mysqli->query($sales_per_branch_timeline_query);
        while ($row = $sales_per_branch_timeline_result->fetch_assoc()) {
            $dashboard_data['sales_per_branch_timeline'][] = $row;
        }

        // Sort low stocks to be on top
        usort($dashboard_data['low_stocks'], fn($a, $b) => $a['quantity'] - $b['quantity']);
  
        jsonRes(array_merge(['ok' => true, 'role' => 'owner'], $dashboard_data));

      } else { // Staff Dashboard
        $staff_branch_id = $_SESSION['assigned_branch_id'] ?? null;
        if (!$staff_branch_id) {
            jsonRes(['ok' => true, 'role' => 'staff', 'trending_items' => []]); // Staff not assigned to a branch
        }

        // The new structure will be an associative array: [ 'category_name' => [items] ]
        $trending_by_category = [];
        $sql = "SELECT items FROM receipts WHERE branch_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $staff_branch_id);
        $stmt->execute();
        $res = $stmt->get_result();

        // 1. Aggregate sales quantity for each product
        $item_sales = [];
        while ($r = $res->fetch_assoc()) {
            $items = json_decode($r['items'], true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    $id = intval($item['id']);
                    if ($id > 0) {
                        $qty = intval($item['qty']);
                        if (!isset($item_sales[$id])) $item_sales[$id] = 0;
                        $item_sales[$id] += $qty;
                    }
                }
            }
        }

        if (!empty($item_sales)) {
            // 2. Fetch product details (including category) for all sold items
            $sold_product_ids = array_keys($item_sales);
            $ids_placeholder = implode(',', array_fill(0, count($sold_product_ids), '?'));
            $types = str_repeat('i', count($sold_product_ids));
            
            $product_sql = "SELECT id, name, photo, category FROM products WHERE id IN ($ids_placeholder)";
            $product_stmt = $mysqli->prepare($product_sql);
            $product_stmt->bind_param($types, ...$sold_product_ids);
            $product_stmt->execute();
            $product_res = $product_stmt->get_result();
            
            // 3. Group items by category and add their sales quantity
            while ($p_row = $product_res->fetch_assoc()) {
                $category_name = !empty($p_row['category']) ? ucfirst($p_row['category']) : 'Uncategorized';
                if (!isset($trending_by_category[$category_name])) {
                    $trending_by_category[$category_name] = [];
                }
                // Ensure photo path is correctly formatted
                $photo_path = trim($p_row['photo']);
                if ($photo_path === '') {
                    $photo_url = 'uploads/no-image.png';
                } else {
                    // Check if it's already a full path or just a filename
                    $photo_url = (strpos($photo_path, '/') === false) ? 'uploads/' . $photo_path : $photo_path;
                }

                $trending_by_category[$category_name][] = [
                    'name' => $p_row['name'],
                    'qty' => $item_sales[$p_row['id']],
                    'photo' => $photo_url
                ];
            }

            // 4. For each category, sort by quantity and slice the top 5
            foreach ($trending_by_category as $category => &$items) {
                usort($items, function($a, $b) {
                    return $b['qty'] <=> $a['qty'];
                });
                $items = array_slice($items, 0, 5);
            }
        }

        jsonRes(['ok' => true, 'role' => 'staff', 'trending_items' => $trending_by_category]);
      }
      break;

    // --------------------------------------------------------------------------------------------------------------------

    /* This is a placeholder for the old dashboard implementation, which is now inside the is_owner() block above.
       The following code is now effectively replaced.
    */
    /*
      $dashboard_data = [
          'sales_today' => 0,
          'sales_yesterday' => 0,
          'sales_this_month' => 0,
          'sales_last_month' => 0,
          'total_sales' => 0,
          'sales_timeline_data' => [],
          'low_stocks' => [],
          'sales_per_branch' => [],
      ];

      // Sales Today
      $sales_today_query = "SELECT SUM(total) AS sales_today FROM receipts WHERE DATE(created_at) = CURDATE()";
      $sales_today_result = $mysqli->query($sales_today_query);
      $sales_today_data = $sales_today_result->fetch_assoc();
      $dashboard_data['sales_today'] = $sales_today_data['sales_today'] ?? 0;

      // Sales Yesterday
      $sales_yesterday_query = "SELECT SUM(total) AS sales_yesterday FROM receipts WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY";
      $sales_yesterday_result = $mysqli->query($sales_yesterday_query);
      $sales_yesterday_data = $sales_yesterday_result->fetch_assoc();
      $dashboard_data['sales_yesterday'] = $sales_yesterday_data['sales_yesterday'] ?? 0;

      // Sales This Month
      $sales_this_month_query = "SELECT SUM(total) AS sales_this_month FROM receipts WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
      $sales_this_month_result = $mysqli->query($sales_this_month_query);
      $sales_this_month_data = $sales_this_month_result->fetch_assoc();
      $dashboard_data['sales_this_month'] = $sales_this_month_data['sales_this_month'] ?? 0;

      // Sales Last Month
      $sales_last_month_query = "SELECT SUM(total) AS sales_last_month FROM receipts WHERE MONTH(created_at) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(CURDATE() - INTERVAL 1 MONTH)";
      $sales_last_month_result = $mysqli->query($sales_last_month_query);
      $sales_last_month_data = $sales_last_month_result->fetch_assoc();
      $dashboard_data['sales_last_month'] = $sales_last_month_data['sales_last_month'] ?? 0;

      // Total Sales (All Time)
      $total_sales_query = "SELECT SUM(total) AS total_sales FROM receipts";
      $total_sales_result = $mysqli->query($total_sales_query);
      $total_sales_data = $total_sales_result->fetch_assoc();
      $dashboard_data['total_sales'] = $total_sales_data['total_sales'] ?? 0;

      // Sales Per Branch
      $sales_per_branch_query = "SELECT b.name, SUM(r.total) as total_sales
                                 FROM receipts r
                                 JOIN branches b ON r.branch_id = b.id
                                 WHERE r.branch_id IS NOT NULL
                                 GROUP BY r.branch_id, b.name
                                 ORDER BY total_sales DESC";
      $sales_per_branch_result = $mysqli->query($sales_per_branch_query);
      while ($row = $sales_per_branch_result->fetch_assoc()) $dashboard_data['sales_per_branch'][] = $row;

      // Sales Timeline Data for Trends Chart (last 30 days)
      $sales_timeline_query = "SELECT DATE(created_at) as date, SUM(total) as sales FROM receipts WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC";
      $sales_timeline_result = $mysqli->query($sales_timeline_query);
      while ($row = $sales_timeline_result->fetch_assoc()) $dashboard_data['sales_timeline_data'][] = ['date' => $row['date'], 'sales' => (float)$row['sales']];

      // Low Stocks (quantity <= 3)
      $low_stocks_query = "SELECT p.name, ps.size, ps.quantity 
                           FROM product_stocks ps 
                           JOIN products p ON ps.product_id = p.id 
                           WHERE ps.quantity <= 3 
                           ORDER BY ps.quantity ASC, p.name ASC";
      $low_stocks_result = $mysqli->query($low_stocks_query);
      while ($row = $low_stocks_result->fetch_assoc()) {
          $dashboard_data['low_stocks'][] = $row;
      }
    */

    // --------------------------------------------------------------------------------------------------------------------

    case 'forgot_password':
      if ($method !== 'POST') jsonRes(['ok' => false, 'error' => 'POST required']);
      $email = trim($_POST['email'] ?? '');
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          jsonRes(['ok' => false, 'error' => 'Invalid email format.']);
      }

      $stmt = $mysqli->prepare("SELECT id, username FROM users WHERE email = ? AND role = 'owner' LIMIT 1");
      $stmt->bind_param('s', $email); $stmt->execute();
      $res = $stmt->get_result();
      $user = $res->fetch_assoc();

      if (!$user) {
          // To prevent user enumeration (revealing which emails are registered), we always return a success message.
          jsonRes(['ok' => true, 'message' => 'If an owner account with that email exists, a password reset link has been sent.']);
      }

      $user_id = $user['id'];
      $username = $user['username'];
      $token = bin2hex(random_bytes(32)); // Generate a more secure token
      $expiration_time = date('Y-m-d H:i:s', strtotime('+60 minutes'));

      // Update user's password reset token and expiry
      $update_stmt = $mysqli->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
      $update_stmt->bind_param('ssi', $token, $expiration_time, $user_id);
      if (!$update_stmt->execute()) {
          jsonRes(['ok' => false, 'error' => 'Failed to update password reset token.']);
      }

      // Include PHPMailer
      require 'src/PHPMailer.php';
      require 'src/SMTP.php';
      require 'src/Exception.php';

      $mail = new PHPMailer\PHPMailer\PHPMailer(true);

      // --- Email Sending ---
      try {
          //Server settings
          // $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER; // Keep this commented out for normal operation
          $mail->isSMTP();
          $mail->Host       = 'smtp.gmail.com';  // Gmail SMTP server
          $mail->SMTPAuth   = true;
          $mail->Username   = 'timowtisakay@gmail.com';  // Your Gmail address
          $mail->Password   = 'blahftydaolptdvr';       // Your new App Password
          $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
          $mail->Port       = 587;

          //Recipients
          $mail->setFrom('timowtisakay@gmail.com', 'Motify Password Reset'); // Your Gmail address
          $mail->addAddress($email, $username);

          //Content
          $reset_link = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?code=' . $token;
          $mail->isHTML(true);
          $mail->Subject = 'Password Reset Request';
          $mail->Body    = 'Please click the following link to reset your password: <a href="' . $reset_link . '">Reset Password</a>';

          $mail->send();
          jsonRes(['ok' => true, 'message' => 'If an owner account with that email exists, a password reset link has been sent.']);
      } catch (Exception $e) {
          jsonRes(['ok' => false, 'error' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"]);
      }
      break;
    case 'reset_password':
      if ($method !== 'POST') {
          jsonRes(['ok' => false, 'error' => 'POST method is required.']);
      }
      $code = trim($_POST['code'] ?? '');
      $password = $_POST['password'] ?? '';

      if (empty($code) || empty($password)) {
          jsonRes(['ok' => false, 'error' => 'The reset code and a new password are required.']);
      }
      
      // Enforce password length on the server-side
      if (strlen($password) < 8) {
          jsonRes(['ok' => false, 'error' => 'Password must be at least 8 characters long.']);
      }

      $current_time = date('Y-m-d H:i:s');
      $stmt = $mysqli->prepare("SELECT id, username FROM users WHERE password_reset_token = ? AND password_reset_expires > ? AND role = 'owner' LIMIT 1");
      $stmt->bind_param('ss', $code, $current_time);
      $stmt->execute();
      $res = $stmt->get_result();
      $user = $res->fetch_assoc();
      if (!$user) {
          jsonRes(['ok' => false, 'error' => 'Invalid or expired reset code.']);
      }

      $hash = password_hash($password, PASSWORD_DEFAULT);
      $update_stmt = $mysqli->prepare("UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?");
      $update_stmt->bind_param('si', $hash, $user['id']);
      if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
        jsonRes(['ok' => true, 'message' => 'Password has been reset successfully.']);
      } else {
        jsonRes(['ok' => false, 'error' => 'Failed to update password.']);
      }
      break;

    default:
      jsonRes(['ok'=>false,'error'=>'unknown action']);
  }

} catch(Exception $e){
  jsonRes(['ok'=>false,'error'=>$e->getMessage()]);
}