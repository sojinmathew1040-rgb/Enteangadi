<?php
$qs = $_SERVER['QUERY_STRING'] ?? '';
header("Location: ../register.php" . ($qs !== '' ? '?' . $qs : ''));
exit;
