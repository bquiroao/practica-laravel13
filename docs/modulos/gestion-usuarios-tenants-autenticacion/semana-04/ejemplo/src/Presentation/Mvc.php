<?php
declare(strict_types=1);
namespace Auth\Presentation;
final class Request { public function __construct(public string $method,public string $path,public array $body=[],public array $headers=[]){} }
final class Response { public function __construct(public int $status,public array $body=[]){} }
final class AuthMvcController {
 public function __construct(private AuthController $auth){}
 public function register(string $tenant,array $b):Response{$r=$this->auth->register($tenant,(string)($b['email']??''),(string)($b['password']??''));return new Response($r['status'],$r['body']);}
 public function login(string $tenant,array $b):Response{$r=$this->auth->login($tenant,(string)($b['email']??''),(string)($b['password']??''));return new Response($r['status'],$r['body']);}
 public function logout(string $token):Response{$r=$this->auth->logout($token);return new Response($r['status'],$r['body']);}
}
final class Router {
 public function __construct(private AuthMvcController $controller){}
 public function dispatch(Request $r):Response {
  if(preg_match('#^/tenants/([^/]+)/users$#',$r->path,$m)){if($r->method!=='POST')return new Response(405,['error'=>'Método no permitido']);return $this->controller->register($m[1],$r->body);}
  if(preg_match('#^/tenants/([^/]+)/sessions$#',$r->path,$m)){if($r->method!=='POST')return new Response(405,['error'=>'Método no permitido']);return $this->controller->login($m[1],$r->body);}
  if($r->path==='/sessions'){if($r->method!=='DELETE')return new Response(405,['error'=>'Método no permitido']);$h=$r->headers['Authorization']??$r->headers['authorization']??'';$token=preg_match('/^Bearer\s+(.+)$/i',$h,$m)?$m[1]:'';return $this->controller->logout($token);}
  return new Response(404,['error'=>'Ruta no encontrada']);
 }
}
final class JsonView { public function render(Response $r):string{return json_encode($r->body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); } }
