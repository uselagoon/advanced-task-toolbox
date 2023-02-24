.PHONY: test-build-and-push
test-build-and-push:
	docker build --no-cache . -t bomoko/remk8up:latest --no-cache
	docker push bomoko/remk8up:latest



.PHONY: build-and-push
build-and-push:
	docker build . -t amazeeio/advanced-task-toolbox:latest
	docker push amazeeio/advanced-task-toolbox:latest
