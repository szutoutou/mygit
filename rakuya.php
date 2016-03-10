<?php
echo 1;
set_time_limit(0);
$strPage = '/search/rent/eNozMAACE1MD8oAhbhkLVwAUrhE5/0/0/0';
$j = 0;
$curlUrl = array();
$sql = '';
$page = '';
$arrayEhid = array();


for ($i = 4380; $i <= 5711; $i++) {
    if(!$strPage){
        die();
    }
    if ($j == 10) {
        $arrayItem = Curl_http($curlUrl, 0);
        foreach ($arrayItem['return'] as $key => $strItem) {
            $patternName = "/obj-master-name\">(.*)<\/span>/";
            preg_match($patternName, $strItem, $arrayName);
            $patternCrop = "/corp\">(.*)<\/li>\s\s\s\s\s\s<li class=\"branch/";
            preg_match($patternCrop, $strItem, $arrayCrop);
            $patternBranch = "/branch\">(.*)<\/li>\s\s\s\s\s\s/";
            preg_match($patternBranch, $strItem, $arrayBranch);
            $patternNum = "/出租<[\s|\S].*\">(.[0-9]*)</";
            preg_match($patternNum, $strItem, $arrayNum);
            $patternTel = "/encode_tel2=\"(.*)\"\sagent_euid/";
            preg_match($patternTel, $strItem, $arrayTel);
            $strName = end($arrayName);
            $strCrop = end($arrayCrop);
            $strBranch = end($arrayBranch);
            $strNum = end($arrayNum);
            $strTel = base64_decode(end($arrayTel));
            if (!$strTel) {
                $patternTel = "/encode_tel1=\"(.*)\"\sencode_tel2/";
                preg_match($patternTel, $strItem, $arrayTel);
                $strTel = base64_decode(end($arrayTel));
            }
            $sql = $sql . "insert into rakuya (name,crop,branch,num,tel,ehid) values('" . $strName . "','" . $strCrop . "','" . $strBranch . "','" . $strNum . "','" . $strTel . "','" . $arrayEhid[$key] . "');\r\n";
        }
        echo $page;
        echo $sql;
        $open = fopen("page.txt", "a");
        fwrite($open, $page);
        fclose($open);
        $open = fopen("sql.txt", "a");
        fwrite($open, $sql);
        fclose($open);
        $sql = '';
        $page = '';
        $curlUrl = array();
        $j = 0;
        $arrayEhid=array();
    }
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, 'http://happyrent.rakuya.com.tw' . $strPage);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $strList = curl_exec($curl);

    $patternList = "/<a href=\"(.[0-9a-zA-Z_\-.\/]*)\">下一頁<\/a><\/DIV><\/TD>/";
    preg_match($patternList, $strList, $arrayList);
    curl_close($curl);
    $patternUrl = "/{\"ehid\":\"(.[0-9a-zA-Z]*)\",\"lat/";
    preg_match_all($patternUrl, $strList, $arrayUrl);
    $arrayUrl = end($arrayUrl);

    foreach ($arrayUrl as $key => $strEhid) {
        $curlUrl[] = 'http://items.rakuya.com.tw/rent_item/info?ehid=' . $strEhid;
        $arrayEhid[] = $strEhid;
    }
    $page = $page . $i . $strPage . "\r\n";
    $flag=$strPage;
    $strPage = end($arrayList);
    $j++;

}

/*
curl 多线程抓取
*/
/**
 * curl 多线程
 *
 * @param array $array 并行网址
 * @param int $timeout 超时时间
 * @return array
 */
function Curl_http($array, $timeout)
{
    $res = array();
    $mh = curl_multi_init();//创建多个curl语柄
    $startime = getmicrotime();
    foreach ($array as $k => $url) {
        $conn[$k] = curl_init($url);

        curl_setopt($conn[$k], CURLOPT_TIMEOUT, $timeout);//设置超时时间
        curl_setopt($conn[$k], CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($conn[$k], CURLOPT_MAXREDIRS, 7);//HTTp定向级别
        curl_setopt($conn[$k], CURLOPT_HEADER, 0);//这里不要header，加块效率
        curl_setopt($conn[$k], CURLOPT_FOLLOWLOCATION, 1); // 302 redirect
        curl_setopt($conn[$k], CURLOPT_RETURNTRANSFER, 1);
        curl_multi_add_handle($mh, $conn[$k]);
    }
    //防止死循环耗死cpu 这段是根据网上的写法
    do {
        $mrc = curl_multi_exec($mh, $active);//当无数据，active=true
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);//当正在接受数据时
    while ($active and $mrc == CURLM_OK) {//当无数据时或请求暂停时，active=true
        if (curl_multi_select($mh) != -1) {
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
    }

    foreach ($array as $k => $url) {
        curl_error($conn[$k]);
        $res[$k] = curl_multi_getcontent($conn[$k]);//获得返回信息
        // $header[$k] = curl_getinfo($conn[$k]);//返回头信息
        curl_close($conn[$k]);//关闭语柄
        curl_multi_remove_handle($mh, $conn[$k]);   //释放资源
    }

    curl_multi_close($mh);
    $endtime = getmicrotime();
    $diff_time = $endtime - $startime;

    return array('diff_time' => $diff_time,
        'return' => $res,
        // 'header' => $header
    );

}

//计算当前时间
function getmicrotime()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}