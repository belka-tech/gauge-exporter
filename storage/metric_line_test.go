package storage_test

import (
	"gauge-exporter/storage"
	"testing"

	"github.com/stretchr/testify/assert"
)

func TestCorrectMetricLineSortedLabelsKeysReturned(t *testing.T) {
	// Arrange
	line := storage.NewMetricLine(map[string]string{"c": "d", "a": "b"}, 123.4)

	// Act
	keys := line.GetSortedLabelsKeys()

	// Assert
	assert.Equal(t, []string{"a", "c"}, keys)
}

func TestMetricLinePropertiesOrderDoesNotAffectSerialization(t *testing.T) {
	// Arrange
	line1 := storage.NewMetricLine(map[string]string{"a": "1", "b": "2", "c": "3"}, 123.4)
	line2 := storage.NewMetricLine(map[string]string{"c": "3", "a": "1", "b": "2"}, 123.4)

	// Act
	line1Str := line1.String()
	line2Str := line2.String()

	// Assert
	assert.Equal(t, line1Str, line2Str)
	assert.Equal(t, "a:1;b:2;c:3", line1Str)
}

func TestMetricLineProperlyConvertedToString(t *testing.T) {
	testCases := []struct {
		labels   map[string]string
		expected string
	}{
		{labels: map[string]string{"a": "1", "c": "3", "b": "2"}, expected: "a:1;b:2;c:3"},
		{labels: map[string]string{"c": "1", "a": "2", "b": "3"}, expected: "a:2;b:3;c:1"},
	}
	for _, tc := range testCases {
		// Arrange
		line := storage.NewMetricLine(tc.labels, 123.4)

		// Act
		lineStr := line.String()

		// Assert
		assert.Equal(t, tc.expected, lineStr)
	}
}

func TestMetricLineHasLabelsWithValuesWorksCorrectly(t *testing.T) {
	testCases := []struct {
		primary        map[string]string
		secondary      map[string]string
		expectedResult bool
	}{
		{
			primary:        map[string]string{"l1": "v1", "l2": "v2"},
			secondary:      map[string]string{},
			expectedResult: true,
		},
		{
			primary:        map[string]string{"l1": "v1", "l2": "v2"},
			secondary:      map[string]string{"l1": "v1"},
			expectedResult: true,
		},
		{
			primary:        map[string]string{"l2": "v2", "l1": "v1", "l3": "v3"},
			secondary:      map[string]string{"l3": "v3", "l1": "v1"},
			expectedResult: true,
		},
		{
			primary:        map[string]string{"l1": "v1", "l2": "v2"},
			secondary:      map[string]string{"l1": "v1", "l2": "v2"},
			expectedResult: true,
		},
		{
			primary:        map[string]string{"l1": "v1", "l2": "v2"},
			secondary:      map[string]string{"l1": "v1", "l2": "v2", "l3": "v3"},
			expectedResult: false,
		},
	}
	for _, tc := range testCases {
		// Arrange
		line := storage.NewMetricLine(tc.primary, 123.4)

		// Act
		result := line.HasLabelsWithValues(tc.secondary)

		// Assert
		assert.Equal(t, tc.expectedResult, result)
	}
}

func TestMetricLineHasAnyLabelWorksCorrectly(t *testing.T) {
	testCases := []struct {
		primary        map[string]string
		secondary      map[string]string
		expectedResult bool
	}{
		{
			primary:        map[string]string{"l1": "v1", "l2": "v2"},
			secondary:      map[string]string{},
			expectedResult: false,
		},
		{
			primary:        map[string]string{"l1": "v1", "l2": "v2"},
			secondary:      map[string]string{"l3": "v3", "l4": "v4"},
			expectedResult: false,
		},
		{
			primary:        map[string]string{"l1": "v1", "l2": "v2"},
			secondary:      map[string]string{"l2": "v3"},
			expectedResult: true,
		},
		{
			primary:        map[string]string{"l1": "v1", "l2": "v2"},
			secondary:      map[string]string{"l2": "v2"},
			expectedResult: true,
		},
	}
	for _, tc := range testCases {
		// Arrange
		line := storage.NewMetricLine(tc.primary, 123.4)

		// Act
		result := line.HasAnyLabel(tc.secondary)

		// Assert
		assert.Equal(t, tc.expectedResult, result)
	}
}
