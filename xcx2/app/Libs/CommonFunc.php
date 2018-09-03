<?php
namespace App\Libs;

use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Log;
use Auth;
use Cache;
use Mail;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;

/**
 * 通用方法类
 */
class CommonFunc
{
    static $comma = [',', '，', '、'];
    public static function _success($data = array(), $msg = 'success')
    {
        return response()->json(
            ['success' => true, 'results' => $msg, 'rows' => $data]
        );
    }

    public static function _fail($msg = 'fail', $data = array())
    {
        return response()->json(
            ['success' => false, 'results' => $msg, 'rows' => $data]
        );
    }

    public static function _failAlert($msg)
    {
        echo "<script>alert('" . $msg . "');window.close();</script>";
        exit;
    }

    public static function getLen($str, $kangJi = 1)
    {
        $re = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        preg_match_all($re, $str, $match);

        $length = 0;
        foreach ($match[0] as $char) {
            $length += (strlen($char) > 1) ? $kangJi : 1;
        }

        return $length;
    }

    public static function checkKanJIAz_09($str)
    {
        return preg_match('/^[0-9a-zA-Z_\x{4e00}-\x{9fa5}]+$/u', $str);
    }

    //get offline cols, the result is [realCol=>tplCol, ...]
    public static function getOffCols($fieldMaps, $renames)
    {
        if ($fieldMaps == '*') return '*';
        $fieldMaps = json_decode($fieldMaps, true);
        $renames = json_decode($renames, true);

        $tmp = array();
        foreach ($fieldMaps as $v) {
            if (!empty($v['table_column'])) {
                $tmp[$v['table_column']] = $v['temp_field'];
            }
        }

        $renames = array_flip($renames);
        $fieldMaps = array();
        foreach ($tmp as $k => $v) {
            $fieldMaps[$renames[$k]] = $v;
        }

        return $fieldMaps;
    }

    public static function curlRequest($url, $optTimeout = 1, $retry = 2,
                                       $optHeader = false, $optReturnTransfer = true, $options = array())
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, $optHeader);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, $optReturnTransfer);
        curl_setopt($ch, CURLOPT_TIMEOUT, $optTimeout);

        if (!is_numeric($retry) || $retry < 0 ||
            (count($options) > 0) && (!is_array($options) || !curl_setopt_array($ch, $options))
        ) {
            return false;
        }
        do {
            $res = curl_exec($ch);
            $errNo = curl_errno($ch);
            $errorStr = curl_error($ch);
        } while ($errNo && $retry--);

        curl_close($ch);

        if ($errNo != 0) {
            $msg = "curl get :{$url} error_str:{$errorStr}";
            Log::warning($msg);
        }
        return array('data' => $res, 'errNo' => $errNo, 'errStr' => $errorStr);
    }

    public static function arrayArrayUnique($array)
    {
        if (!is_array($array)) {
            return $array;
        }

        $res = array();
        foreach ($array as $v) {
            $tmp = implode(',', $v);
            $res[] = $tmp;
        }

        $res = array_unique($res);

        $finally = array();
        foreach ($res as $row) {
            $tmp = explode(',', $row);
            $finally[] = $tmp;
        }

        return $finally;
    }

    /**
     * 默认GET请求
     * 如果用POST请求，请将参数写至params
     * @param $url
     * @param array $params
     * @param array $header
     * @param int $timeout
     * @return mixed
     */
    public static function curl($url, $params = [], $header = [], $timeout = 5)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, $params ? 0 : 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $file_contents = curl_exec($ch);
        $errNo = curl_errno($ch);
        $errorStr = curl_error($ch);
        if ($errNo != 0) {
            $msg = "curl get :{$url},errorNo {$errNo}, error_str:{$errorStr}";
            LogService::logWrite('curlError', $msg);
        }
        curl_close($ch);
        return $file_contents;
    }

    public static function getESData($sql)
    {
        $sql = trim($sql);
        LogService::logWrite('essql', $sql);
        $url = 'http://' . config('database.es.host') . ':' . config('database.es.port') . '/_sql';
        $url .= '?sql=' . urlencode($sql);
        $res = CommonFunc::curl($url);
        $res = json_decode($res, true);
        if ($res === NULL) {
           throw  new HttpException('null get while get es data', 231623);
        }
        return $res;
    }

    public static function getSql(\Illuminate\Database\Query\Builder $model){
        function replace ($sql, $bindings)
        {
            $needle = '?';
            foreach ($bindings as $replace){
                $pos = strpos($sql, $needle);
                if ($pos !== false) {
                    $sql = substr_replace($sql, "'".$replace."'", $pos, strlen($needle));
                }
            }
            return $sql;
        }
        $sql = replace($model->toSql(), $model->getBindings());
        $sql = str_replace('`','',$sql);
        return $sql;
    }

    public static function setDataPagination($data, $currentPage, $totalPage, $pageSize) {
        return [
            'current_page' => $currentPage,
            'total' => $totalPage,
            'per_page' => $pageSize,
            'data' => $data,
        ];
    }

    //一维数组
    public static function delArrayEmpty($array){
        $tmp=[];
        foreach ($array as $val){
            $val=preg_replace('/\s+/', '', $val);
            if(!empty($val)){
                $tmp[]=$val;
            }
        }
        return $tmp;
    }
    
    public function getCurrentUsername() {
        return Auth::User()->name;
    }

    public static function getKeywords($keyJson)
    {
        if(is_string($keyJson)){
            $arr = json_decode($keyJson, TRUE);
        }else{
            $arr=$keyJson;
        }

        $keywords = [];
        foreach ($arr as $v) {
            if (is_string($v) && strlen($v))  {
                $keywords[]  = strtolower($v);
            } elseif (is_array($v)) {
                foreach ($v as $vb) {
                    if (is_string($vb) && strlen($vb)) {
                        $keywords[] = strtolower($vb);
                    }
                }
            }
        }
        return $keywords;
    }
	
	/**
	 * @desc 爬虫数据中script 标签删除
	 * @param type $string
	 * @return type
	 */
	public static function delScript($string) {
		$pregfind = array("/<script.*>.*<\/script>/siU",
			"/<style.*>.*<\/style>/siU",
			"/<iframe.*>.*<\/iframe>/siU",
			"/\&nbsp;/siU",
			'/on(mousewheel|mouseover|click|load|onload|submit|focus|blur)="[^"]*"/i');
		$pregreplace = array('','','','','');
		$string = preg_replace($pregfind, $pregreplace, $string);
		return $string;
	}
	
	public static function formatDate($dateString) {
		$str = trim($dateString);
		$replaceArr = array('年','月','/','.');
		$str = str_replace($replaceArr, '-', $str);
		$clearArr = array('日');
		$str = str_replace($clearArr, '', $str);
		
		$str = trim($str);
		$str = preg_replace("/[^0-9\-:\. ]/i", '', $str);
		return $str;
	}
    //$name 项目名
    public static function sendEmail($name){
        return;
        $message='新建项目'.$name.'请确定使用哪种模型';
        $receiver = [
        ];
        Mail::raw($message, function ($message) use($receiver) {
            $from = env('MAIL_FROM');
            foreach($receiver as $to){
                $message ->from($from)->to($to)->subject('nlp新建项目提醒');
            }
        });
    }

    /*
     * 将多个值得value转换为执行sql不报错形式
     * 主要针对str型使用IN()的时候需要补''
     * return string
     */
    public static function covertMultiValue($value){
        $value = str_replace(self::$comma, ',', $value);
        //转化为数组
        $valueArr = explode(',', $value);
        $ret = [];
        foreach($valueArr as $valueItem){
            $ret[] = "'{$valueItem}'";
        }

        return join(',', $ret);
    }

    /**
     * 获取文件大小
     * Converts bytes into human readable file size.
     * @param string $bytes
     * @return string human readable file size (2,87 Мб)
     * @author Mogilev Arseny
     */
    static function fileSizeConvert($bytes){
        $bytes = floatval($bytes);
        $arBytes = array(
            0 => array(
                "UNIT" => "TB",
                "VALUE" => pow(1024, 4)
            ),
            1 => array(
                "UNIT" => "GB",
                "VALUE" => pow(1024, 3)
            ),
            2 => array(
                "UNIT" => "MB",
                "VALUE" => pow(1024, 2)
            ),
            3 => array(
                "UNIT" => "KB",
                "VALUE" => 1024
            ),
            4 => array(
                "UNIT" => "B",
                "VALUE" => 1
            ),
        );

        foreach($arBytes as $arItem) {
            if($bytes >= $arItem["VALUE"]) {
                $result = $bytes / $arItem["VALUE"];
                $result = str_replace(".", "," , strval(round($result, 2)))." ".$arItem["UNIT"];
                break;
            }
        }
        return empty($result) ? $bytes . 'B' : $result;
    }

    /*
     * 获取上一篇id
     */

    static function getPrevId($model,$id)
    {
        return $model::where('id', '<', $id)->max('id');
    }

    /*
     * 获取下一篇id
     */
    static function getNextId($model,$id)
    {
        return $model::where('id', '>', $id)->min('id');
    }

    /*
     * ab测试接口
     */
    static function testApi(Request $request,$api,$param=[],$method='get',$total=100,$sys=10){
        $cookies = $request->header('cookie');  //获取头信息中的cookie
        $laravel_session = explode(';',$cookies)[1];
        $laravel_session  = str_replace(["\r\n"," "],"",$laravel_session);

        $host = $request->server('HTTP_HOST');  //获取当前服务器的host eg:www.bmwcustomerboard.com:10013
        $paramstr = http_build_query($param);   //把参数转化为key=val&key2=val2格式

        $full_url = 'http://'.$host.'/'.$api;

        $newapi = str_replace('/','_',$api);  //将接口中的/替换为_

        $cookies = "Cookie:".$laravel_session;
        if($method=='post'){
            $tmp = storage_path()."/app/public/testapi";

            if(!file_exists($tmp)){
                if(false===@mkdir($tmp,0777,true)){
                    return '创建testapi文件夹失败';
                }
            }

            $tmpfile = $tmp.'/'.$newapi.'.txt';
            file_put_contents($tmpfile,$paramstr);//参数写进文件

            exec("/opt/testapi/testapipost.sh $total $sys $cookies $tmpfile \"$full_url\" $newapi",$arr);
        }

        if($method=='get'){
            $full_url_param = "{$full_url}?{$paramstr}";
            exec("/opt/testapi/testapiget.sh $total $sys $cookies \"$full_url_param\" $newapi",$arr);
        }

        return $arr;

    }

}