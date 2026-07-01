
        // Global variables for active table tracking
        let activeDineInOrder = null;
        let selectedTableQR = null;

        // Dark/Light Theme Switching System
        function updateThemeUI() {
            const isLight = document.documentElement.classList.contains('light-mode');
            const icon = document.getElementById('themeIcon');
            const btn = document.getElementById('themeToggleBtn');
            
            if (isLight) {
                if (icon) {
                    icon.className = 'fas fa-sun';
                    icon.style.color = 'var(--gold)';
                }
                if (btn) {
                    btn.style.background = 'var(--bg-secondary)';
                    btn.style.borderColor = 'rgba(0, 0, 0, 0.08)';
                    btn.style.boxShadow = '0 4px 15px rgba(0,0,0,0.06)';
                }
            } else {
                if (icon) {
                    icon.className = 'fas fa-moon';
                    icon.style.color = 'var(--gold)';
                }
                if (btn) {
                    btn.style.background = 'var(--bg-secondary)';
                    btn.style.borderColor = 'rgba(255, 255, 255, 0.08)';
                    btn.style.boxShadow = '0 4px 15px rgba(0,0,0,0.3)';
                }
            }
            updateChartTheme();
        }

        function toggleTheme() {
            if (document.documentElement.classList.contains('light-mode')) {
                document.documentElement.classList.remove('light-mode');
                localStorage.setItem('medusa_admin_theme', 'dark');
            } else {
                document.documentElement.classList.add('light-mode');
                localStorage.setItem('medusa_admin_theme', 'light');
            }
            updateThemeUI();
        }

        function updateChartTheme() {
            if (!window.salesChartInstance) return;
            const isLight = document.documentElement.classList.contains('light-mode');
            const gridColor = isLight ? 'rgba(0, 0, 0, 0.05)' : 'rgba(255, 255, 255, 0.05)';
            const tickColor = isLight ? '#64748b' : '#a09f9f';
            
            window.salesChartInstance.options.scales.x.grid.color = gridColor;
            window.salesChartInstance.options.scales.x.ticks.color = tickColor;
            window.salesChartInstance.options.scales.y.grid.color = gridColor;
            window.salesChartInstance.options.scales.y.ticks.color = tickColor;
            window.salesChartInstance.update();
        }

        // Switch Sidebar Tabs
        function switchTab(tabId, el) {
            // Remove active classes
            document.querySelectorAll('.sidebar-link').forEach(link => link.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
            
            // Add active class
            if (el) el.classList.add('active');
            const panel = document.getElementById(tabId);
            if (panel) panel.classList.add('active');
            
            // Save active tab to localStorage
            localStorage.setItem('medusa_active_admin_tab', tabId);
            
            // If kitchen panel is active, start live polling
            if (tabId === 'kitchen-tab') {
                startKitchenPolling();
            } else {
                stopKitchenPolling();
            }

            // If liquor quota panel is active, reload active quotas list
            if (tabId === 'liquor-tab') {
                loadActiveQuotas();
            }
        }

        // 1. Chart.js Sales Graph
        const ctx = document.getElementById('salesChart');
        if (ctx) {
            window.salesChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'Sales Revenue (₹)',
                        data: <?php echo json_encode($chart_data); ?>,
                        borderColor: '#dfba86',
                        backgroundColor: 'rgba(223, 186, 134, 0.1)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            grid: { color: 'rgba(255, 255, 255, 0.05)' },
                            ticks: { color: '#a09f9f' }
                        },
                        x: {
                            grid: { color: 'rgba(255, 255, 255, 0.05)' },
                            ticks: { color: '#a09f9f' }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
            updateChartTheme(); // apply theme colors to chart immediately
        }

        // Initialize theme UI state and restore active tab on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            updateThemeUI();
            
            // Move all modals to the root body element to fix Bootstrap z-index backdrop bugs
            document.querySelectorAll('.modal').forEach(m => document.body.appendChild(m));

            // Restore active tab
            const activeTab = localStorage.getItem('medusa_active_admin_tab');
            if (activeTab) {
                const sidebarLink = document.querySelector(`.sidebar-link[onclick*="${activeTab}"]`);
                if (sidebarLink) {
                    switchTab(activeTab, sidebarLink);
                }
            }
            // Remove temporary style tag to allow normal stylesheet rules to take over
            document.getElementById('temp-tab-css')?.remove();
        });

        // 2. Order Status Controls (Online list)
        function updateOrderStatus(id, newStatus) {
            if (!newStatus) return;
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'update_order_status',
                    order_id: id,
                    status: newStatus
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating order status');
                }
            });
        }

        // 3. Dine-In Table Selection Loader
        function loadTableOrderDetails(order) {
            activeDineInOrder = order;
            document.getElementById('table-detail-card').style.display = 'block';
            
            // extract table number
            let tbl = 'Unknown';
            if (preg_match = order.delivery_address.match(/Table\s+([A-Za-z0-9]+)/i)) {
                tbl = preg_match[1];
            }
            
            document.getElementById('detail-table-title').textContent = 'Table ' + tbl + ' Order Details';
            
            const badge = document.getElementById('detail-table-status');
            badge.className = 'status-badge status-' + order.order_status.toLowerCase();
            badge.textContent = order.order_status;
            
            // Fetch items
            fetch('dashboardtest.php?action=get_kitchen_orders')
            .then(res => res.json())
            .then(data => {
                const updatedOrder = data.orders.find(o => o.id == order.id);
                if (updatedOrder) {
                    activeDineInOrder = updatedOrder;
                    renderTableItems(updatedOrder.items, updatedOrder.total_amount);
                } else {
                    // Fallback to active order items
                    renderTableItems([], order.total_amount);
                }
            });
        }

        function renderTableItems(items, total) {
            const tbody = document.getElementById('detail-table-items');
            tbody.innerHTML = '';
            
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No items in this order yet.</td></tr>';
            } else {
                items.forEach(it => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><strong>${it.item_name}</strong></td>
                        <td>${it.quantity}</td>
                        <td>₹${parseFloat(it.price).toFixed(2)}</td>
                        <td>₹${(it.price * it.quantity).toFixed(2)}</td>
                    `;
                    tbody.appendChild(row);
                });
            }
            document.getElementById('detail-table-total').textContent = '₹' + parseFloat(total).toFixed(2);
        }

        // 4. Dine-in Order Modification
        function openAddTableItemModal() {
            if (!activeDineInOrder) return;
            document.getElementById('add_table_order_id').value = activeDineInOrder.id;
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('addTableItemModal'));
            modal.show();
        }

        function submitAddTableItem(e) {
            e.preventDefault();
            const order_id = document.getElementById('add_table_order_id').value;
            const food_item_id = document.getElementById('add_table_food_id').value;
            const quantity = document.getElementById('add_table_qty').value;
            
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'add_table_item',
                    order_id: order_id,
                    food_item_id: food_item_id,
                    quantity: quantity
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('addTableItemModal')).hide();
                    alert('Item added successfully!');
                    // Reload table details
                    loadTableOrderDetails(activeDineInOrder);
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        // 5. Dine-in Settle Invoice
        function openBillSettleModal() {
            if (!activeDineInOrder) return;
            document.getElementById('settle_order_id').value = activeDineInOrder.id;
            document.getElementById('settle_bill_total').textContent = '₹' + parseFloat(activeDineInOrder.total_amount).toFixed(2);
            
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('settleBillModal'));
            modal.show();
        }

        function submitSettleBill(e) {
            e.preventDefault();
            const order_id = document.getElementById('settle_order_id').value;
            const method = document.getElementById('settle_payment_method').value;
            
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'settle_bill',
                    order_id: order_id,
                    payment_method: method
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('settleBillModal')).hide();
                    alert('Invoice successfully settled & table released!', () => {
                        location.reload();
                    });
                } else {
                    alert('Error settling bill');
                }
            });
        }

        // 6. QR Code Configuration Dialog
        function openTableQRModal(tableCode, isOccupied) {
            selectedTableQR = tableCode;
            document.getElementById('qrModalTitle').textContent = 'Table ' + tableCode + ' Configuration';
            document.getElementById('qrTableLabel').textContent = 'Table ' + tableCode;
            
            // Generate QR Code targeting the menu page with this table number prefilled
            // Use PHP to inject the real local Wi-Fi IP address so smartphones can reach it instead of looking for 'localhost'
            const networkIp = '<?php echo gethostbyname(gethostname()); ?>';
            const serverPort = window.location.port ? ':' + window.location.port : '';
            
            // Extract the path by removing /admintest/dashboardtest.php from current pathname
            const basePath = window.location.pathname.replace('/admintest/dashboardtest.php', '');
            const menuUrl = `http://${networkIp}${serverPort}${basePath}/menutest.html?table=${tableCode}`;
            
            const qrContainer = document.getElementById('qrCodeContainer');
            qrContainer.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(menuUrl)}" alt="QR Code" style="width: 150px; height: 150px;">`;
            
            document.getElementById('qrOpenLink').href = menuUrl;
            
            const btnAct = document.getElementById('btnDineInAct');
            if (isOccupied) {
                btnAct.textContent = 'View Active Order';
                btnAct.className = 'btn btn-sm btn-gold-action';
                btnAct.onclick = () => {
                    bootstrap.Modal.getInstance(document.getElementById('tableQRModal')).hide();
                    switchTab('orders-tab', document.querySelector('.sidebar-link[onclick*="orders-tab"]'));
                    // Load table active order
                    // Look up order matching tableCode
                    fetch('dashboardtest.php?action=get_kitchen_orders')
                    .then(res => res.json())
                    .then(data => {
                        const ord = data.orders.find(o => o.delivery_address.includes('Table ' + tableCode));
                        if (ord) {
                            loadTableOrderDetails(ord);
                        } else {
                            alert('No active order data found');
                        }
                    });
                };
            } else {
                btnAct.textContent = 'Open New Dine-In Order';
                btnAct.className = 'btn btn-sm btn-outline-light';
                btnAct.onclick = () => {
                    const custName = prompt('Enter Guest Name (Optional):', 'Guest');
                    if (custName === null) return;
                    
                    fetch('dashboardtest.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'create_dinein_order',
                            table_code: tableCode,
                            customer_name: custName
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            bootstrap.Modal.getInstance(document.getElementById('tableQRModal')).hide();
                            alert('New table order successfully opened!', () => {
                                location.reload();
                            });
                        } else {
                            alert('Error opening table order');
                        }
                    });
                };
            }

            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('tableQRModal'));
            modal.show();
        }

        // 7. Kitchen Panel Live Polling Logic
        let kitchenInterval = null;
        
        function startKitchenPolling() {
            loadKitchenOrders();
            kitchenInterval = setInterval(loadKitchenOrders, 5000); // Poll every 5 seconds
        }
        
        function stopKitchenPolling() {
            if (kitchenInterval) {
                clearInterval(kitchenInterval);
                kitchenInterval = null;
            }
        }
        
        function loadKitchenOrders() {
            fetch('dashboardtest.php?action=get_kitchen_orders')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderKitchenColumn(data.orders.filter(o => o.order_status.toLowerCase() === 'pending'), 'kitchen-pending-list', 'pending');
                    renderKitchenColumn(data.orders.filter(o => o.order_status.toLowerCase() === 'preparing'), 'kitchen-preparing-list', 'preparing');
                    renderKitchenColumn(data.orders.filter(o => o.order_status.toLowerCase() === 'ready'), 'kitchen-ready-list', 'ready');
                    // Re-apply search filter after every refresh so results don't vanish
                    filterKitchenOrders();
                }
            });
        }
        
        function renderKitchenColumn(orders, containerId, columnType) {
            const container = document.getElementById(containerId);
            container.innerHTML = '';
            
            // Update counter badge
            document.getElementById('count-kitchen-' + columnType).textContent = orders.length;
            
            if (orders.length === 0) {
                container.innerHTML = `<div class="text-center text-muted py-5">No orders in this state.</div>`;
                return;
            }
            
            orders.forEach(order => {
                const card = document.createElement('div');
                card.className = 'kitchen-card';
                
                const itemsList = order.items.map(it => `<li>${it.item_name} <strong>x${it.quantity}</strong></li>`).join('');
                
                let btn = '';
                if (columnType === 'pending') {
                    btn = `<button class="btn btn-sm btn-gold-action btn-action-full" onclick="updateOrderStatus(${order.id}, 'preparing')">Start Cooking</button>`;
                } else if (columnType === 'preparing') {
                    btn = `<button class="btn btn-sm btn-success w-100 text-dark" onclick="updateOrderStatus(${order.id}, 'ready')">Mark Ready</button>`;
                } else if (columnType === 'ready') {
                    btn = `<button class="btn btn-sm btn-primary w-100 text-white" onclick="updateOrderStatus(${order.id}, 'completed')">Complete / Serve</button>`;
                }
                
                card.innerHTML = `
                    <div class="kitchen-card-header">
                        <span>#${order.order_number}</span>
                        <span class="text-gold">${order.delivery_address}</span>
                    </div>
                    <ul class="kitchen-card-items">
                        ${itemsList}
                    </ul>
                    ${btn}
                `;
                container.appendChild(card);
            });
        }

        // ======= CUSTOMIZATION MANAGER =======
        let custFoodItemId = null;

        function openCustomizationManager(foodItemId, dishName) {
            custFoodItemId = foodItemId;
            document.getElementById('custManagerTitle').textContent = 'Customizations: ' + dishName;
            document.getElementById('custManagerSubtitle').textContent = 'Add/remove selection groups (size, crust, toppings, sauce, etc.)';
            document.getElementById('cust_food_item_id').value = foodItemId;
            resetCustomGroupForm();
            loadExistingCustomizations(foodItemId);
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('customizationManagerModal'));
            modal.show();
        }

        function loadExistingCustomizations(foodItemId) {
            const container = document.getElementById('existingCustomizationsContainer');
            container.innerHTML = '<div class="text-muted text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</div>';

            fetch('../api/save-customization.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_customizations', food_item_id: foodItemId })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    container.innerHTML = '<div class="alert alert-warning">Could not load customizations. Please import the updated restaurant_db.sql first.</div>';
                    return;
                }
                if (data.customizations.length === 0) {
                    container.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-info-circle me-2"></i>No customizations set up for this dish yet.</div>';
                    return;
                }
                container.innerHTML = '';
                data.customizations.forEach(group => {
                    const card = document.createElement('div');
                    card.className = 'mb-3';
                    card.style.cssText = 'background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:10px; padding:1rem;';

                    const optionTags = group.options.map(o => {
                        const priceLabel = o.price_add > 0 ? ` <span style="color:#2ec4b6">+₹${o.price_add}</span>` : (o.price_add < 0 ? ` <span style="color:#ff6b6b">-₹${Math.abs(o.price_add)}</span>` : '');
                        return `<span style="background:rgba(223,186,134,0.1); color:#dfba86; border:1px solid rgba(223,186,134,0.2); border-radius:20px; padding:2px 10px; font-size:0.8rem; display:inline-block; margin:2px;">${o.label}${priceLabel}</span>`;
                    }).join('');

                    const typeBadge = group.group_type === 'multiple' ? '<span class="badge bg-primary ms-2">Multi-Select</span>' : '<span class="badge bg-secondary ms-2">Single Choice</span>';
                    const reqBadge = group.is_required == 1 ? '<span class="badge bg-danger ms-1">Required</span>' : '<span class="badge bg-dark ms-1">Optional</span>';

                    card.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong style="color:#fff;">${group.group_name}</strong>
                                ${typeBadge}${reqBadge}
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-light" onclick="editCustomGroup(${JSON.stringify(group).replace(/"/g,'&quot;')})" title="Edit Group"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteCustomGroup(${group.id})" title="Delete Group"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <div>${optionTags}</div>
                    `;
                    container.appendChild(card);
                });
            })
            .catch(() => {
                container.innerHTML = '<div class="alert alert-danger">Failed to load. Check that dish_customizations table exists in your database.</div>';
            });
        }

        function addOptionRow(label = '', priceAdd = 0) {
            const container = document.getElementById('optionsBuilderContainer');
            const idx = container.children.length;
            const row = document.createElement('div');
            row.className = 'd-flex gap-2 mb-2 option-row align-items-center';
            row.innerHTML = `
                <input type="text" class="form-control bg-dark text-white border-secondary option-label" placeholder="Option label (e.g. Thin Crust)" value="${label}" required>
                <input type="number" step="1" class="form-control bg-dark text-white border-secondary option-price" style="max-width:130px;" placeholder="Price (+/-)" value="${priceAdd}" title="Price added to base (0 = free, negative = discount)">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.option-row').remove()" title="Remove"><i class="fas fa-times"></i></button>
            `;
            container.appendChild(row);
        }

        function collectOptionsFromForm() {
            const rows = document.querySelectorAll('#optionsBuilderContainer .option-row');
            const options = [];
            rows.forEach(row => {
                const lbl = row.querySelector('.option-label').value.trim();
                const price = parseFloat(row.querySelector('.option-price').value) || 0;
                if (lbl) options.push({ label: lbl, price_add: price });
            });
            return options;
        }

        function submitCustomGroup(e) {
            e.preventDefault();
            const options = collectOptionsFromForm();
            if (options.length === 0) {
                alert('Please add at least one option to this group.');
                return;
            }

            const editId = document.getElementById('cust_group_edit_id').value;
            const bodyData = {
                action: 'save_customization_group',
                food_item_id: custFoodItemId,
                group_name: document.getElementById('cust_group_name').value,
                group_type: document.getElementById('cust_group_type').value,
                is_required: document.getElementById('cust_group_required').value,
                options_json: JSON.stringify(options),
                sort_order: 0,
                id: editId
            };

            fetch('../api/save-customization.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(bodyData)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    resetCustomGroupForm();
                    loadExistingCustomizations(custFoodItemId);
                } else {
                    alert('Error: ' + (data.message || 'Save failed'));
                }
            });
        }

        function editCustomGroup(group) {
            document.getElementById('cust_group_edit_id').value = group.id;
            document.getElementById('cust_group_name').value = group.group_name;
            document.getElementById('cust_group_type').value = group.group_type;
            document.getElementById('cust_group_required').value = group.is_required;
            document.getElementById('custGroupSubmitBtn').textContent = 'Update Group';

            // Clear and repopulate options
            const container = document.getElementById('optionsBuilderContainer');
            container.innerHTML = '';
            (group.options || []).forEach(o => addOptionRow(o.label, o.price_add));

            // Scroll to form
            document.getElementById('addCustomGroupForm').scrollIntoView({ behavior: 'smooth' });
        }

        function deleteCustomGroup(id) {
            if (!confirm('Delete this customization group? This cannot be undone.')) return;
            fetch('../api/save-customization.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'delete_customization_group', id: id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadExistingCustomizations(custFoodItemId);
                } else {
                    alert('Delete failed');
                }
            });
        }

        function resetCustomGroupForm() {
            document.getElementById('addCustomGroupForm').reset();
            document.getElementById('cust_group_edit_id').value = '';
            document.getElementById('cust_food_item_id').value = custFoodItemId || '';
            document.getElementById('optionsBuilderContainer').innerHTML = '';
            document.getElementById('custGroupSubmitBtn').textContent = 'Save Group';
            // Add one blank option row to start
            addOptionRow();
        }

        // 8. Menu Management CRUD
        function toggleMenuAvailability(id, isChecked) {
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'toggle_menu_item',
                    id: id,
                    val: isChecked ? 1 : 0
                })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert('Failed to update availability status.');
                }
            });
        }

        function switchImageSource(source) {
            const uploadBtn = document.getElementById('btn_notif_img_upload');
            const urlBtn = document.getElementById('btn_notif_img_url');
            const uploadContainer = document.getElementById('image_upload_container');
            const urlContainer = document.getElementById('image_url_container');
            
            if (source === 'upload') {
                if (uploadBtn) uploadBtn.classList.add('active');
                if (urlBtn) urlBtn.classList.remove('active');
                if (uploadContainer) uploadContainer.style.display = 'block';
                if (urlContainer) urlContainer.style.display = 'none';
            } else {
                if (uploadBtn) uploadBtn.classList.remove('active');
                if (urlBtn) urlBtn.classList.add('active');
                if (uploadContainer) uploadContainer.style.display = 'none';
                if (urlContainer) urlContainer.style.display = 'block';
            }
        }

        function handleImageFileSelect(input) {
            const file = input.files[0];
            if (!file) return;

            const dropzone = document.getElementById('image_upload_container');
            const originalHTML = dropzone.innerHTML;
            dropzone.innerHTML = `
                <div class="spinner-border text-gold my-2" role="status" style="width: 1.5rem; height: 1.5rem;">
                    <span class="visually-hidden">Uploading...</span>
                </div>
                <div class="dropzone-text text-gold">Uploading image...</div>
            `;

            const formData = new FormData();
            formData.append('action', 'upload_dish_image');
            formData.append('dish_image', file);

            fetch('dashboardtest.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('menu_image_url').value = data.image_url;
                    updateImagePreview(data.image_url);
                } else {
                    alert(data.message || 'Upload failed');
                    dropzone.innerHTML = originalHTML;
                }
            })
            .catch(err => {
                console.error("Error uploading image:", err);
                alert("An error occurred during file upload.");
                dropzone.innerHTML = originalHTML;
            });
        }

        function updateImagePreview(url) {
            const previewWrapper = document.getElementById('image_preview_wrapper');
            const previewImg = document.getElementById('dish_image_preview');
            const dropzone = document.getElementById('image_upload_container');
            const warningEl = document.getElementById('image_url_warning');
            
            if (warningEl) warningEl.style.display = 'none';

            if (!url) {
                if (previewWrapper) previewWrapper.style.display = 'none';
                return;
            }
            
            let displayUrl = url;
            if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
                // Pipe external links through local proxy to bypass browser-side CORS and referrer policies
                displayUrl = 'dashboardtest.php?action=proxy_image&url=' + encodeURIComponent(url);
            } else if (url && !url.startsWith('//')) {
                if (url.startsWith('../')) {
                    displayUrl = url;
                } else if (url.startsWith('uploads/')) {
                    displayUrl = '../' + url;
                } else {
                    displayUrl = '../uploads/' + url;
                }
            }

            if (previewImg) {
                previewImg.onload = function() {
                    if (warningEl) warningEl.style.display = 'none';
                };
                previewImg.onerror = function() {
                    if (warningEl) warningEl.style.display = 'block';
                };
                previewImg.src = displayUrl;
            }
            if (previewWrapper) previewWrapper.style.display = 'block';
            
            if (dropzone) {
                dropzone.innerHTML = `
                    <i class="fas fa-cloud-upload-alt dropzone-icon"></i>
                    <div class="dropzone-text">Drag & drop image here, or <span>browse</span></div>
                    <div class="dropzone-subtext">Supports all image formats (Max 20MB)</div>
                `;
            }
        }

        function removeDishImage(event) {
            if (event) event.stopPropagation();
            const urlInput = document.getElementById('menu_image_url');
            if (urlInput) urlInput.value = '';
            const fileInput = document.getElementById('dish_image_file');
            if (fileInput) fileInput.value = '';
            const previewWrapper = document.getElementById('image_preview_wrapper');
            if (previewWrapper) previewWrapper.style.display = 'none';
        }

        function setupImageDragAndDrop() {
            const dropzone = document.getElementById('image_upload_container');
            if (!dropzone) return;

            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('dragover');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('dragover');
                }, false);
            });

            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                
                // 1. Handle dragged files
                if (dt.files && dt.files.length > 0) {
                    const fileInput = document.getElementById('dish_image_file');
                    if (fileInput) {
                        fileInput.files = dt.files;
                        handleImageFileSelect(fileInput);
                    }
                } 
                // 2. Handle dragged URLs from other tabs
                else {
                    const url = dt.getData('text/uri-list') || dt.getData('text/plain');
                    if (url && (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('data:image/'))) {
                        const urlInput = document.getElementById('menu_image_url');
                        if (urlInput) {
                            urlInput.value = url;
                            switchImageSource('url');
                            updateImagePreview(url);
                        }
                    }
                }
            }, false);

            // 3. Handle Clipboard Paste (Ctrl+V) anywhere inside the CRUD Modal
            const modalEl = document.getElementById('menuCrudModal');
            if (modalEl) {
                modalEl.addEventListener('paste', (e) => {
                    // Check if current focused element is a text input/textarea (like Name or Description)
                    // and allow default paste behavior for those fields
                    const activeEl = document.activeElement;
                    if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA')) {
                        if (activeEl.id !== 'menu_image_url') {
                            return; // Let standard text inputs handle text paste normally
                        }
                    }

                    const clipboardData = e.clipboardData || window.clipboardData;
                    if (!clipboardData) return;

                    // A. Check for pasted files (e.g. screenshots, copied local image file)
                    if (clipboardData.files && clipboardData.files.length > 0) {
                        e.preventDefault();
                        const fileInput = document.getElementById('dish_image_file');
                        if (fileInput) {
                            fileInput.files = clipboardData.files;
                            switchImageSource('upload');
                            handleImageFileSelect(fileInput);
                        }
                    } 
                    // B. Check for pasted URLs/links
                    else {
                        const pastedText = clipboardData.getData('text').trim();
                        if (pastedText && (pastedText.startsWith('http://') || pastedText.startsWith('https://') || pastedText.startsWith('data:image/'))) {
                            e.preventDefault();
                            const urlInput = document.getElementById('menu_image_url');
                            if (urlInput) {
                                urlInput.value = pastedText;
                                switchImageSource('url');
                                updateImagePreview(pastedText);
                            }
                        }
                    }
                });
            }
        }

        function openAddMenuModal() {
            document.getElementById('menuCrudForm').reset();
            document.getElementById('menu_item_id').value = '';
            document.getElementById('menuModalTitle').textContent = 'Add New Dish';
            document.getElementById('btnMenuSubmit').textContent = 'Save Dish';
            
            removeDishImage();
            switchImageSource('upload');
            
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('menuCrudModal'));
            modal.show();
        }

        function openEditMenuModal(dish) {
            document.getElementById('menu_item_id').value = dish.id;
            document.getElementById('menu_name').value = dish.name;
            document.getElementById('menu_category').value = dish.category;
            document.getElementById('menu_price').value = dish.price;
            document.getElementById('menu_description').value = dish.description;
            document.getElementById('menu_image_url').value = dish.image_url;
            
            const fileInput = document.getElementById('dish_image_file');
            if (fileInput) fileInput.value = '';
            
            if (dish.image_url) {
                updateImagePreview(dish.display_image_url || dish.image_url);
                if (dish.image_url.startsWith('uploads/')) {
                    switchImageSource('upload');
                } else {
                    switchImageSource('url');
                }
            } else {
                removeDishImage();
                switchImageSource('upload');
            }
            
            document.getElementById('menuModalTitle').textContent = 'Edit Dish Details';
            document.getElementById('btnMenuSubmit').textContent = 'Update Dish';
            
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('menuCrudModal'));
            modal.show();
        }

        function submitMenuCrud(e) {
            e.preventDefault();
            const id = document.getElementById('menu_item_id').value;
            const action = id ? 'edit_menu_item' : 'add_menu_item';

            const submitBtn = document.getElementById('btnMenuSubmit');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
            
            const bodyData = {
                action: action,
                name: document.getElementById('menu_name').value,
                category: document.getElementById('menu_category').value,
                price: document.getElementById('menu_price').value,
                description: document.getElementById('menu_description').value,
                image_url: document.getElementById('menu_image_url').value || ''
            };
            if (id) bodyData.id = id;
            
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(bodyData)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('menuCrudModal')).hide();
                    showToast(id ? 'Dish updated successfully!' : 'New dish added successfully!', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showToast('Error saving dish: ' + (data.message || 'Unknown error'), 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(err => {
                showToast('Network error. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        }

        function deleteMenuItem(id) {
            if (!confirm('Are you sure you want to delete this menu dish permanently?')) return;
            
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'delete_menu_item',
                    id: id
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error deleting menu item');
                }
            });
        }

        // 9. Save settings
        function saveSettings(e) {
            e.preventDefault();
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'save_settings',
                    restaurant_name: document.getElementById('set_restaurant_name').value,
                    gst_rate: document.getElementById('set_gst_rate').value,
                    packing_charge: document.getElementById('set_packing_charge').value,
                    opening_hours: document.getElementById('set_opening_hours').value,
                    silver_discount: document.getElementById('set_silver_discount').value,
                    gold_discount: document.getElementById('set_gold_discount').value,
                    platinum_discount: document.getElementById('set_platinum_discount').value,
                    gold_threshold: document.getElementById('set_gold_threshold').value,
                    platinum_threshold: document.getElementById('set_platinum_threshold').value,
                    points_earning_percent: document.getElementById('set_points_earning_percent').value,
                    inactivity_months: document.getElementById('set_inactivity_months').value,
                    inactivity_deduction_percent: document.getElementById('set_inactivity_deduction_percent').value
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Settings updated successfully!', () => {
                        location.reload();
                    });
                } else {
                    alert('Error saving configs');
                }
            });
        }

        // =====================================================================
        // ADVANCED SEARCH & REPORTING FRONTEND CONTROLLER
        // =====================================================================

        // ---- Utility: debounce helper ----
        function debounce(fn, delay) {
            let timer;
            return function(...args) {
                clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        // ---- Utility: show loading spinner in a tbody ----
        function setTableLoading(tbodyId, colSpan) {
            const tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Searching...</td></tr>`;
        }

        // ---- Utility: format currency ----
        function fmtINR(val) {
            return '₹' + parseFloat(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // ---- Utility: growth badge HTML ----
        function growthBadge(val) {
            const num = parseFloat(val || 0);
            const cls = num >= 0 ? 'text-success' : 'text-danger';
            const icon = num >= 0 ? 'fa-caret-up' : 'fa-caret-down';
            return `<i class="fas ${icon}"></i> ${Math.abs(num)}% vs last period`;
        }

        // ---- Toggle Custom Date Fields ----
        function toggleCustomDateFields(context, value) {
            if (context === 'orders') {
                const row = document.getElementById('orders_custom_date_row');
                if (row) row.style.display = (value === 'custom') ? 'flex' : 'none';
            } else if (context === 'reports') {
                document.querySelectorAll('.reports_custom_date').forEach(el => {
                    el.style.display = (value === 'custom') ? 'block' : 'none';
                });
            }
        }

        // =====================================================================
        // 1. ORDERS SEARCH
        // =====================================================================
        function performOrdersSearch(event) {
            if (event) event.preventDefault();
            setTableLoading('orders-search-results-body', 7);

            const params = new URLSearchParams({
                action: 'search_orders',
                search: document.getElementById('order_search_input')?.value || '',
                status: document.getElementById('order_status_select')?.value || 'all',
                payment_status: document.getElementById('order_payment_status_select')?.value || 'all',
                type: document.getElementById('order_type_select')?.value || 'all',
                date: document.getElementById('order_date_select')?.value || 'all',
                start_date: document.getElementById('order_start_date')?.value || '',
                end_date: document.getElementById('order_end_date')?.value || '',
                min_amount: document.getElementById('order_min_amount')?.value || '',
                max_amount: document.getElementById('order_max_amount')?.value || ''
            });

            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) { showSearchError('orders-search-results-body', 7, 'Search failed.'); return; }
                renderOrdersSearchResults(data.orders);
            })
            .catch(() => showSearchError('orders-search-results-body', 7, 'Network error.'));
        }

        function getStarRatingHtml(rating, review) {
            rating = parseInt(rating);
            if (isNaN(rating) || rating < 1 || rating > 5) return '';
            let title = rating + '/5 Stars' + (review ? ': ' + review.replace(/"/g, '&quot;') : '');
            let html = `<div class="feedback-stars mt-1" style="color: #dfba86; font-size: 0.85rem;" title="${title}">`;
            for (let i = 1; i <= 5; i++) {
                html += (i <= rating) ? '★' : '☆';
            }
            html += '</div>';
            return html;
        }

        function renderOrdersSearchResults(orders) {
            // Show/hide the results card
            const card = document.getElementById('orders-search-results-card');
            if (card) card.style.display = 'block';

            const tbody = document.getElementById('orders-search-results-body');
            if (!tbody) return;

            if (!orders || orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No orders found matching your criteria.</td></tr>';
                return;
            }

            tbody.innerHTML = orders.map(ord => {
                const items = (ord.items || []).map(i => `${i.item_name} ×${i.quantity}`).join(', ') || '—';
                const statusMap = {
                    pending: 'bg-warning text-dark',
                    preparing: 'bg-primary text-white',
                    ready: 'bg-info text-dark',
                    completed: 'bg-success text-dark',
                    cancelled: 'bg-danger text-white'
                };
                const badgeCls = statusMap[ord.order_status?.toLowerCase()] || 'bg-secondary text-white';
                const isOnline = !ord.delivery_address?.toLowerCase().startsWith('table ');
                const typeBadge = isOnline
                    ? '<span class="badge bg-dark border border-secondary text-white">Online</span>'
                    : '<span class="badge bg-dark border border-secondary text-white">Dine-In</span>';

                return `<tr>
                    <td>
                        <strong class="text-gold">#${ord.order_number || ord.id}</strong>
                        ${getStarRatingHtml(ord.rating, ord.review)}
                    </td>
                    <td><strong>${ord.customer_name || '—'}</strong><br><small class="text-muted">${ord.customer_phone || ''}</small></td>
                    <td><small class="text-muted">${items}</small></td>
                    <td class="text-gold">${fmtINR(ord.total_amount)}</td>
                    <td>${typeBadge}</td>
                    <td><span class="status-badge ${badgeCls}">${(ord.order_status || '').toUpperCase()}</span></td>
                    <td><small class="text-muted">${ord.order_date ? new Date(ord.order_date).toLocaleString('en-IN') : '—'}</small></td>
                </tr>`;
            }).join('');
        }

        function showSearchError(tbodyId, colSpan, msg) {
            const tbody = document.getElementById(tbodyId);
            if (tbody) tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center text-danger py-3"><i class="fas fa-exclamation-triangle me-2"></i>${msg}</td></tr>`;
        }

        // =====================================================================
        // 2. KITCHEN SEARCH (Client-Side)
        // =====================================================================
        let _kitchenStatusFilter = 'all';

        function filterKitchenOrders() {
            const query = (document.getElementById('kitchen_search_input')?.value || '').trim().toLowerCase();
            const resetBtn = document.getElementById('kitchen-reset-btn');

            // Show reset button only when there's an active query
            if (resetBtn) {
                resetBtn.style.display = query ? '' : 'none';
            }

            // Filter cards and track per-column matches
            const columnMatches = {
                'kitchen-pending-list': 0,
                'kitchen-preparing-list': 0,
                'kitchen-ready-list': 0
            };

            document.querySelectorAll('.kitchen-card').forEach(card => {
                const text = card.textContent.toLowerCase();
                const match = !query || text.includes(query);
                card.style.display = match ? '' : 'none';

                // Count visible cards per column
                const parentList = card.closest('[id^="kitchen-"][id$="-list"]');
                if (match && parentList && parentList.id in columnMatches) {
                    columnMatches[parentList.id]++;
                }
            });

            // Show 'no results' message per column when search has no matches
            if (query) {
                Object.entries(columnMatches).forEach(([listId, count]) => {
                    const list = document.getElementById(listId);
                    if (!list) return;
                    let noRes = list.querySelector('.kitchen-no-results');
                    if (count === 0) {
                        if (!noRes) {
                            noRes = document.createElement('div');
                            noRes.className = 'kitchen-no-results text-center text-muted py-4';
                            noRes.innerHTML = `<i class="fas fa-search me-2"></i>No match for "${query}"`;
                            list.appendChild(noRes);
                        } else {
                            noRes.innerHTML = `<i class="fas fa-search me-2"></i>No match for "${query}"`;
                            noRes.style.display = '';
                        }
                    } else if (noRes) {
                        noRes.style.display = 'none';
                    }
                });
            } else {
                // Remove all no-results messages when query cleared
                document.querySelectorAll('.kitchen-no-results').forEach(el => el.remove());
            }
        }

        function resetKitchenSearch() {
            const input = document.getElementById('kitchen_search_input');
            if (input) input.value = '';
            filterKitchenOrders();
        }

        function filterKitchenStatus(status) {
            _kitchenStatusFilter = status;
            // Toggle active button
            ['all', 'pending', 'preparing', 'ready'].forEach(s => {
                const btn = document.getElementById(`btn-kitchen-filter-${s}`);
                if (btn) btn.classList.toggle('active', s === status);
            });
            // Show/hide kitchen columns
            const colMap = {
                all: ['kitchen-pending-list', 'kitchen-preparing-list', 'kitchen-ready-list'],
                pending: ['kitchen-pending-list'],
                preparing: ['kitchen-preparing-list'],
                ready: ['kitchen-ready-list']
            };
            ['kitchen-pending-list', 'kitchen-preparing-list', 'kitchen-ready-list'].forEach(id => {
                const el = document.getElementById(id);
                const parent = el ? el.closest('.kitchen-col') : null;
                if (parent) parent.style.display = (status === 'all' || (colMap[status] || []).includes(id)) ? '' : 'none';
            });
        }

        // =====================================================================
        // 3. MENU SEARCH
        // =====================================================================
        function performMenuSearch(event) {
            if (event) event.preventDefault();

            const params = new URLSearchParams({
                action: 'search_menu',
                search: document.getElementById('menu_search_input')?.value || '',
                category: document.getElementById('menu_category_select')?.value || 'all',
                availability: document.getElementById('menu_availability_select')?.value || 'all',
                diet_type: document.getElementById('menu_diet_select')?.value || 'all',
                min_price: document.getElementById('menu_price_min')?.value || '',
                max_price: document.getElementById('menu_price_max')?.value || '',
                bestseller: document.getElementById('menu_bestseller_check')?.checked ? '1' : '0'
            });

            // Show reset button when search is active
            const resetBtn = document.getElementById('menu-reset-btn');
            if (resetBtn) resetBtn.style.display = '';

            const card = document.getElementById('menu-search-results-card');
            const tbody = document.getElementById('menu-search-results-body');
            if (card) card.style.display = 'block';
            if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Searching menu...</td></tr>';

            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) { showSearchError('menu-search-results-body', 7, 'Search failed.'); return; }
                renderMenuSearchResults(data.menu);
            })
            .catch(() => showSearchError('menu-search-results-body', 7, 'Network error.'));
        }

        function resetMenuSearch() {
            // Clear all filter inputs
            const fields = ['menu_search_input', 'menu_price_min', 'menu_price_max'];
            fields.forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
            const selects = ['menu_category_select', 'menu_diet_select', 'menu_availability_select'];
            selects.forEach(id => { const el = document.getElementById(id); if (el) el.value = el.options[0].value; });
            const check = document.getElementById('menu_bestseller_check');
            if (check) check.checked = false;
            // Hide results and reset button
            const card = document.getElementById('menu-search-results-card');
            if (card) card.style.display = 'none';
            const resetBtn = document.getElementById('menu-reset-btn');
            if (resetBtn) resetBtn.style.display = 'none';
        }

        function renderMenuSearchResults(menu) {
            const tbody = document.getElementById('menu-search-results-body');
            if (!tbody) return;

            if (!menu || menu.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No menu items found.</td></tr>';
                return;
            }

            tbody.innerHTML = menu.map(dish => {
                let imgSrc = dish.display_image_url || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=100&h=100&fit=crop&auto=format';

                const vegBadge = dish.is_veg
                    ? '<span class="badge" style="background:#16a34a; color:#fff; font-size:0.7rem;">VEG</span>'
                    : '<span class="badge" style="background:#dc2626; color:#fff; font-size:0.7rem;">NON-VEG</span>';
                const bestBadge = dish.is_bestseller
                    ? '<span class="badge bg-warning text-dark ms-1" style="font-size:0.7rem;">⭐ BESTSELLER</span>'
                    : '';

                const custCount = dish.cust_count || 0;
                const custActive = custCount > 0 ? 'active' : '';
                const isAvail = dish.is_available == 1;

                return `<tr>
                    <td><img src="${imgSrc}" alt="" style="width:44px;height:44px;border-radius:8px;object-fit:cover;" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=100&h=100&fit=crop&auto=format'"></td>
                    <td><strong>${dish.name}</strong> ${vegBadge}${bestBadge}</td>
                    <td class="text-uppercase"><small>${dish.category || '—'}</small></td>
                    <td class="text-gold">${fmtINR(dish.price)}</td>
                    <td><small class="text-muted">${(dish.description || '').substring(0, 60)}${(dish.description || '').length > 60 ? '…' : ''}</small></td>
                    <td class="text-center">
                        <div class="form-check form-switch premium-switch d-inline-block">
                            <input class="form-check-input" type="checkbox" role="switch"
                                ${isAvail ? 'checked' : ''}
                                onchange="toggleMenuAvailability(${dish.id}, this.checked)">
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <button class="btn btn-sm btn-luxury-action btn-luxury-custom ${custActive}"
                                onclick="openCustomizationManager(${dish.id}, '${dish.name.replace(/'/g, "\\'")}')"
                                title="Manage Customizations">
                                <i class="fas fa-sliders-h"></i>
                                <span class="luxury-badge bg-gold-badge ms-1">${custCount}</span>
                            </button>
                            <button class="btn btn-sm btn-luxury-action btn-luxury-edit"
                                onclick="openEditMenuModal(${JSON.stringify(dish).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')})"
                                title="Edit Item">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-luxury-action btn-luxury-delete"
                                onclick="deleteMenuItem(${dish.id})"
                                title="Delete Item">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        }

        // =====================================================================
        // 4. CUSTOMERS SEARCH
        // =====================================================================
        function performCustomersSearch(event) {
            if (event) event.preventDefault();

            const params = new URLSearchParams({
                action: 'search_customers',
                search: document.getElementById('customer_search_input')?.value || ''
            });

            setTableLoading('customers-table-body', 8);

            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) { showSearchError('customers-table-body', 8, 'Search failed.'); return; }
                renderCustomersSearchResults(data.customers);
            })
            .catch(() => showSearchError('customers-table-body', 8, 'Network error.'));
        }

        function renderCustomersSearchResults(customers) {
            const tbody = document.getElementById('customers-table-body');
            if (!tbody) return;

            if (!customers || customers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No customers found.</td></tr>';
                return;
            }

            tbody.innerHTML = customers.map(c => {
                const paid = c.payment_summary?.paid_count || 0;
                const failed = c.payment_summary?.failed_count || 0;
                const pending = c.payment_summary?.pending_count || 0;
                const lastDate = c.last_order_date ? new Date(c.last_order_date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

                return `<tr>
                    <td><strong>${c.customer_name || 'Guest'}</strong><br><small class="text-muted">ID: ${c.customer_id || 'GUEST'}</small></td>
                    <td>${c.customer_phone || '—'}</td>
                    <td><small class="text-muted">${c.email || '—'}</small></td>
                    <td>${c.order_count || 0} orders</td>
                    <td class="text-gold">${fmtINR(c.total_spent)}</td>
                    <td><small class="text-muted">${lastDate}</small></td>
                    <td><span class="badge bg-dark border border-secondary text-white">${c.favorite_dish || '—'}</span></td>
                    <td>
                        <span class="badge bg-success text-dark">Paid: ${paid}</span>
                        ${pending > 0 ? `<span class="badge bg-warning text-dark ms-1">Pending: ${pending}</span>` : ''}
                        ${failed > 0 ? `<span class="badge bg-danger text-white ms-1">Failed: ${failed}</span>` : ''}
                    </td>
                </tr>`;
            }).join('');
        }

        // =====================================================================
        // 5. PAYMENTS SEARCH
        // =====================================================================
        function performPaymentsSearch(event) {
            if (event) event.preventDefault();

            const params = new URLSearchParams({
                action: 'search_payments',
                search: document.getElementById('payment_search_input')?.value || '',
                method: document.getElementById('payment_method_select')?.value || 'all',
                status: document.getElementById('payment_status_select')?.value || 'all',
                min_amount: document.getElementById('payment_min_amount')?.value || '',
                max_amount: document.getElementById('payment_max_amount')?.value || ''
            });

            setTableLoading('payments-table-body', 6);

            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) { showSearchError('payments-table-body', 6, 'Search failed.'); return; }
                renderPaymentsSearchResults(data.payments);
            })
            .catch(() => showSearchError('payments-table-body', 6, 'Network error.'));
        }

        function renderPaymentsSearchResults(logs) {
            const tbody = document.getElementById('payments-table-body');
            if (!tbody) return;

            if (!logs || logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No transactions found.</td></tr>';
                return;
            }

            tbody.innerHTML = logs.map(log => {
                let method = 'ONLINE GATEWAY';
                const addr = (log.delivery_address || '').toUpperCase();
                const pm = (log.payment_method || '').toLowerCase();
                if (addr.includes('PAID VIA CASH') || pm === 'cash' || pm === 'cod') method = 'CASH';
                else if (addr.includes('PAID VIA CARD') || pm === 'card') method = 'CARD';
                else if (addr.includes('PAID VIA UPI') || pm === 'upi') method = 'UPI';
                else if (addr.includes('PAID VIA NETBANKING') || addr.includes('PAID VIA NET BANKING') || pm === 'netbanking') method = 'NET BANKING';
                else if (addr.includes('PAID VIA WALLET') || pm === 'wallet') method = 'WALLET';

                const isPaid = log.order_status?.toLowerCase() === 'completed';
                const statusHtml = isPaid
                    ? '<span class="status-badge bg-success text-dark">Paid</span>'
                    : '<span class="status-badge bg-warning text-dark">Pending Settlement</span>';
                const dateStr = log.order_date ? new Date(log.order_date).toLocaleString('en-IN') : '—';

                return `<tr>
                    <td>#${log.order_number || log.id}</td>
                    <td>${log.customer_name || '—'}</td>
                    <td class="text-gold">${fmtINR(log.total_amount)}</td>
                    <td><span class="badge bg-dark border border-secondary text-white">${method}</span></td>
                    <td>${statusHtml}</td>
                    <td><small class="text-muted">${dateStr}</small></td>
                </tr>`;
            }).join('');
        }

        // =====================================================================
        // 6. REPORTS / BI DASHBOARD
        // =====================================================================
        let repSalesChartInst = null;
        let repPaymentChartInst = null;
        let repCategoryChartInst = null;
        let _lastReportData = null;

        function fmtINR(val) {
            return '₹' + Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function growthBadge(val) {
            const num = parseFloat(val || 0);
            const icon = num >= 0 ? 'fa-caret-up' : 'fa-caret-down';
            return `<i class="fas ${icon}"></i> ${Math.abs(num)}% vs last period`;
        }

        function loadReportsData(event) {
            if (event) event.preventDefault();

            const range = document.getElementById('report_range_select')?.value || 'thisweek';
            const start_date = document.getElementById('report_start_date')?.value || '';
            const end_date = document.getElementById('report_end_date')?.value || '';

            // Show loading skeleton on summary cards
            ['rep_revenue','rep_orders','rep_aov','rep_perf_score'].forEach(id => {
                const el = document.getElementById(id);
                if (el) { el.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
            });

            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_reports_data', range, start_date, end_date })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) { alert('Failed to load report data. Please try again.'); return; }
                _lastReportData = data;
                renderReportSummary(data.summary);
                renderReportTrendChart(data.trend);
                renderReportPaymentChart(data.payments);
                renderReportCategoryChart(data.categories);
                renderReportDishesTable(data.dishes);
                renderReportCustomersTable(data.top_customers);
            })
            .catch(() => alert('Network error while loading reports.'));
        }

        function renderReportSummary(summary) {
            if (!summary) return;

            const rev = document.getElementById('rep_revenue');
            const revG = document.getElementById('rep_revenue_growth');
            if (rev) rev.textContent = fmtINR(summary.revenue);
            if (revG) { revG.className = parseFloat(summary.revenue_growth) >= 0 ? 'text-success' : 'text-danger'; revG.innerHTML = growthBadge(summary.revenue_growth); }

            const ord = document.getElementById('rep_orders');
            const ordG = document.getElementById('rep_orders_growth');
            if (ord) ord.textContent = summary.orders_count || 0;
            if (ordG) { ordG.className = parseFloat(summary.orders_growth) >= 0 ? 'text-success' : 'text-danger'; ordG.innerHTML = growthBadge(summary.orders_growth); }

            const aov = document.getElementById('rep_aov');
            const aovG = document.getElementById('rep_aov_growth');
            if (aov) aov.textContent = fmtINR(summary.aov);
            if (aovG) { aovG.className = parseFloat(summary.aov_growth) >= 0 ? 'text-success' : 'text-danger'; aovG.innerHTML = growthBadge(summary.aov_growth); }

            const perf = document.getElementById('rep_perf_score');
            if (perf) perf.textContent = (summary.performance_score || 0) + '/100';

            // Operations panel
            const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
            setEl('rep_op_online', summary.online_orders || 0);
            setEl('rep_op_dinein', summary.dinein_orders || 0);
            setEl('rep_op_acceptance', (summary.acceptance_rate || 0) + '%');
            setEl('rep_op_completion', (summary.completion_rate || 0) + '%');
            setEl('rep_cust_total', summary.total_customers || 0);
            setEl('rep_cust_new', summary.new_customers || 0);
            setEl('rep_cust_returning', summary.returning_customers || 0);
            setEl('rep_cust_retention', (summary.retention_rate || 0) + '%');
        }

        function getChartColors() {
            const isLight = document.documentElement.classList.contains('light-mode');
            return {
                gridColor: isLight ? 'rgba(0,0,0,0.07)' : 'rgba(255,255,255,0.06)',
                tickColor: isLight ? '#475569' : '#a09f9f',
                labelColor: isLight ? '#1e293b' : '#f0ece4',
                gold: '#dfba86',
                palette: ['#dfba86','#2ec4b6','#6366f1','#f97316','#ec4899','#84cc16','#14b8a6','#f43f5e']
            };
        }

        function renderReportTrendChart(trend) {
            const canvas = document.getElementById('repSalesChart');
            if (!canvas) return;
            const colors = getChartColors();

            if (repSalesChartInst) repSalesChartInst.destroy();

            repSalesChartInst = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: trend?.labels || [],
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: trend?.data || [],
                        backgroundColor: 'rgba(223,186,134,0.18)',
                        borderColor: colors.gold,
                        borderWidth: 2,
                        borderRadius: 6,
                        hoverBackgroundColor: 'rgba(223,186,134,0.38)'
                    }]
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            grid: { color: colors.gridColor },
                            ticks: { color: colors.tickColor, callback: v => '₹' + Number(v).toLocaleString('en-IN') }
                        },
                        x: {
                            grid: { color: colors.gridColor },
                            ticks: { color: colors.tickColor, maxRotation: 45 }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: { label: ctx => ' ₹' + Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }
                        }
                    }
                }
            });
        }

        function renderReportPaymentChart(payments) {
            const canvas = document.getElementById('repPaymentChart');
            if (!canvas || !payments) return;
            const colors = getChartColors();

            const labels = Object.keys(payments).filter(k => payments[k].amount > 0);
            const data = labels.map(k => payments[k].amount);

            if (repPaymentChartInst) repPaymentChartInst.destroy();

            if (labels.length === 0) {
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                canvas.parentElement.innerHTML = `<canvas id="repPaymentChart" style="max-height:280px;max-width:280px;"></canvas><p class="text-center text-muted mt-3">No payment data for this period.</p>`;
                return;
            }

            repPaymentChartInst = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{ data, backgroundColor: colors.palette.slice(0, labels.length), borderWidth: 2, borderColor: document.documentElement.classList.contains('light-mode') ? '#fff' : '#0a0a0a' }]
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: { position: 'bottom', labels: { color: colors.labelColor, padding: 12, font: { size: 11 } } },
                        tooltip: {
                            callbacks: { label: ctx => ` ${ctx.label}: ₹${Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 })} (${((ctx.raw / data.reduce((a,b) => a+b, 0))*100).toFixed(1)}%)` }
                        }
                    }
                }
            });
        }

        function renderReportCategoryChart(categories) {
            const canvas = document.getElementById('repCategoryChart');
            if (!canvas || !categories) return;
            const colors = getChartColors();

            const labels = categories.map(c => (c.category_name || 'Other').toUpperCase());
            const data = categories.map(c => parseFloat(c.revenue || 0));

            if (repCategoryChartInst) repCategoryChartInst.destroy();

            if (labels.length === 0) {
                canvas.parentElement.innerHTML = `<canvas id="repCategoryChart" style="max-height:280px;max-width:280px;"></canvas><p class="text-center text-muted mt-3">No category data for this period.</p>`;
                return;
            }

            repCategoryChartInst = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{ data, backgroundColor: colors.palette.slice(0, labels.length), borderWidth: 2, borderColor: document.documentElement.classList.contains('light-mode') ? '#fff' : '#0a0a0a' }]
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: { position: 'bottom', labels: { color: colors.labelColor, padding: 10, font: { size: 11 } } },
                        tooltip: {
                            callbacks: { label: ctx => ` ${ctx.label}: ₹${Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 })}` }
                        }
                    }
                }
            });
        }

        function renderReportDishesTable(dishes) {
            const tbody = document.querySelector('#rep-dishes-table tbody');
            if (!tbody) return;

            if (!dishes || dishes.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No dish data for this period.</td></tr>';
                return;
            }

            tbody.innerHTML = dishes.map((d, i) => `<tr>
                <td>
                    <span style="display:inline-block;width:20px;height:20px;border-radius:50%;background:rgba(223,186,134,0.15);color:#dfba86;font-size:0.7rem;font-weight:700;text-align:center;line-height:20px;margin-right:8px;">${i+1}</span>
                    ${d.item_name}
                </td>
                <td>${d.qty_sold || 0}</td>
                <td class="text-gold">${fmtINR(d.revenue)}</td>
            </tr>`).join('');
        }

        function renderReportCustomersTable(customers) {
            const tbody = document.querySelector('#rep-customers-table tbody');
            if (!tbody) return;

            if (!customers || customers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No customer data for this period.</td></tr>';
                return;
            }

            tbody.innerHTML = customers.map((c, i) => `<tr>
                <td>
                    <span style="display:inline-block;width:20px;height:20px;border-radius:50%;background:rgba(223,186,134,0.15);color:#dfba86;font-size:0.7rem;font-weight:700;text-align:center;line-height:20px;margin-right:8px;">${i+1}</span>
                    <strong>${c.customer_name || 'Guest'}</strong><br><small class="text-muted">${c.customer_phone || ''}</small>
                </td>
                <td>${c.order_count || 0}</td>
                <td class="text-gold">${fmtINR(c.total_spent)}</td>
            </tr>`).join('');
        }

        // =====================================================================
        // 7. EXPORT FUNCTIONS
        // =====================================================================
        function populateReportTemplate() {
            if (!_lastReportData) return false;
            
            const summary = _lastReportData.summary || {};
            const dishes = _lastReportData.dishes || [];
            
            // Format dates
            document.getElementById('print_report_period').textContent = `${summary.start_date || 'N/A'} to ${summary.end_date || 'N/A'}`;
            document.getElementById('print_report_date').textContent = new Date().toLocaleString();
            
            // Populate summary
            document.getElementById('print_report_revenue').textContent = `₹${parseFloat(summary.revenue || 0).toFixed(2)}`;
            document.getElementById('print_report_orders').textContent = `${summary.orders_count || 0} (${summary.online_orders || 0} Online, ${summary.dinein_orders || 0} Dine-In)`;
            document.getElementById('print_report_aov').textContent = `₹${parseFloat(summary.aov || 0).toFixed(2)}`;
            
            let acc = parseFloat(summary.acceptance_rate || 0).toFixed(0);
            let cmp = parseFloat(summary.completion_rate || 0).toFixed(0);
            document.getElementById('print_report_rates').textContent = `${acc}% / ${cmp}%`;
            
            let score = parseFloat(summary.performance_score || 0);
            document.getElementById('print_report_score').textContent = `${score.toFixed(0)} / 100`;
            
            // Customer Analytics
            document.getElementById('print_report_cust_total').textContent = summary.total_customers || 0;
            document.getElementById('print_report_cust_new').textContent = summary.new_customers || 0;
            document.getElementById('print_report_cust_return').textContent = summary.returning_customers || 0;
            document.getElementById('print_report_cust_rate').textContent = `${summary.retention_rate || 0}%`;
            
            // Top Dishes
            const dishesTbody = document.getElementById('print_top_dishes_tbody');
            dishesTbody.innerHTML = '';
            dishes.slice(0, 15).forEach(dish => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="padding: 10px; border: 1px solid #ddd;">${dish.item_name || dish.name || 'Unknown'}</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${dish.category || dish.category_name || 'N/A'}</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${dish.qty_sold || 0}</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">₹${parseFloat(dish.revenue || 0).toFixed(2)}</td>
                `;
                dishesTbody.appendChild(tr);
            });
            
            // Payment Methods
            const payments = _lastReportData.payments || {};
            const paymentsTbody = document.getElementById('print_payments_tbody');
            paymentsTbody.innerHTML = '';
            Object.entries(payments).forEach(([method, vals]) => {
                if (vals.count > 0) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="padding: 10px; border: 1px solid #ddd;">${method.toUpperCase()}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${vals.count}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">₹${parseFloat(vals.amount || 0).toFixed(2)}</td>
                    `;
                    paymentsTbody.appendChild(tr);
                }
            });

            // Top Customers
            const customers = _lastReportData.top_customers || [];
            const customersTbody = document.getElementById('print_customers_tbody');
            if (customersTbody) {
                customersTbody.innerHTML = '';
                customers.slice(0, 10).forEach(c => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="padding: 10px; border: 1px solid #ddd;">${c.customer_name || 'Unknown'}<br><small style="color:#666;">${c.customer_phone}</small></td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${c.order_count || 0}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">₹${parseFloat(c.total_spent || 0).toFixed(2)}</td>
                    `;
                    customersTbody.appendChild(tr);
                });
            }

            // Helper to grab chart canvas with white background
            function getCanvasDataURL(canvasId) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return '';
                try {
                    const tempCanvas = document.createElement('canvas');
                    tempCanvas.width = canvas.width;
                    tempCanvas.height = canvas.height;
                    const ctx = tempCanvas.getContext('2d');
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
                    ctx.drawImage(canvas, 0, 0);
                    return tempCanvas.toDataURL('image/jpeg', 0.95);
                } catch(e) {
                    console.error('Canvas extract error:', e);
                    return '';
                }
            }

            // Grab chart canvas images
            document.getElementById('print_sales_chart_img').src = getCanvasDataURL('repSalesChart');
            document.getElementById('print_payment_chart_img').src = getCanvasDataURL('repPaymentChart');
            document.getElementById('print_category_chart_img').src = getCanvasDataURL('repCategoryChart');
            
            return true;
        }

        function printReport() {
            if (!populateReportTemplate()) {
                alert('Please generate a report first by clicking "Update Report".');
                return;
            }
            window.print();
        }
  
        function exportReportToPDF() {
            if (!populateReportTemplate()) {
                alert('Please generate a report first by clicking "Update Report".');
                return;
            }
            
            const element = document.getElementById('printableReportTemplate');
            
            // Temporarily show the template for html2pdf rendering since it clones it
            const originalDisplay = element.style.display;
            const originalWidth = element.style.width;
            
            element.style.display = 'block';
            // Force A4 physical pixel width so that it wraps and sizes identically to print view
            element.style.width = '794px'; 
            
            const opt = {
                margin:       10,
                filename:     'Medusa_Business_Report_' + new Date().toISOString().slice(0, 10) + '.pdf',
                image:        { type: 'jpeg', quality: 1.0 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save().then(() => {
                element.style.display = originalDisplay;
                element.style.width = originalWidth;
            });
        }

        async function exportReportToExcel() {
            if (!_lastReportData) {
                alert('Please generate a report first by clicking "Update Report".');
                return;
            }

            // Dynamically load ExcelJS if not available
            if (typeof ExcelJS === 'undefined') {
                const btn = document.querySelector('[onclick="exportReportToExcel()"]');
                const origHtml = btn ? btn.innerHTML : '';
                if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing...';
                
                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = "https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js";
                    script.onload = resolve;
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
                if (btn) btn.innerHTML = origHtml;
            }

            const summary = _lastReportData.summary || {};
            const dishes = _lastReportData.dishes || [];
            const categories = _lastReportData.categories || [];
            const payments = _lastReportData.payments || {};
            const customers = _lastReportData.top_customers || [];

            const workbook = new ExcelJS.Workbook();
            workbook.creator = 'Medusa Luxury Dashboard';
            workbook.created = new Date();

            // Colors
            const gold = 'FFD4AF37'; // Medusa Gold
            const dark = 'FF111111'; // Dark bg
            const white = 'FFFFFFFF';
            const gray = 'FFF0F0F0';
            
            // Reusable Border Style
            const thinBorder = {
                top: {style:'thin', color: {argb:'FFCCCCCC'}},
                left: {style:'thin', color: {argb:'FFCCCCCC'}},
                bottom: {style:'thin', color: {argb:'FFCCCCCC'}},
                right: {style:'thin', color: {argb:'FFCCCCCC'}}
            };

            // 1. Summary Sheet
            const wsSum = workbook.addWorksheet('Summary');
            wsSum.columns = [{ width: 25 }, { width: 20 }, { width: 25 }];
            
            // Header
            wsSum.addRow(['MEDUSA RESTAURANT - BUSINESS INTELLIGENCE REPORT']);
            wsSum.mergeCells('A1:C1');
            wsSum.getCell('A1').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: dark } };
            wsSum.getCell('A1').font = { color: { argb: gold }, size: 14, bold: true };
            wsSum.getCell('A1').alignment = { horizontal: 'center', vertical: 'middle' };
            wsSum.getRow(1).height = 30;

            wsSum.addRow([]);
            wsSum.addRow(['Report Period', `${summary.start_date || ''} to ${summary.end_date || ''}`]);
            wsSum.mergeCells('B3:C3');
            wsSum.addRow(['Generated At', new Date().toLocaleString()]);
            wsSum.mergeCells('B4:C4');
            wsSum.addRow([]);

            // Subheader - Financials
            const sumHeader = wsSum.addRow(['Financial Metric', 'Value', 'Growth vs Last Period']);
            sumHeader.eachCell(c => {
                c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF333333' } };
                c.font = { color: { argb: white }, bold: true };
                c.border = thinBorder;
            });

            // Data - Financials
            const revG = parseFloat(summary.revenue_growth || 0);
            const ordG = parseFloat(summary.orders_growth || 0);
            const aovG = parseFloat(summary.aov_growth || 0);

            const r1 = wsSum.addRow(['Total Revenue (INR)', parseFloat(summary.revenue || 0), (revG>0?'+':'') + revG + '%']);
            r1.getCell(2).numFmt = '₹#,##0.00';
            r1.getCell(3).font = { color: { argb: revG >= 0 ? 'FF007A33' : 'FFC1272D' }, bold: true };
            r1.eachCell(c => { c.border = thinBorder; c.alignment = { horizontal: c._column._number > 1 ? 'right' : 'left' }; });

            const r2 = wsSum.addRow(['Completed Orders', parseInt(summary.orders_count || 0), (ordG>0?'+':'') + ordG + '%']);
            r2.getCell(3).font = { color: { argb: ordG >= 0 ? 'FF007A33' : 'FFC1272D' }, bold: true };
            r2.eachCell(c => { c.border = thinBorder; c.alignment = { horizontal: c._column._number > 1 ? 'right' : 'left' }; });

            const r3 = wsSum.addRow(['Average Order Value (INR)', parseFloat(summary.aov || 0), (aovG>0?'+':'') + aovG + '%']);
            r3.getCell(2).numFmt = '₹#,##0.00';
            r3.getCell(3).font = { color: { argb: aovG >= 0 ? 'FF007A33' : 'FFC1272D' }, bold: true };
            r3.eachCell(c => { c.border = thinBorder; c.alignment = { horizontal: c._column._number > 1 ? 'right' : 'left' }; });

            const r4 = wsSum.addRow(['Performance Score', `${summary.performance_score || 0}/100`, '']);
            r4.eachCell(c => { c.border = thinBorder; c.alignment = { horizontal: c._column._number > 1 ? 'right' : 'left' }; });

            wsSum.addRow([]);

            // Subheader - Operations
            const opsHeader = wsSum.addRow(['Operations & Customers', 'Value', '']);
            wsSum.mergeCells(`B${opsHeader.number}:C${opsHeader.number}`);
            opsHeader.eachCell(c => {
                c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF333333' } };
                c.font = { color: { argb: white }, bold: true };
                c.border = thinBorder;
            });
            
            const addOpRow = (label, val) => {
                const row = wsSum.addRow([label, val, '']);
                wsSum.mergeCells(`B${row.number}:C${row.number}`);
                row.eachCell(c => { c.border = thinBorder; });
                row.getCell(2).alignment = { horizontal: 'right' };
            };
            
            addOpRow('Online Orders', summary.online_orders || 0);
            addOpRow('Dine-in Orders', summary.dinein_orders || 0);
            addOpRow('Acceptance Rate', `${parseFloat(summary.acceptance_rate || 0).toFixed(1)}%`);
            addOpRow('Completion Rate', `${parseFloat(summary.completion_rate || 0).toFixed(1)}%`);
            addOpRow('Total Customers Reached', summary.total_customers || 0);
            addOpRow('New Guest Registrations', summary.new_customers || 0);
            addOpRow('Returning Customer Base', summary.returning_customers || 0);
            addOpRow('Guest Retention Rate', `${parseFloat(summary.retention_rate || 0).toFixed(1)}%`);

            // 2. Top Dishes Sheet
            const wsDishes = workbook.addWorksheet('Top Dishes');
            wsDishes.columns = [{ width: 10 }, { width: 35 }, { width: 25 }, { width: 15 }, { width: 20 }];
            
            wsDishes.addRow(['BEST SELLING DISHES']);
            wsDishes.mergeCells('A1:E1');
            wsDishes.getCell('A1').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: dark } };
            wsDishes.getCell('A1').font = { color: { argb: gold }, size: 12, bold: true };
            wsDishes.getCell('A1').alignment = { horizontal: 'center' };
            
            const dh = wsDishes.addRow(['Rank', 'Dish Name', 'Category', 'Qty Sold', 'Revenue (INR)']);
            dh.eachCell(c => { c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: gray } }; c.font = { bold: true }; c.border = thinBorder; });
            
            dishes.forEach((d, i) => {
                const r = wsDishes.addRow([i + 1, d.item_name || d.name, d.category || 'N/A', parseInt(d.qty_sold), parseFloat(d.revenue || 0)]);
                r.getCell(5).numFmt = '₹#,##0.00';
                r.eachCell(c => { c.border = thinBorder; c.alignment = { horizontal: c._column._number > 3 ? 'right' : 'left' }; });
                r.getCell(1).alignment = { horizontal: 'center' };
            });

            // 3. Category Performance
            const wsCat = workbook.addWorksheet('Categories');
            wsCat.columns = [{ width: 25 }, { width: 15 }, { width: 20 }];
            
            wsCat.addRow(['CATEGORY PERFORMANCE']);
            wsCat.mergeCells('A1:C1');
            wsCat.getCell('A1').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: dark } };
            wsCat.getCell('A1').font = { color: { argb: gold }, size: 12, bold: true };
            wsCat.getCell('A1').alignment = { horizontal: 'center' };

            const ch = wsCat.addRow(['Category', 'Units Sold', 'Revenue (INR)']);
            ch.eachCell(c => { c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: gray } }; c.font = { bold: true }; c.border = thinBorder; });

            categories.forEach(c => {
                const r = wsCat.addRow([c.category_name || c.category, parseInt(c.units_sold || c.qty), parseFloat(c.revenue || 0)]);
                r.getCell(3).numFmt = '₹#,##0.00';
                r.eachCell(cell => { cell.border = thinBorder; cell.alignment = { horizontal: cell._column._number > 1 ? 'right' : 'left' }; });
            });

            // 4. Payments
            const wsPay = workbook.addWorksheet('Payments');
            wsPay.columns = [{ width: 25 }, { width: 15 }, { width: 20 }];
            
            wsPay.addRow(['PAYMENT BREAKDOWN']);
            wsPay.mergeCells('A1:C1');
            wsPay.getCell('A1').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: dark } };
            wsPay.getCell('A1').font = { color: { argb: gold }, size: 12, bold: true };
            wsPay.getCell('A1').alignment = { horizontal: 'center' };

            const ph = wsPay.addRow(['Payment Method', 'Transactions', 'Total Amount (INR)']);
            ph.eachCell(c => { c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: gray } }; c.font = { bold: true }; c.border = thinBorder; });

            Object.entries(payments).forEach(([method, vals]) => {
                if (vals.count > 0) {
                    const r = wsPay.addRow([method.toUpperCase(), parseInt(vals.count), parseFloat(vals.amount || 0)]);
                    r.getCell(3).numFmt = '₹#,##0.00';
                    r.eachCell(cell => { cell.border = thinBorder; cell.alignment = { horizontal: cell._column._number > 1 ? 'right' : 'left' }; });
                }
            });

            // 5. Top Customers
            const wsCust = workbook.addWorksheet('Top Customers');
            wsCust.columns = [{ width: 25 }, { width: 15 }, { width: 15 }, { width: 20 }];
            
            wsCust.addRow(['TOP PERFORMING CUSTOMERS']);
            wsCust.mergeCells('A1:D1');
            wsCust.getCell('A1').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: dark } };
            wsCust.getCell('A1').font = { color: { argb: gold }, size: 12, bold: true };
            wsCust.getCell('A1').alignment = { horizontal: 'center' };

            const cuh = wsCust.addRow(['Customer Name', 'Phone', 'Orders', 'Total Spent (INR)']);
            cuh.eachCell(c => { c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: gray } }; c.font = { bold: true }; c.border = thinBorder; });

            customers.slice(0, 50).forEach(c => {
                const r = wsCust.addRow([c.customer_name || 'Unknown', c.customer_phone, parseInt(c.order_count || 0), parseFloat(c.total_spent || 0)]);
                r.getCell(4).numFmt = '₹#,##0.00';
                r.eachCell(cell => { cell.border = thinBorder; cell.alignment = { horizontal: cell._column._number > 2 ? 'right' : 'left' }; });
            });

            // Trigger Download
            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'Medusa_Business_Report_' + new Date().toISOString().slice(0, 10) + '.xlsx';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // =====================================================================
        // 8. AUTO-INITIALIZATION
        // =====================================================================
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-load reports when reports tab is activated
            const reportsLink = document.querySelector('[onclick*="reports-tab"]');
            if (reportsLink) {
                const origOnclick = reportsLink.getAttribute('onclick');
                reportsLink.setAttribute('onclick', origOnclick);
                reportsLink.addEventListener('click', function() {
                    setTimeout(() => { loadReportsData(null); }, 150);
                });
            }
            
            const campaignsLink = document.querySelector('[onclick*="campaigns-tab"]');
            if (campaignsLink) {
                campaignsLink.addEventListener('click', function() {
                    setTimeout(() => { fetchCampaigns(); }, 150);
                });
            }

            // Real-time debounced orders search (search-as-you-type on the text field)
            const orderInput = document.getElementById('order_search_input');
            if (orderInput) {
                orderInput.addEventListener('input', debounce(() => performOrdersSearch(null), 400));
            }

            // Real-time debounced menu search
            const menuInput = document.getElementById('menu_search_input');
            if (menuInput) {
                menuInput.addEventListener('input', debounce(() => performMenuSearch(null), 400));
            }

            // Real-time debounced customer search
            const custInput = document.getElementById('customer_search_input');
            if (custInput) {
                custInput.addEventListener('input', debounce(() => performCustomersSearch(null), 400));
            }

            // Real-time debounced payment search
            const payInput = document.getElementById('payment_search_input');
            if (payInput) {
                payInput.addEventListener('input', debounce(() => performPaymentsSearch(null), 400));
            }

            // Real-time debounced careers search
            const careerInput = document.getElementById('career_search_input');
            if (careerInput) {
                careerInput.addEventListener('input', debounce(() => performCareersSearch(null), 400));
            }

            // Real-time selectors search for careers
            const posFilter = document.getElementById('career_position_filter');
            if (posFilter) {
                posFilter.addEventListener('change', () => performCareersSearch(null));
            }
            const statFilter = document.getElementById('career_status_filter');
            if (statFilter) {
                statFilter.addEventListener('change', () => performCareersSearch(null));
            }

            // Auto-load career applications when careers tab is activated
            const careersLink = document.querySelector('[onclick*="careers-tab"]');
            if (careersLink) {
                careersLink.addEventListener('click', function() {
                    setTimeout(() => { performCareersSearch(); }, 150);
                });
            }

            // Ensure orders-search-results-card is hidden by default
            const ordResCard = document.getElementById('orders-search-results-card');
            if (ordResCard) ordResCard.style.display = 'none';

            const menuResCard = document.getElementById('menu-search-results-card');
            if (menuResCard) menuResCard.style.display = 'none';

            // Update BI report chart colors when theme toggles
            document.addEventListener('themeChanged', () => {
                if (repSalesChartInst) { renderReportTrendChart(_lastReportData?.trend); }
                if (repPaymentChartInst && _lastReportData) { renderReportPaymentChart(_lastReportData.payments); }
                if (repCategoryChartInst && _lastReportData) { renderReportCategoryChart(_lastReportData.categories); }
            });
        });

        // Patch toggleTheme to dispatch custom event for chart theme updates
        const _origToggleTheme = toggleTheme;
        toggleTheme = function() {
            _origToggleTheme();
            document.dispatchEvent(new Event('themeChanged'));
        };

        // =====================================================================
        // CAREERS PORTAL DASHBOARD CONTROLLER
        // =====================================================================
        function performCareersSearch(event) {
            if (event) event.preventDefault();
            setTableLoading('careers-table-body', 7);

            const params = new URLSearchParams({
                action: 'get_career_applications',
                search: document.getElementById('career_search_input')?.value || '',
                position: document.getElementById('career_position_filter')?.value || 'all',
                status: document.getElementById('career_status_filter')?.value || 'all'
            });

            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) { showSearchError('careers-table-body', 7, 'Failed to load applications.'); return; }
                
                // Update metrics counters
                if (data.summary) {
                    const setMetric = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
                    setMetric('careers_total_metric', data.summary.total);
                    setMetric('careers_pending_metric', data.summary.pending);
                    setMetric('careers_shortlisted_metric', data.summary.shortlisted);
                    setMetric('careers_rejected_metric', data.summary.rejected);
                }

                renderCareersSearchResults(data.applications);
            })
            .catch(() => showSearchError('careers-table-body', 7, 'Network error loading applications.'));
        }

        function renderCareersSearchResults(applications) {
            const tbody = document.getElementById('careers-table-body');
            if (!tbody) return;

            if (!applications || applications.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No applications found matching criteria.</td></tr>';
                return;
            }

            tbody.innerHTML = applications.map(app => {
                const statusMap = {
                    pending: 'bg-warning text-dark',
                    reviewed: 'bg-info text-dark',
                    shortlisted: 'bg-success text-dark',
                    rejected: 'bg-danger text-white'
                };
                const badgeCls = statusMap[app.status?.toLowerCase()] || 'bg-secondary text-white';
                const appliedDate = app.applied_at ? new Date(app.applied_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
                const escLetter = (app.cover_letter || '').replace(/'/g, "\\'").replace(/"/g, '&quot;').replace(/\n/g, '\\n');
                
                let viewLetterBtn = '';
                if (app.cover_letter && app.cover_letter.trim() !== '') {
                    viewLetterBtn = `<button class="btn-action-circle btn-action-circle-info" onclick="openCoverLetterModal('${escLetter}')" title="View Message"><i class="fas fa-envelope"></i></button>`;
                }

                const ext = app.resume_path.split('.').pop().toLowerCase();
                const downloadName = `${app.full_name.replace(/\s+/g, '_')}_Resume.${ext}`;

                return `<tr>
                    <td>
                        <strong>${app.full_name}</strong><br>
                        <small class="text-muted"><i class="fas fa-envelope"></i> ${app.email}</small><br>
                        <small class="text-muted"><i class="fas fa-phone"></i> ${app.mobile}</small><br>
                        <small class="text-muted"><i class="fas fa-map-marker-alt"></i> ${app.city}</small>
                    </td>
                    <td><strong>${app.position}</strong></td>
                    <td>${app.experience} Years</td>
                    <td class="text-gold">₹${parseFloat(app.expected_salary).toLocaleString('en-IN')}</td>
                    <td>
                        <div class="d-flex align-items-center gap-1">
                            <button class="btn btn-sm btn-outline-warning d-flex align-items-center gap-1" onclick="openResumeModal('${app.resume_path}', '${app.full_name.replace(/'/g, "\\'")}')" title="View Resume"><i class="fas fa-eye"></i> View</button>
                            <a href="../${app.resume_path}" download="${downloadName}" class="btn btn-sm btn-outline-secondary d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0;" title="Download"><i class="fas fa-download"></i></a>
                        </div>
                    </td>
                    <td><span class="status-badge ${badgeCls}">${app.status || 'Pending'}</span></td>
                    <td>
                        <div class="d-flex align-items-center gap-2 flex-nowrap">
                            ${viewLetterBtn}
                            <button class="btn-action-circle btn-action-circle-light" onclick="updateCareerStatus(${app.id}, 'Reviewed')" title="Mark Reviewed"><i class="fas fa-check-double"></i></button>
                            <button class="btn-action-circle btn-action-circle-success" onclick="updateCareerStatus(${app.id}, 'Shortlisted')" title="Shortlist"><i class="fas fa-user-check"></i></button>
                            <button class="btn-action-circle btn-action-circle-danger" onclick="updateCareerStatus(${app.id}, 'Rejected')" title="Reject"><i class="fas fa-user-times"></i></button>
                            <button class="btn-action-circle btn-action-circle-danger" style="background: rgba(220, 38, 38, 0.2); border: 1px solid #dc2626;" onclick="deleteCareerApplication(${app.id})" title="Delete Application"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        }

        function deleteCareerApplication(id) {
            if (!confirm('Are you sure you want to permanently delete this application?')) return;
            
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'delete_career_application',
                    id: id
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    performCareersSearch(); // Refresh list
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete'));
                }
            })
            .catch(() => alert('Network error while deleting application.'));
        }

        function updateCareerStatus(id, newStatus) {
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'update_career_status',
                    id: id,
                    status: newStatus
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    performCareersSearch();
                } else {
                    alert('Error updating status: ' + data.message);
                }
            })
            .catch(() => alert('Network error updating application status.'));
        }

        function openCoverLetterModal(message) {
            document.getElementById('coverLetterContent').textContent = message;
            const modal = new bootstrap.Modal(document.getElementById('coverLetterModal'));
            modal.show();
        }

        function openResumeModal(resumePath, candidateName) {
            const body = document.getElementById('resumeViewerBody');
            if (!body) return;
            
            const ext = resumePath.split('.').pop().toLowerCase();
            const fullUrl = '../' + resumePath;
            
            if (ext === 'pdf') {
                body.innerHTML = `<iframe src="${fullUrl}" style="width: 100%; height: 600px; border: none; background: #ffffff;"></iframe>`;
            } else {
                const downloadName = candidateName.replace(/\s+/g, '_') + '_Resume.' + ext;
                body.innerHTML = `
                    <div class="text-center p-5">
                        <div style="font-size: 4rem; color: var(--gold); margin-bottom: 1.5rem;"><i class="far fa-file-word"></i></div>
                        <h4 class="mb-3 text-gold">Word Document Resume</h4>
                        <p class="text-muted mb-4">Word documents (.doc / .docx) cannot be previewed directly in the browser.<br>Please download the file to view its contents.</p>
                        <a href="${fullUrl}" class="btn btn-outline-warning btn-lg px-4" download="${downloadName}" style="border-radius: 10px;">
                            <i class="fas fa-file-download me-2"></i>Download Word Document
                        </a>
                    </div>
                `;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('resumeViewerModal'));
            modal.show();
        }

        // =====================================================================
        // END OF ADVANCED SEARCH & REPORTING CONTROLLER
        // =====================================================================

        // Global Premium Theme Alert Override
        (function() {
            window.alert = function(message, callback) {
                const existing = document.getElementById('customAlertModal');
                if (existing) existing.remove();

                const overlay = document.createElement('div');
                overlay.id = 'customAlertModal';
                overlay.style.cssText = 'position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:99999; display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity 0.22s ease-out; padding:1.5rem;';

                const isLight = document.documentElement.classList.contains('light-mode');

                const box = document.createElement('div');
                box.style.cssText = `background:${isLight ? 'rgba(255,255,255,0.96)' : 'linear-gradient(135deg, #1c1a17 0%, #0d0c0a 100%)'}; border:1px solid ${isLight ? 'rgba(223,186,134,0.35)' : 'rgba(223,186,134,0.25)'}; border-radius:20px; width:100%; max-width:400px; padding:2.2rem 2rem; box-shadow:${isLight ? '0 20px 50px rgba(0,0,0,0.08)' : '0 30px 70px rgba(0,0,0,0.8)'}; transform:scale(0.85); transition:transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1); text-align:center; position:relative;`;

                let iconHtml = '';
                const msgLower = message.toLowerCase();
                if (msgLower.includes('success') || msgLower.includes('booked') || msgLower.includes('✅') || msgLower.includes('settled') || msgLower.includes('opened')) {
                    iconHtml = '<div style="width:58px; height:58px; border-radius:50%; background:rgba(46,196,182,0.1); border:2px solid #2ec4b6; display:inline-flex; align-items:center; justify-content:center; margin-bottom:1.2rem; color:#2ec4b6; font-size:1.6rem;"><i class="fas fa-check"></i></div>';
                } else if (msgLower.includes('error') || msgLower.includes('fail') || msgLower.includes('denied') || msgLower.includes('invalid') || msgLower.includes('please') || msgLower.includes('failed')) {
                    iconHtml = '<div style="width:58px; height:58px; border-radius:50%; background:rgba(239,68,68,0.08); border:2px solid #ef4444; display:inline-flex; align-items:center; justify-content:center; margin-bottom:1.2rem; color:#ef4444; font-size:1.6rem;"><i class="fas fa-exclamation-triangle"></i></div>';
                } else {
                    iconHtml = '<div style="width:58px; height:58px; border-radius:50%; background:rgba(223,186,134,0.08); border:2px solid #dfba86; display:inline-flex; align-items:center; justify-content:center; margin-bottom:1.2rem; color:#dfba86; font-size:1.6rem;"><i class="fas fa-info-circle"></i></div>';
                }

                const cleanMessage = message.replace('✅', '').replace('❌', '').trim();

                box.innerHTML = `
                    ${iconHtml}
                    <div style="font-size:0.95rem; line-height:1.6; color:${isLight ? '#1e293b' : '#f0ece4'}; margin-bottom:1.8rem; font-weight:500; font-family:'Plus Jakarta Sans', sans-serif;">
                        ${cleanMessage}
                    </div>
                    <button id="customAlertOkBtn" style="background:linear-gradient(135deg, #dfba86 0%, #c89640 100%); color:#0a0a0a; border:none; border-radius:10px; padding:0.72rem 2.8rem; font-weight:700; font-size:0.88rem; cursor:pointer; transition:all 0.2s; letter-spacing:0.4px; outline:none; font-family:'Plus Jakarta Sans', sans-serif;">OK</button>
                `;

                overlay.appendChild(box);
                document.body.appendChild(overlay);

                overlay.offsetHeight; // Reflow
                overlay.style.opacity = '1';
                box.style.transform = 'scale(1)';

                const closeAlert = () => {
                    overlay.style.opacity = '0';
                    box.style.transform = 'scale(0.85)';
                    setTimeout(() => {
                        overlay.remove();
                        if (typeof callback === 'function') {
                            callback();
                        }
                    }, 220);
                    window.removeEventListener('keydown', handleKeydown);
                };

                const handleKeydown = (e) => {
                    if (e.key === 'Enter' || e.key === 'Escape') {
                        e.preventDefault();
                        closeAlert();
                    }
                };

                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) closeAlert();
                });
                box.querySelector('#customAlertOkBtn').addEventListener('click', closeAlert);
                window.addEventListener('keydown', handleKeydown);
            };
        })();

        // ==========================================
        // MEDUSA NOTIFICATION SYSTEM CLIENT CONTROLLERS
        // ==========================================
        let notifActiveFilter = 'all';
        let notifSearchTerm = '';
        let notifCurrentPage = 0;
        const notifPageLimit = 15;
        let soundEnabled = localStorage.getItem('medusa_sound_enabled') !== 'false';
        let lastFetchedNotifId = 0;
        let isInitialLoad = true;

        // Custom chime/bell sound synth fallback using Web Audio API
        function playChimeSound() {
            if (!soundEnabled) return;
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                // Play first tone (A5)
                playTone(audioCtx, 880, 0.1, 0.4);
                // Play second tone (C6) slightly delayed and higher
                setTimeout(() => {
                    playTone(audioCtx, 1046.5, 0.1, 0.4);
                }, 120);
            } catch (e) {
                console.warn("Web Audio API not supported or blocked by browser policies: ", e);
            }
        }

        function playSOSAlarm() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                // Urgent repeating alarm: high-low-high pattern
                const pattern = [1200, 800, 1200, 800, 1200];
                pattern.forEach((freq, i) => {
                    setTimeout(() => playTone(audioCtx, freq, 0, 0.18), i * 200);
                });
            } catch (e) {
                console.warn("SOS alarm audio error:", e);
            }
        }

        function playTone(ctx, freq, startTime, duration) {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, ctx.currentTime);
            
            gain.gain.setValueAtTime(0.25, ctx.currentTime);
            // Exponential decay to avoid clicking pops
            gain.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + duration);
            
            osc.start();
            osc.stop(ctx.currentTime + duration);
        }

        // Toggle sound preference dynamically
        function toggleSoundPreference(enabled) {
            soundEnabled = enabled;
            localStorage.setItem('medusa_sound_enabled', enabled ? 'true' : 'false');
            const icon = document.getElementById('soundToggleIcon');
            if (icon) {
                icon.className = enabled ? 'fas fa-volume-up' : 'fas fa-volume-mute';
                icon.style.color = enabled ? 'var(--gold)' : 'var(--gray)';
            }
        }

        // Toggle notification bell dropdown
        function toggleNotificationDropdown(event) {
            if (event) event.stopPropagation();
            const dropdown = document.getElementById('notificationDropdownMenu');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }

        // Close dropdown on click outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notificationDropdownMenu');
            const bellBtn = document.getElementById('notificationBellBtn');
            if (dropdown && dropdown.classList.contains('show')) {
                if (!dropdown.contains(e.target) && (!bellBtn || !bellBtn.contains(e.target))) {
                    dropdown.classList.remove('show');
                }
            }
        });

        // Navigate to notification center tab
        function goToNotificationsTab(event) {
            if (event) event.stopPropagation();
            // Find notifications sidebar tab button
            const sidebarBtn = document.querySelector('.sidebar-link[onclick*="notifications-tab"]');
            if (sidebarBtn) {
                sidebarBtn.click();
            }
            // Close dropdown
            const dropdown = document.getElementById('notificationDropdownMenu');
            if (dropdown) dropdown.classList.remove('show');
        }

        // Toast notifications stacking system
        function showToastNotification(notif, isSOS) {
            let toastContainer = document.getElementById('toastContainerMedusa');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toastContainerMedusa';
                toastContainer.className = 'toast-container-medusa';
                document.body.appendChild(toastContainer);
            }

            const toast = document.createElement('div');
            toast.className = isSOS ? 'toast-medusa toast-medusa-sos' : 'toast-medusa';
            
            const iconClass = getNotifIcon(notif.type, notif.title);
            const colorClass = isSOS ? 'notif-sos-urgent' : getNotifClass(notif.type);

            toast.innerHTML = `
                <div class="notif-icon-circle ${colorClass}" style="width: 34px; height: 34px; font-size: 0.95rem;">
                    <i class="${iconClass}"></i>
                </div>
                <div class="toast-medusa-content">
                    <div class="toast-medusa-title">${escapeHtml(notif.title)}</div>
                    <div class="toast-medusa-body">${escapeHtml(notif.body)}</div>
                </div>
                <button class="toast-medusa-close" onclick="this.parentElement.classList.add('fade-out'); setTimeout(() => this.parentElement.remove(), 300);">&times;</button>
            `;

            toastContainer.appendChild(toast);

            // SOS stays visible for 12 seconds; regular toasts 4 seconds
            const dismissDelay = isSOS ? 12000 : 4000;
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.add('fade-out');
                    setTimeout(() => toast.remove(), 300);
                }
            }, dismissDelay);
        }

        // Fetch paginated history for center page tab
        function fetchNotificationsPage(page) {
            notifCurrentPage = page;
            const offset = page * notifPageLimit;
            
            const tableBody = document.getElementById('notifications-table-body');
            if (!tableBody) return;
            
            tableBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center py-5">
                        <div class="spinner-border text-gold" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </td>
                </tr>
            `;
            
            const url = `../notifications_api.php?action=fetch&filter=${notifActiveFilter}&search=${encodeURIComponent(notifSearchTerm)}&limit=${notifPageLimit}&offset=${offset}`;
            
            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const totalBadge = document.getElementById('notif_total_badge');
                        if (totalBadge) {
                            totalBadge.innerText = `${data.total_count} total`;
                        }
                        
                        renderNotificationsTable(data.notifications);
                        renderNotificationsPagination(data.total_count, page);
                    } else {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="4" class="text-center py-4 text-danger">
                                    Error loading notifications: ${escapeHtml(data.message)}
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(err => {
                    console.error("Error fetching notifications page:", err);
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center py-4 text-danger">
                                Failed to fetch notifications history from server.
                            </td>
                        </tr>
                    `;
                });
        }

        // Render notifications inside center page table
        function renderNotificationsTable(notifications) {
            const tableBody = document.getElementById('notifications-table-body');
            if (!tableBody) return;
            
            if (notifications.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-5">
                            <div class="notif-empty-state">
                                <div class="notif-empty-icon"><i class="fas fa-bell-slash"></i></div>
                                <div class="notif-empty-title">No notifications found</div>
                                <div class="notif-empty-desc">No events match your search or filter options.</div>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            notifications.forEach(n => {
                const isSOS = n.type === 'system' && n.title && n.title.toUpperCase().includes('SOS');
                const iconClass = getNotifIcon(n.type, n.title);
                const colorClass = isSOS ? 'notif-sos-urgent' : getNotifClass(n.type);
                const rowClass = n.is_read == 1 ? 'read-row' : 'unread-row';
                const formattedTime = formatDateTime(n.created_at);
                
                html += `
                    <tr class="notif-row ${rowClass}" data-id="${n.id}">
                        <td>
                            <div class="notif-icon-circle ${colorClass}">
                                <i class="${iconClass}"></i>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold notif-row-title">${escapeHtml(n.title)}</div>
                            <div class="text-muted small">${escapeHtml(n.body)}</div>
                        </td>
                        <td>
                            <span class="text-muted small">${formattedTime}</span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-2 justify-content-end">
                                ${n.is_read == 0 ? `
                                <button class="btn btn-sm btn-outline-success p-1 px-2" onclick="markNotifRead(${n.id}, event)" title="Mark as Read" style="border-color: rgba(46, 196, 182, 0.4); color: #2ec4b6; background: transparent;">
                                    <i class="fas fa-check"></i>
                                </button>` : ''}
                                <button class="btn btn-sm btn-outline-danger p-1 px-2" onclick="deleteNotification(${n.id}, event)" title="Delete" style="border-color: rgba(235, 94, 85, 0.4); color: #eb5e55; background: transparent;">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        }

        // Render pagination links for center page tab
        function renderNotificationsPagination(totalCount, currentPage) {
            const paginationInfo = document.getElementById('notif_pagination_info');
            const paginationList = document.getElementById('notif_pagination_list');
            
            if (!paginationInfo || !paginationList) return;
            
            const startIdx = totalCount === 0 ? 0 : currentPage * notifPageLimit + 1;
            const endIdx = Math.min((currentPage + 1) * notifPageLimit, totalCount);
            
            paginationInfo.innerText = `Showing ${startIdx} to ${endIdx} of ${totalCount} entries`;
            
            const totalPages = Math.ceil(totalCount / notifPageLimit);
            
            if (totalPages <= 1) {
                paginationList.innerHTML = '';
                return;
            }
            
            const isLight = document.documentElement.classList.contains('light-mode');
            const pageLinkClass = isLight ? 'bg-light text-dark border-secondary' : 'bg-dark text-white border-secondary';
            
            let html = '';
            
            // Previous link
            const prevDisabled = currentPage === 0 ? 'disabled' : '';
            html += `
                <li class="page-item ${prevDisabled}">
                    <a class="page-link ${pageLinkClass}" href="javascript:void(0)" onclick="${currentPage > 0 ? `fetchNotificationsPage(${currentPage - 1})` : ''}" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            `;
            
            // Page numbers link
            for (let i = 0; i < totalPages; i++) {
                const activeClass = i === currentPage ? 'active' : '';
                const linkStyle = i === currentPage ? 'background-color: var(--gold) !important; border-color: var(--gold) !important; color: #000 !important; font-weight: bold;' : '';
                const currentLinkClass = i === currentPage ? '' : pageLinkClass;
                
                html += `
                    <li class="page-item ${activeClass}">
                        <a class="page-link ${currentLinkClass}" style="${linkStyle}" href="javascript:void(0)" onclick="fetchNotificationsPage(${i})">${i + 1}</a>
                    </li>
                `;
            }
            
            // Next link
            const nextDisabled = currentPage === totalPages - 1 ? 'disabled' : '';
            html += `
                <li class="page-item ${nextDisabled}">
                    <a class="page-link ${pageLinkClass}" href="javascript:void(0)" onclick="${currentPage < totalPages - 1 ? `fetchNotificationsPage(${currentPage + 1})` : ''}" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            `;
            
            paginationList.innerHTML = html;
        }

        // Set active filters on click
        function setNotifFilter(filter) {
            notifActiveFilter = filter;
            const buttons = document.querySelectorAll('#notif_filter_buttons_container .notif-filter-btn');
            buttons.forEach(btn => {
                if (btn.getAttribute('data-filter') === filter) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            fetchNotificationsPage(0);
        }

        // Debounced search keyup
        let notifSearchTimeout = null;
        function handleNotifSearchInput(event) {
            notifSearchTerm = event.target.value;
            clearTimeout(notifSearchTimeout);
            notifSearchTimeout = setTimeout(() => {
                fetchNotificationsPage(0);
            }, 300);
        }

        // Action: Mark single read
        function markNotifRead(id, event) {
            if (event) event.stopPropagation();
            fetch(`../notifications_api.php?action=mark_read&id=${id}`, { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        pollNotifications();
                        // If notifications-tab is active, reload current page
                        const notifTab = document.getElementById('notifications-tab');
                        if (notifTab && notifTab.classList.contains('active')) {
                            fetchNotificationsPage(notifCurrentPage);
                        }
                    }
                })
                .catch(err => console.error("Error marking read:", err));
        }

        // Action: Mark all read
        function markAllNotificationsRead(event) {
            if (event) event.stopPropagation();
            fetch(`../notifications_api.php?action=mark_all_read`, { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        pollNotifications();
                        const notifTab = document.getElementById('notifications-tab');
                        if (notifTab && notifTab.classList.contains('active')) {
                            fetchNotificationsPage(notifCurrentPage);
                        }
                    }
                })
                .catch(err => console.error("Error marking all read:", err));
        }

        // Action: Delete notification
        function deleteNotification(id, event) {
            if (event) event.stopPropagation();
            if (confirm("Are you sure you want to delete this notification?")) {
                fetch(`../notifications_api.php?action=delete&id=${id}`, { method: 'POST' })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            pollNotifications();
                            const notifTab = document.getElementById('notifications-tab');
                            if (notifTab && notifTab.classList.contains('active')) {
                                fetchNotificationsPage(notifCurrentPage);
                            }
                        }
                    })
                    .catch(err => console.error("Error deleting notification:", err));
            }
        }

        // Handle dropdown item action routing based on type
        function handleDropdownItemClick(id, type, event) {
            if (event) event.stopPropagation();
            
            // Mark read immediately
            fetch(`../notifications_api.php?action=mark_read&id=${id}`, { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    pollNotifications();
                    
                    // Route to correct tab
                    if (type === 'order') {
                        const tabBtn = document.querySelector('.sidebar-link[onclick*="orders-tab"]');
                        if (tabBtn) tabBtn.click();
                    } else if (type === 'reservation') {
                        const tabBtn = document.querySelector('.sidebar-link[onclick*="tables-tab"]');
                        if (tabBtn) tabBtn.click();
                    } else {
                        // Default to notification center
                        goToNotificationsTab();
                    }
                })
                .catch(err => console.error("Error clicking dropdown item:", err));

            // Hide dropdown menu
            const dropdown = document.getElementById('notificationDropdownMenu');
            if (dropdown) dropdown.classList.remove('show');
        }

        // Populate badges and bell dropdown content
        function updateNotificationsDropdown(notifications, unreadCount) {
            const badge = document.getElementById('notificationBadge');
            if (badge) {
                if (unreadCount > 0) {
                    badge.innerText = unreadCount > 99 ? '99+' : unreadCount;
                    badge.style.display = 'flex';
                    // Trigger dynamic bounce animation on bell
                    const bellIcon = document.querySelector('#notificationBellBtn i');
                    if (bellIcon) {
                        bellIcon.classList.add('fa-bounce');
                        setTimeout(() => bellIcon.classList.remove('fa-bounce'), 1000);
                    }
                } else {
                    badge.style.display = 'none';
                }
            }

            const dropdownList = document.getElementById('dropdownNotificationList');
            if (!dropdownList) return;

            if (notifications.length === 0) {
                dropdownList.innerHTML = `
                    <div class="notif-empty-state">
                        <div class="notif-empty-icon"><i class="fas fa-bell-slash"></i></div>
                        <div class="notif-empty-title">All caught up!</div>
                        <div class="notif-empty-desc">No new notifications.</div>
                    </div>
                `;
                return;
            }

            let html = '';
            notifications.forEach(n => {
                const isSOS = n.type === 'system' && n.title && n.title.toUpperCase().includes('SOS');
                const iconClass = getNotifIcon(n.type, n.title);
                const colorClass = isSOS ? 'notif-sos-urgent' : getNotifClass(n.type);
                const unreadClass = n.is_read == 0 ? 'unread' : '';
                const timeStr = formatRelativeTime(n.created_at);

                html += `
                    <div class="notification-item ${unreadClass}" onclick="handleDropdownItemClick(${n.id}, '${n.type}', event)">
                        <div class="notif-icon-circle ${colorClass}">
                            <i class="${iconClass}"></i>
                        </div>
                        <div class="notif-details">
                            <div class="notif-title-row">
                                <span class="notif-title-text">${escapeHtml(n.title)}</span>
                                <span class="notif-time">${timeStr}</span>
                            </div>
                            <p class="notif-body-text">${escapeHtml(n.body)}</p>
                        </div>
                        ${n.is_read == 0 ? '<span class="notif-unread-dot"></span>' : ''}
                    </div>
                `;
            });
            dropdownList.innerHTML = html;
        }

        // Polling controller
        function pollNotifications() {
            fetch(`../notifications_api.php?action=fetch&limit=6`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Handle sound chime and toasts for new arrivals
                        if (!isInitialLoad) {
                            const newNotifs = data.notifications.filter(n => n.id > lastFetchedNotifId);
                            if (newNotifs.length > 0) {
                                // Detect SOS alerts first (highest priority)
                                const sosNotifs = newNotifs.filter(n => n.type === 'system' && n.title && n.title.toUpperCase().includes('SOS'));
                                const orderNotifs = newNotifs.filter(n => n.type === 'order');

                                if (sosNotifs.length > 0) {
                                    // Urgent SOS alarm
                                    playSOSAlarm();
                                    sosNotifs.forEach(n => showToastNotification(n, true));
                                }

                                if (orderNotifs.length > 0) {
                                    playChimeSound();
                                }
                                
                                // Slide-in toast for all other new notifications
                                newNotifs.filter(n => !(n.type === 'system' && n.title && n.title.toUpperCase().includes('SOS')))
                                    .forEach(n => showToastNotification(n, false));

                                // Reload page history in case center tab is actively shown
                                const notifTab = document.getElementById('notifications-tab');
                                if (notifTab && notifTab.classList.contains('active')) {
                                    fetchNotificationsPage(notifCurrentPage);
                                }
                            }
                        }

                        // Maintain max tracked ID
                        if (data.notifications.length > 0) {
                            const maxId = Math.max(...data.notifications.map(n => n.id));
                            lastFetchedNotifId = Math.max(lastFetchedNotifId, maxId);
                        }
                        
                        isInitialLoad = false;
                        
                        // Populate badges and bell dropdown content
                        updateNotificationsDropdown(data.notifications, data.unread_count);
                    }
                })
                .catch(err => console.error("Error polling notifications:", err));
        }

        // Helper formatting functions
        function getNotifIcon(type, title) {
            if (type === 'system' && title && title.toUpperCase().includes('SOS')) {
                return 'fas fa-triangle-exclamation';
            }
            switch (type) {
                case 'order': return 'fas fa-receipt';
                case 'payment': return 'fas fa-wallet';
                case 'kitchen': return 'fas fa-fire-burner';
                case 'reservation': return 'fas fa-chair';
                case 'staff': return 'fas fa-user-tie';
                case 'system': return 'fas fa-cogs';
                default: return 'fas fa-bell';
            }
        }

        function getNotifClass(type) {
            return 'notif-' + type;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, "&amp;")
                      .replace(/</g, "&lt;")
                      .replace(/>/g, "&gt;")
                      .replace(/"/g, "&quot;")
                      .replace(/'/g, "&#039;");
        }

        function formatRelativeTime(dateStr) {
            const date = new Date(dateStr.replace(/-/g, '/'));
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return diffMins + 'm ago';
            
            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return diffHours + 'h ago';
            
            const diffDays = Math.floor(diffHours / 24);
            if (diffDays === 1) return 'Yesterday';
            if (diffDays < 7) return diffDays + 'd ago';
            
            return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        }

        function formatDateTime(dateStr) {
            const date = new Date(dateStr.replace(/-/g, '/'));
            return date.toLocaleString(undefined, { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }

        // Liquor Quota functions
        let verifiedUserId = null;

        function showToast(message, type = 'success') {
            showToastNotification({
                type: type === 'success' ? 'payment' : (type === 'error' ? 'system' : 'staff'),
                title: type.charAt(0).toUpperCase() + type.slice(1),
                body: message
            }, false);
        }

        function loadActiveQuotas() {
            const body = document.getElementById('activeQuotasTableBody');
            if (!body) return;
            body.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i> Loading...</td></tr>`;

            fetch('dashboardtest.php?action=load_active_quotas')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (data.quotas.length === 0) {
                            body.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">No active customer quotas found.</td></tr>`;
                            return;
                        }
                        let html = '';
                        data.quotas.forEach(q => {
                            const total_pegs = parseInt(q.total_pegs);
                            const bottles = Math.floor(total_pegs / 8);
                            const pegs = total_pegs % 8;
                            html += `
                                <tr>
                                    <td>
                                        <strong>${escapeHtml(q.user_name)}</strong><br>
                                        <small class="text-muted">${escapeHtml(q.user_phone || q.user_email)}</small>
                                    </td>
                                    <td><span class="text-gold font-weight-bold">${escapeHtml(q.item_name)}</span></td>
                                    <td class="text-center"><strong>${bottles}</strong></td>
                                    <td class="text-center"><strong>${pegs}</strong></td>
                                    <td class="text-center">
                                        <button class="btn btn-gold-action btn-sm" onclick="selectQuotaForConsume('${escapeHtml(q.user_name)}', ${bottles > 0 || pegs > 0}, ${q.food_item_id})">
                                            <i class="fas fa-glass-water me-1"></i> Log Consume
                                        </button>
                                    </td>
                                </tr>
                            `;
                        });
                        body.innerHTML = html;
                    } else {
                        body.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${escapeHtml(data.message)}</td></tr>`;
                    }
                })
                .catch(err => {
                    console.error(err);
                    body.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">Network error loading quotas.</td></tr>`;
                });
        }

        function selectQuotaForConsume(customerName, hasQuota, brandId = null) {
            document.getElementById('consume_search_term').value = customerName;
            document.getElementById('consume_brand_id').innerHTML = '<option value="">-- Verifying... --</option>';
            document.getElementById('btn-admin-consume').disabled = true;
            verifiedUserId = null;
            
            showToast(`Selected ${customerName}. Verifying active quota...`, 'info');
            loadCustomerBrands(brandId); // Auto load it!
        }

        function loadCustomerBrands(autoSelectBrandId = null) {
            const searchTerm = document.getElementById('consume_search_term').value.trim();
            const brandSelect = document.getElementById('consume_brand_id');
            const brandSection = document.getElementById('consume_brand_section');
            const btnVerify = document.getElementById('btn-admin-verify');

            if (!searchTerm) {
                showToast('Please enter a search term.', 'error');
                return;
            }

            const origText = btnVerify.innerHTML;
            btnVerify.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Searching...';
            btnVerify.disabled = true;

            brandSection.style.display = 'none';
            document.getElementById('btn-admin-consume').disabled = true;
            verifiedUserId = null;

            const formData = new FormData();
            formData.append('search_term', searchTerm);

            fetch('dashboardtest.php?action=verify_order_liquor', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btnVerify.innerHTML = origText;
                btnVerify.disabled = false;

                if (data.success) {
                    verifiedUserId = data.user_id;
                    let html = '<option value="">-- Choose Brand --</option>';
                    data.brands.forEach(b => {
                        const total_pegs = parseInt(b.total_pegs || 0);
                        html += `<option value="${b.food_item_id}">${escapeHtml(b.item_name)} (${total_pegs} pegs left)</option>`;
                    });
                    brandSelect.innerHTML = html;
                    
                    // Auto-select the brand if requested
                    if (autoSelectBrandId) {
                        brandSelect.value = autoSelectBrandId;
                    }
                    
                    // Reveal the hidden selection box and consume button
                    brandSection.style.display = 'block';
                    document.getElementById('btn-admin-consume').disabled = false;
                    
                    showToast('Customer verified successfully! Please select a brand to log peg consumption.', 'success');
                } else {
                    brandSelect.innerHTML = '<option value="">Verification Failed</option>';
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                btnVerify.innerHTML = origText;
                btnVerify.disabled = false;
                brandSelect.innerHTML = '<option value="">Error verifying customer</option>';
                showToast('Network error verifying customer.', 'error');
            });
        }

        function adminConsumePeg(e) {
            e.preventDefault();
            const searchTerm = document.getElementById('consume_search_term').value.trim();
            const brandId = document.getElementById('consume_brand_id').value;
            const btn = document.getElementById('btn-admin-consume');

            if (!verifiedUserId || !brandId || !searchTerm) {
                showToast('Please verify customer first.', 'error');
                return;
            }

            const origText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            const formData = new FormData();
            formData.append('user_id', verifiedUserId);
            formData.append('food_item_id', brandId);
            formData.append('search_term', searchTerm);

            fetch('dashboardtest.php?action=admin_consume_peg', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    // Reset form select & verify state
                    document.getElementById('consume_brand_id').innerHTML = '<option value="">-- Click Verify to load brands --</option>';
                    document.getElementById('btn-admin-consume').disabled = true;
                    verifiedUserId = null;
                    document.getElementById('consumePegForm').reset();
                    // Reload quotas list
                    loadActiveQuotas();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Network error logging peg.', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = origText;
            });
        }

        // Initialize notification elements on DOM load
        document.addEventListener('DOMContentLoaded', () => {
            // Set sound switch state and icons
            const toggleInput = document.getElementById('notificationSoundToggle');
            if (toggleInput) {
                toggleInput.checked = soundEnabled;
                toggleSoundPreference(soundEnabled);
            }
            
            // Start AJAX Polling
            pollNotifications();
            setInterval(pollNotifications, 15000);

            // Set up image dropzone drag & drop events
            setupImageDragAndDrop();
        });
    