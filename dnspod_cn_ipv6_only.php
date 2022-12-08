#!/usr/bin/php -d open_basedir=/usr/syno/bin/ddns
<?php

$account = '';  // dnspod ID
$pwd = '';      // dnspod token
$hostname = ''; // 完整域名记录，例nas.airmole.cn

// 请求B站接口获取IPv6地址
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://api.bilibili.com/x/web-interface/zone');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
$ipinfo = curl_exec($ch);
curl_close($ch);
$ipinfo = json_decode($ipinfo, true);
$ip = $ipinfo['data']['addr'];

$hostname = explode('.', $hostname);
$arrayCount = count($hostname);
if ($arrayCount > 2) {
    $subDomain = implode('.', array_slice($hostname, 0, $arrayCount-2));
    $domain = implode('.', array_slice($hostname, $arrayCount-2, 2));
} else {
    $subDomain = '@';
    $domain = implode('.', $hostname);
}

$url = 'https://dnsapi.cn/Domain.List';
$post = array(
	'login_token'=>$account.','.$pwd,
    'format'=>'json'
);
$req = curl_init();
$options = array(
  CURLOPT_URL=>$url,
  CURLOPT_HEADER=>0,
  CURLOPT_VERBOSE=>0,
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_USERAGENT=>'Mozilla/4.0 (compatible;)',
  CURLOPT_POST=>true,
  CURLOPT_POSTFIELDS=>http_build_query($post),
);
curl_setopt_array($req, $options);
$res = curl_exec($req);
$json = json_decode($res, true);

if (1 != $json['status']['code']) {
    if (-1 == $json['status']['code']) {
        echo 'badauth';
    } else if (9 == $json['status']['code']) {
        echo 'nohost';
    } else {
        echo 'Get Domain List failed['.$json['status']['code'].']';
    }
    //print_r($json['status']['code']);
    curl_close($req);
    exit();
}

$domain_total = $json['info']['domain_total'];

$domainID = -1;
for ($i = 0; $i < $domain_total; $i++) {
    if ($json['domains'][$i]['name'] === $domain) {
        $domainID = $json['domains'][$i]['id'];
        break;
    }
}

if ($domainID === -1) {
    echo 'nohost';
    exit();
}

$url = 'https://dnsapi.cn/Record.List';
$post = array(
	'login_token'=>$account.','.$pwd,
    'domain_id'=>$domainID,
    'format'=>'json'
);
$options = array(
  CURLOPT_URL=>$url,
  CURLOPT_HEADER=>0,
  CURLOPT_VERBOSE=>0,
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_USERAGENT=>'Mozilla/4.0 (compatible;)',
  CURLOPT_POST=>true,
  CURLOPT_POSTFIELDS=>http_build_query($post),
);
curl_setopt_array($req, $options);
$res = curl_exec($req);
$json = json_decode($res, true);

if (1 != $json['status']['code']) {
    echo 'Get Record List failed';
    curl_close($req);
    exit();
}

$recordID = -1;
$record_total = $json['info']['record_total'];
for ($i = 0; $i < $record_total; $i++) {
    if (($json['records'][$i]['name'] === $subDomain) and ($json['records'][$i]['type'] === 'AAAA')) {
        $recordID = $json['records'][$i]['id'];
        break;
    }
}

if ($recordID === -1) {
    echo 'nohost';
    curl_close($req);
    exit();
}

$url = 'https://dnsapi.cn/Record.Modify';
$post = array(
	'login_token'=>$account.','.$pwd,
    'domain_id'=>$domainID,
    'record_id'=>$recordID,
    'sub_domain'=>$subDomain,
    'value'=>$ip,
    'record_type'=>'AAAA',
    'record_line'=>'默认',
    'format'=>'json'
);
$options = array(
  CURLOPT_URL=>$url,
  CURLOPT_HEADER=>0,
  CURLOPT_VERBOSE=>0,
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_USERAGENT=>'Mozilla/4.0 (compatible;)',
  CURLOPT_POST=>true,
  CURLOPT_POSTFIELDS=>http_build_query($post),
);
curl_setopt_array($req, $options);
$res = curl_exec($req);
curl_close($req);
$json = json_decode($res, true);

if (1 != $json['status']['code']) {
    echo 'Update Record failed';
    exit();
}

echo 'good';

