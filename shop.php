<?php
require_once 'db_connect.php';

// Initialize variables
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'default';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;

// Validate page number
if ($page < 1) {
  $page = 1;
}

// Build base query
$query = "SELECT p.*, 
          pi.quantity as stock,
          (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
          FROM products p
          JOIN product_inventory pi ON p.id = pi.product_id
          WHERE p.is_active = 1 AND pi.quantity > 0";

$countQuery = "SELECT COUNT(*) as total FROM products p
               JOIN product_inventory pi ON p.id = pi.product_id
               WHERE p.is_active = 1 AND pi.quantity > 0";

$params = [];
$types = '';

// Apply search filter
if (!empty($search)) {
  $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
  $countQuery .= " AND (p.name LIKE ? OR p.description LIKE ?)";
  $searchTerm = "%$search%";
  $params[] = $searchTerm;
  $params[] = $searchTerm;
  $types .= 'ss';
}

// Apply category filter
if (!empty($category) && $category !== 'all') {
  $query .= " AND p.category_id = ?";
  $countQuery .= " AND p.category_id = ?";
  $params[] = $category;
  $types .= 'i';
}

// Apply sorting
switch ($sort) {
  case 'price_asc':
    $query .= " ORDER BY p.price ASC";
    break;
  case 'price_desc':
    $query .= " ORDER BY p.price DESC";
    break;
  case 'name_asc':
    $query .= " ORDER BY p.name ASC";
    break;
  case 'name_desc':
    $query .= " ORDER BY p.name DESC";
    break;
  case 'newest':
    $query .= " ORDER BY p.created_at DESC";
    break;
  default:
    $query .= " ORDER BY p.is_featured DESC, p.created_at DESC";
    break;
}

// Add pagination to main query
$offset = ($page - 1) * $perPage;
$query .= " LIMIT ? OFFSET ?";

// Get total count for pagination
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
  $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalProducts = $countResult->fetch_assoc()['total'];
$countStmt->close();

// Get products
$stmt = $conn->prepare($query);
if (!empty($params)) {
  // For the main query, we need to add the LIMIT and OFFSET parameters
  $params[] = $perPage;
  $params[] = $offset;
  $types .= 'ii'; // 'i' for integer (both perPage and offset are integers)
  $stmt->bind_param($types, ...$params);
} else {
  // If there are no other parameters, just bind the LIMIT and OFFSET
  $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get categories for filter
$categories = [];
$stmt = $conn->prepare("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
  $categories[] = $row;
}
$stmt->close();

// Close connection


// Calculate total pages
$totalPages = ceil($totalProducts / $perPage);
?>

<?php include 'navbar.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Plant Shop - Green Legacy</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root {
      --primary-green: #2E8B57;
      --light-green: #a8e6cf;
    }

    body {
      font-family: 'Poppins', sans-serif;
    }

    .product-card {
      transition: all 0.3s ease;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .product-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 15px rgba(46, 204, 113, 0.1);
    }

    .badge {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }

    .badge-plant {
      background-color: #dcfce7;
      color: #166534;
    }

    .badge-tool {
      background-color: #e0f2fe;
      color: #1e40af;
    }

    .badge-pesticide {
      background-color: #fef3c7;
      color: #92400e;
    }

    .badge-fertilizer {
      background-color: #ede9fe;
      color: #5b21b6;
    }

    .badge-accessory {
      background-color: #fce7f3;
      color: #9d174d;
    }

    .badge-featured {
      background-color: #f3e8ff;
      color: #7e22ce;
    }

    .badge-discount {
      background-color: #fee2e2;
      color: #b91c1c;
    }

    .pagination .active {
      background-color: var(--primary-green);
      color: white;
    }

    .filter-section {
      transition: all 0.3s ease;
    }

    .mobile-filter-btn {
      display: none;
    }

    /* Animated emojis */
    .plant-emoji {
      position: absolute;
      font-size: 1.5rem;
      opacity: 0.8;
      animation: float 6s ease-in-out infinite;
    }

    @keyframes float {

      0%,
      100% {
        transform: translateY(0) rotate(0deg);
      }

      50% {
        transform: translateY(-20px) rotate(10deg);
      }
    }

    /* Additional badges */
    .badge-new {
      background-color: #bfdbfe;
      color: #1e40af;
    }

    .badge-bestseller {
      background-color: #fbcfe8;
      color: #9d174d;
    }

    .badge-organic {
      background-color: #bbf7d0;
      color: #166534;
    }

    .badge-limited {
      background-color: #fef08a;
      color: #854d0e;
    }

    .info-card {
      transition: all 0.3s ease;
      border-left: 4px solid #2E8B57;
    }

    .info-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 15px rgba(46, 204, 113, 0.1);
    }

    @media (max-width: 768px) {
      .mobile-filter-btn {
        display: block;
      }

      .filter-section {
        position: fixed;
        top: 0;
        left: -100%;
        width: 80%;
        height: 100vh;
        background: white;
        z-index: 40;
        padding: 1rem;
        overflow-y: auto;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
      }

      .filter-section.active {
        left: 0;
      }

      .filter-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 30;
      }

      .filter-overlay.active {
        display: block;
      }
    }
  </style>
</head>

<body>
  <!-- Hero Section -->
  <div class="bg-gradient-to-r from-green-500 to-green-700 py-16 mt-9 text-white relative overflow-hidden">
    <!-- Animated plant emojis -->
    <span class="plant-emoji" style="left:10%; top:20%; animation-delay:0s">🌱</span>
    <span class="plant-emoji" style="left:25%; top:30%; animation-delay:1s">🌿</span>
    <span class="plant-emoji" style="left:40%; top:20%; animation-delay:2s">🍃</span>
    <span class="plant-emoji" style="left:60%; top:30%; animation-delay:3s">🌵</span>
    <span class="plant-emoji" style="left:75%; top:20%; animation-delay:0s">🌸</span>
    <span class="plant-emoji" style="left:90%; top:30%; animation-delay:1s">🌻</span>
    <span class="plant-emoji" style="left:15%; top:40%; animation-delay:2s">🌼</span>
    <span class="plant-emoji" style="left:35%; top:50%; animation-delay:0s">🌷</span>
    <span class="plant-emoji" style="left:55%; top:40%; animation-delay:1s">🌺</span>
    <span class="plant-emoji" style="left:75%; top:50%; animation-delay:2s">🌾</span>
    <span class="plant-emoji" style="left:85%; top:60%; animation-delay:0s">🌳</span>
    <span class="plant-emoji" style="left:5%; top:70%; animation-delay:1s">🌲</span>
    <span class="plant-emoji" style="left:20%; top:80%; animation-delay:2s">🌴</span>
    <span class="plant-emoji" style="left:45%; top:70%; animation-delay:0s">🌱</span>

    <div class="container mx-auto px-4 text-center relative z-10">
      <h1 class="text-4xl font-bold mb-4">Discover Your Green Paradise</h1>
      <p class="text-xl max-w-2xl mx-auto">Find the perfect plants and tools to bring life to your space. Quality guaranteed.</p>
    </div>
  </div>

  <!-- Info Cards Section -->
  <div class="container mx-auto px-4 py-8 grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="info-card bg-white p-6 rounded-lg shadow-sm">
      <div class="flex items-center mb-3">
        <div class="bg-green-100 p-3 rounded-full mr-4">
          <i class="fas fa-seedling text-green-600 text-xl"></i>
        </div>
        <h3 class="font-semibold text-lg">Quality Plants</h3>
      </div>
      <p class="text-gray-600">Locally grown with care for optimal health and longevity.</p>
    </div>

    <div class="info-card bg-white p-6 rounded-lg shadow-sm">
      <div class="flex items-center mb-3">
        <div class="bg-green-100 p-3 rounded-full mr-4">
          <i class="fas fa-truck text-green-600 text-xl"></i>
        </div>
        <h3 class="font-semibold text-lg">Fast Shipping</h3>
      </div>
      <p class="text-gray-600">Carefully packaged and delivered within 2-3 business days.</p>
    </div>

    <div class="info-card bg-white p-6 rounded-lg shadow-sm">
      <div class="flex items-center mb-3">
        <div class="bg-green-100 p-3 rounded-full mr-4">
          <i class="fas fa-headset text-green-600 text-xl"></i>
        </div>
        <h3 class="font-semibold text-lg">Expert Support</h3>
      </div>
      <p class="text-gray-600">Our plant specialists are here to help you succeed.</p>
    </div>
  </div>


  <!-- Main Content -->
  <div class="container mx-auto px-4 py-8">
    <!-- Mobile Filter Button -->
    <button id="mobileFilterBtn" class="mobile-filter-btn bg-green-600 text-white px-4 py-2 rounded-md mb-4 flex items-center">
      <i class="fas fa-filter mr-2"></i> Filters
    </button>

    <!-- Filter Overlay (Mobile) -->
    <div id="filterOverlay" class="filter-overlay"></div>

    <div class="flex flex-col md:flex-row gap-8">
      <!-- Filter Section -->
      <div id="filterSection" class="filter-section md:w-1/4">
        <div class="flex justify-between items-center mb-4 md:hidden">
          <h2 class="text-xl font-bold">Filters</h2>
          <button id="closeFilterBtn" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times text-2xl"></i>
          </button>
        </div>

        <form method="GET" class="space-y-6">
          <!-- Search -->
          <div class="space-y-2">
            <h3 class="font-semibold">Search</h3>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
              class="w-full px-3 py-2 border border-gray-300 rounded-md"
              placeholder="Search products...">
          </div>

          <!-- Categories -->
          <div class="space-y-2">
            <h3 class="font-semibold">Categories</h3>
            <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-md">
              <option value="all">All Categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Sort -->
          <div class="space-y-2">
            <h3 class="font-semibold">Sort By</h3>
            <select name="sort" class="w-full px-3 py-2 border border-gray-300 rounded-md">
              <option value="default" <?= $sort === 'default' ? 'selected' : '' ?>>Default</option>
              <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
              <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
              <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
              <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A-Z</option>
              <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name: Z-A</option>
            </select>
          </div>

          <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-md hover:bg-green-700 transition">
            Apply Filters
          </button>

          <a href="shop.php" class="block text-center text-gray-600 hover:text-gray-800">
            Reset Filters
          </a>
        </form>
      </div>

      <!-- Products Section -->
      <div class="md:w-3/4">
        <!-- Results Info -->
        <div class="flex justify-between items-center mb-6">
          <div>
            Showing <?= ($page - 1) * $perPage + 1 ?>-<?= min($page * $perPage, $totalProducts) ?> of <?= $totalProducts ?> products
          </div>
          <div class="hidden md:block">
            <span class="text-gray-600">Sort:</span>
            <select id="sortSelect" class="ml-2 border border-gray-300 rounded-md px-2 py-1">
              <option value="default" <?= $sort === 'default' ? 'selected' : '' ?>>Default</option>
              <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
              <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
              <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
              <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A-Z</option>
              <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name: Z-A</option>
            </select>
          </div>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
          <div class="text-center py-12">
            <i class="fas fa-seedling text-4xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-600">No products found</h3>
            <p class="text-gray-500 mt-2">Try adjusting your search or filter criteria</p>
            <a href="shop.php" class="inline-block mt-4 text-green-600 hover:text-green-800">
              Clear all filters
            </a>
          </div>
        <?php else: ?>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($products as $product): ?>
              <!-- Updated Product Card with Enhanced Discount Badge -->
              <div class="product-card bg-white rounded-lg overflow-hidden border border-gray-100">
                <div class="relative">
                  <a href="product.php?id=<?= $product['id'] ?>">
                    <img src="<?= !empty($product['primary_image']) ? 'uploads/products/' . htmlspecialchars($product['primary_image']) : 'images/placeholder-product.jpg' ?>"
                      alt="<?= htmlspecialchars($product['name']) ?>"
                      class="w-full h-50 object-cover transition-transform duration-300 hover:scale-105">
                  </a>

                  <!-- Badges -->
                  <div class="absolute top-2 left-2 space-y-1">
                    <?php if ($product['is_featured']): ?>
                      <span class="badge badge-featured">
                        <i class="fas fa-star mr-1"></i> Featured
                      </span>
                    <?php endif; ?>

                    <?php if ($product['discount_price']):
                      $discountPercent = round(($product['price'] - $product['discount_price']) / $product['price'] * 100);
                    ?>
                      <span class="badge badge-discount flex items-center">
                        <i class="fas fa-tag mr-1"></i> <?= $discountPercent ?>% OFF
                      </span>
                    <?php endif; ?>

                    <span class="badge badge-<?= $product['product_type'] ?>">
                      <?= ucfirst($product['product_type']) ?>
                    </span>
                  </div>

                  <!-- Quick actions -->
                  <div class="absolute top-2 right-2">
                    <button class="bg-white rounded-full p-2 text-gray-600 hover:text-green-600 shadow-sm">
                      <i class="far fa-heart"></i>
                    </button>
                  </div>
                </div>

                <div class="p-4">
                  <div class="flex justify-between items-start">
                    <div>
                      <a href="product.php?id=<?= $product['id'] ?>" class="font-semibold text-lg hover:text-green-600">
                        <?= htmlspecialchars($product['name']) ?>
                      </a>
                      <p class="text-gray-500 text-sm mt-1"><?= htmlspecialchars($product['category_name'] ?? '') ?></p>
                    </div>

                    <div class="text-right">
                      <?php if ($product['discount_price']): ?>
                        <span class="text-green-600 font-bold">$<?= number_format($product['discount_price'], 2) ?></span>
                        <span class="block text-sm text-gray-400 line-through">$<?= number_format($product['price'], 2) ?></span>
                      <?php else: ?>
                        <span class="text-green-600 font-bold">$<?= number_format($product['price'], 2) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="mt-4 flex justify-between items-center">
                    <span class="text-sm <?= $product['stock'] > 5 ? 'text-green-600' : ($product['stock'] > 0 ? 'text-yellow-600' : 'text-red-600') ?>">
                      <?= $product['stock'] > 5 ? 'In Stock' : ($product['stock'] > 0 ? 'Low Stock' : 'Out of Stock') ?>
                    </span>

                    <button class="add-to-cart-btn bg-green-600 text-white px-3 py-1 rounded-md text-sm hover:bg-green-700 transition 
              <?= $product['stock'] <= 0 ? 'opacity-50 cursor-not-allowed' : '' ?>"
                      data-product-id="<?= $product['id'] ?>"
                      data-stock="<?= $product['stock'] ?>"
                      <?= $product['stock'] <= 0 ? 'disabled' : '' ?>>
                      <i class="fas fa-cart-plus mr-1"></i> Add to Cart
                    </button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Pagination -->
          <?php if ($totalPages > 1): ?>
            <div class="mt-8 flex justify-center">
              <nav class="flex items-center space-x-2">
                <?php if ($page > 1): ?>
                  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                    class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50">
                    &laquo; Prev
                  </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);

                if ($start > 1) {
                  echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50">1</a>';
                  if ($start > 2) {
                    echo '<span class="px-3 py-1">...</span>';
                  }
                }

                for ($i = $start; $i <= $end; $i++): ?>
                  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                    class="px-3 py-1 border rounded-md <?= $i == $page ? 'bg-green-600 text-white border-green-600' : 'border-gray-300 hover:bg-gray-50' ?>">
                    <?= $i ?>
                  </a>
                <?php endfor;

                if ($end < $totalPages) {
                  if ($end < $totalPages - 1) {
                    echo '<span class="px-3 py-1">...</span>';
                  }
                  echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $totalPages])) . '" class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50">' . $totalPages . '</a>';
                }
                ?>

                <?php if ($page < $totalPages): ?>
                  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                    class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50">
                    Next &raquo;
                  </a>
                <?php endif; ?>
              </nav>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <?php include 'footer.php'; ?>

  <script>
    // Mobile filter toggle
    document.getElementById('mobileFilterBtn').addEventListener('click', function() {
      document.getElementById('filterSection').classList.add('active');
      document.getElementById('filterOverlay').classList.add('active');
    });

    document.getElementById('closeFilterBtn').addEventListener('click', function() {
      document.getElementById('filterSection').classList.remove('active');
      document.getElementById('filterOverlay').classList.remove('active');
    });

    document.getElementById('filterOverlay').addEventListener('click', function() {
      document.getElementById('filterSection').classList.remove('active');
      this.classList.remove('active');
    });

    // Sort select change
    document.getElementById('sortSelect')?.addEventListener('change', function() {
      const url = new URL(window.location.href);
      url.searchParams.set('sort', this.value);
      window.location.href = url.toString();
    });

    // Add to cart functionality
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
      button.addEventListener('click', function(e) {
        e.preventDefault();
        const productId = this.dataset.productId;
        const stock = parseInt(this.dataset.stock);

        if (stock <= 0) {
          alert('This product is out of stock');
          return;
        }

        // Show loading state
        const originalText = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Adding...';
        this.disabled = true;

        // Make AJAX request
        fetch('ajax_add_to_cart.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${productId}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Update ALL cart count elements in the page
              document.querySelectorAll('.cart-count').forEach(el => {
                el.textContent = data.cart_count;
                el.classList.remove('hidden');
              });

              // Show success message
              const toast = document.createElement('div');
              toast.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-md shadow-lg flex items-center';
              toast.innerHTML = `
          <i class="fas fa-check-circle mr-2"></i>
          <span>Item added to cart</span>
        `;
              document.body.appendChild(toast);

              // Remove toast after 3 seconds
              setTimeout(() => {
                toast.classList.add('opacity-0', 'transition-opacity', 'duration-300');
                setTimeout(() => toast.remove(), 300);
              }, 3000);
            } else {
              alert(data.message);
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while adding to cart');
          })
          .finally(() => {
            // Restore button state
            this.innerHTML = originalText;
            this.disabled = false;
          });
      });
    });
  </script>
</body>

</html>