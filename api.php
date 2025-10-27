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

    // ---------------- PRODUCTS ----------------
    case 'get_products':
      $q = trim($_GET['q'] ?? $_REQUEST['q'] ?? '');
      // branch filter: if staff, default to assigned branch
      $branch = null;
      if(isset($_GET['branch_id'])) $branch = $_GET['branch_id'] !== '' ? intval($_GET['branch_id']) : null;
      elseif(isset($_REQUEST['branch_id'])) $branch = $_REQUEST['branch_id'] !== '' ? intval($_REQUEST['branch_id']) : null;

      // If staff, enforce assigned branch in POS queries when requested
      if(isset($_SESSION['role']) && $_SESSION['role']==='staff'){
        $assigned = $_SESSION['assigned_branch_id'] ?? null;
        if($assigned) $branch = intval($assigned);
      }

      $sql = "SELECT p.id,p.sku,p.name,p.category,p.price,p.stock,p.branch_id,COALESCE(p.photo,'') AS photo, b.name AS branch_name
              FROM products p LEFT JOIN branches b ON p.branch_id=b.id WHERE 1=1";
      if($branch!==null && $branch!=='') $sql .= " AND p.branch_id = ".intval($branch);
      if($q!==''){
        $q_esc = $mysqli->real_escape_string($q);
        $sql .= " AND (p.name LIKE '%$q_esc%' OR p.sku LIKE '%$q_esc%' OR p.category LIKE '%$q_esc%')";
      }
      $sql .= " ORDER BY p.id DESC LIMIT 1000";
      $res = $mysqli->query($sql);
      if($res===false) jsonRes(['ok'=>false,'error'=>$mysqli->error]);
      $out=[];
      while($r=$res->fetch_assoc()){
        $img = trim($r['photo']);
        if($img==='') $r['photo'] = 'uploads/no-image.png';
        else { if(strpos($img,'/')===false) $r['photo']='uploads/'.$img; else $r['photo']=$img; }
        $r['id'] = (int)$r['id'];
        $r['price'] = (float)$r['price'];
        $r['stock'] = (int)$r['stock'];
        $r['branch_id'] = $r['branch_id']!==null ? (int)$r['branch_id'] : null;
        $out[]=$r;
      }
      jsonRes(['ok'=>true,'products'=>$out]);
      break;

    case 'add_product': // owner only
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $name = trim($_POST['item_name'] ?? $_POST['name'] ?? '');
      if($name==='') jsonRes(['ok'=>false,'error'=>'missing name']);
      $sku = trim($_POST['sku'] ?? '');
      $category = trim($_POST['category'] ?? '');
      $stock = intval($_POST['quantity'] ?? $_POST['stock'] ?? 0);
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
      $sql = "INSERT INTO products (sku,name,category,price,stock,branch_id,photo) VALUES (?,?,?,?,?,?,?)";
      $stmt = $mysqli->prepare($sql);
      $stmt->bind_param('sssdiis', $sku,$name,$category,$price,$stock,$branch_id,$photo_path);
      if($stmt->execute()) jsonRes(['ok'=>true,'id'=>$stmt->insert_id]);
      jsonRes(['ok'=>false,'error'=>$mysqli->error]);
      break;

    case 'edit_product': // owner only
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $id = intval($_POST['id'] ?? 0); if(!$id) jsonRes(['ok'=>false,'error'=>'missing id']);
      $name = trim($_POST['name'] ?? $_POST['item_name'] ?? ''); if($name==='') jsonRes(['ok'=>false,'error'=>'missing name']);
      $sku = trim($_POST['sku'] ?? '');
      $category = trim($_POST['category'] ?? '');
      $stock = intval($_POST['stock'] ?? $_POST['quantity'] ?? 0);
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
      $fields[] = "stock=?"; $types.='i'; $vals[]=$stock;
      if($branch_id===null) $fields[] = "branch_id = NULL";
      else { $fields[] = "branch_id = ?"; $types.='i'; $vals[]=$branch_id;}
      if($photo_path !== null){ $fields[] = "photo = ?"; $types.='s'; $vals[]=$photo_path; }
      $sql = "UPDATE products SET ".implode(', ',$fields)." WHERE id=?";
      $types .= 'i'; $vals[] = $id;
      $stmt = $mysqli->prepare($sql);
      if($types!=='') $stmt->bind_param($types, ...$vals);
      if($stmt->execute()) jsonRes(['ok'=>true]);
      jsonRes(['ok'=>false,'error'=>$mysqli->error]);
      break;

    case 'delete_product':
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $id = intval($_POST['id'] ?? 0);
      if(!$id) jsonRes(['ok'=>false,'error'=>'missing id']);
      $stmt = $mysqli->prepare("DELETE FROM products WHERE id=?");
      $stmt->bind_param('i',$id);
      jsonRes(['ok'=>$stmt->execute()]);
      break;

    // ---------------- ACCOUNTS ----------------
    case 'get_accounts':
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $res = $mysqli->query("SELECT u.id,u.username,u.role,u.assigned_branch_id,b.name AS branch_name FROM users u LEFT JOIN branches b ON u.assigned_branch_id=b.id ORDER BY u.id DESC");
      $out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
      jsonRes(['ok'=>true,'accounts'=>$out]);
      break;

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

    case 'delete_user':
      if($method!=='POST') jsonRes(['ok'=>false,'error'=>'POST required']);
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $id = intval($_POST['id'] ?? 0); if(!$id) jsonRes(['ok'=>false,'error'=>'missing id']);
      $stmt = $mysqli->prepare("DELETE FROM users WHERE id=?"); $stmt->bind_param('i',$id);
      jsonRes(['ok'=>$stmt->execute()]);
      break;

    // ---------------- LOGS ----------------
    case 'get_logs':
      if(!is_owner()) jsonRes(['ok'=>false,'error'=>'forbidden']);
      $type = $_GET['type'] ?? 'action'; // action or sales
      if($type==='sales'){
        $res = $mysqli->query("SELECT s.*, u.username, b.name AS branch_name FROM receipts s LEFT JOIN users u ON s.created_by=u.id LEFT JOIN branches b ON s.branch_id=b.id ORDER BY s.id DESC LIMIT 200");
        $out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
        jsonRes(['ok'=>true,'sales'=>$out]);
      } else {
        $res = $mysqli->query("SELECT l.*, u.username, b.name AS branch_name FROM action_logs l LEFT JOIN users u ON l.user_id=u.id LEFT JOIN branches b ON l.branch_id=b.id ORDER BY l.id DESC LIMIT 500");
        $out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
        jsonRes(['ok'=>true,'actions'=>$out]);
      }
      break;

// ---------------- RECENT/SUMMARY & CHECKOUT ----------------
case 'get_recent_sales':
  $res = $mysqli->query("SELECT r.*, u.username FROM receipts r LEFT JOIN users u ON r.created_by=u.id ORDER BY r.id DESC LIMIT 10");
  $out=[]; while($r=$res->fetch_assoc()) $out[]=$r;
  jsonRes(['ok'=>true,'sales'=>$out]);
  break;

case 'get_stats':
  $r = $mysqli->query("SELECT COUNT(*) AS cnt FROM products")->fetch_assoc();
  $r2 = $mysqli->query("SELECT IFNULL(SUM(stock),0) AS total_stock FROM products")->fetch_assoc();
  $r3 = $mysqli->query("SELECT COUNT(*) AS sales_count FROM receipts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc();
  jsonRes(['ok'=>true,'products'=>intval($r['cnt']),'total_stock'=>floatval($r2['total_stock']),'sales_30d'=>intval($r3['sales_count'])]);
  break;

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
    if ($pid <= 0 || $qty <= 0) continue;

    $stmt = $mysqli->prepare("SELECT id,name,price,stock FROM products WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$row = $res->fetch_assoc()) continue;
    if ($qty > $row['stock']) $qty = $row['stock'];
    if ($qty <= 0) continue;

    $line = floatval($row['price']) * $qty;
    $total += $line;
    $detailed[] = [
      'id' => intval($row['id']),
      'name' => $row['name'],
      'qty' => $qty,
      'price' => floatval($row['price'])
    ];

    $stmt2 = $mysqli->prepare("UPDATE products SET stock = stock - ? WHERE id=? AND stock >= ?");
    $stmt2->bind_param('iii', $qty, $pid, $qty);
    $stmt2->execute();

    if ($stmt2->affected_rows <= 0) {
      $mysqli->rollback();
      jsonRes(['ok' => false, 'error' => 'insufficient stock for product id ' . $pid]);
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


    default:
      jsonRes(['ok'=>false,'error'=>'unknown action']);
  }

} catch(Exception $e){
  jsonRes(['ok'=>false,'error'=>$e->getMessage()]);
}