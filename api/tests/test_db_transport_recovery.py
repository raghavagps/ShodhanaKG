"""Local controlled sockets reproduce slow/unfinished backend responses, without touching KG/services."""
import json,os,socketserver,subprocess,threading,time,unittest
PHP=os.environ.get('PHP_BIN','php')
class Peer(socketserver.BaseRequestHandler):
    def handle(self):
        self.request.recv(65536)
        mode=self.server.mode
        if mode=='stall': time.sleep(3); return
        body=b'{"result":[{"value":1}]}'
        if mode=='chunked': response=b'HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n'+hex(len(body))[2:].encode()+b'\r\n'+body+b'\r\n0\r\n\r\n'
        else: response=b'HTTP/1.1 200 OK\r\nContent-Length: '+str(len(body)).encode()+b'\r\n\r\n'+body
        self.request.sendall(response);time.sleep(3)
class Server(socketserver.ThreadingTCPServer):
    daemon_threads=True
class Recovery(unittest.TestCase):
    def run_peer(self,mode,full=False):
        with Server(('127.0.0.1',0),Peer) as server:
            server.mode=mode;threading.Thread(target=server.serve_forever,daemon=True).start()
            url='http://127.0.0.1:'+str(server.server_address[1])
            expression="arcade_command('SELECT 1','sql',array(),1)" if full else "arcade_command_socket('"+url+"/test','{}','username','password',1)"
            php="require 'api/db.php';$ARCADE_URL='"+url+"';try{$r="+expression+";echo json_encode($r);}catch(Exception $e){echo 'TIMEOUT';}"
            t=time.monotonic();p=subprocess.run([PHP,'-r',php],capture_output=True,text=True,timeout=5);elapsed=time.monotonic()-t
            server.shutdown();self.assertEqual(p.returncode,0,p.stderr);return p.stdout,elapsed
    def test_framed_responses_complete_without_socket_close(self):
        for mode in ['length','chunked']:
            with self.subTest(mode=mode):
                body,elapsed=self.run_peer(mode);self.assertIn('result',json.loads(body));self.assertLess(elapsed,1.5)
    def test_socket_timeout_and_next_request_recovery(self):
        body,elapsed=self.run_peer('stall');self.assertEqual(body,'TIMEOUT');self.assertLess(elapsed,2)
        body,elapsed=self.run_peer('length');self.assertIn('result',json.loads(body));self.assertLess(elapsed,1.5)
    def test_shared_transport_deadline(self):
        body,elapsed=self.run_peer('stall',True);self.assertEqual(body,'TIMEOUT');self.assertLess(elapsed,2)
if __name__=='__main__':unittest.main()
