<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__);
$controller = file_get_contents($moduleRoot . '/controllers/front/post.php');
$commentModel = file_get_contents($moduleRoot . '/classes/CciBlogComment.php');
$template = file_get_contents($moduleRoot . '/views/templates/front/post.tpl');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: {$message}\n");
};

$assert(str_contains($template, 'name="comment_token"'), 'comment form submits a CSRF token');
$assert(str_contains($controller, 'hash_equals($this->commentFormToken($postId), $submittedToken)'), 'controller verifies the comment CSRF token');
$assert(str_contains($template, 'name="comment_started_at"'), 'comment form submits a signed start time');
$assert(str_contains($controller, '$age < 3') && str_contains($controller, '$age > 7200'), 'controller rejects implausibly fast and stale submissions');
$assert(str_contains($commentModel, 'hash_hmac(\'sha256\', trim($ip)'), 'IP addresses are stored as keyed fingerprints');
$assert(str_contains($commentModel, 'SET ip_address ='), 'expired IP fingerprints are purged');
$assert(str_contains($controller, 'parentBelongsToPost'), 'reply parents are constrained to the current post');
$assert(str_contains($controller, 'isDuplicate($postId, $email, $content)'), 'duplicate comments are rejected');
$assert(!str_contains($controller, '$comment->ip_address   = $ip;'), 'raw IP address is not persisted');

fwrite(STDOUT, "CCI Blog comment security smoke test passed.\n");
