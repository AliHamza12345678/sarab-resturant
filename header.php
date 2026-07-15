<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/customer_auth.php';
require_once __DIR__ . '/config/cache.php';
$settings = cache_remember('site_settings', 60, function() use ($conn) {
    return mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings LIMIT 1"));
});
if (!$settings) {
    $settings = [
        'site_name' => 'Sarab',
        'phone' => '+1 (800) 123-4567',
        'email' => 'hello@sarabfood.com',
        'address' => '42 Flavor Street, NY',
        'facebook' => '#',
        'instagram' => '#',
        'twitter' => '#',
        'opening_hours' => 'Wed - Sun, 9 AM - 11 PM'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="UTF-8">
      <meta http-equiv="X-UA-Compatible" content="IE=edge">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="author" content="<?php echo htmlspecialchars($settings['site_name']); ?>">
      <meta name="description" content="<?php echo htmlspecialchars($settings['site_name']); ?> - Fast Food & Restaurant Template">
      <title><?php echo htmlspecialchars($settings['site_name']); ?></title>
      <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Poppins:wght@300;400;500;600;700&family=Dancing+Script:wght@700&display=swap" rel="stylesheet"/>
      <!-- Bootstrap 5.3 -->
      <link href="css/bootstrap.min.css" rel="stylesheet"/>
      <!-- AOS Animate on Scroll -->
      <link href="css/aos.css" rel="stylesheet"/>
      <!-- Swiper -->
      <link href="css/swiper-bundle.min.css" rel="stylesheet"/>
      <!-- all min css -->
      <link rel="stylesheet" href="css/all.min.css"/>
      <!-- magnific CSS -->
      <link rel="stylesheet" href="css/magnific-popup.css"/>
      <!-- Style CSS -->
      <link rel="stylesheet" href="css/style.css" />
   </head>
   <body>
      <!-- ============================================================
         TOP BAR
         ============================================================ -->
      <div id="topbar">
         <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
               <div class="top-contact d-flex flex-wrap">
                  <span><i class="fas fa-phone-alt"></i><?php echo htmlspecialchars($settings['phone']); ?></span>
                  <span><i class="fas fa-envelope"></i><?php echo htmlspecialchars($settings['email']); ?></span>
                  <span><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($settings['address']); ?></span>
               </div>
               <div class="d-flex align-items-center gap-3">
                  <span class="ttag"><i class="fas fa-fire me-1"></i>Free Delivery Today!</span>
                  <div class="tsoc">
                     <a href="<?php echo htmlspecialchars($settings['facebook']); ?>"><i class="fab fa-facebook-f"></i></a>
                     <a href="<?php echo htmlspecialchars($settings['instagram']); ?>"><i class="fab fa-instagram"></i></a>
                     <a href="<?php echo htmlspecialchars($settings['twitter']); ?>"><i class="fab fa-twitter"></i></a>
                  </div>
               </div>
            </div>
         </div>
      </div>
      <!-- ============================================================
         NAVBAR
         ============================================================ -->
      <nav class="navbar navbar-expand-lg" id="nav">
         <div class="container">
            <a class="navbar-brand" href="#">
               <?php if (!empty($settings['logo'])): ?>
                 <img src="<?php echo htmlspecialchars($settings['logo']); ?>" alt="<?php echo htmlspecialchars($settings['site_name']); ?>" style="height:44px;object-fit:contain;">
               <?php else: ?>
               <div class="blogo">
                  <div class="bico"><i class="fas fa-utensils"></i></div>
                  <div>
                     <div class="bname"><?php 
                        $site_name = htmlspecialchars($settings['site_name']);
                        if ($site_name === 'Sarab') {
                            echo 'Sar<span>ab</span>';
                        } else {
                            echo $site_name;
                        }
                     ?></div>
                     <div class="bsub">Fast Food & Restaurant</div>
                  </div>
               </div>
               <?php endif; ?>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navmenu">
            <i class="fas fa-bars" style="color:var(--primary);font-size:1.35rem;"></i>
            </button>
            <div class="collapse navbar-collapse" id="navmenu">
               <ul class="navbar-nav mx-auto">
                  <li class="nav-item"><a class="nav-link active" href="#hero">Home</a></li>
                  <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                  <li class="nav-item"><a class="nav-link" href="#menu">Menu</a></li>
                  <li class="nav-item"><a class="nav-link" href="#chefs">Chefs</a></li>
                  <li class="nav-item"><a class="nav-link" href="#reservation">Reservation</a></li>
                  <li class="nav-item"><a class="nav-link" href="#testimonials">Reviews</a></li>
                  <li class="nav-item"><a class="nav-link" href="#contact-section">Contact</a></li>
               </ul>
               <div class="d-flex align-items-center gap-1">
                  <!-- FIX 1: Search button -->
                  <button id="navSearchBtn" title="Search"><i class="fas fa-search"></i></button>
                  <?php if (customer_logged_in()): ?>
                    <a href="my-account.php" class="nav-link" title="My Account"><i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars(explode(' ', $_SESSION['customer_user']['full_name'])[0]); ?></a>
                  <?php else: ?>
                    <a href="login.php" class="nav-link" title="Login"><i class="fas fa-user me-1"></i>Login</a>
                  <?php endif; ?>
                  <a href="#menu" class="nav-link nav-cta"><i class="fas fa-shopping-bag me-1"></i>Order Now</a>
               </div>
            </div>
         </div>
      </nav>
      <!-- ============================================================
         FIX 1 � SEARCH OVERLAY POPUP
         ============================================================ -->
      <div id="searchOv">
         <button class="sovclose" id="searchClose"><i class="fas fa-times"></i></button>
         <div class="sovbox">
            <h4>What are you craving today?</h4>
            <div class="sovinput">
               <input type="text" id="searchInput" placeholder="Search burgers, pizza, chicken..." autocomplete="off"/>
               <button><i class="fas fa-search"></i></button>
            </div>
            <!-- Categories inside search box -->
            <div class="sovcats">
               <div class="sovcat active" data-cat="all">
                  <img src="img/menu/1.jpg" alt=""/>All Items
               </div>
               <div class="sovcat" data-cat="burgers">
                  <img src="img/menu/1.jpg" alt=""/>Burgers
               </div>
               <div class="sovcat" data-cat="pizza">
                  <img src="img/menu/2.jpg" alt=""/>Pizza
               </div>
               <div class="sovcat" data-cat="chicken">
                  <img src="img/menu/3.jpg" alt=""/>Chicken
               </div>
               <div class="sovcat" data-cat="wraps">
                  <img src="img/menu/4.jpg" alt=""/>Wraps
               </div>
               <div class="sovcat" data-cat="pasta">
                  <img src="img/menu/5.jpg" alt=""/>Pasta
               </div>
               <div class="sovcat" data-cat="desserts">
                  <img src="img/menu/6.jpg" alt=""/>Desserts
               </div>
            </div>
            <div class="sovtrend">
               <p><i class="fas fa-fire me-1" style="color:var(--secondary);"></i>Trending Searches</p>
               <span class="ttag">Smash Burger</span>
               <span class="ttag">Nashville Chicken</span>
               <span class="ttag">Truffle Pizza</span>
               <span class="ttag">Lava Cake</span>
               <span class="ttag">Loaded Fries</span>
               <span class="ttag">Mango Shake</span>
            </div>
         </div>
      </div>