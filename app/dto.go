package app

type labelsValuesDto struct {
	Labels map[string]string `json:"labels"`
	Value  float64           `json:"value"`
}

type inputDto struct {
	TTL          int               `json:"ttl"`
	Data         []labelsValuesDto `json:"data"`
	SystemLabels map[string]string `json:"system_labels"`
}
