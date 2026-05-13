<?php
session_start();

const APP_NAME = 'Quizflow';

// import config
$configFile = __DIR__ . '/includes/core/config.php';
if (!file_exists($configFile)) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Setup erforderlich - Quizflow</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="flex min-h-screen items-center justify-center bg-gray-200">
        <div class="w-full max-w-2xl rounded-3xl bg-white p-8 shadow-lg">
            <h1 class="text-3xl font-bold">Quizflow ist noch nicht konfiguriert</h1>
            <p class="mt-4 text-gray-600">Fuehren Sie zuerst den Installer aus oder legen Sie die Datei <code>includes/core/config.php</code> an.</p>
            <pre class="mt-6 overflow-x-auto rounded-2xl bg-gray-900 p-4 text-sm text-white">./quizflow_installer.sh</pre>
        </div>
    </body>
    </html>
    <?php
    exit;
}
include_once $configFile;

function persistAdminPasswordHash($configFile, $hash) {
    $configContent = file_get_contents($configFile);
    $constantLine = "const ADMIN_PASSWORD_HASH = '" . addslashes($hash) . "';";

    if (preg_match("/const ADMIN_PASSWORD_HASH = '.*?';/", $configContent)) {
        $configContent = preg_replace("/const ADMIN_PASSWORD_HASH = '.*?';/", $constantLine, $configContent, 1);
    } else {
        $configContent = rtrim($configContent) . PHP_EOL . $constantLine . PHP_EOL;
    }

    return file_put_contents($configFile, $configContent) !== false;
}

// Passwort aus config holen (z.B. $ADMIN_PASSWORD_HASH)
$adminPasswordHash = defined('ADMIN_PASSWORD_HASH') ? ADMIN_PASSWORD_HASH : null;

// --- Passwort setzen, falls noch nicht vorhanden ---
if (!$adminPasswordHash) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
        $newPassword = $_POST['new_password'];
        if (strlen($newPassword) < 6) {
            $error = "Das Passwort muss mindestens 6 Zeichen lang sein.";
        } else {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            if (persistAdminPasswordHash($configFile, $hash)) {
                header("Location: login.php");
                exit;
            }
            $error = "Das Passwort konnte nicht gespeichert werden.";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Admin Passwort setzen - Quizflow</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="flex flex-col justify-center items-center min-h-screen bg-gray-200">
        <div class="bg-white shadow-lg rounded-2xl p-8 w-full max-w-md">
            <h1 class="text-2xl font-bold mb-6 text-center">Admin-Passwort festlegen</h1>
            <?php if (!empty($error)): ?>
                <div class="mb-4 text-red-600"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" class="flex flex-col gap-4">
                <input type="password" name="new_password" placeholder="Neues Passwort" required class="border rounded px-3 py-2">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Speichern</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --- Login-Formular und Verarbeitung ---
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password = $_POST['password'] ?? '';
    if (password_verify($password, $adminPasswordHash)) {
        session_regenerate_id(true);
        $_SESSION['loggedin'] = TRUE;
        $_SESSION['name'] = 'admin';
        header('Location: admin.php');
        exit;
    } else {
        $error = "Login fehlgeschlagen";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Login - Quizflow</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex flex-col justify-center items-center min-h-screen bg-gray-200">
    <div class="bg-white shadow-lg rounded-2xl p-8 w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center">Quizflow Login</h1>
        <?php if ($error): ?>
            <div class="mb-4 text-red-600"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" class="flex flex-col gap-4">
            <input type="password" name="password" placeholder="Passwort" required class="border rounded px-3 py-2">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Login</button>
        </form>
    </div>
</body>
</html>