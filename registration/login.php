<?php
session_start();
require "db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user["password"])) {
                $user_primary_key = isset($user["user_id"]) ? $user["user_id"] : $user["id"];
                
                session_regenerate_id(true);
                $_SESSION["user_id"]   = $user_primary_key;
                $_SESSION["username"]  = $user["username"];
                $_SESSION["branch_id"] = $user["branch_id"];
                $_SESSION["role"]      = $user["role"];

                header("Location: index.php");
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PANABO COOP | System Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --royal-blue: #004aad;
            --dark-blue: #002d6b;
            --accent-green: #00ff88;
            --glass-bg: rgba(255, 255, 255, 0.9);
            --text-main: #1e293b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }

        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            /* Animated gradient background */
            background: linear-gradient(-45deg, #002d6b, #004aad, #1e293b, #0f172a);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
            overflow: hidden;
        }

        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* ===== Login Card ===== */
        .login-card {
            background: var(--glass-bg);
            width: 100%;
            max-width: 420px;
            padding: 50px 40px;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            position: relative;
        }

        .login-card::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; height: 6px;
            background: linear-gradient(90deg, var(--royal-blue), var(--accent-green));
            border-radius: 24px 24px 0 0;
        }

        .brand-logo {
            font-size: 28px;
            font-weight: 800;
            color: var(--dark-blue);
            margin-bottom: 8px;
            letter-spacing: -1px;
        }

        .sub-text {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 35px;
        }

        /* ===== Form Groups ===== */
        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
            margin-left: 4px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            font-size: 15px;
            transition: all 0.3s ease;
            color: var(--text-main);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--royal-blue);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(0, 74, 173, 0.1);
        }

        /* ===== Button ===== */

        .btn-back{
    position:fixed;
    top:25px;
    left:25px;
    border:none;
    background:white;
    color:#1e293b;
    padding:10px 18px;
    border-radius:30px;
    font-weight:600;
    text-decoration:none;
}
        .btn-login {
            width: 100%;
            padding: 15px;
            background: var(--royal-blue);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .btn-login:hover {
            background: var(--dark-blue);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 74, 173, 0.3);
        }

        .btn-login:active { transform: translateY(0); }

        /* ===== Error Handling ===== */
        .error-box {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
            border: 1px solid #fee2e2;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ===== Decorative Footer ===== */
        .login-footer {
            margin-top: 30px;
            font-size: 12px;
            color: #94a3b8;
        }

        /* Responsive Mobile adjustment */
        @media (max-width: 480px) {
            .login-card { padding: 40px 25px; width: 95%; }
        }
    </style>
</head>
<body>

<a href="../index.php" class="btn-back">
← Back
</a>


<div class="login-card">
    <div class="brand-logo">PANABO COOP 2026</div>
    <div class="sub-text">Please sign in to access the system</div>

    <?php if ($error): ?>
        <div class="error-box">
            <span>⚠️</span> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="Enter your username" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-login">
            SIGN IN
        </button>
    </form>

    <div class="login-footer">
        &copy; <?php echo date("Y"); ?> Panabo Multi-Purpose Cooperative.<br>
        Attendance & Allowance System
    </div>
</div>

</body>
</html>