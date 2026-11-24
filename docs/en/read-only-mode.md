---
title: Read-only mode
description: When read-only mode is on, which actions it turns off, and how to set it.
---

Read-only mode turns off every action that changes something, while every page stays readable. It is the safe way to use the debugger in production.

## When it is on

Set it with `DEBUGGER_READ_ONLY`:

```dotenv title=".env"
# Empty (default): on in production, off in every other environment
DEBUGGER_READ_ONLY=

# Always on
DEBUGGER_READ_ONLY=true

# Off, even in production
DEBUGGER_READ_ONLY=false
```

While it is on, the debugger shows "Read-only: actions are turned off in this environment." at the top of the page, and the Overview shows the mode as `read-only`.

## Actions it turns off

- Logs: Clear, Delete
- Queue: Retry, Retry all, Delete
- Cache: Clear, Rebuild
- Mail: Send test email
- Storage: upload, Delete, Check disks
- Connections: Ping, Ping all
- Users: Log in as

The buttons show but can't be pressed. If an action is called straight through the API, it answers 403.

## What still works

- Every page and list.
- Opening test files in Storage.
- Going back from impersonation, so nobody gets stuck in someone else's account.

## Turning it off for a while in production

Say you need to retry a failed job in production:

1. Set `DEBUGGER_READ_ONLY=false` in `.env`.
2. Run `php artisan config:clear`, or `php artisan config:cache` if your config is cached.
3. Do the action.
4. Set `DEBUGGER_READ_ONLY` back and repeat step 2.

> [!WARNING]
> While read-only mode is off, everyone who can open the debugger can run actions, Log in as included. Turn it back on when you are done.
