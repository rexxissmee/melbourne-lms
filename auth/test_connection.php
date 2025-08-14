
<?php
$conn = new mysqli("localhost", "root", "", "melbourne_lms");

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
echo "✅ Connection successful!";
?>
