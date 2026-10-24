<?php
declare(strict_types=1);

/* Versioned public API. All write operations are moderation-only contributions. */
function mhApiReply(int $status, array $body, int $cache = 0, array $extra = []): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: ' . ($cache ? 'public, max-age=' . $cache . ', stale-while-revalidate=60' : 'no-store'));
    foreach ($extra as $key => $value) if (preg_match('/^[A-Za-z0-9-]+$/', (string)$key)) header($key . ': ' . (string)$value);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}
function mhApiOk($data, array $meta = [], int $status = 200, int $cache = 30): void {
    mhApiReply($status, ['success'=>true,'data'=>$data,'meta'=>array_merge(['api_version'=>'1','generated_at'=>date(DATE_ATOM)],$meta)], $cache);
}
function mhApiError(int $status, string $code, string $message, array $details = [], array $headers = []): void {
    $e=['code'=>$code,'message'=>$message]; if($details)$e['details']=$details;
    mhApiReply($status,['success'=>false,'data'=>null,'error'=>$e,'meta'=>['api_version'=>'1','generated_at'=>date(DATE_ATOM)]],0,$headers);
}
function mhApiText($v,int $max=120):string {
    if(!is_scalar($v))return ''; $v=trim((string)$v);
    return function_exists('mb_substr')?mb_substr($v,0,$max,'UTF-8'):substr($v,0,$max);
}
function mhApiPage(array $q):array {
    $p=$q['page']??'1';$n=$q['per_page']??'20';
    $p=is_scalar($p)&&preg_match('/^[0-9]{1,6}$/',(string)$p)?(int)$p:1;
    $n=is_scalar($n)&&preg_match('/^[0-9]{1,3}$/',(string)$n)?(int)$n:20;
    return ['page'=>max(1,min(10000,$p)),'per_page'=>max(1,min(100,$n))];
}
function mhApiValidateContribution(array $in):array {
    $errors=[];$fa=trim(strip_tags(mhApiText($in['name_fa']??'',121)));$en=trim(strip_tags(mhApiText($in['name_en']??'',121)));
    $desc=trim(strip_tags(mhApiText($in['description']??'',1001)));
    $len=static fn($s)=>function_exists('mb_strlen')?mb_strlen($s,'UTF-8'):strlen($s);
    if($fa==='')$errors['name_fa']='نام فارسی الزامی است.';elseif($len($fa)>120)$errors['name_fa']='حداکثر ۱۲۰ نویسه.';
    if($len($en)>120)$errors['name_en']='حداکثر ۱۲۰ نویسه.';if($len($desc)>1000)$errors['description']='حداکثر ۱۰۰۰ نویسه.';
    $raw=$in['urls']??($in['url']??[]);if(!is_array($raw))$raw=[$raw];if(count($raw)>10)$errors['urls']='حداکثر ۱۰ لینک.';
    $urls=[];foreach(array_slice($raw,0,10) as $u){if(!is_scalar($u))continue;$u=trim((string)$u);
        if($u===''||strlen($u)>1000||filter_var($u,FILTER_VALIDATE_URL)===false)continue;
        if(!in_array(strtolower((string)parse_url($u,PHP_URL_SCHEME)),['http','https','ftp','rsync'],true))continue;
        if(!in_array($u,$urls,true))$urls[]=$u;
    }
    if(!$urls)$errors['urls']='حداقل یک URL معتبر با پروتکل http، https، ftp یا rsync لازم است.';
    $pr=$in['protocols']??[];if(!is_array($pr))$pr=[$pr];$custom=$in['custom_protocols']??[];if(!is_array($custom))$custom=[$custom];
    $protocols=normalizeProtocols($pr,$custom);if(!$protocols)$errors['protocols']='حداقل یک پروتکل معتبر لازم است.';elseif(count($protocols)>5)$errors['protocols']='حداکثر ۵ پروتکل.';
    $phone=mhApiText($in['phone']??'',30);$phone=strtr($phone,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    $phone=preg_replace('/[^0-9+]/','',$phone)??'';if(strlen($phone)<10||strlen($phone)>15||!preg_match('/^\+?[0-9]+$/',$phone))$errors['phone']='شماره تماس معتبر لازم است.';
    $email=mhApiText($in['email']??'',191);if(strlen($email)>190||($email!==''&&filter_var($email,FILTER_VALIDATE_EMAIL)===false))$errors['email']='ایمیل معتبر یا خالی وارد کنید.';
    return ['valid'=>!$errors,'errors'=>$errors,'data'=>['name_fa'=>$fa,'name_en'=>$en,'description'=>$desc,'urls'=>$urls,'protocols'=>$protocols,'phone'=>$phone,'email'=>$email,'website'=>trim(strip_tags(mhApiText($in['website']??'',300)))]];
}
function mhApiQuota(PDO $pdo):array {
    $pdo->exec("CREATE TABLE IF NOT EXISTS api_rate_limits (ip_hash CHAR(64) NOT NULL, window_start DATETIME NOT NULL, request_count INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY(ip_hash,window_start), KEY idx_api_rate_window(window_start)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ip=trim((string)($_SERVER['REMOTE_ADDR']??'unknown'));if($ip===''||strlen($ip)>64)$ip='unknown';
    $secret=(defined('DB_PASS')&&DB_PASS!=='')?DB_PASS:SITE_URL;$hash=hash_hmac('sha256',$ip,$secret);$now=time();$startTs=intdiv($now,3600)*3600;$start=gmdate('Y-m-d H:i:s',$startTs);
    $s=$pdo->prepare("INSERT INTO api_rate_limits(ip_hash,window_start,request_count) VALUES(?,?,1) ON DUPLICATE KEY UPDATE request_count=request_count+1");$s->execute([$hash,$start]);
    $s=$pdo->prepare("SELECT request_count FROM api_rate_limits WHERE ip_hash=? AND window_start=?");$s->execute([$hash,$start]);$used=(int)$s->fetchColumn();
    try{if(random_int(1,20)===1)$pdo->exec("DELETE FROM api_rate_limits WHERE window_start < UTC_TIMESTAMP() - INTERVAL 2 DAY");}catch(Throwable $ignored){}
    return ['ok'=>$used<=8,'limit'=>8,'remaining'=>max(0,8-$used),'reset'=>$startTs+3600,'retry'=>max(1,$startTs+3600-$now)];
}
function mhApiMirror(PDO $r,array $cats=[],array $protos=[]):array {
    $id=(int)$r['id'];
    return ['id'=>$id,'name_fa'=>(string)$r['name_fa'],'name_en'=>(string)($r['name_en']??''),'slug'=>(string)$r['slug'],'url'=>(string)$r['url'],'description'=>(string)($r['description']??''),
    'protocols'=>array_map(static fn($p)=>['protocol'=>$p['protocol'],'label'=>getProtocolLabel($p['protocol'],$p['custom_label']??null)],$protos[$id]??[]),
    'categories'=>array_map(static fn($c)=>['id'=>(int)$c['id'],'name'=>$c['name'],'slug'=>$c['slug'],'type'=>$c['type']??'software'],$cats[$id]??[]),
    'status'=>['http'=>$r['http_status']??'unknown','response_ms'=>isset($r['last_response_ms'])?(int)$r['last_response_ms']:null,'http_code'=>isset($r['last_http_code'])?(int)$r['last_http_code']:null,'uptime_percent'=>isset($r['uptime_percent'])?(float)$r['uptime_percent']:null,'last_checked_at'=>$r['last_http_checked_at']??null],
    'repository_health'=>['profile'=>$r['repository_health_profile']??'http_only','status'=>$r['repository_health_status']??'not_configured','checked_at'=>$r['repository_health_checked_at']??null,'metadata_date'=>$r['repository_metadata_date']??null,'age_seconds'=>isset($r['repository_age_seconds'])?(int)$r['repository_age_seconds']:null,'message'=>$r['repository_health_message']??null]];
}
function mhApiListMirrors(PDO $pdo,array $q,bool $providers=false):array {
    $pg=mhApiPage($q);$where=["m.status='active'"];$params=[];$term=mhApiText($q['q']??'',120);
    if($term!==''){$like='%'.normalizeSearchQuery($term,120).'%';$where[]="(m.name_fa LIKE ? OR m.name_en LIKE ? OR m.description LIKE ? OR m.url LIKE ?)";array_push($params,$like,$like,$like,$like);}
    $cat=mhApiText($q['category']??'',120);
    if($cat!==''){if(ctype_digit($cat)&&(int)$cat>0){$where[]="EXISTS(SELECT 1 FROM mirror_categories mcx WHERE mcx.mirror_id=m.id AND mcx.category_id=?)";$params[]=(int)$cat;}
    elseif(preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,119}$/',$cat)){$where[]="EXISTS(SELECT 1 FROM mirror_categories mcx JOIN categories ccx ON ccx.id=mcx.category_id WHERE mcx.mirror_id=m.id AND ccx.slug=?)";$params[]=$cat;}
    else throw new InvalidArgumentException('Invalid category.');}
    elseif(isset($q['category_id'])&&$q['category_id']!==''){$n=filter_var($q['category_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($n===false)throw new InvalidArgumentException('Invalid category_id.');$where[]="EXISTS(SELECT 1 FROM mirror_categories mcx WHERE mcx.mirror_id=m.id AND mcx.category_id=?)";$params[]=(int)$n;}
    $proto=mhApiText($q['protocol']??'',32);
    if($proto!==''){
      if(!array_key_exists($proto,getProtocolOptions()))throw new InvalidArgumentException('Invalid protocol.');
      if(hasTable($pdo,'mirror_protocols')){
        if($proto==='custom')$where[]="(EXISTS(SELECT 1 FROM mirror_protocols px WHERE px.mirror_id=m.id AND px.protocol='custom') OR (NOT EXISTS(SELECT 1 FROM mirror_protocols px0 WHERE px0.mirror_id=m.id) AND m.protocol IN('custom','other')))";
        else{$where[]="(EXISTS(SELECT 1 FROM mirror_protocols px WHERE px.mirror_id=m.id AND px.protocol=?) OR (NOT EXISTS(SELECT 1 FROM mirror_protocols px0 WHERE px0.mirror_id=m.id) AND m.protocol=?))";$params[]=$proto;$params[]=$proto;}
      }elseif($proto==='custom')$where[]="m.protocol IN('custom','other')";
      else{$where[]='m.protocol=?';$params[]=$proto;}
    }
    $hs=mhApiText($q['http_status']??'',16);if($hs!==''){if(!in_array($hs,['up','slow','down','unknown'],true))throw new InvalidArgumentException('Invalid http_status.');$where[]="COALESCE((SELECT msx.status FROM mirror_status msx WHERE msx.mirror_id=m.id),'unknown')=?";$params[]=$hs;}
    $health=mhApiText($q['repository_health']??'',24);if($health!==''){if(!in_array($health,['healthy','stale','invalid','error','unknown','not_configured'],true))throw new InvalidArgumentException('Invalid repository_health.');$where[]="COALESCE((SELECT rhx.status FROM mirror_repository_health rhx WHERE rhx.mirror_id=m.id),'not_configured')=?";$params[]=$health;}
    if($providers)$where[]="EXISTS(SELECT 1 FROM mirror_categories pmc JOIN categories pc ON pc.id=pmc.category_id WHERE pmc.mirror_id=m.id AND pc.type='provider')";
    $w=implode(' AND ',$where);$s=$pdo->prepare("SELECT COUNT(*) FROM mirrors m WHERE ".$w);$s->execute($params);$total=(int)$s->fetchColumn();$limit=$pg['per_page'];$offset=($pg['page']-1)*$limit;
    $sql="SELECT m.id,m.name_fa,m.name_en,m.slug,m.url,m.protocol,m.description,COALESCE(ms.status,'unknown') http_status,ms.last_checked_at last_http_checked_at,ms.last_response_ms,ms.last_http_code,ms.uptime_percent,COALESCE(rh.profile,'http_only') repository_health_profile,COALESCE(rh.status,'not_configured') repository_health_status,rh.last_checked_at repository_health_checked_at,rh.metadata_date repository_metadata_date,rh.age_seconds repository_age_seconds,rh.message repository_health_message FROM mirrors m LEFT JOIN mirror_status ms ON ms.mirror_id=m.id LEFT JOIN mirror_repository_health rh ON rh.mirror_id=m.id WHERE ".$w." ORDER BY m.name_fa,m.id LIMIT ".(int)$limit." OFFSET ".(int)$offset;
    $s=$pdo->prepare($sql);$s->execute($params);$rows=$s->fetchAll();$ids=array_map(static fn($r)=>(int)$r['id'],$rows);$cm=getMirrorCategoryTagsMap($pdo,$ids);$pm=getMirrorProtocolsMap($pdo,$ids);
    return ['items'=>array_map(static fn($r)=>mhApiMirror($r,$cm,$pm),$rows),'meta'=>['page'=>$pg['page'],'per_page'=>$limit,'total'=>$total,'pages'=>$total?(int)ceil($total/$limit):0]];
}
function mhApiCategories(PDO $pdo,array $q):array {
    $pg=mhApiPage($q);$where=[];$params=[];$type=mhApiText($q['type']??'',16);
    if($type!==''){if(!in_array($type,['software','provider'],true))throw new InvalidArgumentException('Invalid type.');$where[]='c.type=?';$params[]=$type;}
    if(isset($q['parent_id'])&&$q['parent_id']!==''){$n=filter_var($q['parent_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);if($n===false)throw new InvalidArgumentException('Invalid parent_id.');if((int)$n===0)$where[]='c.parent_id IS NULL';else{$where[]='c.parent_id=?';$params[]=(int)$n;}}
    $w=$where?' WHERE '.implode(' AND ',$where):'';$s=$pdo->prepare("SELECT COUNT(*) FROM categories c".$w);$s->execute($params);$total=(int)$s->fetchColumn();$limit=$pg['per_page'];$offset=($pg['page']-1)*$limit;
    $s=$pdo->prepare("SELECT c.id,c.name,c.slug,c.description,c.type,c.parent_id,p.name parent_name,p.slug parent_slug,(SELECT COUNT(DISTINCT mc.mirror_id) FROM mirror_categories mc JOIN mirrors m ON m.id=mc.mirror_id WHERE mc.category_id=c.id AND m.status='active') active_mirrors FROM categories c LEFT JOIN categories p ON p.id=c.parent_id".$w." ORDER BY CASE WHEN c.parent_id IS NULL THEN 0 ELSE 1 END,c.sort_order,c.name LIMIT ".(int)$limit." OFFSET ".(int)$offset);$s->execute($params);
    $items=array_map(static fn($r)=>['id'=>(int)$r['id'],'name'=>$r['name'],'slug'=>$r['slug'],'description'=>$r['description']??'','type'=>$r['type'],'parent_id'=>$r['parent_id']!==null?(int)$r['parent_id']:null,'parent_name'=>$r['parent_name']??null,'parent_slug'=>$r['parent_slug']??null,'active_mirrors'=>(int)$r['active_mirrors']],$s->fetchAll());
    return ['items'=>$items,'meta'=>['page'=>$pg['page'],'per_page'=>$limit,'total'=>$total,'pages'=>$total?(int)ceil($total/$limit):0]];
}
function mhApiContribution(PDO $pdo):void {
    if((int)($_SERVER['CONTENT_LENGTH']??0)>65536){mhApiError(413,'payload_too_large','بدنه حداکثر ۶۴ کیلوبایت باشد.');return;}
    if(strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''))[0]))!=='application/json'){mhApiError(415,'unsupported_media_type','Content-Type باید application/json باشد.');return;}
    try{$quota=mhApiQuota($pdo);}catch(Throwable $e){error_log('MirrorHub API v1: rate limiter unavailable.');mhApiError(503,'submission_unavailable','ثبت پیشنهاد موقتاً در دسترس نیست.');return;}
    header('X-RateLimit-Limit: '.$quota['limit']);header('X-RateLimit-Remaining: '.$quota['remaining']);header('X-RateLimit-Reset: '.$quota['reset']);
    if(!$quota['ok']){mhApiError(429,'rate_limit_exceeded','محدودیت ارسال؛ بعداً دوباره تلاش کنید.',[],['Retry-After'=>(string)$quota['retry']]);return;}
    $raw=file_get_contents('php://input',false,null,0,65537);if(!is_string($raw)||$raw===''){mhApiError(400,'invalid_json','بدنه JSON خالی است.');return;}if(strlen($raw)>65536){mhApiError(413,'payload_too_large','بدنه حداکثر ۶۴ کیلوبایت باشد.');return;}
    $in=json_decode($raw,true);if(!is_array($in)||json_last_error()!==JSON_ERROR_NONE){mhApiError(400,'invalid_json','ساختار JSON معتبر نیست.');return;}
    $v=mhApiValidateContribution($in);if(!$v['valid']){mhApiError(422,'validation_failed','برخی فیلدها معتبر نیستند.',$v['errors']);return;}
    $d=$v['data'];if($d['website']!==''){mhApiOk(['status'=>'pending_review'],[],202,0);return;}
    if(!hasTable($pdo,'requests')||!hasTable($pdo,'request_links')||!hasTable($pdo,'request_protocols')){mhApiError(503,'schema_upgrade_required','ساختار ثبت پیشنهاد نیاز به به‌روزرسانی دارد.');return;}
    try{$pdo->beginTransaction();$s=$pdo->prepare("INSERT INTO requests(name_fa,name_en,url,protocol,parent_category_id,category_id,category_name,description,status,email,phone) VALUES(?,?,?,?,NULL,NULL,NULL,?,'pending',?,?)");
        $s->execute([htmlspecialchars($d['name_fa'],ENT_QUOTES,'UTF-8'),htmlspecialchars($d['name_en'],ENT_QUOTES,'UTF-8'),$d['urls'][0],$d['protocols'][0]['protocol'],htmlspecialchars($d['description'],ENT_QUOTES,'UTF-8'),$d['email']!==''?$d['email']:null,$d['phone']]);
        $id=(int)$pdo->lastInsertId();if(!setRequestProtocols($pdo,$id,$d['protocols'])||!setRequestLinks($pdo,$id,$d['urls']))throw new RuntimeException('Request detail tables unavailable.');
        $pdo->commit();mhApiOk(['request_id'=>$id,'status'=>'pending_review'],[],201,0);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('MirrorHub API v1: contribution storage failed.');mhApiError(503,'submission_failed','ثبت پیشنهاد انجام نشد؛ بعداً دوباره تلاش کنید.');}
}
function mirrorhubApiV1(PDO $pdo):void {
    header('Access-Control-Allow-Origin: *');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');header('Access-Control-Allow-Headers: Accept, Content-Type');header('Access-Control-Max-Age: 600');header('X-API-Version: 1');
    try{header('X-Request-ID: '.bin2hex(random_bytes(8)));}catch(Throwable $e){header('X-Request-ID: unavailable');}
    $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));if($method==='OPTIONS'){http_response_code(204);header('Cache-Control: public, max-age=600');return;}
    $resource=mhApiText($_GET['resource']??'',64);if($resource==='')$resource='schema';
    if($resource==='contributions'){if($method!=='POST'){mhApiError(405,'method_not_allowed','برای ثبت پیشنهاد از POST استفاده کنید.',[],['Allow'=>'POST, OPTIONS']);return;}mhApiContribution($pdo);return;}
    if($method!=='GET'){mhApiError(405,'method_not_allowed','این مسیر فقط GET می‌پذیرد.',[],['Allow'=>'GET, OPTIONS']);return;}
    try{switch($resource){
    case 'schema':case 'openapi':$file=dirname(__DIR__).'/docs/openapi-v1.json';if(!is_file($file)){mhApiError(503,'schema_unavailable','فایل OpenAPI در دسترس نیست.');return;}header('Content-Type: application/vnd.oai.openapi+json;version=3.1');header('Cache-Control: public, max-age=300');readfile($file);return;
    case 'mirrors':$r=mhApiListMirrors($pdo,$_GET);mhApiOk($r['items'],$r['meta'],200,30);return;
    case 'mirror':$slug=mhApiText($_GET['slug']??'',120);if($slug===''){mhApiError(400,'missing_slug','نامک میرور الزامی است.');return;}
        $s=$pdo->prepare("SELECT m.id,m.name_fa,m.name_en,m.slug,m.url,m.protocol,m.description,COALESCE(ms.status,'unknown') http_status,ms.last_checked_at last_http_checked_at,ms.last_response_ms,ms.last_http_code,ms.uptime_percent,COALESCE(rh.profile,'http_only') repository_health_profile,COALESCE(rh.status,'not_configured') repository_health_status,rh.last_checked_at repository_health_checked_at,rh.metadata_date repository_metadata_date,rh.age_seconds repository_age_seconds,rh.message repository_health_message FROM mirrors m LEFT JOIN mirror_status ms ON ms.mirror_id=m.id LEFT JOIN mirror_repository_health rh ON rh.mirror_id=m.id WHERE m.slug=? AND m.status='active' LIMIT 1");
        $s->execute([$slug]);$row=$s->fetch();if(!$row){mhApiError(404,'mirror_not_found','میرور فعال پیدا نشد.');return;}$id=(int)$row['id'];mhApiOk(mhApiMirror($row,getMirrorCategoryTagsMap($pdo,[$id]),getMirrorProtocolsMap($pdo,[$id])),[],200,30);return;
    case 'categories':$r=mhApiCategories($pdo,$_GET);mhApiOk($r['items'],$r['meta'],200,60);return;
    case 'stats':$mon=monitorStatusSummary($pdo);$s=$pdo->query("SELECT (SELECT COUNT(*) FROM mirrors WHERE status='active') active_mirrors,(SELECT COUNT(*) FROM categories) categories")->fetch();
        mhApiOk(['active_mirrors'=>(int)$s['active_mirrors'],'categories'=>(int)$s['categories'],'monitored_mirrors'=>(int)($mon['mirrors']??0),'online'=>(int)($mon['up']??0),'slow'=>(int)($mon['slow']??0),'offline'=>(int)($mon['down']??0),'unknown'=>(int)($mon['unknown']??0),'average_response_ms'=>isset($mon['avg_response_ms'])?(int)$mon['avg_response_ms']:null,'uptime_percent_30d'=>isset($mon['uptime_percent'])?(float)$mon['uptime_percent']:null,'last_monitor_cycle_at'=>$mon['last_run_at']??null,'open_incidents'=>(int)($mon['open_incidents']??0)],[],200,60);return;
    case 'status':$mon=monitorStatusSummary($pdo);$r=mhApiListMirrors($pdo,$_GET,true);
        mhApiOk(['summary'=>['monitored_mirrors'=>(int)($mon['mirrors']??0),'online'=>(int)($mon['up']??0),'slow'=>(int)($mon['slow']??0),'offline'=>(int)($mon['down']??0),'unknown'=>(int)($mon['unknown']??0),'uptime_percent_30d'=>isset($mon['uptime_percent'])?(float)$mon['uptime_percent']:null,'last_monitor_cycle_at'=>$mon['last_run_at']??null],'mirrors'=>$r['items']],$r['meta'],200,30);return;
    case 'history':$slug=mhApiText($_GET['slug']??'',120);if($slug===''){mhApiError(400,'missing_slug','نامک میرور الزامی است.');return;}$s=$pdo->prepare("SELECT id FROM mirrors WHERE slug=? AND status='active' LIMIT 1");$s->execute([$slug]);$id=(int)$s->fetchColumn();if(!$id){mhApiError(404,'mirror_not_found','میرور فعال پیدا نشد.');return;}
        $pg=mhApiPage($_GET);$limit=min(50,$pg['per_page']);$s=$pdo->prepare("SELECT checked_at,status,response_ms,http_code,method FROM mirror_checks WHERE mirror_id=? ORDER BY checked_at DESC LIMIT ".(int)$limit);$s->execute([$id]);$checks=array_map(static fn($x)=>['checked_at'=>$x['checked_at'],'status'=>$x['status'],'response_ms'=>$x['response_ms']!==null?(int)$x['response_ms']:null,'http_code'=>$x['http_code']!==null?(int)$x['http_code']:null,'method'=>$x['method']],$s->fetchAll());
        mhApiOk(['slug'=>$slug,'recent_checks'=>$checks,'uptime_daily_30d'=>array_values(monitorUptimeSeries($pdo,$id,30)),'latency_recent'=>monitorLatencySeries($pdo,$id,50)],['per_page'=>$limit],200,30);return;
    default:mhApiError(404,'endpoint_not_found','مسیر API پیدا نشد.');return;
    }}catch(InvalidArgumentException $e){mhApiError(400,'invalid_parameter','پارامتر درخواست معتبر نیست.');}
    catch(Throwable $e){error_log('MirrorHub API v1: request failed for resource '.preg_replace('/[^a-z0-9_-]/i','',$resource));mhApiError(500,'internal_error','خطای داخلی رخ داد؛ جزئیات فنی منتشر نمی‌شود.');}
}
