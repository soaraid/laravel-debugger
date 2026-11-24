---
title: Security
description: What the debugger protects when you use it in production.
---

The debugger can read logs, run cache commands, and log in as other users. This page explains what guards it.

## Access

- Every page and every API call goes through the access check. See [Access control](./access-control.md).
- Without `Debugger::auth()` or the `viewDebugger` gate, the debugger only opens in the `local` environment.
- Actions that change something use the CSRF token from the app's session.
- `DEBUGGER_ENABLED=false` removes every debugger route.

> [!WARNING]
> `DEBUGGER_ENABLED` defaults to `true`. In production, the debugger stays closed as long as you don't define a gate that is too loose. Check your `viewDebugger` gate before you deploy.

## Read-only mode

In production, every action is off by default. See [Read-only mode](./read-only-mode.md).

## Logs

- Only files in `storage/logs` are read. A file is picked from the list, never from a path the browser sends, so `../` can't be used.
- Clear and Delete only reach files in that list, and are off in read-only mode.

## Storage

- Test files are stored with private visibility.
- Only files in the test folder can be opened or deleted.
- Files open with a type set from the extension, never from their content, and with the `Content-Security-Policy: sandbox` and `X-Content-Type-Options: nosniff` headers. An opened file can't run scripts on your app's domain.

## Passwords and tokens

- Mailer passwords, keys, and tokens are never shown. The Mail page shows only the mailer, host, port, and From address.
- API headers on the Connections page are never shown.
- Error messages from the mailer, disks, and APIs are filtered first. Values like `password=...`, `token=...`, `Bearer ...`, and connection URLs like `mysql://...` or `redis://...` are replaced with `***`.

## Impersonation

- You can't log in as yourself or nest impersonation.
- The `impersonate` gate limits who may do it, and `beImpersonated` limits who may be logged in as.
- The session is regenerated when you start and when you go back.
- The bar in the app always reminds you that you are using someone else's account.

## Audit log

Every action is written to the `debugger_audit_logs` table: who, which action, its target, the IP, and the time. What is recorded:

- Clear and Delete in Logs
- Retry, Retry all, and Delete in Queue
- Clear and Rebuild in Cache
- Test emails
- Upload and Delete in Storage
- Starting and stopping impersonation

The audit log is kept for `audit.retention_days` days (the default is 90) and cleaned up by `debugger:prune`.
