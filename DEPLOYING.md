# Deploying theme changes

How a change to `theme/ospira/` reaches the live site.

## The one thing to know first

**The live theme directory is a copy, not a git checkout.**

```
/var/www/<site>/theme/ospira    <- what Moodle actually reads
~/ospira-lms                    <- the clone, read by nothing
```

The live path is a plain directory owned by the web server user. It is not a
symlink to the clone, so `git pull` on its own deploys nothing. The `rsync`
step below is what actually publishes a change.

## Procedure

```bash
cd ~/ospira-lms
git fetch origin
git reset --hard origin/main

DEST=/var/www/<site>/theme/ospira/
sudo rsync -avn theme/ospira/ "$DEST"   # dry run first - review the list
sudo rsync -av  theme/ospira/ "$DEST"
sudo chown -R www-data:www-data "$DEST"

cd /var/www/<site>
sudo -u www-data php admin/cli/upgrade.php --non-interactive
sudo -u www-data php admin/cli/purge_caches.php
sudo systemctl reload php8.2-fpm
```

Bump `$plugin->version` in `theme/ospira/version.php` (format `YYYYMMDDXX`)
with every theme change, or Moodle will not register the update.

## Things that bite

**Always run Moodle CLI as the web server user.** Running it as root creates
root-owned files under `moodledata` that the web server cannot write, and the
site then fails with "Invalid permissions detected when trying to create a
directory".

```bash
sudo -u www-data php admin/cli/purge_caches.php    # correct
sudo php admin/cli/purge_caches.php                # breaks the site
```

**PHP opcache revalidates every 60 seconds,** so a deployed change can take a
minute to appear. The `reload php8.2-fpm` above makes it immediate.

**`rsync` without `--delete` does not remove files** that were deleted from
the repo. If a file is dropped upstream, remove it from the live directory by
hand. `--delete` is deliberately not used here because the live directory is
shared with the running site.

**Nothing in the Moodle database deploys via git.** Front page content, site
logo, favicon, site name and every admin setting live in the database, not in
this repo. A fresh install starts without them however correct the code is.
The brand assets in `deploy/brand/` are committed so a rebuild does not lose
them, but they still have to be uploaded through
Site administration -> Appearance -> Logos.

**History was rewritten on 2026-09-07.** Existing clones must use
`git fetch` + `git reset --hard origin/main`; `git pull` will report diverged
branches and fail to merge.

## Local development

`docker-compose.yml` runs Moodle and MariaDB locally with `theme/ospira`
bind-mounted, so edits appear without rebuilding. This is for development
only and is not how the live site runs.

```bash
docker compose up -d
docker compose logs -f moodle
docker compose exec --user daemon moodle php /bitnami/moodle/admin/cli/purge_caches.php
```

Moodle is served at `http://localhost:8080`. The same run-as-the-web-server
rule applies: `--user daemon` in the container, never root.

## Verifying a deploy

- The changed page renders as expected, logged in as a real account
- Dashboard stat tiles agree with the lists beneath them
- Other applications sharing the host still respond
