# Contributing to Tech-Tians CRM

Thank you for contributing to the Tech-Tians CRM Portal. To ensure codebase quality, security, and smooth collaboration, all contributors must adhere to this guide.

---

## 1. Branch Strategy

We follow a GitFlow-inspired branching strategy:

```
  main (Production)
   ▲
   │ [PR after staging validation & QA approval]
  develop (Staging & Integration)
   ▲
   ├── feature/client-filter-enhancement
   ├── fix/csrf-token-expiration
   └── chore/update-dependencies
```

- **`main`**: Production-ready branch. Code here is deployed directly to production. Protected branch; no direct pushes.
- **`develop`**: Integration branch for upcoming releases. Deployed directly to the staging environment. Protected branch; no direct pushes.
- **`feature/*`**: Feature branches branched off `develop`. Used for new functionality (e.g. `feature/export-csv-filters`).
- **`fix/*`**: Bug fix branches branched off `develop`. Used for non-emergency bug fixes (e.g. `fix/mobile-regex-validation`).
- **`hotfix/*`**: Emergency patch branches branched off `main` for critical production hotfixes. Merged back into both `main` and `develop`.

---

## 2. Conventional Commits

All commit messages must follow the [Conventional Commits specification](https://www.conventionalcommits.org/):

```
<type>(<scope>): <short description in present tense>

[optional body explaining rationale]

[optional footer(s) like BREAKING CHANGE or Closes #123]
```

### Allowed Types
- **`feat`**: A new user-facing or API feature (e.g. `feat(client): add PAN number encryption at rest`)
- **`fix`**: A bug fix (e.g. `fix(auth): prevent session fixation on login`)
- **`docs`**: Documentation changes only (e.g. `docs(monitoring): add uptimerobot setup steps`)
- **`refactor`**: Code change that neither fixes a bug nor adds a feature (e.g. `refactor(database): extract query pagination into trait`)
- **`perf`**: Code change that improves performance (e.g. `perf(cache): cache dropdown lookups for 1 day`)
- **`test`**: Adding missing tests or correcting existing tests (e.g. `test(e2e): add playwright wizard validation spec`)
- **`security`**: Security hardening or vulnerability remediation (e.g. `security(headers): enforce strict CSP and HSTS`)
- **`chore`**: Maintenance, build tools, CI/CD, or dependency updates (e.g. `chore(ci): update github actions php version`)

### Examples
```text
feat(clients): add excel export with PAN masking for non-admin roles

fix(rate-limit): enforce IP and email combination on login throttle

security(storage): deny direct web execution in uploads folder via htaccess
```

---

## 3. Pull Request (PR) Rules & Workflow

1. **Create Branch**: Branch off latest `develop`:
   ```bash
   git checkout develop
   git pull origin develop
   git checkout -b feature/your-feature-name
   ```
2. **Local Quality Verification**: Run the full test suite and static analysis before pushing:
   ```bash
   composer test
   composer phpstan
   ```
3. **Open Pull Request**:
   - Target branch: `develop` (or `main` only for hotfixes).
   - Use a clear title following Conventional Commits format.
   - Describe what changed and provide manual verification steps.
4. **CI/CD Checks**:
   - All GitHub Actions automated checks must pass:
     - PHP syntax lint (`php -l`)
     - PHPStan Level 6 static analysis (0 errors)
     - PHPUnit unit & integration test suites
     - Playwright headless end-to-end tests
5. **Code Review & Approval**:
   - At least 1 peer approval is required before merge.
   - No direct pushes or force-pushes (`git push --force`) to `develop` or `main`.
   - Merge via **Squash and Merge** or **Rebase and Merge** to maintain a clean linear commit history.
