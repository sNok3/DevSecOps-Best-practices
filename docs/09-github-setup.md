# 9. GitHub repository set-up (applies the in-depth research)

Repository: `sNok3/DevSecOps-Best-practices` (**private**). Mistakes M1–M8 refer to the research document.

## 9.1 What is in the repository

| File | Control | Mistake |
|---|---|---|
| `.github/workflows/ci.yml` | Tests, `composer audit --locked --abandoned=fail`, `npm ci --ignore-scripts`, `npm audit`, build, Pint | M2, M8 |
| `.github/workflows/secret-scan.yml` | gitleaks over the full history (checksum-verified download) + canary test | M1 |
| `.github/workflows/sast.yml` | Semgrep (PHP, React, secrets rules); replaces CodeQL, which needs paid Code Security on private repos | M8 |
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

| Setting | Where | Value | Private repo, personal account |
|---|---|---|---|
| Dependabot alerts + security updates | Settings → Advanced Security | On | ✔ free |
| Dependency graph | Settings → Advanced Security | On | ✔ free |
| Secret scanning + push protection | Settings → Advanced Security | On | ✘ needs GitHub Secret Protection (paid); gitleaks covers it |
| CodeQL code scanning | Settings → Advanced Security | — | ✘ needs GitHub Code Security (paid); Semgrep covers it |
| Workflow permissions | Settings → Actions → General | Read repository contents; Actions may **not** approve PRs | ✔ |
| Allowed actions | Settings → Actions → General | Allow GitHub + selected actions; require full-length SHA pinning | ✔ |
| Fork PR workflows | Settings → Actions → General | Require approval for all outside collaborators | ✔ |
| Ruleset for `main` | Settings → Rules → Rulesets | Restrict deletions, block force pushes, require PR, require status checks `test`, `gitleaks`, `semgrep`, linear history | ✔ on GitHub Pro / Team / public; ✘ on GitHub Free private repos |
| Environment `security-audit` | Settings → Environments | Secret `SETTINGS_READ_TOKEN` (fine-grained PAT, this repo, Administration: read) | ✔ |
| 2FA | Account settings | On | ✔ |

> Note: on a *free* personal account, rulesets/branch protection and secret scanning are not available
> for private repositories. Students get GitHub Pro free through the GitHub Student Developer Pack, which
> enables rulesets on private repos. Otherwise, make the repository public for the demonstration: then
> secret scanning, push protection, CodeQL and rulesets are all free.

## 9.3 Validation (evidence log)

| # | Control | Test | Expected |
|---|---|---|---|
| V1 | gitleaks canary | Run `secret-scan` | Canary step "detected as expected", job green |
| V2 | gitleaks real leak | Branch with `.env` force-added (`git add -f .env`), open PR | `gitleaks` job fails, names file + rule |
| V3 | Dependency audit | Branch: pin a package to a version with a published advisory (GitHub Advisory Database) | `composer audit` step fails with the CVE id |
| V4 | Dependabot | Same branch merged in a test repo / alert view | Alert + security-update PR |
| V5 | SAST | Branch with `DB::select("select * from notes where id = ".$request->id)` | Semgrep reports SQL injection, job fails |
| V6 | Workflow injection | Branch adding `run: echo "${{ github.event.pull_request.title }}"` | zizmor `template-injection`, job fails |
| V7 | Ruleset | `git push origin main` directly / `git push --force` | Rejected: repository rule violation |
| V8 | Token permission | Job without extra permissions tries `gh release create` | HTTP 403 |
| V9 | Settings drift | Disable Dependabot security updates, run `settings-drift` | Job fails naming the setting; re-enable → green |
| V10 | Scorecard | Run `scorecard` before and after hardening | Score per check in the log |
