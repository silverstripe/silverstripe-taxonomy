---
title: Testing
summary: Run the PHP, Jest and Playwright tests of the module
icon: vial
---

# Testing

The module has three layers of tests. The first two share recorded API responses, so a change to the API contract fails both. The tests are few on purpose. Each one covers the contract or a security rule.

| Layer | Location | What it covers |
| ----- | -------- | -------------- |
| PHP API and model tests | `tests/php/` | The permission matrix, one test for each error status, the checks on fields and parents, `PATCH` with `Sort`, the list of the terms of a type, and the recorded fixtures |
| Jest, React Testing Library and MSW | `client/src/**/tests/` | The behaviour of each screen against the recorded responses |
| Playwright, in a real browser | `tests/playwright/` | A reorder with the keyboard, and creating a type, a term and a child term |

Run them with these commands.

```bash
vendor/bin/phpunit vendor/silverstripe/taxonomy/tests/php
yarn test
yarn test:e2e
```

## Recorded fixtures

The PHP API tests write the normalised JSON responses to `client/src/tests/fixtures/`. The Jest tests serve those files through MSW. Without the environment variable, the PHP tests assert that the committed files still match the responses. To write the files again after a deliberate change to the API, run the PHP tests with `TAXONOMY_RECORD_FIXTURES=1`.

```bash
TAXONOMY_RECORD_FIXTURES=1 vendor/bin/phpunit vendor/silverstripe/taxonomy/tests/php
```

## Playwright tests

The Playwright tests need the built bundle in `client/dist/`, so run `yarn build` first. They use Chromium only, and need these environment variables:

| Variable | Default | Purpose |
| -------- | ------- | ------- |
| `SS_BASE_URL` | `http://bugfix31.test` | The URL of the site that runs the tests |
| `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` | `/chrome-linux64/chrome` | A Chrome for Testing binary. Playwright does not download a browser |

```bash
yarn build
yarn test:e2e
```

The tests use [`silverstripe/testsession`](https://github.com/silverstripe/silverstripe-testsession) to run against a temporary database, so they never touch the data of the site. The data comes from `tests/playwright/fixtures/taxonomy.yml`, which creates an administrator and an editor with the password `Taxonomy-e2e-1!`. A site with the `silverstripe/mfa` module shows a registration screen after login, and the tests choose "Setup later".

The test session is a global session, which means that it applies to every visitor of the site while the tests run. Run the tests only against a development site that nobody else uses at that time. The tests start the session before the first test and end it after the last one.

> [!WARNING]
> `silverstripe/testsession` loads a fixture only from a path that starts with one folder and then `tests/`. The set-up step of the tests links the fixtures folder into the site root as `taxonomy-e2e/`, and the tear-down step removes the link, the state file `TESTS_RUNNING.json` and the leftover temporary database. If a run stops before the tear-down, delete `taxonomy-e2e/` and `TESTS_RUNNING.json` from the site root by hand.

When `silverstripe/testsession` is installed, the module adds `KeepTestDatabaseExtension` to `TestSessionEnvironment`. A test session creates its database in one request and uses it in the next requests, but the framework drops a temporary database when the request that created it ends. The extension turns that off only for a start request that has the parameter `taxonomyKeepTestDatabase=1`, which the Playwright set-up sends. Other test sessions, and PHPUnit runs, still drop their databases at the end of the request.
