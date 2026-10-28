<?php

declare(strict_types=1);
namespace NativeWeek4\Presentation;
final class JsonView
{
    public function render(array $model): string
    {
        return json_encode(['scope'=>'CONTRACT_ONLY','result'=>$model],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
    }
}