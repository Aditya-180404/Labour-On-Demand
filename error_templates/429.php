<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Blocked - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            margin: 0;
        }
        .error-card {
            max-width: 500px;
            width: 90%;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 5px solid #dc3545;
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            background: #fff5f5;
            color: #dc3545;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 25px;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        p {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .btn-retry {
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .ref-code {
            font-size: 0.75rem;
            color: #adb5bd;
            margin-top: 30px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="icon-circle">
            <i class="fas fa-shield-alt"></i>
        </div>
        <h1>Security Protocol Enabled</h1>
        <p>
            Our systems have detected an unusual number of requests from your connection. 
            To ensure the stability of the platform, your access has been temporarily limited.
        </p>
        <div class="alert alert-danger py-2 rounded-pill small">
            Please try again in about 5 minutes.
        </div>
        <button onclick="window.location.reload()" class="btn btn-primary btn-retry mt-3">
            <i class="fas fa-sync-alt me-2"></i>Check Again
        </button>
        <div class="ref-code">
            REF: ANTI_DDOS_THROTTLE_429
        </div>
    </div>
</body>
</html>
