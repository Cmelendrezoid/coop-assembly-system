                        <?php 
                        try {
                            $filter_date = $_GET['filter_date'] ?? '';
                            
                            if (!empty($filter_date)) {
                                $recent_arrivals = $pdo->query("
                                    SELECT m.id, m.full_name, m.migs_category, m.allowance_claimed_at,
                                    GROUP_CONCAT(c.item_name SEPARATOR ', ') AS items_claimed
                                    FROM members m
                                    LEFT JOIN member_freebie_claims c ON m.id = c.member_id
                                    WHERE m.allowance_claimed = 1 AND DATE(m.allowance_claimed_at) = '" . $pdo->quote($filter_date) . "'
                                    GROUP BY m.id
                                    ORDER BY m.allowance_claimed_at DESC 
                                    LIMIT 20
                                ")->fetchAll();
                            } else {
                                $recent_arrivals = $pdo->query("
                                    SELECT m.id, m.full_name, m.migs_category, m.allowance_claimed_at,
                                    GROUP_CONCAT(c.item_name SEPARATOR ', ') AS items_claimed
                                    FROM members m
                                    LEFT JOIN member_freebie_claims c ON m.id = c.member_id
                                    WHERE m.allowance_claimed = 1
                                    GROUP BY m.id
                                    ORDER BY m.allowance_claimed_at DESC 
                                    LIMIT 20
                                ")->fetchAll();
                            }
                            
                            if ($recent_arrivals):
                                foreach ($recent_arrivals as $arrival):
                        ?>
                            <tr>
                                <td><strong>#<?= $arrival['id'] ?></strong></td>
                                <td style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($arrival['full_name']) ?></td>
                                <td><span class="badge badge-success"><?= htmlspecialchars(!empty($arrival['migs_category']) ? $arrival['migs_category'] : 'REGULAR') ?></span></td>
                                <td><span class="badge badge-success">✓ Claimed & Attended</span><br><small style="color: var(--text-muted);"><?= htmlspecialchars($arrival['items_claimed'] ?: 'No items selected') ?></small></td>
                                <td style="font-size: 12px; color: var(--text-muted);"><?= date('Y-m-d H:i:s', strtotime($arrival['allowance_claimed_at'])) ?></td>
                            </tr>
                        <?php 
                                endforeach;
                            else:
                        ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No arrivals or freebie claims recorded yet.</td></tr>
                        <?php 
                            endif;
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='5' style='color: #991b1b;'>Error fetching attendees: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
            const scanInput = document.getElementById('gate-scan-input');
            const scanForm = document.getElementById('gate-scan-form');

            if (scanForm) {
                scanForm.addEventListener('submit', function(e) {
                    if (!scanInput || scanInput.value.trim() === '') {
                        e.preventDefault();
                        if (scanInput) scanInput.focus();
                    }
                });
            }

            if (scanForm) {
                scanForm.addEventListener('submit', function(e) {
                    if (!scanInput || scanInput.value.trim() === '') {
                        e.preventDefault();
                        if (scanInput) scanInput.focus();
                    }
                });
            }

            if (scanInput) {
                scanInput.focus();
                
                document.addEventListener('click', function(e) {
                    if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'A' && !e.target.classList.contains('item-chk')) {
                        scanInput.focus();
                    }
                });
            }
        </script>
    <?php endif; ?>
</div>

<!-- ========================================================================= -->
<!-- 3. RUNTIME THERMAL PRINT RUN STAGE                                        -->
<!-- ========================================================================= -->
<?php 
if (!empty($print_target_id)): 
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$print_target_id]);
    $print_member = $stmt->fetch();
    
    if ($print_member):
        $raw_category = $print_member['migs_category'] ?? $print_member['category'] ?? $print_member['migs_status'] ?? '';
        $migs_category = strtoupper(trim((string)$raw_category));

        $claims_stmt = $pdo->prepare("SELECT item_name FROM member_freebie_claims WHERE member_id = ?");
        $claims_stmt->execute([$print_target_id]);
        $claimed_items_db = $claims_stmt->fetchAll(PDO::FETCH_COLUMN);

        $gate_qr_url = "gate_id=" . $print_member['id']; 
        $gate_qr_file = 'temp_qr_gate_' . $print_member['id'] . '.png';
        QRcode::png($gate_qr_url, $gate_qr_file, QR_ECLEVEL_M, 4, 2);
?>
    <div id="thermal-receipt-view">
        
        <?php if ($is_duplicate): ?>
            <div class="duplicate-notice">⚠️ DUPLICATE COPY - ALREADY CLAIMED</div>
        <?php endif; ?>

        <?php if ($route === 'registration'): ?>
            <div class="receipt-header">GENERAL ASSEMBLY</div>
            <div style="font-size: 11px; font-weight: bold; letter-spacing: 1px; text-align: center; color: #000;">PRE-REGISTRATION QR PASS</div>
            <div class="receipt-divider"></div>
            
            <table class="receipt-details-table">
                <tr>
                    <td style="width: 45%;"><strong>MEMBER ID:</strong></td>
                    <td><?= htmlspecialchars($print_member['id']) ?></td>
                </tr>
                <tr>
                    <td><strong>NAME:</strong></td>
                    <td><?= htmlspecialchars($print_member['full_name']) ?></td>
                </tr>
                <tr>
                    <td><strong>CATEGORY:</strong></td>
                    <td><?= htmlspecialchars(!empty($migs_category) ? $migs_category : 'REGULAR') ?></td>
                </tr>
            </table>
            
            <div class="receipt-divider"></div>
            <?php
                $claimed_items_db = array_filter(array_map('trim', array_unique($claimed_items_db)));
            ?>
            <?php if (!empty($claimed_items_db)): ?>
                <div style="font-size: 11px; font-weight: 700; margin-bottom: 8px; text-align: left; width: 100%; color: #000;">Pre-Registration Freebies</div>
                <div class="freebie-container">
                    <?php foreach ($claimed_items_db as $item): ?>
                        <div class="freebie-row">
                            <div class="freebie-chk-box"></div>
                            <div class="freebie-label"><?= htmlspecialchars($item) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="receipt-divider"></div>
            <?php else: ?>
                <div style="font-size: 10px; color: #000; margin-bottom: 10px; text-align: center;">No pre-registration freebies were selected.</div>
            <?php endif; ?>
            <div style="font-size: 9px; font-weight: bold; margin-bottom: 2px; text-align: center; color: #000;">PRESENT THIS AT TERMINAL B</div>
            
            <div class="qr-wrapper">
                <img src="<?= $gate_qr_file ?>?v=<?= time() ?>" alt="Entrance QR Pass">
            </div>
            <div style="font-size: 8px; color: #000; text-align: center;">Scan at Terminal B to verify entrance and claim freebies.</div>

        <?php else: ?>
            <div class="receipt-header">GENERAL ASSEMBLY</div>
            <div style="font-size: 11px; font-weight: bold; letter-spacing: 1px; text-align: center; color: #000;">
                <?= $is_duplicate ? 'ATTENDANCE RECEIPT (REPRINT)' : 'ATTENDANCE & FREEBIES CLAIMED' ?>
            </div>
            <div class="receipt-divider"></div>
            
            <table class="receipt-details-table">
                <tr>
                    <td style="width: 45%;"><strong>MEMBER ID:</strong></td>
                    <td><?= htmlspecialchars($print_member['id']) ?></td>
                </tr>
                <tr>
                    <td><strong>NAME:</strong></td>
                    <td><?= htmlspecialchars($print_member['full_name']) ?></td>
                </tr>
                <tr>
                    <td><strong>CATEGORY:</strong></td>
                    <td><?= htmlspecialchars(!empty($migs_category) ? $migs_category : 'REGULAR') ?></td>
                </tr>
            </table>
            
            <div class="receipt-divider"></div>
            <div style="font-size: 10px; font-weight: bold; margin-bottom: 3px; text-align: left; width: 100%; color: #000;">🔐 E-VOTING CREDENTIALS:</div>
            
            <div class="credential-box">
                <strong>Username:</strong> <?= htmlspecialchars($print_member['username'] ?? 'None Assigned') ?><br>
                <strong>Password:</strong> <?= htmlspecialchars($print_member['password'] ?? 'None Assigned') ?>
            </div>

            <!-- EXCLUDE FREEBIES ON DUPLICATE / RE-SCAN -->
            <?php if (!$is_duplicate): ?>
                <div class="receipt-divider"></div>
                <div style="font-size: 11px; font-weight: bold; margin-bottom: 4px; text-align: center; width: 100%; color: #000;">🎁 ISSUED FREEBIES & ALLOWANCE</div>

                <?php
                    $claimed_items_db = array_filter(array_map('trim', array_unique($claimed_items_db)));
                ?>
                <?php if (!empty($claimed_items_db)): ?>
                    <div class="freebie-container">
                        <?php foreach ($claimed_items_db as $item): ?>
                            <div class="freebie-row">
                                <div class="freebie-chk-box"></div>
                                <div class="freebie-label"><?= htmlspecialchars($item) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="font-size: 10px; color: #000; margin-bottom: 10px; text-align: center;">No freebies were recorded for this member.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Auto-Print Trigger -->
<?php if (!empty($print_target_id)): ?>
<script>
    window.addEventListener('load', function() {
        setTimeout(function() {
            window.print();
        }, 400);
    });
</script>
<?php endif; ?>

<!-- Theme & Date Filter Script -->
<script>
    function filterByDate() {
        const dateInput = document.getElementById('date-filter').value;
        if (!dateInput) {
            alert('Please select a date');
            return;
        }
        window.location.href = 'index.php?route=gate&filter_date=' + encodeURIComponent(dateInput);
    }
    
    function clearDateFilter() {
        window.location.href = 'index.php?route=gate';
    }
    
    <?php if ($route === 'gate'): ?>
    setInterval(function() {
        fetch('index.php?route=gate&action=get_attendance_data')
            .then(response => response.text())
            .then(data => {
                document.getElementById('attendance-table-body').innerHTML = data;
            })
            .catch(error => console.log('Real-time update check...'));
    }, 5000);
    <?php endif; ?>

    const toggleBtn = document.getElementById('theme-toggle');
    const currentTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    
    if (currentTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        if(toggleBtn) toggleBtn.textContent = '☀️ Light Mode';
    }

    if(toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            let theme = document.documentElement.getAttribute('data-theme');
            if (theme === 'dark') {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('theme', 'light');
                toggleBtn.textContent = '🌙 Dark Mode';
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                toggleBtn.textContent = '☀️ Light Mode';
            }
        });
    }
</script>

</body>
</html>