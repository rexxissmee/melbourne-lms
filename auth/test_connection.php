
<?php
$conn = new mysqli("localhost", "root", "", "lms_melbourne");

if ($conn->connect_error) {
    die("❌ Kết nối thất bại: " . $conn->connect_error);
}
echo "✅ Kết nối thành công!";
?>
