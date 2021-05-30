<?php
require_once('proxy.php');

define('PROXY_SERVER_LIST', ['http://127.0.0.1/proxy/test/php(server)/by-post-header.php']);

(new ProxyMiddleware(PROXY_SERVER_LIST))->do();