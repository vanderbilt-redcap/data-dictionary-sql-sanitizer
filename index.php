<?php

namespace VUMC\DataDictionarySQLSanitizer;

require_once 'classes/SessionFlashbag.php';

use VUMC\SessionFlashbag\SessionFlashbag;

session_start(); // Ensure sessions are started.

// Initialize the flashbag.
$flashbag = new SessionFlashbag('my_flashbag_key');

// Add messages.
$flashbag->add('success', 'Data Dictionary downloaded successfully!');


$pid = (int)$_GET['pid'];

#Only available for super users / admins
if (!$module->isSuperUser()) {
    die;
}
//print_array($flashbag->getMessages());
if(!defined('APP_PATH_WEBROOT_ALL')) {
    if (APP_PATH_WEBROOT[0] == '/') {
        $APP_PATH_WEBROOT_ALL = substr(APP_PATH_WEBROOT, 1);
    }
    define('APP_PATH_WEBROOT_ALL', APP_PATH_WEBROOT_FULL . $APP_PATH_WEBROOT_ALL);
}

$module->initialize();

echo $module->getTwig()->render('index.html.twig', [
                                                     'redcap_js' => $module->loadREDCapJS(),
                                                     'pid' => $pid,
                                                     'redcap_csrf_token' => $module->getCSRFToken(),
                                                     'styles' =>$module->getUrl('css/styles.css'),
                                                     'download_data_dictionary_url' =>$module->getUrl('download-data-dictionary-AJAX.php'),
                                                     'sql_fields_sanitize' => $module->sanitizeSQLFields($pid),
                                                     'flashbag_messages' => $flashbag->getMessages()
                                                 ]
);
