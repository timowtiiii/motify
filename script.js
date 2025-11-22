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

  const formatCurrency = (num) => Number(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  // Panels
  let inventoryRefreshInterval = null; // For real-time inventory updates

  const panels = {
    dashboard: document.getElementById('panel-dashboard'),
    inventory: document.getElementById('panel-inventory'),
    branches: document.getElementById('panel-branches'),
    suppliers: document.getElementById('panel-suppliers'),
    accounts: document.getElementById('panel-accounts'),
    consignment: document.getElementById('panel-consignment'),
    logs: document.getElementById('panel-logs'),
    pos: document.getElementById('panel-pos')
  };
  function showPanel(name){
    Object.values(panels).forEach(p=>p && p.classList.add('d-none'));
    if(panels[name]) panels[name].classList.remove('d-none');
    document.body.scrollTop = document.documentElement.scrollTop = 0; // Scroll to top

    const menuItems = document.querySelectorAll('.sidebar .list-group-item');
    menuItems.forEach(item => {
        if (item.id === `menu-${name}`) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
    
    // Start/Stop inventory polling based on visible panel
    if (name === 'inventory') {
        if (!inventoryRefreshInterval) {
            console.log('Starting inventory auto-refresh.');
            inventoryRefreshInterval = setInterval(loadInventory, 10000); // Refresh every 10 seconds
        }
    } else {
        if (inventoryRefreshInterval) {
            console.log('Stopping inventory auto-refresh.');
            clearInterval(inventoryRefreshInterval);
            inventoryRefreshInterval = null;
        }
    }
  }
  ['dashboard','inventory','branches','suppliers','accounts','consignment','logs','pos'].forEach(id=>{
    const el = document.getElementById('menu-'+id); if(!el) return;
    el.addEventListener('click', ()=> showPanel(id)); if (id === 'logs') el.addEventListener('click', loadActionLogs); if (id === 'suppliers') el.addEventListener('click', loadSuppliers); if (id === 'consignment') el.addEventListener('click', loadConsignments);
  });

  // CART
  window.CART = window.CART || [];
  function addToCart(id, size, qty = 1) {
    qty = parseInt(qty || 1, 10);
    const ex = CART.find(x => x.id == id && x.size == size);
    if (ex) {
        ex.qty += qty;
    } else {
        CART.push({ id: parseInt(id, 10), size, qty });
    }
    updateCartUI();
  }
  function updateCart(id, size, newQty) {
    const item = CART.find(x => x.id == id && x.size == size);
    if (item) {
        if (newQty > 0) {
            item.qty = newQty;
        } else {
            CART = CART.filter(x => !(x.id == id && x.size == size));
        }
    }
    updateCartUI();
    renderCart();
  }
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

  function populateSupplierSelects() {
    return api('get_suppliers').then(res => {
        if (!res.ok) return;
        const selects = document.querySelectorAll('.supplier-select');
        const options = res.suppliers.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
        selects.forEach(s => { s.innerHTML = '<option value="">Select Supplier</option>' + options; });
    });
  }

  // POS products
  function loadPOSProducts(){
    const container = document.getElementById('posProducts');
    if(!container) return;
    const q = document.getElementById('posSearch') ? document.getElementById('posSearch').value : '';
    const category = document.querySelector('#posCategoryFilter .btn.active')?.dataset.category || '';
    const branch = document.getElementById('posBranchSelect') ? document.getElementById('posBranchSelect').value : '';
    api('get_products',{ params:{ q: q, category: category, branch_id: branch, source: 'pos' } }).then(res=>{
      if(!res.ok){ container.innerHTML = `<div class="text-danger">Failed to load products: ${escapeHtml(res.error)}</div>`; console.error(res.error); return; }
      const arr = res.products || [];
      if(arr.length===0){ container.innerHTML = '<div class="text-muted">No products found</div>'; return; }

      const getStock = (stocks, size) => {
          const stock = stocks.find(s => s.size.toLowerCase() === size.toLowerCase());
          return stock ? stock.quantity : 0;
      };

      container.innerHTML = arr.map(p => {
        const totalStock = p.stocks.reduce((sum, s) => sum + s.quantity, 0);
        return `
          <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
            <div class="card h-100 pos-product-card-container">
              <div class="pos-product-header" data-product-id="${p.id}" style="cursor: pointer;">
                <img src="${escapeHtml(p.photo || 'uploads/no-image.png')}" class="card-img-top" style="height:100px;object-fit:cover" alt="${escapeHtml(p.name)}">
                <div class="card-body p-2">
                  <div class="fw-bold" style="font-size:0.9em;">${escapeHtml(p.name)}</div>
                  <div class="small text-muted">${escapeHtml(p.category || '')}</div>
                  <div class="fw-semibold mt-1">₱${formatCurrency((p.price || 0) * 1.12)}</div>
                  <div class="badge ${totalStock > 0 ? 'bg-success-light' : 'bg-danger-light'} text-dark mt-1">
                    ${totalStock} Items Available
                  </div>
                </div>
              </div>
              <div class="pos-product-details p-2 d-none">
                <!-- This content will be generated on click -->
              </div>
            </div>
          </div>
        `;
      }).join('');

      // Attach click listener to new product card headers
      container.querySelectorAll('.pos-product-header').forEach(header => {
        header.addEventListener('click', () => {
          const productId = header.dataset.productId;
          const product = res.products.find(p => p.id == productId);
          if (!product) return;

          const detailsContainer = header.nextElementSibling;

          // Collapse any other open details sections
          document.querySelectorAll('.pos-product-details').forEach(d => {
            if (d !== detailsContainer) {
              d.classList.add('d-none');
              d.innerHTML = ''; // Clear content to save memory
            }
          });

          // Toggle the current one
          detailsContainer.classList.toggle('d-none');

          // If we are opening it, populate the content
          if (!detailsContainer.classList.contains('d-none')) {
            let stockHtml = '';
            if (product.stock_display_type === 'regular') {
              stockHtml = `<div class="regular-stock-display input-group-text bg-light border-end-0 mb-2"><small>Stock: ${getStock(product.stocks, 'os')}</small></div>`;
            } else { // sizes
              stockHtml = `<div class="size-boxes d-flex justify-content-center gap-2 mb-3">
                  <div class="size-box" data-size="s">S<br><small>${getStock(product.stocks, 's')} left</small></div>
                  <div class="size-box" data-size="m">M<br><small>${getStock(product.stocks, 'm')} left</small></div>
                  <div class="size-box" data-size="l">L<br><small>${getStock(product.stocks, 'l')} left</small></div>
                  <div class="size-box" data-size="xl">XL<br><small>${getStock(product.stocks, 'xl')} left</small></div>
                </div>`;
            }
            stockHtml += `<div class="input-group input-group-sm mt-2">
                <input type="number" min="1" value="1" class="form-control qty-input">
                <button class="btn btn-primary btn-sm add-to-cart-btn">Add</button>
              </div>`;
            detailsContainer.innerHTML = stockHtml;

            // Add event listeners for size selection
            detailsContainer.querySelectorAll('.size-box').forEach(box => {
              box.addEventListener('click', (e) => {
                e.stopPropagation();
                detailsContainer.querySelectorAll('.size-box').forEach(b => b.classList.remove('selected'));
                box.classList.add('selected');
                const addButton = detailsContainer.querySelector('.add-to-cart-btn');
                const stockText = box.querySelector('small')?.textContent || '0 left';
                addButton.disabled = parseInt(stockText, 10) <= 0;
              });
            });

            // Add event listener for the "Add to Cart" button
            detailsContainer.querySelector('.add-to-cart-btn').addEventListener('click', (e) => {
              e.stopPropagation();
              const qty = parseInt(detailsContainer.querySelector('.qty-input').value || '1', 10);
              if (product.stock_display_type === 'regular') {
                addToCart(product.id, 'os', qty, res.products);
              } else {
                const selectedSizeBox = detailsContainer.querySelector('.size-box.selected');
                if (!selectedSizeBox) { alert('Please select a size.'); return; }
                const size = selectedSizeBox.dataset.size;
                addToCart(product.id, size, qty, res.products);
              }
            });
          }
        });
      });

      // Simplified addToCart with stock checking
      window.addToCart = (id, size, qty, productList) => {
        const product = productList.find(p => p.id == id);
        if (!product) return;

        const stockAvailable = (product.stocks.find(s => s.size === size) || {}).quantity || 0;
        const itemInCart = CART.find(item => item.id == id && item.size === size);
        const qtyInCart = itemInCart ? itemInCart.qty : 0;

        if (qty + qtyInCart > stockAvailable) {
            alert(`Cannot add. Stock for this item is only ${stockAvailable}. You already have ${qtyInCart} in cart.`);
            return;
        }

        const existingItem = CART.find(x => x.id == id && x.size == size);
        if (existingItem) { existingItem.qty += qty; } 
        else { CART.push({ id: parseInt(id, 10), size, qty }); }
        updateCartUI();
      };
    }); // End of api().then()
  }

  document.getElementById('posSearch')?.addEventListener('input', debounce(loadPOSProducts, 300));
  document.getElementById('posBranchSelect')?.addEventListener('change', loadPOSProducts);
  document.querySelectorAll('#posCategoryFilter .btn').forEach(btn => {
    btn.addEventListener('click', () => {
      // Set active state
      document.querySelectorAll('#posCategoryFilter .btn').forEach(b => b.classList.remove('btn-secondary', 'active'));
      document.querySelectorAll('#posCategoryFilter .btn').forEach(b => b.classList.add('btn-outline-secondary'));
      btn.classList.remove('btn-outline-secondary');
      btn.classList.add('btn-secondary', 'active');
      loadPOSProducts();
    });
  });

  // Inventory list (view-only for staff)
  function loadInventory(){
    const tbl = document.getElementById('inventoryContent'); if(!tbl) return;
    const q = document.getElementById('inventorySearch') ? document.getElementById('inventorySearch').value : '';
    const category = document.querySelector('#inventoryCategoryFilter .btn.active')?.dataset.category || '';
    const branch = document.getElementById('filterBranch') ? document.getElementById('filterBranch').value : '';
    const stockLevel = document.getElementById('inventoryStockLevelFilter') ? document.getElementById('inventoryStockLevelFilter').value : '';
    api('get_products',{ params:{ q:q, category: category, branch_id: branch, source: 'inventory', stock_level: stockLevel } }).then(res=>{
      if(!res.ok) { console.error(res.error); return; }
      console.log(res.products);

      const getStockLevelIndicator = (quantity) => {
        const qty = Number(quantity);
        let color = 'danger'; // red for 0
        let width = (qty / 20) * 100; // Max width at 20 items

        if (qty > 10) {
            color = 'success';
        } else if (qty > 5) {
            color = 'info';
        } else if (qty > 0) {
            color = 'warning';
        }
        if (width > 100) width = 100;

        return `
          <div class="progress" style="height: 1.25rem;">
            <div class="progress-bar bg-${color}" role="progressbar" style="width: ${width}%;" aria-valuenow="${qty}" aria-valuemin="0" aria-valuemax="20">
              <span class="fw-bold">${qty}</span>
            </div>
          </div>`;
      };

      const rows = res.products.map(p => `<tr>
        <td>${p.id}</td>
        <td>${escapeHtml(p.name)}</td>
        <td>${escapeHtml(p.category || '')}</td>
        <td>₱${formatCurrency(p.price || 0)}</td>
        <td style="min-width: 150px;">
          ${(p.stocks && p.stocks.length > 0)
            ? p.stocks.map(s => `<div class="d-flex align-items-center mb-1"><div class="me-2" style="width:50px;">${s.size === 'os' ? 'Stock' : s.size.toUpperCase()}:</div><div class="flex-grow-1">${getStockLevelIndicator(s.quantity)}</div></div>`).join('')
            : '<span class="text-muted">N/A</span>'}
        </td>
        <td>${(p.consigned_stock && p.consigned_stock > 0)
            ? `<span class="badge bg-info">Consigned: ${p.consigned_stock}</span>`
            : ''
        }</td>
        <td>${escapeHtml(p.branch_name || '')}</td>
        <td>${USER_ROLE === 'owner' ? `<button class="btn btn-sm btn-outline-primary edit-product" data-id="${p.id}">Edit</button> <button class="btn btn-sm btn-danger delete-product" data-id="${p.id}">Delete</button>` : 'Read-only'}</td>
      </tr>`).join('');
      tbl.innerHTML = `<table class="table"><thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Consignment</th><th>Branch</th><th>Actions</th></tr></thead><tbody>${rows}</tbody></table>`;
      document.querySelectorAll('.edit-product').forEach(btn=> btn.addEventListener('click', ()=>{
        const id = btn.dataset.id;
        const prod = res.products.find(x=>x.id==id);
        if(!prod) return;
        document.getElementById('editItemId').value = prod.id;
        document.getElementById('editItemName').value = prod.name;
        const categorySelect = document.getElementById('editItemCategory');
        categorySelect.value = prod.category;

        // Determine if the product uses regular stock ('os') or sized stock
        const hasRegularStock = prod.stocks.some(s => s.size === 'os');
        const stockType = hasRegularStock ? 'regular' : 'sizes';

        // Manually trigger change to update stock fields visibility
        handleCategoryChange(
            prod.category,
            document.getElementById('editItemSizeStock'),
            document.getElementById('editItemRegularStock'),
            document.getElementById('editItemOthersStockTypeContainer')
        );

        // Also trigger the 'others' stock type handler if needed
        handleOthersStockTypeChange(stockType, document.getElementById('editItemSizeStock'), document.getElementById('editItemRegularStock'));
        document.getElementById('editItemPrice').value = prod.price;

        // Reset all stock fields
        document.getElementById('editItemStockS').value = 0;
        document.getElementById('editItemStockM').value = 0;
        document.getElementById('editItemStockL').value = 0;
        document.getElementById('editItemStockXL').value = 0;
        document.getElementById('editItemStockRegular').value = 0;

        if(prod.stocks){
            prod.stocks.forEach(s => {
                let input;
                if (s.size === 'os') {
                    input = document.getElementById('editItemStockRegular');
                } else {
                    input = document.getElementById(`editItemStock${s.size.toUpperCase()}`);
                }
                if(input) input.value = s.quantity;
            });
        }
        setTimeout(()=>{ const sel=document.getElementById('editItemBranchSelect'); if(sel) sel.value = prod.branch_id||''; },120);
        document.getElementById('editItemCurrentImg').src = prod.photo || 'uploads/no-image.png'; document.getElementById('editItemCurrentImg').style.display='block';
        new bootstrap.Modal(document.getElementById('editItemModal')).show();
      }));
      document.querySelectorAll('.delete-product').forEach(btn=> btn.addEventListener('click', ()=>{
        if(!confirm('Delete product?')) return;
        const fd = new FormData(); fd.append('id', btn.dataset.id);
        fetch('api.php?action=delete_product',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ if(res.ok){ loadInventory(); loadPOSProducts(); } else alert(res.error||'Error'); });
      }));
    });
  }
  document.getElementById('inventorySearch')?.addEventListener('input', debounce(loadInventory, 300));
  document.getElementById('filterBranch')?.addEventListener('change', loadInventory);
  document.getElementById('inventoryStockLevelFilter')?.addEventListener('change', loadInventory);

  // Add product form
  const addItemForm = document.getElementById('addItemForm');
  if(addItemForm) addItemForm.addEventListener('submit', e=>{
    e.preventDefault();
    if (!addItemForm.checkValidity()) {
      e.stopPropagation();
      addItemForm.classList.add('was-validated');
      return;
    }
    const fd = new FormData(addItemForm);
    fetch('api.php?action=add_product',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ 
      if(res.ok){ bootstrap.Modal.getInstance(document.getElementById('addItemModal'))?.hide(); addItemForm.reset(); addItemForm.classList.remove('was-validated'); loadInventory(); loadPOSProducts(); } 
      else alert(res.error||'Error'); 
    });
  });

  // Add/Edit Modal Category Change Handler
  function handleCategoryChange(categoryValue, sizeStockEl, regularStockEl, othersContainerEl) {
      if (categoryValue === 'bracket' || categoryValue === 'topbox') {
          sizeStockEl.classList.add('d-none');
          regularStockEl.classList.remove('d-none');
          if (othersContainerEl) othersContainerEl.classList.add('d-none');
      } else if (categoryValue === 'others') {
          sizeStockEl.classList.add('d-none');
          regularStockEl.classList.add('d-none');
          if (othersContainerEl) othersContainerEl.classList.remove('d-none');
      } else {
          sizeStockEl.classList.remove('d-none');
          regularStockEl.classList.add('d-none');
          if (othersContainerEl) othersContainerEl.classList.add('d-none');
      }
  }

  const addItemCategorySelect = document.querySelector('#addItemModal select[name="category"]');
  if (addItemCategorySelect) {
      addItemCategorySelect.addEventListener('change', (e) => {
          handleCategoryChange(e.target.value, document.getElementById('addItemSizeStock'), document.getElementById('addItemRegularStock'), document.getElementById('addItemOthersStockTypeContainer'));
      });
  }
  const editItemCategorySelect = document.querySelector('#editItemModal select[name="category"]');
  if (editItemCategorySelect) {
      editItemCategorySelect.addEventListener('change', (e) => {
          handleCategoryChange(e.target.value, document.getElementById('editItemSizeStock'), document.getElementById('editItemRegularStock'), document.getElementById('editItemOthersStockTypeContainer'));
      });
  }

  // Handler for the new "others" stock type dropdown
  function handleOthersStockTypeChange(typeValue, sizeStockEl, regularStockEl) {
      if (typeValue === 'sizes') {
          sizeStockEl.classList.remove('d-none');
          regularStockEl.classList.add('d-none');
      } else if (typeValue === 'regular') {
          sizeStockEl.classList.add('d-none');
          regularStockEl.classList.remove('d-none');
      } else {
          sizeStockEl.classList.add('d-none');
          regularStockEl.classList.add('d-none');
      }
  }

  document.querySelector('#addItemModal select[name="others_stock_type"]')?.addEventListener('change', (e) => {
      handleOthersStockTypeChange(e.target.value, document.getElementById('addItemSizeStock'), document.getElementById('addItemRegularStock'));
  });
  document.querySelector('#editItemModal select[name="others_stock_type"]')?.addEventListener('change', (e) => {
      handleOthersStockTypeChange(e.target.value, document.getElementById('editItemSizeStock'), document.getElementById('editItemRegularStock'));
  });

  // Edit product form
  const editItemForm = document.getElementById('editItemForm');
  if(editItemForm) editItemForm.addEventListener('submit', e=>{
    e.preventDefault();
    if (!editItemForm.checkValidity()) {
      e.stopPropagation();
      editItemForm.classList.add('was-validated');
      return;
    }
    const fd = new FormData(editItemForm);
    fetch('api.php?action=edit_product',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ 
      if(res.ok){ bootstrap.Modal.getInstance(document.getElementById('editItemModal'))?.hide(); editItemForm.classList.remove('was-validated'); loadInventory(); loadPOSProducts(); } 
      else alert(res.error||'Error'); 
    });
  });

  // Branches
  function loadBranches(){
    api('get_branches').then(res=>{
      if(!res.ok) return;
      const list = document.getElementById('branchesManage');
      if(!list) return;
      list.innerHTML = res.branches.map(b=>`<div class="list-group-item d-flex justify-content-between align-items-center p-2 mb-2 border rounded"><strong>${escapeHtml(b.name)}</strong><div>${USER_ROLE==='owner'?`<button class='btn btn-sm btn-primary edit-branch' data-id='${b.id}' data-name='${escapeHtml(b.name)}'>Edit</button> <button class='btn btn-sm btn-danger delete-branch' data-id='${b.id}'>Delete</button>`:''}</div></div>`).join('');
      document.querySelectorAll('.edit-branch').forEach(btn=> btn.addEventListener('click', ()=>{ document.getElementById('editBranchId').value=btn.dataset.id; document.getElementById('editBranchName').value=btn.dataset.name; new bootstrap.Modal(document.getElementById('editBranchModal')).show(); }));
      document.querySelectorAll('.delete-branch').forEach(btn=> btn.addEventListener('click', ()=>{ if(!confirm('Delete branch?')) return; const fd=new FormData(); fd.append('id',btn.dataset.id); fetch('api.php?action=delete_branch',{ method:'POST', body:fd }).then(r=>r.json()).then(res=>{ if(res.ok){ loadBranches(); populateBranchSelects(); loadInventory(); } else alert(res.error||'Error'); }); }));
    });
  }

  // Add branch
  const addBranchForm = document.getElementById('addBranchForm');
  if(addBranchForm) addBranchForm.addEventListener('submit', e=>{ 
    e.preventDefault(); 
    if (!addBranchForm.checkValidity()) { e.stopPropagation(); addBranchForm.classList.add('was-validated'); return; }
    const fd = new FormData(addBranchForm); 
    fetch('api.php?action=add_branch',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ 
      if(res.ok){ bootstrap.Modal.getInstance(document.getElementById('addBranchModal'))?.hide(); addBranchForm.reset(); addBranchForm.classList.remove('was-validated'); loadBranches(); populateBranchSelects(); } 
      else alert(res.error||'Error'); 
    }); 
  });

  // Edit branch
  const editBranchForm = document.getElementById('editBranchForm');
  if(editBranchForm) editBranchForm.addEventListener('submit', e=>{ 
    e.preventDefault(); 
    if (!editBranchForm.checkValidity()) { e.stopPropagation(); editBranchForm.classList.add('was-validated'); return; }
    const fd=new FormData(editBranchForm); 
    fetch('api.php?action=edit_branch',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ 
      if(res.ok){ bootstrap.Modal.getInstance(document.getElementById('editBranchModal'))?.hide(); editBranchForm.classList.remove('was-validated'); loadBranches(); populateBranchSelects(); } 
      else alert(res.error||'Error'); 
    }); 
  });

  // Suppliers
  function loadSuppliers(){
    const tbl = document.getElementById('suppliersContent'); if(!tbl) return;
    api('get_suppliers').then(res=>{
      if(!res.ok) { console.error(res.error); return; }
      const rows = res.suppliers.map(s=>`<tr>
        <td>${s.id}</td>
        <td>${escapeHtml(s.name)}</td>
        <td>${escapeHtml(s.email||'')}</td>
        <td>${escapeHtml(s.phone||'')}</td>
        <td>${escapeHtml(s.location||'')}</td>
        <td>${escapeHtml(s.brands||'')}</td>
        <td>${escapeHtml(s.products||'')}</td>
        <td><button class="btn btn-sm btn-primary edit-supplier" data-id="${s.id}">Edit</button> <button class="btn btn-sm btn-danger delete-supplier" data-id="${s.id}">Delete</button></td>
      </tr>`).join('');
      tbl.innerHTML = `<table class="table"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Location</th><th>Brands</th><th>Products</th><th>Actions</th></tr></thead><tbody>${rows}</tbody></table>`;
      
      document.querySelectorAll('.edit-supplier').forEach(btn=> btn.addEventListener('click', ()=>{
        const id = btn.dataset.id;
        const supplier = res.suppliers.find(x=>x.id==id);
        if(!supplier) return;

        document.getElementById('editSupplierId').value = supplier.id;
        document.getElementById('editSupplierName').value = supplier.name;
        document.getElementById('editSupplierEmail').value = supplier.email;
        document.getElementById('editSupplierPhone').value = supplier.phone;
        document.getElementById('editSupplierLocation').value = supplier.location;
        document.getElementById('editSupplierBrands').value = supplier.brands;
        document.getElementById('editSupplierProducts').value = supplier.products;

        new bootstrap.Modal(document.getElementById('editSupplierModal')).show();
      }));

      document.querySelectorAll('.delete-supplier').forEach(btn=> btn.addEventListener('click', ()=>{
        if(!confirm('Delete supplier?')) return;
        const fd = new FormData(); fd.append('id', btn.dataset.id);
        fetch('api.php?action=delete_supplier',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ if(res.ok) loadSuppliers(); else alert(res.error||'Error'); });
      }));
    });
  }

  const addSupplierForm = document.getElementById('addSupplierForm');
  if(addSupplierForm) addSupplierForm.addEventListener('submit', e=>{
    e.preventDefault();
    if (!addSupplierForm.checkValidity()) { e.stopPropagation(); addSupplierForm.classList.add('was-validated'); return; }
    const fd = new FormData(addSupplierForm);
    fetch('api.php?action=add_supplier',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ 
      if(res.ok){ bootstrap.Modal.getInstance(document.getElementById('addSupplierModal'))?.hide(); addSupplierForm.reset(); addSupplierForm.classList.remove('was-validated'); loadSuppliers(); } 
      else alert(res.error||'Error'); 
    });
  });

  const editSupplierForm = document.getElementById('editSupplierForm');
  if(editSupplierForm) editSupplierForm.addEventListener('submit', e=>{
    e.preventDefault();
    if (!editSupplierForm.checkValidity()) { e.stopPropagation(); editSupplierForm.classList.add('was-validated'); return; }
    const fd = new FormData(editSupplierForm);
    fetch('api.php?action=edit_supplier',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ 
      if(res.ok){ 
        bootstrap.Modal.getInstance(document.getElementById('editSupplierModal'))?.hide(); 
        editSupplierForm.classList.remove('was-validated'); 
        loadSuppliers(); 
      } else {
        alert(res.error||'Error'); 
      }
    });
  });

  // Consignment
  function loadConsignments() {
      const tbl = document.getElementById('consignmentContent'); if (!tbl) return;
      const supplierId = document.getElementById('consignmentSupplierFilter')?.value || '';

      api('get_consignments', { params: { supplier_id: supplierId } }).then(res => {
          if (!res.ok) { console.error(res.error); tbl.innerHTML = `<div class="text-danger">Failed to load data.</div>`; return; }

          // Calculate total owed based on the value of UNSOLD items from ACTIVE consignments
          const totalOwed = res.consignments
              .filter(c => c.status === 'active')
              .reduce((sum, c) => sum + ((c.quantity_consigned - c.quantity_sold) * c.cost_price), 0);
          document.getElementById('totalOwed').textContent = formatCurrency(totalOwed);

          const rows = res.consignments.map(c => `<tr>
              <td>${c.id}</td>
              <td>${escapeHtml(c.product_name)}</td>
              <td>${escapeHtml(c.supplier_name)}</td>
              <td>${escapeHtml(c.branch_name || 'N/A')}</td>
              <td>${c.quantity_consigned}</td>
              <td>${c.quantity_sold}</td>
              <td>₱${formatCurrency(c.cost_price)}</td>
              <td>₱${formatCurrency((c.quantity_consigned - c.quantity_sold) * c.cost_price)}</td>
              <td><span class="badge bg-${c.status === 'active' ? 'warning' : 'success'}">${c.status}</span></td>
              <td>
                ${c.status === 'active' ? `
                  <button class="btn btn-sm btn-primary edit-consignment" data-id="${c.id}">Edit</button>
                  <button class="btn btn-sm btn-success mark-paid" data-id="${c.id}">Mark Paid</button>
                ` : ''}
              </td>
          </tr>`).join('');
          tbl.innerHTML = `<table class="table"><thead><tr><th>ID</th><th>Product</th><th>Supplier</th><th>Branch</th><th>Consigned</th><th>Sold</th><th>Cost Price</th><th>Total Owed</th><th>Status</th><th>Action</th></tr></thead><tbody>${rows}</tbody></table>`;

          document.querySelectorAll('.edit-consignment').forEach(btn => btn.addEventListener('click', () => {
              const id = btn.dataset.id;
              const consignment = res.consignments.find(c => c.id == id);
              if (!consignment) return;

              document.getElementById('editConsignmentId').value = consignment.id;
              document.getElementById('editConsignmentProduct').textContent = consignment.product_name;
              document.getElementById('editConsignmentSupplier').textContent = consignment.supplier_name;
              document.getElementById('editConsignmentCostPrice').value = consignment.cost_price;
              document.getElementById('editConsignmentQuantity').value = consignment.quantity_consigned;
              document.getElementById('editConsignmentQuantity').min = consignment.quantity_sold; // Can't set quantity lower than what's already sold

              new bootstrap.Modal(document.getElementById('editConsignmentModal')).show();
          }));

          document.querySelectorAll('.mark-paid').forEach(btn => btn.addEventListener('click', () => {
              if (!confirm('Are you sure you want to mark this as paid? This action cannot be undone.')) return;
              const id = btn.dataset.id;
              api('mark_consignment_paid', { method: 'POST', body: { id: id } }).then(res => {
                  if (res.ok) {
                      loadConsignments();
                  } else {
                      alert(res.error || 'Failed to update status.');
                  }
              });
          }));
      });
  }

  document.getElementById('consignmentSupplierFilter')?.addEventListener('change', loadConsignments);

  document.getElementById('addConsignmentBtn')?.addEventListener('click', () => {
      // Populate products in the modal
      const productSelect = document.getElementById('consignmentProduct');
      const branchSelect = document.getElementById('consignmentBranch');

      // Reset and disable product dropdown initially
      productSelect.innerHTML = '<option value="">Select a branch first</option>';
      productSelect.disabled = true;
      branchSelect.innerHTML = '<option value="">Loading branches...</option>';

      // Populate branches first
      api('get_branches').then(res => {
          if(res.ok) branchSelect.innerHTML = '<option value="">Select Branch</option>' + res.branches.map(b=>`<option value="${b.id}">${escapeHtml(b.name)}</option>`).join('');
      });

      // Add a one-time event listener for the branch change
      const branchChangeHandler = () => {
          const selectedBranchId = branchSelect.value;
          productSelect.disabled = true;

          if (!selectedBranchId) {
              productSelect.innerHTML = '<option value="">Select a branch first</option>';
              return;
          }

          productSelect.innerHTML = '<option value="">Loading products...</option>';
          // Fetch products filtered by the selected branch
          api('get_products', { params: { source: 'inventory', branch_id: selectedBranchId } }).then(res => {
              if (res.ok) {
                  productSelect.innerHTML = '<option value="">Select Product</option>' + res.products.map(p => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('');
                  productSelect.disabled = false;
              }
          });
      };

      branchSelect.addEventListener('change', branchChangeHandler);

      new bootstrap.Modal(document.getElementById('addConsignmentModal')).show();
  });

  const addConsignmentForm = document.getElementById('addConsignmentForm');
  if (addConsignmentForm) {
      addConsignmentForm.addEventListener('submit', e => {
          e.preventDefault();
          if (!addConsignmentForm.checkValidity()) {
            e.stopPropagation();
            addConsignmentForm.classList.add('was-validated');
            return;
          }
          const form = e.target;
          const productId = form.querySelector('[name="product_id"]').value;
          const quantity = form.querySelector('[name="quantity_consigned"]').value;
          const branchId = form.querySelector('[name="branch_id"]').value;
          const supplierId = form.querySelector('[name="supplier_id"]').value;
          const costPrice = form.querySelector('[name="cost_price"]').value;

          const payload = {
            product_id: productId,
            quantity_consigned: quantity,
            branch_id: branchId,
            supplier_id: supplierId,
            cost_price: costPrice
          };

          api('add_consignment', { 
            method: 'POST', 
            body: payload 
          }).then(res => {
              if (res.ok) {
                  bootstrap.Modal.getInstance(document.getElementById('addConsignmentModal'))?.hide();
                  form.reset();
                  form.classList.remove('was-validated');
                  loadConsignments();
                  loadInventory(); // Refresh inventory to reflect new stock
              } else {
                  alert(res.error || 'Error adding consignment.');
              }
          });
      });
  }

  const editConsignmentForm = document.getElementById('editConsignmentForm');
  if (editConsignmentForm) {
      editConsignmentForm.addEventListener('submit', e => {
          e.preventDefault();
          if (!editConsignmentForm.checkValidity()) {
            e.stopPropagation();
            editConsignmentForm.classList.add('was-validated');
            return;
          }
          const fd = new FormData(editConsignmentForm);
          api('edit_consignment', { method: 'POST', body: fd }).then(res => {
              if (res.ok) {
                  bootstrap.Modal.getInstance(document.getElementById('editConsignmentModal'))?.hide();
                  editConsignmentForm.classList.remove('was-validated');
                  loadConsignments();
                  loadInventory(); // Refresh inventory to reflect stock changes
              } else {
                  alert(res.error || 'Error updating consignment.');
              }
          });
      });
  }

  // Restrict phone input fields to numbers and specific signs
  const restrictPhoneInput = (e) => {
    e.target.value = e.target.value.replace(/[^0-9\+\-\(\)\s]/g, '');
  };
  document.querySelector('#addSupplierForm input[name="phone"]')?.addEventListener('input', restrictPhoneInput);
  document.getElementById('editSupplierPhone')?.addEventListener('input', restrictPhoneInput);

  // Accounts admin panel
  function loadAccounts(){
    api('get_accounts').then(res=>{
      if(!res.ok) return; 
      const tbl = document.getElementById('accountsContent'); 
      if(!tbl) return; 
      tbl.innerHTML = `<table class="table"><thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Branch</th><th>Actions</th></tr></thead><tbody>${res.accounts.map(a=>`<tr><td>${a.id}</td><td>${escapeHtml(a.username)}</td><td>${escapeHtml(a.email||'')}</td><td>${escapeHtml(a.role)}</td><td>${escapeHtml(a.branch_name||'')}</td><td><button class="btn btn-sm btn-primary edit-account" data-id="${a.id}">Edit</button> <button class="btn btn-sm btn-danger delete-account" data-id="${a.id}">Delete</button></td></tr>`).join('')}</tbody></table>`; 
      
      document.querySelectorAll('.edit-account').forEach(btn=> btn.addEventListener('click', ()=>{
        const id = btn.dataset.id;
        const user = res.accounts.find(x=>x.id==id);
        if(!user) return;
        
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUserName').value = user.username;
        document.getElementById('editUserEmail').value = user.email || '';
        setTimeout(()=>{ 
            const roleSelect = document.getElementById('editUserRole');
            if(roleSelect) roleSelect.value = user.role;
            const sel=document.getElementById('editUserBranchSelect'); 
            if(sel) sel.value = user.assigned_branch_id||''; 
            roleSelect.dispatchEvent(new Event('change')); // Trigger change to show/hide email field
        },100);
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
      }));

      document.querySelectorAll('.delete-account').forEach(b=> b.addEventListener('click', ()=>{ if(!confirm('Delete user?')) return; const fd=new FormData(); fd.append('id', b.dataset.id); fetch('api.php?action=delete_user',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ if(res.ok) loadAccounts(); else alert(res.error||'Error'); }); })); 
    });
  }

  // register quick modal for admin
  const registerForm = document.getElementById('registerForm');
  if(registerForm) registerForm.addEventListener('submit', e=>{ 
    e.preventDefault(); 
    if (!registerForm.checkValidity()) { e.stopPropagation(); registerForm.classList.add('was-validated'); return; }
    const fd = new FormData(registerForm); 
    fetch('api.php?action=add_user',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{ 
      if(res.ok){ 
        bootstrap.Modal.getInstance(document.getElementById('registerModal'))?.hide(); 
        loadAccounts(); 
        registerForm.reset(); 
        registerForm.classList.remove('was-validated');
      } else alert(res.error||'Error'); 
    }); 
  });

  // Edit user form handler
  const editUserForm = document.getElementById('editUserForm');
  if(editUserForm) editUserForm.addEventListener('submit', e=>{
    e.preventDefault(); 
    if (!editUserForm.checkValidity()) { e.stopPropagation(); editUserForm.classList.add('was-validated'); return; }
    const fd = new FormData(editUserForm); 
    fetch('api.php?action=edit_user',{ method:'POST', body: fd }).then(r=>r.json()).then(res=>{
      if(res.ok){ 
        bootstrap.Modal.getInstance(document.getElementById('editUserModal'))?.hide(); 
        editUserForm.classList.remove('was-validated');
        loadAccounts(); 
      } else alert(res.error||'Error'); 
    }); 
  });

  // Show/hide email field based on role selection
  document.getElementById('registerRole')?.addEventListener('change', e => {
    const emailGroup = document.getElementById('registerEmailGroup');
    if (e.target.value === 'owner') {
      emailGroup.classList.remove('d-none');
      emailGroup.querySelector('input').required = true;
    } else {
      emailGroup.classList.add('d-none');
      emailGroup.querySelector('input').required = false;
    }
  });

  document.getElementById('editUserRole')?.addEventListener('change', e => {
    const emailGroup = document.getElementById('editEmailGroup');
    if (e.target.value === 'owner') {
      emailGroup.classList.remove('d-none');
      emailGroup.querySelector('input').required = true;
    } else {
      emailGroup.classList.add('d-none');
      emailGroup.querySelector('input').required = false;
    }
  });


  // Logs - UPDATED: clearer display
  function loadActionLogs(){ 
    const el = document.getElementById('logsContent'); 
    if(!el) return; 
    el.innerHTML = '<div class="text-center text-muted">Loading action logs...</div>';

    const timeRangeType = document.getElementById('logsTimeRangeType')?.value || 'monthly';
    const timeRangeValue = document.getElementById('logsTimeRangeValue')?.value || '';
    const params = { type: 'action', time_range_type: timeRangeType, time_range_value: timeRangeValue };

    api('get_logs',{ params: params }).then(res=>{
      if(!res.ok) { el.innerHTML = `<div class="text-danger">Failed to load action logs: ${escapeHtml(res.error||'Unknown error')}</div>`; return; }
      const rows = res.actions.map(l=>`<tr>
        <td>${l.id}</td>
        <td>${escapeHtml(l.action)}</td>
        <td>${escapeHtml(l.username||'N/A')}</td>
        <td>${escapeHtml(l.branch_name||'N/A')}</td>
        <td>${escapeHtml(l.created_at)}</td>
        <td>${escapeHtml(l.meta||'')}</td>
      </tr>`).join('');
      el.innerHTML = `<table class="table"><thead><tr><th>ID</th><th>Action</th><th>User</th><th>Branch</th><th>Time</th><th>Meta</th></tr></thead><tbody>${rows}</tbody></table>`; 
    }); 
  }

  function loadSalesLogs(){
    const downloadBtn = document.getElementById('downloadPdf');
    if (downloadBtn) downloadBtn.style.display = 'block';
    const el = document.getElementById('logsContent'); 
    if(!el) return; 
    el.innerHTML = '<div class="text-center text-muted">Loading sales logs...</div>';

    const timeRangeType = document.getElementById('logsTimeRangeType')?.value || 'monthly';
    const timeRangeValue = document.getElementById('logsTimeRangeValue')?.value || '';
    const params = { type: 'sales', time_range_type: timeRangeType, time_range_value: timeRangeValue };
    api('get_logs',{ params: params }).then(res=>{

      if(!res.ok) { el.innerHTML = `<div class="text-danger">Failed to load sales logs: ${escapeHtml(res.error||'Unknown error')}</div>`; return; }

      const rows = res.sales.map((s, index) => {
        let itemsHtml = 'No item data';
        try {
          const items = JSON.parse(s.items);
          const formatLine = (name, qty, price, total) => {
              let line = `<td>${escapeHtml(name)}</td>`;
              line += `<td class="text-center">${qty}</td>`;
              line += `<td class="text-end">₱${formatCurrency(price)}</td>`;
              line += `<td class="text-end">₱${formatCurrency(total)}</td>`;
              return `<tr>${line}</tr>`;
          };
          if (Array.isArray(items)) {
            itemsHtml = items.map(item => 
              // The 'price' column will show the pre-VAT price (base_price).
              // The 'total' column will show the final VAT-inclusive total (price * qty).
              formatLine(`${item.name} (${item.size.toUpperCase()})`, 
                item.qty, 
                item.base_price ?? (item.price / 1.12), // Fallback for old receipts without base_price
                item.price * item.qty)
            ).join('');
          }
        } catch (e) { console.error('Failed to parse items JSON for receipt ' + s.receipt_no, s.items); }

        return `
          <tr class="log-summary-row" data-bs-toggle="collapse" data-bs-target="#log-details-${s.id}" style="cursor:pointer;">
            <td>${s.id}</td>
            <td>${escapeHtml(s.receipt_no)}</td>
            <td>₱${formatCurrency(s.total||0)}</td>
            <td>${escapeHtml(s.payment_mode)}</td>
            <td>${escapeHtml(s.username||'N/A')}</td>
            <td>${escapeHtml(s.branch_name||'N/A')}</td>
            <td>${escapeHtml(s.created_at)}</td>
            <td><button class="btn btn-sm btn-outline-info">Details</button></td>
          </tr>
          <tr class="collapse" id="log-details-${s.id}">
            <td colspan="8">
              <div class="p-3 bg-light">
                <div class="row">
                  <div class="col-md-8">
                    <h6>Items Sold:</h6>
                    <table class="table table-sm">
                      <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
                      <tbody>${itemsHtml}</tbody>
                    </table>
                  </div>
                  <div class="col-md-4">
                    <h6>Summary:</h6>
                    <ul class="list-group">
                      <li class="list-group-item d-flex justify-content-between"><span>Vatable Sales:</span> <strong>₱${formatCurrency(s.vatable_sales || 0)}</strong></li>
                      <li class="list-group-item d-flex justify-content-between"><span>VAT (12%):</span> <strong>₱${formatCurrency(s.vat_amount || 0)}</strong></li>
                      <li class="list-group-item d-flex justify-content-between bg-dark text-white">
                        <span class="fw-bold">TOTAL:</span> 
                        <strong class="fw-bold">₱${formatCurrency(s.total || 0)}</strong>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        `;
      }).join('');

      el.innerHTML = `
        <table class="table table-hover">
          <thead>
            <tr><th>ID</th><th>Receipt No</th><th>Total</th><th>Payment</th><th>User</th><th>Branch</th><th>Time</th><th>Action</th></tr>
          </thead>
          <tbody>
            ${rows}
          </tbody>
        </table>`; 
    }); 
  }

  // Event listeners for time range buttons
  document.getElementById('showActionLogs')?.addEventListener('click', loadActionLogs);
  document.getElementById('showSalesLogs')?.addEventListener('click', loadSalesLogs);
  
document.getElementById('downloadPdf')?.addEventListener('click', () => {
    const logType = document.querySelector('#logButtons .btn.active').id === 'showSalesLogs' ? 'sales' : 'action';
    const timeRangeType = document.getElementById('logsTimeRangeType')?.value || 'monthly';
    const selectedValue = document.getElementById('logsTimeRangeValue')?.value || '';

    const params = { type: logType, time_range_type: timeRangeType, time_range_value: selectedValue };



    api('get_logs', { params: params }).then(res => {
      if (res.ok) {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        const title = logType === 'sales' ? 'Sales Log' : 'Action Log';
        const headers = logType === 'sales' 
            ? [['ID', 'Receipt No', 'Products', 'Total', 'Payment', 'User', 'Branch', 'Time']]
            : [['ID', 'Action', 'User', 'Branch', 'Time', 'Meta']];
        
        const body = logType === 'sales'
            ? res.sales.map(s => [
                s.id, // ID
                s.receipt_no, // Receipt No
                (() => { // Products
                  try {
                    const items = JSON.parse(s.items);
                    if (Array.isArray(items)) {
                      return items.map(item => `${item.qty}x ${item.name} (${item.size.toUpperCase()})`).join('\n');
                    }
                  } catch (e) { /* ignore parse error */ }
                  return 'N/A';
                })(),
                `P ${formatCurrency(s.total || 0)}`, // Total
                // The rest of the fields remain the same
                s.payment_mode,
                s.username || 'N/A',
                s.branch_name || 'N/A',
                s.created_at
              ])
            : res.actions.map(l => [
                l.id,
                l.action,
                l.username || 'N/A',
                l.branch_name || 'N/A',
                l.created_at,
                l.meta || ''
              ]);

        doc.setFontSize(18);
        doc.text(title, 14, 22);
        doc.autoTable({
            head: headers,
            body: body,
            startY: 30,
            theme: 'grid',
            styles: { fontSize: 8 },
            headStyles: { fillColor: [22, 160, 133] },
        });

        const filename = `${logType}_log_${new Date().toISOString().slice(0,10)}.pdf`;
        doc.save(filename);
      } else {
        alert(res.error || `Could not generate ${logType} PDF.`);
      }
    });
  });

  function renderCart() {
    console.log('Rendering cart...');
    const items = CART.slice();
    if (items.length === 0) {
        const modalInstance = bootstrap.Modal.getInstance(document.getElementById('checkoutModal'));
        if (modalInstance) {
            modalInstance.hide();
        }
        return;
    }

    const elem = document.getElementById('checkoutItems');
    if (!elem) return;

    elem.innerHTML = '';
    let total = 0;

    const branch = document.getElementById('posBranchSelect') ? document.getElementById('posBranchSelect').value : '';
    api('get_products', { params: { q: '', branch_id: branch } }).then(res => {
        if (!res.ok) {
            alert(`Failed to load products for checkout: ${res.error}`);
            return;
        }

        const productsById = {};
        (res.products || []).forEach(p => productsById[p.id] = p);

        items.forEach(it => {
            const p = productsById[it.id];
            if (!p) return;

            const line = Number(p.price) * it.qty; // Use base price for calculation, VAT is handled on server
            total += line;

            elem.innerHTML += `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>${escapeHtml(p.name)} (${it.size.toUpperCase()})</div>
                    <div class="d-flex align-items-center">
                        <button class="btn btn-sm btn-outline-secondary cart-qty-minus" data-id="${p.id}" data-size="${it.size}">-</button>
                        <span class="mx-2">${it.qty}</span>
                        <button class="btn btn-sm btn-outline-secondary cart-qty-plus" data-id="${p.id}" data-size="${it.size}">+</button>
                        <button class="btn btn-sm btn-danger ms-2 cart-remove" data-id="${p.id}" data-size="${it.size}">x</button>
                        <div class="ms-3" style="width: 80px; text-align: right;">₱${line.toFixed(2)}</div>
                    </div>
                </div>`;
        });

        document.getElementById('checkoutTotal').textContent = total.toFixed(2);

        elem.querySelectorAll('.cart-qty-plus').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const size = btn.dataset.size;
                const item = CART.find(x => x.id == id && x.size == size);
                if (item) {
                    updateCart(id, size, item.qty + 1);
                }
            });
        });

        elem.querySelectorAll('.cart-qty-minus').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const size = btn.dataset.size;
                const item = CART.find(x => x.id == id && x.size == size);
                if (item) {
                    updateCart(id, size, item.qty - 1);
                }
            });
        });

        elem.querySelectorAll('.cart-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const size = btn.dataset.size;
                updateCart(id, size, 0);
            });
        });
    });
  }

  document.getElementById('cartButton')?.addEventListener('click', () => {
      if (CART.length === 0) {
          return alert('Cart empty');
      }
      renderCart();
      new bootstrap.Modal(document.getElementById('checkoutModal')).show();
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
        bootstrap.Modal.getInstance(document.getElementById('checkoutModal'))?.hide();
        
        // --- START RECEIPT GENERATION ---
        
        let receiptText = "";
        
        // Header
        receiptText += "--------------------------------------\n";
        receiptText += "          MOTIFY RETAIL RECEIPT       \n";
        receiptText += "--------------------------------------\n";
        receiptText += `Receipt No: ${res.receipt_no}\n`;
        // Use the returned timestamp for accuracy
        receiptText += `Cashier: ${escapeHtml(res.username)}\n`;
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
            const lineTotal = item.qty * item.price; // item.price is now VAT-inclusive from API
            receiptText += formatLine(item.name, item.qty, item.price, lineTotal);
        });
        
        // Footer
        receiptText += "--------------------------------------\n";
        receiptText += `Vatable Sales:          ₱ ${Number(res.vatable_sales).toFixed(2).padStart(8)}\n`;
        receiptText += `VAT (12%):              ₱ ${Number(res.vat_amount).toFixed(2).padStart(8)}\n`;
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

  // Function to generate the time range selector based on the selected type
  function generateTimeRangeSelector() {
    const container = document.getElementById('logsTimeRangeContainer');
    if (!container) return;

    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0'); // Month is 0-indexed
    const week = Math.ceil((((now - new Date(year, 0, 1)) / 86400000) + new Date(year, 0, 1).getDay() + 1) / 7);

    container.innerHTML = `
      <select class="form-select" id="logsTimeRangeType" style="flex: 0 0 100px;">
        <option value="all">All Time</option>
        <option value="weekly">Weekly</option>
        <option value="monthly" selected>Monthly</option>
        <option value="yearly">Yearly</option>
      </select>
      <input type="month" class="form-control" id="logsTimeRangeValue" value="${year}-${month}">
    `;

    const typeSelect = document.getElementById('logsTimeRangeType');
    const valueInput = document.getElementById('logsTimeRangeValue');

    const updateValueInput = () => {
        const type = typeSelect.value;
        let newElement;
        if (type === 'weekly') {
            newElement = document.createElement('input');
            newElement.type = 'week';
            newElement.value = `${year}-W${String(week).padStart(2, '0')}`;
        } else if (type === 'monthly') {
            newElement = document.createElement('input');
            newElement.type = 'month';
            newElement.value = `${year}-${month}`;
        } else if (type === 'yearly') {
            newElement = document.createElement('select');
            for (let i = year; i >= 2020; i--) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = i;
                newElement.appendChild(option);
            }
        } else { // 'all'
            newElement = document.createElement('input');
            newElement.type = 'text';
            newElement.readOnly = true;
            newElement.classList.add('d-none'); // Hide it for 'All Time'
        }
        newElement.id = 'logsTimeRangeValue';
        newElement.className = 'form-control';
        valueInput.replaceWith(newElement);
        newElement.addEventListener('change', () => {
            const currentLogType = document.querySelector('#logButtons .btn.active').id;
            if (currentLogType === 'showSalesLogs') loadSalesLogs();
            else loadActionLogs();
        });
        // Initial load for the default view (sales)
        loadSalesLogs();
    };

    typeSelect.addEventListener('change', updateValueInput);
  }

  // Initialize the new log filter UI
  generateTimeRangeSelector();

  // Set active state for log buttons
  const actionLogBtn = document.getElementById('showActionLogs');
  const salesLogBtn = document.getElementById('showSalesLogs');
  const timeRangeContainer = document.getElementById('logsTimeRangeContainer');

  actionLogBtn?.addEventListener('click', () => {
    timeRangeContainer.classList.remove('d-none');
    actionLogBtn.classList.add('active');
    salesLogBtn.classList.remove('active');
  });
  salesLogBtn?.addEventListener('click', () => {
    timeRangeContainer.classList.remove('d-none');
    salesLogBtn.classList.add('active');
    actionLogBtn.classList.remove('active');
  });

  // Add event listeners to all modals to reset forms when they are hidden
  document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('hidden.bs.modal', () => {
      const form = modal.querySelector('form');
      if (form) {
        form.reset();
        form.classList.remove('was-validated');
      }
      // Also reset any image previews
      const preview = modal.querySelector('img[id$="Preview"], img[id$="CurrentImg"]');
      if (preview) {
        preview.style.display = 'none';
        preview.src = '';
      }
    });
  });

  // Init
  function loadDashboard() {
    const container = document.getElementById('dashboard-grid');
    if (!container) return;

    api('get_dashboard_data').then(res => {
        if (!res.ok) {
            container.innerHTML = `<div class="text-danger">Failed to load dashboard: ${escapeHtml(res.error)}</div>`;
            return;
        }

        // Check role from response and render appropriate dashboard
        if (res.role === 'staff') {
            const trendingItemsByCategory = res.trending_items || {};
            let staffDashboardHtml = '';

            if (Object.keys(trendingItemsByCategory).length > 0) {
                for (const category in trendingItemsByCategory) {
                    const items = trendingItemsByCategory[category];
                    staffDashboardHtml += `
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Hot Deals: ${escapeHtml(category)} (Last 30 Days)</h6>
                                    <div class="row g-3 mt-2">${items.map(item =>
                                        `<div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 col-6">
                                            <div class="card h-100 text-center">
                                                <img src="${escapeHtml(item.photo)}" class="card-img-top" style="height: 120px; object-fit: cover;">
                                                <div class="card-body p-2">
                                                    <div class="fw-semibold small">${escapeHtml(item.name)}</div>
                                                    <span class="badge bg-primary rounded-pill mt-2">${item.qty} sold</span>
                                                </div>
                                            </div>
                                        </div>`
                                    ).join('')}</div>
                                </div>
                            </div>
                        </div>`;
                }
            } else {
                staffDashboardHtml = '<div class="col-12"><div class="card"><div class="card-body"><p class="text-muted mb-0">No sales data available to determine trending items in your branch.</p></div></div></div>';
            }

            container.innerHTML = staffDashboardHtml;
            return; // Stop further execution for staff
        }

        const salesToday = formatCurrency(res.sales_today);
        const salesYesterday = formatCurrency(res.sales_yesterday);
        const salesThisMonth = formatCurrency(res.sales_this_month);
        const salesLastMonth = formatCurrency(res.sales_last_month);
        const totalSales = formatCurrency(res.total_sales);

        const compareSales = (current, previous) => {
            const currentNum = Number(current);
            const previousNum = Number(previous);
            let percentageText = '';
            let arrow = '';
            let color = 'text-muted';
            
            if (previousNum === 0) {
                if (currentNum > 0) {
                    arrow = '<i class="fas fa-arrow-up"></i>';
                    color = 'text-success';
                }
            } else {
                const percentageChange = ((currentNum - previousNum) / previousNum) * 100;
                if (percentageChange > 0) {
                    arrow = '<i class="fas fa-arrow-up"></i>';
                    color = 'text-success';
                    percentageText = `${percentageChange.toFixed(1)}%`;
                } else if (percentageChange < 0) {
                    arrow = '<i class="fas fa-arrow-down"></i>';
                    color = 'text-danger';
                    percentageText = `${Math.abs(percentageChange).toFixed(1)}%`;
                }
            }
            return `<span class="${color}">${arrow} ${percentageText}</span>`;
        };

        const trendTodayYesterday = compareSales(salesToday, salesYesterday);
        const trendThisMonthLastMonth = compareSales(salesThisMonth, salesLastMonth);

        container.innerHTML = `
            ${res.low_stocks.length > 0 ? `
            <div class="col-12 mb-3">
                <div class="card border-warning">
                    <div class="card-body">
                        <h5 class="card-subtitle mb-2 text-danger">⚠️ Low Stocks (5 or less)</h5>
                        <ul class="list-group list-group-flush" style="max-height: 150px; overflow-y: auto;">${res.low_stocks.map(item => 
                                `<li class="list-group-item d-flex justify-content-between align-items-center p-1">
                                    ${escapeHtml(item.name)} (${item.size.toUpperCase()})
                                    <span class="badge bg-danger rounded-pill">${item.quantity}</span>
                                </li>`).join('')}</ul>
                    </div>
                </div>
            </div>` : ''}
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Sales Today</h6>
                        <h2 class="card-title">₱${salesToday}</h2>
                        <p class="card-text">
                            ${trendTodayYesterday} vs yesterday (₱${salesYesterday})
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Sales This Month</h6>
                        <h2 class="card-title">₱${salesThisMonth}</h2>
                        <p class="card-text">
                            ${trendThisMonthLastMonth} vs last month (₱${salesLastMonth})
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Total Sales</h6>
                        <h2 class="card-title">₱${totalSales}</h2>
                        <p class="card-text text-muted">All-time revenue</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Sales per Branch</h6>
                        ${res.sales_per_branch.length > 0 
                            ? `<ul class="list-group list-group-flush" style="max-height: 250px; overflow-y: auto;">${res.sales_per_branch.map(item => 
                                `<li class="list-group-item d-flex justify-content-between align-items-center p-1">
                                    ${escapeHtml(item.name)}
                                    <span class="fw-bold">₱${Number(item.total_sales).toFixed(2)}</span>
                                </li>`).join('')}</ul>`
                            : '<p class="text-muted mb-0">No sales data available per branch.</p>'
                        }
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Sales Trend per Branch (Last 30 Days)</h6>
                        <div style="position: relative; height: 250px;">
                            <canvas id="branch-trends-chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">📉 Least Sold Products (Last 30 Days)</h6>
                        ${res.least_sold_products && Object.keys(res.least_sold_products).length > 0 
                            ? `<ul class="list-group list-group-flush" style="max-height: 150px; overflow-y: auto;">${Object.values(res.least_sold_products).map(item => 
                                `<li class="list-group-item d-flex justify-content-between align-items-center p-1">
                                    ${escapeHtml(item.name)}
                                    <span class="badge bg-secondary rounded-pill">${item.qty_sold} sold</span>
                                </li>`).join('')}</ul>`
                            : '<p class="text-muted mb-0">No product data available.</p>'
                        }
                    </div>
                </div>
            </div>
            <div class="col-lg-12 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Sales Trend (Last 30 Days)</h6>
                        <div style="position: relative; height: 250px;">
                            <canvas id="trends-chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">🔥 Trending Products (Last 30 Days)</h6>
                        ${res.trending_products && res.trending_products.length > 0 ? `
                        <div class="row g-3 mt-2">${res.trending_products.map(item =>
                            `<div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 col-6">
                                <div class="card h-100 text-center">
                                    <img src="${escapeHtml(item.photo || 'uploads/no-image.png')}" class="card-img-top" style="height: 120px; object-fit: cover;">
                                    <div class="card-body p-2">
                                        <div class="fw-semibold small">${escapeHtml(item.name)}</div>
                                        <span class="badge bg-primary rounded-pill mt-2">${item.qty_sold} sold</span>
                                    </div>
                                </div>
                            </div>`
                        ).join('')}</div>
                        ` : '<p class="text-muted mb-0">No sales data available to determine trending products.</p>'}
                    </div>
                </div>
            </div>
        `;

        // Sales Trend Chart (Last 30 Days)
        const salesTimelineData = res.sales_timeline_data || [];
        if (salesTimelineData.length > 0) {
            new Chart(document.getElementById('trends-chart'), {
                type: 'line',
                data: {
                    labels: salesTimelineData.map(item => item.date),
                    datasets: [{
                        label: 'Daily Sales',
                        data: salesTimelineData.map(item => item.sales),
                        borderColor: '#6C5CE7',
                        backgroundColor: 'rgba(108, 92, 231, 0.2)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Sales (₱)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        title: {
                            display: false,
                        }
                    }
                }
            });
        } else {
            document.getElementById('trends-chart').parentElement.innerHTML = '<p class="text-muted mb-0">No sales trend data available for the last 30 days.</p>';
        }

        // New: Sales Trend per Branch Chart
        const salesPerBranchTimeline = res.sales_per_branch_timeline || [];
        const branchChartCanvas = document.getElementById('branch-trends-chart');
        if (salesPerBranchTimeline.length > 0 && branchChartCanvas) {
            const branchData = {};
            const dates = [...new Set(salesPerBranchTimeline.map(item => item.date))].sort();

            salesPerBranchTimeline.forEach(item => {
                if (!branchData[item.branch_name]) {
                    branchData[item.branch_name] = {};
                }
                branchData[item.branch_name][item.date] = parseFloat(item.sales);
            });

            const branchColors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];
            let colorIndex = 0;

            const datasets = Object.keys(branchData).map(branchName => {
                const color = branchColors[colorIndex % branchColors.length];
                colorIndex++;
                return {
                    label: branchName,
                    data: dates.map(date => branchData[branchName][date] || 0),
                    borderColor: color,
                    backgroundColor: color.replace(')', ', 0.2)').replace('rgb', 'rgba'),
                    tension: 0.4,
                    fill: false
                };
            });

            new Chart(branchChartCanvas, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Sales (₱)' }
                        },
                        x: {
                            title: { display: true, text: 'Date' }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        title: { display: false }
                    }
                }
            });
        } else if (branchChartCanvas) {
            branchChartCanvas.parentElement.innerHTML = '<p class="text-muted mb-0">No sales trend data available for branches.</p>';
        }
    });
  }

  // Set up auto-refresh for the dashboard every 15 seconds
  // We only set the interval after the first load is complete to avoid race conditions
  function setupDashboardAutoRefresh() {
    setInterval(loadDashboard, 15000); // Refresh every 15 seconds
  }

  // Forgot Password Form Handler
  const forgotPasswordForm = document.getElementById('forgotPasswordForm');
  if (forgotPasswordForm) {
    forgotPasswordForm.addEventListener('submit', e => {
      e.preventDefault();
      const email = forgotPasswordForm.querySelector('#email').value;
      const messageEl = document.getElementById('responseMessage');
      messageEl.innerHTML = '<div class="alert alert-info">Sending request...</div>';

      const fd = new FormData();
      fd.append('email', email);
      api('forgot_password', { method: 'POST', body: fd }).then(res => {
        if (res.ok) {
          messageEl.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message)}</div>`;
          forgotPasswordForm.reset();
        } else {
          messageEl.innerHTML = `<div class="alert alert-danger">${escapeHtml(res.error || 'An unknown error occurred.')}</div>`;
        }
      });
    });
  }

  // Reset Password Form Handler
  const resetPasswordForm = document.getElementById('resetPasswordForm');
  if (resetPasswordForm) {
    resetPasswordForm.addEventListener('submit', e => {
      e.preventDefault();
      const password = resetPasswordForm.querySelector('#password').value;
      const passwordConfirm = resetPasswordForm.querySelector('#password_confirm').value;
      const messageEl = document.getElementById('responseMessage');

      if (password !== passwordConfirm) {
        messageEl.innerHTML = '<div class="alert alert-danger">Passwords do not match.</div>';
        return;
      }

      messageEl.innerHTML = '<div class="alert alert-info">Resetting password...</div>';
      const fd = new FormData(resetPasswordForm);
      api('reset_password', { method: 'POST', body: fd }).then(res => {
        if (res.ok) {
          messageEl.innerHTML = `<div class="alert alert-success">${escapeHtml(res.message)} You will be redirected to the login page shortly.</div>`;
          setTimeout(() => window.location.href = 'login.php', 3000);
        } else {
          messageEl.innerHTML = `<div class="alert alert-danger">${escapeHtml(res.error || 'An unknown error occurred.')}</div>`;
        }
      });
    });
  }

  populateBranchSelects().then(()=>{
    loadBranches();
    loadInventory();
    loadPOSProducts();
    loadAccounts();
    populateSupplierSelects();
    loadSuppliers();
    loadDashboard();
    setupDashboardAutoRefresh(); // Start auto-refreshing
    updateCartUI();

    // Correctly attach event listeners for inventory category filters
    document.querySelectorAll('#inventoryCategoryFilter .btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('#inventoryCategoryFilter .btn').forEach(b => {
          b.classList.remove('btn-secondary', 'active');
          b.classList.add('btn-outline-secondary');
        });
        btn.classList.add('btn-secondary', 'active');
        btn.classList.remove('btn-outline-secondary');
        loadInventory();
      });
    });
  });

});