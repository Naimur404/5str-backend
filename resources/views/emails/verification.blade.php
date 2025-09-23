<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - {{ $appName }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-top: 4px solid #007bff;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 28px;
        }
        .verification-code {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            font-size: 32px;
            font-weight: bold;
            text-align: center;
            padding: 25px;
            border-radius: 8px;
            margin: 30px 0;
            letter-spacing: 4px;
            font-family: 'Courier New', monospace;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }
        .info-box {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .warning-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
            color: #856404;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 14px;
        }
        .highlight {
            color: #007bff;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .security-note {
            font-size: 12px;
            color: #999;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Email Verification</h1>
        </div>
        
        <p>Hello <strong>{{ $name }}</strong>,</p>
        
        <p>Thank you for registering with <strong>{{ $appName }}</strong>! To complete your registration and secure your account, please verify your email address.</p>
        
        <div class="verification-code">
            {{ $code }}
        </div>
        
        <div class="info-box">
            <h3>How to verify:</h3>
            <ol>
                <li>Copy the verification code above</li>
                <li>Open your app and go to the verification screen</li>
                <li>Enter the code to verify your email</li>
            </ol>
        </div>
        
        <div class="warning-box">
            <strong>⏰ Important:</strong> This verification code will expire in <span class="highlight">20 minutes</span> (at {{ $expiresAt->format('Y-m-d H:i:s') }}). Please verify your email before it expires.
        </div>
        
        <p><strong>Security Notes:</strong></p>
        <ul>
            <li>This code is unique and can only be used once</li>
            <li>Never share this code with anyone</li>
            <li>You must verify your email within 20 minutes to activate your account</li>
            <li>Until verified, you won't be able to login to your account</li>
        </ul>
        
        <p>If you didn't create an account with us, please ignore this email or contact our support team if you have concerns.</p>
        
        <div class="footer">
            <p>This verification email was sent on {{ now()->format('Y-m-d H:i:s') }}</p>
            <p>From: {{ $appName }} Team</p>
            <div class="security-note">
                This is an automated message. Please do not reply to this email.
            </div>
        </div>
    </div>
</body>
</html>