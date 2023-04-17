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
        $iteration_id = $request->getQueryParams()['id'] ?? $shortcut->getCurrentIterationId();
        $shortcut->uploadReport((int) $iteration_id, $shortcut->generateReport((int) $iteration_id));
        return new Response(200, [], "I always return 200 lol");
    }
}

return new Handler();
