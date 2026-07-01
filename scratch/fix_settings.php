<?php
$file = 'd:/New folder/htdocs/restaurant_medusa/settings.php';
$content = file_get_contents($file);

$pattern = '/<!-- New: Login Alerts -->.*?<!-- Submit feedback form -->/s';
$replacement = '<!-- New: Login Alerts -->
                            <div class="bg-white p-4 rounded-4 border mb-4" style="border-color: rgba(0,0,0,0.05) !important;">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h4 class="text-dark mb-1" style="font-size: 1.1rem; font-weight: 600;">Login Alerts</h4>
                                        <p class="text-muted m-0" style="font-size: 0.85rem;">Get notified of unrecognized logins.</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="login_alerts_toggle" checked>
                                    </div>
                                </div>
                            </div>

                            <!-- New: Trusted Devices -->
                            <div class="bg-white p-4 rounded-4 border mb-4" style="border-color: rgba(0,0,0,0.05) !important;">
                                <h4 class="text-dark mb-3" style="font-size: 1.1rem; font-weight: 600;">Trusted Devices</h4>
                                <ul class="list-group list-group-flush mb-3">
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 border-0">
                                        <div>
                                            <div style="font-size: 0.95rem; font-weight: 500;">iPhone 14 Pro Max</div>
                                            <small class="text-muted">Currently active</small>
                                        </div>
                                        <span class="badge bg-success bg-opacity-10 text-success">Active</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 border-top">
                                        <div>
                                            <div style="font-size: 0.95rem; font-weight: 500;">MacBook Pro (Chrome)</div>
                                            <small class="text-muted">Last used 2 days ago</small>
                                        </div>
                                        <button class="btn btn-sm btn-outline-danger">Revoke</button>
                                    </li>
                                </ul>
                            </div>

                            <!-- New: Account Recovery -->
                            <div class="bg-white p-4 rounded-4 border" style="border-color: rgba(0,0,0,0.05) !important;">
                                <h4 class="text-dark mb-3" style="font-size: 1.1rem; font-weight: 600;">Account Recovery</h4>
                                <p class="text-muted mb-3" style="font-size: 0.85rem;">Add a fallback email in case you lose access.</p>
                                <div class="input-group">
                                    <input type="email" class="form-control" placeholder="Recovery Email" style="background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.1);">
                                    <button class="btn btn-dark" type="button">Save</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Login Sessions -->
                    <div class="bg-white p-4 rounded-4 border mt-4" style="border-color: rgba(0,0,0,0.05) !important;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="text-dark m-0" style="font-size: 1.1rem; font-weight: 600;">Recent Login Sessions</h4>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="logoutOtherDevices()">Logout Other Devices</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" style="font-size: 0.85rem; border: 1px solid rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden;">
                                <thead style="background: rgba(0,0,0,0.02);">
                                    <tr>
                                        <th class="text-muted">IP Address</th>
                                        <th class="text-muted">Device / Browser</th>
                                        <th class="text-muted">Timestamp</th>
                                        <th class="text-muted">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($login_logs)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No logs found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($login_logs as $log): ?>
                                            <tr>
                                                <td class="text-dark" style="font-family: monospace;">
                                                    <?php echo htmlspecialchars($log[\'ip_address\'] === \'::1\' || $log[\'ip_address\'] === \'127.0.0.1\' ? \'Localhost\' : $log[\'ip_address\']); ?>
                                                </td>
                                                <td class="text-muted" title="<?php echo htmlspecialchars($log[\'user_agent\']); ?>">
                                                    <?php 
                                                        $ua = $log[\'user_agent\'];
                                                        if (preg_match(\'/(Chrome|Safari|Firefox|Edge|MSIE|Trident|Opera)/i\', $ua, $matches)) {
                                                            echo $matches[0];
                                                        } else {
                                                            echo "Browser";
                                                        }
                                                        echo (strpos(strtolower($ua), \'mobile\') !== false) ? " (Mobile)" : " (Desktop)";
                                                    ?>
                                                </td>
                                                <td class="text-muted"><?php echo date(\'d M Y, H:i:s\', strtotime($log[\'login_time\'])); ?></td>
                                                <td>
                                                    <span class="badge bg-success bg-opacity-10 text-success mb-1">Success</span>
                                                    <br><a href="api/logout.php" class="text-danger text-decoration-underline" style="font-size: 0.75rem;">Not you? Logout</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ══ TAB 6: CUSTOMER FEEDBACK ══ -->
                <div class="tab-pane fade" id="pill-feedback" role="tabpanel">
                    <h2 class="section-title"><i class="fa-solid fa-star"></i> Customer Feedback & Reviews</h2>
                    
                    <div class="row g-4">
                        <!-- Submit feedback form -->';

$content = preg_replace($pattern, $replacement, $content);
file_put_contents($file, $content);
echo "Replaced successfully!\n";
?>
