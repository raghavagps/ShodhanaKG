<?php
require_once __DIR__.'/../core/PhpCompatibility.php';
require_once __DIR__.'/../core/SocketHttp.php';
require_once __DIR__.'/../core/LLMClient.php';
$checks=0;
function hostCheck($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
hostCheck(mb_strtolower('ĀYURVEDA','UTF-8')==='āyurveda','Unicode lowercasing');
hostCheck(mb_strlen('शोधन','UTF-8')===4,'Unicode character count');
hostCheck(mb_substr('Āyurveda',0,1,'UTF-8')==='Ā','Unicode slicing');
$previous=getenv('OLLAMA_KEEP_ALIVE');
$method=new ReflectionMethod('LLMClient','keepAlive');$method->setAccessible(true);
foreach (['-1'=>-1,'0'=>0,'30m'=>'30m'] as $setting=>$expected) {
 putenv('OLLAMA_KEEP_ALIVE='.$setting);hostCheck($method->invoke(null)===$expected,'Keep-alive JSON type preserved');
}
if ($previous===false) putenv('OLLAMA_KEEP_ALIVE'); else putenv('OLLAMA_KEEP_ALIVE='.$previous);
$r=SocketHttp::decode("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nOK");
hostCheck($r['status']===200&&$r['body']==='OK','Fixed length');
$r=SocketHttp::decode("HTTP/1.1 503 Unavailable\r\nTransfer-Encoding: chunked\r\n\r\n2\r\nNO\r\n0\r\n\r\n");
hostCheck($r['status']===503&&$r['body']==='NO','Chunked non-success preserved');
foreach (["HTTP/1.1 200 OK\r\nContent-Length: 4\r\n\r\nOK", "HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n5\r\nOK\r\n", 'invalid'] as $raw) {
 $failed=false;try{SocketHttp::decode($raw);}catch(Exception $e){$failed=true;}hostCheck($failed,'Malformed/truncated response rejected');
}
echo 'PASS '.$checks.' legacy host transport checks on '.PHP_VERSION."\n";
