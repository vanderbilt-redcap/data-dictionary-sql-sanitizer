<?php

$pid = (int)$_GET['pid'];

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
                                                 ]
);
