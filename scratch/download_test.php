<?php
$url = 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=500&auto=format&fit=crop';
$dest = __DIR__ . '/test_download.jpg';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$data = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($data && $http_code === 200) {
    file_put_contents($dest, $data);
    echo "Downloaded successfully! HTTP $http_code\n";
} else {
    echo "Failed to download. HTTP $http_code. Data length: " . strlen($data) . "\n";
}
?>
