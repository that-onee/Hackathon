<?php
require_once __DIR__ . '/db.php';

function registerUser(string $username, string $email, string $password): array {
    $username = trim($username);
    $email    = strtolower(trim($email));

    if (strlen($username) < 3 || strlen($username) > 30) return ['error' => 'Username must be 3-30 characters.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))       return ['error' => 'Invalid email address.'];
    if (strlen($password) < 6)                            return ['error' => 'Password must be at least 6 characters.'];

    $exists = dbFetch('SELECT id FROM users WHERE username=? OR email=?', [$username, $email]);
    if ($exists) return ['error' => 'Username or email already taken.'];

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $id   = dbInsert('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)', [$username, $email, $hash]);

    return ['success' => true, 'user_id' => $id, 'username' => $username];
}

function loginUser(string $identifier, string $password): array {
    $identifier = strtolower(trim($identifier));
    $user = dbFetch('SELECT * FROM users WHERE email=? OR username=?', [$identifier, $identifier]);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['error' => 'Invalid credentials.'];
    }
    return ['success' => true, 'user' => $user];
}

function requireAuth(): array {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php'); exit;
    }
    return ['user_id' => $_SESSION['user_id'], 'username' => $_SESSION['username']];
}
