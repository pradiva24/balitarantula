<?php
/**
 * Standalone QR encoder for short booking codes.
 * QR Version 3-L, byte mode, 29x29 modules, 15 ECC codewords.
 * No Composer, GD, Imagick, network or external dependencies.
 */
if (!function_exists('bta_qr_v3_l_matrix')) {
function bta_qr_v3_l_matrix(string $text): array {
    $bytes = array_values(unpack('C*', $text) ?: []);
    if (count($bytes) > 53) return [];
    $bits = [];
    $push = static function(int $value, int $length) use (&$bits): void {
        for ($i = $length - 1; $i >= 0; $i--) $bits[] = ($value >> $i) & 1;
    };
    $push(0b0100, 4); // byte mode
    $push(count($bytes), 8);
    foreach ($bytes as $b) $push($b, 8);
    $push(0, min(4, 440 - count($bits)));
    while (count($bits) % 8) $bits[] = 0;
    $data = [];
    for ($i=0; $i<count($bits); $i+=8) {
        $v=0; for($j=0;$j<8;$j++) $v=($v<<1)|$bits[$i+$j];
        $data[]=$v;
    }
    $pads = [0xEC,0x11]; $pi=0;
    // QR padding must always begin with 0xEC, independent of the current data length.
    while (count($data) < 55) $data[] = $pads[$pi++ % 2];
    $ecc = bta_qr_rs_remainder($data, 15);
    $codewords = array_merge($data, $ecc);
    $modules = array_fill(0, 29, array_fill(0, 29, null));
    $set = static function(array &$m, int $r, int $c, bool $v): void { if($r>=0&&$r<29&&$c>=0&&$c<29)$m[$r][$c]=$v; };
    $finder = static function(array &$m, int $r0, int $c0) use ($set): void {
        for($r=-1;$r<=7;$r++) for($c=-1;$c<=7;$c++) {
            $black = ($r>=0&&$r<=6&&$c>=0&&$c<=6 && ($r===0||$r===6||$c===0||$c===6||($r>=2&&$r<=4&&$c>=2&&$c<=4)));
            $set($m,$r0+$r,$c0+$c,$black);
        }
    };
    $finder($modules,0,0); $finder($modules,0,22); $finder($modules,22,0);
    for($c=8;$c<=20;$c++) $set($modules,6,$c,($c%2)===0);
    for($r=8;$r<=20;$r++) $set($modules,$r,6,($r%2)===0);
    for($r=19;$r<=25;$r++) for($c=19;$c<=25;$c++) {
        if ($modules[$r][$c]===null) $modules[$r][$c]=false;
    }
    for($r=20;$r<=24;$r++) for($c=20;$c<=24;$c++) $modules[$r][$c]=($r===20||$r===24||$c===20||$c===24||($r===22&&$c===22));
    $modules[21][8]=true;
    $format = bta_qr_format_bits(0, 0b01);
    for($i=0;$i<15;$i++) {
        $mod = (($format >> $i) & 1) === 1;
        if($i<6) $modules[$i][8]=$mod;
        elseif($i<8) $modules[$i+1][8]=$mod;
        else $modules[29-15+$i][8]=$mod;
        if($i<8) $modules[8][29-$i-1]=$mod;
        elseif($i<9) $modules[8][15-$i-1+1]=$mod;
        else $modules[8][15-$i-1]=$mod;
    }
    $dataBits=[]; foreach($codewords as $cw) for($i=7;$i>=0;$i--) $dataBits[]=(($cw>>$i)&1);
    $bit=0; $up=true;
    for($col=28;$col>0;$col-=2) {
        if($col===6) $col--;
        for($k=0;$k<29;$k++) {
            $row=$up ? 28-$k : $k;
            for($j=0;$j<2;$j++) {
                $cc=$col-$j;
                if($modules[$row][$cc]!==null) continue;
                $v=$bit<count($dataBits)?$dataBits[$bit++]:0;
                if((($row+$cc)%2)===0) $v ^= 1;
                $modules[$row][$cc]=($v===1);
            }
        }
        $up=!$up;
    }
    for($r=0;$r<29;$r++) for($c=0;$c<29;$c++) if($modules[$r][$c]===null) $modules[$r][$c]=false;
    return $modules;
}
function bta_qr_format_bits(int $mask, int $ec): int {
    $value = (($ec << 3) | $mask);
    $data = $value << 10;
    $g = 0x537;
    $bitLength = static function(int $v): int { $n=0; while($v>0){$n++; $v >>= 1;} return $n; };
    while ($bitLength($data) >= $bitLength($g)) {
        $data ^= $g << ($bitLength($data) - $bitLength($g));
    }
    return (($value << 10) | $data) ^ 0x5412;
}
function bta_qr_rs_remainder(array $data, int $degree): array {
    $exp=array_fill(0,512,0); $log=array_fill(0,256,0); $x=1;
    for($i=0;$i<255;$i++){ $exp[$i]=$x; $log[$x]=$i; $x<<=1; if($x&0x100)$x^=0x11d; }
    for($i=255;$i<512;$i++)$exp[$i]=$exp[$i-255];
    $gen=[1];
    for($i=0;$i<$degree;$i++){
        $next=array_fill(0,count($gen)+1,0);
        foreach($gen as $j=>$coef){$next[$j]^=$coef; if($coef)$next[$j+1]^=$exp[$log[$coef]+$i];}
        $gen=$next;
    }
    $res=array_fill(0,$degree,0);
    foreach($data as $b){$factor=$b^$res[0]; array_shift($res); $res[]=0; if($factor){for($j=0;$j<$degree;$j++) if($gen[$j+1]) $res[$j]^=$exp[$log[$factor]+$log[$gen[$j+1]]];}}
    return $res;
}
}
