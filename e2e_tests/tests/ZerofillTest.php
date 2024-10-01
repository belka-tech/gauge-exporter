<?php

declare(strict_types=1);

namespace BelkaTech\GaugeExporterTests;

use BelkaTech\GaugeExporterClient\MetricBag;
use PHPUnit\Framework\Attributes\TestDox;

final class ZerofillTest extends AbstractTestCase
{
    #[TestDox("Create metric with two lines and zerofill one line")]
    public function testSendOneLine1(): void
    {
        // Step 1
        $metricBag = new MetricBag('metric');
        $metricBag->set(['a' => 'b'], 5);
        $metricBag->set(['a' => 'c'], 6);
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{a="b"} 5
        metric{a="c"} 6
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        // Step 2
        $metricBag = new MetricBag('metric');
        $metricBag->set(['a' => 'b'], 10);
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{a="b"} 10
        metric{a="c"} 0
        OUT;
        $this->assertSame($expectedResult, $actualResult);
    }

    #[TestDox("Create metric with two lines and zerofill two lines and add one line more")]
    public function testSendOneLine2(): void
    {
        // Step 1
        $metricBag = new MetricBag('metric');
        $metricBag->set(['a' => 'b'], 5);
        $metricBag->set(['a' => 'c'], 6);
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{a="b"} 5
        metric{a="c"} 6
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        // Step 2
        $metricBag = new MetricBag('metric');
        $metricBag->set(['a' => 'd'], 10);
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{a="b"} 0
        metric{a="c"} 0
        metric{a="d"} 10
        OUT;
        $this->assertSame($expectedResult, $actualResult);
    }

    #[TestDox("Create metric with two multiple lines and zerofill with adding new line")]
    public function testMultipleLabelsLine2(): void
    {
        // Step 1
        $metricBag = new MetricBag('metric');
        $metricBag->set(['a' => 'b', 'c' => 'b'], 5);
        $metricBag->set(['a' => 'c'], 6);
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{a="b",c="b"} 5
        metric{a="c"} 6
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        // Step 2
        $metricBag = new MetricBag('metric');
        $metricBag->set(['a' => 'd'], 10);
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{a="b",c="b"} 0
        metric{a="c"} 0
        metric{a="d"} 10
        OUT;
        $this->assertSame($expectedResult, $actualResult);
    }

    #[TestDox("Create metric with two lines and send request with bag but without lines")]
    public function testSendEmptyBag(): void
    {
        // Step 1
        $metricBag = new MetricBag('metric');
        $metricBag->set(['a' => 'b'], 5);
        $metricBag->set(['a' => 'c'], 6);
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{a="b"} 5
        metric{a="c"} 6
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        // Step 2
        $metricBag = new MetricBag('metric');
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{a="b"} 0
        metric{a="c"} 0
        OUT;
        $this->assertSame($expectedResult, $actualResult);
    }

    #[TestDox("Create metric with two lines and send one more with different system labels")]
    public function testSendWithMultipleDefaultLabels(): void
    {
        // Step 1
        $metricBag = new MetricBag('metric');
        $metricBag->set(['a' => 'b'], 5);
        $metricBag->set(['a' => 'c'], 6);
        $this->createGaugeExporterClient(['env' => 'test'])->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{a="b",env="test"} 5
        metric{a="c",env="test"} 6
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        // Step 2
        $metricBag = new MetricBag('metric');
        $metricBag->set(['a' => 'b'], 5);
        $this->createGaugeExporterClient(['host' => 'rent'])->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{a="b",env="test"} 5
        metric{a="b",host="rent"} 5
        metric{a="c",env="test"} 6
        OUT;
        $this->assertSame($expectedResult, $actualResult);
    }

    #[TestDox("Create metric with two lines and send two more with different system labels")]
    public function testSendWithMultipleDefaultLabels2(): void
    {
        // Step 1
        $metricBag = new MetricBag('metric');
        $metricBag->set(['a' => 'b'], 5);
        $metricBag->set(['a' => 'c'], 6);
        $this->createGaugeExporterClient(['env' => 'test'])->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{a="b",env="test"} 5
        metric{a="c",env="test"} 6
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        // Step 2
        $metricBag = new MetricBag('metric');
        $metricBag->set(['a' => 'b'], 5);
        $this->createGaugeExporterClient(['host' => 'rent'])->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{a="b",env="test"} 5
        metric{a="b",host="rent"} 5
        metric{a="c",env="test"} 6
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        // Step 3
        $metricBag = new MetricBag('metric');
        $metricBag->set(['a' => 'c'], 6);
        $this->createGaugeExporterClient(['host' => 'rent'])->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{a="b",env="test"} 5
        metric{a="b",host="rent"} 0
        metric{a="c",env="test"} 6
        metric{a="c",host="rent"} 6
        OUT;
        $this->assertSame($expectedResult, $actualResult);
    }
}
