<?php
session_start();

const APP_NAME = 'Quizflow';

// import config
$configFile = __DIR__ . '/includes/core/config.php';
include_once $configFile;

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
            // Schreibe das Passwort-Hash in die config.php
            $configContent = file_get_contents($configFile);
            $configContent .= "\nconst ADMIN_PASSWORD_HASH = '" . addslashes($hash) . "';\n";
            file_put_contents($configFile, $configContent);
            // Nach dem Setzen neu laden
            header("Location: login.php");
            exit;
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
        session_regenerate_id();
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