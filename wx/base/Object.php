<?php
namespace wx\base;
/**
*
*/
class Object
{

    public $host = "http://wxzhsngq.tunnel.2bdata.com";

    public $wxToken = 'ydgczgmdcjrph3jcvbdp4miffw0sdxos';

    public $appId = 'wx8e4ec4591312a0ef';

    public $appSecret = 'f3c41005f745d79d9f4cdbe2f2e2eca7';

    public $authorization=false;

    function reUrl($url){
        header("location: {$url}");
        exit;
    }
}
