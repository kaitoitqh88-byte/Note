<?php
// assets/vps_login_checker.php - SSH login function for AJAX VPS check
require_once dirname(__DIR__).'/vendor/autoload.php';
use phpseclib3\Net\SSH2;

function checkVpsLogin($ip, $username, $password) {
    $ssh = new SSH2($ip);
    try {
        if (!$ssh->login($username, $password)) {
            return ['success'=>false,'error'=>'Sai tài khoản hoặc không kết nối được VPS!'];
        }
        return ['success'=>true];
    } catch(Exception $e) {
        return ['success'=>false,'error'=>$e->getMessage()];
    }
}
