<?php 
        // Helper function to render table rows
        $csrf_token = generateCsrfToken(); // Generate token once for the forms

        function renderOrderRows($orders, $is_super, $csrf_token) {
            if (empty($orders)) {
                echo '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No orders found.</td></tr>';
                return;
            }
            foreach ($orders as $order) {
                $statusClass = match($order['status']) {
                    'active' => 'bg-green-100 text-green-800',
                    'payment_submitted' => 'bg-yellow-100 text-yellow-800',
                    default => 'bg-gray-100 text-gray-800',
                };
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-bold text-gray-900">#<?= $order['id'] ?></td>
                    <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($order['email']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-500"><?= date('M d, Y', strtotime($order['order_date'] ?? 'now')) ?></td>
                    <td class="px-6 py-4 font-bold text-gray-800">
                        ₦<?= number_format($order['display_total'], 2) ?>
                        <?php if(!$is_super): ?><span class="text-xs font-normal text-gray-400 block">(Your Share)</span><?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full font-bold <?= $statusClass ?>">
                            <?= strtoupper(str_replace('_', ' ', $order['status'])) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <?php if($is_super && $order['status'] === 'payment_submitted'): ?>
                             <form method="POST" action="approve_order.php" onsubmit="return confirm('Are you sure you want to approve this order?');" class="inline">
                                 <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                 <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                 <button type="submit" class="text-green-600 hover:text-green-800 font-bold text-sm bg-green-50 px-3 py-1 rounded border border-green-200">Approve</button>
                             </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
        }
        ?>

        <div x-show="activeTab === 'pending'" class="p-0"><table class="w-full text-left"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-6 py-3">Order ID</th><th class="px-6 py-3">Customer</th><th class="px-6 py-3">Date</th><th class="px-6 py-3">Total</th><th class="px-6 py-3">Status</th><th class="px-6 py-3"></th></tr></thead><tbody class="divide-y divide-gray-100"><?php renderOrderRows($pendingApprovalOrders, $is_super, $csrf_token); ?></tbody></table></div>
        
        <div x-show="activeTab === 'active'" class="p-0"><table class="w-full text-left"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-6 py-3">Order ID</th><th class="px-6 py-3">Customer</th><th class="px-6 py-3">Date</th><th class="px-6 py-3">Total</th><th class="px-6 py-3">Status</th><th class="px-6 py-3"></th></tr></thead><tbody class="divide-y divide-gray-100"><?php renderOrderRows($activeOrders, $is_super, $csrf_token); ?></tbody></table></div>
        
        <div x-show="activeTab === 'all'" class="p-0"><table class="w-full text-left"><thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-6 py-3">Order ID</th><th class="px-6 py-3">Customer</th><th class="px-6 py-3">Date</th><th class="px-6 py-3">Total</th><th class="px-6 py-3">Status</th><th class="px-6 py-3"></th></tr></thead><tbody class="divide-y divide-gray-100"><?php renderOrderRows($allOrders, $is_super, $csrf_token); ?></tbody></table></div>