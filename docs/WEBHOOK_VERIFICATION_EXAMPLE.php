<?php
// Minimal consumer-side WorkIntel webhook verification example.
$secret = getenv('WORKINTEL_WEBHOOK_SECRET');
$timestamp = $_SERVER['HTTP_X_WORKINTEL_TIMESTAMP'] ?? '';
$provided = $_SERVER['HTTP_X_WORKINTEL_SIGNATURE'] ?? '';
$body = file_get_contents('php://input') ?: '';
if (!$secret || !$timestamp || abs(time() - (int) $timestamp) > 300) { http_response_code(401); exit; }
$expected = 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
if (!hash_equals($expected, $provided)) { http_response_code(401); exit; }
http_response_code(204);
