package app

import (
	"bytes"
	"encoding/json"
	"gauge-exporter/storage"
	"io"
	"maps"
	"net/http"
	"strings"
	"time"

	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/promhttp"
)

type App struct {
	storage       *storage.MetricsStorage
	listenAddr    string
	version       string
	metricsTotal  prometheus.Gauge
	requestsCount *prometheus.CounterVec
}

func NewApp(storage *storage.MetricsStorage, listenAddr string, version string) *App {
	return &App{
		storage:    storage,
		listenAddr: listenAddr,
		version:    version,
		metricsTotal: prometheus.NewGauge(
			prometheus.GaugeOpts{Name: "gauge_exporter_metric_lines_total"},
		),
		requestsCount: prometheus.NewCounterVec(
			prometheus.CounterOpts{Name: "gauge_exporter_metrics_requests_total"},
			[]string{"status"},
		),
	}
}

func (app *App) Run() {
	mux := http.NewServeMux()
	mux.HandleFunc("/metrics", app.outputPrometheusHandler)
	mux.HandleFunc("/gauge/", app.inputMetricsHandler)
	mux.HandleFunc("/version", app.versionHandler)

	server := &http.Server{
		Addr:              app.listenAddr,
		Handler:           mux,
		ReadHeaderTimeout: 3 * time.Second,
		WriteTimeout:      10 * time.Second,
		IdleTimeout:       120 * time.Second,
	}

	err := server.ListenAndServe()
	if err != nil {
		panic(err)
	}
}

func (app *App) outputPrometheusHandler(w http.ResponseWriter, r *http.Request) {
	promRegistry := prometheus.NewRegistry()

	for _, metricName := range app.storage.GetAllMetricsNames() {
		for _, line := range app.storage.GetMetricLines(metricName) {
			if app.storage.IsExpired(metricName) {
				app.storage.Delete(metricName)
				continue
			}

			normalizedName := strings.ReplaceAll(metricName, ".", "_")

			m := prometheus.NewGaugeVec(prometheus.GaugeOpts{Name: normalizedName}, line.GetSortedLabelsKeys())
			m.With(line.GetLabels()).Set(line.Value)

			_ = promRegistry.Register(uncheckedCollector{m})
		}
	}

	app.metricsTotal.Set(float64(app.getTotalStorageLines()))

	_ = promRegistry.Register(app.metricsTotal)
	_ = promRegistry.Register(app.requestsCount)

	promhttp.HandlerFor(promRegistry, promhttp.HandlerOpts{}).ServeHTTP(w, r)
}

func (app *App) inputMetricsHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPut {
		w.WriteHeader(http.StatusMethodNotAllowed)
		app.incFailedRequestsCount()
		return
	}

	parts := strings.Split(r.URL.Path, "/")
	if len(parts) < 3 {
		http.Error(w, "Invalid URL", http.StatusBadRequest)
		app.incFailedRequestsCount()
		return
	}
	metricName := parts[2]
	if len(metricName) == 0 {
		http.Error(w, "Invalid Metric Name", http.StatusBadRequest)
		app.incFailedRequestsCount()
		return
	}

	var buf bytes.Buffer
	_, err := io.Copy(&buf, r.Body)
	if err != nil {
		http.Error(w, "Error reading request body", http.StatusInternalServerError)
		app.incFailedRequestsCount()
		return
	}

	var data inputDto
	if err := json.Unmarshal(buf.Bytes(), &data); err != nil {
		http.Error(w, "Error parsing JSON", http.StatusBadRequest)
		app.incFailedRequestsCount()
		return
	}

	if data.TTL == 0 || data.Data == nil {
		http.Error(w, "Incorrect input format", http.StatusBadRequest)
		app.incFailedRequestsCount()
		return
	}

	metricLines := make([]*storage.MetricLine, 0, len(data.Data))
	for _, v := range data.Data {
		line := storage.NewMetricLine(v.Labels, v.Value)
		if line.HasAnyLabel(data.SystemLabels) {
			http.Error(w, "Metric labels must not contain any of system_labels", http.StatusBadRequest)
			app.incFailedRequestsCount()
			return
		}

		maps.Copy(v.Labels, data.SystemLabels)

		metricLines = append(metricLines, line)
	}

	app.storage.UpdateMetricLines(metricName, data.SystemLabels, metricLines, time.Duration(data.TTL)*time.Second)
	app.incSuccessfulRequestsCount()
}

func (app *App) versionHandler(w http.ResponseWriter, _ *http.Request) {
	_, _ = w.Write([]byte(app.version))
}

func (app *App) getTotalStorageLines() int {
	total := 0
	for _, metricName := range app.storage.GetAllMetricsNames() {
		total += len(app.storage.GetMetricLines(metricName))
	}

	return total
}

func (app *App) incFailedRequestsCount() {
	app.requestsCount.WithLabelValues("failed").Inc()
}

func (app *App) incSuccessfulRequestsCount() {
	app.requestsCount.WithLabelValues("success").Inc()
}
