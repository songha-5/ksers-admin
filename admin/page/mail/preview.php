<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/common.php';
ksers_require_admin($pdo);

$key = isset($_GET['template']) ? (string)$_GET['template'] : 'deadline';
$templates = ksers_admin_mail_templates();
if (!isset($templates[$key])) {
    $key = 'deadline';
}
$template = $templates[$key];
$vars = ksers_admin_mail_vars_from_row([
    'receipt_no' => 'A2027-0016',
    'title' => 'Endoscopic closure after gastric ESD',
    'presenter' => 'Hong Gildong',
    'final_type' => 'Oral',
    'session' => 'To be announced',
    'presentation_datetime' => '2027-04-23 09:00',
    'room' => 'Room A',
    'presentation_minutes' => '7',
    'file_deadline' => 'March 31 (Tue), 2027',
    'deleted_at' => '2026-11-13 09:13 (KST)',
]);
$parts = ksers_admin_mail_render_parts(
    $template,
    $template['subject'],
    $template['title'],
    $template['body'],
    $vars,
    'Hong Gildong',
    'member@example.com'
);
$parts['fields']['logo_mark_url'] = '/mail/KSERS2027_mail_logo_mark.png';
echo ksers_mail_render('en', $parts['fields']);
