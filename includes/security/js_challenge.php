<?php
/**
 * Browser Verification Page
 * Part of the advanced security layer to block non-browser bots.
 */
if (!defined('EXECUTION_ALLOWED')) exit;

// We use a simple JS redirect with a token
$token = bin2hex(random_bytes(16));
$_SESSION['js_verify_token'] = $token;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifying your browser...</title>
    <style>
        body { background: #0f172a; color: white; font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .box { text-align: center; background: rgba(30, 41, 59, 0.8); padding: 40px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .spinner { width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.1); border-top-color: #3b82f6; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        h1 { font-size: 1.5rem; margin-bottom: 10px; }
        p { color: #94a3b8; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="box">
        <div class="spinner"></div>
        <h1>Checking your browser...</h1>
        <p>Please wait while we verify your connection is secure.</p>
        <form id="jsForm" method="POST" style="display:none;">
            <input type="hidden" name="js_challenge_solved" value="1">
            <input type="hidden" name="challenge_token" value="<?php echo $token; ?>">
        </form>
    </div>
    <script>
        setTimeout(function() {
            document.getElementById('jsForm').submit();
        }, 1000);
    </script>
</body>
</html>
