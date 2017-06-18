<?php
require_once 'load.php';

use wx\Tool;
$tool = new Tool();
$user = $tool->getUser();
include('html/user.html');
