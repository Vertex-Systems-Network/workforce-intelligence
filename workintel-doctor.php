<?php
$root=__DIR__;$fail=0;$warn=0;
/** Prints one diagnostic row and updates failure or warning counters. */
function row(string $label,bool $ok,string $detail='',bool $warning=false): void { global $fail,$warn; $tag=$ok?'OK':($warning?'WARN':'FAIL'); if(!$ok){if($warning)$warn++;else$fail++;} echo sprintf("[%s] %-28s %s\n",$tag,$label,$detail); }
echo "WorkIntel Doctor\n================\n";
row('PHP >= 8.3',version_compare(PHP_VERSION,'8.3.0','>='),PHP_VERSION);
$required=['openssl','pdo','mbstring','dom','xml','xmlwriter','fileinfo','json'];foreach($required as $ext)row('PHP ext '.$ext,extension_loaded($ext));
$dbConnection=''; $environmentFile=file_exists($root.'/.env')?$root.'/.env':$root.'/.env.example'; if(file_exists($environmentFile)){$raw=file_get_contents($environmentFile)?:'';preg_match('/^DB_CONNECTION=(.*)$/m',$raw,$dbm);$dbConnection=trim($dbm[1]??''," \"'");}
if($dbConnection==='mysql') row('PDO MySQL',extension_loaded('pdo_mysql'),'Required for DB_CONNECTION=mysql');
elseif($dbConnection==='pgsql') row('PDO PostgreSQL',extension_loaded('pdo_pgsql'),'Required for DB_CONNECTION=pgsql');
elseif($dbConnection==='sqlite') row('PDO SQLite',extension_loaded('pdo_sqlite'),'Required for the default zero install and PHPUnit');
else row('PDO SQLite (tests)',extension_loaded('pdo_sqlite'),'Required for PHPUnit sqlite :memory: tests',true);
foreach(['artisan','composer.json','package.json','config/view.php','storage/framework/views','storage/framework/sessions','storage/framework/cache/data','storage/logs'] as $item)row($item,file_exists($root.'/'.$item)||is_dir($root.'/'.$item));
foreach(['storage','bootstrap/cache'] as $dir)row($dir.' writable',is_dir($root.'/'.$dir)&&is_writable($root.'/'.$dir));
row('.env exists',file_exists($root.'/.env'),'Create from .env.example if missing',true);
if(file_exists($root.'/.env')){$env=file_get_contents($root.'/.env')?:'';preg_match('/^APP_URL=(.*)$/m',$env,$m);row('APP_URL configured',!empty(trim($m[1]??'')),trim($m[1]??''),true);preg_match('/^APP_KEY=(.*)$/m',$env,$k);row('APP_KEY configured',!empty(trim($k[1]??'')),'',true);}
$composer=trim((string)shell_exec('composer --version 2>&1'));row('Composer installed',$composer!==''&&!str_contains(strtolower($composer),'not found')&&!str_contains(strtolower($composer),'not recognized'),$composer);$node=trim((string)shell_exec('node --version 2>&1'));row('Node installed',$node!==''&&!str_contains(strtolower($node),'not recognized'),$node);$npm=trim((string)shell_exec('npm --version 2>&1'));row('npm installed',$npm!==''&&!str_contains(strtolower($npm),'not recognized'),$npm);
row('vendor installed',is_file($root.'/vendor/autoload.php'),'Run composer install if missing',true);row('node_modules installed',is_dir($root.'/node_modules'),'Run npm install if missing',true);
echo "\nResult: {$fail} failure(s), {$warn} warning(s).\n";exit($fail>0?1:0);
