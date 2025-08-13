
<?php
$conn = new mysqli("localhost", "root", "", "lms_melbourne");

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
echo "✅ Connection successful!";
?>
