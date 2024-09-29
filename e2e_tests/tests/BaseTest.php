<?php

declare(strict_types=1);

namespace BelkaCar\GaugeExporterTests;

use Belkacar\GaugeExporterClient\MetricBag;
use Belkacar\GaugeExporterClient\Exception\BadResponseException;

final class BaseTest extends AbstractTestCase
{
    #[TestDox("Create metric with two lines")]
    public function testSimpleSend(): void
    {
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
    }

    #[TestDox("Create metric with float metric value")]
    public function testSendFloatValue(): void
    {
        $metricBag = new MetricBag('metric');
        $metricBag->set(['a' => 'b'], 5.65);
        $metricBag->set(['a' => 'c'], 6.76);
        $metricBag->set(['a' => 'e'], 6.7643);
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{a="b"} 5.65
        metric{a="c"} 6.76
        metric{a="e"} 6.7643
        OUT;

        $this->assertSame($expectedResult, $actualResult);
    }

    #[TestDox("Create metric without lines")]
    public function testSendEmptyBag(): void
    {
        $metricBag = new MetricBag('metric');
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();

        $this->assertSame('', $actualResult);
    }

    #[TestDox("Create metric without labels")]
    public function testSendBagWithNoLabels(): void
    {
        $metricBag = new MetricBag('metric');
        $metricBag->set([], 15);
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();

        $expectedResult = <<< OUT
        metric 15
        OUT;
        $this->assertSame($expectedResult, $actualResult);
    }

    #[TestDox("Create metric without labels")]
    public function testSendTwoMetrics(): void
    {
        $metricBag = new MetricBag('metric_one');
        $metricBag->set(['a' => 'b'], 5);
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $metricBag = new MetricBag('metric_two');
        $metricBag->set(['key' => 'value'], 10);
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric_one{a="b"} 5
        metric_two{key="value"} 10
        OUT;

        $this->assertSame($expectedResult, $actualResult);
    }

    #[TestDox("Create metric with multiple labels")]
    public function testMultipleLabels(): void
    {
        // Step 1
        $metricBag = new MetricBag('metric');
        $metricBag->set(['key1' => 'value1', 'key2' => 'value2'], 5);
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{key1="value1",key2="value2"} 5
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        // Step 2
        $metricBag = new MetricBag('metric');
        $metricBag->set(['key2' => 'value2', 'key1' => 'value1'], 10);
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{key1="value1",key2="value2"} 10
        OUT;
        $this->assertSame($expectedResult, $actualResult);
    }

    #[TestDox("Create metric with different labels cardinality")]
    public function testMultipleLabelsAndOnetipleLabels(): void
    {
        // Step 1
        $metricBag = new MetricBag('metric');
        $metricBag->set(['key1' => 'value1', 'key2' => 'value2'], 5);
        $metricBag->set(['key1' => 'value1'], 6);
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{key1="value1",key2="value2"} 5
        metric{key1="value1"} 6
        OUT;
        $this->assertSame($expectedResult, $actualResult);

        // Step 2
        $metricBag = new MetricBag('metric');
        $metricBag->set(['key2' => 'value2', 'key1' => 'value1'], 10);
        $metricBag->set(['key1' => 'value1'], 16);
        $this->createGaugeExporterClient()->send($metricBag, 300);

        $actualResult = $this->getPrometheusMetricsFromDaemon();
        $expectedResult = <<< OUT
        metric{key1="value1",key2="value2"} 10
        metric{key1="value1"} 16
        OUT;
        $this->assertSame($expectedResult, $actualResult);
    }

    #[TestDox("Metric name like a.b.c is converted to a_b_c")]
    public function testDotsAreConvertedToUnderscoresWithinMetricName(): void
    {
        // Arrange
        $metricBag = new MetricBag('metric.full.name');
        $metricBag->set(['key1' => 'value1', 'key2' => 'value2'], 5);
        $metricBag->set(['key1' => 'value1'], 6);

        // Act
        $this->createGaugeExporterClient()->send($metricBag, 300);

        // Assert
        $expectedResult = <<< OUT
        metric_full_name{key1="value1",key2="value2"} 5
        metric_full_name{key1="value1"} 6
        OUT;
        $this->assertSame($expectedResult, $this->getPrometheusMetricsFromDaemon());
    }

    #[TestDox("MetricBag labels must not contain any of system_labels")]
    public function testMetricBagLabelsIsNotAllowedToHaveAnyOfSystemLabels(): void
    {
        // Arrange
        $metricBag = new MetricBag('metric.name');
        $metricBag->set(['key1' => 'value1', 'key2' => 'value2'], 5);
        $metricBag->set(['key1' => 'value1'], 6);

        // Act
        $exceptionClass = null;
        $exceptionMessage = null;
        try {
            $this->createGaugeExporterClient(['key2' => 'any-value'])->send($metricBag, 300);
        } catch (\Exception $e) {
            $exceptionClass = get_class($e);
            $responseBodyContent = trim($e->getResponse()->getBody()->getContents());
        }

        // Assert
        $this->assertSame(BadResponseException::class, $exceptionClass);
        $this->assertSame('Metric labels must not contain any of system_labels', $responseBodyContent);
    }
}
