package main

import (
	"gauge-exporter/app"
	"gauge-exporter/storage"

	"github.com/alecthomas/kingpin/v2"
)

var (
	appVersion = "dev"
	listenAddr = kingpin.Flag("listen", "Http service address").Default("0.0.0.0:8181").String()
)

func main() {
	kingpin.Version(appVersion)
	kingpin.Parse()

	app.NewApp(
		storage.NewMetricsStorage(),
		*listenAddr,
		appVersion,
	).Run()
}
