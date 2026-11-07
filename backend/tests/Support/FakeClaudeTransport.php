<?php

namespace Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** PSR-18 transport for the Anthropic SDK in tests: records requests, answers with canned messages. */
class FakeClaudeTransport implements ClientInterface
{
    /** @var RequestInterface[] */
    public array $requests = [];

    /** @param array<int, array> $replies message bodies, returned in order */
    public function __construct(public array $replies) {}

    public static function message(string $text, string $stop = 'end_turn'): array
    {
        return [
            'id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => [['type' => 'text', 'text' => $text]], 'stop_reason' => $stop, 'stop_sequence' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ];
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $body = array_shift($this->replies) ?? self::message('{}');

        return new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
    }

    public function body(int $i = 0): array
    {
        return json_decode((string) $this->requests[$i]->getBody(), true);
    }
}
