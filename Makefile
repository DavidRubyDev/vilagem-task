# Single entry point for tests and QA. Every command runs in Docker.
# Compose runs containers as the host user; bash does not export UID and CI runners do not use uid 1000.
export UID := $(shell id -u)
export GID := $(shell id -g)

COMPOSE := docker compose
PHP := $(COMPOSE) run --rm --no-deps app
ACTIONLINT_IMAGE := rhysd/actionlint:1.7.12

# The only list of check groups. CI builds its job matrix from it (make ci-targets).
CHECK_TARGETS := lint-ci php-qa php-tests

comma := ,
empty :=
space := $(empty) $(empty)

.PHONY: build install-php up-app up down cs cs-fix phpstan deptrac test-unit test-api lint-ci \
	php-qa php-tests check qa test ci-targets $(CHECK_TARGETS)

build:
	$(COMPOSE) build app

install-php: build
	$(PHP) composer install --no-interaction --no-progress

up-app:
	$(COMPOSE) up -d --wait --no-deps app

up:
	$(COMPOSE) up

down:
	$(COMPOSE) down

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

test-api: up-app
	$(PHP) vendor/bin/phpunit --testsuite api

lint-ci:
	docker run --rm -u "$(UID):$(GID)" -v "$(CURDIR)":/repo -w /repo $(ACTIONLINT_IMAGE) -no-color .github/workflows/ci.yml

php-qa: install-php cs phpstan deptrac

php-tests: install-php test-unit test-api

check: $(CHECK_TARGETS)

qa: cs phpstan deptrac lint-ci

test: test-unit test-api

ci-targets:
	@echo '[$(subst $(space),$(comma),$(foreach t,$(CHECK_TARGETS),"$(t)"))]'
