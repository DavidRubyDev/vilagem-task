# Single entry point for tests and QA. Every command runs in Docker.
# Compose runs containers as the host user; bash does not export UID and CI runners do not use uid 1000.
export UID := $(shell id -u)
export GID := $(shell id -g)

COMPOSE := docker compose
PHP := $(COMPOSE) run --rm --no-deps app
NODE := $(COMPOSE) run --rm --no-deps node
# API and E2E tests run against their own app (fixture data, no published port), so they never touch the app of `make up`.
TEST_COMPOSE := $(COMPOSE) -p parcel-map-test -f compose.yaml -f compose.test.yaml
ACTIONLINT_IMAGE := rhysd/actionlint:1.7.12
# The Claude Code hooks run on host python3; their tests run in this image.
PYTHON_IMAGE := python:3.14.10-slim
# The district whose cadastral areas `make coverage` writes to config/coverage.csv (Jičín).
DISTRICT := 3604

# The only list of check groups. CI builds its job matrix from it (make ci-targets).
CHECK_TARGETS := lint-ci test-hooks php-qa php-tests frontend-checks e2e

comma := ,
empty :=
space := $(empty) $(empty)

.PHONY: build data coverage perf install-php install-node assets watch up-app up down cs cs-fix phpstan deptrac format \
	test-unit test-integration test-api test-frontend test-e2e typecheck lint-frontend lint-ci test-hooks \
	php-qa php-tests check qa test ci-targets $(CHECK_TARGETS)

build:
	$(COMPOSE) build app

data: build install-php
	$(PHP) bin/console parcels:import

coverage: build
	$(PHP) bin/console coverage:generate --district=$(DISTRICT)

install-php: build
	$(PHP) composer install --no-interaction --no-progress

install-node:
	$(NODE) npm ci --no-audit --no-fund

assets: install-node
	$(NODE) npm run build

watch:
	$(NODE) npm run watch

up-app:
	$(TEST_COMPOSE) up -d --wait --no-deps app

up:
	$(COMPOSE) up

down:
	$(COMPOSE) down
	$(TEST_COMPOSE) down

cs:
	$(PHP) vendor/bin/php-cs-fixer check --diff

cs-fix:
	$(PHP) vendor/bin/php-cs-fixer fix

phpstan:
	$(PHP) vendor/bin/phpstan analyse --no-progress

deptrac:
	$(PHP) vendor/bin/deptrac analyse --no-progress --fail-on-uncovered --report-uncovered

test-unit:
	$(PHP) vendor/bin/phpunit --testsuite unit

test-integration:
	$(PHP) vendor/bin/phpunit --testsuite integration

test-api: up-app
	$(TEST_COMPOSE) run --rm --no-deps app vendor/bin/phpunit --testsuite api

typecheck:
	$(NODE) npm run typecheck

lint-frontend:
	$(NODE) npm run lint
	$(NODE) npm run format:check

format: cs-fix
	$(NODE) npm run format

test-frontend:
	$(NODE) npm test

test-e2e: up-app
	$(TEST_COMPOSE) run --rm --no-deps node npm run e2e

# Measures the running app (make up) with real data (make data) in a browser; not part of check.
perf:
	$(NODE) npm run perf

lint-ci:
	docker run --rm -u "$(UID):$(GID)" -v "$(CURDIR)":/repo -w /repo $(ACTIONLINT_IMAGE) -no-color .github/workflows/ci.yml

test-hooks:
	docker run --rm -u "$(UID):$(GID)" -v "$(CURDIR)":/repo -w /repo $(PYTHON_IMAGE) python -I -B -m unittest discover -s .claude/hooks/tests -v

php-qa: install-php cs phpstan deptrac

php-tests: install-php test-unit test-integration test-api

frontend-checks: install-node typecheck lint-frontend test-frontend

e2e: install-php assets up-app test-e2e

check: $(CHECK_TARGETS)

qa: cs phpstan deptrac lint-ci typecheck lint-frontend

test: test-hooks test-unit test-integration test-api test-frontend test-e2e

ci-targets:
	@echo '[$(subst $(space),$(comma),$(foreach t,$(CHECK_TARGETS),"$(t)"))]'
