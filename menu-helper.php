<?php
/* Frontend navigation renderer - v110. Reads the menu saved from Admin > Navigation Menu. */
function bt_menu_settings(){
  static $menus=null; if($menus!==null)return $menus;
  $file=__DIR__.'/menu-settings.json';
  $menus=[];
  if(is_file($file)){ $x=json_decode(@file_get_contents($file),true); if(is_array($x))$menus=$x; }
  if(!$menus){
    $menus=[
      ['label'=>'Home','url'=>'/','enabled'=>1],
      ['label'=>'Adventures','url'=>'/packages.php','enabled'=>1],
      ['label'=>'About Us','url'=>'/about.php','enabled'=>1],
      ['label'=>'Travel Guide','url'=>'/blog.php','enabled'=>1],
      ['label'=>'Gallery','url'=>'/#gallery','enabled'=>1],
      ['label'=>'Reviews','url'=>'/#reviews','enabled'=>1],
      ['label'=>'Book Now','url'=>'/booking.php','enabled'=>1,'button'=>1]
    ];
  }
  return $menus;
}
function bt_menu_url($url){
  $url=trim((string)$url); if($url==='') return '#';
  if(preg_match('~^(https?:)?//|^mailto:|^tel:|^#|^javascript:~i',$url)) return $url;
  $base=defined('BASE_URL')?rtrim(BASE_URL,'/') : '';
  if($url[0]==='/') return $base.$url;
  return $base.'/'.$url;
}
function bt_render_navigation($id='mainNav',$class=''){
  $menus=bt_menu_settings();
  echo '<style>
#mainNav .nav-ticket{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:9px 14px;margin-left:2px;border:1px solid #1f5f43;border-radius:8px;background:#fff;color:#1f5f43;text-decoration:none;font-weight:800;line-height:1.2;white-space:nowrap}
#mainNav .nav-ticket:hover{background:#edf7f1;color:#174a35}
#mainNav .nav-ticket .ticket-icon{width:18px;height:18px;display:block;object-fit:contain;flex:0 0 18px}
@media(max-width:900px){
  #mainNav{background:#fff !important;background-color:#fff !important;}
  #mainNav .nav-ticket{display:flex;width:100%;margin:6px 0 0;padding:12px 11px;justify-content:center;align-items:center;text-align:center;border-radius:8px;gap:7px}
  #mainNav .nav-ticket .ticket-icon{width:19px;height:19px;flex-basis:19px}
}
</style>';

  // The Check Ticket item is now editable from Admin > Navigation Menu.
  // Legacy menu JSON without a ticket flag keeps the previous automatic fallback.
  $hasExplicitTicket=false;
  foreach($menus as $m){
    $label=(string)($m['label']??'');
    if(!empty($m['ticket']) || preg_match('/check\s*ticket|view\s*(my\s*)?ticket|my\s*booking/i',$label)){
      $hasExplicitTicket=true;
      break;
    }
  }

  echo '<nav id="'.htmlspecialchars($id,ENT_QUOTES,'UTF-8').'"'.($class?' class="'.htmlspecialchars($class,ENT_QUOTES,'UTF-8').'"':'').'>';

  $ticketRendered=false;

  foreach($menus as $m){
    if(empty($m['enabled'])) continue;
    $label=trim((string)($m['label']??'')); if($label==='') continue;

    $isTicket=!empty($m['ticket']) || preg_match('/check\s*ticket|view\s*(my\s*)?ticket|my\s*booking/i',$label);

    if($isTicket){
      $ticketUrl=bt_menu_url($m['url']??'/my-booking');
      $ticketIcon='data:image/png;base64,' . 'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAACXBIWXMAAA7DAAAOwwHHb6hkAAAAGXRFWHRTb2Z0d2FyZQB3d3cuaW5rc2NhcGUub3Jnm+48GgAAA8hJREFUWIXll0toXGUUx3/nu5OnmMediDZqiOCDUpAWO/emjZWkWDVzx1jbTrCLJupGBBXsIqU7BbFF3VUR1FUxbUlqhJhJFBRtUfIY04UWCqaCj6S1JplYUxubydzjIonmnTjTbPS/PJz/g/N93+Vc+L/DWlP1aNSy77hhS/5dZaXj/QODi7XIWvgGvYr14Dcoug+kFECU10Y6eg+sWYBC7/5iw7W9gmkAdYCfEDkqMtlEyjykIm8ks1k39mHvyGxeICPXlzDBeGi7L1IvOrEbxIB+pKKvjOaVd9DSkgIoqa0c1FTycFZSdwHvzpZIawL/jJgG4BagD+QdYwWOD7d9NbYYx/bcD4CCRKxnR1oBinZuLDLJnDqgHrQSGBBoMpb13lBb1/mV+HY4VIfIsYAlt/7a1nNppr78EUSjVvDqD9W+SL0k2QMK0C7ojpFY/DOmC6tBruW3/+lb48mUvwt4e6a+6ATsR0MbxJd9Ck8CN4F0AUet/LxjQy1fXFmt6QLdsHtCxb95NBavXhCgqHbzJuNbz6G6HSgHfkH0lKo5LeiQUbJUKFfoTzcAqIvKfqNaPdwZPwUgdo1bgMUJVGvSF04LHcbKeiIgRg+rskmNbBvNLeuaeTrzEQy7j6locyLWm5ORbTRqFV/9catAc8qfOITtORfssPPiisSaO3NKws7mjMxnwY44+20vNBgA1iEMrMjoPH9tGL6+XgFQfgYpNavtL6mtLA1G3IPXLcA0DDABvB+MOGfsiLtnqUY/laxU1VczNbTDoUeCEecM0ARMGNBqUX1GlX5UW4Ke05CpyZLmEfcpRDp8OKvo0whVcxqCEfeQ7Tm/FT94X+F8cjBc4dpe6Jt0zYt2biyyPeey7YVenl2fcwfEBI4AhZIXcOcLjHR09yRi8XvTDWCS2S5QkDT+kSUDqO+vyYIyJa6Las99BZp6HrjCJN3zG+2Is8X2nLNp+0/4XcAfWX7ghdn1gO2FtiLmHlRrFKIqPDva2fP7QgVuAzakG2D0077LwbB7QEXftMPu3YL/sQrfBUA+RxXgW0F3J9rjremarISRjp637Ih7SfEPgjwOU/tANlCXiPW2LEc2k6kvU5bVmGmIRHvPSeCk7TlRoHnVO+HwJ30XgdczDTAfBrg4fb7LoryqKjcYrljwPNOGcDvoBaOirQiNxZ6zjWh0yR+Vsfzxh1X80xkbR6NWUTj0AEqjIq1SUlt5o59KHge8jMX/BURoN3n5e//+OEyt2qn1iiw6BVWyDFqmIt9nZIymwDo3Eus+l4nOfwd/ASLWVVDceCh6AAAAAElFTkSuQmCC';
      echo '<a class="nav-ticket" href="'.htmlspecialchars($ticketUrl,ENT_QUOTES,'UTF-8').'" aria-label="'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'"><img class="ticket-icon" src="'.htmlspecialchars($ticketIcon,ENT_QUOTES,'UTF-8').'" alt="" aria-hidden="true"> <span>'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</span></a>';
      $ticketRendered=true;
      continue;
    }

    $url=bt_menu_url($m['url']??'#');
    $isButton=!empty($m['button']) || preg_match('/book|reserve|booking/i',$label);
    $cls=$isButton?'nav-book':'';

    // Backward-compatible fallback for old menu-settings.json.
    if(!$hasExplicitTicket && !$ticketRendered && $isButton){
      $ticketUrl=bt_menu_url('/my-booking');
      $ticketIcon='data:image/png;base64,' . 'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAACXBIWXMAAA7DAAAOwwHHb6hkAAAAGXRFWHRTb2Z0d2FyZQB3d3cuaW5rc2NhcGUub3Jnm+48GgAAA8hJREFUWIXll0toXGUUx3/nu5OnmMediDZqiOCDUpAWO/emjZWkWDVzx1jbTrCLJupGBBXsIqU7BbFF3VUR1FUxbUlqhJhJFBRtUfIY04UWCqaCj6S1JplYUxubydzjIonmnTjTbPS/PJz/g/N93+Vc+L/DWlP1aNSy77hhS/5dZaXj/QODi7XIWvgGvYr14Dcoug+kFECU10Y6eg+sWYBC7/5iw7W9gmkAdYCfEDkqMtlEyjykIm8ks1k39mHvyGxeICPXlzDBeGi7L1IvOrEbxIB+pKKvjOaVd9DSkgIoqa0c1FTycFZSdwHvzpZIawL/jJgG4BagD+QdYwWOD7d9NbYYx/bcD4CCRKxnR1oBinZuLDLJnDqgHrQSGBBoMpb13lBb1/mV+HY4VIfIsYAlt/7a1nNppr78EUSjVvDqD9W+SL0k2QMK0C7ojpFY/DOmC6tBruW3/+lb48mUvwt4e6a+6ATsR0MbxJd9Ck8CN4F0AUet/LxjQy1fXFmt6QLdsHtCxb95NBavXhCgqHbzJuNbz6G6HSgHfkH0lKo5LeiQUbJUKFfoTzcAqIvKfqNaPdwZPwUgdo1bgMUJVGvSF04LHcbKeiIgRg+rskmNbBvNLeuaeTrzEQy7j6locyLWm5ORbTRqFV/9catAc8qfOITtORfssPPiisSaO3NKws7mjMxnwY44+20vNBgA1iEMrMjoPH9tGL6+XgFQfgYpNavtL6mtLA1G3IPXLcA0DDABvB+MOGfsiLtnqUY/laxU1VczNbTDoUeCEecM0ARMGNBqUX1GlX5UW4Ke05CpyZLmEfcpRDp8OKvo0whVcxqCEfeQ7Tm/FT94X+F8cjBc4dpe6Jt0zYt2biyyPeey7YVenl2fcwfEBI4AhZIXcOcLjHR09yRi8XvTDWCS2S5QkDT+kSUDqO+vyYIyJa6Las99BZp6HrjCJN3zG+2Is8X2nLNp+0/4XcAfWX7ghdn1gO2FtiLmHlRrFKIqPDva2fP7QgVuAzakG2D0077LwbB7QEXftMPu3YL/sQrfBUA+RxXgW0F3J9rjremarISRjp637Ih7SfEPgjwOU/tANlCXiPW2LEc2k6kvU5bVmGmIRHvPSeCk7TlRoHnVO+HwJ30XgdczDTAfBrg4fb7LoryqKjcYrljwPNOGcDvoBaOirQiNxZ6zjWh0yR+Vsfzxh1X80xkbR6NWUTj0AEqjIq1SUlt5o59KHge8jMX/BURoN3n5e//+OEyt2qn1iiw6BVWyDFqmIt9nZIymwDo3Eus+l4nOfwd/ASLWVVDceCh6AAAAAElFTkSuQmCC';
      echo '<a class="nav-ticket" href="'.htmlspecialchars($ticketUrl,ENT_QUOTES,'UTF-8').'" aria-label="Check Ticket"><img class="ticket-icon" src="'.htmlspecialchars($ticketIcon,ENT_QUOTES,'UTF-8').'" alt="" aria-hidden="true"> <span>Check Ticket</span></a>';
      $ticketRendered=true;
    }

    echo '<a'.($cls?' class="'.$cls.'"':'').' href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</a>';
  }

  if(!$hasExplicitTicket && !$ticketRendered){
    $ticketUrl=bt_menu_url('/my-booking');
    $ticketIcon='data:image/png;base64,' . 'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAACXBIWXMAAA7DAAAOwwHHb6hkAAAAGXRFWHRTb2Z0d2FyZQB3d3cuaW5rc2NhcGUub3Jnm+48GgAAA8hJREFUWIXll0toXGUUx3/nu5OnmMediDZqiOCDUpAWO/emjZWkWDVzx1jbTrCLJupGBBXsIqU7BbFF3VUR1FUxbUlqhJhJFBRtUfIY04UWCqaCj6S1JplYUxubydzjIonmnTjTbPS/PJz/g/N93+Vc+L/DWlP1aNSy77hhS/5dZaXj/QODi7XIWvgGvYr14Dcoug+kFECU10Y6eg+sWYBC7/5iw7W9gmkAdYCfEDkqMtlEyjykIm8ks1k39mHvyGxeICPXlzDBeGi7L1IvOrEbxIB+pKKvjOaVd9DSkgIoqa0c1FTycFZSdwHvzpZIawL/jJgG4BagD+QdYwWOD7d9NbYYx/bcD4CCRKxnR1oBinZuLDLJnDqgHrQSGBBoMpb13lBb1/mV+HY4VIfIsYAlt/7a1nNppr78EUSjVvDqD9W+SL0k2QMK0C7ojpFY/DOmC6tBruW3/+lb48mUvwt4e6a+6ATsR0MbxJd9Ck8CN4F0AUet/LxjQy1fXFmt6QLdsHtCxb95NBavXhCgqHbzJuNbz6G6HSgHfkH0lKo5LeiQUbJUKFfoTzcAqIvKfqNaPdwZPwUgdo1bgMUJVGvSF04LHcbKeiIgRg+rskmNbBvNLeuaeTrzEQy7j6locyLWm5ORbTRqFV/9catAc8qfOITtORfssPPiisSaO3NKws7mjMxnwY44+20vNBgA1iEMrMjoPH9tGL6+XgFQfgYpNavtL6mtLA1G3IPXLcA0DDABvB+MOGfsiLtnqUY/laxU1VczNbTDoUeCEecM0ARMGNBqUX1GlX5UW4Ke05CpyZLmEfcpRDp8OKvo0whVcxqCEfeQ7Tm/FT94X+F8cjBc4dpe6Jt0zYt2biyyPeey7YVenl2fcwfEBI4AhZIXcOcLjHR09yRi8XvTDWCS2S5QkDT+kSUDqO+vyYIyJa6Las99BZp6HrjCJN3zG+2Is8X2nLNp+0/4XcAfWX7ghdn1gO2FtiLmHlRrFKIqPDva2fP7QgVuAzakG2D0077LwbB7QEXftMPu3YL/sQrfBUA+RxXgW0F3J9rjremarISRjp637Ih7SfEPgjwOU/tANlCXiPW2LEc2k6kvU5bVmGmIRHvPSeCk7TlRoHnVO+HwJ30XgdczDTAfBrg4fb7LoryqKjcYrljwPNOGcDvoBaOirQiNxZ6zjWh0yR+Vsfzxh1X80xkbR6NWUTj0AEqjIq1SUlt5o59KHge8jMX/BURoN3n5e//+OEyt2qn1iiw6BVWyDFqmIt9nZIymwDo3Eus+l4nOfwd/ASLWVVDceCh6AAAAAElFTkSuQmCC';
    echo '<a class="nav-ticket" href="'.htmlspecialchars($ticketUrl,ENT_QUOTES,'UTF-8').'" aria-label="Check Ticket"><img class="ticket-icon" src="'.htmlspecialchars($ticketIcon,ENT_QUOTES,'UTF-8').'" alt="" aria-hidden="true"> <span>Check Ticket</span></a>';
  }

  echo '</nav>';
}

