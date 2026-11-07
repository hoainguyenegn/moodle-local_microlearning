# Local integrated Moodle demo

This setup uses the existing Moodle checkout at `/Users/vothuy/Documents/moodle`
and mounts this plugin into `local/microlearning`. It creates separate Docker
volumes and a separate database; it does not use other running MySQL databases.
The web interface is bound to http://127.0.0.1:8088.

Configuration secrets live in ignored `dev/.env` and `engine/.env`.
The local administrator is `admin`; its generated password is
`MOODLE_ADMIN_PASSWORD` in `dev/.env`. Do not commit this file.

```sh
docker compose -f dev/compose.yaml up -d db web speech cron
```

To stop without deleting data:

```sh
docker compose -f dev/compose.yaml stop
```

The speech image must first be built using `docker build -t microlearning-speech:local engine`.
On a fresh machine build the web image too. This is a local demo, not a production
hosting setup. Model downloads require internet during initial provisioning.

The demo course has ID 2. Direct entry:
http://127.0.0.1:8088/local/microlearning/narration.php?courseid=2
A generated two-slide sample is available in the administrator's narration history.

The Apache configuration denies web access to `dev`, `engine`, and dotfiles.
Do not remove these restrictions or expose this development environment publicly.
`install.sh` is for a fresh database only; do not rerun it on the installed instance.
