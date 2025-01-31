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
namespace Hyperf\Tracer\Adapter;

use Hyperf\Guzzle\ClientFactory as GuzzleClientFactory;
use RuntimeException;
use function strlen;

class HttpClientFactory
{
    private GuzzleClientFactory $guzzleClientFactory;

    public function __construct(GuzzleClientFactory $guzzleClientFactory)
    {
        $this->guzzleClientFactory = $guzzleClientFactory;
    }

    public function build(array $options): callable
    {
        return function (string $payload) use ($options): void {
            $url = $options['endpoint_url'];
            unset($options['endpoint_url']);
            $client = $this->guzzleClientFactory->create($options);
            $additionalHeaders = $options['headers'] ?? [];
            $requiredHeaders = [
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($payload),
                'b3' => '0',
            ];
            $headers = array_merge($additionalHeaders, $requiredHeaders);
            $response = $client->post($url, [
                'body' => $payload,
                'headers' => $headers,
                // If 'no_aspect' option is true, then the HttpClientAspect will not modify the client options.
                'no_aspect' => true,
            ]);
            $statusCode = $response->getStatusCode();
            if ($statusCode !== 202) {
                throw new RuntimeException(
                    sprintf('Reporting of spans failed, status code %d', $statusCode)
                );
            }
        };
    }
}
