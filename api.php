<?php
// api.php - central router
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';
session_start();

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

    // --------------------------------------------------------------------------------------------------------------------

    // ---------------- PRODUCTS ----------------
    case 'get_products':
        $q = trim($_GET['q'] ?? $_REQUEST['q'] ?? '');
        $source = $_GET['source'] ?? ''; // 'pos' or 'inventory'

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

        $sql = "SELECT p.id, p.sku, p.name, p.category, p.price, p.branch_id, COALESCE(p.photo, '') AS photo, b.name AS branch_name
                FROM products p LEFT JOIN branches b ON p.branch_id = b.id WHERE 1=1";
        if ($branch !== null && $branch !== '') {
            $sql .= " AND p.branch_id = " . intval($branch);
        }
        if ($q !== '') {
            $q_esc = $mysqli->real_escape_string($q);
            $sql .= " AND (p.name LIKE '%$q_esc%' OR p.sku LIKE '%$q_esc%' OR p.category LIKE '%$q_esc%')";
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
      if($name==='') jsonRes(['ok'=>false,'error'=>'missing name']);
      $sku = trim($_POST['sku'] ?? '');
      $category = trim($_POST['category'] ?? '');
      $price = floatval($_POST['price'] ?? 0);
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
      $name = trim($_POST['name'] ?? $_POST['item_name'] ?? ''); if($name==='') jsonRes(['ok'=>false,'error'=>'missing name']);
      $sku = trim($_POST['sku'] ?? '');
      $category = trim($_POST['category'] ?? '');
      $price = floatval($_POST['price'] ?? 0);
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
      if($photo_path !== null){ $fields[] = "photo = ?"; $types.='s'; $vals[]=$photo_path; }
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
      $res = $mysqli->query("SELECT u.id,u.username,u.role,u.assigned_branch_id,b.name AS branch_name FROM users u LEFT JOIN branches b ON u.assigned_branch_id=b.id ORDER BY u.id DESC");
      $out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
      jsonRes(['ok'=>true,'accounts'=>$out]);
      break;

    // --------------------------------------------------------------------------------------------------------------------

    case 'add_user':
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $username = trim($_POST['username'] ?? '');
      $password = $_POST['password'] ?? '';
      $role = ($_POST['role'] ?? 'staff')==='owner' ? 'owner' : 'staff';
      $branch_id = (isset($_POST['branch_id']) && $_POST['branch_id']!=='') ? intval($_POST['branch_id']) : null;
      if($username==='' || $password==='') jsonRes(['ok'=>false,'error'=>'missing']);
      $stmt = $mysqli->prepare("SELECT id FROM users WHERE username=? LIMIT 1"); $stmt->bind_param('s',$username); $stmt->execute(); $res = $stmt->get_result();
      if($res->fetch_assoc()) jsonRes(['ok'=>false,'error'=>'username exists']);
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $sql = "INSERT INTO users (username,password,role,assigned_branch_id,created_at) VALUES (?,?,?,?,NOW())";
      $stmt = $mysqli->prepare($sql);
      $stmt->bind_param('sssi', $username,$hash,$role,$branch_id);
      if($stmt->execute()) jsonRes(['ok'=>true,'id'=>$stmt->insert_id]);
      jsonRes(['ok'=>false,'error'=>$mysqli->error]);
      break;

    // --------------------------------------------------------------------------------------------------------------------

    case 'edit_user':
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $id = intval($_POST['id'] ?? 0); if(!$id) jsonRes(['ok'=>false,'error'=>'missing id']);
      $username = trim($_POST['username'] ?? '');
      $role = ($_POST['role'] ?? 'staff') === 'owner' ? 'owner' : 'staff';
      $branch_id = (isset($_POST['branch_id']) && $_POST['branch_id']!=='') ? intval($_POST['branch_id']) : null;
      if($branch_id === null) {
          $sql = "UPDATE users SET username=?, role=?, assigned_branch_id=NULL WHERE id=?";
          $stmt = $mysqli->prepare($sql); $stmt->bind_param('ssi',$username,$role,$id);
      } else {
          $sql = "UPDATE users SET username=?, role=?, assigned_branch_id=? WHERE id=?";
          $stmt = $mysqli->prepare($sql); $stmt->bind_param('ssii',$username,$role,$branch_id,$id);
      }
      jsonRes(['ok'=>$stmt->execute()]);
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
      if($type==='sales'){
        $res = $mysqli->query("SELECT s.*, u.username, b.name AS branch_name FROM receipts s LEFT JOIN users u ON s.created_by=u.id LEFT JOIN branches b ON s.branch_id=b.id ORDER BY s.id DESC LIMIT 200");
        $out=[]; 
        while($r=$res->fetch_assoc()) {
          $items = json_decode($r['items'], true);
          $product_names = [];
          if (is_array($items)) {
            foreach ($items as $item) {
              $product_names[] = $item['name'];
            }
          }
          $r['products'] = implode(', ', $product_names);
          $out[]=$r;
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

case 'export_sales_logs':
  if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
  $res = $mysqli->query("SELECT s.*, u.username, b.name AS branch_name FROM receipts s LEFT JOIN users u ON s.created_by=u.id LEFT JOIN branches b ON s.branch_id=b.id ORDER BY s.id DESC");
  $csv = "\"Receipt ID\",\"Products\",\"Total\",\"Payment Method\",\"User\",\"Branch\",\"Time\"\r\n";
  while($r=$res->fetch_assoc()) {
    $items = json_decode($r['items'], true);
    $product_names = [];
    if (is_array($items)) {
      $i = 1;
      foreach ($items as $item) {
        $product_names[] = $i . '. ' . $item['name'];
        $i++;
      }
    }
    $r['products'] = implode("\r\n", $product_names);
    $csv .= '"' . $r['receipt_no'] . '","' . $r['products'] . '","' . $r['total'] . '","' . $r['payment_mode'] . '","' . $r['username'] . '","' . $r['branch_name'] . '","' . $r['created_at'] . "'\r\n";
  }
  jsonRes(['ok'=>true, 'csv'=>$csv]);
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

    if ($qty > $stock_row['quantity']) $qty = $stock_row['quantity'];
    if ($qty <= 0) continue;

    $line = floatval($row['price']) * $qty;
    $total += $line;
    $detailed[] = [
      'id' => intval($row['id']),
      'name' => $row['name'],
      'qty' => $qty,
      'size' => $size,
      'price' => floatval($row['price'])
    ];

    $stmt2 = $mysqli->prepare("UPDATE product_stocks SET quantity = quantity - ? WHERE product_id=? AND size = ? AND quantity >= ?");
    $stmt2->bind_param('iisi', $qty, $pid, $size, $qty);
    $stmt2->execute();

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

  $stmt = $mysqli->prepare("INSERT INTO receipts (receipt_no, items, total, payment_mode, branch_id, created_by, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())");
  $stmt->bind_param('ssdsii', $receipt_no, $items_json, $total, $payment, $branch_id, $user_id);

  if ($stmt->execute()) {
    // ✅ Log action
    $meta = 'receipt:' . $receipt_no;
    $log = $mysqli->prepare("INSERT INTO action_logs (user_id, action, meta, branch_id) VALUES (?, 'checkout', ?, ?)");
    $log->bind_param('isi', $user_id, $meta, $branch_id);
    $log->execute();

    $mysqli->commit();
    // UPDATED: Include detailed items, payment mode, and timestamp in the response
    jsonRes([
        'ok' => true, 
        'receipt_no' => $receipt_no, 
        'total' => $total, 
        'items_sold' => $detailed, 
        'payment_mode' => $payment, 
        'timestamp' => date('Y-m-d H:i:s')
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
  
        $low_stocks_query = "SELECT p.name, ps.size, ps.quantity FROM product_stocks ps JOIN products p ON ps.product_id = p.id WHERE ps.quantity <= 3 ORDER BY ps.quantity ASC, p.name ASC";
        $low_stocks_result = $mysqli->query($low_stocks_query);
        while ($row = $low_stocks_result->fetch_assoc()) {
            $dashboard_data['low_stocks'][] = $row;
        }        

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

      $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? AND role = 'owner' LIMIT 1");
      $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? AND role = 'owner' LIMIT 1"); #username is the email
      $stmt->bind_param('s', $email);
      $stmt->execute();
      $res = $stmt->get_result();
      $user = $res->fetch_assoc();

      if (!$user) {
          // To prevent user enumeration, we send a success message even if the email doesn't exist.
          jsonRes(['ok' => true, 'message' => 'If an owner account with that email exists, a password reset code has been sent.']);
          jsonRes(['ok' => true, 'message' => 'If an owner account with that email exists, a password reset code has been sent.']); #email is the username
      }

      $user_id = $user['id'];
      $verification_code = random_int(100000, 999999); // 6-digit code
      $expiration_time = date('Y-m-d H:i:s', strtotime('+15 minutes'));

      // Delete any old codes for this user
      $del_stmt = $mysqli->prepare("DELETE FROM password_reset_requests WHERE user_id = ?");
      $del_stmt->bind_param('i', $user_id);
      $del_stmt->execute();

      // Insert new code
      $ins_stmt = $mysqli->prepare("INSERT INTO password_reset_requests (user_id, verification_code, expiration_time) VALUES (?, ?, ?)");
      $ins_stmt->bind_param('iss', $user_id, $verification_code, $expiration_time);
      $ins_stmt->execute();

      // --- Email Sending ---
      $subject = "Your Password Reset Code";
      $message = "Your password reset code is: " . $verification_code . "\n";
      $message .= "This code will expire in 15 minutes.\n";
      $headers = 'From: no-reply@motify.com' . "\r\n" .
                 'Reply-To: no-reply@motify.com' . "\r\n" .
                 'X-Mailer: PHP/' . phpversion();
      
      // Note: The mail() function requires a configured mail server (SMTP) on your web server to work.
      // On a local XAMPP setup, this will likely fail without additional configuration.
      @mail($email, $subject, $message, $headers);

      jsonRes(['ok' => true, 'message' => 'If an owner account with that email exists, a password reset code has been sent.']);
      jsonRes(['ok' => true, 'message' => 'If an owner account with that email exists, a password reset code has been sent.']); #email is the username
      break;

    case 'reset_password':
      if ($method !== 'POST') jsonRes(['ok' => false, 'error' => 'POST required']);
      $code = trim($_POST['code'] ?? '');
      $password = $_POST['password'] ?? '';

      if (empty($code) || empty($password)) jsonRes(['ok' => false, 'error' => 'Code and new password are required.']);

      $stmt = $mysqli->prepare("SELECT user_id FROM password_reset_requests WHERE verification_code = ? AND expiration_time > NOW() LIMIT 1");
      $stmt->bind_param('s', $code);
      $stmt->execute();
      $res = $stmt->get_result();
      if (!$req = $res->fetch_assoc()) jsonRes(['ok' => false, 'error' => 'Invalid or expired verification code.']);

      $hash = password_hash($password, PASSWORD_DEFAULT);
      $update_stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'owner'");
      $update_stmt->bind_param('si', $hash, $req['user_id']);
      if ($update_stmt->execute()) {
        $del_stmt = $mysqli->prepare("DELETE FROM password_reset_requests WHERE user_id = ?");
        $del_stmt->bind_param('i', $req['user_id']);
        $del_stmt->execute();
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