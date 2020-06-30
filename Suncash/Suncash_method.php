<?php

class Suncash_Methods
{

}

$params = array(
    'method' => 'payment',
    'P01' => 'f3c29901fd045341e79a95e1fc0be1b9532c9c6fb4717e34baab4aef4187287f',
    'P02' => 'JHM COMMERCE',
    'P03' => 10.00,
    'P04' => '12',
    'P05' => 'http://localhost/original/car/holden-sv6/',
    'P06' => 'Headset|1|200.00~Charger|1|300.00',
);
$method='payment';

$curl = curl_init();
$url = "http://dev.mysuncash.com/api/checkout.php?";
//$req = curl_init($url);
////curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
//curl_setopt($req, CURLOPT_POST, true );
//curl_setopt($req, CURLOPT_POSTFIELDS,  http_build_query($params));
//curl_setopt($req, CURLOPT_CONNECTTIMEOUT ,0);
//$respCode = curl_getinfo($req, CURLINFO_HTTP_CODE);
//$resp = curl_exec($req);

$options = array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => http_build_query($params),
    CURLOPT_HTTPHEADER => array(
        "content-type: application/json",
        "accept: application/json",
        "cache-control: no-cache",
    ),
);
if (ENV == 'DEV') {
    $options[CURLOPT_SSL_VERIFYHOST] = false;
    $options[CURLOPT_SSL_VERIFYPEER] = false;
}
curl_setopt_array($curl, $options);
$response = curl_exec($curl);
curl_close($curl);

