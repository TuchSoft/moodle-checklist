<?php
$x = [];
$string = "2016 e bho onwards Frédéric Massart - FMCorz.net";
$string = '23:12  ⚠  Expected "#FFF" to be "#fff"';



//preg_match("/^(?<line>\d+):(?<col>\d+)\s+\u26A0+\s+(?<msg>\S.+?)\s{2}(?<code>\S.+?)$/u", $string, $x);
var_dump($x);

echo mb_ord("⚠")."\n";
echo mb_ord("✖");

