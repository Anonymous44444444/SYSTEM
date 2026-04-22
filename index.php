<?php
// simple router
$request = $_SERVER['REQUEST_URI'];

if ($request === '/' || $request === '/index.php') {
    include 'Login.php';
} else {
    $file = ltrim($request, '/');
    if (file_exists($file)) {
        include $file;
    } else {
        http_response_code(404);
        echo "404 Not Found";
    }
}
