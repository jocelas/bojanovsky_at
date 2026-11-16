<?php
session_start();

// Password hash (bcrypt) for "lauraisttoll"
$password_hash = password_hash('lauraisttoll', PASSWORD_BCRYPT);

// If already authenticated, redirect to wrapped page
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header('Location: wrapped.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting via session
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['first_attempt_time'] = time();
    }

    // Reset counter after 15 minutes
    if (time() - $_SESSION['first_attempt_time'] > 900) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['first_attempt_time'] = time();
    }

    $_SESSION['login_attempts']++;

    if ($_SESSION['login_attempts'] > 10) {
        $error = 'Too many attempts. Please try again later.';
    } else {
        $input = isset($_POST['password']) ? $_POST['password'] : '';

        // Verify against known password
        if ($input === 'lauraisttoll') {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['login_attempts'] = 0;
            header('Location: wrapped.php');
            exit;
        } else {
            $error = 'Wrong password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<title>Chat Wrapped 2025</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&display=swap');
:root{--accent:#1DB954;--bg1:#0d0d0d;--pink:#FF6B9D}
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;font-family:'Montserrat',sans-serif;background:var(--bg1);color:#fff}
body{display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0d0d0d 0%,#064e3b 100%);min-height:100vh;padding:20px}
.login-card{
  background:rgba(255,255,255,.05);
  backdrop-filter:blur(20px);
  -webkit-backdrop-filter:blur(20px);
  border:1px solid rgba(255,255,255,.1);
  border-radius:24px;
  padding:48px 40px;
  max-width:400px;
  width:100%;
  text-align:center;
}
.login-card h1{
  font-size:clamp(1.6rem,5vw,2.4rem);
  font-weight:900;
  text-transform:uppercase;
  letter-spacing:-1px;
  line-height:1.1;
  margin-bottom:8px;
  background:linear-gradient(135deg,var(--accent),var(--pink));
  -webkit-background-clip:text;
  -webkit-text-fill-color:transparent;
  background-clip:text;
}
.login-card h2{
  font-size:clamp(.9rem,2.5vw,1.2rem);
  font-weight:700;
  opacity:.7;
  margin-bottom:32px;
}
.login-card form{display:flex;flex-direction:column;gap:16px}
.login-card input[type="password"]{
  width:100%;
  padding:14px 20px;
  border:2px solid rgba(255,255,255,.15);
  border-radius:14px;
  background:rgba(255,255,255,.07);
  color:#fff;
  font-family:'Montserrat',sans-serif;
  font-size:1.05rem;
  font-weight:700;
  outline:none;
  text-align:center;
  backdrop-filter:blur(10px);
  transition:border-color .3s;
}
.login-card input[type="password"]::placeholder{color:rgba(255,255,255,.35);font-weight:400}
.login-card input[type="password"]:focus{border-color:var(--accent)}
.login-card button{
  padding:14px 24px;
  border:none;
  border-radius:14px;
  background:var(--accent);
  color:#fff;
  font-family:'Montserrat',sans-serif;
  font-size:1.05rem;
  font-weight:900;
  text-transform:uppercase;
  letter-spacing:1px;
  cursor:pointer;
  transition:transform .2s,opacity .2s;
}
.login-card button:hover{transform:scale(1.03)}
.login-card button:active{transform:scale(.97);opacity:.8}
.error{
  color:#FF6B9D;
  font-size:.9rem;
  font-weight:700;
  margin-top:4px;
  min-height:1.2em;
}
.footer{
  margin-top:24px;
  font-size:.75rem;
  opacity:.3;
}
@media(max-width:600px){
  .login-card{padding:36px 24px;border-radius:20px}
  .login-card input[type="password"]{padding:12px 16px;font-size:.95rem}
  .login-card button{padding:12px 20px;font-size:.95rem}
}
@supports(padding:env(safe-area-inset-bottom)){
  body{padding-bottom:calc(20px + env(safe-area-inset-bottom))}
}
</style>
</head>
<body>
<div class="login-card">
  <h1>Chat Wrapped</h1>
  <h2>2025 &ndash; 2026</h2>
  <form method="POST" action="index.php" autocomplete="off">
    <input type="password" name="password" placeholder="Enter password" autofocus required>
    <button type="submit">Unlock &rarr;</button>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
  </form>
  <div class="footer">Jonas &amp; Laura</div>
</div>
</body>
</html>
