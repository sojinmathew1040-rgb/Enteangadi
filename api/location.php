<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'set_location') {
        $_SESSION['user_location'] = [
            'name' => $_POST['location_name'],
            'lat' => $_POST['latitude'],
            'lng' => $_POST['longitude']
        ];
        
        echo json_encode(['success' => true, 'location' => $_SESSION['user_location']]);
        exit;
    }

    if ($_POST['action'] === 'clear_location') {
        unset($_SESSION['user_location']);
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
