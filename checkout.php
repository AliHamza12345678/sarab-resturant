<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/customer_auth.php';
require_once __DIR__ . '/config/cache.php';

if (isset($_POST['place_order_btn'])) {
    verify_csrf();

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $allowed_payment_methods = ['Cash on Delivery', 'Card (Simulation)'];
    if (!in_array($payment_method, $allowed_payment_methods, true)) {
        $payment_method = 'Cash on Delivery';
    }

    $cart_data_raw = $_POST['cart_data'] ?? '';
    $cart_items = json_decode($cart_data_raw, true);
    $error = "";

    if (empty($full_name) || empty($email) || empty($phone) || empty($address)) {
        $error = "Please fill in all customer details.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (empty($cart_items) || !is_array($cart_items)) {
        $error = "Your cart is empty. Please add items before checking out.";
    } else {
        // SECURITY: never trust prices/titles sent by the client. Re-fetch every
        // item's real price and title from the database using only the item id
        // and requested quantity from the cart.
        $verified_items = [];
        $total_price = 0;

        foreach ($cart_items as $item) {
            $item_id  = isset($item['id']) ? intval($item['id']) : 0;
            $item_qty = isset($item['quantity']) ? intval($item['quantity']) : 0;

            if ($item_id <= 0 || $item_qty <= 0 || $item_qty > 50) {
                continue; // skip invalid/tampered entries
            }

            $stmt = mysqli_prepare($conn, "SELECT id, title, price FROM menu_items WHERE id = ? AND status = 1 LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'i', $item_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $menuItem = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if (!$menuItem) {
                continue; // item no longer exists / unavailable, skip it
            }

            $verified_items[] = [
                'id'       => $menuItem['id'],
                'title'    => $menuItem['title'],
                'price'    => (float) $menuItem['price'],
                'quantity' => $item_qty,
            ];
            $total_price += $menuItem['price'] * $item_qty;
        }

        if (empty($verified_items)) {
            $error = "Your cart items are no longer available. Please refresh and try again.";
        } else {
            mysqli_begin_transaction($conn);
            try {
                $customerUserId = customer_logged_in() ? (int) $_SESSION['customer_user']['id'] : null;
                $orderStmt = mysqli_prepare($conn, "INSERT INTO orders (user_id, full_name, email, phone, address, payment_method, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
                mysqli_stmt_bind_param($orderStmt, 'isssssd', $customerUserId, $full_name, $email, $phone, $address, $payment_method, $total_price);
                if (!mysqli_stmt_execute($orderStmt)) {
                    throw new Exception(mysqli_stmt_error($orderStmt));
                }
                $order_id = mysqli_insert_id($conn);
                mysqli_stmt_close($orderStmt);

                $itemStmt = mysqli_prepare($conn, "INSERT INTO order_items (order_id, menu_item_id, title, price, quantity) VALUES (?, ?, ?, ?, ?)");
                foreach ($verified_items as $item) {
                    mysqli_stmt_bind_param($itemStmt, 'iisdi', $order_id, $item['id'], $item['title'], $item['price'], $item['quantity']);
                    if (!mysqli_stmt_execute($itemStmt)) {
                        throw new Exception(mysqli_stmt_error($itemStmt));
                    }
                }
                mysqli_stmt_close($itemStmt);

                mysqli_commit($conn);
                cache_flush_all(); // new order changes dashboard KPIs/charts
                $_SESSION['checkout_success'] = true;
                $_SESSION['checkout_order_id'] = $order_id;

                require_once __DIR__ . '/config/mailer.php';
                try { send_order_confirmation_email($conn, $order_id); } catch (\Throwable $e) { error_log('order email failed: ' . $e->getMessage()); }
            } catch (Exception $e) {
                mysqli_rollback($conn);
                error_log("Checkout failed: " . $e->getMessage());
                $error = "We couldn't place your order right now. Please try again.";
            }
        }
    }

    if (!empty($error)) {
        $_SESSION['checkout_error'] = $error;
    }

    // Redirect (Post/Redirect/Get) so refreshing the page never re-submits the order
    header("Location: checkout.php");
    exit();
}

$success  = $_SESSION['checkout_success'] ?? false;
$order_id = $_SESSION['checkout_order_id'] ?? 0;
$error    = $_SESSION['checkout_error'] ?? '';
unset($_SESSION['checkout_success'], $_SESSION['checkout_order_id'], $_SESSION['checkout_error']);

$order_summary = null;
if ($success && $order_id) {
    $order_summary = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM orders WHERE id=" . (int) $order_id));
    $order_items_summary = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id=" . (int) $order_id);
    $settings_for_summary = mysqli_fetch_assoc(mysqli_query($conn, "SELECT currency_symbol FROM settings LIMIT 1")) ?: ['currency_symbol' => '$'];
}

require_once 'header.php';
?>

<!-- BREADCRUMB -->
<div style="background: #fafafa; padding: 40px 0; border-bottom: 1px solid #eee; margin-top: 78px;">
   <div class="container text-center">
      <h2 style="font-weight: 700; margin-bottom: 10px;">Checkout</h2>
      <p style="color: #888; margin-bottom: 0;">Complete your order and satisfy your cravings</p>
   </div>
</div>

<div class="container my-5">
    <?php if ($success): ?>
        <div class="text-center py-5" data-aos="fade-up">
            <div style="font-size: 4rem; color: #28a745; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 style="font-weight: 700; margin-bottom: 10px;">Order Placed Successfully!</h2>
            <p style="color: #666; margin-bottom: 10px;">Thank you for your order. Your Order ID is <strong>#<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></strong>. We will begin preparing your delicious food shortly.</p>
            <p style="color: #888; font-size: 0.9rem; margin-bottom: 30px;"><i class="fas fa-envelope me-1"></i>A confirmation has been sent to your email.</p>

            <?php if ($order_summary): ?>
            <div class="mx-auto p-4 bg-white rounded shadow-sm border text-start" style="max-width: 480px;">
                <h5 class="mb-3" style="font-weight: 700;">Order Summary</h5>
                <?php $currency = $settings_for_summary['currency_symbol']; while ($oi = mysqli_fetch_assoc($order_items_summary)): ?>
                    <div class="d-flex justify-content-between mb-2" style="font-size: 0.92rem;">
                        <span><?php echo htmlspecialchars($oi['title']); ?> &times; <?php echo (int) $oi['quantity']; ?></span>
                        <span><?php echo $currency; ?><?php echo number_format($oi['price'] * $oi['quantity'], 2); ?></span>
                    </div>
                <?php endwhile; ?>
                <hr>
                <div class="d-flex justify-content-between" style="font-weight: 700;">
                    <span>Total</span>
                    <span><?php echo $currency; ?><?php echo number_format($order_summary['total_price'], 2); ?></span>
                </div>
                <p class="text-muted small mt-3 mb-0"><i class="fas fa-truck me-1"></i>Delivering to: <?php echo htmlspecialchars($order_summary['address']); ?></p>
            </div>
            <?php endif; ?>

            <a href="index.php" class="btn-red px-5 py-3 mt-4" style="border-radius: 30px; text-decoration: none; font-weight: 600; display: inline-block;">Go Back to Home</a>
        </div>
        
        <script>
            // Clear cart from local storage on successful order placement
            localStorage.removeItem('restaurant_cart');
        </script>

    <?php else: ?>
        <form method="POST" action="" id="checkoutForm" class="row g-4" data-aos="fade-up">
            <?php csrf_field(); ?>
            <?php if (!empty($error)): ?>
                <div class="col-12">
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <input type="hidden" name="cart_data" id="checkoutCartData">

            <!-- Customer Details Form -->
            <div class="col-lg-7">
                <div class="p-4 bg-white rounded shadow-sm border">
                    <h4 class="mb-4" style="font-weight: 700; color: #333;"><i class="far fa-id-card me-2" style="color: var(--primary);"></i>Customer Details</h4>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 500;">Full Name</label>
                        <input type="text" name="full_name" class="form-control py-2" required placeholder="John Doe" value="<?php echo htmlspecialchars($_SESSION['customer_user']['full_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 500;">Email Address</label>
                            <input type="email" name="email" class="form-control py-2" required placeholder="john@example.com" value="<?php echo htmlspecialchars($_SESSION['customer_user']['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 500;">Phone Number</label>
                            <input type="tel" name="phone" class="form-control py-2" required placeholder="+1 (555) 019-2834" value="<?php echo htmlspecialchars($_SESSION['customer_user']['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 500;">Delivery Address</label>
                        <textarea name="address" class="form-control" rows="4" required placeholder="Flat, Street, Area, City"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 500;">Payment Method</label>
                        <select name="payment_method" id="paymentMethod" class="form-select py-2">
                            <option value="Cash on Delivery">Cash on Delivery</option>
                            <option value="Card (Simulation)">Credit/Debit Card (Simulation)</option>
                        </select>
                    </div>

                    <div id="cardFields" style="display:none; background:#fafafa; border:1px dashed #ddd; border-radius:8px; padding:16px; margin-bottom:8px;">
                        <p class="small text-muted mb-3"><i class="fas fa-shield-alt me-1"></i>Simulation only — no real payment is processed and no card data is sent to the server.</p>
                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 500;">Card Number</label>
                            <input type="text" id="cardNumber" class="form-control py-2" placeholder="4242 4242 4242 4242" maxlength="19" autocomplete="off">
                            <small id="cardNumberFeedback" class="text-danger"></small>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label" style="font-weight: 500;">Expiry (MM/YY)</label>
                                <input type="text" id="cardExpiry" class="form-control py-2" placeholder="12/28" maxlength="5" autocomplete="off">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label" style="font-weight: 500;">CVV</label>
                                <input type="text" id="cardCvv" class="form-control py-2" placeholder="123" maxlength="4" autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary Section -->
            <div class="col-lg-5">
                <div class="p-4 bg-white rounded shadow-sm border">
                    <h4 class="mb-4" style="font-weight: 700; color: #333;"><i class="fas fa-shopping-basket me-2" style="color: var(--primary);"></i>Order Summary</h4>
                    
                    <div id="checkoutSummaryItems" class="mb-3">
                        <!-- Items populated dynamically by JS -->
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-2" style="font-size: 0.95rem; color: #666;">
                        <span>Subtotal</span>
                        <span id="summarySubtotal">$0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3" style="font-size: 0.95rem; color: #666;">
                        <span>Delivery Fee</span>
                        <span style="color: #28a745; font-weight: 500;">FREE</span>
                    </div>
                    <div class="d-flex justify-content-between mb-4" style="font-size: 1.15rem; font-weight: 700; color: #333;">
                        <span>Total</span>
                        <span id="summaryTotal">$0.00</span>
                    </div>
                    
                    <button type="submit" name="place_order_btn" class="btn-red w-100 py-3" style="border-radius: 30px; font-weight: 600;">Place Order</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script src="js/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
    function loadCheckoutSummary() {
        let cart = JSON.parse(localStorage.getItem('restaurant_cart')) || [];
        
        // Put cart data in hidden input for form submit
        $('#checkoutCartData').val(JSON.stringify(cart));
        
        let container = $('#checkoutSummaryItems');
        container.empty();
        
        if (cart.length === 0) {
            container.html('<p class="text-muted text-center py-3">No items in your cart.</p>');
            $('#summarySubtotal').text('$0.00');
            $('#summaryTotal').text('$0.00');
            $('button[name="place_order_btn"]').prop('disabled', true);
            return;
        }
        
        let total = 0;
        cart.forEach(item => {
            let sub = item.price * item.quantity;
            total += sub;
            
            container.append(`
                <div class="d-flex align-items-center mb-3">
                    <img src="${item.img}" style="width: 45px; height: 45px; object-fit: cover; border-radius: 6px; margin-right: 12px;">
                    <div style="flex: 1;">
                        <h6 style="margin: 0; font-size: 0.9rem; font-weight: 600; color: #444;">${item.title}</h6>
                        <small style="color: #888;">Qty: ${item.quantity} &times; $${item.price.toFixed(2)}</small>
                    </div>
                    <span style="font-weight: 600; font-size: 0.95rem; color: #333;">$${sub.toFixed(2)}</span>
                </div>
            `);
        });
        
        $('#summarySubtotal').text('$' + total.toFixed(2));
        $('#summaryTotal').text('$' + total.toFixed(2));
    }
    
    loadCheckoutSummary();

    // Show/hide simulated card fields based on payment method
    function toggleCardFields() {
        if ($('#paymentMethod').val() === 'Card (Simulation)') {
            $('#cardFields').slideDown(150);
        } else {
            $('#cardFields').slideUp(150);
        }
    }
    $('#paymentMethod').on('change', toggleCardFields);
    toggleCardFields();

    // Format card number with spaces as the user types
    $('#cardNumber').on('input', function() {
        let digits = $(this).val().replace(/\D/g, '').slice(0, 16);
        $(this).val(digits.replace(/(.{4})/g, '$1 ').trim());
    });
    $('#cardExpiry').on('input', function() {
        let digits = $(this).val().replace(/\D/g, '').slice(0, 4);
        $(this).val(digits.length > 2 ? digits.slice(0,2) + '/' + digits.slice(2) : digits);
    });
    $('#cardCvv').on('input', function() {
        $(this).val($(this).val().replace(/\D/g, '').slice(0, 4));
    });

    // Luhn algorithm - standard checksum used by all real card networks.
    // This is purely a client-side realism/UX check for the simulation;
    // no card data is ever sent to the server.
    function luhnCheck(cardNumber) {
        let digits = cardNumber.replace(/\D/g, '');
        if (digits.length < 12) return false;
        let sum = 0, alt = false;
        for (let i = digits.length - 1; i >= 0; i--) {
            let n = parseInt(digits.charAt(i), 10);
            if (alt) { n *= 2; if (n > 9) n -= 9; }
            sum += n;
            alt = !alt;
        }
        return sum % 10 === 0;
    }

    $('#checkoutForm').on('submit', function(e) {
        if ($('#paymentMethod').val() === 'Card (Simulation)') {
            let num = $('#cardNumber').val();
            let expiry = $('#cardExpiry').val();
            let cvv = $('#cardCvv').val();
            let feedback = $('#cardNumberFeedback');
            feedback.text('');

            if (!luhnCheck(num)) {
                feedback.text('That card number doesn\'t look valid (simulation check).');
                e.preventDefault();
                return false;
            }
            if (!/^\d{2}\/\d{2}$/.test(expiry)) {
                feedback.text('Enter expiry as MM/YY.');
                e.preventDefault();
                return false;
            }
            if (cvv.length < 3) {
                feedback.text('Enter a valid CVV.');
                e.preventDefault();
                return false;
            }
        }
    });
});
</script>

<?php
require_once 'footer.php';
?>
