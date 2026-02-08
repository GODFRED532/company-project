<!DOCTYPE html>
<html>
<head>
    <title>URL Test</title>
</head>
<body>
    <h1>URL Test Page</h1>
    
    <h2>Test Links:</h2>
    <ul>
        <li><a href="sales_history.php">Direct Link to Sales History</a></li>
        <li><a href="sales.php">Link to sales.php (should redirect)</a></li>
        <li><a href="index.php">Back to Dashboard</a></li>
    </ul>
    
    <h2>Current URL Information:</h2>
    <p><strong>Current URL:</strong> <?php echo $_SERVER['REQUEST_URI']; ?></p>
    <p><strong>Server Name:</strong> <?php echo $_SERVER['SERVER_NAME']; ?></p>
    <p><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?></p>
    
    <h2>File Check:</h2>
    <p>sales_history.php exists: <?php echo file_exists('sales_history.php') ? '✅ Yes' : '❌ No'; ?></p>
    <p>sales.php exists: <?php echo file_exists('sales.php') ? '✅ Yes' : '❌ No'; ?></p>
    
    <script>
        console.log('Current URL:', window.location.href);
        console.log('Current pathname:', window.location.pathname);
    </script>
</body>
</html>
