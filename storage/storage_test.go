package storage_test

import (
	"encoding/json"
	"gauge-exporter/storage"
	"testing"
	"time"

	"github.com/stretchr/testify/assert"
)

func TestCanCreateNewMetricsStorage(t *testing.T) {
	// Act
	s := storage.NewMetricsStorage()

	// Assert
	assert.NotNil(t, s)
}

func TestNewMetricLinesAreAdded(t *testing.T) {
	// Arrange
	s := storage.NewMetricsStorage()

	// Act
	line1 := storage.NewMetricLine(map[string]string{"a": "b"}, 123.4)
	s.UpdateMetricLines("metric-name-1", map[string]string{}, []*storage.MetricLine{line1}, time.Second)
	line2 := storage.NewMetricLine(map[string]string{"a": "b"}, 100)
	s.UpdateMetricLines("metric-name-2", map[string]string{}, []*storage.MetricLine{line2}, time.Second)

	// Assert
	expected := `{
		"metric-name-1":{"a:b":{"Labels":{"a":"b"},"Value":123.4}},
		"metric-name-2":{"a:b":{"Labels":{"a":"b"},"Value":100}}
	}`
	assert.JSONEq(t, expected, storageToJSON(s))
}

func TestAllExistingMetricLinesAreZeroedWhenMetricUpdated(t *testing.T) {
	// Arrange
	s := storage.NewMetricsStorage()

	line := storage.NewMetricLine(map[string]string{"a": "b"}, 123.4)
	s.UpdateMetricLines("metric-name-1", map[string]string{}, []*storage.MetricLine{line}, time.Second)
	line = storage.NewMetricLine(map[string]string{"c": "d"}, 100)
	s.UpdateMetricLines("metric-name-2", map[string]string{}, []*storage.MetricLine{line}, time.Second)
	line = storage.NewMetricLine(map[string]string{"f": "g", "c": "d"}, 100)
	s.UpdateMetricLines("metric-name-2", map[string]string{}, []*storage.MetricLine{line}, time.Second)

	// Act
	line = storage.NewMetricLine(map[string]string{"c": "d"}, 150)
	s.UpdateMetricLines("metric-name-2", map[string]string{}, []*storage.MetricLine{line}, time.Second)

	// Assert
	expected := `{
		"metric-name-1":{"a:b":{"Labels":{"a":"b"},"Value":123.4}},
		"metric-name-2":{
			"c:d":{"Labels":{"c":"d"},"Value":150},
			"c:d;f:g":{"Labels":{"c":"d","f":"g"},"Value":0}
		}
	}`
	assert.JSONEq(t, expected, storageToJSON(s))
}

func TestMetricLinesWithSameLabelsButDifferentOrderTreatedAsSame(t *testing.T) {
	// Arrange
	metricName := "metric-name"
	s := storage.NewMetricsStorage()

	// Act
	line := storage.NewMetricLine(map[string]string{"a": "b", "c": "d"}, 123.4)
	s.UpdateMetricLines(metricName, map[string]string{}, []*storage.MetricLine{line}, time.Second)
	line = storage.NewMetricLine(map[string]string{"c": "d", "a": "b"}, 100)
	s.UpdateMetricLines(metricName, map[string]string{}, []*storage.MetricLine{line}, time.Second)

	// Assert
	expected := `{
		"metric-name":{"a:b;c:d":{"Labels":{"a":"b","c":"d"},"Value":100}}
	}`
	assert.JSONEq(t, expected, storageToJSON(s))
}

func TestMetricExpiresCorrectly(t *testing.T) {
	// Arrange
	s := storage.NewMetricsStorage()

	line := storage.NewMetricLine(map[string]string{"a": "b", "c": "d"}, 123.4)
	s.UpdateMetricLines("metric-name-1", map[string]string{}, []*storage.MetricLine{line}, time.Second)
	line = storage.NewMetricLine(map[string]string{"a": "b", "c": "d", "f": "g"}, 123.4)
	s.UpdateMetricLines("metric-name-2", map[string]string{}, []*storage.MetricLine{line}, 2*time.Second)

	// Act
	time.Sleep(time.Second)

	// Assert
	assert.True(t, s.IsExpired("metric-name-1"))
	assert.False(t, s.IsExpired("metric-name-2"))
}

func TestMetricIsDeleted(t *testing.T) {
	// Arrange
	s := storage.NewMetricsStorage()

	line := storage.NewMetricLine(map[string]string{"a": "b", "c": "d"}, 123.4)
	s.UpdateMetricLines("metric-name-1", map[string]string{}, []*storage.MetricLine{line}, time.Second)
	line = storage.NewMetricLine(map[string]string{"a": "b", "c": "d", "f": "g"}, 123.4)
	s.UpdateMetricLines("metric-name-2", map[string]string{}, []*storage.MetricLine{line}, 2*time.Second)

	// Act
	s.Delete("metric-name-1")

	// Assert
	expected := `{
		"metric-name-2":{"a:b;c:d;f:g":{"Labels":{"a":"b","c":"d","f":"g"},"Value":123.4}}
	}`
	assert.JSONEq(t, expected, storageToJSON(s))
}

func storageToJSON(st *storage.MetricsStorage) string {
	m := make(map[string]map[string]*storage.MetricLine)

	for _, metricName := range st.GetAllMetricsNames() {
		for _, metricLine := range st.GetMetricLines(metricName) {
			if m[metricName] == nil {
				m[metricName] = make(map[string]*storage.MetricLine)
			}
			m[metricName][metricLine.String()] = metricLine
		}
	}

	serialized, _ := json.Marshal(m)
	return string(serialized)
}
