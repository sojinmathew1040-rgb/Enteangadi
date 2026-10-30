<?php
$qs = $_SERVER['QUERY_STRING'] ?? '';
header("Location: ../login.php" . ($qs !== '' ? '?' . $qs : ''));
exit;
