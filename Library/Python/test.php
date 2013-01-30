<?php
$k = $argv[1];
if (!empty($k))
{
    $k = trim($k);
    //$priceText = passthru('python ./360buy.py '.$k);
    $a = array();
    exec('python ./360buy.py ' . $k, $a);
    echo $a[0];
    $price = (int)$a[0] / 100;
    echo $price;
}
