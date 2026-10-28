# 9. GitHub repository set-up (applies the in-depth research)

Repository: `sNok3/DevSecOps-Best-practices` (**public**, so every GitHub security feature is free). Mistakes M1–M8 refer to the research document.

## 9.1 What is in the repository

| File | Control | Mistake |
|---|---|---|
| `.github/workflows/ci.yml` | Tests, `composer audit --locked --abandoned=fail`, `npm ci --ignore-scripts`, `npm audit`, build, Pint | M2, M8 |
| `.github/workflows/secret-scan.yml` | gitleaks over the full history (checksum-verified download) + canary test | M1 |
| `.github/workflows/codeql.yml` | CodeQL `security-extended` for GitHub Actions workflows and JavaScript/React | M5, M8 |
| `.github/workflows/dependency-review.yml` | Fails a PR that adds a vulnerable (moderate+) or GPL/AGPL dependency | M2 |
| `.github/workflows/sast.yml` | Semgrep (PHP, React, secrets rules): CodeQL has no PHP support | M8 |
| `.github/workflows/workflow-lint.yml` | zizmor + actionlint on workflow changes | M4, M5 |
| `.github/workflows/scorecard.yml` | OpenSSF Scorecard (results in the job log) | all |
| `.github/workflows/settings-drift.yml` | Weekly REST API check that the settings below are still on | M3, M6 |
| `.github/dependabot.yml` | Weekly updates for Composer, npm **and GitHub Actions** | M2, M4 |
| `.github/CODEOWNERS` | Owner review for every path | M6 |
| `.gitleaks.toml`, `.pre-commit-config.yaml` | Local secret scan before a commit exists | M1 |
| `SECURITY.md` | How to report a vulnerability | M8 |
| `.gitignore`, `.env.example`, `.npmrc` | `.env` never committed; secure defaults; npm install scripts disabled, audit on | M1, M2 |

Every workflow has `permissions: contents: read` (Scorecard: `read-all`), uses `persist-credentials: false`,
pins every action to a full commit SHA with the version as a comment, and has a `timeout-minutes`.

## 9.2 Settings to switch on (web interface)

| Setting | Where | Value | Public repo |
|---|---|---|---|
| Dependabot alerts + security updates | Settings → Advanced Security | On | ✔ |
| Private vulnerability reporting | Settings → Advanced Security | On | ✔ |
| Dependency graph | Settings → Advanced Security | On | ✔ |
| Secret scanning + push protection | Settings → Advanced Security | On | ✔ free on public repos |
| CodeQL code scanning | Settings → Advanced Security | Advanced set-up (the workflow file); do **not** also enable Default set-up | ✔ free on public repos |
| Workflow permissions | Settings → Actions → General | Read repository contents; Actions may **not** approve PRs | ✔ |
| Allowed actions | Settings → Actions → General | Allow GitHub + selected actions; require full-length SHA pinning | ✔ |
| Fork PR workflows | Settings → Actions → General | Require approval for all outside collaborators | ✔ |
| Ruleset for `main` | Settings → Rules → Rulesets | Restrict deletions, block force pushes, require PR, require status checks `test`, `gitleaks`, `semgrep`, `analyze (actions)`, `analyze (javascript-typescript)`, `dependency-review`, linear history | ✔ |
| Environment `security-audit` | Settings → Environments | Secret `SETTINGS_READ_TOKEN` (fine-grained PAT, this repo, Administration: read) | ✔ |
| 2FA | Account settings | On | ✔ |

> Making the repository public is safe only because no secret was ever committed: run the gitleaks
> workflow (or `gitleaks git .` locally) over the full history **before** switching the visibility.

## 9.3 Validation (evidence log)

| # | Control | Test | Expected |
|---|---|---|---|
| V1 | gitleaks canary | Run `secret-scan` | Canary step "detected as expected", job green |
| V2 | gitleaks real leak | Branch with `.env` force-added (`git add -f .env`), open PR | `gitleaks` job fails, names file + rule |
| V3 | Dependency audit | Branch: pin a package to a version with a published advisory (GitHub Advisory Database) | `composer audit` step fails with the CVE id |
| V4 | Dependency review | Same branch as a PR | `dependency-review` fails and lists the advisory |
| V4b | Dependabot | After merging into a throw-away branch / the alert view | Alert + security-update PR |
| V4c | Push protection | Push a fine-grained PAT (no permissions, 1-day expiry) to a branch, then revoke it | Push rejected: "Push cannot contain secrets" |
| V5b | CodeQL | PR with `element.innerHTML = location.hash` in a React file | CodeQL alert on the PR, `analyze` check fails |
| V5 | SAST | Branch with `DB::select("select * from notes where id = ".$request->id)` | Semgrep reports SQL injection, job fails |
| V6 | Workflow injection | Branch adding `run: echo "${{ github.event.pull_request.title }}"` | zizmor `template-injection`, job fails |
| V7 | Ruleset | `git push origin main` directly / `git push --force` | Rejected: repository rule violation |
| V8 | Token permission | Job without extra permissions tries `gh release create` | HTTP 403 |
| V9 | Settings drift | Disable Dependabot security updates, run `settings-drift` | Job fails naming the setting; re-enable → green |
| V10 | Scorecard | Run `scorecard` before and after hardening | Score per check in the log |
