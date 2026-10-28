<?php
declare(strict_types=1);
namespace Auth\Persistence;
use Auth\Application\{PersistenceFailure,TenantRepository,UserRepository,SessionRepository};
use Auth\Domain\{User,Session};
use DateTimeImmutable;
final class InMemoryTenantRepository implements TenantRepository {
 private array $tenants=[]; private bool $fail=false;
 public function __construct(array $activeTenants=['H1'=>true,'H2'=>true]) { $this->tenants=$activeTenants; }
 public function failNext(): void {$this->fail=true;} private function ok():void{if($this->fail){$this->fail=false;throw new PersistenceFailure('Error de persistencia.');}}
 public function isActive(string $tenantId):bool{$this->ok();return ($this->tenants[$tenantId]??false)===true;}
}
final class InMemoryUserRepository implements UserRepository {
 private array $users=[]; private bool $fail=false;
 public function failNext():void{$this->fail=true;} private function ok():void{if($this->fail){$this->fail=false;throw new PersistenceFailure('Error de persistencia.');}}
 public function emailExists(string $tenantId,string $email):bool{$this->ok();foreach($this->users as $u)if($u->tenantId===$tenantId&&$u->email===$email)return true;return false;}
 public function add(User $user):void{$this->ok();if($this->emailExists($user->tenantId,$user->email))throw new PersistenceFailure('Error de persistencia.');$this->users[$user->id]=$user;}
 public function findByEmail(string $tenantId,string $email):?User{$this->ok();foreach($this->users as $u)if($u->tenantId===$tenantId&&$u->email===$email)return $u;return null;}
 public function find(string $userId):?User{$this->ok();return $this->users[$userId]??null;}
 public function all():array{return $this->users;}
}
final class InMemorySessionRepository implements SessionRepository {
 private array $sessions=[]; private array $hashes=[]; private bool $fail=false;
 public function failNext():void{$this->fail=true;} private function ok():void{if($this->fail){$this->fail=false;throw new PersistenceFailure('Error de persistencia.');}}
 public function add(Session $session,string $tokenHash):void{$this->ok();$this->sessions[$session->id]=$session;$this->hashes[$tokenHash]=$session->id;}
 public function findByTokenHash(string $tokenHash):?Session{$this->ok();$id=$this->hashes[$tokenHash]??null;return $id===null?null:($this->sessions[$id]??null);}
 public function revoke(string $sessionId,DateTimeImmutable $at):void{$this->ok();if(isset($this->sessions[$sessionId])&&$this->sessions[$sessionId]->revokedAt===null)$this->sessions[$sessionId]=new Session($this->sessions[$sessionId]->id,$this->sessions[$sessionId]->userId,$this->sessions[$sessionId]->tenantId,$this->sessions[$sessionId]->expiresAt,$at);}
}
