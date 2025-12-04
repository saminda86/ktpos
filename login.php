<?php
// Session ආරම්භ කිරීම
session_start();
// පරිශීලකයෙක් දැනටමත් ලොග් වී ඇත්නම් Dashboard එකට යොමු කිරීම
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// දත්ත සමුදා සම්බන්ධතාවය ඇතුළත් කිරීම
require_once 'db_connect.php';

$login_error = '';

// Login කිරීමේ Logic එක
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // දත්ත සමුදා සම්බන්ධතාවය නැවත විවෘත කිරීම
    // ⚠️ db_connect.php තුළ $conn වසා ඇත්නම් මෙය අවශ්‍ය වේ.
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // සම්බන්ධතාවය සාර්ථක දැයි පරීක්ෂා කිරීම 
    if ($conn->connect_error) {
        // දත්ත සමුදා දෝෂයක් ඇත්නම් එය Session එකට දමයි
        $_SESSION['login_error'] = "Database connection error.";
        header('Location: login.php');
        exit();
    }

    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    // පරිශීලකයා දත්ත සමුදායෙන් සොයා ගැනීම
    $sql = "SELECT user_id, username, password, name, user_type FROM users WHERE username = ? AND status = 'Active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // මුරපදය පරීක්ෂා කිරීම (Password verification)
        if (password_verify($password, $user['password'])) {
            // ලොග් වීම සාර්ථකයි. Session විචල්‍යයන් සැකසීම
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['user_type'] = $user['user_type'];

            // Dashboard එකට යොමු කිරීම
            header('Location: dashboard.php');
            exit();
        } else {
            // වැරදි මුරපදය - Session එක තුළ error message ගබඩා කිරීම
            $_SESSION['login_error'] = "Invalid Username or Password. Please try again.";
            header('Location: login.php'); // Redirect to clear POST data
            exit();
        }
    } else {
        // පරිශීලකයා සොයා ගැනීමට නොහැකි වීම - Session එක තුළ error message ගබඩා කිරීම
        $_SESSION['login_error'] = "Invalid Username or Password. Please try again.";
            header('Location: login.php'); // Redirect to clear POST data
            exit();
    }

    $stmt->close();
    $conn->close(); 
}

// Session එකෙන් error message එක ලබා ගැනීම
if (isset($_SESSION['login_error'])) {
    $login_error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KAWDU TECHNOLOGY | Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@400;600;700&family=Roboto:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* වර්ණ තේමාව styles.css වෙතින් ලබා ගනී */
        :root {
            --primary-color: #27b19d; /* Turquoise Green */
            --accent-color: #1e8779;  
            --light-grey-bg: #f0f0f0; 
            --form-bg: #ffffff; 
        }
        
        body {
            background-color: var(--light-grey-bg); 
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            /* 🟢 Google Fonts යෙදීම */
            font-family: 'Noto Sans Sinhala', 'Roboto', sans-serif;
            font-size: 14px; 
        }
        
        .login-container {
            max-width: 900px; 
            width: 90%;
            display: flex; 
            background-color: var(--form-bg);
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15); 
            overflow: hidden; 
        }

        /* 1. වම් පැත්තේ විස්තර කොටස (Green Theme) */
        .info-section {
            flex: 1; 
            padding: 40px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%); 
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .info-section img {
            max-width: 150px;
            margin-bottom: 20px; 
            border-radius: 0; 
            box-shadow: none; 
        }
        .info-section h3 {
            font-weight: 800;
            font-size: 1.8rem;
            margin-bottom: 2px; 
            line-height: 1.2; 
        }
        /* Tagline - පරතරය අඩු කර ඇත */
        .info-section .tagline {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-top: 5px; 
            margin-bottom: 20px;
            line-height: 1.4; 
        }
        
        /* නව Software Footer Style - Highlight කිරීම */
        .software-footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.4);
            width: 100%;
            font-size: 0.85rem;
            font-weight: 600;
            opacity: 0.9;
            color: #ffeaa7; 
        }

        /* 2. දකුණු පැත්තේ Login Form එක */
        .login-form-section {
            flex: 1; 
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-form-section h4 {
            color: var(--primary-color); 
            margin-bottom: 30px;
            font-weight: 700;
            text-align: center;
        }
        
        /* Input/Button Styles */
        .form-control {
            border-radius: 8px;
            height: 50px;
            border: 1px solid #ced4da;
            transition: border-color 0.3s;
            background-color: var(--light-grey-bg); 
        }
        .form-control:focus {
            border-color: var(--primary-color); 
            box-shadow: 0 0 0 0.25rem rgba(39, 177, 157, 0.25); 
        }
        .btn-primary {
            background-color: var(--primary-color); 
            border-color: var(--primary-color);
            font-weight: 600;
            height: 50px;
            border-radius: 8px;
            transition: background-color 0.3s, transform 0.2s;
        }
        .btn-primary:hover {
            background-color: var(--accent-color); 
            border-color: var(--accent-color);
            transform: translateY(-1px);
        }

        /* කුඩා තිර සඳහා යාවත්කාලීන කිරීම */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 420px;
            }
            .info-section {
                padding: 30px;
            }
            .login-form-section {
                padding: 30px;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    
    <div class="info-section">
        <?php 
            // ලෝගෝවේ URL එක නිවැරදි මාර්ගය අනුව
            $logo_path = 'uploads/products/KAWDU technology FB LOGO.png'; 
        ?>
        <img src="<?php echo $logo_path; ?>" alt="KAWDU TECHNOLOGY Logo" class="img-fluid">
        
        <h3 class="mt-4 mb-0">KAWDU TECHNOLOGY</h3>
        <p class="tagline">Your trusted service partner!</p> 
        
        <div class="mt-4 pt-3 border-top border-light opacity-75 w-100">
            <p class="mb-1" style="line-height: 1.4;"><i class="fas fa-map-marker-alt"></i> 323'Waduwelivitiya(North), Kahaduwa</p>
            <p class="mb-0" style="line-height: 1.4;"><i class="fas fa-phone-alt"></i> 0776 228 943 | 0786 228 943</p>
        </div>
        
        <div class="software-footer">
            This software is designed by KAWDU TECHNOLOGY
        </div>
    </div>
    
    <div class="login-form-section">
        <h4>System Login</h4>
        
        <?php if ($login_error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>දෝෂයක්!</strong> <?php echo htmlspecialchars($login_error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" required placeholder="Enter your username">
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required placeholder="Enter your password">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">LOGIN</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>