<?php

declare(strict_types=1);

namespace BelkaTech\GaugeExporterTests;

use BelkaTech\GaugeExporterClient\MetricBag;
use PHPUnit\Framework\Attributes\TestDox;

final class TtlTest extends AbstractTestCase
{
    #[TestDox("Simple TTL parameter test")]
    public function testSimpleTtl(): void
    {
        // Step 1
        $metricBag = new MetricBag('metric');
        $metricBag->set(['key1' => 'value1'], 5);
        $metricBag->set(['key1' => 'value2'], 6);
        $this->createGaugeExporterClient()->send($metricBag, 1);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{key1="value1"} 5
        metric{key1="value2"} 6
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        //Step 2
        sleep(2);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $this->assertEmpty($actualResult);
    }

    #[TestDox("Create metric without Labels and check TTL")]
    public function testTtlEmptyLabels(): void
    {
        // Step 1
        $metricBag = new MetricBag('metric');
        $metricBag->set([], 5);
        $this->createGaugeExporterClient()->send($metricBag, 1);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric 5
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        //Step 2
        sleep(2);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $this->assertEmpty($actualResult);
    }

    #[TestDox("Create two metrics with different TTL and check disappearance order")]
    public function testTtlTwoBags(): void
    {
        // Step 1
        $metricBag = new MetricBag('metric1');
        $metricBag->set(['key1' => 'value1'], 5);
        $metricBag->set(['key1' => 'value2'], 6);
        $this->createGaugeExporterClient()->send($metricBag, 1);

        $metricBag = new MetricBag('metric2');
        $metricBag->set(['key1' => 'value1'], 9);
        $metricBag->set(['key1' => 'value2'], 19);
        $this->createGaugeExporterClient()->send($metricBag, 3);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric1{key1="value1"} 5
        metric1{key1="value2"} 6
        metric2{key1="value1"} 9
        metric2{key1="value2"} 19
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        // Step 2
        sleep(2);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric2{key1="value1"} 9
        metric2{key1="value2"} 19
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        // Step 3
        sleep(4);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $this->assertEmpty($actualResult);
    }

    #[TestDox("Create metric and wait TTL, repeat twice")]
    public function testAddMetricAfterTtl(): void
    {
        // Step 1
        $metricBag = new MetricBag('metric');
        $metricBag->set(['key1' => 'value1'], 5);
        $metricBag->set(['key1' => 'value2'], 6);
        $this->createGaugeExporterClient()->send($metricBag, 1);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{key1="value1"} 5
        metric{key1="value2"} 6
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        // Step 2
        sleep(2);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $this->assertEmpty($actualResult);

        // Step 3
        $metricBag = new MetricBag('metric');
        $metricBag->set(['key1' => 'value1'], 5);
        $metricBag->set(['key1' => 'value2'], 6);
        $this->createGaugeExporterClient()->send($metricBag, 1);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{key1="value1"} 5
        metric{key1="value2"} 6
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        // Step 4
        sleep(2);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $this->assertEmpty($actualResult);
    }

    #[TestDox("Create metric with TTL, create metric again and extend TTL")]
    public function testExtendMetricTtl(): void
    {
        // Step 1
        $metricBag = new MetricBag('metric');
        $metricBag->set(['key1' => 'value1'], 5);
        $metricBag->set(['key1' => 'value2'], 6);
        $this->createGaugeExporterClient()->send($metricBag, 3);

        sleep(2);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{key1="value1"} 5
        metric{key1="value2"} 6
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        // Step 2
        $metricBag = new MetricBag('metric');
        $metricBag->set(['key1' => 'value1'], 15);
        $metricBag->set(['key1' => 'value2'], 16);
        $this->createGaugeExporterClient()->send($metricBag, 3);

        sleep(2);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{key1="value1"} 15
        metric{key1="value2"} 16
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        // Step 3
        sleep(2);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $this->assertEmpty($actualResult);
    }
}
