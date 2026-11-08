<?php

declare(strict_types=1);

define('_PS_VERSION_', '9.2.0');
define('_COOKIE_KEY_', 'test-only-' . bin2hex(random_bytes(24)));
require_once __DIR__ . '/../classes/CciBlogFeed.php';
require_once __DIR__ . '/../classes/CciBlogPostPreview.php';

function editorialCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS: ' . $message . "\n";
}

$post = ['id_post' => 7, 'title' => 'Zażółć & <tekst> 🐈', 'intro' => "<p>A &amp; B ]]> C\x01</p>",
    'url' => 'https://shop.example/blog/article', 'date_published' => '2026-10-01 12:00:00',
    'date_add' => '2026-10-01 12:00:00', 'date_upd' => '2026-10-03 13:00:00', 'cover_image' => ''];
$channel = ['title' => 'Blog', 'description' => 'Articles', 'url' => 'https://shop.example/blog',
    'feedUrl' => 'https://shop.example/blog/feed.xml', 'language' => 'pl-PL', 'identity' => 'fixture', 'shopId' => 1, 'langId' => 2];
$xml = CciBlogFeed::render([$post], $channel);
$feed = simplexml_load_string($xml);
editorialCheck($feed !== false, 'RSS remains valid with CDATA terminators and XML control characters.');
editorialCheck((string) $feed->channel->item->title === $post['title'], 'Unicode and title characters survive RSS encoding.');
editorialCheck((string) $feed->channel->item->description === 'A & B ]]> C', 'RSS description is plain text and escaped once.');
$changed = $post;
$changed['url'] = 'https://shop.example/blog/new-slug';
$other = simplexml_load_string(CciBlogFeed::render([$changed], $channel));
editorialCheck((string) $feed->channel->item->guid === (string) $other->channel->item->guid, 'Changing a slug preserves the item GUID.');
editorialCheck((string) $feed->channel->lastBuildDate === gmdate(DATE_RSS, strtotime($post['date_upd'])), 'Feed update time reflects content changes.');
$channel['shopId'] = 2;
$other = simplexml_load_string(CciBlogFeed::render([$post], $channel));
editorialCheck((string) $feed->channel->item->guid !== (string) $other->channel->item->guid, 'RSS GUIDs are scoped by shop.');
$modified = CciBlogFeed::lastModified([$post]);
editorialCheck(CciBlogFeed::notModified('"abc"', $modified, ['HTTP_IF_NONE_MATCH' => '"other", W/"abc"']), 'Weak and multiple ETags support conditional reads.');
editorialCheck(!CciBlogFeed::notModified('"abc"', $modified, ['HTTP_IF_NONE_MATCH' => '"other"', 'HTTP_IF_MODIFIED_SINCE' => gmdate(DATE_RSS, $modified)]), 'A mismatching ETag takes precedence over the timestamp.');
editorialCheck(CciBlogFeed::notModified('"abc"', $modified, ['HTTP_IF_MODIFIED_SINCE' => gmdate(DATE_RSS, $modified)]), 'An unchanged feed supports timestamp validation.');
editorialCheck(!CciBlogFeed::notModified('"abc"', 0, ['HTTP_IF_MODIFIED_SINCE' => 'invalid']), 'An empty feed is not incorrectly treated as unchanged.');

$validators = CciBlogFeed::validators($xml, 'test');
editorialCheck($validators === CciBlogFeed::validators($xml, 'test'), 'Unchanged representations keep stable HTTP validators.');
$feedDirectory = rtrim(sys_get_temp_dir(), '/\\') . '/cci-blog-feeds-' . substr(hash('sha256', _COOKIE_KEY_), 0, 24);
$feedState = $feedDirectory . '/' . hash('sha256', 'test') . '.json';
file_put_contents($feedState, json_encode(['etag' => $validators['etag'], 'modified' => time() - 3600]));
$afterDelete = CciBlogFeed::validators(CciBlogFeed::render([], $channel), 'test');
editorialCheck($afterDelete['etag'] !== $validators['etag'] && $afterDelete['modified'] >= time() - 1, 'Deleting feed items invalidates timestamps as well as ETags.');
unlink($feedState);
rmdir($feedDirectory);

$snapshot = ['id_post' => 7, 'title' => 'Private draft', 'content' => '<p>Not published</p>'];
$token = CciBlogPostPreview::create($snapshot, 1, 2);
$directory = rtrim(sys_get_temp_dir(), '/\\') . '/cci-blog-previews-' . substr(hash('sha256', _COOKIE_KEY_), 0, 24);
$file = $directory . '/' . explode('.', $token)[0] . '.json';

try {
    editorialCheck(CciBlogPostPreview::read($token, 1, 2) === $snapshot, 'A signed preview returns its snapshot.');
    editorialCheck(CciBlogPostPreview::read($token, 2, 2) === null, 'Preview links cannot cross shops.');
    editorialCheck(CciBlogPostPreview::read($token, 1, 3) === null, 'Preview links cannot cross languages.');
    $tampered = substr($token, 0, -1) . (str_ends_with($token, 'a') ? 'b' : 'a');
    editorialCheck(CciBlogPostPreview::read($tampered, 1, 2) === null, 'A forged preview signature is rejected.');
    editorialCheck(CciBlogPostPreview::read('../../etc/passwd', 1, 2) === null, 'Preview identifiers cannot traverse directories.');
    [$id] = explode('.', $token);
    $expired = time() - 1;
    $signature = hash_hmac('sha256', implode(':', ['cci-blog-preview', $id, $expired, 1, 2]), _COOKIE_KEY_);
    editorialCheck(CciBlogPostPreview::read($id . '.' . $expired . '.' . $signature, 1, 2) === null, 'Even correctly signed expired previews are rejected.');
    editorialCheck((fileperms($directory) & 0777) === 0700 && (fileperms($file) & 0777) === 0600, 'Snapshot files are private on disk.');
    unlink($file);
    editorialCheck(CciBlogPostPreview::read($token, 1, 2) === null, 'Missing snapshots do not fall back to public posts.');
} finally {
    if (is_file($file)) {
        unlink($file);
    }
    rmdir($directory);
}
