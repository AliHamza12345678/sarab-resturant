<?php
// This file is a content fragment meant to be included by index.php.
// If someone requests it directly, send them to the real page instead of
// crashing (it depends on functions/session state that index.php sets up).
if (basename($_SERVER['SCRIPT_NAME']) === 'contact.php') {
    header('Location: index.php#contact-section');
    exit();
}

$contact_error = "";
$contact_success = false;
$contact_throttle_key = 'contact_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if (isset($_POST['contact_btn'])) {
    verify_csrf();

    if (!login_attempt_allowed($contact_throttle_key, 8, 600)) {
        $contact_error = "You're sending messages too quickly. Please wait a few minutes and try again.";
    } else {
    login_attempt_register_failure($contact_throttle_key);
    $c_name    = trim($_POST['full_name'] ?? '');
    $c_email   = trim($_POST['email'] ?? '');
    $c_phone   = trim($_POST['phone'] ?? '');
    $c_subject = trim($_POST['subject'] ?? '');
    $c_message = trim($_POST['message'] ?? '');

    if (empty($c_name) || empty($c_email) || empty($c_message)) {
        $contact_error = "Please fill in your name, email and message.";
    } elseif (!filter_var($c_email, FILTER_VALIDATE_EMAIL)) {
        $contact_error = "Please enter a valid email address.";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO contact_messages (full_name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssss', $c_name, $c_email, $c_phone, $c_subject, $c_message);
        if (mysqli_stmt_execute($stmt)) {
            $contact_success = true;
            mysqli_stmt_close($stmt);
            require_once __DIR__ . '/config/mailer.php';
            try { send_contact_acknowledgement_email($conn, $c_name, $c_email); } catch (\Throwable $e) { error_log('contact ack email failed: ' . $e->getMessage()); }
        } else {
            error_log("Contact message insert failed: " . mysqli_stmt_error($stmt));
            $contact_error = "Sorry, your message could not be sent. Please try again.";
            mysqli_stmt_close($stmt);
        }
    }
    }
}
?>
<!-- ============================================================
         CONTACT FORM
         ============================================================ -->
      <section id="contact-section">
         <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
               <span class="slbl">Get In Touch</span>
               <h2 class="stitle">Contact <span>Us</span></h2>
               <div class="sline"></div>
               <p class="sdesc mx-auto" style="max-width:480px;">Have a question, feedback, or want to plan a special event? We'd love to hear from you.</p>
            </div>
            <div class="row g-4">
               <div class="col-lg-4" data-aos="fade-right">
                  <div class="ctdark">
                     <h4>Let's Talk</h4>
                     <p class="ctsub">We typically respond within 2 hours during business hours.</p>
                     <div class="ctitem">
                        <div class="cticon"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="ctinfo"><strong>Address</strong><span>42 Flavor Street, Manhattan,<br/>New York, NY 10001</span></div>
                     </div>
                     <div class="ctitem">
                        <div class="cticon"><i class="fas fa-phone-alt"></i></div>
                        <div class="ctinfo"><strong>Phone</strong><span>+1 (800) 123-4567</span></div>
                     </div>
                     <div class="ctitem">
                        <div class="cticon"><i class="fas fa-envelope"></i></div>
                        <div class="ctinfo"><strong>Email</strong><span>hello@sarabfood.com</span></div>
                     </div>
                     <div class="ctitem">
                        <div class="cticon"><i class="fas fa-clock"></i></div>
                        <div class="ctinfo"><strong>Working Hours</strong><span>Wed - Sun: 9 AM - 11 PM</span></div>
                     </div>
                     <div class="ctsocrow">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                     </div>
                  </div>
               </div>
               <div class="col-lg-8" data-aos="fade-left">
                  <div class="fcard">
                     <form method="POST" action="#contact-section">
                     <?php csrf_field(); ?>
                     <div class="row g-3">
                        <div class="col-sm-6"><label class="flbl">Your Name *</label><input type="text" name="full_name" class="fctrl" placeholder="John Doe" required/></div>
                        <div class="col-sm-6"><label class="flbl">Email Address *</label><input type="email" name="email" class="fctrl" placeholder="you@email.com" required/></div>
                        <div class="col-sm-6"><label class="flbl">Phone Number</label><input type="tel" name="phone" class="fctrl" placeholder="+1 (800) 000-0000"/></div>
                        <div class="col-sm-6">
                           <label class="flbl">Subject *</label>
                           <select name="subject" class="fctrl">
                              <option>General Inquiry</option>
                              <option>Catering &amp; Events</option>
                              <option>Feedback</option>
                              <option>Partnership</option>
                              <option>Media &amp; Press</option>
                           </select>
                        </div>
                        <div class="col-12"><label class="flbl">Message *</label><textarea name="message" class="fctrl" rows="5" placeholder="Write your message here..." required></textarea></div>
                        <?php if (!empty($contact_error)): ?>
                          <div class="col-12"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($contact_error); ?></div></div>
                        <?php endif; ?>
                        <div class="col-12"><button type="submit" name="contact_btn" class="btn-red" id="ctcBtn"><i class="fas fa-paper-plane"></i>Send Message</button></div>
                     </div>
                     </form>
                     <?php if ($contact_success): ?>
                     <div class="sucmsg" id="ctcOk" style="display:block;">
                        <i class="fas fa-check-circle"></i>
                        <p>Message sent! We'll reply within 2 hours.</p>
                     </div>
                     <?php endif; ?>
                  </div>
               </div>
            </div>
         </div>
      </section>