<?php
session_start();
require_once "../includes/auth.php";
logoutUser();
header("Location: index.php");
  exit;
