package storage

import (
	"fmt"
	"sort"
	"strings"
)

// MetricLine представляет собой конкретное значение метрики.
// Реализует интерфейс Stringer чтобы структуру можно было использовать в контексте строки.
type MetricLine struct {
	Labels map[string]string
	Value  float64
}

func NewMetricLine(labels map[string]string, value float64) *MetricLine {
	return &MetricLine{labels, value}
}

func (l MetricLine) GetLabels() map[string]string {
	return l.Labels
}

func (l MetricLine) HasLabelsWithValues(labels map[string]string) bool {
	if len(labels) == 0 {
		return true
	}
	for k, v := range labels {
		if val, ok := l.Labels[k]; !ok || v != val {
			return false
		}
	}
	return true
}

func (l MetricLine) HasAnyLabel(labels map[string]string) bool {
	if len(labels) == 0 {
		return false
	}
	for k := range labels {
		if _, ok := l.Labels[k]; ok {
			return true
		}
	}
	return false
}

func (l MetricLine) GetSortedLabelsKeys() []string {
	keys := make([]string, 0, len(l.Labels))
	for k := range l.Labels {
		keys = append(keys, k)
	}
	sort.Strings(keys)
	return keys
}

func (l MetricLine) String() string {
	labels := l.GetLabels()

	result := make([]string, 0, len(labels))
	for _, k := range l.GetSortedLabelsKeys() {
		result = append(result, fmt.Sprintf("%s:%s", k, labels[k]))
	}

	return strings.Join(result, ";")
}
