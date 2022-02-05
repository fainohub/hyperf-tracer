<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf + PicPay.
 *
 * @link     https://github.com/PicPay/hyperf-tracer
 * @document https://github.com/PicPay/hyperf-tracer/wiki
 * @contact  @PicPay
 * @license  https://github.com/PicPay/hyperf-tracer/blob/main/LICENSE
 */
namespace Hyperf\Tracer;

class SpanTagManager
{
    private const DEFAULTS = [
        'db' => [
            'db.query' => 'db.query',
            'db.statement' => 'db.statement',
            'db.query_time' => 'db.query_time',
        ],
        'coroutine' => [
            'id' => 'coroutine.id',
        ],
        'exception' => [
            'class' => 'exception.class',
            'code' => 'exception.code',
            'message' => 'exception.message',
            'stack_trace' => 'exception.stack_trace',
        ],
        'http' => [
            'url' => 'http.url',
            'host' => 'http.host',
            'method' => 'http.method',
            'target' => 'http.target',
            'route' => 'http.route',
            'scheme' => 'http.scheme',
            'server_name' => 'http.server_name',
            'status_code' => 'http.status_code',
            'request.header' => 'http.request.header',
            'response.header' => 'http.response.header',
        ],
        'net' => [
            'host.port' => 'net.host.port',
        ],
        'redis' => [
            'arguments' => 'arguments',
            'result' => 'result',
        ],
        'service' => [
            'name' => 'service.name',
            'namespace' => 'service.namespace',
            'instance.id' => 'service.instance.id',
            'version' => 'service.version',
        ]
    ];

    private array $tags = self::DEFAULTS;

    public function apply(array $tags): void
    {
        $this->tags = array_replace_recursive($this->tags, $tags);
    }

    public function get(string $type, string $name): string
    {
        return $this->tags[$type][$name];
    }

    public function has(string $type, string $name): bool
    {
        return isset($this->tags[$type][$name]);
    }
}
