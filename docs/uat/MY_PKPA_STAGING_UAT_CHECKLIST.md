# MY PKPA Staging UAT Checklist

Status: ready to prepare staging Human UAT with conditions.

Environment:
- [ ] `APP_ENV=staging`
- [ ] `APP_DEBUG=false`
- [ ] HTTPS `APP_URL`
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `SESSION_SAME_SITE=lax`
- [ ] trusted proxy configured
- [ ] storage writable and private
- [ ] public academic files blocked

Core:
- [ ] Core staging/testing URL configured
- [ ] app access role mapping verified
- [ ] `E2E_*` real Core test accounts provided
- [ ] inactive and no-app-access accounts available for negative UAT

Operations:
- [ ] queue worker running
- [ ] scheduler cron running
- [ ] health endpoint monitored
- [ ] failed jobs monitored
- [ ] log rotation enabled
- [ ] DB backup available
- [ ] restore rehearsal approved
- [ ] mail sandbox active

Security:
- [ ] no secrets in repository
- [ ] rate limit enabled
- [ ] cookies secure on HTTPS
- [ ] `APP_DEBUG=false`
- [ ] file downloads via protected controller

Human UAT:
- [ ] participants assigned
- [ ] scenarios distributed
- [ ] evidence folder prepared
- [ ] sign-off template prepared
