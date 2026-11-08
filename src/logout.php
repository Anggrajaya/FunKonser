<?php
require_once __DIR__ . "/session_init.php";

akhiriSesi();

header("Location: /FunKonser/src/login.php", true, 303);
exit;
