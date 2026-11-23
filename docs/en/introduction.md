---
title: Introduction
description: What Laravel Debugger is, who it is for, and what is in it.
---

Laravel Debugger is a log viewer and a set of ops pages for Laravel apps, in one place at `/debugger`. You can read logs, handle failed jobs, check the scheduler, and test mail or storage without opening a terminal on the server.

## Who it is for

For developers who look after a Laravel app that is already running. This is usually work done over SSH: opening `storage/logs`, running `queue:retry`, or checking whether cron still runs. Here it is all in the browser, with access you set yourself.

## What is in it

- [Overview](./overview.md): one list that shows which parts need a look.
- [Logs](./logs.md): read log files with level filters, search, and a readable stack trace.
- [Queue](./queue.md): failed jobs with their exception, with Retry and Delete.
- [Scheduler](./scheduler.md): scheduled tasks, when they run next, and a history of every run.
- [Cache](./cache.md): clear the application, config, route, and view caches.
- [Mail](./mail.md): send a test email through the queue or right away.
- [Storage](./storage.md): upload a test file and check that disks can be written.
- [Connections](./connections.md): ping the external APIs your app calls.
- [Users](./users.md): log in as another user to see the app from their side.

Every module can be turned off on its own. See [Configuration](./configuration.md).

## How it differs from Laravel Telescope

Laravel Debugger does not record requests, queries, or events. It reads what your app already has: log files, the failed jobs table, and config. It stores only two small things itself: a history of scheduler runs and an audit log of actions taken in the debugger. So there is no extra work on each request of your app. You can use both together.

## Next step

Continue to [Installation](./installation.md), then choose who may open the debugger in [Access control](./access-control.md).
