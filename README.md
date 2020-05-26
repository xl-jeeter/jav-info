# jav-info
根据番号查询jav信息，生成nfo文件，并将视频文件分类到文件夹中
#运行环境
PHP5.6或以上，Apache或Nginx，暂时不需要mysql
#部署项目
直接将项目解压到Apache或Nginx的默认网站根目录。\
如果保留了jav-info文件夹，就在浏览器中输入localhost/jav-info\
如果没有保留jav-info文件夹，直接输入localhost打开
#要求
* 你的电影文件名都有番号。
* 你有稳定的梯子，能用socke5方式，且本地端口为1080,也可以自行修改项目中的端口号：
    1. libraries/Util.php文件，http_request方法，if($proxy)判断下，修改CURLOPT_PROXYPORT的值。
    2. controllers/Jav_info.php文件，download_img方法，修改CURLOPT_PROXYPORT的值。
#使用方法
1. 将所有电影文件存放到一个文件夹中。
2. 填写基础路径。基础路径为你的电影文件存放的文件夹路径。
3. 点击文件路径下选择文件按钮，选择电影文件。
4. 查看左上角番号时候正确，如果不正确，可以修改为正确的。
5. 选择该电影是步兵还是骑兵。
6. 设置电影文件重命名格式，预设为\[{num}\]{actor} - {title}。预设以下几个占位符
    * {num} ： 番号
    * {actor} ： 演员
    * {title} ： 标题
    * {maker} ： 制作商
    * {year} ： 年份
7. 选择是否建立同名目录。如果是，则会创建一个以第6部设置的重命名格式命名的文件夹，用以存放电影文件、nfo以及封面图片。
8. 选择是否建立上级目录。如果是，则设置上级目录的命名格式，预设为#{actor}。然后创建文件夹，用以存放所有该复合该条件的电影。
9. 点击左上角“搜索信息”按钮，等待拉取信息。
10. 如果出现多个检索结果，可以在检索结果栏切换，选择最适合的一个。
11. 如果不想在页面上显示封面图，可以在显示封面图处选择。
12. 查看左侧电影信息，是否正确，不正确可以进行修改。
13. 点击右侧“重命名”按钮，会进行nfc文件创建、封面图下载、电影文件移动。
14. 可以单独下载封面图。
