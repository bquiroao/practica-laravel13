<?php

declare(strict_types=1);
namespace OrdersWeek3\Persistence;
use OrdersWeek3\Domain\{Order,Repository,PersistenceFailure};
final class PdoRepository implements Repository
{
    public function __construct(private \PDO $pdo) {}
    public function save(Order $o): int
    {
        try {
            $this->pdo->beginTransaction();
            $s=$this->pdo->prepare('INSERT INTO lab_orders (actor,patient,encounter,status) VALUES (:actor,:patient,:encounter,:status)');
            $s->execute(['actor'=>$o->actor,'patient'=>$o->patient,'encounter'=>$o->encounter,'status'=>$o->status]);
            $id=(int)$this->pdo->lastInsertId();
            $part=$this->pdo->prepare('INSERT INTO lab_order_tests (order_id,code) VALUES (:id,:code)');
            foreach($o->tests as $code)$part->execute(['id'=>$id,'code'=>$code]);
            $this->pdo->commit();return $id;
        } catch(\PDOException $e) {
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw new PersistenceFailure('Orden no confirmada.',0,$e);
        }
    }
    public function find(string $patient,string $encounter): array
    {
        try {
            $s=$this->pdo->prepare('SELECT id,actor,patient,encounter,status FROM lab_orders WHERE patient=:patient AND encounter=:encounter ORDER BY id');
            $s->execute(['patient'=>$patient,'encounter'=>$encounter]);
            $orders=$s->fetchAll(\PDO::FETCH_ASSOC);
            $parts=$this->pdo->prepare('SELECT code FROM lab_order_tests WHERE order_id=:id ORDER BY id');
            foreach($orders as &$o){$parts->execute(['id'=>$o['id']]);$o['tests']=$parts->fetchAll(\PDO::FETCH_COLUMN);}unset($o);
            return $orders;
        }catch(\PDOException $e){throw new PersistenceFailure('Consulta no disponible.',0,$e);}
    }
}