// script.js
document.addEventListener('DOMContentLoaded', ()=>{

  // Simple API helper - UPDATED: Added robust error handling
  function api(action, opts={}){
    opts = opts || {};
    const method = (opts.method||'GET').toUpperCase();
    const params = Object.assign({}, opts.params || {});
    params.action = action;

    const doFetch = (url, fetchOpts) => {
        return fetch(url, fetchOpts)
            .then(r => {
                if (!r.ok) {
                    // Try to parse error message if available, otherwise return generic error
                    return r.text().then(text => {
                        try {
                            const errorJson = JSON.parse(text);
                            return errorJson; // If PHP returned {ok:false, error:...}
                        } catch (e) {
                            // Non-JSON response (e.g. PHP error/500), return generic fail
                            console.error(`Non-JSON API error from ${action}: ${text}`);
                            return { ok: false, error: `Server returned status ${r.status}` };
                        }
                    });
                }
                return r.json().catch(e => {
                    // Catch JSON parsing failure even if status is 200 (e.g. empty response)
                    console.error("JSON parse error:", e);
                    return { ok: false, error: 'Invalid JSON response from server' };
                });
            })
            .catch(e => {
                // Catch network failure (e.g. server down)
                console.error("API network call failed:", e);
                return { ok: false, error: 'Network connection error' };
            });
    };
    
    if(method === 'GET'){
      const url = 'api.php?' + new URLSearchParams(params).toString();
      return doFetch(url);
    } else {
      if(opts.body instanceof FormData){
        return doFetch('api.php?action='+action, { method:'POST', body: opts.body });
      } else {
        const body = opts.body && typeof opts.body === 'object' ? JSON.stringify(opts.body) : (opts.body||'');
        return doFetch('api.php?action='+action, { method:'POST', headers:{'Content-Type':'application/json'}, body: body });
      }
    }
  }

  // Helpers
  function escapeHtml(s){ if(s===undefined||s===null) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  function debounce(fn, ms=250){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }

  // Panels
  const panels = {
    dashboard: document.getElementById('panel-dashboard'),
    inventory: document.getElementById('panel-inventory'),
    branches: document.getElementById('panel-branches'),
    accounts: document.getElementById('panel-accounts'),
    logs: document.getElementById('panel-logs'),
    pos: document.getElementById('panel-pos')
  };
  function showPanel(name){
    Object.values(panels).forEach(p=>p && p.classList.add('d-none'));
    if(panels[name]) panels[name].classList.remove('d-none');
  }
  ['dashboard','inventory','branches','accounts','logs','pos'].forEach(id=>{
    const el = document.getElementById('menu-'+id);
    if(!el) return;
    el.addEventListener('click', ()=> showPanel(id));
    // ADDED: Default load action logs when logs panel is opened
    if (id === 'logs') el.addEventListener('click', loadActionLogs);
  });

  // CART
  window.CART = window.CART || [];
  function addToCart(id,qty=1){ qty = parseInt(qty||1,10); const ex = CART.find(x=>x.id==id); if(ex) ex.qty += qty; else CART.push({id:parseInt(id,10), qty}); updateCartUI(); }
  function updateCartUI(){ const count = CART.reduce((s,i)=>s+i.qty,0); const el = document.getElementById('cartCount'); if(el) el.textContent = count; }

  // Branch selects (populate and enforce staff behavior)
  function populateBranchSelects(){
    return api('get_branches').then(res=>{
      if(!res.ok) return;
      const selects = document.querySelectorAll('#posBranchSelect,#filterBranch,#addItemBranchSelect,#editItemBranchSelect,#userBranchSelect,#editUserBranchSelect');
      selects.forEach(s=>{
        if(!s) return;
        s.innerHTML = '<option value="">All Branches</option>' + res.branches.map(b=>`<option value="${b.id}">${escapeHtml(b.name)}</option>`).join('');
      });
      // If ASSIGNED_BRANCH is set (server-side), set posBranchSelect and lock for staff
      const posSel = document.getElementById('posBranchSelect');
      if(posSel && typeof ASSIGNED_BRANCH !== 'undefined' && ASSIGNED_BRANCH){
        posSel.value = ASSIGNED_BRANCH;
      }
      // If staff role, disable branch selector to prevent switching branch on UI (server also enforces)
      if(typeof USER_ROLE !== 'undefined' && USER_ROLE === 'staff'){
        const p = document.getElementById('posBranchSelect');
        if(p) p.disabled = true;
      }
    });
  }

  // POS products
  function loadPOSProducts(){
    const container = document.getElementById('posProducts');
    if(!container) return;
    const q = document.getElementById('posSearch') ? document.getElementById('posSearch').value : '';
    const branch = document.getElementById('posBranchSelect') ? document.getElementById('posBranchSelect').value : '';
    api('get_products',{ params:{ q: q, branch_id: branch } }).then(res=>{
      if(!res.ok){ container.innerHTML = `<div class="text-danger">Failed to load products: ${escapeHtml(res.error)}</div>`; console.error(res.error); return; }
      const arr = res.products || [];
      if(arr.length===0){ container.innerHTML = '<div class="text-muted">No products found</div>'; return; }
      container.innerHTML = arr.map(p=>`
        <div class="col-md-3 col-sm-6">
          <div class="card h-100">
            <img src="${escapeHtml(p.photo||'uploads/no-image.png')}" class="card-img-top" style="height:140px;object-fit:cover" alt="${escapeHtml(p.name)}">
            <div class="card-body p-2">
              <div class="fw-bold">${escapeHtml(p.name)}</div>
              <div class="small text-muted">${escapeHtml(p.category||'')}</div>
              <div class="fw-semibold mt-1">₱${Number(p.price||0).toFixed(2)}</div>
              <div class="mt-2 text-center">
                <input type="number" min="1" value="1" class="form-control form-control-sm qty-for-${p.id}" style="width:80px;margin:0 auto;">
                <button class="btn btn-sm btn-primary w-100 mt-2 addpos" data-id="${p.id}">Add</button>
              </div>
            </div>
          </div>
        </div>
      `).join('');
      container.querySelectorAll('.addpos').forEach(b=> b.addEventListener('click', ()=>{
        const id = b.dataset.id; const qty = parseInt(document.querySelector('.qty-for-'+id).value||'1',10);
        addToCart(id, qty);
      }));
    });
  }

  document.getElementById('posSearch')?.addEventListener('input', debounce(loadPOSProducts,300));
  document.getElementById('posBranchSelect')?.addEventListener('change', loadPOSProducts);

  // Inventory list (view-only for staff)
  function loadInventory(){
    const tbl = document.getElementById('inventoryContent'); if(!tbl) return;
    const q = document.getElementById('inventorySearch') ? document.getElementById('inventorySearch').value : '';
    const branch = document.getElementById('filterBranch') ? document.getElementById('filterBranch').value : '';
    api('get_products',{ params:{ q:q, branch_id: branch } }).then(res=>{
      if(!res.ok) { console.error(res.error); return; }
      const rows = res.products.map(p=>`<tr>
        <td>${p.id}</td>
        <td>${escapeHtml(p.name)}</td>
        <td>${escapeHtml(p.category||'')}</td>
        <td>₱${Number(p.price||0).toFixed(2)}</td>
        <td>${p.stock}</td>
        <td>${escapeHtml(p.branch_name||'')}</td>
        <td>${USER_ROLE==='owner'?`<button class="btn btn-sm btn-outline-primary edit-product" data-id="${p.id}">Edit</button> <button class="btn btn-sm btn-danger delete-product" data-id="${p.id}">Delete</button>`:'Read-only'}</td>
      </tr>`).join('');
      tbl.innerHTML = `<table class="table"><thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Branch</th><th>Actions</th></tr></thead><tbody>${rows}</tbody></table>`;
      document.querySelectorAll('.edit-product').forEach(btn=> btn.addEventListener('click', ()=>{
        const id = btn.dataset.id;
        const prod = res.products.find(x=>x.id==id);
        if(!prod) return;
        document.getElementById('editItemId').value = prod.id;
        document.getElementById('editItemName').value = prod.name;
        document.getElementById('editItemCategory').value = prod.category;
        document.getElementById('editItemQty').value = prod.stock;
        document.getElementById('editItemPrice').value = prod.price;
        setTimeout(()=>{ const sel=document.getElementById('editItemBranchSelect'); if(sel) sel.value = prod.branch_id||''; },120);
        document.getElementById('editItemCurrentImg').src = prod.photo || 'uploads/no-image.png'; document.getElementById('editItemCurrentImg').style.display='block';
        new bootstrap.Modal(document.getElementById('editItemModal')).show();
      }));
      document.querySelectorAll('.delete-product').forEach(btn=> btn.addEventListener('click', ()=>{
        if(!confirm('Delete product?')) return;
        const fd = new FormData(); fd.append('id', btn.dataset.id);
        fetch('api.php?action=delete_product',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ if(res.ok) loadInventory(); else alert(res.error||'Error'); });
      }));
    });
  }
  document.getElementById('inventorySearch')?.addEventListener('input', debounce(loadInventory, 300));
  document.getElementById('filterBranch')?.addEventListener('change', loadInventory);

  // Add product form
  const addItemForm = document.getElementById('addItemForm');
  if(addItemForm) addItemForm.addEventListener('submit', e=>{
    e.preventDefault();
    const fd = new FormData(addItemForm);
    fetch('api.php?action=add_product',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ if(res.ok){ bootstrap.Modal.getInstance(document.getElementById('addItemModal'))?.hide(); loadInventory(); loadPOSProducts(); } else alert(res.error||'Error'); });
  });

  // Edit product form
  const editItemForm = document.getElementById('editItemForm');
  if(editItemForm) editItemForm.addEventListener('submit', e=>{
    e.preventDefault();
    const fd = new FormData(editItemForm);
    fetch('api.php?action=edit_product',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ if(res.ok){ bootstrap.Modal.getInstance(document.getElementById('editItemModal'))?.hide(); loadInventory(); loadPOSProducts(); } else alert(res.error||'Error'); });
  });

  // Branches
  function loadBranches(){
    api('get_branches').then(res=>{
      if(!res.ok) return;
      const list = document.getElementById('branchesManage');
      if(!list) return;
      list.innerHTML = res.branches.map(b=>`<div class="card p-2 mb-2"><div class="d-flex justify-content-between align-items-center"><strong>${escapeHtml(b.name)}</strong><div>${USER_ROLE==='owner'?`<button class='btn btn-sm btn-primary edit-branch' data-id='${b.id}' data-name='${escapeHtml(b.name)}'>Edit</button> <button class='btn btn-sm btn-danger delete-branch' data-id='${b.id}'>Delete</button>`:''}</div></div></div>`).join('');
      document.querySelectorAll('.edit-branch').forEach(btn=> btn.addEventListener('click', ()=>{ document.getElementById('editBranchId').value=btn.dataset.id; document.getElementById('editBranchName').value=btn.dataset.name; new bootstrap.Modal(document.getElementById('editBranchModal')).show(); }));
      document.querySelectorAll('.delete-branch').forEach(btn=> btn.addEventListener('click', ()=>{ if(!confirm('Delete branch?')) return; const fd=new FormData(); fd.append('id',btn.dataset.id); fetch('api.php?action=delete_branch',{ method:'POST', body:fd }).then(r=>r.json()).then(res=>{ if(res.ok){ loadBranches(); populateBranchSelects(); loadInventory(); } else alert(res.error||'Error'); }); }));
    });
  }

  // Add branch
  const addBranchForm = document.getElementById('addBranchForm');
  if(addBranchForm) addBranchForm.addEventListener('submit', e=>{ e.preventDefault(); const fd = new FormData(addBranchForm); fetch('api.php?action=add_branch',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ if(res.ok){ bootstrap.Modal.getInstance(document.getElementById('addBranchModal'))?.hide(); loadBranches(); populateBranchSelects(); } else alert(res.error||'Error'); }); });

  // Edit branch
  const editBranchForm = document.getElementById('editBranchForm');
  if(editBranchForm) editBranchForm.addEventListener('submit', e=>{ e.preventDefault(); const fd=new FormData(editBranchForm); fetch('api.php?action=edit_branch',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ if(res.ok){ bootstrap.Modal.getInstance(document.getElementById('editBranchModal'))?.hide(); loadBranches(); populateBranchSelects(); } else alert(res.error||'Error'); }); });

  // Accounts admin panel
  function loadAccounts(){
    api('get_accounts').then(res=>{ 
      if(!res.ok) return; 
      const tbl = document.getElementById('accountsContent'); 
      if(!tbl) return; 
      tbl.innerHTML = `<table class="table"><thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Branch</th><th>Actions</th></tr></thead><tbody>${res.accounts.map(a=>`<tr><td>${a.id}</td><td>${escapeHtml(a.username)}</td><td>${escapeHtml(a.role)}</td><td>${escapeHtml(a.branch_name||'')}</td><td><button class="btn btn-sm btn-primary edit-account" data-id="${a.id}">Edit</button> <button class="btn btn-sm btn-danger delete-account" data-id="${a.id}">Delete</button></td></tr>`).join('')}</tbody></table>`; 
      
      document.querySelectorAll('.edit-account').forEach(btn=> btn.addEventListener('click', ()=>{
        const id = btn.dataset.id;
        const user = res.accounts.find(x=>x.id==id);
        if(!user) return;
        
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUserName').value = user.username;
        document.getElementById('editUserRole').value = user.role;
        setTimeout(()=>{ 
            const sel=document.getElementById('editUserBranchSelect'); 
            if(sel) sel.value = user.assigned_branch_id||''; 
        },100);
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
      }));

      document.querySelectorAll('.delete-account').forEach(b=> b.addEventListener('click', ()=>{ if(!confirm('Delete user?')) return; const fd=new FormData(); fd.append('id', b.dataset.id); fetch('api.php?action=delete_user',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ if(res.ok) loadAccounts(); else alert(res.error||'Error'); }); })); 
    });
  }

  // register quick modal for admin
  const registerForm = document.getElementById('registerForm');
  if(registerForm) registerForm.addEventListener('submit', e=>{ e.preventDefault(); const fd = new FormData(registerForm); fetch('api.php?action=add_user',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ if(res.ok){ bootstrap.Modal.getInstance(document.getElementById('registerModal'))?.hide(); loadAccounts(); } else alert(res.error||'Error'); }); });

  // Edit user form handler
  const editUserForm = document.getElementById('editUserForm');
  if(editUserForm) editUserForm.addEventListener('submit', e=>{ 
    e.preventDefault(); 
    const fd = new FormData(editUserForm); 
    fetch('api.php?action=edit_user',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ 
      if(res.ok){ 
        bootstrap.Modal.getInstance(document.getElementById('editUserModal'))?.hide(); 
        loadAccounts(); 
      } else alert(res.error||'Error'); 
    }); 
  });


  // Logs - UPDATED: clearer display
function loadActionLogs(){ 
    const el = document.getElementById('logsContent'); 
    if(!el) return; 
    el.innerHTML = '<div class="text-center text-muted">Loading action logs...</div>';
    api('get_logs',{ params:{ type:'action' }}).then(res=>{ 
      if(!res.ok) { el.innerHTML = `<div class="text-danger">Failed to load action logs: ${escapeHtml(res.error||'Unknown error')}</div>`; return; }
      const rows = res.actions.map(l=>`<tr>
        <td>${l.id}</td>
        <td>${escapeHtml(l.action)}</td>
        <td>${escapeHtml(l.username||'N/A')}</td>
        <td>${escapeHtml(l.branch_name||'N/A')}</td>
        <td>${escapeHtml(l.created_at)}</td>
        <td>${escapeHtml(l.meta||'')}</td>
        <td>${USER_ROLE==='owner'?`<button class="btn btn-sm btn-danger delete-log" data-id="${l.id}">Delete</button>`:'Read-only'}</td>
      </tr>`).join('');
      el.innerHTML = `<table class="table"><thead><tr><th>ID</th><th>Action</th><th>User</th><th>Branch</th><th>Time</th><th>Meta</th><th>Actions</th></tr></thead><tbody>${rows}</tbody></table>`; 
      
      // ADDED: Delete button handler
      document.querySelectorAll('.delete-log').forEach(btn=> btn.addEventListener('click', ()=>{
        if(!confirm('Are you sure you want to permanently delete this log entry?')) return;
        const fd = new FormData(); fd.append('id', btn.dataset.id);
        fetch('api.php?action=delete_log',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ 
          if(res.ok) loadActionLogs(); 
          else alert(res.error||'Error deleting log'); 
        });
      }));
    }); 
  }

  function loadSalesLogs(){ 
    const el = document.getElementById('logsContent'); 
    if(!el) return; 
    el.innerHTML = '<div class="text-center text-muted">Loading sales logs...</div>';
    api('get_logs',{ params:{ type:'sales' }}).then(res=>{ 
      if(!res.ok) { el.innerHTML = `<div class="text-danger">Failed to load sales logs: ${escapeHtml(res.error||'Unknown error')}</div>`; return; }
      const rows = res.sales.map(s=>`<tr>
        <td>${s.id}</td>
        <td>${escapeHtml(s.receipt_no)}</td>
        <td>₱${Number(s.total||0).toFixed(2)}</td>
        <td>${escapeHtml(s.payment_mode)}</td>
        <td>${escapeHtml(s.username||'N/A')}</td>
        <td>${escapeHtml(s.branch_name||'N/A')}</td>
        <td>${escapeHtml(s.created_at)}</td>
      </tr>`).join('');
      el.innerHTML = `<table class="table"><thead><tr><th>ID</th><th>Receipt No</th><th>Total</th><th>Payment</th><th>User</th><th>Branch</th><th>Time</th></tr></thead><tbody>${rows}</tbody></table>`; 
    }); 
  }

  document.getElementById('showActionLogs')?.addEventListener('click', loadActionLogs);
  document.getElementById('showSalesLogs')?.addEventListener('click', loadSalesLogs);

  // Checkout flow (floating cart) - Now should show an alert if API fails
  document.getElementById('cartButton')?.addEventListener('click', ()=>{
    const items = CART.slice();
    if(items.length===0) return alert('Cart empty');
    const elem = document.getElementById('checkoutItems'); if(!elem) return;
    elem.innerHTML = '';
    let total = 0;

    // fetch product details once (filter by selected branch if any)
    const branch = document.getElementById('posBranchSelect') ? document.getElementById('posBranchSelect').value : '';
    api('get_products', { params: { q: '', branch_id: branch } }).then(res=>{
      if(!res.ok){ alert(`Failed to load products for checkout: ${res.error}`); return; }
      const productsById = {};
      (res.products||[]).forEach(p=> productsById[p.id] = p);

      items.forEach(it=>{
        const p = productsById[it.id];
        if(!p) return; // product missing
        const line = Number(p.price) * it.qty;
        total += line;
        elem.innerHTML += `<div class="d-flex justify-content-between"><div>${escapeHtml(p.name)} x ${it.qty}</div><div>₱${line.toFixed(2)}</div></div>`;
      });

      document.getElementById('checkoutTotal').textContent = total.toFixed(2);
      // show modal
      new bootstrap.Modal(document.getElementById('checkoutModal')).show();
    });
  });

  // Checkout flow (floating cart) - Now with detailed receipt generation
  document.getElementById('confirmCheckout')?.addEventListener('click', ()=>{
    const pm = document.getElementById('paymentMode').value || 'Cash';
    const branch = document.getElementById('posBranchSelect') ? document.getElementById('posBranchSelect').value : '';
    const payload = { items: CART, payment_mode: pm, branch_id: branch };
    
    // Process the response to build a better receipt
    api('checkout',{ method:'POST', body: payload }).then(res=>{ // Using the robust API helper
      if(res.ok){
        CART = []; updateCartUI();
        new bootstrap.Modal(document.getElementById('checkoutModal'))?.hide();
        
        // --- START RECEIPT GENERATION ---
        
        let receiptText = "";
        
        // Header
        receiptText += "--------------------------------------\n";
        receiptText += "          MOTIFY RETAIL RECEIPT       \n";
        receiptText += "--------------------------------------\n";
        receiptText += `Receipt No: ${res.receipt_no}\n`;
        // Use the returned timestamp for accuracy
        receiptText += `Date: ${new Date(res.timestamp).toLocaleString()}\n`; 
        receiptText += "--------------------------------------\n";
        receiptText += "ITEM              QTY   PRICE    TOTAL\n";
        receiptText += "--------------------------------------\n";

        // Items List
        // Helper function for padded formatting
        const formatLine = (name, qty, price, total) => {
            let line = name.padEnd(17).substring(0, 17);
            line += String(qty).padStart(4);
            line += ` ${price.toFixed(2).padStart(7)}`;
            line += ` ${total.toFixed(2).padStart(8)}\n`;
            return line;
        };

        (res.items_sold || []).forEach(item => {
            const lineTotal = item.qty * item.price;
            receiptText += formatLine(item.name, item.qty, item.price, lineTotal);
        });
        
        // Footer
        receiptText += "--------------------------------------\n";
        receiptText += `TOTAL:                  ₱ ${Number(res.total).toFixed(2).padStart(8)}\n`;
        receiptText += `PAYMENT MODE:           ${escapeHtml(res.payment_mode)}\n`;
        receiptText += "--------------------------------------\n";
        receiptText += "THANK YOU FOR YOUR PURCHASE!\n";
        
        // --- END RECEIPT GENERATION ---
        
        document.getElementById('receiptContent').innerText = receiptText;
        new bootstrap.Modal(document.getElementById('receiptModal')).show();
        loadInventory(); loadPOSProducts(); loadBranches();
      } else alert(res.error||'Error');
    });
  });
  // Print Handler Function
document.getElementById('printReceiptButton')?.addEventListener('click', () => {
    const content = document.getElementById('receiptContent').innerText;
    
    // Create a temporary iframe or window to contain only the receipt content
    const printWindow = window.open('', '', 'height=600,width=400');
    printWindow.document.write('<html><head><title>Receipt</title>');
    // Simple styling for better print simulation
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: monospace; font-size: 10px; margin: 10px; }');
    printWindow.document.write('pre { white-space: pre; margin: 0; padding: 0; }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<pre>' + content + '</pre>');
    printWindow.document.write('</body></html>');
    
    printWindow.document.close();
    printWindow.focus();
    
    // Delay print call slightly to ensure content is rendered
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
});

  // Init
  populateBranchSelects().then(()=>{ loadBranches(); loadInventory(); loadPOSProducts(); loadAccounts(); updateCartUI(); });

});