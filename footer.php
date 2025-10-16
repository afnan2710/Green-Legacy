<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    :root {
      --primary-green: #2E8B57;
      --light-green: #a8e6cf;
      --dark-green: #1a5c3a;
    }

    .footer {
      background-color: var(--dark-green);
      color: white;
    }

    .footer-links a {
      position: relative;
      transition: all 0.3s ease;
    }

    .footer-links a:hover {
      color: var(--light-green);
    }

    .footer-links a::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 0;
      height: 1px;
      background: var(--light-green);
      transition: width 0.3s ease;
    }

    .footer-links a:hover::after {
      width: 100%;
    }

    .newsletter-input {
      transition: all 0.3s ease;
    }

    .newsletter-input:focus {
      box-shadow: 0 0 0 3px rgba(168, 230, 207, 0.5);
    }

    .social-icon {
      transition: all 0.3s ease;
    }

    .social-icon:hover {
      transform: translateY(-3px);
      color: var(--light-green);
    }
  </style>
</head>

<body>
  <footer class="footer py-12 px-4">
    <div class="container mx-auto">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        <!-- Brand Info -->
        <div class="flex flex-col items-center md:items-start">
          <div class="flex items-center mb-4">
            <img src="footerlogo.png" alt="Green Legacy Logo" class="h-22 mr-2">
          </div>
          <div class="flex space-x-4 mt-2">
            <a href="#" class="social-icon text-white text-xl hover:text-green-300">
              <i class="fab fa-facebook-f"></i>
            </a>
            <a href="#" class="social-icon text-white text-xl hover:text-green-300">
              <i class="fab fa-instagram"></i>
            </a>
            <a href="#" class="social-icon text-white text-xl hover:text-green-300">
              <i class="fab fa-pinterest-p"></i>
            </a>
            <a href="#" class="social-icon text-white text-xl hover:text-green-300">
              <i class="fab fa-youtube"></i>
            </a>
          </div>
        </div>

        <!-- Quick Links -->
        <div class="footer-links">
          <h3 class="text-lg font-semibold mb-4 border-b border-green-700 pb-2">Quick Links</h3>
          <ul class="space-y-2">
            <li><a href="shop.php" class="text-green-100">Plant Shop</a></li>
            <li><a href="tools.php" class="text-green-100">Gardening Tools</a></li>
            <li><a href="guides.php" class="text-green-100">Beginner Guides</a></li>
            <li><a href="exchange.php" class="text-green-100">Plant Exchange</a></li>
            <li><a href="blogs.php" class="text-green-100">Blog & Articles</a></li>
            <li><a href="sitemap.php" class="text-green-100">Sitemap</a></li>
            <li><a href="terms.php" class="text-green-100">Terms of Service</a></li>
          </ul>
        </div>

        <!-- Customer Service -->
        <div class="footer-links">
          <h3 class="text-lg font-semibold mb-4 border-b border-green-700 pb-2">Customer Service</h3>
          <ul class="space-y-2">
            <li><a href="contact.php" class="text-green-100">Contact Us</a></li>
            <li><a href="careers.php" class="text-green-100">Careers</a></li>
            <li><a href="faq.php" class="text-green-100">FAQs</a></li>
            <li><a href="order_tracking.php" class="text-green-100">Order Tracking</a></li>
            <li><a href="shipping.php" class="text-green-100">Shipping Policy</a></li>
            <li><a href="returns.php" class="text-green-100">Return Policy</a></li>
            <li><a href="privacy.php" class="text-green-100">Privacy Policy</a></li>
          </ul>
        </div>

        <!-- Newsletter -->
        <div>
          <h3 class="text-lg font-semibold mb-4 border-b border-green-700 pb-2">Join Our Newsletter</h3>
          <p class="text-green-100 mb-4">
            Get gardening tips, exclusive offers, and updates on new arrivals.
          </p>
          <form class="flex flex-col space-y-3" method="POST" action="subscribe_newsletter.php">
            <input
              type="email"
              name="email"
              placeholder="Your email address"
              class="newsletter-input px-4 py-2 rounded bg-green-800 text-white placeholder-green-300 focus:outline-none focus:bg-green-700"
              required>
            <button
              type="submit"
              class="bg-green-600 hover:bg-green-500 text-white py-2 px-6 rounded transition duration-200">
              Subscribe
            </button>
          </form>
          <?php if (isset($_GET['subscription'])): ?>
            <div class="mt-4 text-sm <?php echo $_GET['status'] === 'success' ? 'text-green-300' : 'text-red-300'; ?>">
              <?php echo htmlspecialchars($_GET['message']); ?>
            </div>
          <?php endif; ?>
          <div class="mt-4 flex items-center">
            <i class="fas fa-phone-alt text-green-300 mr-2"></i>
            <span class="text-green-100">+880 1601-701444</span>
          </div>
          <div class="mt-2 flex items-center">
            <i class="fas fa-envelope text-green-300 mr-2"></i>
            <span class="text-green-100">info@greenlegacy.com</span>
          </div>
        </div>
      </div>

      <!-- Copyright -->
      <div class="border-t border-green-700 mt-8 pt-8 text-center text-green-300">
        <p>&copy; <?php echo date('Y'); ?> Green Legacy. All rights reserved.</p>
        <p class="mt-2 text-sm">Designed with <i class="fas fa-heart text-red-400"></i> for plant lovers</p>
      </div>
    </div>
  </footer>
</body>

</html>