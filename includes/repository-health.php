<?php
/* Repository metadata health is separate from HTTP reachability. */
function monitorRepositoryHealthProfiles(): array {
    return [
        'http_only' => ['label'=>'فقط دسترس‌پذیری HTTP','default_path'=>'','parser'=>'none'],
        'debian_release' => ['label'=>'Debian / InRelease','default_path'=>'dists/stable/InRelease','parser'=>'inrelease'],
        'ubuntu_release' => ['label'=>'Ubuntu / InRelease','default_path'=>'dists/noble/InRelease','parser'=>'inrelease'],
        'alpine_index' => ['label'=>'Alpine / APKINDEX','default_path'=>'latest-stable/main/x86_64/APKINDEX.tar.gz','parser'=>'last_modified'],
        'arch_db' => ['label'=>'Arch Linux / core.db','default_path'=>'core/os/x86_64/core.db','parser'=>'last_modified'],
        'rpm_repomd' => ['label'=>'RPM / repomd.xml','default_path'=>'repodata/repomd.xml','parser'=>'repomd'],
        'custom' => ['label'=>'مسیر سفارشی / Last-Modified','default_path'=>'','parser'=>'last_modified'],
    ];
}

function monitorNormalizeRepositoryPath(string $profile, ?string $path): array {
    $profiles=monitorRepositoryHealthProfiles();
    if(!isset($profiles[$profile])) return ['valid'=>false,'path'=>'','error'=>'پروفایل سلامت مخزن ناشناخته است.'];
    if($profile==='http_only') return ['valid'=>true,'path'=>'','error'=>null];
    $path=trim((string)$path);
    if($path==='') $path=(string)$profiles[$profile]['default_path'];
    $path=ltrim($path,'/');
    if($path==='' || mb_strlen($path,'UTF-8')>500 || str_contains($path,'\\') ||
       str_contains($path,'?') || str_contains($path,'#') ||
       preg_match('/[\x00-\x20\x7f]/',$path) || preg_match('/^[a-z][a-z0-9+.-]*:/i',$path)) {
        return ['valid'=>false,'path'=>'','error'=>'مسیر metadata باید نسبی و بدون URL، فاصله یا پارامتر query باشد.'];
    }
    foreach(explode('/',$path) as $segment) {
        if($segment==='' || $segment==='.' || $segment==='..') return ['valid'=>false,'path'=>'','error'=>'مسیر metadata نباید شامل پیمایش به والد باشد.'];
    }
    return ['valid'=>true,'path'=>$path,'error'=>null];
}

function monitorRepositoryHealthConfig(PDO $pdo,int $mirrorId): array {
    $defaults=['profile'=>'http_only','metadata_path'=>'','max_age_hours'=>168];
    if($mirrorId<=0) return $defaults;
    try {
        $stmt=$pdo->prepare('SELECT profile, metadata_path, max_age_hours FROM mirror_repository_health WHERE mirror_id = ? LIMIT 1');
        $stmt->execute([$mirrorId]); $row=$stmt->fetch();
        if(!$row) return $defaults;
        $profile=(string)($row['profile']??'http_only');
        if(!isset(monitorRepositoryHealthProfiles()[$profile])) $profile='http_only';
        return ['profile'=>$profile,'metadata_path'=>(string)($row['metadata_path']??''),'max_age_hours'=>max(1,min(720,(int)($row['max_age_hours']??168)))];
    } catch(Throwable $e) { return $defaults; }
}

function monitorSaveRepositoryHealthConfig(PDO $pdo,int $mirrorId,string $profile,?string $path,int $maxAgeHours): void {
    if($mirrorId<=0 || !isset(monitorRepositoryHealthProfiles()[$profile])) throw new InvalidArgumentException('تنظیمات سلامت مخزن نامعتبر است.');
    if($maxAgeHours<1 || $maxAgeHours>720) throw new InvalidArgumentException('حداکثر سن metadata باید بین ۱ تا ۷۲۰ ساعت باشد.');
    $normalized=monitorNormalizeRepositoryPath($profile,$path);
    if(!$normalized['valid']) throw new InvalidArgumentException((string)$normalized['error']);
    $storedPath=$normalized['path']!==''?$normalized['path']:null;
    $initialStatus=$profile==='http_only'?'not_configured':'unknown';
    $stmt=$pdo->prepare("INSERT INTO mirror_repository_health
      (mirror_id,profile,metadata_path,max_age_hours,status,last_checked_at,http_code,response_ms,last_modified_at,metadata_date,age_seconds,checked_url,content_type,message)
      VALUES (?,?,?,?,?,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL)
      ON DUPLICATE KEY UPDATE profile=VALUES(profile),metadata_path=VALUES(metadata_path),max_age_hours=VALUES(max_age_hours),
      status=VALUES(status),last_checked_at=NULL,http_code=NULL,response_ms=NULL,last_modified_at=NULL,metadata_date=NULL,
      age_seconds=NULL,checked_url=NULL,content_type=NULL,message=NULL");
    $stmt->execute([$mirrorId,$profile,$storedPath,$maxAgeHours,$initialStatus]);
}

function monitorRepositoryFetchMetadata(string $url,bool $wantBody,float $timeoutSeconds=4.0,int $maxBytes=65536): array {
    $out=['http_code'=>0,'response_ms'=>null,'last_modified'=>null,'content_type'=>null,'body'=>'','error'=>null];
    $timeoutSeconds=max(1.0,min(8.0,$timeoutSeconds)); $maxBytes=max(1024,min(65536,$maxBytes)); $started=microtime(true);
    if(function_exists('curl_init')) {
        $headers=[]; $body=''; $truncated=false; $ch=curl_init($url);
        $opts=[
            CURLOPT_USERAGENT=>SITE_NAME.'-Repository-Health/1.0',CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>5,
            CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>(int)ceil($timeoutSeconds),CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_NOBODY=>!$wantBody,CURLOPT_RETURNTRANSFER=>!$wantBody,CURLOPT_HEADER=>false,
            CURLOPT_HEADERFUNCTION=>static function($curl,string $line) use (&$headers): int {
                $len=strlen($line);
                if(preg_match('/^HTTP\/\S+\s+\d+/i',trim($line))) $headers=[];
                $pair=explode(':',$line,2);
                if(count($pair)===2) { $key=strtolower(trim($pair[0])); if(in_array($key,['last-modified','content-type'],true)) $headers[$key]=trim($pair[1]); }
                return $len;
            },
        ];
        if(defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            $opts[CURLOPT_PROTOCOLS]=CURLPROTO_HTTP|CURLPROTO_HTTPS;
            if(defined('CURLOPT_REDIR_PROTOCOLS')) $opts[CURLOPT_REDIR_PROTOCOLS]=CURLPROTO_HTTP|CURLPROTO_HTTPS;
        }
        if($wantBody) $opts[CURLOPT_WRITEFUNCTION]=static function($curl,string $chunk) use (&$body,&$truncated,$maxBytes): int {
            $remaining=$maxBytes-strlen($body);
            if($remaining<=0) { $truncated=true; return 0; }
            $len=strlen($chunk);
            if($len>$remaining) { $body.=substr($chunk,0,$remaining); $truncated=true; return 0; }
            $body.=$chunk;
            if(strlen($body)>=$maxBytes) { $truncated=true; return 0; }
            return $len;
        };
        $transport=curl_exec($ch); $out['http_code']=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        $errno=curl_errno($ch); $error=(string)curl_error($ch); curl_close($ch);
        $out['response_ms']=(int)max(1,round((microtime(true)-$started)*1000));
        $out['last_modified']=$headers['last-modified']??null; $out['content_type']=$headers['content-type']??null; $out['body']=$body;
        $writeErr=defined('CURLE_WRITE_ERROR')?CURLE_WRITE_ERROR:23;
        if($errno!==0 && !($wantBody && $truncated && $errno===$writeErr)) $out['error']=$error!==''?$error:'خطای انتقال metadata';
        elseif($transport===false && !$truncated) $out['error']='دریافت metadata ناموفق بود.';
        return $out;
    }
    $ctx=stream_context_create(['http'=>['method'=>$wantBody?'GET':'HEAD','timeout'=>$timeoutSeconds,'ignore_errors'=>true,'follow_location'=>1,'max_redirects'=>5,
        'header'=>'User-Agent: '.SITE_NAME.'-Repository-Health/1.0'."\r\n"],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $stream=@fopen($url,'rb',false,$ctx); $headers=$http_response_header??[];
    foreach($headers as $header) {
        if(preg_match('/^HTTP\/\S+\s+(\d+)/i',$header,$m)) $out['http_code']=(int)$m[1];
        elseif(stripos($header,'Last-Modified:')===0) $out['last_modified']=trim(substr($header,14));
        elseif(stripos($header,'Content-Type:')===0) $out['content_type']=trim(substr($header,13));
    }
    if($stream===false) $out['error']='اتصال به metadata ناموفق بود.';
    else { if($wantBody) $out['body']=(string)stream_get_contents($stream,$maxBytes); fclose($stream); }
    $out['response_ms']=(int)max(1,round((microtime(true)-$started)*1000)); return $out;
}

function monitorRepositoryTimestamp(?string $raw): ?int {
    $raw=trim((string)$raw); if($raw==='') return null;
    if(preg_match('/^\d{10}$/',$raw)) { $ts=(int)$raw; return $ts>946684800?$ts:null; }
    if(preg_match('/^\d{8}$/',$raw)) { $date=DateTimeImmutable::createFromFormat('!Ymd',$raw); if($date instanceof DateTimeImmutable) return $date->getTimestamp(); }
    $ts=strtotime($raw); return $ts===false?null:$ts;
}

function monitorEvaluateRepositoryHealth(string $profile,array $response,int $maxAgeHours,?int $now=null): array {
    $now=$now??time(); $maxAgeHours=max(1,min(720,$maxAgeHours)); $code=(int)($response['http_code']??0);
    $modified=monitorRepositoryTimestamp((string)($response['last_modified']??'')); $body=(string)($response['body']??'');
    $out=['status'=>'unknown','http_code'=>$code,'response_ms'=>isset($response['response_ms'])?(int)$response['response_ms']:null,
      'last_modified_at'=>$modified?date('Y-m-d H:i:s',$modified):null,'metadata_date'=>null,'age_seconds'=>null,
      'content_type'=>isset($response['content_type'])?mb_substr((string)$response['content_type'],0,255):null,'checked_url'=>null,'message'=>''];
    if(!empty($response['error'])) { $out['status']='error'; $out['message']='خطای دریافت metadata: '.mb_substr((string)$response['error'],0,420); return $out; }
    if($code===404 || $code===410) { $out['status']='invalid'; $out['message']='فایل metadata پیدا نشد (HTTP '.$code.'). مسیر مخزن یا نسخهٔ توزیع را بررسی کنید.'; return $out; }
    if($code<200 || $code>=300) { $out['status']='error'; $out['message']=$code>0?'metadata با HTTP '.$code.' در دسترس نیست.':'پاسخ HTTP از metadata دریافت نشد.'; return $out; }

    $metadata=null; $validUntil=null; $parser=monitorRepositoryHealthProfiles()[$profile]['parser']??'last_modified';
    if($parser==='inrelease') {
        if(preg_match('/^Date:\s*(.+)$/mi',$body,$m)) $metadata=monitorRepositoryTimestamp($m[1]);
        if(preg_match('/^Valid-Until:\s*(.+)$/mi',$body,$m)) $validUntil=monitorRepositoryTimestamp($m[1]);
        if($metadata===null) { $out['status']='invalid'; $out['message']='فایل InRelease پاسخ داده، اما فیلد Date قابل تشخیص نیست.'; return $out; }
        if($validUntil!==null && $validUntil<$now) { $out['status']='stale'; $out['metadata_date']=date('Y-m-d H:i:s',$metadata); $out['age_seconds']=max(0,$now-$metadata); $out['message']='مقدار Valid-Until فایل InRelease منقضی شده است.'; return $out; }
    } elseif($parser==='repomd') {
        if(preg_match('/<revision>\s*([^<]+)\s*<\/revision>/i',$body,$m)) $metadata=monitorRepositoryTimestamp($m[1]);
        if($metadata===null) $metadata=$modified;
    } elseif($parser==='last_modified') $metadata=$modified;

    if($metadata===null) { $out['status']='unknown'; $out['message']='فایل metadata در دسترس است، اما تاریخ قابل اتکایی برای سنجش تازگی ارائه نمی‌دهد.'; return $out; }
    $age=max(0,$now-$metadata); $out['metadata_date']=date('Y-m-d H:i:s',$metadata); $out['age_seconds']=$age;
    if($age>$maxAgeHours*3600) { $out['status']='stale'; $out['message']='metadata از حد تازگی تنظیم‌شده ('.$maxAgeHours.' ساعت) قدیمی‌تر است.'; }
    else { $out['status']='healthy'; $out['message']=$validUntil!==null?'metadata در دسترس است و Valid-Until آن هنوز منقضی نشده است.':'metadata در دسترس است و تاریخ آن در محدودهٔ تازگی تنظیم‌شده است.'; }
    return $out;
}

function monitorCheckRepositoryHealth(string $baseUrl,string $profile,?string $path,int $maxAgeHours=168): array {
    $profiles=monitorRepositoryHealthProfiles();
    if(!isset($profiles[$profile]) || $profile==='http_only') return ['status'=>'not_configured','message'=>'بررسی محتوای مخزن برای این میرور پیکربندی نشده است.'];
    $normalized=monitorNormalizeRepositoryPath($profile,$path);
    if(!$normalized['valid']) return ['status'=>'invalid','message'=>(string)$normalized['error']];
    $parts=parse_url(trim($baseUrl));
    if(!$parts || !in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true) || empty($parts['host']) ||
       isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        return ['status'=>'error','message'=>'آدرس پایهٔ مخزن باید URL معتبر HTTP/HTTPS بدون اطلاعات ورود یا query باشد.'];
    }
    $url=rtrim($baseUrl,'/').'/'.$normalized['path'];
    $wantBody=in_array($profiles[$profile]['parser'],['inrelease','repomd'],true);
    $response=monitorRepositoryFetchMetadata($url,$wantBody,4.0,65536);
    if(!$wantBody && in_array((int)($response['http_code']??0),[405,501],true)) $response=monitorRepositoryFetchMetadata($url,true,4.0,2048);
    $result=monitorEvaluateRepositoryHealth($profile,$response,$maxAgeHours); $result['checked_url']=$url; return $result;
}

function monitorPersistRepositoryHealth(PDO $pdo,int $mirrorId,array $result): void {
    if($mirrorId<=0) return;
    $stmt=$pdo->prepare("UPDATE mirror_repository_health SET status=?,last_checked_at=NOW(),http_code=?,response_ms=?,
       last_modified_at=?,metadata_date=?,age_seconds=?,checked_url=?,content_type=?,message=? WHERE mirror_id=?");
    $stmt->execute([$result['status']??'unknown',$result['http_code']??null,$result['response_ms']??null,
       $result['last_modified_at']??null,$result['metadata_date']??null,$result['age_seconds']??null,
       isset($result['checked_url'])?mb_substr((string)$result['checked_url'],0,1000):null,
       isset($result['content_type'])?mb_substr((string)$result['content_type'],0,255):null,
       mb_substr((string)($result['message']??''),0,500),$mirrorId]);
}

function monitorRepositoryHealthStatusFa(string $status): string {
    return match($status) {'healthy'=>'محتوا سالم','stale'=>'محتوا قدیمی','invalid'=>'metadata نامعتبر',
       'error'=>'خطای بررسی محتوا','not_configured'=>'فقط HTTP',default=>'نامشخص'};
}

function monitorRepositoryHealthBadge(?array $row): string {
    $profile=(string)($row['repository_health_profile']??$row['profile']??'http_only');
    $status=(string)($row['repository_health_status']??$row['health_status']??'not_configured');
    if($profile==='http_only') $status='not_configured';
    $classes=['healthy'=>'mon-up','stale'=>'mon-slow','invalid'=>'mon-down','error'=>'mon-down','unknown'=>'mon-unknown','not_configured'=>'mon-unknown'];
    $message=trim((string)($row['repository_health_message']??$row['message']??''));
    $checked=$row['repository_health_checked_at']??$row['last_checked_at']??null;
    $title=$message!==''?$message:monitorRepositoryHealthStatusFa($status);
    if($checked) $title.=' — آخرین بررسی: '.monitorTimeAgoFa((string)$checked);
    return '<span class="mon-badge mon-sm '.$classes[$status].'" title="'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'"><span class="mon-dot"></span><span>'.htmlspecialchars(monitorRepositoryHealthStatusFa($status),ENT_QUOTES,'UTF-8').'</span></span>';
}
