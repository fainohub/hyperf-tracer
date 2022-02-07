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
namespace Hyperf\Tracer\Aspect;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Uri;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AroundInterface;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\Exception;
use Hyperf\Tracer\ExceptionAppender;
use Hyperf\Tracer\SpanStarter;
use Hyperf\Tracer\SpanTagManager;
use Hyperf\Tracer\SwitchManager;
use OpenTracing\Tracer;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use const OpenTracing\Formats\TEXT_MAP;
use const OpenTracing\Tags\SPAN_KIND_RPC_CLIENT;

/** @Aspect */
class HttpClientAspect implements AroundInterface
{
    use SpanStarter;
    use ExceptionAppender;

    public array $classes = [Client::class . '::request'];

    public array $annotations = [];

    private Tracer $tracer;

    private SwitchManager $switchManager;

    private SpanTagManager $spanTagManager;

    public function __construct(Tracer $tracer, SwitchManager $switchManager, SpanTagManager $spanTagManager)
    {
        $this->tracer = $tracer;
        $this->switchManager = $switchManager;
        $this->spanTagManager = $spanTagManager;
    }

    /**
     * @throws Exception
     * @throws Throwable
     * @return mixed return the value from process method of ProceedingJoinPoint, or the value that you handled
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        if ($this->switchManager->isEnabled('guzzle') === false) {
            return $proceedingJoinPoint->process();
        }
        $options = $proceedingJoinPoint->arguments['keys']['options'];
        if (isset($options['no_aspect']) && $options['no_aspect'] === true) {
            return $proceedingJoinPoint->process();
        }
        /** @var Client $instance */
        $instance = $proceedingJoinPoint->getInstance();
        /** @var Uri $base_uri */
        $base_uri = $instance->getConfig('base_uri');
        $arguments = $proceedingJoinPoint->arguments;
        $method = strtoupper($arguments['keys']['method'] ?? '');
        $uri = $arguments['keys']['uri'] ?? '';
        $host = $base_uri === null ? (parse_url($uri, PHP_URL_HOST) ?? '') : $base_uri->getHost();

        $span = $this->startSpan($host, [], SPAN_KIND_RPC_CLIENT);

        $span->setTag('category', 'http');
        $span->setTag('component', 'GuzzleHttp');
        $span->setTag('kind', 'client');
        $span->setTag('source', $proceedingJoinPoint->className . '::' . $proceedingJoinPoint->methodName);

        if ($this->spanTagManager->has('http', 'url')) {
            $span->setTag($this->spanTagManager->get('http', 'url'), $uri);
        }
        if ($this->spanTagManager->has('http', 'host')) {
            $span->setTag($this->spanTagManager->get('http', 'host'), $host);
        }
        if ($this->spanTagManager->has('http', '.method')) {
            $span->setTag($this->spanTagManager->get('http', 'method'), $method);
        }

        $appendHeaders = [];
        // Injects the context into the wire
        $this->tracer->inject(
            $span->getContext(),
            TEXT_MAP,
            $appendHeaders
        );

        $options['headers'] = array_replace($options['headers'] ?? [], $appendHeaders);
        $proceedingJoinPoint->arguments['keys']['options'] = $options;

        foreach ($options['headers'] as $key => $value) {
            $span->setTag($this->spanTagManager->get('http', 'request.header') . '.' . $key, $value);
        }

        try {
            $result = $proceedingJoinPoint->process();
            if ($result instanceof ResponseInterface) {
                $span->setTag($this->spanTagManager->get('http', 'status_code'), $result->getStatusCode());
            }
            $span->setTag('otel.status_code', 'OK');
        } catch (Throwable $exception) {
            $this->switchManager->isEnabled('exception') && $this->appendExceptionToSpan($span, $exception);
            if ($exception instanceof BadResponseException) {
                $span->setTag($this->spanTagManager->get('http', 'status_code'), $exception->getResponse()->getStatusCode());
            }
            throw $exception;
        } finally {
            $span->finish();
        }
        return $result;
    }
}
