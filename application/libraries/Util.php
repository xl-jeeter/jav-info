<?php
class Util{
    /**
     * 通过curl方式获取远程的数据
     * @param $url
     * @param array $params
     * @param string $method
     * @param bool $json
     * @param int $timeout
     * @param array $headers
     * @param bool $proxy
     * @param string $cookie
     * @return bool|mixed|string
     */
	function http_request($url, $params=array(), $method='GET',$json=TRUE,$timeout=6,$headers=array(),$proxy=false,$cookie="",$get_header=false)
	{
		$ch = curl_init();
		$method = strtoupper($method);
		if($method == 'GET')
		{
			if(!empty($params))
			{
				$url .= '?'.http_build_query($params,'','&');
			}

			curl_setopt($ch, CURLOPT_URL, $url);
		}
		else if($method == 'POST')
		{
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		}
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        if(!empty($headers)){
            curl_setopt($ch,CURLOPT_HTTPHEADER, $headers);
        }
        if($proxy){
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 0);
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
            curl_setopt($ch, CURLOPT_PROXY, "127.0.0.1");
            curl_setopt($ch, CURLOPT_PROXYPORT, "1080");
        }
        if($cookie){
            curl_setopt($ch, CURLOPT_COOKIE , $cookie );
        }

        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		//curl_setopt($ch, CURLOPT_SSLVERSION, 1);
		$content = curl_exec($ch);
		$info = curl_getinfo($ch);
        // 解析HTTP数据流
        preg_match("/^(.*?)\r\n\r\n/s",$content,$header_match);
        $header = !empty($header_match[1]) ? $header_match[1] : "";
        $body = !empty($header_match[0])? str_replace($header_match[0],"",$content) : $content;
        // 解析COOKIE
        preg_match("/set\-cookie:([^\r\n]*)/i", $header, $matches);
        if(!empty($matches[1])){
            $temp = explode("; ",trim($matches[1]));
            $response_cookie = [];
            foreach ($temp as $key => $val) {
                if($key==0){
                    $response_cookie['value'] = $val;
                    $cookie = $val;
                }else{
                    $kv = explode("=",$val);
                    $response_cookie[$kv[0]] = $kv[1]??"";
                }
            }
        }else{
            $response_cookie = "";
        }

        if(!empty($info['redirect_url'])){
            return $this->http_request($info['redirect_url'],[],"GET",$json,$timeout,$headers,$proxy,$cookie,$get_header);
        }
		if(curl_error($ch)>0)
		{
            log_message('Error', $url.'||'.$method.'||'.var_export($params,true).'||'.var_export($info,true).'||'.curl_error($ch).'||'.curl_errno($ch).'||'.$_SERVER['REQUEST_URI']);
		}
		curl_close($ch);

		if($json)
		{
		    if($get_header){
                return ["header"=>$headers,"cookie"=>$response_cookie,"content"=>json_decode(trim($body), true)];
            }else{
                return json_decode(trim($body), true);
            }
		}
		else
		{
		    if($get_header){
		        return ["header"=>$headers,"cookie"=>$response_cookie,"content"=>$body];
            }else{
                return $body;
            }
		}
	}
}
