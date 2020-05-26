<?php


class Jav_info extends CI_Controller
{
    private $path,$dir,$format,$actor_arr,$genre_arr,$model,$build_same_title_dir,$build_up_level_dir,$up_level_dir,$fanart,$thumb,$title,$actor,$maker,$label,$set,$director,
        $premiered,$release,$num,$runtime,$genre,$plot,$studio,$year,$filename,$ext,$header,$cookie,$arzon_host;

    public function __construct()
    {
        parent::__construct();
        include "./application/libraries/phpQuery/phpQuery/phpQuery.php";
        $this->header = ['User-Agent' => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.90 Safari/537.36"];
        $this->cookie = "";
        $this->arzon_host = "https://www.arzon.jp";
        $this->path = $this->format = $this->build_same_title_dir = $this->build_up_level_dir = $this->up_level_dir = $this->fanart = $this->thumb = $this->title = $this->actor = $this->maker = 
        $this->label = $this->set = $this->director = $this->premiered = $this->release = $this->num = $this->runtime = $this->genre = $this->plot = $this->studio = $this->year = $this->filename = 
        $this->dir = $this->ext = "";
        $this->actor_arr = $this->genre_arr = [];
        $this->model = "骑兵";
    }

    public function index()
    {
        $this->load->view("jav_info");
    }

    public function check_info()
    {
        $number = htmlspecialchars(trim($this->input->get("number")));
        $model = htmlspecialchars(trim($this->input->get("model")));
        if(empty($number)){
            exit(json_encode(array('status' => 'fail', 'msg' => '缺少番号', 'data' => '', 'err_code' => '')));
        }
        $data = $this->javbus_get_list($number,$model);
        exit(json_encode(array('status' => 'success', 'msg' => 'ok', 'data' => $data, 'err_code' => '')));
    }

    public function javbus_get_list($number,$model="骑兵")
    {
        if($model=="骑兵"){
            $url = "https://www.javbus.com/search/{$number}";
        }else{
            $url = "https://www.javbus.com/uncensored/search/{$number}";
        }
        $result = $this->util->http_request($url, [], "GET", false, 120, [], true,"existmag=all");
        phpQuery::newDocument($result);
        $list = pq(".movie-box");
        $detail_arr = [];
        $data = [];
        foreach ($list as $item) {
            $detail_arr[] = pq($item)->attr("href");
            $arr['thumb'] = pq($item)->find("img")->attr("src");
            $arr['poster'] = pq($item)->find("img")->attr("src");
            $arr['title'] = pq($item)->find("img")->attr("title");
            $data[] = $arr;
        }
        foreach ($detail_arr as $key => $val) {
            $this->javbus_get_detail($val, $data[$key]);
        }
        $plot = $this->arzon_get_list($number);
        foreach ($data as $key => $val) {
            $data[$key]['plot'] = $plot;
        }
        return $data;
    }

    public function javbus_get_detail($url, &$data)
    {
        $detail = $this->util->http_request($url, [], "GET", false, 120, [], true);
        phpQuery::newDocument($detail);
        $data['fanart'] = pq(".screencap")->find("img")->attr("src");
        $info = pq(".info p");
        $data["num"] = $data["premiered"] = $data["release"] = $data["runtime"] = $data["director"] = $data["maker"] = $data["label"] = $data["set"] = "";
        foreach ($info as $val) {
            $text = pq($val)->text();
            $text_arr = explode(": ", $text);
            if (!empty($text_arr[1])) {
                $text_arr[1] = trim($text_arr[1]);
                switch ($text_arr[0]) {
                    case "識別碼":{$data["num"] = $text_arr[1];break;}
                    case "發行日期":{$data["premiered"] = $data["release"] = $text_arr[1];break;}
                    case "長度":{$data["runtime"] = $text_arr[1];break;}
                    case "導演":{$data["director"] = $text_arr[1];break;}
                    case "製作商":{$data["maker"] = $text_arr[1];break;}
                    case "發行商":{$data["label"] = $text_arr[1];break;}
                    case "系列":{$data["set"] = $text_arr[1];break;}
                }
            }
        }
        $data['studio'] = $data['maker'] . "/" . $data['label'];
        $data['year'] = substr($data['premiered'], 0, 4);
        $data['outline'] = "";
        $data['plot'] = "";
        $data['genre'] = $data['actor'] = [];
        $style = pq(".info")->find(".genre");
        foreach ($style as $val) {
            if (empty(pq($val)->attr("onmouseover"))) {
                $data['genre'][] = pq($val)->find("a")->text();
            } else {
                $data['actor'][] = pq($val)->find("a")->text();
            }
        }
        $data['genre'] = implode(",", $data['genre']);
        foreach ($data['actor'] as $actor) {
            $data['title'] = trim(str_replace($actor, "", $data['title']));
        }
        $data['actor'] = implode(",", $data['actor']);
    }

    public function arzon_get_list($number)
    {
        $url = "https://www.arzon.jp/itemlist.html";
        $result = $this->util->http_request($url, ["q" => $number], "GET", false, 120, $this->header, true, $this->cookie, true);
        if (!empty(strstr($result['content'], "年齢認証"))) {
            $this->cookie = !empty($result['cookie']['value']) ?: "";
            $url = "https://www.arzon.jp/index.php?action=adult_customer_agecheck&agecheck=1&redirect=https%3A%2F%2Fwww.arzon.jp%2Fitemlist.html%3Fq%3D{$number}";
            $result = $this->util->http_request($url, [], "GET", false, 120, $this->header, true, $this->cookie, true);
        }
        phpQuery::newDocumentHTML($result['content']);
        $list = pq(".hentry");
        $detail_arr = [];
        $plot = "";
        foreach ($list as $item) {
            $detail_arr[] = pq($item)->find("dt a")->attr("href");
        }
        if (!empty($detail_arr[0])) {
            $plot = $this->arzon_get_plot($detail_arr[0]);
        }
        return $plot;
    }

    public function arzon_get_plot($url)
    {
        $url = $this->arzon_host . $url;
        $detail = $this->util->http_request($url, [], "GET", false, 120, $this->header, true, $this->cookie, true);
        phpQuery::newDocumentHTML($detail['content']);
        $tr = pq("table.item_detail")->find("tr");
        $intro = pq($tr)->eq(1);
        $plot = trim(str_replace("作品紹介", "", pq($intro)->find(".item_text")->text()), "\r\n        \r\n");
        return $plot;
    }

    public function rename_file()
    {
        set_time_limit(0);
        $this->set_params();
        $fanart_path = $this->download_img($this->fanart, $this->dir, $this->filename, "fanart");
        if($this->model == "骑兵"){
            $thumb_path = $this->cut_thumb($this->dir,$fanart_path);
        }else{
            $thumb_path = $this->download_img($this->thumb, $this->dir, $this->filename, "thumb");
        }

        $nfo = "<movie>
  <title>{$this->title}</title>
  <studio>{$this->studio}</studio>
  <maker>{$this->maker}</maker>
  <label>{$this->label}</label>
  <year>{$this->year}</year>
  <premiered>{$this->premiered}</premiered>
  <release>{$this->release}</release>
  <outline></outline>
  <plot>{$this->plot}</plot>
  <runtime>{$this->runtime}</runtime>
  <director>{$this->director}</director>
  <num>{$this->num}</num>
  <set>\r\n    <name>{$this->set}</name>\r\n  </set>
";
        foreach ($this->actor_arr as $val) {
            $nfo .= "  <actor>\r\n    <name>{$val}</name>\r\n    <type>actor</type>\r\n  </actor>\r\n";
        }
        foreach ($this->genre_arr as $val) {
            $nfo .= "  <genre>{$val}</genre>\r\n";
        }
        $nfo .= "  <fanart>{$fanart_path}</fanart>\r\n";
        $nfo .= "  <thumb>{$thumb_path}</thumb>\r\n";
        $nfo .= "  <poster>{$thumb_path}</poster>\r\n";
        $nfo .= "</movie>";

        rename($this->path,$this->dir . $this->filename . "." . $this->ext);
        $nfo_file = fopen($this->dir . $this->filename . ".nfo", "w");
        fwrite($nfo_file, $nfo);
        fclose($nfo_file);
        exit(json_encode(array('status' => 'success', 'msg' => '重命名成功', 'data' => '', 'err_code' => '')));
    }

    public function set_params()
    {
        $this->path = trim($this->input->post("path"));
        $this->format = trim($this->input->post("format"));
        $this->model = trim($this->input->post("model"));
        $this->build_same_title_dir = trim($this->input->post("build_same_title_dir")) == "on" ? true : false;
        $this->build_up_level_dir = trim($this->input->post("build_up_level_dir")) == "on" ? true : false;
        $this->up_level_dir = trim($this->input->post("up_level_dir", true));
        $this->fanart = trim($this->input->post("fanart", true));
        $this->thumb = trim($this->input->post("thumb", true));
        $this->title = trim($this->input->post("title", true));
        $this->actor = trim($this->input->post("actor", true));
        $this->maker = trim($this->input->post("maker", true));
        $this->label = trim($this->input->post("label", true));
        $this->set = trim($this->input->post("set", true));
        $this->director = trim($this->input->post("director", true));
        $this->premiered = $this->release = trim($this->input->post("premiered", true));
        $this->num = trim($this->input->post("num", true));
        $this->runtime = trim($this->input->post("runtime", true));
        $this->genre = trim($this->input->post("genre", true));
        $this->plot = trim($this->input->post("plot", true));
        $this->actor_arr = explode(",", $this->actor);
        $this->genre_arr = explode(",", $this->genre);
        $this->studio = $this->maker . "/" . $this->label;
        $this->year = substr($this->premiered, 0, 4);
        if (count($this->actor_arr) > 1) {
            $this->actor = $this->actor_arr[0];
        }
        if (empty($this->path) || empty($this->format) || empty($this->title)) {
            exit(json_encode(array('status' => 'fail', 'msg' => '请填写完整或进行搜索', 'data' => '', 'err_code' => '')));
        }
        $this->title = str_replace(["/","\\",":","*","?","\"",">","<","|"],"",$this->title);
        if(mb_strlen($this->title)>100){
            $this->title = mb_substr($this->title,0,100);
        }
        //生成文件夹
        $this->filename = $this->replace_name($this->format);
        $this->dir = dirname($this->path) . "\\";
        $this->ext = pathinfo($this->path)['extension'];
        if ($this->build_up_level_dir) {
            $this->up_level_dir = $this->replace_name($this->up_level_dir);
            $this->dir .= $this->up_level_dir . "\\";
            if (!is_dir($this->dir)) {
                mkdir($this->dir);
            }
        }
        if ($this->build_same_title_dir) {
            $this->dir .= $this->filename . "\\";
            if (!is_dir($this->dir)) {
                mkdir($this->dir);
            }
        }
    }

    public function replace_name($str)
    {
        $str = str_replace("{num}", $this->num, $str);
        $str = str_replace("{actor}", $this->actor, $str);
        $str = str_replace("{title}", $this->title, $str);
        $str = str_replace("{maker}", $this->maker, $str);
        $str = str_replace("{year}", $this->year, $str);
        return $str;
    }

    public function download_img($url, $dir, $filename, $type="fanart")
    {
        if (empty($url)) {
            exit(json_encode(array('status' => 'fail', 'msg' => '缺少图片地址', 'data' => '', 'err_code' => '')));
        }
        $ext = pathinfo($url)['extension'];
        if($type != "fanart"){
            $ext = "png";
        }
        /*$ctx = stream_context_create(array(
                "http" => array("timeout" => 5,
                    "proxy" => "127.0.0.1:1080",
                    "request_fulluri" => True,)
            )
        );
        $content = file_get_contents($url,false,$ctx);*/
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 0);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
        curl_setopt($ch, CURLOPT_PROXY, "127.0.0.1");
        curl_setopt($ch, CURLOPT_PROXYPORT, "1080");
        $content = curl_exec($ch);
        curl_close($ch);
        $download_file = fopen($dir . $filename . "." . $ext, "w");
        fwrite($download_file, $content);
        fclose($download_file);
        return $filename .".". $ext;
    }

    public function dl_img()
    {
        $this->set_params();
        $type = htmlspecialchars(trim($this->input->post("type", true)));
        if ($type == "fanart") {
            $url = $this->fanart;
        } else {
            $url = $this->thumb;
        }
        $this->download_img($url, $this->dir, $this->filename, $type);
        exit(json_encode(array('status' => 'success', 'msg' => '保存成功', 'data' => '', 'err_code' => '')));
    }

    public function get_new_file(){
        $this->set_params();
        exit(json_encode(array('status' => 'success', 'msg' => 'ok', 'data' => $this->dir . $this->filename . "." . $this->ext, 'err_code' => '')));
    }

    public function cut_thumb($dir, $filename){
        $path = $dir.$filename;
        $img_info = getimagesize($path);
        $src_width = $img_info[0];
        $src_height = $target_height = $img_info[1];
        $target_width = $src_width/2-22;
        $img_type = $img_info[2];
        $dst_ims = imagecreatetruecolor($target_width, $target_height);//创建真彩画布
        $white = imagecolorallocate($dst_ims, 0, 0, 0);
        imagefill($dst_ims, 0, 0, $white);
        if($img_type == 2){
            $src_im = @imagecreatefromjpeg($path);//读取原图像
        }else if($img_type == 3){
            $src_im = @imagecreatefrompng($path);//读取原图像
        }else{
			$src_im = @imagecreatefromgif($path);//读取原图像
		}
        imagecopy($dst_ims, $src_im, 0, 0 ,$src_width/2+22, 0 , $src_width , $src_height);//缩放图片到指定尺寸
        $new_filename = str_replace([".jpg",".jpeg"],"",$filename).".png";
        imagepng($dst_ims,$dir.$new_filename);
        imagedestroy($dst_ims);
        imagedestroy($src_im);
        return $new_filename;
    }
}
