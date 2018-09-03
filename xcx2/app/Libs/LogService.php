<?php
namespace App\Libs;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LogService
{
    public static function logWrite($logName, $content, $daily = true,$type='INFO')
    {

        if (empty($log)) {
            static $log = null;
        }

        $log = new Logger('');
        $dayFlag = $daily ? date('Ymd') . '_' : '';
        $file = storage_path() . '/logs/' . $dayFlag . $logName . '.log';

        $log->pushHandler(new StreamHandler($file), Logger::INFO);
        if($type=='ERROR'){
            $log->addError($content);

        }else{
            $log->addInfo($content);
        }

        return;
    }

}
