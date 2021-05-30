<?php
require_once('proxy.php');

define('PROXY_SERVER_LIST', []);

(new ProxyMiddleware(PROXY_SERVER_LIST))->doUsingGet();