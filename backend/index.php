<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Enable CORS for frontend
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Simple routing
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Remove query string and base path
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace('/index.php', '', $path);

// Remove trailing slash
$path = rtrim($path, '/');

// Route handling
switch ($path) {
    case '/api/repos':
        if ($request_method === 'GET') {
            require_once __DIR__ . '/api/github.php';
            handleGetRepos();
        }
        break;
    
    case '/api/prs':
        if ($request_method === 'GET') {
            require_once __DIR__ . '/api/github.php';
            handleGetPRs();
        }
        break;
    
    case '/api/pr-details':
        if ($request_method === 'GET') {
            require_once __DIR__ . '/api/github.php';
            handleGetPRDetails();
        }
        break;
    
    case '/api/review':
        if ($request_method === 'POST') {
            require_once __DIR__ . '/api/ai-review.php';
            handleAIReview();
        }
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}