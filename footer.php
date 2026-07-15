  <!-- FOOTER -->
      <footer>
         <div class="container">
            <div class="row g-5">
               <div class="col-lg-4">
                  <div class="fnm"><?php 
                     $site_name = htmlspecialchars($settings['site_name']);
                     if ($site_name === 'Sarab') {
                         echo 'Sar<span>ab</span>';
                     } else {
                         echo $site_name;
                     }
                  ?></div>
                  <p class="fdesc">We bring the world's finest flavors together in a fast, friendly, and affordable experience. Every meal crafted with love.</p>
                  <div class="fsoc">
                     <a href="<?php echo htmlspecialchars($settings['facebook']); ?>"><i class="fab fa-facebook-f"></i></a>
                     <a href="<?php echo htmlspecialchars($settings['instagram']); ?>"><i class="fab fa-instagram"></i></a>
                     <a href="<?php echo htmlspecialchars($settings['twitter']); ?>"><i class="fab fa-twitter"></i></a>
                  </div>
               </div>
               <div class="col-sm-6 col-lg-2">
                  <div class="ftit">Quick Links</div>
                  <ul class="flinks ps-0">
                     <li><a href="#hero"><i class="fas fa-chevron-right"></i>Home</a></li>
                     <li><a href="#about"><i class="fas fa-chevron-right"></i>About Us</a></li>
                     <li><a href="#menu"><i class="fas fa-chevron-right"></i>Our Menu</a></li>
                     <li><a href="#reservation"><i class="fas fa-chevron-right"></i>Reservation</a></li>
                     <li><a href="#blog"><i class="fas fa-chevron-right"></i>Blog</a></li>
                     <li><a href="#contact-section"><i class="fas fa-chevron-right"></i>Contact</a></li>
                  </ul>
               </div>
               <div class="col-sm-6 col-lg-2">
                  <div class="ftit">Our Menu</div>
                  <ul class="flinks ps-0">
                     <li><a href="#menu"><i class="fas fa-chevron-right"></i>Burgers</a></li>
                     <li><a href="#menu"><i class="fas fa-chevron-right"></i>Pizza</a></li>
                     <li><a href="#menu"><i class="fas fa-chevron-right"></i>Fried Chicken</a></li>
                     <li><a href="#menu"><i class="fas fa-chevron-right"></i>Wraps &amp; Rolls</a></li>
                     <li><a href="#menu"><i class="fas fa-chevron-right"></i>Pasta</a></li>
                     <li><a href="#menu"><i class="fas fa-chevron-right"></i>Desserts</a></li>
                  </ul>
               </div>
               <div class="col-lg-4">
                  <div class="ftit">Get In Touch</div>
                  <div class="fci">
                     <div class="fciico"><i class="fas fa-map-marker-alt"></i></div>
                     <div class="fciinfo"><strong>Address</strong><?php echo htmlspecialchars($settings['address']); ?></div>
                  </div>
                  <div class="fci">
                     <div class="fciico"><i class="fas fa-phone-alt"></i></div>
                     <div class="fciinfo"><strong>Phone</strong><?php echo htmlspecialchars($settings['phone']); ?></div>
                  </div>
                  <div class="fci">
                     <div class="fciico"><i class="fas fa-envelope"></i></div>
                     <div class="fciinfo"><strong>Email</strong><?php echo htmlspecialchars($settings['email']); ?></div>
                  </div>
                  <div class="fci">
                     <div class="fciico"><i class="fas fa-clock"></i></div>
                     <div class="fciinfo"><strong>Hours</strong><?php echo htmlspecialchars($settings['opening_hours']); ?></div>
                  </div>
               </div>
            </div>
         </div>
         <div class="fbot">
            <div class="container">
               <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                  <p>&copy 2026 <span><?php echo htmlspecialchars($settings['site_name']); ?> Restaurant</span>. All Rights Reserved by Sarab Food.Made with <span><i class="fas fa-heart"></i></span>  <br>Distributed by Ali HAmza</p>
                  <div><a href="#">Privacy Policy</a><a href="#">Terms</a><a href="#">Cookies</a></div>
               </div>
            </div>
         </div>
      </footer>
      <!-- Floating cart trigger -->
      <div class="cartfl" id="cartTrigger" style="display: flex; align-items: center; justify-content: center; position: fixed; bottom: 80px; right: 20px; z-index: 1050; cursor: pointer; background: var(--primary); color: #fff; width: 60px; height: 60px; border-radius: 50%; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: all 0.3s ease;">
         <i class="fas fa-shopping-cart" style="font-size: 1.5rem;"></i>
         <div class="ccount" id="cartCount" style="position: absolute; top: -5px; right: -5px; background: var(--secondary); color: #fff; width: 22px; height: 22px; border-radius: 50%; font-size: 0.75rem; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid #fff;">0</div>
      </div>

      <!-- Cart Sidebar Panel -->
      <div id="cartSidebar" style="position: fixed; top: 0; right: -400px; width: 400px; max-width: 100%; height: 100vh; background: #fff; box-shadow: -5px 0 25px rgba(0,0,0,0.15); z-index: 1090; transition: right 0.4s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column;">
         <div style="padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
            <h5 style="margin: 0; font-weight: 700; color: #333;"><i class="fas fa-shopping-basket me-2" style="color: var(--primary);"></i>Your Cart</h5>
            <button id="closeCartBtn" style="background: none; border: none; font-size: 1.25rem; color: #999; cursor: pointer; transition: color 0.2s;"><i class="fas fa-times"></i></button>
         </div>
         
         <div id="cartItemsContainer" style="flex: 1; overflow-y: auto; padding: 20px;">
            <div style="text-align: center; margin-top: 50px; color: #aaa;">
               <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 15px;"></i>
               <p>Your cart is empty</p>
            </div>
         </div>
         
         <div style="padding: 20px; border-top: 1px solid #eee; background: #fafafa;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px; font-weight: 600; font-size: 1.1rem; color: #333;">
               <span>Total:</span>
               <span id="cartTotalPrice">$0.00</span>
            </div>
            <a href="checkout.php" class="btn-red w-100 text-center" style="display: block; padding: 12px; border-radius: 30px; font-weight: 600; text-decoration: none; background: var(--primary); color: #fff;">Proceed to Checkout</a>
         </div>
      </div>
      <!-- Cart Backdrop overlay -->
      <div id="cartBackdrop" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.4); z-index: 1080; display: none;"></div>
      <!-- Back to top -->
      <button id="btt" onclick="window.scrollTo({top:0,behavior:'smooth'})"><i class="fas fa-chevron-up"></i></button>
    
	<!-- jQuery -->
      <script src="js/jquery-3.7.1.min.js"></script>
      <!-- Bootstrap 5 -->
      <script src="js/bootstrap.bundle.min.js"></script>
      <!-- AOS -->
      <script src="js/aos.js"></script>
      <!-- Swiper -->
      <script src="js/swiper-bundle.min.js"></script>
      <!-- CounterUp -->
      <script src="js/jquery.magnific-popup.min.js"></script>
      <!-- Main js -->
      <script src="js/main.js"></script>
      
      <!-- Shopping Cart Logic -->
      <script>
      $(document).ready(function() {
          function getCart() {
              return JSON.parse(localStorage.getItem('restaurant_cart')) || [];
          }

          function saveCart(cart) {
              localStorage.setItem('restaurant_cart', JSON.stringify(cart));
              updateCartUI();
          }

          function updateCartUI() {
              let cart = getCart();
              let totalQty = cart.reduce((acc, item) => acc + item.quantity, 0);
              $('#cartCount').text(totalQty);

              let container = $('#cartItemsContainer');
              container.empty();

              if (cart.length === 0) {
                  container.html(`
                      <div style="text-align: center; margin-top: 50px; color: #aaa;">
                         <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 15px;"></i>
                         <p>Your cart is empty</p>
                      </div>
                  `);
                  $('#cartTotalPrice').text('$0.00');
              } else {
                  let total = 0;
                  cart.forEach((item, index) => {
                      let subtotal = item.price * item.quantity;
                      total += subtotal;

                      container.append(`
                          <div style="display: flex; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #f5f5f5; padding-bottom: 15px;">
                              <img src="${item.img}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; margin-right: 15px;" loading="lazy">
                              <div style="flex: 1;">
                                  <h6 style="margin: 0 0 5px 0; font-size: 0.95rem; font-weight: 600; color: #333;">${item.title}</h6>
                                  <span style="color: var(--primary); font-weight: 600; font-size: 0.9rem;">$${item.price.toFixed(2)}</span>
                                  <div style="display: flex; align-items: center; margin-top: 5px;">
                                      <button class="cart-qty-btn decrease-qty" data-index="${index}" style="background: #eee; border: none; border-radius: 4px; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; cursor: pointer;">-</button>
                                      <span style="margin: 0 10px; font-weight: 600; font-size: 0.9rem;">${item.quantity}</span>
                                      <button class="cart-qty-btn increase-qty" data-index="${index}" style="background: #eee; border: none; border-radius: 4px; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; cursor: pointer;">+</button>
                                  </div>
                              </div>
                              <button class="remove-cart-item" data-index="${index}" style="background: none; border: none; color: #ccc; cursor: pointer; padding: 5px; font-size: 1.1rem; transition: color 0.2s;"><i class="fas fa-trash-alt"></i></button>
                          </div>
                      `);
                  });
                  $('#cartTotalPrice').text('$' + total.toFixed(2));
              }
          }

          // Toggle Cart Sidebar
          $('#cartTrigger').on('click', function() {
              $('#cartSidebar').css('right', '0');
              $('#cartBackdrop').fadeIn(200);
          });

          $('#closeCartBtn, #cartBackdrop').on('click', function() {
              $('#cartSidebar').css('right', '-400px');
              $('#cartBackdrop').fadeOut(200);
          });

          // Quantity modifiers inside sidebar
          $(document).on('click', '.increase-qty', function() {
              let cart = getCart();
              let idx = $(this).data('index');
              cart[idx].quantity += 1;
              saveCart(cart);
          });

          $(document).on('click', '.decrease-qty', function() {
              let cart = getCart();
              let idx = $(this).data('index');
              if (cart[idx].quantity > 1) {
                  cart[idx].quantity -= 1;
              } else {
                  cart.splice(idx, 1);
              }
              saveCart(cart);
          });

          $(document).on('click', '.remove-cart-item', function() {
              let cart = getCart();
              let idx = $(this).data('index');
              cart.splice(idx, 1);
              saveCart(cart);
          });

          window.addEventListener('cartUpdated', function() {
              updateCartUI();
          });

          updateCartUI();
      });
      </script>
   </body>
</html>