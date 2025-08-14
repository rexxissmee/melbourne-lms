<?php
class Database
{
    private $host = 'localhost';
    private $db_name = 'lms_melbourne';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection()
    {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// Session configuration
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Helper functions
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function hasRole($role)
{
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function formatDate($date)
{
    return date('M j, Y g:i A', strtotime($date));
}

function sanitizeInput($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Render forum text with simple formatting.
 * Supports [b]bold[/b], [i]italic[/i], [u]underline[/u], and **bold** syntax.
 * New lines are converted to <br>.
 * Only a safe allowlist of tags is kept.
 */
function renderForumText(string $text): string
{
    // Basic BBCode style replacements
    $converted = $text;
    $converted = preg_replace('/\[b\](.*?)\[\/b\]/is', '<strong>$1</strong>', $converted);
    $converted = preg_replace('/\[i\](.*?)\[\/i\]/is', '<em>$1</em>', $converted);
    $converted = preg_replace('/\[u\](.*?)\[\/u\]/is', '<u>$1</u>', $converted);
    // Markdown-like bold **text**
    $converted = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $converted);

    // Strip all tags except the allowed ones, then convert new lines
    $safe = strip_tags($converted, '<strong><em><u><br>');
    return nl2br($safe);
}
