package app

import "github.com/prometheus/client_golang/prometheus"

type uncheckedCollector struct {
	c prometheus.Collector
}

func (u uncheckedCollector) Describe(_ chan<- *prometheus.Desc) {}
func (u uncheckedCollector) Collect(ch chan<- prometheus.Metric) {
	u.c.Collect(ch)
}
