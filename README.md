# Gauge Exporter

Gauge Exporter is a Prometheus exporter written in Go that allows you to expose custom gauge metrics to Prometheus. 
It provides a simple HTTP API for inputting metrics and serves them in Prometheus format.

This exporter is specifically designed for use with stateless languages and environments where it is not feasible to maintain metric state in memory, such as in PHP.
In these scenarios, Gauge Exporter provides a way to externally store and aggregate metrics that can't be kept in application memory between requests.
If your application is written in a language that allows for easy in-memory state management, you might find other Prometheus client libraries more suitable for direct instrumentation.

Gauge Exporter is specifically designed for gauge metrics and does not support counter metrics. 
It is optimized for use cases where the metric value can increase, decrease, or be set to a specific point.
If you need to work with cumulative counters that only increase over time, this exporter may not be suitable for your needs. Please, consider StatsD Exporter or Pushgateway.

## Comparison

### Gauge Exporter vs Prometheus Pushgateway 
Gauge Exporter offers two key advantages over Prometheus Pushgateway.
First, it automatically sets unprovided metric labels to zero, simplifying metric reporting with varying label sets.
Second, it supports Time-to-Live (TTL) based metric expiration, allowing automatic cleanup of stale data.
These features make Gauge Exporter particularly suitable for scenarios with time-sensitive metrics and dynamic labeling requirements, while Pushgateway remains ideal for batch jobs and situations requiring full control over metric persistence.

## Usage

### Running the Exporter

To run the Gauge Exporter, use the following command:

```bash
./gauge-exporter --listen=0.0.0.0:8181
```

You can customize the listening address and port using the `--listen` flag.

### Endpoints

1. `/metrics`: Prometheus metrics endpoint
2. `/gauge/{metric_name}`: Input endpoint for gauge metrics
3. `/version`: Returns the version of the exporter

### Inputting MetricBag 

To input a metric, send a PUT request to `/gauge/{metric_name}` with the following JSON payload:

```json
{
  "ttl": 300,
  "data": [
    {
      "labels": {
        "label1": "value1",
        "label2": "value2"
      },
      "value": 42.0
    }
  ],
  "system_labels": {
    "sys_label1": "sys_value1"
  }
}
```

- `ttl`: Time-to-live in seconds for the metric
- `data`: Array of metric data points
- `system_labels`: Labels applied to all data points in this request

### Querying Metrics

To query the metrics, send a GET request to the `/metrics` endpoint. This will return all the metrics in Prometheus format.

## Built-in Metrics

The exporter provides the following built-in metrics:

- `gauge_exporter_metric_lines_total`: Total number of metric lines stored
- `gauge_exporter_metrics_requests_total`: Total number of metric requests, labeled by status (success/failed)

## Clients
TBA