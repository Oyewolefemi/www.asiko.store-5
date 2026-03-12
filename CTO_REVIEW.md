# CTO Repository Review — www.asiko.store-5

Date: 2026-03-12
Reviewer: CTO-style technical and product review

## Executive Summary

This repository demonstrates strong execution velocity and clear product-market experimentation across multiple commerce surfaces (`kiosk`, `cakes`, `scrummy`, `asiko`, `mailing`, and `hq`). The team is shipping real features (checkout, admin management, inventory, mail events), but the platform currently carries **material security and operational risk** that should be addressed before scaling traffic or onboarding more tenants.

**Overall maturity assessment:**
- Product delivery: **B**
- Engineering scalability: **C-**
- Security posture: **D**
- Operational readiness: **C-**

## What is Working Well

1. **Cross-app architecture intent is good**
   - `hq/core.php` and `hq/app_configs.json` indicate a centralized app-switching/control-plane direction. This is the right long-term concept for a multi-brand commerce suite.

2. **Reasonable use of parameterized SQL in core user/admin flows**
   - Example files use prepared statements in login/register and admin CRUD style flows (`kiosk/auth.php`, `cakes/admin/product_form.php`).

3. **Defensive controls exist in key places**
   - CSRF tokens and password hashing helpers are present (`kiosk/functions.php`, `cakes/admin/product_form.php`).
   - File uploads in admin product forms include MIME and size checks (`cakes/admin/product_form.php`).

4. **Shared eventing idea for communications is promising**
   - The `fire_mail_event` abstraction in config enables decoupled event-triggered mail (`kiosk/config.php`) and can evolve into proper domain events.

## Critical Risks (Immediate)

### 1) Secrets are committed to source control (Critical)
- A private key is tracked in the repo (`hq/keys/private.pem`).
- Database credentials are hard-coded in app config JSON (`hq/app_configs.json`).

**Impact:** Immediate credential/key compromise risk, lateral movement risk, compliance failure risk.

**Actions (48 hours):**
- Rotate all impacted credentials/keys.
- Remove secrets from git history, not only current tree.
- Replace with environment-managed secret injection (vault/parameter store or deployment-time env vars).

### 2) Error details are exposed to users in runtime paths (High)
- Multiple files use `die()` with exception messages and enable `display_errors` in web code (`kiosk/functions.php`, `kiosk/config.php`, `asiko/dashboard.php`, `asiko/inventory.php`, etc.).

**Impact:** Information leakage of internals (DB schemas, paths, stack context), aiding attacker reconnaissance.

**Actions (1 week):**
- Enforce environment-aware error handling (`display_errors=Off` in production).
- Return generic user-safe messages; log detailed errors server-side only.

### 3) Large blast radius from monorepo + repeated code copies (High)
- `cakes` and `scrummy` are near-clones with drift (`diff -qr cakes scrummy` shows multiple divergent files).

**Impact:** Security fixes and behavior changes must be applied many times; defects and inconsistency become likely.

**Actions (2–4 weeks):**
- Extract shared commerce engine/components (auth, cart, admin forms, upload policies).
- Keep only brand/theming/config as app-specific overlays.

## Strategic Risks (Near-term)

1. **No visible CI quality gate in repository root**
   - No `.github/workflows` pipeline detected.
   - Current quality checks are manual/adhoc.

2. **Configuration strategy is mixed and inconsistent**
   - Some paths read `../.env`, some app-local `.env`, and some JSON-managed DB credentials.
   - This complicates deployment, debugging, and tenant isolation.

3. **Operational observability appears minimal**
   - There is no obvious centralized logging, metrics, tracing, or healthcheck discipline in repo structure.

## 30/60/90 Day CTO Plan

### First 30 days (stabilize + de-risk)
- Secret hygiene remediation (rotation + history cleanup + secret scanning in CI).
- Standardized production error handling and logging policy.
- Add baseline CI: PHP lint, unit tests where present, static checks, secret scan.
- Define secure configuration contract for all apps.

### Days 31–60 (platform consolidation)
- Create shared core package/module for duplicated functionality across `cakes`/`scrummy`/`kiosk`.
- Introduce a single auth/security policy package (CSRF/session/password/upload rules).
- Add migration/versioning for DB schema changes.

### Days 61–90 (scale readiness)
- Introduce SLOs + monitoring dashboards (error rate, latency, checkout conversion, mail dispatch health).
- Add release process (staging/prod promotion, rollback strategy).
- Begin tenancy hardening (data partitioning guarantees, key management, incident runbooks).

## Priority Backlog (Suggested)

P0:
- Remove + rotate exposed secrets.
- Disable production error display everywhere.
- Add secret scanning and block on CI.

P1:
- Consolidate duplicate apps into shared modules.
- Standardize config loading patterns.
- Add integration tests for checkout/order lifecycle.

P2:
- Improve observability and operations (alerts/runbooks).
- Begin performance profiling and caching strategy.

## Validation Performed for this Review

- Repository structure review and spot checks on key app entry/config/security files.
- Static syntax pass across PHP files (`php -l` over repository).
- Duplication assessment between major sibling apps (`diff -qr cakes scrummy`).
