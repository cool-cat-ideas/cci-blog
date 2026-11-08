<?php

declare(strict_types=1);

define('_PS_VERSION_', '9.2.0');
require dirname(__DIR__) . '/classes/CciBlogPublicationAudit.php';
require dirname(__DIR__) . '/classes/CciBlogPublicationAuditStorage.php';
$assert = static function (bool $valid, string $message): void { if (!$valid) { throw new RuntimeException($message); } };
$summary = CciBlogPublicationAuditStorage::pack(['score' => 83, 'overallStatus' => 'good', 'complete' => true, 'version' => CciBlogPublicationAudit::VERSION, 'rulesFingerprint' => 'rules', 'contextFingerprint' => 'context', 'dataRevision' => 'data', 'validUntil' => 200, 'checks' => [['message' => 'A full report should never be stored.']]]);
$encoded = json_encode($summary, JSON_THROW_ON_ERROR);
$read = CciBlogPublicationAuditStorage::read($encoded, '83', 'rules', 'context', 'data', 100);
$assert($read['score'] === 83 && !$read['stale'] && !$read['notChecked'], 'A list must show the last saved score without evaluating rules.');
$assert(!isset($summary['checks']) && strlen($encoded) < 500, 'Persist the result and freshness metadata, not the full report.');
foreach ([['new-rules', 'context', 'data'], ['rules', 'new-context', 'data'], ['rules', 'context', 'new-data']] as [$rules, $context, $revision]) {
    $read = CciBlogPublicationAuditStorage::read($encoded, 83, $rules, $context, $revision, 100);
    $assert($read['stale'] && $read['score'] === 83, 'Changed policy/settings/catalogue data must retain and label the last saved score.');
}
$assert(CciBlogPublicationAuditStorage::read($encoded, 83, 'rules', 'context', 'data', 200)['stale'], 'Scheduled publication changes must expire saved verification metadata.');
$assert(CciBlogPublicationAuditStorage::read(null, 100, 'rules', 'context', 'data')['notChecked'], 'An old numeric cache is not a saved result from the new engine.');
$assert(CciBlogPublicationAuditStorage::read($encoded, 20, 'rules', 'context', 'data')['notChecked'], 'The displayed result must agree with the SQL sort value.');
$summary['score'] = null;
$summary['overallStatus'] = 'info';
$summary['complete'] = false;
$assert(CciBlogPublicationAuditStorage::read(json_encode($summary), null, 'rules', 'context', 'data', 100)['score'] === null, 'Unavailable results must remain nullable, distinct from zero.');
echo "OK: compact saved publication results, freshness, expiry and sort consistency.\n";
