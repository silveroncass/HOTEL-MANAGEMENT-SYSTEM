<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode([]); exit; }
$db = getDB();
$staff = $db->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id=r.role_id WHERE r.role_name='staff' AND u.is_active=1")->fetchColumn();
$rooms = $db->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
echo json_encode(['staff'=>(int)$staff,'rooms'=>(int)$rooms]);
