# How auto-deploy works

Every push to `main` on this repo triggers `.github/workflows/deploy.yml`, which:

1. SSHes into the droplet using the `DROPLET_SSH_KEY` repo secret
2. Runs `git pull` in `/opt/portal`
3. Reloads nginx and PHP-FPM
4. Logs the timestamp

If a deploy ever fails, check the run at:
**https://github.com/markamplifycancom/amplifycan-portal/actions**

## Required GitHub repo secrets

| Secret | Purpose |
|---|---|
| `DROPLET_HOST` | Public IP of the droplet (e.g., `203.0.113.42`) |
| `DROPLET_USER` | SSH user — `root` works for v1 |
| `DROPLET_SSH_KEY` | Private SSH key in PEM format. The corresponding public key must be in the droplet's `~/.ssh/authorized_keys`. |

Set these at:
**https://github.com/markamplifycancom/amplifycan-portal/settings/secrets/actions**

## Triggering a deploy without code changes

Go to **Actions** tab → "Deploy to Droplet" → "Run workflow" → main → Run.
