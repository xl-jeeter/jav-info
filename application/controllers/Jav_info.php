<?php
include "./application/libraries/phpQuery/phpQuery/phpQuery.php";

class Jav_info extends CI_Controller
{
	private $path = "";                         //视频文件路径
	private $target_path = "";                  //重命名后视频存放的根目录
	private $dir = "";                          //重命名后视频存放的完整目录
	private $format = "";                       //视频命名格式，最终会替换成对应的信息
	private $actor_arr = [];                    //演员数组
	private $genre_arr = [];                    //视频类型数组
	private $model = "骑兵";                    //搜索模式，骑兵或步兵，默认骑兵
	private $build_same_title_dir = true;       //是否生成视频同名目录，用于存放视频、nfo、海报图片
	private $build_up_level_dir = true;         //是否生成上级目录，用于存放同类视频，如：按照演员、年份分类
	private $up_level_dir = "";                 //上级目录命名格式，最终会替换成对应的信息
	private $fanart = "";                       //海报原图地址
	private $thumb = "";                        //海报缩略图地址
	private $title = "";                        //标题
	private $actor = "";                        //演员
	private $maker = "";                        //制作商
	private $label = "";                        //发行商
	private $set = "";                          //系列
	private $director = "";                     //导演
	private $premiered = "";                    //发售日
	private $release = "";                      //
	private $num = "";                          //番号
	private $runtime = "";                      //片场
	private $genre = "";                        //视频类型
	private $plot = "";                         //简介
	private $studio = "";                       //工作室
	private $year = "";                         //发售年份
	private $filename = "";                     //文件名，根据format替换信息后生成
	private $ext = "";                          //视频文件扩展名
	private $header = ['User-Agent' => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.90 Safari/537.36"];
	private $cookie = "";
	private $arzon_host = "https://www.arzon.jp";
	private $file_list = [];                    //扫描存放资源的根目录后，得出的视频文件路径列表

	public function __construct()
	{
		parent::__construct();
	}

	public function index()
	{
		$this->load->view("jav_info");
	}

	/**
	 * 搜索视频信息
	 */
	public function check_info()
	{
		$number = htmlspecialchars(trim($this->input->get("number")));
		$model = htmlspecialchars(trim($this->input->get("model")));
		if (empty($number)) {
			exit(json_encode(array('status' => 'fail', 'msg' => '缺少番号', 'data' => '', 'err_code' => '')));
		}
		$data = $this->javbus_get_list($number, $model);
		if (empty($data)) {
			exit(json_encode(array('status' => 'fail', 'msg' => '找不到电影信息', 'data' => '', 'err_code' => '')));
		} else {
			exit(json_encode(array('status' => 'success', 'msg' => 'ok', 'data' => $data, 'err_code' => '')));
		}
	}

	/**
	 * 爬取javbus的番号对应的搜索结果
	 * @param string $number 番号
	 * @param string $model 搜索模式
	 * @return array
	 */
	public function javbus_get_list($number, $model = "骑兵")
	{
		if ($model == "骑兵") {
			$url = "https://www.javbus.com/search/{$number}";   //骑兵搜索地址
		} else {
			$url = "https://www.javbus.com/uncensored/search/{$number}";    //步兵搜索地址
		}
		$result = $this->util->http_request($url, [], "GET", false, 120, [], true, "existmag=all");
		phpQuery::newDocument($result);
		$list = pq(".movie-box");
		$detail_arr = [];
		$data = [];
		foreach ($list as $item) {      //获取所有搜索结果的信息
			$detail_arr[] = pq($item)->attr("href");        //搜索结果的链接
			$arr['thumb'] = pq($item)->find("img")->attr("src");
			$arr['poster'] = pq($item)->find("img")->attr("src");
			$arr['title'] = pq($item)->find("img")->attr("title");
			$data[] = $arr;
		}
		foreach ($detail_arr as $key => $val) {     //获取每个搜索结果的详细信息
			$this->javbus_get_detail($val, $data[$key]);
		}
		$plot = $this->arzon_get_list($number);     //在arzon爬取视频的简介
		foreach ($data as $key => $val) {
			$data[$key]['plot'] = $plot;
		}
		return $data;
	}

	/**
	 * 爬取视频的详细信息
	 * @param string $url 搜索结果url
	 * @param array $data 数据数组
	 */
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
					case "識別碼":
					{
						$data["num"] = $text_arr[1];
						break;
					}
					case "發行日期":
					{
						$data["premiered"] = $data["release"] = $text_arr[1];
						break;
					}
					case "長度":
					{
						$data["runtime"] = $text_arr[1];
						break;
					}
					case "導演":
					{
						$data["director"] = $text_arr[1];
						break;
					}
					case "製作商":
					{
						$data["maker"] = $text_arr[1];
						break;
					}
					case "發行商":
					{
						$data["label"] = $text_arr[1];
						break;
					}
					case "系列":
					{
						$data["set"] = $text_arr[1];
						break;
					}
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

	/**
	 * 爬取arzon的搜索结果列表
	 * @param string $number 番号
	 * @return string
	 */
	public function arzon_get_list($number)
	{
		$url = "https://www.arzon.jp/itemlist.html";
		$result = $this->util->http_request($url, ["q" => $number], "GET", false, 120, $this->header, true, $this->cookie, true);
		if (!empty(strstr($result['content'], "年齢認証"))) {       //爬取时可能会有年龄认证页面
			$this->cookie = !empty($result['cookie']['value']) ?: "";       //设置cookie
			$url = "https://www.arzon.jp/index.php?action=adult_customer_agecheck&agecheck=1&redirect=https%3A%2F%2Fwww.arzon.jp%2Fitemlist.html%3Fq%3D{$number}";
			$result = $this->util->http_request($url, [], "GET", false, 120, $this->header, true, $this->cookie, true);     //使用cookie请求认证链接
		}
		phpQuery::newDocumentHTML($result['content']);
		$list = pq(".hentry");
		$detail_arr = [];
		$plot = "";
		foreach ($list as $item) {
			$detail_arr[] = pq($item)->find("dt a")->attr("href");
		}
		if (!empty($detail_arr[0])) {       //爬取第一个搜索结果
			$plot = $this->arzon_get_plot($detail_arr[0]);
		}
		return $plot;
	}

	/**
	 * 根据搜索结果爬取视频简介
	 * @param string $url 搜索结果url
	 * @return string
	 */
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

	/**
	 * 重命名视频文件并移动到指定的目录
	 */
	public function rename_file()
	{
		set_time_limit(0);
		$this->set_params();
		$fanart_path = $this->download_img($this->fanart, $this->dir, $this->filename, "fanart");   //下载海报原图
		if ($this->model == "骑兵") {       //缩略图分辨率太低，直接从原图裁切
			$thumb_path = $this->cut_thumb($this->dir, $fanart_path);
		} else {      //无码直接下载缩略图
			$thumb_path = $this->download_img($this->thumb, $this->dir, $this->filename, "thumb");
		}
		//nfo文件的内容
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
		foreach ($this->actor_arr as $val) {    //演员
			$nfo .= "  <actor>\r\n    <name>{$val}</name>\r\n    <type>actor</type>\r\n  </actor>\r\n";
		}
		foreach ($this->genre_arr as $val) {    //类型
			$nfo .= "  <genre>{$val}</genre>\r\n";
		}
		$nfo .= "  <fanart>{$fanart_path}</fanart>\r\n";
		$nfo .= "  <thumb>{$thumb_path}</thumb>\r\n";
		$nfo .= "  <poster>{$thumb_path}</poster>\r\n";
		$nfo .= "</movie>";

		rename($this->path, $this->dir . $this->filename . "." . $this->ext);    //移动文件到新目录并重命名
		$nfo_file = fopen($this->dir . $this->filename . ".nfo", "w");    //写入nfo文件
		fwrite($nfo_file, $nfo);
		fclose($nfo_file);
		exit(json_encode(array('status' => 'success', 'msg' => '重命名成功', 'data' => '', 'err_code' => '')));
	}

	/**
	 * 设置相关参数
	 */
	public function set_params()
	{
		$this->target_path = trim($this->input->post("target_path"));
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
		if (count($this->actor_arr) > 1) {  //如果有多个演员，只取第一个作为文件名替换
			$this->actor = $this->actor_arr[0];
		}
		if (empty($this->path) || empty($this->format) || empty($this->title)) {
			exit(json_encode(array('status' => 'fail', 'msg' => '请填写完整或进行搜索', 'data' => '', 'err_code' => '')));
		}
		$this->title = str_replace(["/", "\\", ":", "*", "?", "\"", ">", "<", "|"], "", $this->title);    //删除标题中无效字符
		if (mb_strlen($this->title) > 50) {    //标题太长的话，Emby会扫描媒体库失败，所以裁剪一下
			$this->title = mb_substr($this->title, 0, 50);
		}
		$this->target_path = str_replace("\\", "/", $this->target_path);  //地址统一只用左斜杠
		//生成文件夹
		$this->filename = $this->replace_name($this->format);   //按照格式替换文件名
		$this->dir = rtrim($this->target_path, "/") . "/";
		if (!is_dir($this->dir)) {
			exit(json_encode(array('status' => 'fail', 'msg' => '目标根目录不是有效地址', 'data' => '', 'err_code' => '')));
		}
		$this->ext = pathinfo($this->path)['extension'];
		if ($this->build_up_level_dir) {        //生成上级分类目录
			$this->up_level_dir = $this->replace_name($this->up_level_dir);
			$this->dir .= $this->up_level_dir . "/";
			if (!is_dir($this->dir)) {
				mkdir($this->dir);
			}
		}
		if ($this->build_same_title_dir) {      //生成视频同名目录
			$this->dir .= $this->filename . "/";
			if (!is_dir($this->dir)) {
				mkdir($this->dir);
			}
		}
	}

	/**
	 * 按照格式替换响应信息
	 * @param string $str 命名格式
	 * @return mixed
	 */
	public function replace_name($str)
	{
		$str = str_replace("{num}", $this->num, $str);
		$str = str_replace("{actor}", $this->actor, $str);
		$str = str_replace("{title}", $this->title, $str);
		$str = str_replace("{maker}", $this->maker, $str);
		$str = str_replace("{year}", $this->year, $str);
		return $str;
	}

	/**
	 * 下载图片
	 * @param string $url 图片地址
	 * @param string $dir 存放目录
	 * @param string $filename 图片名
	 * @param string $type 图片类型
	 * @return string           图片文件路径
	 */
	public function download_img($url, $dir, $filename, $type = "fanart")
	{
		if (empty($url)) {
			exit(json_encode(array('status' => 'fail', 'msg' => '缺少图片地址', 'data' => '', 'err_code' => '')));
		}
		$ext = pathinfo($url)['extension'];
		if ($type != "fanart") {      //缩略图使用png格式区分
			$ext = "png";
		}
		$content = $this->util->http_request($url, [], "GET", false, 6, [], true, "", false);
		$download_file = fopen($dir . $filename . "." . $ext, "w");     //生成图片文件
		fwrite($download_file, $content);
		fclose($download_file);
		return $filename . "." . $ext;
	}

	/**
	 * 单独下载图片入口
	 */
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

	/**
	 * 生成视频的新目录文件夹
	 */
	public function get_new_file()
	{
		$this->set_params();
		exit(json_encode(array('status' => 'success', 'msg' => 'ok', 'data' => $this->dir . $this->filename . "." . $this->ext, 'err_code' => '')));
	}

	/**
	 * 裁切图片
	 * @param string $dir 文件目录
	 * @param string $filename 文件名
	 * @return string           裁切后的图片路径
	 */
	public function cut_thumb($dir, $filename)
	{
		$path = $dir . $filename;
		$img_info = getimagesize($path);
		$src_width = $img_info[0];
		$src_height = $target_height = $img_info[1];
		$target_width = $src_width / 2 - 22;
		$img_type = $img_info[2];
		$dst_ims = imagecreatetruecolor($target_width, $target_height);//创建真彩画布
		$white = imagecolorallocate($dst_ims, 0, 0, 0);
		imagefill($dst_ims, 0, 0, $white);
		if ($img_type == 2) {
			$src_im = @imagecreatefromjpeg($path);//读取原图像
		} else if ($img_type == 3) {
			$src_im = @imagecreatefrompng($path);//读取原图像
		}
		imagecopy($dst_ims, $src_im, 0, 0, $src_width / 2 + 22, 0, $src_width, $src_height);//缩放图片到指定尺寸
		$new_filename = str_replace([".jpg", ".jpeg"], "", $filename) . ".png";
		imagepng($dst_ims, $dir . $new_filename);      //生成图片
		imagedestroy($dst_ims);
		imagedestroy($src_im);
		return $new_filename;
	}

	/**
	 * 扫描存放资源的根目录下所有视频文件
	 */
	public function scan_file()
	{
		$source_path = trim($this->input->get("source_path", true));
		$source_path = str_replace("\\", "/", $source_path);
		$this->file_list($source_path);
		exit(json_encode(array('status' => 'success', 'msg' => 'ok', 'data' => $this->file_list, 'err_code' => '')));
	}

	/**
	 * 递归扫描文件
	 * @param string $path 进行扫描的目录
	 */
	public function file_list($path)
	{
		$path = rtrim($path, "/") . "/";
		$arr = scandir($path);      //扫描目录下所有文件和文件夹
		foreach ($arr as $item) {
			if (in_array($item, [".", ".."])) {     //过滤无用文件
				continue;
			}
			$file_path = $path . $item;       //当前操作目录
			if (is_dir($path . $item)) {    //如果是文件夹，继续扫描内容
				$this->file_list($file_path);
			} else {                                //是文件
				$file_info = pathinfo($file_path);  //获取扩展名
				if (!isset($file_info['extension'])) {
					continue;
				}
				$ext = strtolower($file_info['extension']);
				if (in_array($ext, ["mp4", "avi", "mkv", "wmv", "rmvb", "rm"])) {   //如果是视频文件，就插入文件路径到file_list
					$this->file_list[] = $file_path;
				}
			}
		}
	}
}
