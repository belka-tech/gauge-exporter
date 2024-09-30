<?php

declare(strict_types=1);

namespace BelkaTech\GaugeExporterTests;

use BelkaTech\GaugeExporterClient\GaugeExporterClient;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

abstract class AbstractTestCase extends TestCase
{
    private const DAEMON_HOST = 'http://localhost:8181';
    private Process $daemonProcess;

    protected function setUp(): void
    {
        parent::setUp();

        $daemonPath = getenv('DAEMON_PATH');

        $this->daemonProcess = new Process([$daemonPath], realpath(__DIR__ . '/../..'));
        $this->daemonProcess->start();
        usleep(20_000);
        if (!$this->daemonProcess->isRunning()) {
            throw new RuntimeException('Daemon doesnt started: ' . $this->daemonProcess->getErrorOutput());
        }
    }

    protected function tearDown(): void
    {
        if ($this->daemonProcess->isRunning()) {
            $this->daemonProcess->stop();
        }
        parent::tearDown();
    }

    protected function createGaugeExporterClient(array $systemLabels = []): GaugeExporterClient
    {
        return new GaugeExporterClient(new Client(), self::DAEMON_HOST, $systemLabels);
    }

    protected function getPrometheusMetricsFromDaemon(): string
    {
        $httpClient = new Client();
        $metricsResponse = $httpClient->get(self::DAEMON_HOST . '/metrics');

        return $this->clearMetricsOutput($metricsResponse->getBody()->getContents());
    }

    private function clearMetricsOutput(string $output): string
    {
        $metricArray = array_filter(explode("\n", $output), function (string $line) {
            if (strlen($line) === 0) {
                return false;
            }
            if ($line[0] === '#') {
                return false;
            }
            if (str_starts_with($line, 'go_')) {
                return false;
            }
            if (str_starts_with($line, 'process_')) {
                return false;
            }
            if (str_starts_with($line, 'promhttp_')) {
                return false;
            }
            if (str_starts_with($line, 'gauge_exporter_')) {
                return false;
            }
            return true;
        });

        sort($metricArray);

        return implode("\n", $metricArray);
    }
}
