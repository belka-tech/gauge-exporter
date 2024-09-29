package storage

import (
	"sync"
	"time"
)

type MetricBag map[string]*MetricLine

// MetricsStorage это хранилище всех метрик и их значений(MetricLine).
// Структура является потоко-безопасной.
type MetricsStorage struct {
	mutex          sync.RWMutex
	metricToBag    map[string]MetricBag
	metricToExpiry map[string]time.Time
}

func NewMetricsStorage() *MetricsStorage {
	return &MetricsStorage{
		metricToBag:    make(map[string]MetricBag),
		metricToExpiry: make(map[string]time.Time),
	}
}

func (s *MetricsStorage) UpdateMetricLines(
	metricName string,
	systemLabels map[string]string,
	lines []*MetricLine,
	ttl time.Duration,
) {
	s.mutex.Lock()
	defer s.mutex.Unlock()

	s.zeroFillMetricLines(metricName, systemLabels)

	s.metricToExpiry[metricName] = time.Now().Add(ttl)

	for _, line := range lines {
		s.addMetricLine(metricName, line)
	}
}

func (s *MetricsStorage) IsExpired(metricName string) bool {
	return time.Now().After(s.metricToExpiry[metricName])
}

func (s *MetricsStorage) GetMetricLines(name string) []*MetricLine {
	s.mutex.RLock()
	defer s.mutex.RUnlock()

	lines := make([]*MetricLine, 0, len(s.metricToBag[name]))
	for _, line := range s.metricToBag[name] {
		lines = append(lines, line)
	}
	return lines
}

func (s *MetricsStorage) GetAllMetricsNames() []string {
	s.mutex.RLock()
	defer s.mutex.RUnlock()

	names := make([]string, 0, len(s.metricToBag))
	for name := range s.metricToBag {
		names = append(names, name)
	}

	return names
}

func (s *MetricsStorage) Delete(metricName string) {
	s.mutex.Lock()
	defer s.mutex.Unlock()

	delete(s.metricToBag, metricName)
	delete(s.metricToExpiry, metricName)
}

func (s *MetricsStorage) addMetricLine(metricName string, line *MetricLine) {
	if s.metricToBag[metricName] == nil {
		s.metricToBag[metricName] = make(MetricBag)
	}
	s.metricToBag[metricName][line.String()] = line
}

func (s *MetricsStorage) zeroFillMetricLines(metricName string, systemLabels map[string]string) {
	for _, line := range s.metricToBag[metricName] {
		if line.HasLabelsWithValues(systemLabels) {
			line.Value = 0
		}
	}
}
