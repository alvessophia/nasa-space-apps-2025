<?php
    // PHP 8.4 JSON API â€” CORS by ENV + logging (no API key)
    declare(strict_types=1);

    @set_time_limit(480);                 // atÃ© 8 min de execuÃ§Ã£o
    @ini_set('max_execution_time','480');
    @ini_set('default_socket_timeout','480');

    $openAIAPIKey = getenv("OPENAI_API_KEY");
    // ====== CONFIG ======
    $ENV='PROD'; // 'DEV' ou 'PROD'
    $ALLOW_ORIGINS = [
      'https://globotany.cwiz.com.br',  // 1 n (API)
      'https://globotanny.cwiz.com.br'  // 2 n (site atual da pÃ¡gina)
      ];
    $LOG_FILE='/var/log/globotany.log';

    // >>> Defina sua chave antes de subir para PROD <<<
    $openAIAPIKey = $openAIAPIKey ?? ''; // ex.: getenv('OPENAI_API_KEY')

    // ====== UTIL ======
    function respond(int $code,array $data):never{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
    }
    function allowOrigin(string $origin,bool $credentials=true):void{
    header("Access-Control-Allow-Origin: {$origin}");
    header('Vary: Origin');
    if($credentials){header('Access-Control-Allow-Credentials: true');}
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    }
    function log_globotany(string $msg,string $file):void{
    $line=sprintf("[%s] %s\n",date('Y-m-d H:i:s'),$msg);
    @file_put_contents($file,$line,FILE_APPEND|LOCK_EX);
    }
    function dateValid(string $d):bool{$dt=DateTime::createFromFormat('Y-m-d',$d);return $dt&&$dt->format('Y-m-d')===$d;}
    function urlValid(string $u):bool{return (bool)filter_var($u,FILTER_VALIDATE_URL);}
    function numRange(float $v,float $min,float $max):bool{return $v>=$min&&$v<=$max;}

    // ====== CORS & ORIGIN ENFORCEMENT ======
    $origin=$_SERVER['HTTP_ORIGIN']??''; $referer=$_SERVER['HTTP_REFERER']??''; $client=$_SERVER['REMOTE_ADDR']??'unknown';
    if($_SERVER['REQUEST_METHOD']==='OPTIONS'){
    if($ENV==='DEV'){allowOrigin($origin?:'*',false);http_response_code(204);exit;}
    if($origin && in_array($origin,$ALLOW_ORIGINS,true)){allowOrigin($origin);http_response_code(204);exit;}
    http_response_code(403);exit;
    }
    if($ENV==='DEV'){
    allowOrigin($origin?:'*',false);
    }else{
    if(!$origin||!in_array($origin,$ALLOW_ORIGINS,true)){respond(403,['error'=>'origin_not_allowed']);}
    allowOrigin($origin);
    $scheme=($_SERVER['HTTP_X_FORWARDED_PROTO']??($_SERVER['REQUEST_SCHEME']??'')); // forÃ§a HTTPS em PROD
    if(strtolower($scheme)!=='https'){log_globotany("Non-HTTPS blocked. IP={$client}",$LOG_FILE);respond(403,['error'=>'https_required']);}
    }

    // ====== INPUT ======
    $raw=file_get_contents('php://input');
    if($raw===''){log_globotany("Empty body. IP={$client}",$LOG_FILE);respond(400,['error'=>'empty_body']);}
    try{$payload=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){
    log_globotany("Invalid JSON: ".$e->getMessage()." IP={$client}",$LOG_FILE);
    respond(400,['error'=>'invalid_json']);
    }
    if(!is_array($payload)){respond(400,['error'=>'invalid_json_type']);}

    // ====== VALIDATION ======
    $required=['dateA','dateB','urlA','urlB','center'];
    foreach($required as $k){if(!array_key_exists($k,$payload)){log_globotany("Missing field: {$k} IP={$client}",$LOG_FILE);respond(422,['error'=>'missing_field','field'=>$k]);}}
    if(!is_string($payload['dateA'])||!dateValid($payload['dateA'])){respond(422,['error'=>'bad_dateA']);}
    if(!is_string($payload['dateB'])||!dateValid($payload['dateB'])){respond(422,['error'=>'bad_dateB']);}
    if(!is_string($payload['urlA'])||!urlValid($payload['urlA'])){respond(422,['error'=>'bad_urlA']);}
    if(!is_string($payload['urlB'])||!urlValid($payload['urlB'])){respond(422,['error'=>'bad_urlB']);}
    if(!is_array($payload['center'])||!isset($payload['center']['lat'],$payload['center']['lng'])){respond(422,['error'=>'bad_center']);}
    $lat=(float)$payload['center']['lat']; $lng=(float)$payload['center']['lng'];
    if(!numRange($lat,-90,90)||!numRange($lng,-180,180)){respond(422,['error'=>'center_out_of_range']);}
    if(!$openAIAPIKey){respond(500,['error'=>'openai_api_key_missing']);}

    // ====== SANITY HINTS ======
    if(str_contains($payload['urlA'],'time=')&&!str_contains($payload['urlA'],$payload['dateA'])){$warnA='urlA_time_mismatch';}
    if(str_contains($payload['urlB'],'time=')&&!str_contains($payload['urlB'],$payload['dateB'])){$warnB='urlB_time_mismatch';}

    // ====== IMAGE HELPERS (GD) ======
    /** Baixa bytes de uma URL com timeout e valida status 200 */
    function http_get_binary(string $url,int $timeout=60):string{
    $ch=curl_init($url);
    curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_FOLLOWLOCATION=>true,
    CURLOPT_TIMEOUT=>$timeout,
    CURLOPT_CONNECTTIMEOUT=>10,
    CURLOPT_SSL_VERIFYPEER=>true,
    CURLOPT_SSL_VERIFYHOST=>2,
    CURLOPT_HTTPHEADER=>['Accept: image/*']
    ]);
    $data=curl_exec($ch);
    $code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    $err=curl_error($ch);
    curl_close($ch);
    if($data===false||$code!==200){throw new RuntimeException("download_failed code={$code} err={$err}");}
    return (string)$data;
    }
    /** Cria GD image a partir de bytes; retorna [resource,width,height] */
    function gd_from_bytes(string $bytes):array{
    $img=@imagecreatefromstring($bytes);
    if(!$img){throw new RuntimeException('invalid_image_data');}
    $w=imagesx($img); $h=imagesy($img);
    return [$img,$w,$h];
    }
    /** Junta duas imagens lado a lado com cabeÃ§alho e rÃ³tulos simples; retorna caminho do JPEG */
    function compose_side_by_side($imgA,int $wA,int $hA,$imgB,int $wB,int $hB,string $labelA,string $labelB):string{
    $gap=10; $pad=10; $header=26; $footer=0;
    $W=$pad+$wA+$gap+$wB+$pad;
    $H=$pad+$header+max($hA,$hB)+$footer+$pad;
    $out=imagecreatetruecolor($W,$H);
    // cores
    $bg = imagecolorallocate($out, 10, 16, 32);
    $fg = imagecolorallocate($out, 220, 230, 255);
    $mut= imagecolorallocate($out, 140, 160, 200);
    $sep= imagecolorallocate($out, 40, 60, 110);
    imagefilledrectangle($out,0,0,$W-1,$H-1,$bg);
    // header
    imagestring($out, 5, $pad, $pad+5, "Globotany | A: {$labelA} | B: {$labelB}", $mut);
    // colagem
    $y0=$pad+$header;
    imagecopy($out,$imgA,$pad,$y0,0,0,$wA,$hA);
    imagecopy($out,$imgB,$pad+$wA+$gap,$y0,0,0,$wB,$hB);
    // separador vertical
    imageline($out,$pad+$wA+$gap/2,$y0,$pad+$wA+$gap/2,$y0+max($hA,$hB),$sep);
    // salva JPEG temp
    $tmp=tempnam(sys_get_temp_dir(),'globo_img_');
    $jpg=$tmp.'.jpg'; @unlink($tmp);
    imagejpeg($out,$jpg,90);
    imagedestroy($out);
    return $jpg;
    }
    /** Carrega e compÃµe; retorna caminho + base64 data URL */
    function build_composite_from_urls(string $urlA,string $labelA,string $urlB,string $labelB):array{
    $binA=http_get_binary($urlA); [$imA,$wA,$hA]=gd_from_bytes($binA);
    $binB=http_get_binary($urlB); [$imB,$wB,$hB]=gd_from_bytes($binB);
    $jpg=compose_side_by_side($imA,$wA,$hA,$imB,$wB,$hB,$labelA,$labelB);
    imagedestroy($imA); imagedestroy($imB);
    $bytes=file_get_contents($jpg);
    $b64=base64_encode($bytes);
    $dataUrl="data:image/jpeg;base64,".$b64;
    return [$jpg,$dataUrl];
    }

    // ====== OPENAI RESPONSES CALL ======
    /** Faz chamada Ã  Responses API com imagem base64 data URL e prompt */
    // Send merged image (data URL string) + prompt to OpenAI Responses API
    function openai_analyze(string $apiKey,string $dataUrl,string $prompt,int $maxTokens=2000):array{
    $endpoint='https://api.openai.com/v1/responses';
    $payload=[
    'model'=>'gpt-5', // ou 'gpt-5-2025-08-07'
    'input'=>[[
        'role'=>'user',
        'content'=>[
        ['type'=>'input_text','text'=>$prompt],
        ['type'=>'input_image','image_url'=>$dataUrl] // string (URL/data URL)
        ]
    ]],
    // Controle para modelos reasoning:
    'reasoning'=>['effort'=>'low'],                       // menos gasto em raciocÃ­nio
    'text'=>['format'=>['type'=>'text'],'verbosity'=>'low'], // forÃ§a saÃ­da textual enxuta
    'max_output_tokens'=>3000,                            // mais fÃ´lego para responder
    'tool_choice'=>'none'
    ];
    
    $ch=curl_init($endpoint);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>480,
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>[
        'Content-Type: application/json',
        'Authorization: Bearer '.$apiKey
        ],
        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
    ]);
    $resp=curl_exec($ch);
    $code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    $err=curl_error($ch);
    curl_close($ch);

    if($resp===false){throw new RuntimeException("openai_http_error: {$err}");}
    $json=json_decode($resp,true);
    if($code<200||$code>=300){throw new RuntimeException("openai_bad_status code={$code} body=".$resp);}

    // Try to extract text from Responses output
    $text=null;
    if(isset($json['output']) && is_array($json['output'])){
        $buf=[];
        foreach($json['output'] as $chunk){
        if(isset($chunk['content']) && is_array($chunk['content'])){
            foreach($chunk['content'] as $c){
            if(($c['type']??'')==='output_text' && isset($c['text'])){$buf[]=$c['text'];}
            if(($c['type']??'')==='text' && isset($c['text'])){$buf[]=$c['text'];}
            }
        }
        if(isset($chunk['output_text'])){$buf[]=$chunk['output_text'];}
        }
        $text=trim(implode("\n",$buf));
    }
    if(!$text && isset($json['output_text'])){$text=trim((string)$json['output_text']);}

    return ['status'=>$code,'body'=>$json,'text'=>$text];
    }

    // ====== PIPELINE ======
    log_globotany("Accepted request for analysis. IP={$client} ENV={$ENV}",$LOG_FILE);

    try{
    // 1) compor imagem
    [$jpgPath,$dataUrl]=build_composite_from_urls(
    $payload['urlA'],"{$payload['dateA']} (A)",
    $payload['urlB'],"{$payload['dateB']} (B)"
    );
    // 2) prompt (inclui contexto geogrÃ¡fico e questÃµes)
 $prompt = <<<HTML
You are a remote sensing analyst participating in the NASA Space Apps Challenge 2025 with team Globotany (https://www.spaceappschallenge.org/2025/find-a-team/globotany/
).
Analyze WITH RIGOR two TRUE COLOR satellite images joined side by side (A on the LEFT, B on the RIGHT), referring to the dates:
â€¢ Image A: {$payload['dateA']}
â€¢ Image B: {$payload['dateB']}
Approximate scene center: lat {$lat}, lng {$lng}.

OBJECTIVE
Generate an HTML5 REPORT, structured and ready for display in a browser (Chrome), with clear, technical, and concise language, covering:

Differences in BLOOMING between the years (A vs. B).

Likely type of TERRESTRIAL VEGETATION at the given coordinate.

Comments on the EVOLUTION of blooming from one year to the next.

FORMATTING REQUIREMENTS

Output must be EXCLUSIVELY HTML5 with no explanations outside the HTML.

Use <article>, <header>, and six <section>.

The header (<header>) must describe the region of the globe in the format: "Country â€“ State/Region â€“ Sub-region/Feature" (for example: "United States â€“ Colorado â€“ Rocky Mountains") based on latitude/longitude; if uncertain, provide the best guess and the confidence level.

Each section must have a TITLE in BOLD and an ICON (emoji/Unicode) compatible with Chrome (e.g., ðŸ—ºï¸, ðŸŒ¸, ðŸŒ¼, ðŸŒ¿, ðŸ“ˆ, âš ï¸). Do not use external images, only characters.

Whenever presenting judgments, include a CONFIDENCE label (low/medium/high) and, when useful, approximate percentage estimates (e.g., â€œ~35% Â±10%â€).

Avoid statements that cannot be seen/derived from the images; flag uncertainties.

Consider typical True Color limitations (clouds, shadows, solar angle, VIIRS/NOAA-20 resolution) and, if present, describe the impact on interpretation.

CONTENT OF THE SIX SECTIONS (in this order):

ðŸ—ºï¸ Geographic and environmental context
â€” Describe the region (countryâ€“stateâ€“sub-region), typical relief/biome/land use, dominant climate, and the approximate position of the central point (lat {$lat}, lng {$lng}). Include confidence.

ðŸŒ¸ Blooming â€” Image A ({$payload['dateA']})
â€” Characterize the blooming/greenness state in A (on the left): presence/absence of green/flowered/cultivated patches, linear patterns (agriculture), natural patches, cloud/smoke cover. Quantify in relative terms (% estimated vegetated/active coverage). Include confidence.

ðŸŒ¼ Blooming â€” Image B ({$payload['dateB']})
â€” Same for B (on the right), with the same metric/language to allow direct comparison. Include confidence.

ðŸ” Visual differences A â†’ B (interannual)
â€” List and compare changes in blooming/greenness between the years: increase/decrease, spatial shift, start/end of season, signs of water stress, agricultural/urban expansion, variations in water bodies that affect adjacent blooming. Use bullets and, when possible, quantify relative magnitude (small/medium/large) and direction (+/â€“). Include confidence.

ðŸŒ¿ Likely type of terrestrial vegetation at the point
â€” Provide the predominant expected type for the coordinate (e.g., temperate forest, grassland, cerrado, steppe, annual crop, vineyard, conifers, xerophytic shrubs, etc.), justifying by appearance, regional context, and seasonal pattern. Offer alternatives if ambiguous and mark confidence.

ðŸ“ˆ Blooming evolution and implications
â€” Interpret the observed patterns: typical phenology vs. anomaly, possible causes (seasonality, climate variability, agricultural management, natural disturbances/fires), ecological/agricultural implications, and recommendations (e.g., observe additional dates, use spectral indices, check time series). Indicate next steps suggested for the challenge.

NOTES

Consider that the image is a True Color composite; if deemed necessary, recommend future use of indices (NDVI, EVI) or cloud mask products.

Do not include the image HTML; produce only the structured textual report.

Keep the text objective (max. ~500â€“800 words in total), with lists when useful.

Do not repeat the instructions; only return the final HTML.

NOW GENERATE THE HTML5 AS ABOVE.
HTML;
    // 3) chamar OpenAI
    $analysis = openai_analyze($openAIAPIKey,$dataUrl,$prompt,900);

    // 4) resposta final
    $meta=[
    'received_at'=>gmdate('c'),
    'center_deg'=>['lat'=>$lat,'lng'=>$lng],
    'days_between'=>((new DateTime($payload['dateA']))->diff(new DateTime($payload['dateB'])))->days,
    'composite_path'=>$jpgPath
    ];
    if(isset($warnA)){$meta['warnA']=$warnA;}
    if(isset($warnB)){$meta['warnB']=$warnB;}

    log_globotany("openAI Returned: \n" . json_encode($analysis,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$LOG_FILE);

    respond(200,[
    'ok'=>true,
    'analysis_text'=>$analysis['text'],   // texto jÃ¡ extraÃ­do se disponÃ­vel
    'openai_raw'=>$analysis['body'],      // retorno completo da Responses API
    'meta'=>$meta,
    'received'=>$payload
    ]);

    } catch(Throwable $e){
    log_globotany("Pipeline error: ".$e->getMessage()." IP={$client}",$LOG_FILE);
    respond(500,['error'=>'analysis_failed','message'=>$e->getMessage()]);
    }
?>
