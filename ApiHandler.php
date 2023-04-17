<?php declare(strict_types=1);


require __DIR__ . '/vendor/autoload.php';

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Shortcut\Shortcut;

class Handler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $shortcut = new Shortcut();
        var_dump($request);

        $iteration_id = $request['queryStringParameters']['id'] ?? $shortcut->getCurrentIterationId();
        $shortcut->uploadReport($iteration_id, $shortcut->generateReport($iteration_id));
        return new Response(200, [], "success");
    }
}

return new Handler();
