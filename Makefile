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

# The only list of check groups. CI builds its job matrix from it (make ci-targets).
CHECK_TARGETS := lint-ci php-qa php-tests frontend-checks e2e

comma := ,
empty :=
space := $(empty) $(empty)

.PHONY: build data install-php install-node assets watch up-app up down cs cs-fix phpstan deptrac format \
	test-unit test-integration test-api test-frontend test-e2e typecheck lint-frontend lint-ci \
	php-qa php-tests check qa test ci-targets $(CHECK_TARGETS)

build:
	$(COMPOSE) build app

data: build
	$(PHP) bin/console parcels:import

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

lint-ci:
	docker run --rm -u "$(UID):$(GID)" -v "$(CURDIR)":/repo -w /repo $(ACTIONLINT_IMAGE) -no-color .github/workflows/ci.yml

php-qa: install-php cs phpstan deptrac

php-tests: install-php test-unit test-integration test-api

frontend-checks: install-node typecheck lint-frontend test-frontend

e2e: install-php assets up-app test-e2e

check: $(CHECK_TARGETS)

qa: cs phpstan deptrac lint-ci typecheck lint-frontend

test: test-unit test-integration test-api test-frontend test-e2e

ci-targets:
	@echo '[$(subst $(space),$(comma),$(foreach t,$(CHECK_TARGETS),"$(t)"))]'
