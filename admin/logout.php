<?php

session_start();
session_destroy();

header("Location: ../voters/login.php");
exit();