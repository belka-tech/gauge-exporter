test:
	go test -v ./...  && go build && cd ./e2e_tests && vendor/bin/phpunit