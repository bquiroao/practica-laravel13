<?php

declare(strict_types=1);
namespace NativeWeek4\Presentation;
use NativeWeek3\Application\Evaluate;

final class EvaluationController
{
    public function __construct(private Evaluate $evaluate) {}
    /** Traducción de entrada CLI, sin SQL, política de destino ni decisión de estado. */
    public function handle(array $input): array
    {
        $id=$input['demoOrderId']??null;
        $target=$input['target']??null;
        if(!is_string($id)||!is_string($target))return ['status'=>'INVALID_INPUT'];
        return $this->evaluate->execute($id,$target);
    }
}