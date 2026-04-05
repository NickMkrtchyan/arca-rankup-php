<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

use ArCa\Routes\{Payment, Result, Webhook, Api};

$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path   = rtrim($path, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

// ── Webhook routes need raw body — no output buffering ────────────────────────
if ($path === '/webhook/cancel' || $path === '/webhook/capture') {
    header('Content-Type: application/json');
    match ($path) {
        '/webhook/cancel'  => Webhook::handleCancel(),
        '/webhook/capture' => Webhook::handleCapture(),
    };
    exit;
}

// ── JSON API routes ───────────────────────────────────────────────────────────
if (str_starts_with($path, '/api/') || $path === '/health') {
    header('Content-Type: application/json');
    match ($path) {
        '/api/stats'  => Api::stats(),
        '/api/config' => Api::config(),
        '/health'     => Api::health(),
        default       => (function() { http_response_code(404); echo json_encode(['error' => 'Not found']); })(),
    };
    exit;
}

// ── Payment routes ────────────────────────────────────────────────────────────
match ($path) {
    '/pay'    => Payment::handle(),
    '/result' => Result::handle(),
    '/'       => (function() {
        http_response_code(200);
        require dirname(__DIR__) . '/templates/dashboard.php';
    })(),
    default   => (function() {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    })(),
};
