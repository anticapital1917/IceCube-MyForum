<?php
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo '[]'; exit(); }

$q = '%' . trim($_GET['q'] ?? '') . '%';
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE username LIKE ? AND id != ? LIMIT 8");
$stmt->execute([$q, $_SESSION['user_id']]);
echo json_encode($stmt->fetchAll());
