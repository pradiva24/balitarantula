<?php
function coupon_validate(string $code, float $subtotal, int $packageId, ?string $date=null): array {
    $code=strtoupper(trim($code));
    if($code==='') return ['ok'=>false,'message'=>'Enter a promo code.','discount'=>0,'code'=>''];
    $date=$date ?: date('Y-m-d');
    $st=db()->prepare("SELECT * FROM coupons WHERE code=? AND status=1 LIMIT 1"); $st->execute([$code]); $c=$st->fetch();
    if(!$c) return ['ok'=>false,'message'=>'Promo code not found or inactive.','discount'=>0,'code'=>$code];
    if($c['start_date'] && $date<$c['start_date']) return ['ok'=>false,'message'=>'This promo is not active yet.','discount'=>0,'code'=>$code];
    if($c['end_date'] && $date>$c['end_date']) return ['ok'=>false,'message'=>'This promo has expired.','discount'=>0,'code'=>$code];
    if($c['usage_limit']!==null && (int)$c['used_count'] >= (int)$c['usage_limit']) return ['ok'=>false,'message'=>'This promo has reached its usage limit.','discount'=>0,'code'=>$code];
    if($c['package_id']!==null && (int)$c['package_id']!==$packageId) return ['ok'=>false,'message'=>'This promo is not valid for the selected package.','discount'=>0,'code'=>$code];
    if($subtotal < (float)$c['min_subtotal']) return ['ok'=>false,'message'=>'Minimum booking value for this promo is '.rupiah($c['min_subtotal']).'.','discount'=>0,'code'=>$code];
    $discount=$c['discount_type']==='percent' ? $subtotal*((float)$c['discount_value']/100) : (float)$c['discount_value'];
    if($c['max_discount']!==null) $discount=min($discount,(float)$c['max_discount']);
    $discount=max(0,min($discount,$subtotal));
    return ['ok'=>true,'message'=>'Promo applied: '.($c['discount_type']==='percent'?rtrim(rtrim((string)$c['discount_value'],'0'),'.').'%' : rupiah($c['discount_value'])),'discount'=>$discount,'code'=>$code,'coupon'=>$c];
}
