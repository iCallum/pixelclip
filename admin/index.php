<?php
$USER = "admin";
$PASS = "changeme";

if (!isset($_SERVER['PHP_AUTH_USER']) ||
    $_SERVER['PHP_AUTH_USER'] !== $USER ||
    $_SERVER['PHP_AUTH_PW'] !== $PASS) {
    header('WWW-Authenticate: Basic realm="PixelClip Admin"');
    header('HTTP/1.0 401 Unauthorized');
    echo "Auth required";
    exit;
}

$dir = __DIR__ . "/../i/";
$files = array_diff(scandir($dir), ['.', '..']);

?>
<!DOCTYPE html>
<html>
<head>
<title>PixelClip Admin</title>
<style>
body { font-family: Arial; background:#fafafa; padding:40px; }
table { width:100%; border-collapse: collapse; }
td, th { padding: 10px; border-bottom: 1px solid #ddd; }
</style>
</head>
<body>
<h2>PixelClip Admin Panel</h2>
<table>
<tr><th>Image</th><th>Actions</th></tr>
<?php foreach ($files as $f): ?>
<tr>
<td><a href="../i/<?php echo $f; ?>" target="_blank"><?php echo $f; ?></a></td>
<td><a href="?delete=<?php echo $f; ?>" onclick="return confirm('Delete?')">Delete</a></td>
</tr>
<?php endforeach; ?>
</table>
<?php
if (isset($_GET['delete'])) {
    unlink($dir . $_GET['delete']);
    header("Location: index.php");
}
?>
</body>
</html>