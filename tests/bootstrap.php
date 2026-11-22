<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/Stubs/joomla.php';

define('_JEXEC', 1);
define('JPATH_ROOT', sys_get_temp_dir() . '/pdfviewer-test-site');
define('JPATH_ADMINISTRATOR', JPATH_ROOT . '/administrator');

require_once dirname(__DIR__) . '/content_plugin/pdfviewer.php';
