<?php
require __DIR__.'/../includes/bootstrap.php';csrf_check();log_act('logout');$_SESSION=[];session_destroy();out(true,'خارج شدید');
