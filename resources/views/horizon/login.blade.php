<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Horizon · Sign in</title>
    <meta name="robots" content="noindex,nofollow">
    <style>
        :root {
            color-scheme: dark;
            --bg: #0b1020;
            --card: #141a30;
            --border: #1f2742;
            --text: #e6edf3;
            --muted: #8b95a7;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --error-bg: #2a1620;
            --error-border: #6b2032;
            --error-text: #fda4af;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                "Helvetica Neue", Arial, sans-serif;
        }
        .wrap {
            min-height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 380px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }
        .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        }
        h1 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        h2 {
            font-size: 22px;
            font-weight: 600;
            margin: 0 0 6px;
        }
        p.sub {
            margin: 0 0 24px;
            color: var(--muted);
            font-size: 14px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            color: var(--muted);
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 11px 14px;
            background: #0f1525;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 14px;
            outline: none;
            transition: border-color 0.15s;
        }
        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: var(--accent);
        }
        .field { margin-bottom: 16px; }
        button {
            width: 100%;
            padding: 11px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s;
            margin-top: 8px;
        }
        button:hover { background: var(--accent-hover); }
        button:disabled { opacity: 0.6; cursor: not-allowed; }
        .errors {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .errors ul {
            margin: 0;
            padding-left: 18px;
        }
        .status {
            background: #142a1f;
            border: 1px solid #1d4030;
            color: #6ee7b7;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="brand">
                <span class="dot"></span>
                <h1>5str · Horizon</h1>
            </div>
            <h2>Sign in</h2>
            <p class="sub">Use your admin email and password.</p>

            @if (session('status'))
                <div class="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="errors">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('horizon.login.attempt') }}" autocomplete="off">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email"
                           value="{{ old('email') }}" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Sign in</button>
            </form>

            <div class="footer">Restricted area · Authorised admins only</div>
        </div>
    </div>
</body>
</html>
