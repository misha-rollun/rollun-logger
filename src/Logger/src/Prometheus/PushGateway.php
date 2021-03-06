<?php
declare(strict_types=1);

namespace rollun\logger\Prometheus;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Prometheus\Collector;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Psr\Http\Message\ResponseInterface;
use rollun\logger\Writer\PrometheusWriter;

/**
 * Class PushGateway
 *
 * @author    r.ratsun <r.ratsun.rollun@gmail.com>
 *
 * @copyright Copyright © 2014 Rollun LC (http://rollun.com/)
 * @license   LICENSE.md New BSD License
 */
class PushGateway
{
    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $port = '9091';

    /**
     * PushGateway constructor.
     */
    public function __construct()
    {
        $this->host = getenv('PROMETHEUS_HOST');
        if (!empty(getenv('PROMETHEUS_PORT'))) {
            $this->port = getenv('PROMETHEUS_PORT');
        }
    }

    /**
     * @param CollectorRegistry $collectorRegistry
     * @param Collector         $collector
     * @param string            $job
     * @param array             $groupingKey
     * @param string            $method
     *
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function doRequest(CollectorRegistry $collectorRegistry, Collector $collector, string $job, array $groupingKey, string $method): ResponseInterface
    {
        $url = "http://{$this->host}:{$this->port}/metrics/job/" . $job;
        if (!empty($groupingKey)) {
            foreach ($groupingKey as $label => $value) {
                $url .= "/" . $label . "/" . $value;
            }
        }
        $client = new Client();
        $requestOptions = [
            'headers'         => [
                'Content-Type' => RenderTextFormat::MIME_TYPE,
            ],
            'connect_timeout' => 10,
            'timeout'         => 20,
        ];

        if ($method != PrometheusWriter::METHOD_DELETE) {
            $samples = $collectorRegistry->getMetricFamilySamples();
            foreach ($samples as $sample) {
                if ($sample->getName() == $collector->getName()) {
                    $requestOptions['body'] = (new RenderTextFormat())->render([$sample]);
                    break 1;
                }
            }
        }

        return $client->request($method, $url, $requestOptions);
    }
}
