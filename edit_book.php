<?php
session_start();
require '../includes/db.php';
require '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($book_id <= 0) {
    redirect_with_message('manage_books.php', 'Invalid book ID', 'error');
}

// Get book details
$book = get_book_by_id($conn, $book_id);

if (!$book) {
    redirect_with_message('manage_books.php', 'Book not found', 'error');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize input data
    $title = sanitize_input($_POST['title']);
    $author = sanitize_input($_POST['author']);
    $genre = sanitize_input($_POST['genre']);
    $price = floatval($_POST['price']);
    $description = sanitize_input($_POST['description']);
    $stock = intval($_POST['stock_quantity']);
    
    // Validate data
    $validation_errors = validate_book_data([
        'title' => $title,
        'author' => $author,
        'price' => $price,
        'stock_quantity' => $stock
    ]);
    
    $errors = array_merge($errors, $validation_errors);
    
    // Handle image upload
    $cover_image = $book['cover_image']; // Keep existing image by default
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_result = upload_book_cover($_FILES['cover_image']);
        if ($upload_result['success']) {
            // Delete old image if it exists
            if (!empty($book['cover_image']) && file_exists('../' . $book['cover_image'])) {
                unlink('../' . $book['cover_image']);
            }
            $cover_image = $upload_result['filename'];
        } else {
            $errors[] = $upload_result['message'];
        }
    }
    
    // If no errors, update the book
    if (empty($errors)) {
        try {
            // PDO prepared statement - remove bind_param
            $stmt = $conn->prepare("UPDATE books SET title=?, author=?, genre=?, price=?, description=?, stock_quantity=?, cover_image=? WHERE book_id=?");
            
            // PDO execute with array of parameters
            if ($stmt->execute([$title, $author, $genre, $price, $description, $stock, $cover_image, $book_id])) {
                // Check if any rows were actually updated
                if ($stmt->rowCount() > 0) {
                    redirect_with_message('manage_books.php', 'Book "' . htmlspecialchars($title) . '" updated successfully!', 'success');
                } else {
                    // No rows updated - book might not exist or no changes made
                    redirect_with_message('manage_books.php', 'No changes were made to the book.', 'info');
                }
            } else {
                $errors[] = "Failed to update book. Please try again.";
            }
        } catch (PDOException $e) {
            // PDO-specific exception handling
            error_log("Book update error: " . $e->getMessage());
            $errors[] = "Database error: Unable to update book. Please try again.";
        } catch (Exception $e) {
            // Generic exception handling
            error_log("Unexpected error during book update: " . $e->getMessage());
            $errors[] = "Unexpected error occurred. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Book - BookNest Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="container">
            <div class="admin-nav">
                <div class="logo">📚 BookNest Admin</div>
                <a href="dashboard.php">Dashboard</a>
                <a href="manage_books.php">Manage Books</a>
                <a href="orders.php">Orders</a>
                <a href="../books.php">View Site</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="form-container">
            <h2>Edit Book: <?php echo htmlspecialchars($book['title']); ?></h2>
            
            <!-- Display errors -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Display session messages -->
            <?php echo display_session_message(); ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Book Title *</label>
                    <input type="text" id="title" name="title" required 
                           value="<?php echo htmlspecialchars($book['title']); ?>">
                </div>

                <div class="form-group">
                    <label for="author">Author *</label>
                    <input type="text" id="author" name="author" required 
                           value="<?php echo htmlspecialchars($book['author']); ?>">
                </div>

                <div class="form-group">
                    <label for="genre">Genre</label>
                    <input type="text" id="genre" name="genre" 
                           value="<?php echo htmlspecialchars($book['genre']); ?>"
                           placeholder="e.g., Fiction, Non-fiction, Mystery, Romance">
                </div>

                <div class="form-group">
                    <label for="price">Price (Rs.) *</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" required 
                           value="<?php echo $book['price']; ?>">
                </div>

                <div class="form-group">
                    <label for="stock_quantity">Stock Quantity *</label>
                    <input type="number" id="stock_quantity" name="stock_quantity" min="0" required 
                           value="<?php echo $book['stock_quantity']; ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="5" 
                              placeholder="Enter book description, summary, or key features..."><?php echo htmlspecialchars($book['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="cover_image">Book Cover Image</label>
                    
                    <!-- Current image preview -->
                    <?php if (!empty($book['cover_image']) && file_exists('../' . $book['cover_image'])): ?>
                        <div style="margin-bottom: 15px;">
                            <p><strong>Current Image:</strong></p>
                            <img src="../<?php echo htmlspecialchars($book['cover_image']); ?>" 
                                 alt="Current cover" style="max-width: 150px; max-height: 200px; border-radius: 5px; border: 2px solid #ddd;">
                        </div>
                    <?php endif; ?>
                    
                    <div class="file-upload" id="fileUploadArea" style="cursor:pointer;">
                        <input type="file" id="cover_image" name="cover_image" accept="image/*" style="display:none;">
                        <div class="file-upload-text" id="fileUploadText">
                            📷 Click to upload new book cover image<br>
                            <small>Supported formats: JPG, PNG, GIF (Max 5MB)</small>
                            <?php if (!empty($book['cover_image'])): ?>
                                <br><small>Leave empty to keep current image</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-success">Update Book</button>
                    <a href="manage_books.php" class="btn btn-secondary">Cancel</a>
                    <a href="../book_details.php?id=<?php echo $book_id; ?>" class="btn btn-primary" target="_blank">Preview Book</a>
                </div>
            </form>
            
            <!-- Danger Zone -->
            <div style="margin-top: 40px; padding: 20px; background: #fff5f5; border: 1px solid #feb2b2; border-radius: 5px;">
                <h3 style="color: #e53e3e; margin-bottom: 10px;">⚠️ Danger Zone</h3>
                <p style="margin-bottom: 15px; color: #666;">Once you delete a book, there is no going back. Please be certain.</p>
                <a href="delete_books.php?id=<?php echo $book_id; ?>" 
                   class="btn btn-danger" 
                   onclick="return confirm('Are you sure you want to delete this book?\n\nBook: <?php echo addslashes($book['title']); ?>\nAuthor: <?php echo addslashes($book['author']); ?>\n\nThis action cannot be undone!');">
                    🗑️ Delete Book
                </a>
            </div>
        </div>
    </div>

    <script>
        // Make the file upload area clickable
        document.getElementById('fileUploadArea').addEventListener('click', function() {
            document.getElementById('cover_image').click();
        });

        // File upload preview
        document.getElementById('cover_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const uploadText = document.getElementById('fileUploadText');
            
            if (file) {
                uploadText.innerHTML = `📷 Selected: ${file.name}<br><small>Size: ${(file.size / 1024 / 1024).toFixed(2)} MB</small>`;
            } else {
                uploadText.innerHTML = '📷 Click to upload new book cover image<br><small>Supported formats: JPG, PNG, GIF (Max 5MB)</small><?php if (!empty($book['cover_image'])): ?><br><small>Leave empty to keep current image</small><?php endif; ?>';
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const price = document.getElementById('price').value;
            const stock = document.getElementById('stock_quantity').value;
            
            if (price < 0) {
                alert('Price cannot be negative');
                e.preventDefault();
                return;
            }
            
            if (stock < 0) {
                alert('Stock quantity cannot be negative');
                e.preventDefault();
                return;
            }
            
            // Confirm update
            if (!confirm('Are you sure you want to update this book?')) {
                e.preventDefault();
                return;
            }
        });

        // Auto-save draft functionality (optional)
        let autoSaveTimer;
        function autoSave() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                // You could implement auto-save functionality here
                console.log('Auto-save triggered');
            }, 5000);
        }

        // Trigger auto-save on input changes
        document.querySelectorAll('input, textarea').forEach(element => {
            element.addEventListener('input', autoSave);
        });
    </script>
</body>
</html>