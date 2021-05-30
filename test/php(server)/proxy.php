<?php

define('PROXY_URL_HEADER', 'X-Proxy-Dist-Url');

class URLTools{
    const METHOD_MAP = [
        'HEAD' => CURLOPT_NOBODY,
        'POST' => CURLOPT_POST,
        'GET' => CURLOPT_HTTPGET
    ];
    public static function setDefault(&$init, $key, $value){
        if(!isset($init[$key])) $init[$key] = $value;
    }
    /**
     * Regex, return true is match, false instead.
     * @param array|String $subject
     * @param array|String $pattern
     */
    public static function regMatch($subject, $pattern){
        $text = preg_replace($pattern, "yes", $subject);
        if($text == 'yes') return true;
        return false;
    }
    public static function isHttps($url){
        return URLTools::regMatch($url, '#(https://(\S*?\.\S*?))([\s)\[\]{},;"\':<]|\.\s|$)#i');
    }
    public static function isHttp($url){
        return URLTools::regMatch($url, '#(http://(\S*?\.\S*?))([\s)\[\]{},;"\':<]|\.\s|$)#i');
    }
    public static function getDefaultCurlHandle($url, $init=[]){
        // URLTools::setDefault($init, "headers", []);
        // URLTools::setDefault($init["headers"], "Accept", "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8");
        // URLTools::setDefault($init["headers"], "Accept-Encoding", "gzip, deflate");
        // URLTools::setDefault($init["headers"], "Accept-Language", "zh-TW,zh;q=0.8,en-US;q=0.5,en;q=0.3");
        // URLTools::setDefault($init["headers"], "User-Agent", "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:86.0) Gecko/20100101 Firefox/86.0");
        // URLTools::setDefault($init, "method", "GET");
        // URLTools::setDefault($init, "body", 0);
        $headers = URLTools::getRequestHeaders();
        $body = file_get_contents('php://input');

        URLTools::setDefault($init, "headers", $headers);
        URLTools::setDefault($init, "method", strtoupper($_SERVER['REQUEST_METHOD']));
        if($body <> '') URLTools::setDefault($init, "body", 0);
        else URLTools::setDefault($init, "body", $body);
        URLTools::setDefault($init, "redirect", true);

        $ch = curl_init();
        //pretty_print_r($init, 'crlf');
        curl_setopt($ch, CURLOPT_URL, $url); //設定url
        //curl_setopt($ch, CURLOPT_HTTPHEADER, 0); //回傳header訊息
        curl_setopt($ch, CURLOPT_POSTFIELDS, $init["body"]); //head 或 get 不能有 body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //如果成功只將結果返回，不自動輸出任何內容。
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $init["redirect"]); // follow redirects.
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);  // 自動設定Referer
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);  // timeout on connect
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);  // timeout on response
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);  // stop after 10 redirects

        if(isset($init["headers"]["Referer"])) {
            curl_setopt($ch, CURLOPT_REFERER, $init["headers"]["Referer"]);
            unset($init["headers"]["Referer"]);
        }
        if(isset($init["headers"]["User-Agent"])) {
            curl_setopt($ch, CURLOPT_USERAGENT, $init["headers"]["User-Agent"]);
            unset($init["headers"]["User-Agent"]);
        }
        if(isset($init["headers"]["Accept-Encoding"])) {
            curl_setopt($ch, CURLOPT_ENCODING, $init["headers"]["Accept-Encoding"]);
            unset($init["headers"]["Accept-Encoding"]);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, URLTools::mergeHeaders($init["headers"]));

        curl_setopt($ch, CURLOPT_COOKIESESSION, 1); 
        curl_setopt( $ch, CURLOPT_COOKIEJAR,  'cookie.txt' );
        curl_setopt( $ch, CURLOPT_COOKIEFILE, 'cookie.txt' );

        if(array_key_exists(strtoupper($init["method"]), URLTools::METHOD_MAP)){
            curl_setopt($ch, URLTools::METHOD_MAP[$init["method"]], true);
        }else{
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $init["method"]);
        }

        if(URLTools::isHttps($url)){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //這個是重點,規避ssl的證書檢查。
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 跳過host驗證
        }
        return $ch;
    }
    public static function getCurlResponseInfo($curlHandle){
        $err     = curl_errno( $curlHandle );
        $errmsg  = curl_error( $curlHandle );
        $header  = curl_getinfo( $curlHandle );

        return ['errno'=>$err, 'errmsg'=>$errmsg, 'header'=>$header];
    }
    /** Like fetch in javascript. See https://developer.mozilla.org/en-US/docs/Web/API/WindowOrWorkerGlobalScope/fetch.
    * @param String $url        請求的url
    * @param Array  $init       An object containing any custom settings that you want to apply to the request. 
    */
    public static function fetch($url, $init=[]){
        $ch = URLTools::getDefaultCurlHandle($url, $init);
        $content = curl_exec( $ch );
        $res = URLTools::getCurlResponseInfo($ch);
        curl_close( $ch );
        $res['content'] = $content;
        return $res;
    }
    public static function jsFetch($url, $init=[]){
        $data = URLTools::fetch($url, $init);
        $res = [
            'url'=> $data['header']['url'],
            'headers'=> [
                'content-type'=> $data['header']['content_type'],
                'content-length'=> $data['header']['download_content_length'],
            ],
            'status'=> $data['header']['http_code'],
            'body'=> $data['content'],
        ];
        return $res;
    }
    public static function toPhpHeader($key){
        $keys = explode('-', $key);
        foreach($keys as $k => $v){
            $keys[$k] = ucfirst($v);
        }
        $key = implode('-', $keys);
        return $key;
    }
    public static function getRequestHeaders() {
        $headers = array();
        foreach($_SERVER as $key => $value) {
            if (substr($key, 0, 5) <> 'HTTP_') {
                continue;
            }
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[trim($header)] = trim($value);
        }
        return $headers;
    }
    public static function mergeHeaders($headers){
        $res = [];
        foreach($headers as $key => $val){
            $res[] = $key.': '.$val;
        }
        return $res;
    }
    public static function parsePost(){
        $data = file_get_contents('php://input');
        $json = json_decode($data, TRUE);
        if(!isset($json['init'])){
            $json['init'] = [];
        }
        return $json;
    }
    public static function simpleProxy(){
        $json = URLTools::parsePost();
        $res = URLTools::jsFetch($json['url'], $json['init']);
        //solve if body is binary
        //become: length of json, json, body
        $body = $res['body'];
        unset($res['body']);
        $res = json_encode($res);
        echo strlen($res);
        echo $res;
        echo $body;
    }
    public static function decodeURIcomponent($obj){
        if(!is_array($obj)){
            return urldecode($obj);
        }
        $res = [];
        foreach($obj as $key=>$val){
            $res[URLTools::decodeURIcomponent($key)] = URLTools::decodeURIcomponent($val);
        }
        return $res;
    }
}
class ProxyServer{
    protected $url;
    function __construct()
    {

    }
    protected function getCh($type){
        $ch = null;
        $headers = URLTools::getRequestHeaders();
        switch($type){
            case 'classic':
                $headers = URLTools::getRequestHeaders();
                $this->url = $headers[PROXY_URL_HEADER];
                unset($headers[PROXY_URL_HEADER]);
                unset($headers["Host"]);
                unset($headers["Referer"]);
                unset($headers["Accept-Encoding"]);
                $ch = URLTOOLS::getDefaultCurlHandle($this->url, ['headers' => $headers]);
                break;
            case "get":
                $this->url = URLTools::decodeURIcomponent($_GET["url"]);
                $init = URLTools::decodeURIcomponent($_GET);
                unset($init["url"]);
                $ch = URLTOOLS::getDefaultCurlHandle($this->url, $init);
                break;
        }
        //print_r($headers);
        return $ch;
    }
    protected function _do($type){
        $ch = $this->getCh($type);
        
        $putStream = new PutStream();
        $downloadStreamChunk = new DownloadStreamChunk($ch, function($ch, $data) use (&$putStream){
            $putStream->put($data);
        });
        $downloadStreamChunk->getDownloadStream()->setHeaderCallback(function($ch, $key, $val){
            if($key == "Content-Encoding" || $key == "Content-Length") return;
            
            header($key.': '.$val, true);
        });
        $downloadStreamChunk->download();
        $putStream->close();
    }
    public function do(){
        $this->_do("classic");
    }
    public function doUsingGet(){
        $this->_do("get");
    }
}
class ProxyClient{
    protected $curlHandle;
    protected $content = '';
    protected $headers = [];
    public $downloadCallback;
    public $downloadHeaderCallback;
    function __construct(){
        $this->downloadCallback = function($ch, $data){
            $this->content += $data;
        };
        $this->downloadHeaderCallback = function($ch, $key, $val){
            $this->headers[$key] = $val;
        };
    }
    protected function _fetch($url, $proxy_url, $init=[], $type='classic'){
        switch($type){
            case 'classic':
                URLTools::setDefault($init, PROXY_URL_HEADER, $url);
                break;
            case "get":
                $url = URLTools::decodeURIcomponent($_GET["url"]);
                $init = URLTools::decodeURIcomponent($_GET);
                unset($init["url"]);
                break;
        }
        $this->curlHandle = URLTOOLS::getDefaultCurlHandle($url, $init);
        $downloadStreamChunk = new DownloadStreamChunk($this->curlHandle, $this->downloadCallback);
        $downloadStreamChunk->getDownloadStream()->setHeaderCallback($this->downloadHeaderCallback);
        $downloadStreamChunk->download();
        return ['body' => $this->content, 'headers' => $this->headers];
    }
    public function fetch($url, $proxy_url, $init=[]){
        return $this->_fetch($url, $proxy_url, $init, $type='classic');
    }
    public function fetchUsingGet($url, $proxy_url, $init=[]){
        return $this->_fetch($url, $proxy_url, $init, $type='get');
    }
}
class ProxyMiddleware extends ProxyServer{
    public $proxy_url_list;
    function __construct($proxy_url_list){
        $this->proxy_url_list = $proxy_url_list;
    }
    protected function getCh($type){
        $ch = null;
        $headers = URLTools::getRequestHeaders();
        $this->url = $this->proxy_url_list[random_int(0, count($this->proxy_url_list)-1)];
        switch($type){
            case 'classic':
                $headers = URLTools::getRequestHeaders();
                
                unset($headers["Host"]);
                unset($headers["Referer"]);
                unset($headers["Accept-Encoding"]);
                URLTools::setDefault($headers, "Transfer-Encoding", 'chunked');
                $ch = URLTOOLS::getDefaultCurlHandle($this->url, ['headers' => $headers]);
                break;
            case "get":
                $url = null;
                if(isset($_GET["url"])){
                    $url = URLTools::decodeURIcomponent($_GET["url"]);
                }else{
                    $url = $headers[PROXY_URL_HEADER];
                }
                $init = URLTools::decodeURIcomponent($_GET);
                $init[PROXY_URL_HEADER] = $url;
                unset($init["url"]);
                URLTools::setDefault($init, "headers", []);
                URLTools::setDefault($headers['headers'], "Transfer-Encoding", 'chunked');
                $ch = URLTOOLS::getDefaultCurlHandle($this->url, $init);
                break;
        }
        //print_r($headers);
        return $ch;
    }
}

class DownloadStream{
    protected $callback;
    protected $headerCallback;
    protected $curlHandle;
    protected $length = 0;
    protected $end = false;
    function __construct($curlHandle, $callback = null, $headerCallback = null)
    {
        $this->setCallback($callback);
        $this->setHeaderCallback($headerCallback);
        $this->curlHandle = $curlHandle;
        curl_setopt($this->curlHandle, CURLOPT_HEADERFUNCTION, function($curl, $header){
            $headerCallback = $this->headerCallback;
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) // ignore invalid headers
                return $len;
            $headerCallback($curl, URLTOOLS::toPhpHeader(trim($header[0])), trim($header[1]));
            return $len;
        });
        curl_setopt($this->curlHandle, CURLOPT_WRITEFUNCTION, function($curl, $data) {
            $len = strlen($data);
            
            $this->length += $len;
            $callback = $this->callback;
            $callback($curl, $data);
            return $len;
        });
    }
    public function download(){
        if($this->callback==null) $this->callback = function($a, $b){};
        if($this->headerCallback==null) $this->headerCallback = function($a, $b){};
        $res =  curl_exec( $this->curlHandle );
        $this->end = true;
        return $res;
    }
    public function getCurlHandle(){
        return $this->curlHandle;
    }
    public function getLength(){
        return $this->length;
    }
    public function setHeaderCallback($func){
        $this->headerCallback = $func;
    }
    public function setCallback($func){
        $this->callback = $func;
    }
    public function isEnd(){
        return $this->end;
    }
}
class DownloadStreamChunk{
    const CHUNK_SIZE = 1024*1024;
    protected $downloadStream;
    protected $count = 0;
    protected $tempData = '';
    protected $callback;
    protected $chunkSize;
    function __construct($ch, $callback = null, $chunkSize = DownloadStreamChunk::CHUNK_SIZE)
    {
        $this->callback = $callback;
        $this->chunkSize = $chunkSize;
        $this->downloadStream = new DownloadStream($ch, function($ch, $data){
            $callback = $this->callback;
            $count = $this->count;
            $tempData = $this->tempData;
            $chunkSize = $this->chunkSize;
            $count += strlen($data);
            $tempData .= $data; 
            if($count >= $chunkSize){
                $chunks = str_split($tempData, $chunkSize);
                if(strlen($chunks[count($chunks)-1]) < $chunkSize)
                    $tempData = array_pop($chunks);
                else $tempData = '';
                $count = strlen($tempData);
                foreach($chunks as $key=>$val){
                    $callback($ch, $val);
                }
            }
            $this->count = $count;
            $this->tempData = $tempData;
        });
    }
    protected function chunkData(){

    }
    public function download(){
        $callback = $this->callback;
        $this->downloadStream->download();
        $callback($this->downloadStream->getCurlHandle(), $this->tempData);
    }
    public function getDownloadStream(){
        return $this->downloadStream;
    }
}
class PutStream{
    protected $fileName;
    protected $stdout;
    function __construct($fn = "")
    {
        $this->fileName = $fn;
        $quoted = sprintf('"%s"', addcslashes(basename($fn), '"\\'));
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $quoted); 
        header('Content-Transfer-Encoding: binary');
        header('Connection: Keep-Alive');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        $this->stdout = fopen('php://output', 'w');
    }
    public function getFileName(){
        return $this->fileName;
    }
    public function put($data){
        fputs($this->stdout, $data);
        ob_flush();
        flush();
    }
    public function close(){
        fclose($this->stdout);
    }
}
class Proxy{
}



function pretty_print_r($arr, $type='html', $indent=4, $indent_inc=4){
    $ln = '</br>';
    $sp = '&nbsp;';
    if($type=='crlf'){
        $ln = "\r\n";
        $sp = ' ';
    }
    if(!is_array($arr)){
        echo $arr;
        return;
    }
    $i = 0;
    $len = count($arr);
    echo "{".$ln;
    foreach($arr as $key => $val){
        echo str_repeat($sp, $indent);
        echo $key.'=>'.$sp;
        pretty_print_r($val, $type, $indent+$indent_inc, $indent_inc);
        $i++;
        if($i==$len) echo $ln;
        else echo ','.$ln;
    }
    echo str_repeat($sp, $indent-$indent_inc).'}';
}

//(new ProxyServer())->doUsingGet();
//$ch = URLTOOLS::getDefaultCurlHandle("https://www.google.com");
//echo curl_exec($ch);
//pretty_print_r(fetch('http://followjohn.epizy.com/getip.php'));

/*test for javascript
var data = {
    url: "https://www.google.com"
};
fetch("http://127.0.0.1/test/proxy.php", {
    body: JSON.stringify(data), // must match 'Content-Type' header
    cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
    credentials: 'same-origin', // include, same-origin, *omit
    headers: {
      'user-agent': 'Mozilla/4.0 MDN Example',
      'content-type': 'application/json'
    },
    method: 'POST', // *GET, POST, PUT, DELETE, etc.
    mode: 'cors', // no-cors, cors, *same-origin
    redirect: 'follow', // manual, *follow, error
    referrer: 'no-referrer', // *client, no-referrer
  })
  .then(response => {return response.text()}).then((res)=>{
  console.log(res)
})
*/

