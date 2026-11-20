<?php
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Strict');

session_start();

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    redirect('/');
}

$error = '';

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$pdo->exec(
    'DELETE FROM login_attempts
     WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)'
);

$limit = 5;
$windowMinutes = 15;

$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM login_attempts
     WHERE ip_address = ?
       AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)'
);

$stmt->execute([
    $ip,
    $windowMinutes
]);

$attemptCount = (int)$stmt->fetchColumn();

$isBlocked = $attemptCount >= $limit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_check();

    if ($isBlocked) {

        $error = 'Prea multe încercări. Încearcă din nou peste aproximativ 15 minute.';

    } else {

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare(
            'SELECT *
             FROM users
             WHERE email = ?
               AND active = 1
             LIMIT 1'
        );

        $stmt->execute([$email]);

        $u = $stmt->fetch();

        if (
            $u &&
            password_verify(
                $password,
                $u['password_hash']
            )
        ) {

            $clear = $pdo->prepare(
                'DELETE FROM login_attempts
                 WHERE ip_address = ?'
            );

            $clear->execute([$ip]);

            session_regenerate_id(true);

            $_SESSION['user_id'] = $u['id'];
            $_SESSION['user_name'] = $u['name'];
            $_SESSION['user_email'] = $u['email'];
            $_SESSION['last_activity'] = time();

            redirect('/');

        } else {

            $insert = $pdo->prepare(
                'INSERT INTO login_attempts
                (ip_address)
                VALUES (?)'
            );

            $insert->execute([$ip]);

            $error = 'Email sau parolă incorectă.';
        }
    }
}

?>
<!doctype html>

<html lang="ro">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>

<meta
    name="color-scheme"
    content="light"
>

<title>
    Autentificare · EcoAutoFin
</title>

<link
    rel="stylesheet"
    href="/assets/css/app.css?v=<?= time() ?>"
>

<style>

*{
    box-sizing:border-box;
}

html,
body{
    margin:0;
    min-height:100%;
}

body{
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:24px;
    background:
        radial-gradient(
            circle at 50% 0%,
            #eaf2f8 0%,
            #f5f8fb 32%,
            #f7f9fb 70%
        );
    font-family:Inter,Arial,sans-serif;
    color:#172033;
}

.login-wrap{
    width:100%;
    max-width:390px;
}

.login-logo-wrap{
    display:flex;
    justify-content:center;
    margin-bottom:20px;
}

.login-logo{
    width:180px;
    max-width:70%;
    height:auto;
    display:block;
}

.login-card-modern{
    padding:28px;
    background:rgba(255,255,255,.94);
    border:1px solid #e1e8ef;
    border-radius:18px;
    box-shadow:0 18px 45px rgba(15,39,68,.06);
}

.login-top{
    text-align:center;
    margin-bottom:24px;
}

.login-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:22px;
    margin-bottom:8px;
    padding:4px 9px;
    border-radius:999px;
    background:#eef3f8;
    color:#657386;
    font-size:8px;
    font-weight:800;
    letter-spacing:.08em;
    text-transform:uppercase;
}

.login-top h1{
    margin:0;
    font-size:24px;
    line-height:1.15;
    font-weight:750;
    letter-spacing:-.035em;
    color:#172033;
}

.login-top p{
    margin:8px 0 0;
    color:#7b8593;
    font-size:11px;
    line-height:1.5;
}

.login-error{
    margin-bottom:16px;
    padding:10px 12px;
    background:#fff0f0;
    border:1px solid #f4d3d3;
    border-radius:9px;
    color:#9b3535;
    font-size:10px;
    line-height:1.4;
}

.login-form{
    display:grid;
    gap:14px;
}

.login-form label{
    display:block;
    margin:0;
    color:#4f5c6d;
    font-size:10px;
    font-weight:700;
}

.login-form input{
    width:100%;
    height:43px;
    margin-top:6px;
    padding:0 12px;
    background:#fff;
    border:1px solid #dce4eb;
    border-radius:9px;
    outline:none;
    color:#172033;
    font-family:inherit;
    font-size:12px;
    transition:border-color .15s ease,box-shadow .15s ease;
}

.login-form input:focus{
    border-color:#1769aa;
    box-shadow:0 0 0 3px rgba(23,105,170,.08);
}

.login-submit{
    width:100%;
    height:43px;
    margin-top:2px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:1px solid #1769aa;
    border-radius:9px;
    background:#1769aa;
    color:#fff;
    font-size:12px;
    font-weight:750;
    cursor:pointer;
    transition:background .15s ease,border-color .15s ease,transform .15s ease;
}

.login-submit:hover{
    background:#0f5b96;
    border-color:#0f5b96;
}

.login-submit:active{
    transform:translateY(1px);
}

.login-secure{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    margin-top:18px;
    color:#99a3af;
    font-size:9px;
}

.login-secure-dot{
    width:6px;
    height:6px;
    border-radius:50%;
    background:#39a36b;
}

.login-credit{
    margin-top:18px;
    text-align:center;
    color:#9aa4b1;
    font-size:9px;
}

.login-credit a{
    color:#1769aa;
    text-decoration:none;
    font-weight:800;
}

.login-credit a:hover{
    text-decoration:underline;
}

@media(max-width:480px){

    body{
        padding:18px;
    }

    .login-card-modern{
        padding:23px 20px;
        border-radius:15px;
    }

    .login-logo{
        width:165px;
    }

}

.login-logo-wrap{background:#153a5a;border-radius:18px;padding:24px;margin-bottom:18px}.login-logo{width:190px;max-width:90%;filter:none}.login-card-modern{border:0;box-shadow:0 12px 40px rgba(10,40,60,.06)}body{background:#f3f6f8!important}
</style>

</head>

<body>

<div class="login-wrap">

    <div class="login-logo-wrap">

        <img
            src="/assets/logo"
            alt="EcoAutoFin"
            class="login-logo"
        >

    </div>

    <div class="login-card-modern">

        <div class="login-top">

            <span class="login-badge">
                Software intern
            </span>

            <h1>
                Autentificare
            </h1>

            <p>
                Accesează platforma internă pentru produse,
                clienți și oferte comerciale.
            </p>

        </div>

        <?php if ($error): ?>

            <div class="login-error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form
            method="post"
            class="login-form"
        >

            <input
                type="hidden"
                name="csrf"
                value="<?= e(csrf_token()) ?>"
            >

            <label>

                Email

                <input
                    type="email"
                    name="email"
                    value="<?= e($_POST['email'] ?? '') ?>"
                    required
                    autocomplete="username"
                    autofocus
                    placeholder="nume@ecoautofin.ro"
                >

            </label>

            <label>

                Parolă

                <input
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Introdu parola"
                >

            </label>

            <button
                type="submit"
                class="login-submit"
            >
                Intră în aplicație
            </button>

        </form>

        <div class="login-secure">

            <span class="login-secure-dot"></span>

            Acces securizat · exclusiv uz intern

        </div>

    </div>

    <div class="login-credit">

        Software intern realizat de

        <a
            href="https://cioltea.ro"
            target="_blank"
            rel="noopener noreferrer"
        >
            CIOLTEA.RO
        </a>

    </div>

</div>

</body>

</html>