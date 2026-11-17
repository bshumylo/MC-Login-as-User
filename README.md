# Login as User

**Lets an EspoCRM administrator log in as another user, for example to check what that user sees and to debug access rights.**

English · [Українська](README.uk.md)

Version **1.0.2** · EspoCRM **≥ 10.0.0** · PHP **≥ 8.3** · AGPL-3.0

## What it does

- **One click.** Open the record of a user and choose **Login as User** in the actions menu. You see EspoCRM exactly as that user sees it.
- **Easy to return.** While you act as another user, an *Acting as …* banner with a **Return to My Account** button stays on screen. The **Log Out** item in the user menu also becomes **Return to My Account**.
- **Good for ACL checks.** Test roles, teams and field access without asking for a password.
- **Clear records.** What you do is saved as that user (created by, modified by, stream). Action History records keep the link to your own login.
- **A trace in the log.** The start and the manual end of every session (who, as whom, IP address) are written to the EspoCRM log.
- **Time limit.** A session ends by itself after one hour (you can change this).
- **Three languages.** English, Ukrainian and Czech.

## Requirements

- EspoCRM 10.0.0 or newer.
- PHP 8.3 – 8.5.

## Installation

1. Download `mc-login-as-user-1.0.2.zip` from [Releases](../../releases/latest). Do not use the automatic "Source code (zip)" file: it cannot be installed.
2. In EspoCRM open **Administration → Extensions → Upload**, choose the ZIP, then click **Install**. The installer rebuilds the system itself. Run **Administration → Rebuild** only if something looks wrong.
3. No setup is needed. Open **Administration → Users**, choose an active regular user and use **Login as User** in the actions menu (top right of the record). Only administrators see this item.

## Upgrading

1. Make a backup of the database and files.
2. Install the new ZIP over the old one (**Administration → Extensions → Upload → Install**).
3. Version notes:
   - From versions before 1.0: the old `login-as-user-id` cookie is removed automatically and is no longer used.
   - **1.0.2**: only active regular users can be chosen now. See Security.
   - Releases 1.0.1 and older use tags without the `v` prefix.

## Settings

There is no admin page. One optional parameter goes into `data/config.php`:

| Parameter | Default | What it does |
|---|---|---|
| `loginAsUserTtl` | `3600` (one hour) | How long a session lasts, in seconds. Allowed range: 60 to 28800; a value outside the range is adjusted to the nearest limit. |

Example: `'loginAsUserTtl' => 1800,`

To end a session earlier, click *Return to My Account* or log out.

## Security

- **Administrators only, regular users only.** You can act only as an active regular user, the same rule as the built-in EspoCRM feature. You cannot act as yourself, as an administrator or super-admin, or as a portal, API, system or inactive user. API users cannot start a session. The rules are checked when the session starts and on every request.
- **A signed ticket, not a plain cookie.** The start request creates an **HttpOnly** cookie signed with the `cryptKey` of your instance. It names the administrator, the target user, the auth token of the current session and an expiry. It cannot be forged, reused after logout or in another session, or read by scripts. Invalid tickets are ignored.
- **Time-limited.** One hour by default (see Settings).
- **Logged.** Start and end are written to the log at the `warning` level, so the lines are kept with default log settings.
- Use it only with the knowledge of your team, and only when you need it. Everything you do is recorded as the other user.

To report a vulnerability, see [SECURITY.md](SECURITY.md).

## For developers

**Build the ZIP locally.** The extension uses the official template.

```bash
npm ci
node build --extension   # creates build/mc-login-as-user-<version>.zip
```

**Releases.** Raise the version in `extension.json` and `package.json` and merge it into `main`. The workflow [.github/workflows/release.yml](.github/workflows/release.yml) creates the release `vX.Y.Z` with `mc-login-as-user-X.Y.Z.zip` automatically. The same workflow is used in all MC extension repositories. See [CHANGELOG.md](CHANGELOG.md) for the history.

**Technical notes.** The start and stop endpoints are `POST /api/v1/LoginAsUser/start` and `POST /api/v1/LoginAsUser/stop`. The start request is accepted only with the auth token of the current session. The code is in `src/files/custom/Espo/Modules/LoginAsUser/`; unit tests are in `tests/unit/` (`npm run unit-tests`).

Originally created by Apertia Tech s.r.o. This repository continues it on the official template.

## Author and license

Developed by [bshumylo](https://github.com/bshumylo). Built on the official [EspoCRM extension template](https://github.com/espocrm/ext-template).

Licensed under the [GNU AGPL v3](LICENSE). Use it at your own risk and test new versions on a copy of your CRM first.

Found a bug or have an idea? Open an [issue](../../issues).
