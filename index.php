<?php
require_once 'load.php';

use wx\Tool;
$tool = new Tool();
$tool->getAuth();
include('html/index.html');
