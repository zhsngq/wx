<?php
namespace wx;

use wx\base\Object;

/**
 *
 */
class Tool extends Object {

	/**
	 * 用SHA1算法生成安全签名
	 * @param string $token 票据
	 * @param string $timestamp 时间戳
	 * @param string $nonce 随机字符串
	 * @param string $encrypt 密文消息
	 */
	function getSHA1($token, $timestamp, $nonce) {
		//排序
		try {
			$array = array($token, $timestamp, $nonce);
			sort($array, SORT_STRING);
			$str = sha1(implode($array));
			return $_GET['signature'] == $str;
		} catch (Exception $e) {
			return false;
		}
	}

	function tokenVerificate() {
		if ($this->getSHA1($this->wxToken, $_GET['timestamp'], $_GET['nonce'])) {
			echo $_GET['echostr'];
		} else {
			echo "error";
		}
	}

	function getAccessToken() {
		$wxaccesstoken = $this->getCache('wxaccesstoken');
		if ($this->getCache('wxaccesstoken')) {
			if (time() - $wxaccesstoken['time'] > ($wxaccesstoken['expires_in'] - 10)) {
				$this->setAccessToken();
			}
		} else {
			$this->setAccessToken();
		}
		return $wxaccesstoken['access_token'];
	}

	function setAccessToken() {
		$res = $this->cUrl("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->appId}&secret={$this->appSecret}");
		$res = json_decode($res, true);
		if (isset($res['errcode'])) {
			new Exception(json_encode($res));
		}
		$res['time'] = time();
		$this->setCache('wxaccesstoken', $res);
		$this->getAccessToken();
	}

	/**
	 * [cUrl cURL(支持HTTP/HTTPS，GET/POST)]
	 * @author qiuguanyou
	 * @copyright 烟火里的尘埃
	 * @version   V1.0
	 * @date      2017-04-12
	 * @param     [string]     $url    [请求地址]
	 * @param     [Array]      $header [HTTP Request headers array('Content-Type'=>'application/x-www-form-urlencoded')]
	 * @param     [Array]      $data   [参数数据 array('name'=>'value')]
	 * @return    [type]               [如果服务器返回xml则返回xml，不然则返回json]
	 */
	function cUrl($url, $header = null, $data = null) {
		//初始化curl
		$curl = curl_init();
		//设置cURL传输选项
		if (is_array($header)) {
			curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		if (!empty($data)) {
//post方式
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
		//获取采集结果
		$output = curl_exec($curl);
		//关闭cURL链接
		curl_close($curl);
		return $output;
	}

	function getAuth() {
		$_SESSION['url'] = $this->host . $_SERVER["REQUEST_URI"];
		if (!isset($_SESSION['hwxaccesstoken'])
			|| time() - $_SESSION['hwxaccesstoken']['time'] > ($_SESSION['hwxaccesstoken']['expires_in'] - 10)) {
			$this->reUrl(
				$this->getWxUrl($this->host . "/wx/api/HAccessToken.php")
			);
		}
	}

	function getWxUrl($url) {
		$url = urlencode($url);
		$scope = $this->authorization?'snsapi_userinfo':'snsapi_base';
		return "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->appId}&" .
			"redirect_uri={$url}" .
			"&response_type=code".
			"&scope=$scope".
			"&state=STATE#wechat_redirect";
	}

	function getHAccessToken() {
		$this->setHAccessToken();
		$this->reUrl($_SESSION['url']);
	}

	function getUser() {
		$this->getAuth();
		if (!isset($_SESSION['user'])) {
			if ($this->authorization) {
				$res = $this->cUrl("https://api.weixin.qq.com/sns/userinfo?" .
					"access_token={$_SESSION['hwxaccesstoken']['access_token']}" .
					"&openid={$_SESSION['hwxaccesstoken']['openid']}" .
					"&lang=zh_CN");
			} else {
				$res = $this->cUrl("https://api.weixin.qq.com/cgi-bin/user/info?".
					"access_token=".$this->getAccessToken().
					"&openid={$_SESSION['hwxaccesstoken']['openid']}".
					"&lang=zh_CN");
			}
			$res = json_decode($res, true);
			if (isset($res['errcode'])) {
				new Exception('wx get user error');
			}
			$_SESSION['user'] = $res;
		}
		return $_SESSION['user'];
	}

	function setHAccessToken() {
		if (!isset($_GET['code'])) {
			new Exception('token has code is not find!');
		}
		$res = $this->cUrl("https://api.weixin.qq.com/sns/oauth2/access_token?" .
			"appid={$this->appId}" .
			"&secret={$this->appSecret}" .
			"&code={$_GET['code']}" .
			"&grant_type=authorization_code");
		$res = json_decode($res, true);
		if (isset($res['errcode'])) {
			new Exception('haccess_token error');
		}
		$res['time'] = time();
		$_SESSION['hwxaccesstoken'] = $res;
	}

	function setCache($key, $val) {
		$redis = new \Redis();
		$redis->connect('127.0.0.1', 6379);
		if (is_array($val)) {
			$val = json_encode($val);
		}
		$redis->set($key, $val);
	}

	function getCache($key) {
		$redis = new \Redis();
		$redis->connect('127.0.0.1', 6379);
		$res = $redis->get($key);
		if ($res == false) {
			return false;
		}
		return json_decode($res, true);
	}

	private function getJsApiTicket() {
		$jsTicket = $this->getCache('ticket');
		if (!$jsTicket || (time() - $jsTicket['time']) > ($jsTicket['expires_in'] - 10)) {
			$this->setJsApiTicket();
		}
		return $jsTicket['ticket'];
	}

	private function setJsApiTicket() {
		$accessToken = $this->getAccessToken();
		$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
		$res = json_decode($this->cUrl($url), true);
		if ($res['errcode'] != 0) {
			new Exception('get ticket error');
		}
		$res['time'] = time();
		$this->setCache('ticket', $res);
		$this->getJsApiTicket();
	}

	public function getSignPackage() {
		$jsapiTicket = $this->getJsApiTicket();
		$url = $this->host . $_SERVER['REQUEST_URI'];
		$timestamp = time();
		$nonceStr = $this->createNonceStr();
		$string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
		$signature = sha1($string);
		$signPackage = array(
			"appId" => $this->appId,
			"nonceStr" => $nonceStr,
			"timestamp" => $timestamp,
			"url" => $url,
			"signature" => $signature,
			"rawString" => $string,
		);
		return $signPackage;
	}

	private function createNonceStr($length = 16) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$str = "";
		for ($i = 0; $i < $length; $i++) {
			$str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
		}
		return $str;
	}

	function getJssdk() {
		$signPackage = $this->getSignPackage();
		include 'jssdk.html';
	}
}
