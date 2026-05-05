<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
echo json_encode($_SESSION, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);